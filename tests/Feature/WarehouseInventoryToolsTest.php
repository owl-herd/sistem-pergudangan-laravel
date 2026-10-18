<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarehouseInventoryToolsTest extends TestCase
{
    private array $users = [];

    private array $warehouses = [];

    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
        if (! Schema::hasTable('stock_adjustments') || ! Schema::hasTable('warehouse_activity_logs')) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_10_09_000001_create_warehouse_activity_tables.php',
                '--force' => true,
            ])->assertExitCode(0);
        }
        $this->assertTrue(Schema::hasTable('stock_adjustments'));
        $this->assertTrue(Schema::hasTable('warehouse_activity_logs'));
    }

    protected function tearDown(): void
    {
        DB::table('stock_adjustments')->whereIn('barang_id', $this->items)->delete();
        DB::table('warehouse_activity_logs')->whereIn('subject_id', $this->items)->delete();
        DB::table('transaksi_gudang')->whereIn('barang_id', $this->items)->delete();
        DB::table('stok_gudang')->whereIn('barang_id', $this->items)->delete();
        DB::table('barang')->whereIn('id', $this->items)->delete();
        DB::table('users')->whereIn('id', $this->users)->delete();
        DB::table('gudang')->whereIn('id', $this->warehouses)->delete();
        parent::tearDown();
    }

    private function actor(string $role, ?int $warehouse = null): User
    {
        $id = DB::table('users')->insertGetId([
            'nama' => 'Audit '.$role,
            'email' => 'audit-'.bin2hex(random_bytes(6)).'@example.test',
            'password_hash' => password_hash('TestPass123', PASSWORD_DEFAULT),
            'role' => $role,
            'gudang_id' => $warehouse,
            'aktif' => 1,
        ]);
        $this->users[] = $id;

        return User::findOrFail($id);
    }

    private function warehouse(): int
    {
        $id = DB::table('gudang')->insertGetId([
            'kode' => 'T-'.bin2hex(random_bytes(4)),
            'nama' => 'Gudang uji stocktake',
            'jenis' => 'proyek',
            'aktif' => 1,
        ]);
        $this->warehouses[] = $id;

        return $id;
    }

    private function item(string $name = 'Barang uji stocktake'): int
    {
        $id = DB::table('barang')->insertGetId([
            'kode' => 'T-'.bin2hex(random_bytes(5)),
            'nama' => $name,
            'kategori' => 'Uji',
            'kategori_barang' => 'barang_siap',
            'satuan' => 'unit',
        ]);
        $this->items[] = $id;

        return $id;
    }

    private function submit(User $actor, string $url, array $data)
    {
        $token = bin2hex(random_bytes(32));

        return $this->actingAs($actor)->withSession(['_token' => $token])->post($url, $data + ['csrf' => $token]);
    }

    public function test_stocktake_updates_stock_and_records_auditable_difference(): void
    {
        $warehouse = $this->warehouse();
        $actor = $this->actor('admin_gudang', $warehouse);
        $item = $this->item();
        DB::table('stok_gudang')->insert(['gudang_id' => $warehouse, 'barang_id' => $item, 'stok' => 8]);

        $this->submit($actor, '/stocktake.php', [
            'barang_id' => (string) $item,
            'stok_fisik' => '5',
            'alasan' => 'Penghitungan rak A',
        ])->assertRedirect('dashboard.php#stok');

        $this->assertSame(5, (int) DB::table('stok_gudang')->where('gudang_id', $warehouse)->where('barang_id', $item)->value('stok'));
        $adjustment = DB::table('stock_adjustments')->where('barang_id', $item)->first();
        $this->assertNotNull($adjustment);
        $this->assertSame(8, (int) $adjustment->stok_sebelum);
        $this->assertSame(5, (int) $adjustment->stok_fisik);
        $this->assertSame(-3, (int) $adjustment->selisih);
        $this->assertSame('Penghitungan rak A', $adjustment->alasan);
        $this->assertSame('keluar', DB::table('transaksi_gudang')->where('barang_id', $item)->value('jenis'));
    }

    public function test_stocktake_with_no_difference_is_still_audited_without_creating_movement(): void
    {
        $warehouse = $this->warehouse();
        $actor = $this->actor('admin_gudang', $warehouse);
        $item = $this->item();
        DB::table('stok_gudang')->insert(['gudang_id' => $warehouse, 'barang_id' => $item, 'stok' => 4]);

        $this->submit($actor, '/stocktake.php', [
            'barang_id' => (string) $item,
            'stok_fisik' => '4',
            'alasan' => 'Hitung sesuai sistem',
        ])->assertRedirect('dashboard.php#stok');

        $this->assertSame(0, (int) DB::table('stock_adjustments')->where('barang_id', $item)->value('selisih'));
        $this->assertSame(0, DB::table('transaksi_gudang')->where('barang_id', $item)->count());
    }

    public function test_manager_cannot_change_stock_but_can_download_filtered_csv(): void
    {
        $manager = $this->actor('manager');
        $warehouse = $this->warehouse();
        $item = $this->item('=HYPERLINK("https://example.test","Unsafe")');
        DB::table('stok_gudang')->insert(['gudang_id' => $warehouse, 'barang_id' => $item, 'stok' => 2]);

        $this->submit($manager, '/stocktake.php', [
            'barang_id' => (string) $item,
            'stok_fisik' => '1',
            'alasan' => 'Tidak berwenang',
        ])->assertForbidden();
        $this->assertSame(2, (int) DB::table('stok_gudang')->where('gudang_id', $warehouse)->where('barang_id', $item)->value('stok'));

        $response = $this->actingAs($manager)->get('/laporan_csv.php?jenis_laporan=stok');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->getContent());
        $this->assertStringContainsString("'=HYPERLINK", $response->getContent());
    }

    public function test_stocktake_is_visible_in_scoped_activity_history(): void
    {
        $warehouse = $this->warehouse();
        $actor = $this->actor('admin_gudang', $warehouse);
        $manager = $this->actor('manager');
        $item = $this->item();
        DB::table('stok_gudang')->insert(['gudang_id' => $warehouse, 'barang_id' => $item, 'stok' => 2]);
        $this->submit($actor, '/stocktake.php', [
            'barang_id' => (string) $item,
            'stok_fisik' => '3',
            'alasan' => 'Hitung ulang',
        ])->assertRedirect();

        $this->actingAs($manager)->get('/aktivitas.php?jenis=stocktake')->assertOk()->assertSee('Stock opname')->assertSee('Hitung ulang');
    }
}
