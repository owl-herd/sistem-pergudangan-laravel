<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchasingNotificationTest extends TestCase
{
    private array $userIds = [];

    private array $warehouseIds = [];

    private array $itemIds = [];

    private array $requestIds = [];

    protected function tearDown(): void
    {
        if ($this->requestIds !== []) {
            DB::table('purchasing_notifications')->whereIn('permintaan_pembelian_id', $this->requestIds)->delete();
            DB::table('permintaan_pembelian')->whereIn('id', $this->requestIds)->delete();
        }
        if ($this->itemIds !== []) {
            DB::table('barang')->whereIn('id', $this->itemIds)->delete();
        }
        if ($this->userIds !== []) {
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        if ($this->warehouseIds !== []) {
            DB::table('gudang')->whereIn('id', $this->warehouseIds)->delete();
        }

        parent::tearDown();
    }

    private function user(string $role, ?int $warehouseId = null, bool $active = true): User
    {
        $id = DB::table('users')->insertGetId([
            'nama' => 'Notifikasi '.$role,
            'email' => 'notification-'.bin2hex(random_bytes(6)).'@example.test',
            'password_hash' => password_hash('InitialPass123', PASSWORD_DEFAULT),
            'role' => $role,
            'gudang_id' => $warehouseId,
            'aktif' => $active ? 1 : 0,
        ]);
        $this->userIds[] = $id;

        return User::findOrFail($id);
    }

    private function item(): int
    {
        $id = DB::table('barang')->insertGetId([
            'kode' => 'NOTIF-'.bin2hex(random_bytes(5)),
            'nama' => 'Barang uji notifikasi',
            'kategori' => 'Pengujian',
            'kategori_barang' => 'barang_siap',
            'satuan' => 'unit',
        ]);
        $this->itemIds[] = $id;

        return $id;
    }

    private function projectWarehouse(): int
    {
        $id = DB::table('gudang')->insertGetId([
            'kode' => 'NOTIF-'.bin2hex(random_bytes(4)),
            'nama' => 'Gudang uji notifikasi',
            'jenis' => 'proyek',
            'aktif' => 1,
        ]);
        $this->warehouseIds[] = $id;

        return $id;
    }

    private function submitRequest(User $actor, int $itemId): int
    {
        $token = bin2hex(random_bytes(32));
        $this->actingAs($actor)
            ->withSession(['_token' => $token])
            ->post('/aksi_pembelian.php', [
                'csrf' => $token,
                'aksi' => 'permintaan_simpan',
                'barang_id' => (string) $itemId,
                'jumlah' => '3',
                'catatan' => 'Permintaan uji notifikasi',
            ])
            ->assertRedirect();

        $id = (int) DB::table('permintaan_pembelian')
            ->where('user_id', $actor->id)
            ->orderByDesc('id')
            ->value('id');
        $this->requestIds[] = $id;

        return $id;
    }

    public function test_center_and_project_purchase_requests_notify_each_active_purchasing_account(): void
    {
        $itemId = $this->item();
        $center = $this->user('admin_pusat');
        $project = $this->user('admin_gudang', $this->projectWarehouse());
        $firstPurchasing = $this->user('purchasing');
        $secondPurchasing = $this->user('purchasing');
        $inactivePurchasing = $this->user('purchasing', null, false);

        $centerRequestId = $this->submitRequest($center, $itemId);
        $projectRequestId = $this->submitRequest($project, $itemId);

        foreach ([$firstPurchasing, $secondPurchasing] as $purchasing) {
            $notifications = DB::table('purchasing_notifications')
                ->where('recipient_id', $purchasing->id)
                ->whereIn('permintaan_pembelian_id', [$centerRequestId, $projectRequestId])
                ->pluck('permintaan_pembelian_id')
                ->all();
            $this->assertEqualsCanonicalizing([$centerRequestId, $projectRequestId], array_map('intval', $notifications));
        }
        $this->assertSame(0, DB::table('purchasing_notifications')->where('recipient_id', $inactivePurchasing->id)->count());

        $this->actingAs($firstPurchasing)
            ->get('/purchasing.php')
            ->assertOk()
            ->assertSee('Permintaan pembelian belum dibaca')
            ->assertSee('Gudang Pusat')
            ->assertSee('Gudang Proyek');
    }

    public function test_read_state_is_independent_for_each_purchasing_account(): void
    {
        $itemId = $this->item();
        $requester = $this->user('admin_pusat');
        $firstPurchasing = $this->user('purchasing');
        $secondPurchasing = $this->user('purchasing');
        $requestId = $this->submitRequest($requester, $itemId);
        $firstNotificationId = (int) DB::table('purchasing_notifications')
            ->where('recipient_id', $firstPurchasing->id)
            ->where('permintaan_pembelian_id', $requestId)
            ->value('id');

        $token = bin2hex(random_bytes(32));
        $this->actingAs($firstPurchasing)
            ->withSession(['_token' => $token])
            ->post('/notifikasi_pembelian.php/'.$firstNotificationId.'/baca', ['csrf' => $token])
            ->assertRedirect('purchasing.php#notifikasi');

        $this->assertNotNull(DB::table('purchasing_notifications')->where('id', $firstNotificationId)->value('read_at'));
        $this->assertNull(DB::table('purchasing_notifications')
            ->where('recipient_id', $secondPurchasing->id)
            ->where('permintaan_pembelian_id', $requestId)
            ->value('read_at'));

        $this->actingAs($secondPurchasing)
            ->post('/notifikasi_pembelian.php/'.$firstNotificationId.'/baca', ['csrf' => $token])
            ->assertNotFound();
    }
}
