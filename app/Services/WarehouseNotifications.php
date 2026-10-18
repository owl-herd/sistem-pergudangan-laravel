<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use PDO;

class WarehouseNotifications
{
    public function notify(PDO $pdo, string $role, ?int $warehouseId, string $key, string $message, string $url): void
    {
        $query = $pdo->prepare('INSERT IGNORE INTO warehouse_notifications (recipient_id,event_key,message,url) SELECT id,?,?,? FROM users WHERE aktif=1 AND role=? AND '.($role === 'manager' ? '1=1' : ($warehouseId === null ? 'gudang_id IS NULL' : 'gudang_id=?')));
        $parameters = [$key, $message, $url, $role];
        if ($warehouseId !== null) {
            $parameters[] = $warehouseId;
        } $query->execute($parameters);
    }

    public function purchaseApproved(PDO $pdo, int $requestId, int $requesterId): void
    {
        $query = $pdo->prepare('SELECT role,gudang_id FROM users WHERE id=?');
        $query->execute([$requesterId]);
        $owner = $query->fetch();
        if ($owner && in_array($owner['role'], ['admin_pusat', 'admin_gudang'], true)) {
            $this->notify($pdo, $owner['role'], $owner['gudang_id'] === null ? null : (int) $owner['gudang_id'], 'purchase-approved:'.$requestId, 'Pembelian #'.$requestId.' disetujui oleh Purchasing.', 'penerimaan_pembelian.php#pembelian-'.$requestId);
        }
    }

    public function weekly(PDO $pdo): void
    {
        $week = CarbonImmutable::now(config('app.timezone'))->startOfWeek();
        $start = $week->subWeek()->toDateString();
        $end = $week->subDay()->toDateString();
        $this->notify($pdo, 'manager', null, 'weekly-report:'.$week->toDateString(), 'Laporan mingguan '.$start.' sampai '.$end.' tersedia. Pilih laporan yang ingin dilihat.', 'dashboard.php?'.http_build_query(['tanggal_mulai' => $start, 'tanggal_akhir' => $end]).'#laporan');
    }

    public function inbox(PDO $pdo, int $userId): array
    {
        $query = $pdo->prepare('SELECT COUNT(*) FROM warehouse_notifications WHERE recipient_id=? AND read_at IS NULL');
        $query->execute([$userId]);
        $count = (int) $query->fetchColumn();
        $query = $pdo->prepare('SELECT * FROM warehouse_notifications WHERE recipient_id=? AND read_at IS NULL ORDER BY created_at DESC,id DESC LIMIT 20');
        $query->execute([$userId]);

        return ['count' => $count, 'items' => $query->fetchAll()];
    }
}
