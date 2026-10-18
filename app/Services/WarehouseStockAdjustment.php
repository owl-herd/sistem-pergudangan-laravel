<?php

namespace App\Services;

use PDO;
use Throwable;

class WarehouseStockAdjustment
{
    public function adjust(int $itemId, int $countedStock, string $reason): void
    {
        require_role('admin_pusat', 'admin_gudang');

        $actor = user();
        $pdo = db();
        $warehouseId = $this->warehouseId($pdo, $actor);

        $pdo->beginTransaction();

        try {
            $itemQuery = $pdo->prepare('SELECT id,kode,nama,satuan FROM barang WHERE id=? AND aktif=1 FOR UPDATE');
            $itemQuery->execute([$itemId]);
            $item = $itemQuery->fetch();
            if (! $item) {
                throw new \RuntimeException('Barang tidak ditemukan atau sudah nonaktif.');
            }

            $pdo->prepare('INSERT IGNORE INTO stok_gudang (gudang_id,barang_id,stok) VALUES (?,?,0)')
                ->execute([$warehouseId, $itemId]);
            $stockQuery = $pdo->prepare('SELECT stok FROM stok_gudang WHERE gudang_id=? AND barang_id=? FOR UPDATE');
            $stockQuery->execute([$warehouseId, $itemId]);
            $currentStock = $stockQuery->fetchColumn();
            if ($currentStock === false) {
                throw new \RuntimeException('Stok gudang tidak dapat dibaca.');
            }

            $currentStock = (int) $currentStock;
            $difference = $countedStock - $currentStock;
            if ($difference > 0) {
                $pdo->prepare('UPDATE stok_gudang SET stok=stok+? WHERE gudang_id=? AND barang_id=?')
                    ->execute([$difference, $warehouseId, $itemId]);
            } elseif ($difference < 0) {
                $updateQuery = $pdo->prepare('UPDATE stok_gudang SET stok=stok-? WHERE gudang_id=? AND barang_id=? AND stok>=?');
                $updateQuery->execute([abs($difference), $warehouseId, $itemId, abs($difference)]);
                if ($updateQuery->rowCount() !== 1) {
                    throw new \RuntimeException('Stok berubah saat penyesuaian. Muat ulang halaman lalu coba kembali.');
                }
            }

            $adjustmentQuery = $pdo->prepare('INSERT INTO stock_adjustments (gudang_id,barang_id,user_id,nama_petugas,stok_sebelum,stok_fisik,selisih,alasan) VALUES (?,?,?,?,?,?,?,?)');
            $adjustmentQuery->execute([
                $warehouseId,
                $itemId,
                $actor['id'],
                $actor['nama'],
                $currentStock,
                $countedStock,
                $difference,
                $reason,
            ]);
            $adjustmentId = (int) $pdo->lastInsertId();

            if ($difference !== 0) {
                $increase = $difference > 0;
                $reference = 'Stock opname #'.$adjustmentId;
                $movementQuery = $pdo->prepare('INSERT INTO transaksi_gudang
                    (barang_id,gudang_id,gudang_asal_id,gudang_tujuan_id,user_id,jenis,jumlah,referensi,tujuan_keluar,satuan_transaksi,nama_petugas,tanggal)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())');
                $movementQuery->execute([
                    $itemId,
                    $warehouseId,
                    $increase ? null : $warehouseId,
                    $increase ? $warehouseId : null,
                    $actor['id'],
                    $increase ? 'masuk' : 'keluar',
                    abs($difference),
                    $reference,
                    $increase ? null : 'internal',
                    $item['satuan'],
                    $actor['nama'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function warehouseId(PDO $pdo, array $actor): int
    {
        if ($actor['role'] === 'admin_pusat') {
            $query = $pdo->query("SELECT id FROM gudang WHERE kode='PUSAT' AND jenis='pusat' AND aktif=1 LIMIT 1");
            $warehouseId = (int) $query->fetchColumn();
        } else {
            $warehouseId = (int) ($actor['gudang_id'] ?? 0);
            $query = $pdo->prepare("SELECT id FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 LIMIT 1");
            $query->execute([$warehouseId]);
            if (! $query->fetchColumn()) {
                $warehouseId = 0;
            }
        }

        if ($warehouseId < 1) {
            abort(403, 'Gudang akun tidak tersedia untuk penyesuaian stok.');
        }

        return $warehouseId;
    }
}
