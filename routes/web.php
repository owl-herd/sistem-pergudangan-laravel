<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ActionController;
Route::get('/', [PageController::class,'index']);
Route::get('/bukti_persetujuan.php', [PageController::class, 'bukti_persetujuan']);
Route::get('/dashboard.php', [PageController::class, 'dashboard']);
Route::get('/detail_penerimaan_pembelian.php', [PageController::class, 'detail_penerimaan_pembelian']);
Route::get('/foto_penerimaan_pembelian.php', [PageController::class, 'foto_penerimaan_pembelian']);
Route::get('/index.php', [PageController::class, 'index']);
Route::get('/laporan_pdf.php', [PageController::class, 'laporan_pdf']);
Route::match(['GET','POST'],'/login.php', [PageController::class, 'login']);
Route::get('/penerimaan_pembelian.php', [PageController::class, 'penerimaan_pembelian']);
Route::get('/purchasing.php', [PageController::class, 'purchasing']);
Route::get('/super_admin.php', [PageController::class, 'super_admin']);
Route::get('/surat_jalan.php', [PageController::class, 'surat_jalan']);
Route::post('/aksi_admin.php', [ActionController::class, 'aksi_admin']);
Route::post('/aksi_pembelian.php', [ActionController::class, 'aksi_pembelian']);
Route::post('/aksi_pengguna.php', [ActionController::class, 'aksi_pengguna']);
Route::get('/portal.php', function () { require_role('super_admin','admin_pusat','admin_gudang','manager','purchasing'); go(user()['role']==='super_admin'?'super_admin.php':(user()['role']==='purchasing'?'purchasing.php':'dashboard.php')); });
Route::post('/logout.php', function () { check_csrf(); auth()->logout(); session()->invalidate(); session()->regenerateToken(); return redirect('login.php'); });

Route::get('/profil.php', [App\Http\Controllers\ProfileController::class, 'edit']);
Route::post('/profil.php', [App\Http\Controllers\ProfileController::class, 'update']);
Route::get('/foto_profil.php', [App\Http\Controllers\ProfileController::class, 'photo']);
Route::get('/asisten.php', [App\Http\Controllers\WarehouseAssistantController::class, 'show']);
Route::post('/asisten.php', [App\Http\Controllers\WarehouseAssistantController::class, 'ask'])->middleware('throttle:10,1');
Route::post('/notifikasi_pembelian.php/{notificationId}/baca', [App\Http\Controllers\PurchasingNotificationController::class, 'markRead'])->whereNumber('notificationId');

Route::post('/notifikasi.php/{notificationId}/baca', [App\Http\Controllers\WarehouseNotificationController::class, 'markRead'])->whereNumber('notificationId');
