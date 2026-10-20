<?php

namespace Tests\Feature;

use App\Http\Middleware\WarehouseRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WarehouseMigrationTest extends TestCase
{
    private function actor(string $role): User
    {
        return User::where('role', $role)->where('aktif', 1)->firstOrFail();
    }

    private function submit(string $url, array $data)
    {
        $token = bin2hex(random_bytes(32));

        return $this->withSession(['_token' => $token])->post($url, $data + ['csrf' => $token]);
    }

    private function item(string $category = 'barang_siap'): int
    {
        return DB::table('barang')->insertGetId(['kode' => 'TEST-'.bin2hex(random_bytes(8)), 'nama' => 'Barang uji migrasi', 'kategori' => 'Pengujian', 'kategori_barang' => $category, 'satuan' => 'unit']);
    }

    private function stock(int $warehouse, int $item, int $quantity): void
    {
        DB::table('stok_gudang')->insert(['gudang_id' => $warehouse, 'barang_id' => $item, 'stok' => $quantity]);
    }

    private function amount(int $warehouse, int $item): int
    {
        return (int) DB::table('stok_gudang')->where('gudang_id', $warehouse)->where('barang_id', $item)->value('stok');
    }

    private function center(): int
    {
        return (int) DB::table('gudang')->where('kode', 'PUSAT')->value('id');
    }

    public function test_testing_database_is_isolated(): void
    {
        $this->assertSame('sistem_pergudangan_laravel_test', DB::connection()->getDatabaseName());
    }

    public function test_warehouse_middleware_does_not_rollback_an_existing_transaction(): void
    {
        DB::beginTransaction();
        try {
            app(WarehouseRequest::class)->handle(
                Request::create('/', 'GET'),
                fn () => response('ok')
            );
            $this->assertTrue(DB::connection()->getPdo()->inTransaction());
        } finally {
            if (DB::connection()->getPdo()->inTransaction()) {
                DB::rollBack();
            }
        }
    }

    public function test_home_and_login_keep_the_existing_interface(): void
    {
        $this->get('/')->assertOk()->assertSee('Trikasa Energi Tama');
        $this->get('/login.php')
            ->assertOk()
            ->assertSee('Masuk ke akun Anda')
            ->assertSee('password-visibility-toggle')
            ->assertSee('Tampilkan kata sandi');
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/dashboard.php')->assertRedirect('login.php');
    }

    public function test_role_pages_render(): void
    {
        foreach (['admin_pusat' => 'dashboard.php', 'admin_gudang' => 'dashboard.php', 'manager' => 'dashboard.php', 'purchasing' => 'purchasing.php', 'super_admin' => 'super_admin.php'] as $role => $page) {
            $this->actingAs($this->actor($role))->get('/'.$page)->assertOk();
        }
    }

    public function test_dashboard_navigation_separates_sidebar_and_account_bar(): void
    {
        $this->actingAs($this->actor('manager'))
            ->get('/dashboard.php')
            ->assertOk()
            ->assertSee('Navigasi dashboard')
            ->assertSee('Bagian dashboard')
            ->assertSee('dashboard-account-bar')
            ->assertSee('Trikasa Energi Tama')
            ->assertSee('dashboard-brand-role')
            ->assertSee('permintaan-pembelian');
    }

    public function test_location_stock_summary_is_shown_only_to_roles_with_location_overview(): void
    {
        foreach (['manager' => 'dashboard.php', 'purchasing' => 'purchasing.php', 'admin_pusat' => 'dashboard.php', 'super_admin' => 'super_admin.php'] as $role => $page) {
            $this->actingAs($this->actor($role))
                ->get('/'.$page)
                ->assertOk()
                ->assertSee('Stok per lokasi')
                ->assertSee('Jumlah unit tersedia di tiap gudang.');
        }

        $this->actingAs($this->actor('admin_gudang'))
            ->get('/dashboard.php')
            ->assertOk()
            ->assertDontSee('Ikhtisar multi-gudang');
    }

    public function test_forbidden_roles_cannot_open_operational_pages(): void
    {
        $this->actingAs($this->actor('purchasing'))->get('/dashboard.php')->assertForbidden();
        $this->actingAs($this->actor('admin_pusat'))->get('/super_admin.php')->assertForbidden();
    }

    public function test_role_scoped_pages_deny_every_role_outside_the_allowed_set(): void
    {
        $pageRoles = [
            '/dashboard.php' => ['admin_pusat', 'admin_gudang', 'manager'],
            '/aktivitas.php' => ['super_admin', 'admin_pusat', 'admin_gudang', 'manager'],
            '/purchasing.php' => ['purchasing'],
            '/super_admin.php' => ['super_admin'],
            '/penerimaan_pembelian.php' => ['admin_pusat', 'admin_gudang'],
            '/detail_penerimaan_pembelian.php?id=1' => ['purchasing', 'manager'],
            '/bukti_persetujuan.php?id=1' => ['purchasing', 'manager', 'admin_pusat', 'admin_gudang'],
            '/foto_penerimaan_pembelian.php?id=1' => ['purchasing', 'manager', 'admin_pusat', 'admin_gudang'],
            '/surat_jalan.php?id=1' => ['admin_pusat', 'admin_gudang', 'manager'],
            '/laporan_pdf.php?jenis_laporan=stok' => ['manager'],
            '/laporan_csv.php?jenis_laporan=stok' => ['manager'],
        ];
        $roles = ['super_admin', 'admin_pusat', 'admin_gudang', 'manager', 'purchasing'];

        foreach ($pageRoles as $path => $allowedRoles) {
            foreach ($roles as $role) {
                if (in_array($role, $allowedRoles, true)) {
                    continue;
                }

                $this->actingAs($this->actor($role))->get($path)->assertForbidden();
            }
        }
    }

    public function test_user_administration_action_is_reserved_for_super_admin(): void
    {
        $this->actingAs($this->actor('manager'))
            ->post('/aksi_pengguna.php', ['aksi' => 'buat'])
            ->assertForbidden();
    }

    public function test_legacy_action_endpoints_reject_roles_outside_their_domain(): void
    {
        $this->actingAs($this->actor('manager'))
            ->submit('/aksi_pembelian.php', ['aksi' => 'permintaan_setujui'])
            ->assertForbidden();

        $this->actingAs($this->actor('purchasing'))
            ->submit('/aksi_admin.php', ['aksi' => 'barang_simpan'])
            ->assertForbidden();
    }

    public function test_nonactive_account_is_denied(): void
    {
        $id = DB::table('users')->insertGetId(['nama' => 'Uji nonaktif', 'email' => 'disabled-'.bin2hex(random_bytes(6)).'@example.test', 'password_hash' => Hash::make(bin2hex(random_bytes(12))), 'role' => 'manager', 'aktif' => 0]);
        $this->actingAs(User::findOrFail($id))->get('/dashboard.php')->assertForbidden();
    }

    public function test_login_accepts_existing_password_hash_column(): void
    {
        $password = bin2hex(random_bytes(16));
        $email = 'login-'.bin2hex(random_bytes(6)).'@example.test';
        $id = DB::table('users')->insertGetId(['nama' => 'Uji login', 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'manager']);
        $this->submit('/login.php', ['email' => $email, 'password' => $password])->assertRedirect('dashboard.php');
        $this->assertAuthenticatedAs(User::findOrFail($id));
        $this->submit('/logout.php', [])->assertRedirect('login.php');
        $this->assertGuest();
    }

    public function test_login_failures_do_not_reveal_whether_an_email_exists(): void
    {
        $activeEmail = 'active-'.bin2hex(random_bytes(6)).'@example.test';
        $inactiveEmail = 'inactive-'.bin2hex(random_bytes(6)).'@example.test';
        DB::table('users')->insert(['nama' => 'Akun aktif uji', 'email' => $activeEmail, 'password_hash' => Hash::make('CorrectPass123'), 'role' => 'manager', 'aktif' => 1]);
        DB::table('users')->insert(['nama' => 'Akun nonaktif uji', 'email' => $inactiveEmail, 'password_hash' => Hash::make('CorrectPass123'), 'role' => 'manager', 'aktif' => 0]);
        foreach ([$activeEmail, $inactiveEmail, 'missing-'.bin2hex(random_bytes(6)).'@example.test'] as $email) {
            $this->submit('/login.php', ['email' => $email, 'password' => 'WrongPass123'])
                ->assertOk()
                ->assertSee('Email atau kata sandi tidak sesuai.')
                ->assertDontSee('Akun belum dipetakan atau belum memiliki akses aktif.');
        }
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $email = 'limited-'.bin2hex(random_bytes(6)).'@example.test';
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])
                ->submit('/login.php', ['email' => $email, 'password' => 'WrongPass123'])
                ->assertOk()
                ->assertSee('Email atau kata sandi tidak sesuai.');
        }
    }

    public function test_mutating_endpoints_reject_get_requests(): void
    {
        foreach (['/aksi_admin.php', '/aksi_pembelian.php', '/aksi_pengguna.php', '/logout.php'] as $path) {
            $this->get($path)->assertMethodNotAllowed();
        }
    }

    public function test_actions_reject_missing_csrf(): void
    {
        $this->actingAs($this->actor('admin_pusat'))->post('/aksi_admin.php', ['aksi' => 'barang_simpan'])->assertStatus(419);
        $this->actingAs($this->actor('purchasing'))->post('/aksi_pembelian.php', ['aksi' => 'permintaan_setujui', 'permintaan_id' => '1'])->assertStatus(419);
        $actor = $this->actor('super_admin');
        $this->actingAs($actor)->post('/aksi_pengguna.php', ['aksi' => 'simpan', 'user_id' => (string) $actor->id])->assertStatus(419);
        $this->actingAs($actor)->post('/logout.php')->assertStatus(419);
    }

    public function test_codes_have_separate_sequences(): void
    {
        $this->actingAs($this->actor('admin_pusat'));
        $expected = [];
        foreach (['barang_siap' => 'MP', 'barang_dipakai' => 'MH', 'peralatan' => 'PR'] as $category => $prefix) {
            $last = (int) DB::table('nomor_kode_barang')->where('kategori_barang', $category)->value('nomor_terakhir');
            $name = 'Kode uji '.bin2hex(random_bytes(5));
            $this->submit('/aksi_admin.php', ['aksi' => 'barang_simpan', 'nama' => $name, 'kategori' => 'Uji', 'kategori_barang' => $category, 'satuan' => 'unit', 'stok_awal' => '2'])->assertRedirect();
            $item = DB::table('barang')->where('nama', $name)->first();
            $this->assertNotNull($item);
            $this->assertSame($prefix.'-'.str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT), $item->kode);
            $this->assertSame(2, $this->amount($this->center(), $item->id));
        }
    }

    public function test_shipping_and_partial_receipt_preserve_stock_rules(): void
    {
        $project = $this->actor('admin_gudang');
        $center = $this->center();
        $item = $this->item();
        $this->stock($center, $item, 10);
        $this->actingAs($project);
        $this->submit('/aksi_admin.php', ['aksi' => 'permintaan_simpan', 'barang_id' => (string) $item, 'jumlah' => '4', 'catatan' => 'Uji pengiriman'])->assertRedirect();
        $request = DB::table('permintaan')->where('user_id', $project->id)->orderByDesc('id')->first();
        $second = $this->item('peralatan');
        $this->stock($center, $second, 10);
        DB::table('detail_permintaan')->insert(['permintaan_id' => $request->id, 'barang_id' => $second, 'jumlah' => 2]);
        $this->actingAs($this->actor('admin_pusat'));
        $this->submit('/aksi_admin.php', ['aksi' => 'permintaan_kirim', 'permintaan_id' => (string) $request->id, 'nomor_kendaraan' => 'B 1234 UJI', 'nama_driver' => 'Sopir Uji'])->assertRedirect();
        $shipment = DB::table('pengiriman')->where('permintaan_id', $request->id)->first();
        $this->assertNotNull($shipment);
        $this->assertSame(6, $this->amount($center, $item));
        $this->assertSame(0, $this->amount($project->gudang_id, $item));
        $this->get('/surat_jalan.php?id='.$shipment->id)->assertOk()->assertSee('B 1234 UJI')->assertSee('Sopir Uji')->assertSee(DB::table('barang')->where('id', $second)->value('kode'));
        $details = DB::table('detail_pengiriman')->where('pengiriman_id', $shipment->id)->get();
        $quantities = [];
        foreach ($details as $detail) {
            $quantities[(string) $detail->id] = (string) ($detail->barang_id === $item ? 3 : 2);
        }
        $this->actingAs($project);
        $this->submit('/aksi_admin.php', ['aksi' => 'penerimaan_konfirmasi', 'pengiriman_id' => (string) $shipment->id, 'jumlah_diterima' => $quantities, 'catatan_penerimaan' => 'Selisih satu'])->assertRedirect();
        $this->assertSame(3, $this->amount($project->gudang_id, $item));
        $this->assertSame('diterima_dengan_selisih', DB::table('pengiriman')->where('id', $shipment->id)->value('status'));
        $this->submit('/aksi_admin.php', ['aksi' => 'penerimaan_konfirmasi', 'pengiriman_id' => (string) $shipment->id, 'jumlah_diterima' => $quantities])->assertRedirect();
        $this->assertSame(3, $this->amount($project->gudang_id, $item));
    }

    public function test_missing_transport_does_not_send_goods(): void
    {
        $project = $this->actor('admin_gudang');
        $item = $this->item();
        $center = $this->center();
        $this->stock($center, $item, 10);
        $id = DB::table('permintaan')->insertGetId(['user_id' => $project->id, 'kategori_barang' => 'barang_siap', 'gudang_asal_id' => $center, 'gudang_tujuan_id' => $project->gudang_id]);
        DB::table('detail_permintaan')->insert(['permintaan_id' => $id, 'barang_id' => $item, 'jumlah' => 2]);
        $this->actingAs($this->actor('admin_pusat'));
        $this->submit('/aksi_admin.php', ['aksi' => 'permintaan_kirim', 'permintaan_id' => (string) $id])->assertRedirect();
        $this->assertSame(10, $this->amount($center, $item));
        $this->assertFalse(DB::table('pengiriman')->where('permintaan_id', $id)->exists());
    }

    public function test_purchase_approval_and_receipt_are_separate(): void
    {
        $center = $this->center();
        $item = $this->item('barang_dipakai');
        $this->stock($center, $item, 0);
        $admin = $this->actor('admin_pusat');
        $this->actingAs($admin);
        $this->submit('/aksi_pembelian.php', ['aksi' => 'permintaan_simpan', 'barang_id' => (string) $item, 'jumlah' => '5', 'catatan' => 'Uji pembelian'])->assertRedirect();
        $request = DB::table('permintaan_pembelian')->where('barang_id', $item)->first();
        $this->assertNotNull($request);
        $this->actingAs($this->actor('purchasing'));
        $this->submit('/aksi_pembelian.php', ['aksi' => 'permintaan_setujui', 'permintaan_id' => (string) $request->id])->assertRedirect();
        $this->assertSame('disetujui', DB::table('permintaan_pembelian')->where('id', $request->id)->value('status'));
        $this->assertSame(0, $this->amount($center, $item));
        $this->get('/bukti_persetujuan.php?id='.$request->id)->assertOk();
        $this->get('/detail_penerimaan_pembelian.php?id='.$request->id)->assertOk();
        $this->actingAs($admin);
        $this->get('/penerimaan_pembelian.php')->assertOk();
        $token = bin2hex(random_bytes(32));
        $data = ['aksi' => 'pembelian_penerimaan_simpan', 'permintaan_pembelian_id' => (string) $request->id, 'token_form' => $token, 'jumlah_layak' => '3', 'jumlah_rusak' => '0', 'jumlah_tidak_sesuai' => '0', 'tanggal_diterima' => date('Y-m-d').'T10:00', 'catatan_kondisi' => 'Baik'];
        $this->submit('/aksi_admin.php', $data)->assertRedirect();
        $this->assertSame(3, $this->amount($center, $item));
        $this->submit('/aksi_admin.php', $data)->assertRedirect();
        $this->assertSame(3, $this->amount($center, $item));
        $data['token_form'] = bin2hex(random_bytes(32));
        $data['jumlah_layak'] = '3';
        $this->submit('/aksi_admin.php', $data)->assertRedirect();
        $this->assertSame(3, $this->amount($center, $item));
    }

    public function test_equipment_return_and_usage(): void
    {
        $project = $this->actor('admin_gudang');
        $item = $this->item('peralatan');
        $center = $this->center();
        $this->stock($project->gudang_id, $item, 10);
        $this->stock($center, $item, 0);
        $this->actingAs($project);
        $this->submit('/aksi_admin.php', ['aksi' => 'pemakaian_simpan', 'barang_id' => (string) $item, 'jumlah' => '2', 'catatan' => 'Uji pemakaian'])->assertRedirect();
        $this->assertSame(8, $this->amount($project->gudang_id, $item));
        $this->submit('/aksi_admin.php', ['aksi' => 'peralatan_kirim_kembali', 'barang_id' => (string) $item, 'jumlah' => '3', 'catatan_kondisi' => 'Baik'])->assertRedirect();
        $return = DB::table('pengembalian_peralatan_proyek')->where('barang_id', $item)->first();
        $this->assertNotNull($return);
        $this->assertSame(5, $this->amount($project->gudang_id, $item));
        $this->assertSame(0, $this->amount($center, $item));
        $this->actingAs($this->actor('admin_pusat'));
        $this->submit('/aksi_admin.php', ['aksi' => 'peralatan_kembali_konfirmasi', 'pengembalian_peralatan_id' => (string) $return->id])->assertRedirect();
        $this->assertSame(3, $this->amount($center, $item));
        $this->submit('/aksi_admin.php', ['aksi' => 'peralatan_kembali_konfirmasi', 'pengembalian_peralatan_id' => (string) $return->id])->assertRedirect();
        $this->assertSame(3, $this->amount($center, $item));
    }

    public function test_pdf_and_private_photo_are_served_with_correct_headers(): void
    {
        $this->actingAs($this->actor('manager'));
        $report = $this->get('/laporan_pdf.php');
        $report->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $report->getContent());
        $photo = DB::table('penerimaan_pembelian_foto')->first();
        $this->get('/foto_penerimaan_pembelian.php?id='.$photo->id)->assertOk()->assertHeader('Content-Type', $photo->mime_type);
        $privateRoot = realpath(receipt_photo_storage_directory());
        $publicRoot = realpath(public_path());
        $photoPath = realpath($privateRoot.DIRECTORY_SEPARATOR.$photo->lokasi_file);
        $this->assertNotFalse($privateRoot);
        $this->assertNotFalse($publicRoot);
        $this->assertNotFalse($photoPath);
        $this->assertStringStartsWith(strtolower($privateRoot.DIRECTORY_SEPARATOR), strtolower($photoPath));
        $this->assertStringStartsNotWith(strtolower($publicRoot.DIRECTORY_SEPARATOR), strtolower($photoPath));
        $this->assertFileDoesNotExist(public_path('storage/'.$photo->lokasi_file));
        $this->get('/storage/'.$photo->lokasi_file)->assertNotFound();
    }

    public function test_project_cannot_read_another_projects_delivery_note(): void
    {
        $project = $this->actor('admin_gudang');
        $otherWarehouse = DB::table('gudang')->insertGetId(['kode' => 'TEST-'.bin2hex(random_bytes(4)), 'nama' => 'Proyek lain uji', 'jenis' => 'proyek']);
        $otherUserId = DB::table('users')->insertGetId(['nama' => 'Admin proyek lain', 'email' => 'other-'.bin2hex(random_bytes(6)).'@example.test', 'password_hash' => Hash::make(bin2hex(random_bytes(12))), 'role' => 'admin_gudang', 'gudang_id' => $otherWarehouse]);
        $item = $this->item();
        $center = $this->center();
        $this->stock($center, $item, 5);
        $requestId = DB::table('permintaan')->insertGetId(['user_id' => $project->id, 'kategori_barang' => 'barang_siap', 'gudang_asal_id' => $center, 'gudang_tujuan_id' => $project->gudang_id]);
        DB::table('detail_permintaan')->insert(['permintaan_id' => $requestId, 'barang_id' => $item, 'jumlah' => 2]);
        $this->actingAs($this->actor('admin_pusat'));
        $this->submit('/aksi_admin.php', ['aksi' => 'permintaan_kirim', 'permintaan_id' => (string) $requestId, 'nomor_kendaraan' => 'B 1234 UJI', 'nama_driver' => 'Sopir Uji'])->assertRedirect();
        $shipment = DB::table('pengiriman')->where('permintaan_id', $requestId)->first();
        $this->assertNotNull($shipment);
        $quantities = DB::table('detail_pengiriman')->where('pengiriman_id', $shipment->id)->pluck('jumlah_dikirim', 'id')->map(fn ($quantity) => (string) $quantity)->all();
        $this->actingAs(User::findOrFail($otherUserId))
            ->get('/surat_jalan.php?id='.$shipment->id)
            ->assertNotFound();
        $this->submit('/aksi_admin.php', ['aksi' => 'penerimaan_konfirmasi', 'pengiriman_id' => (string) $shipment->id, 'jumlah_diterima' => $quantities])
            ->assertRedirect();
        $this->assertSame('dalam_perjalanan', DB::table('pengiriman')->where('id', $shipment->id)->value('status'));
        $this->assertSame(0, $this->amount($otherWarehouse, $item));
        $this->assertSame(0, $this->amount($project->gudang_id, $item));
    }

    public function test_insufficient_stock_rolls_back_all_shipping_details(): void
    {
        $project = $this->actor('admin_gudang');
        $center = $this->center();
        $one = $this->item();
        $two = $this->item();
        $this->stock($center, $one, 10);
        $this->stock($center, $two, 1);
        $id = DB::table('permintaan')->insertGetId(['user_id' => $project->id, 'kategori_barang' => 'barang_siap', 'gudang_asal_id' => $center, 'gudang_tujuan_id' => $project->gudang_id]);
        DB::table('detail_permintaan')->insert([['permintaan_id' => $id, 'barang_id' => $one, 'jumlah' => 2], ['permintaan_id' => $id, 'barang_id' => $two, 'jumlah' => 2]]);
        $this->actingAs($this->actor('admin_pusat'));
        $this->submit('/aksi_admin.php', ['aksi' => 'permintaan_kirim', 'permintaan_id' => (string) $id, 'nomor_kendaraan' => 'B 1 UJI', 'nama_driver' => 'Uji'])->assertRedirect();
        $this->assertSame(10, $this->amount($center, $one));
        $this->assertSame(1, $this->amount($center, $two));
        $this->assertFalse(DB::table('pengiriman')->where('permintaan_id', $id)->exists());
        $this->assertSame('diajukan', DB::table('permintaan')->where('id', $id)->value('status'));
    }

    public function test_transport_edit_does_not_mutate_inventory(): void
    {
        $shipment = DB::table('pengiriman')->first();
        $before = DB::table('stok_gudang')->orderBy('id')->get()->toJson();
        $this->actingAs($this->actor('admin_pusat'));
        $this->submit('/aksi_admin.php', ['aksi' => 'surat_jalan_transport_simpan', 'pengiriman_id' => (string) $shipment->id, 'nomor_kendaraan' => 'B 9 UJI', 'nama_driver' => 'Sopir koreksi'])->assertRedirect('surat_jalan.php?id='.$shipment->id);
        $this->get('/surat_jalan.php?id='.$shipment->id)->assertOk()->assertSee('Sopir koreksi');
        $this->assertSame($before, DB::table('stok_gudang')->orderBy('id')->get()->toJson());
    }

    public function test_manager_cannot_write_inventory(): void
    {
        $this->actingAs($this->actor('manager'));
        $this->submit('/aksi_admin.php', ['aksi' => 'barang_simpan', 'nama' => 'Forbidden', 'kategori' => 'Uji', 'kategori_barang' => 'peralatan', 'satuan' => 'unit'])->assertForbidden();
    }

    public function test_super_admin_cannot_disable_their_own_account(): void
    {
        $actor = $this->actor('super_admin');
        $this->actingAs($actor);
        $this->submit('/aksi_pengguna.php', ['aksi' => 'simpan', 'user_id' => (string) $actor->id, 'nama' => $actor->nama, 'email' => $actor->email, 'role' => 'manager', 'aktif' => '0', 'gudang_id' => ''])->assertRedirect();
        $actor->refresh();
        $this->assertSame('super_admin', $actor->role);
        $this->assertTrue($actor->aktif);
    }

    public function test_archiving_with_stock_keeps_item_active(): void
    {
        $item = $this->item();
        $this->stock($this->center(), $item, 2);
        $this->actingAs($this->actor('admin_pusat'));
        $this->submit('/aksi_admin.php', ['aksi' => 'barang_delete', 'barang_id' => (string) $item, 'catatan_penghapusan' => 'Uji arsip'])->assertRedirect();
        $this->assertSame(1, (int) DB::table('barang')->where('id', $item)->value('aktif'));
    }

    public function test_project_addition_updates_only_its_warehouse(): void
    {
        $project = $this->actor('admin_gudang');
        $item = $this->item();
        $this->actingAs($project);
        $this->submit('/aksi_admin.php', ['aksi' => 'penambahan_proyek_simpan', 'barang_id' => (string) $item, 'jumlah' => '3', 'catatan_penambahan' => 'Sumber uji'])->assertRedirect();
        $this->assertSame(3, $this->amount($project->gudang_id, $item));
        $this->assertSame(0, $this->amount($this->center(), $item));
        $this->assertTrue(DB::table('penambahan_barang_proyek')->where('barang_id', $item)->exists());
    }

    public function test_parallel_code_allocations_are_unique(): void
    {
        $marker = bin2hex(random_bytes(5));
        $one = new Process([PHP_BINARY, base_path('tests/Support/code-worker.php'), $marker.'-1'], base_path(), ['APP_ENV' => 'testing']);
        $two = new Process([PHP_BINARY, base_path('tests/Support/code-worker.php'), $marker.'-2'], base_path(), ['APP_ENV' => 'testing']);
        $one->start();
        $two->start();
        $one->wait();
        $two->wait();
        $workerOutput = static fn (Process $worker): string => 'stdout: '.$worker->getOutput().' stderr: '.$worker->getErrorOutput();
        $this->assertTrue($one->isSuccessful(), 'Worker 1 '.$workerOutput($one));
        $this->assertTrue($two->isSuccessful(), 'Worker 2 '.$workerOutput($two));
        $firstResult = json_decode($one->getOutput(), true);
        $secondResult = json_decode($two->getOutput(), true);
        $diagnostics = $workerOutput($one).' / '.$workerOutput($two).' / '.DB::connection()->getDatabaseName();
        $this->assertIsArray($firstResult, 'Worker 1 harus mengeluarkan JSON. '.$diagnostics);
        $this->assertIsArray($secondResult, 'Worker 2 harus mengeluarkan JSON. '.$diagnostics);
        $this->assertSame('sistem_pergudangan_laravel_test', $firstResult['database'] ?? null, $diagnostics);
        $this->assertSame('sistem_pergudangan_laravel_test', $secondResult['database'] ?? null, $diagnostics);
        $this->assertSame($marker.'-1', $firstResult['name'] ?? null, $diagnostics);
        $this->assertSame($marker.'-2', $secondResult['name'] ?? null, $diagnostics);
        $codes = DB::table('barang')->whereIn('nama', [$marker.'-1', $marker.'-2'])->pluck('kode')->all();
        $this->assertCount(2, $codes, $diagnostics);
        $this->assertCount(2, array_unique($codes), $diagnostics);
        $reportedCodes = [$firstResult['code'] ?? null, $secondResult['code'] ?? null];
        sort($reportedCodes);
        sort($codes);
        $this->assertSame($reportedCodes, $codes, $diagnostics);
        $this->assertSame((int) substr($codes[0], 3) + 1, (int) substr($codes[1], 3), $diagnostics);
    }
}
