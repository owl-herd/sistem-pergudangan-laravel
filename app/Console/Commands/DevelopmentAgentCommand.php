<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DevelopmentAgentCommand extends Command
{
    private const MAX_TOOL_STEPS = 8;

    private const MAX_FILE_BYTES = 100000;

    private const BLOCKED_PATHS = ['.git', 'vendor', 'storage', 'bootstrap/cache'];

    private const WRITABLE_EXTENSIONS = ['php', 'blade.php', 'js', 'css', 'md', 'json'];

    protected $signature = 'agent:dev';

    protected $description = 'Mulai asisten coding berbasis model lokal dengan persetujuan untuk perubahan dan tes';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Perintah ini memerlukan terminal interaktif untuk meminta persetujuan tindakan.');

            return self::FAILURE;
        }

        $baseUrl = rtrim((string) config('services.development_agent.base_url'), '/');
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->error('Asisten development hanya boleh memakai layanan model di komputer ini.');

            return self::FAILURE;
        }

        $this->info('Asisten development lokal siap. Ketik "keluar" untuk berhenti.');
        $this->warn('File sumber hanya dikirim ke model lokal yang dikonfigurasi.');

        while (true) {
            $goal = trim((string) $this->ask('Tugas'));
            if ($goal === 'keluar' || $goal === 'exit') {
                return self::SUCCESS;
            }
            if ($goal === '') {
                continue;
            }

            try {
                $this->runAgentTurn($goal);
            } catch (Throwable $exception) {
                report($exception);
                $this->error('Agent gagal: '.$exception->getMessage());
            }
        }
    }

    private function runAgentTurn(string $goal): void
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'Anda adalah asisten coding untuk proyek Laravel 12 ini. Gunakan alat yang tersedia untuk memeriksa source code sebelum menyimpulkan. Anda boleh membaca dan mencari kode. Sebelum menulis file atau menjalankan tes, jelaskan apa yang akan dilakukan; alat tersebut akan meminta persetujuan pengguna. Jangan membaca rahasia, .env, database.sql, vendor, atau storage/private. Jangan mengubah database maupun mengusulkan migrate:fresh/db:wipe. Jawab dalam bahasa Indonesia. Berhenti setelah tujuan terpenuhi atau jelaskan kendala.',
            ],
            ['role' => 'user', 'content' => $goal],
        ];

        for ($step = 0; $step < self::MAX_TOOL_STEPS; $step++) {
            $response = Http::acceptJson()
                ->timeout(120)
                ->post(rtrim((string) config('services.development_agent.base_url'), '/').'/chat/completions', [
                    'model' => config('services.development_agent.model'),
                    'messages' => $messages,
                    'tools' => $this->tools(),
                    'tool_choice' => 'auto',
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Model lokal mengembalikan HTTP '.$response->status().'.');
            }

            $message = $response->json('choices.0.message');
            if (! is_array($message)) {
                throw new RuntimeException('Respons model lokal tidak valid.');
            }

            $toolCalls = $message['tool_calls'] ?? [];
            if ($toolCalls === []) {
                $this->line((string) ($message['content'] ?? 'Agent selesai tanpa jawaban teks.'));

                return;
            }

            $messages[] = $message;
            foreach ($toolCalls as $toolCall) {
                $toolName = $toolCall['function']['name'] ?? '';
                $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                if (! is_array($arguments)) {
                    throw new RuntimeException('Argumen alat dari model tidak valid.');
                }

                $result = $this->executeTool($toolName, $arguments);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => $result,
                ];
            }
        }

        $this->warn('Batas delapan langkah alat tercapai. Tinjau hasil di atas, lalu berikan tugas lanjutan bila perlu.');
    }

    private function tools(): array
    {
        return [
            $this->tool('list_files', 'Daftar file kode project yang aman dibaca.', ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false]),
            $this->tool('read_file', 'Baca satu file kode project yang aman.', [
                'type' => 'object',
                'properties' => ['path' => ['type' => 'string']],
                'required' => ['path'],
                'additionalProperties' => false,
            ]),
            $this->tool('search_code', 'Cari teks literal pada file kode menggunakan ripgrep.', [
                'type' => 'object',
                'properties' => ['query' => ['type' => 'string']],
                'required' => ['query'],
                'additionalProperties' => false,
            ]),
            $this->tool('write_file', 'Buat atau ganti file kode; perubahan selalu meminta persetujuan pengguna.', [
                'type' => 'object',
                'properties' => ['path' => ['type' => 'string'], 'content' => ['type' => 'string']],
                'required' => ['path', 'content'],
                'additionalProperties' => false,
            ]),
            $this->tool('run_tests', 'Jalankan suite tes Laravel setelah persetujuan pengguna.', ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false]),
        ];
    }

    private function tool(string $name, string $description, array $parameters): array
    {
        return ['type' => 'function', 'function' => ['name' => $name, 'description' => $description, 'parameters' => $parameters]];
    }

    private function executeTool(string $name, array $arguments): string
    {
        return match ($name) {
            'list_files' => $this->listFiles(),
            'read_file' => $this->readFile((string) ($arguments['path'] ?? '')),
            'search_code' => $this->searchCode((string) ($arguments['query'] ?? '')),
            'write_file' => $this->writeFile((string) ($arguments['path'] ?? ''), (string) ($arguments['content'] ?? '')),
            'run_tests' => $this->runTests(),
            default => throw new RuntimeException('Alat tidak dikenal: '.$name),
        };
    }

    private function listFiles(): string
    {
        $files = [];
        $directory = new \RecursiveDirectoryIterator(base_path(), \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator($directory, function ($current): bool {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($current->getPathname(), strlen(base_path()) + 1));

            return ! $this->isBlockedPath($relative);
        });
        $iterator = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($iterator as $file) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(base_path()) + 1));
            if ($file->isFile() && preg_match('/\.(php|blade\.php|js|css|md|json)$/i', $relative)) {
                $files[] = $relative;
                if (count($files) >= 250) {
                    break;
                }
            }
        }

        return implode("\n", $files);
    }

    private function readFile(string $path): string
    {
        $resolved = $this->safeExistingPath($path);
        if (! preg_match('/\.(php|blade\.php|js|css|md|json)$/i', $resolved)) {
            throw new RuntimeException('Jenis file ini tidak diizinkan untuk dibaca.');
        }

        $contents = File::get($resolved);

        return strlen($contents) > self::MAX_FILE_BYTES
            ? substr($contents, 0, self::MAX_FILE_BYTES)."\n[File dipotong pada batas 100 KB]"
            : $contents;
    }

    private function searchCode(string $query): string
    {
        if ($query === '' || strlen($query) > 500) {
            throw new RuntimeException('Teks pencarian harus berisi 1–500 karakter.');
        }

        $process = new Process([
            'rg', '--line-number', '--fixed-strings', '--glob', '!vendor/**', '--glob', '!storage/**',
            '--glob', '!.git/**', '--glob', '!database.sql', '--glob', '!.env*', '--', $query, '.',
        ], base_path());
        $process->setTimeout(30);
        $process->run();
        if (! $process->isSuccessful() && $process->getExitCode() !== 1) {
            throw new RuntimeException('Pencarian kode gagal: '.trim($process->getErrorOutput()));
        }

        return substr($process->getOutput(), 0, 12000);
    }

    private function writeFile(string $path, string $content): string
    {
        if (strlen($content) > self::MAX_FILE_BYTES) {
            throw new RuntimeException('Perubahan file melebihi batas 100 KB.');
        }

        $target = $this->safeWritablePath($path);
        $this->warn('Agent meminta '.(File::exists($target) ? 'mengganti' : 'membuat').' file: '.$path);
        $this->line('Ukuran konten: '.strlen($content).' byte.');
        $this->line($content);
        if (! $this->confirm('Terapkan perubahan ini?', false)) {
            return 'Pengguna menolak perubahan file.';
        }

        File::put($target, $content);

        return 'File berhasil diperbarui setelah persetujuan pengguna.';
    }

    private function runTests(): string
    {
        $this->warn('Perintah yang akan dijalankan: php artisan test --compact');
        if (! $this->confirm('Jalankan tes Laravel pada database pengujian?', false)) {
            return 'Pengguna menolak menjalankan tes.';
        }

        $process = new Process([PHP_BINARY, 'artisan', 'test', '--compact'], base_path());
        $process->setTimeout(300);
        $process->run();
        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        return ($process->isSuccessful() ? 'TES BERHASIL' : 'TES GAGAL')."\n".substr($output, 0, 12000);
    }

    private function safeExistingPath(string $path): string
    {
        $path = $this->normalizedPath($path);
        $resolved = realpath(base_path($path));
        if ($resolved === false || ! str_starts_with(strtolower($resolved), strtolower(base_path().DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('File tidak ditemukan di dalam project.');
        }
        if (! $this->isBlockedPath($path) && is_file($resolved)) {
            return $resolved;
        }

        throw new RuntimeException('Akses ke file ini tidak diizinkan.');
    }

    private function safeWritablePath(string $path): string
    {
        $path = $this->normalizedPath($path);
        $parent = realpath(dirname(base_path($path)));
        if ($parent === false || ! str_starts_with(strtolower($parent.DIRECTORY_SEPARATOR), strtolower(base_path().DIRECTORY_SEPARATOR)) || $this->isBlockedPath($path)) {
            throw new RuntimeException('Lokasi file tidak diizinkan.');
        }

        $filename = basename($path);
        if ($filename === '.env' || str_starts_with($filename, '.env.') || $filename === 'database.sql') {
            throw new RuntimeException('File konfigurasi rahasia dan database tidak boleh diubah oleh agent.');
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($extension, ['php', 'js', 'css', 'md', 'json'], true)) {
            throw new RuntimeException('Ekstensi file ini tidak diizinkan untuk diubah.');
        }

        $target = $parent.DIRECTORY_SEPARATOR.$filename;
        if (is_link($target)) {
            throw new RuntimeException('File symbolic link tidak boleh diubah oleh agent.');
        }

        return $target;
    }

    private function normalizedPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) || in_array('..', explode('/', $path), true)) {
            throw new RuntimeException('Path harus relatif dan tetap berada di dalam project.');
        }

        return $path;
    }

    private function isBlockedPath(string $path): bool
    {
        $path = strtolower(trim(str_replace('\\', '/', $path), '/'));
        if ($path === '.env' || str_starts_with($path, '.env/') || str_starts_with($path, '.env.')) {
            return true;
        }

        foreach (self::BLOCKED_PATHS as $blocked) {
            if ($path === $blocked || str_starts_with($path, $blocked.'/')) {
                return true;
            }
        }

        return $path === 'database.sql' || str_starts_with($path, '../');
    }
}
