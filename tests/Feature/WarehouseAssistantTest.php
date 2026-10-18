<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WarehouseAssistantTest extends TestCase
{
    private array $ids = [];

    protected function tearDown(): void
    {
        DB::table('users')->whereIn('id', $this->ids)->delete();
        parent::tearDown();
    }

    private function actor(): User
    {
        $id = DB::table('users')->insertGetId([
            'nama' => 'Asisten Uji',
            'email' => 'assistant-'.bin2hex(random_bytes(6)).'@example.test',
            'password_hash' => password_hash('InitialPass123', PASSWORD_DEFAULT),
            'role' => 'manager',
            'aktif' => 1,
        ]);
        $this->ids[] = $id;

        return User::findOrFail($id);
    }

    public function test_assistant_requires_login_and_limits_questions_to_guidance(): void
    {
        $this->get('/asisten.php')->assertRedirect('login.php');

        $actor = $this->actor();
        $this->actingAs($actor)
            ->get('/asisten.php')
            ->assertOk()
            ->assertSee('Asisten Pergudangan')
            ->assertSee('Jangan masukkan password');

        config(['services.openai.api_key' => null]);
        $this->withSession(['_token' => 'assistant-test-token'])
            ->post('/asisten.php', ['_token' => 'assistant-test-token', 'question' => 'Kapan stok pusat berkurang?'])
            ->assertRedirect()
            ->assertSessionHasErrors('assistant');
    }

    public function test_assistant_rejects_oversized_questions(): void
    {
        $this->actingAs($this->actor())
            ->withSession(['_token' => 'assistant-test-token'])
            ->post('/asisten.php', ['_token' => 'assistant-test-token', 'question' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('question');
    }

    public function test_development_agent_refuses_non_local_endpoints(): void
    {
        config(['services.development_agent.base_url' => 'https://example.test/v1']);

        $this->artisan('agent:dev')
            ->expectsOutput('Asisten development hanya boleh memakai layanan model di komputer ini.')
            ->assertExitCode(1);
    }
}
