<?php

namespace Tests\Unit;

use App\Services\WarehouseQuestionAgent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WarehouseQuestionAgentTest extends TestCase
{
    public function test_agent_reads_static_guidance_before_returning_an_answer(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'tool_calls' => [[
                                'id' => 'call-guidance',
                                'type' => 'function',
                                'function' => ['name' => 'get_warehouse_guidance', 'arguments' => '{}'],
                            ]],
                        ],
                    ]],
                ])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Stok pusat berkurang saat barang dikirim.']]]]),
        ]);

        $answer = app(WarehouseQuestionAgent::class)->answer('Kapan stok pusat berkurang?');

        $this->assertSame('Stok pusat berkurang saat barang dikirim.', $answer);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && collect($request['messages'])->contains(fn (array $message): bool => ($message['role'] ?? null) === 'tool'
                    && ($message['tool_call_id'] ?? null) === 'call-guidance');
        });
    }

    public function test_agent_requires_an_api_key(): void
    {
        config(['services.openai.api_key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY belum dikonfigurasi.');

        app(WarehouseQuestionAgent::class)->answer('Bagaimana proses retur?');
    }
}
