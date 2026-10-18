<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchasingNotificationController;
use App\Http\Controllers\WarehouseAssistantController;
use App\Http\Controllers\WarehouseNotificationController;
use App\Http\Controllers\WarehouseStockAdjustmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'index']);
Route::get('/bukti_persetujuan.php', [PageController::class, 'bukti_persetujuan']);
Route::get('/dashboard.php', [PageController::class, 'dashboard']);
Route::get('/aktivitas.php', [PageController::class, 'activity']);
Route::get('/detail_penerimaan_pembelian.php', [PageController::class, 'detail_penerimaan_pembelian']);
Route::get('/foto_penerimaan_pembelian.php', [PageController::class, 'foto_penerimaan_pembelian']);
Route::get('/index.php', [PageController::class, 'index']);
Route::get('/laporan_pdf.php', [PageController::class, 'laporan_pdf']);
Route::get('/laporan_csv.php', [PageController::class, 'laporan_csv']);
Route::match(['GET', 'POST'], '/login.php', [PageController::class, 'login']);
Route::get('/penerimaan_pembelian.php', [PageController::class, 'penerimaan_pembelian']);
Route::get('/purchasing.php', [PageController::class, 'purchasing']);
Route::get('/super_admin.php', [PageController::class, 'super_admin']);
Route::get('/surat_jalan.php', [PageController::class, 'surat_jalan']);
Route::post('/aksi_admin.php', [ActionController::class, 'aksi_admin']);
Route::post('/stocktake.php', [WarehouseStockAdjustmentController::class, 'store']);
Route::post('/aksi_pembelian.php', [ActionController::class, 'aksi_pembelian']);
Route::post('/aksi_pengguna.php', [ActionController::class, 'aksi_pengguna']);
Route::get('/portal.php', function () {
    require_role('super_admin', 'admin_pusat', 'admin_gudang', 'manager', 'purchasing');
    go(user()['role'] === 'super_admin' ? 'super_admin.php' : (user()['role'] === 'purchasing' ? 'purchasing.php' : 'dashboard.php'));
});
Route::post('/logout.php', function () {
    check_csrf();
    auth()->logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect('login.php');
});

Route::get('/profil.php', [ProfileController::class, 'edit']);
Route::post('/profil.php', [ProfileController::class, 'update']);
Route::get('/foto_profil.php', [ProfileController::class, 'photo']);
Route::get('/asisten.php', [WarehouseAssistantController::class, 'show']);
Route::post('/asisten.php', [WarehouseAssistantController::class, 'ask'])->middleware('throttle:10,1');
Route::post('/notifikasi_pembelian.php/{notificationId}/baca', [PurchasingNotificationController::class, 'markRead'])->whereNumber('notificationId');

Route::post('/notifikasi.php/{notificationId}/baca', [WarehouseNotificationController::class, 'markRead'])->whereNumber('notificationId');
