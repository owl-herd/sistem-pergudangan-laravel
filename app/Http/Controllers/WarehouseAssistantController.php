<?php

namespace App\Http\Controllers;

use App\Services\AccessService;
use App\Services\WarehouseQuestionAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WarehouseAssistantController extends Controller
{
    public function show(AccessService $access)
    {
        $access->ensure(['super_admin', 'admin_pusat', 'admin_gudang', 'manager', 'purchasing']);

        return view('warehouse.assistant');
    }

    public function ask(Request $request, AccessService $access, WarehouseQuestionAgent $agent)
    {
        $access->ensure(['super_admin', 'admin_pusat', 'admin_gudang', 'manager', 'purchasing']);
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        try {
            return back()->with('assistant_answer', $agent->answer($validated['question']));
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'OPENAI_API_KEY belum dikonfigurasi.') {
                return back()->withErrors(['assistant' => 'Asisten belum dikonfigurasi. Administrator perlu mengatur OPENAI_API_KEY.']);
            }

            Log::warning('Warehouse AI assistant request failed.', ['message' => $exception->getMessage()]);

            return back()->withErrors(['assistant' => 'Asisten AI sedang tidak tersedia. Coba lagi beberapa saat lagi.']);
        } catch (Throwable $exception) {
            Log::error('Unexpected warehouse AI assistant failure.', ['exception' => $exception]);

            return back()->withErrors(['assistant' => 'Terjadi kesalahan saat menghubungi asisten AI.']);
        }
    }
}
