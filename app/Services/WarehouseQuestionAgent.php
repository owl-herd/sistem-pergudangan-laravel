<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WarehouseQuestionAgent
{
    private const MAX_TURNS = 4;

    private const WAREHOUSE_GUIDANCE = <<<'GUIDANCE'
Alur dan aturan pergudangan aplikasi:
- Admin proyek mengajukan barang; Admin Pusat mengisi kendaraan dan sopir lalu mengirim.
- Stok pusat berkurang saat pengiriman dan stok proyek bertambah saat penerimaan.
- Surat jalan mencakup semua detail dan tidak mengubah stok.
- Purchasing menyetujui pembelian tanpa mutasi stok; penerimaan hanya menambah jumlah barang yang layak.
- Barang rusak atau tidak sesuai wajib memiliki catatan dan foto.
- Retur peralatan mengurangi stok proyek saat dikirim dan menambah stok pusat setelah diterima.
- Manager dapat membaca data lintas gudang aktif.
Jawab dalam bahasa Indonesia, berdasarkan aturan di atas. Jangan mengarang angka stok, data pengguna, atau keadaan terkini karena agent ini tidak mengakses database. Jangan meminta atau mengulang data rahasia.
GUIDANCE;

    public function answer(string $question): string
    {
        $apiKey = (string) config('services.openai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY belum dikonfigurasi.');
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'Anda adalah asisten tanya-jawab pergudangan yang hanya boleh menjelaskan proses, bukan melakukan aksi atau mengubah data. Panggil alat get_warehouse_guidance sebelum menjawab. '.self::WAREHOUSE_GUIDANCE,
            ],
            ['role' => 'user', 'content' => $question],
        ];

        for ($turn = 0; $turn < self::MAX_TURNS; $turn++) {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model'),
                    'messages' => $messages,
                    'tools' => [[
                        'type' => 'function',
                        'function' => [
                            'name' => 'get_warehouse_guidance',
                            'description' => 'Membaca aturan bisnis pergudangan statis yang disediakan aplikasi.',
                            'parameters' => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
                        ],
                    ]],
                    'tool_choice' => $turn === 0 ? 'required' : 'auto',
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Layanan AI tidak dapat memproses pertanyaan (HTTP '.$response->status().').');
            }

            $message = $response->json('choices.0.message');
            if (! is_array($message)) {
                throw new RuntimeException('Layanan AI mengembalikan respons yang tidak valid.');
            }

            $toolCalls = $message['tool_calls'] ?? [];
            if ($toolCalls === []) {
                $answer = $message['content'] ?? null;
                if (! is_string($answer) || trim($answer) === '') {
                    throw new RuntimeException('Layanan AI tidak memberikan jawaban.');
                }

                return trim($answer);
            }

            $messages[] = $message;
            foreach ($toolCalls as $toolCall) {
                if (($toolCall['function']['name'] ?? null) !== 'get_warehouse_guidance') {
                    throw new RuntimeException('Layanan AI meminta alat yang tidak diizinkan.');
                }
                if (! is_string($toolCall['id'] ?? null)) {
                    throw new RuntimeException('Layanan AI mengembalikan identitas alat yang tidak valid.');
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => self::WAREHOUSE_GUIDANCE,
                ];
            }
        }

        throw new RuntimeException('Agent mencapai batas langkah tanpa menghasilkan jawaban.');
    }
}
