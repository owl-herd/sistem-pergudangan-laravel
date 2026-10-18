<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WarehouseNotifications;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WarehouseNotificationTest extends TestCase
{
    private int $notificationBaseline = 0;

    private array $users = [];

    private array $warehouses = [];

    private array $items = [];

    private array $requests = [];

    private array $purchases = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
        $this->notificationBaseline = (int) DB::table('warehouse_notifications')->max('id');
    }

    protected function tearDown(): void
    {
        DB::table('warehouse_notifications')->where('id', '>', $this->notificationBaseline)->delete();
        if ($this->requests) {
            $shipments = DB::table('pengiriman')->whereIn('permintaan_id', $this->requests)->pluck('id');
            DB::table('transaksi_gudang')->whereIn('permintaan_id', $this->requests)->delete();
            DB::table('detail_pengiriman')->whereIn('pengiriman_id', $shipments)->delete();
            DB::table('pengiriman')->whereIn('id', $shipments)->delete();
            DB::table('detail_permintaan')->whereIn('permintaan_id', $this->requests)->delete();
            DB::table('permintaan')->whereIn('id', $this->requests)->delete();
        }
        DB::table('permintaan_pembelian')->whereIn('id', $this->purchases)->delete();
        DB::table('stok_gudang')->whereIn('barang_id', $this->items)->delete();
        DB::table('barang')->whereIn('id', $this->items)->delete();
        DB::table('users')->whereIn('id', $this->users)->delete();
        DB::table('gudang')->whereIn('id', $this->warehouses)->delete();
        parent::tearDown();
    }

    private function actor(string $role, ?int $warehouse = null, bool $active = true): User
    {
        $id = DB::table('users')->insertGetId(['nama' => 'Notif '.$role, 'email' => 'notif-'.bin2hex(random_bytes(6)).'@example.test', 'password_hash' => password_hash('TestPass123', PASSWORD_DEFAULT), 'role' => $role, 'gudang_id' => $warehouse, 'aktif' => (int) $active]);
        $this->users[] = $id;

        return User::findOrFail($id);
    }

    private function warehouse(): int
    {
        $id = DB::table('gudang')->insertGetId(['kode' => 'N-'.bin2hex(random_bytes(4)), 'nama' => 'Proyek notifikasi', 'jenis' => 'proyek', 'aktif' => 1]);
        $this->warehouses[] = $id;

        return $id;
    }

    private function item(): int
    {
        $id = DB::table('barang')->insertGetId(['kode' => 'N-'.bin2hex(random_bytes(5)), 'nama' => 'Barang notifikasi', 'kategori' => 'Uji', 'kategori_barang' => 'barang_siap', 'satuan' => 'unit']);
        $this->items[] = $id;

        return $id;
    }

    private function submit(User $actor, string $url, array $data)
    {
        $token = bin2hex(random_bytes(32));

        return $this->actingAs($actor)->withSession(['_token' => $token])->post($url, $data + ['csrf' => $token]);
    }

    public function test_approval_notifies_only_active_admins_of_the_requesting_warehouse(): void
    {
        $warehouse = $this->warehouse();
        $project = $this->actor('admin_gudang', $warehouse);
        $peer = $this->actor('admin_gudang', $warehouse);
        $other = $this->actor('admin_gudang', $this->warehouse());
        $inactive = $this->actor('admin_gudang', $warehouse, false);
        $center = $this->actor('admin_pusat');
        $purchasing = $this->actor('purchasing');
        $item = $this->item();
        foreach ([$project, $center] as $requester) {
            $id = DB::table('permintaan_pembelian')->insertGetId(['user_id' => $requester->id, 'barang_id' => $item, 'jumlah' => 3, 'status' => 'menunggu']);
            $this->purchases[] = $id;
            $this->submit($purchasing, '/aksi_pembelian.php', ['aksi' => 'permintaan_setujui', 'permintaan_id' => (string) $id])->assertRedirect();
            $recipients = DB::table('warehouse_notifications')->where('event_key', 'purchase-approved:'.$id)->pluck('recipient_id')->map(fn ($v) => (int) $v)->all();
            $this->assertContains($requester->id, $recipients);
            if ($requester->id === $project->id) {
                $this->assertContains($peer->id, $recipients);
                foreach ([$other, $inactive, $center] as $excluded) {
                    $this->assertNotContains($excluded->id, $recipients);
                }
            } else {
                $this->assertNotContains($project->id, $recipients);
            }
            $count = count($recipients);
            $this->submit($purchasing, '/aksi_pembelian.php', ['aksi' => 'permintaan_setujui', 'permintaan_id' => (string) $id])->assertRedirect();
            $this->assertSame($count, DB::table('warehouse_notifications')->where('event_key', 'purchase-approved:'.$id)->count());
        }
    }

    public function test_request_shipping_and_arrival_notify_the_correct_accounts(): void
    {
        $warehouse = $this->warehouse();
        $project = $this->actor('admin_gudang', $warehouse);
        $other = $this->actor('admin_gudang', $this->warehouse());
        $center = $this->actor('admin_pusat');
        $item = $this->item();
        $centerId = (int) DB::table('gudang')->where('kode', 'PUSAT')->value('id');
        DB::table('stok_gudang')->insert(['gudang_id' => $centerId, 'barang_id' => $item, 'stok' => 10]);
        $this->submit($project, '/aksi_admin.php', ['aksi' => 'permintaan_simpan', 'barang_id' => (string) $item, 'jumlah' => '3'])->assertRedirect();
        $id = (int) DB::table('permintaan')->where('user_id', $project->id)->value('id');
        $this->requests[] = $id;
        $this->assertTrue(DB::table('warehouse_notifications')->where('event_key', 'project-request:'.$id)->where('recipient_id', $center->id)->exists());
        $this->submit($center, '/aksi_admin.php', ['aksi' => 'permintaan_kirim', 'permintaan_id' => (string) $id, 'nomor_kendaraan' => 'B 1234 AB', 'nama_driver' => 'Sopir uji'])->assertRedirect();
        $shipment = (int) DB::table('pengiriman')->where('permintaan_id', $id)->value('id');
        $this->assertTrue(DB::table('warehouse_notifications')->where('event_key', 'shipment-sent:'.$shipment)->where('recipient_id', $project->id)->exists());
        $this->assertFalse(DB::table('warehouse_notifications')->where('event_key', 'shipment-sent:'.$shipment)->where('recipient_id', $other->id)->exists());
        $detail = (int) DB::table('detail_pengiriman')->where('pengiriman_id', $shipment)->value('id');
        $data = ['aksi' => 'penerimaan_konfirmasi', 'pengiriman_id' => (string) $shipment, 'jumlah_diterima' => [(string) $detail => '2']];
        $this->submit($project, '/aksi_admin.php', $data)->assertRedirect();
        $this->assertTrue(DB::table('warehouse_notifications')->where('event_key', 'shipment-received:'.$shipment)->where('recipient_id', $center->id)->where('message', 'like', '%selisih%')->exists());
        $count = DB::table('warehouse_notifications')->where('event_key', 'shipment-received:'.$shipment)->count();
        $this->submit($project, '/aksi_admin.php', $data)->assertRedirect();
        $this->assertSame($count, DB::table('warehouse_notifications')->where('event_key', 'shipment-received:'.$shipment)->count());
        $this->assertSame(2, (int) DB::table('stok_gudang')->where('gudang_id', $warehouse)->where('barang_id', $item)->value('stok'));
    }

    public function test_read_state_is_owned_by_each_account(): void
    {
        $a = $this->actor('admin_pusat');
        $b = $this->actor('admin_pusat');
        $key = 'test:'.bin2hex(random_bytes(5));
        app(WarehouseNotifications::class)->notify(db(), 'admin_pusat', null, $key, 'Pesan uji', 'dashboard.php#permintaan');
        try {
            $id = (int) DB::table('warehouse_notifications')->where('recipient_id', $a->id)->where('event_key', $key)->value('id');
            $this->submit($b, '/notifikasi.php/'.$id.'/baca', [])->assertNotFound();
            $this->submit($a, '/notifikasi.php/'.$id.'/baca', [])->assertRedirect('dashboard.php#notifikasi');
            $this->assertNotNull(DB::table('warehouse_notifications')->where('id', $id)->value('read_at'));
            $this->assertNull(DB::table('warehouse_notifications')->where('recipient_id', $b->id)->where('event_key', $key)->value('read_at'));
        } finally {
            DB::table('warehouse_notifications')->where('event_key', $key)->delete();
        }
    }

    public function test_manager_gets_one_reminder_per_week_and_separate_reports(): void
    {
        $manager = $this->actor('manager');
        $service = app(WarehouseNotifications::class);
        $this->travelTo(now()->setDate(2026, 10, 12)->startOfDay());
        $service->weekly(db());
        $service->weekly(db());
        $this->assertSame(1, DB::table('warehouse_notifications')->where('recipient_id', $manager->id)->count());
        $this->travel(7)->days();
        $service->weekly(db());
        $this->assertSame(2, DB::table('warehouse_notifications')->where('recipient_id', $manager->id)->count());
        $this->travelBack();
        $this->actingAs($manager)->get('/dashboard.php')->assertOk()->assertSee('Pilihan laporan')->assertSee('Notifikasi');
        foreach (['stok', 'permintaan', 'pengiriman', 'pembelian', 'penerimaan_pembelian', 'penambahan', 'retur', 'mutasi'] as $type) {
            $response = $this->get('/laporan_pdf.php?jenis_laporan='.$type.'&tampilan=lihat');
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString('%PDF-1.4',$response->getContent());
            if ($type === 'stok') {
                $this->assertStringNotContainsString('Pengiriman, penerimaan, dan barang dalam perjalanan',$response->getContent());
            }
        }
        $this->get('/laporan_pdf.php?jenis_laporan=invalid')->assertStatus(422);
        $this->actingAs($this->actor('admin_pusat'))->get('/laporan_pdf.php?jenis_laporan=stok')->assertForbidden();
    }
}
