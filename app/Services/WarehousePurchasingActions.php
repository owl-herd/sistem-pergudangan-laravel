<?php

namespace App\Services;

use Illuminate\Http\Exceptions\HttpResponseException;
use PDOException;
use RuntimeException;
use Throwable;

class WarehousePurchasingActions
{
    public function aksi_pembelian()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];
        if ($server['REQUEST_METHOD'] !== 'POST') {
            if (user() && in_array(user()['role'] ?? '', ['admin_pusat', 'admin_gudang', 'manager'], true)) {
                go('dashboard.php');
            }
            go('purchasing.php');
        }
        check_csrf();
        require_role('admin_pusat', 'admin_gudang', 'purchasing');
        $pdo = db();
        $action = is_string($post['aksi'] ?? null) ? $post['aksi'] : '';
        $positiveInt = static function (mixed $value): int {
            return is_string($value) && ctype_digit($value) ? (int) $value : 0;
        };
        $text = static fn (string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
        $textLength = static function (string $value): int {
            $length = preg_match_all('/./us', $value);

            return $length === false ? PHP_INT_MAX : $length;
        };
        $returnUrl = user()['role'] === 'purchasing' ? 'purchasing.php#permintaan' : 'dashboard.php#permintaan-pembelian';

        try {
            if ($action === 'permintaan_simpan') {
                require_role('admin_pusat', 'admin_gudang');
                $itemId = $positiveInt($post['barang_id'] ?? null);
                $quantity = $positiveInt($post['jumlah'] ?? null);
                $note = $text('catatan');
                if ($itemId < 1 || $quantity < 1 || $quantity > 4294967295 || $textLength($note) > 2000) {
                    throw new RuntimeException('Barang, jumlah, atau catatan permintaan tidak valid.');
                }
                $pdo->beginTransaction();
                $itemQuery = $pdo->prepare('SELECT id FROM barang WHERE id=? AND aktif=1 FOR UPDATE');
                $itemQuery->execute([$itemId]);
                if (! $itemQuery->fetchColumn()) {
                    throw new RuntimeException('Barang tidak ditemukan atau sudah nonaktif.');
                }
                $pdo->prepare("INSERT INTO permintaan_pembelian (user_id,barang_id,jumlah,catatan,status) VALUES (?,?,?,?,'menunggu')")
                    ->execute([user()['id'], $itemId, $quantity, $note ?: null]);
                $requestId = (int) $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO purchasing_notifications (recipient_id,permintaan_pembelian_id) SELECT id,? FROM users WHERE role='purchasing' AND aktif=1")
                    ->execute([$requestId]);
                $pdo->commit();
                flash('Permintaan pembelian dikirim kepada Purchasing untuk diproses.');
            } elseif ($action === 'permintaan_setujui' || $action === 'permintaan_tolak') {
                require_role('purchasing');
                $requestId = $positiveInt($post['permintaan_id'] ?? null);
                if ($requestId < 1) {
                    throw new RuntimeException('Permintaan pembelian tidak ditemukan.');
                }
                $status = $action === 'permintaan_setujui' ? 'disetujui' : 'ditolak';
                $pdo->beginTransaction();
                $requestQuery = $pdo->prepare('SELECT id,user_id,barang_id,status FROM permintaan_pembelian WHERE id=? FOR UPDATE');
                $requestQuery->execute([$requestId]);
                $request = $requestQuery->fetch();
                if (! $request || $request['status'] !== 'menunggu') {
                    throw new RuntimeException('Permintaan sudah diproses atau tidak ditemukan.');
                }
                if ((int) $request['user_id'] === (int) user()['id']) {
                    throw new RuntimeException('Anda tidak dapat memutuskan permintaan pembelian sendiri.');
                }
                if ($status === 'disetujui') {
                    $itemQuery = $pdo->prepare('SELECT id FROM barang WHERE id=? AND aktif=1 FOR UPDATE');
                    $itemQuery->execute([(int) $request['barang_id']]);
                    if (! $itemQuery->fetchColumn()) {
                        throw new RuntimeException('Barang sudah nonaktif dan tidak dapat disetujui.');
                    }
                }
                $update = $pdo->prepare("UPDATE permintaan_pembelian SET status=?,diproses_oleh=?,diproses_pada=NOW() WHERE id=? AND status='menunggu' AND user_id<>?");
                $update->execute([$status, user()['id'], $requestId, user()['id']]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Permintaan berubah atau sudah diproses. Muat ulang halaman.');
                }
                if ($status === 'disetujui') {
                    app(WarehouseNotifications::class)->purchaseApproved($pdo, $requestId, (int) $request['user_id']);
                }
                $pdo->commit();
                flash($status === 'disetujui' ? 'Permintaan pembelian disetujui oleh Purchasing. Stok tidak berubah.' : 'Permintaan pembelian ditolak oleh Purchasing. Stok tidak berubah.');
            } else {
                throw new RuntimeException('Aksi permintaan pembelian tidak dikenal.');
            }
        } catch (HttpResponseException $error) {
            throw $error;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $error instanceof PDOException ? 'Perubahan gagal disimpan. Periksa data dan struktur database.' : $error->getMessage();
            flash($message, 'error');
        }
        go($returnUrl);

    }
}
