<?php

namespace App\Http\Controllers;

use App\Services\WarehouseStockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WarehouseStockAdjustmentController extends Controller
{
    public function store(Request $request, WarehouseStockAdjustment $adjustment): RedirectResponse
    {
        check_csrf();

        $validated = $request->validate([
            'barang_id' => ['required', 'integer', 'min:1'],
            'stok_fisik' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'alasan' => ['required', 'string', 'max:1000', static function (string $attribute, mixed $value, \Closure $fail): void {
                if (trim((string) $value) === '') {
                    $fail('Alasan stock opname wajib diisi.');
                }
            }],
        ]);

        $adjustment->adjust(
            (int) $validated['barang_id'],
            (int) $validated['stok_fisik'],
            trim($validated['alasan'])
        );

        flash('Hasil stock opname disimpan. Selisih stok dan aktivitasnya tercatat.');

        return redirect('dashboard.php#stok');
    }
}
