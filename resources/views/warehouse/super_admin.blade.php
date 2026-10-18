
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#123a68">
  <title>Pengelolaan akun · GudangKita</title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(public_path('assets/style.css')) ?>">
</head>
<body>
<nav class="navbar navbar-expand-xl navbar-dark dashboard-navbar sticky-top shadow-sm" style="background: var(--brand-blue-dark);" aria-label="Navigasi Super Admin">
  <div class="container-fluid px-lg-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="super_admin.php"><img src="assets/logo.png" alt=""><span class="fw-bold">Trikasa Energi Tama</span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#superAdminNavigation" aria-controls="superAdminNavigation" aria-expanded="false" aria-label="Buka navigasi Super Admin"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="superAdminNavigation">
      <ul class="navbar-nav me-auto ms-lg-4 gap-lg-2 py-2 py-lg-0">
        <li class="nav-item"><a class="nav-link active" aria-current="location" href="#buat-akun">Buat akun</a></li>
        <li class="nav-item"><a class="nav-link" href="#akun">Daftar akun</a></li>
        <li class="nav-item"><a class="nav-link" href="#pengelolaan-gudang">Gudang proyek</a></li>
        <li class="nav-item"><a class="nav-link" href="#akun-legacy">Akun legacy</a></li>
        <li class="nav-item"><a class="nav-link" href="#riwayat">Riwayat perubahan</a></li>
        <li class="nav-item"><a class="nav-link" href="aktivitas.php">Aktivitas gudang</a></li>
      </ul>
      @include('warehouse.partials.account-panel', ['accountLocation' => 'Pengelolaan sistem', 'accountRole' => 'Super Admin', 'accountNotificationCount' => $notificationInbox['count'], 'accountNotifications' => $notificationInbox['items']])
    </div>
  </div>
</nav>
<main class="container-fluid px-3 px-lg-4 py-4">
@include('warehouse.notifications')
  <?php if($flashMessage): ?><div class="alert alert-<?=e($flashMessage[1]==='error'?'danger':$flashMessage[1])?> alert-dismissible fade show" role="alert"><?=e($flashMessage[0])?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div><?php endif ?>
  @include('warehouse.partials.location-stock-summary')
  <section class="mb-4">
    <span class="section-kicker">Administrasi sistem</span>
    <h1 class="h2 fw-bold text-primary-emphasis mt-1">Pengelolaan akun dan role</h1>
    <p class="text-secondary mb-0">Buat akun, ubah data dan role, atur status akses, serta lihat riwayat perubahan. Role mengikuti pilihan tetap aplikasi.</p>
  </section>

  <section class="app-card card shadow-sm mb-4" id="buat-akun">
    <div class="card-body p-3 p-lg-4">
      <span class="section-kicker">Akun baru</span>
      <h2 class="h4 fw-bold text-primary-emphasis mt-1">Buat akun pengguna</h2>
      <form method="post" action="aksi_pengguna.php" class="row g-3">
        <input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="buat">
        <div class="col-md-6 col-xl-3"><label class="form-label" for="nama-baru">Nama</label><input class="form-control" id="nama-baru" name="nama" maxlength="120" autocomplete="name" required></div>
        <div class="col-md-6 col-xl-3"><label class="form-label" for="email-baru">Email</label><input class="form-control" id="email-baru" type="email" name="email" maxlength="190" autocomplete="email" required></div>
        <div class="col-md-6 col-xl-2"><label class="form-label" for="role-baru">Role</label><select class="form-select" id="role-baru" name="role" data-role-select required><?php foreach($roleLabels as $value=>$label): if(in_array($value,['pengguna','super_admin'],true)) continue; ?><option value="<?=e($value)?>" <?=$value==='admin_pusat'?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></div>
        <div class="col-md-6 col-xl-2"><label class="form-label" for="gudang-baru">Gudang proyek</label><select class="form-select" id="gudang-baru" name="gudang_id" data-warehouse-select disabled><option value="">Pilih saat role Admin Gudang</option><?php foreach($warehouses as $warehouse): if((int)$warehouse['aktif']): ?><option value="<?=$warehouse['id']?>"><?=e($warehouse['nama'])?></option><?php endif; endforeach ?></select></div>
        <div class="col-md-6 col-xl-2"><label class="form-label" for="status-baru">Status akun</label><select class="form-select" id="status-baru" name="aktif" required><option value="1" selected>Aktif</option><option value="0">Nonaktif</option></select></div>
        <div class="col-md-6 col-xl-3"><label class="form-label" for="password-baru">Kata sandi awal</label><input class="form-control" id="password-baru" type="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" required><small class="form-text">Minimal 8 karakter.</small></div>
        <div class="col-12"><button class="btn btn-primary" type="submit">Buat akun</button></div>
      </form>
      <p class="small text-secondary mt-3 mb-0">Role Pengguna dipertahankan untuk riwayat lama dan tidak ditawarkan pada formulir akun baru. Super Admin pertama dibuat melalui skrip CLI; akun Super Admin tambahan ditetapkan melalui ubah role akun. Admin Gudang Proyek wajib ditautkan ke satu gudang proyek aktif.</p>
    </div>
  </section>

  <section class="app-card card shadow-sm mb-4" id="akun">
    <div class="card-body p-3 p-lg-4">
      <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><span class="section-kicker">Daftar akun</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-0">Akun terdaftar</h2></div><span class="text-secondary"><?=count($accounts)?> akun</span></div>
      <?php if(!$accounts): ?><p class="text-secondary mb-0">Belum ada akun.</p><?php endif ?>
      <div class="row g-3">
        <?php foreach($accounts as $account): ?>
        <div class="col-12"><article class="border rounded-3 p-3 p-lg-4">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div><h3 class="h6 fw-bold mb-1"><?=e($account['nama'])?></h3><p class="small text-secondary mb-0"><?=e($account['email'])?> · ID <?=e((string)$account['id'])?><?php if($account['gudang']): ?> · <?=e($account['gudang'])?><?=!(int)$account['gudang_aktif']?' (gudang nonaktif)':''?><?php endif ?></p></div>
            <div class="d-flex flex-wrap gap-2"><span class="badge rounded-pill text-bg-primary"><?=e($roleLabels[$account['role']]??$account['role'])?></span><span class="badge rounded-pill <?=((int)$account['aktif'])?'text-bg-success':'text-bg-secondary'?>"><?=((int)$account['aktif'])?'Aktif':'Nonaktif'?></span></div>
          </div>
          <?php if($account['pemetaan_status']==='menunggu_konfirmasi'): ?>
            <p class="small text-warning-emphasis mt-3 mb-0">Akun legacy menunggu klasifikasi. Gunakan bagian pemetaan akun lama di bawah.</p>
          <?php else: ?>
          <details class="mt-3">
            <summary class="btn btn-outline-primary btn-sm">Ubah akun</summary>
            <form method="post" action="aksi_pengguna.php" class="row g-3 border rounded p-3 mt-2">
              <input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="user_id" value="<?=$account['id']?>">
              <div class="col-md-6 col-xl-3"><label class="form-label" for="nama-<?=$account['id']?>">Nama</label><input class="form-control" id="nama-<?=$account['id']?>" name="nama" maxlength="120" value="<?=e($account['nama'])?>" required></div>
              <div class="col-md-6 col-xl-3"><label class="form-label" for="email-<?=$account['id']?>">Email</label><input class="form-control" id="email-<?=$account['id']?>" type="email" name="email" maxlength="190" value="<?=e($account['email'])?>" required></div>
              <div class="col-md-6 col-xl-2"><label class="form-label" for="role-<?=$account['id']?>">Role</label>
                <?php if((int)$account['id']===$currentUserId): ?><input type="hidden" name="role" value="super_admin"><select class="form-select" id="role-<?=$account['id']?>" disabled><option selected>Super Admin (akun Anda)</option></select><small class="form-text">Role sendiri tidak dapat diubah.</small>
                <?php else: ?><select class="form-select" id="role-<?=$account['id']?>" name="role" data-role-select required><?php foreach($roleLabels as $value=>$label): ?><option value="<?=e($value)?>" <?=$account['role']===$value?'selected':''?>><?=e($label)?></option><?php endforeach ?></select><?php endif ?>
              </div>
              <div class="col-md-6 col-xl-2"><label class="form-label" for="gudang-<?=$account['id']?>">Gudang proyek</label><select class="form-select" id="gudang-<?=$account['id']?>" name="gudang_id" data-warehouse-select <?=$account['role']==='admin_gudang'?'':'disabled'?>>
                <option value="">Pilih gudang aktif</option><?php foreach($warehouses as $warehouse): if((int)$warehouse['aktif']): ?><option value="<?=$warehouse['id']?>" <?=(int)$account['gudang_id']===(int)$warehouse['id']?'selected':''?>><?=e($warehouse['nama'])?></option><?php endif; endforeach ?>
              </select><small class="form-text">Wajib untuk Admin Gudang.</small></div>
              <div class="col-md-6 col-xl-2"><label class="form-label" for="status-<?=$account['id']?>">Status</label>
                <?php if((int)$account['id']===$currentUserId): ?><input type="hidden" name="aktif" value="1"><select class="form-select" id="status-<?=$account['id']?>" disabled><option selected>Aktif (akun Anda)</option></select><small class="form-text">Akun sendiri tidak dapat dinonaktifkan.</small>
                <?php else: ?><select class="form-select" id="status-<?=$account['id']?>" name="aktif" required><option value="1" <?=(int)$account['aktif']===1?'selected':''?>>Aktif</option><option value="0" <?=(int)$account['aktif']===0?'selected':''?>>Nonaktif</option></select><?php endif ?>
              </div>
              <div class="col-md-6 col-xl-4"><label class="form-label" for="password-<?=$account['id']?>">Kata sandi baru (opsional)</label><input class="form-control" id="password-<?=$account['id']?>" type="password" name="password" minlength="12" maxlength="200" autocomplete="new-password"><small class="form-text">Kosongkan jika tidak ingin mengganti kata sandi.</small></div>
              <div class="col-12"><button class="btn btn-primary" type="submit">Simpan perubahan</button></div>
            </form>
          </details>
          <?php endif ?>
        </article></div>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <section class="app-card card shadow-sm mb-4" id="pengelolaan-gudang">
    <div class="card-body p-3 p-lg-4">
      <span class="section-kicker">Pengelolaan gudang proyek</span>
      <h2 class="h4 fw-bold text-primary-emphasis mt-1">Gudang proyek dan tanggung jawab manajer</h2>
      <p class="text-secondary">Bagian ini hanya dapat dikelola oleh Super Admin. Penugasan manajer bersifat opsional dan tidak membatasi laporan lintas gudang.</p>
      <div class="row g-4 mt-1">
        <div class="col-xl-6">
          <h3 class="h6 fw-bold">Tambah gudang proyek</h3>
          <form method="post" action="aksi_admin.php" class="vstack gap-2">
            <input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="gudang_simpan">
            <label for="kode-gudang-proyek">Kode proyek</label><input class="form-control" id="kode-gudang-proyek" name="kode_gudang" maxlength="40" required>
            <label for="nama-gudang-proyek">Nama gudang</label><input class="form-control" id="nama-gudang-proyek" name="nama_gudang" maxlength="160" required>
            <button class="btn btn-primary align-self-start" type="submit">Tambah gudang</button>
          </form>
          <ul class="list-group list-group-flush mt-3">
            <?php foreach($warehouses as $warehouse): ?><li class="list-group-item px-0 d-flex justify-content-between"><span><?=e($warehouse['nama'])?></span><small class="text-secondary"><?=e($warehouse['kode'])?><?=!(int)$warehouse['aktif']?' · Nonaktif':''?></small></li><?php endforeach ?>
            <?php if(!$warehouses): ?><li class="list-group-item px-0 text-secondary">Belum ada gudang proyek.</li><?php endif ?>
          </ul>
        </div>
        <div class="col-xl-6">
          <h3 class="h6 fw-bold">Catatan tanggung jawab manajer</h3>
          <p class="small text-secondary">Pilih manajer dan gudang proyek aktif. Penugasan hanya menjadi catatan tanggung jawab.</p>
          <?php if($managerAccounts&&array_filter($warehouses,static fn(array $warehouse): bool => (int)$warehouse['aktif']===1)): ?>
            <form method="post" action="aksi_admin.php" class="vstack gap-2 mb-3">
              <input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="manager_proyek_simpan">
              <label for="manager-proyek-id">Akun manajer aktif</label><select class="form-select" id="manager-proyek-id" name="manager_id" required><option value="">Pilih manajer</option><?php foreach($managerAccounts as $manager): ?><option value="<?=$manager['id']?>"><?=e($manager['nama'].' · '.$manager['email'])?></option><?php endforeach ?></select>
              <label for="manager-proyek-gudang">Gudang proyek aktif</label><select class="form-select" id="manager-proyek-gudang" name="gudang_id" required><option value="">Pilih proyek</option><?php foreach($warehouses as $warehouse): if((int)$warehouse['aktif']): ?><option value="<?=$warehouse['id']?>"><?=e($warehouse['nama'])?></option><?php endif; endforeach ?></select>
              <button class="btn btn-primary align-self-start" type="submit">Simpan penugasan</button>
            </form>
          <?php else: ?><div class="alert alert-light border">Tambahkan akun Manajer aktif dan gudang proyek aktif untuk membuat catatan penugasan.</div><?php endif ?>
          <?php if($managerAssignments): ?><ul class="list-group list-group-flush"><?php foreach($managerAssignments as $assignment): ?><li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2"><span><strong class="d-block"><?=e($assignment['manager'])?></strong><small class="text-secondary"><?=e($assignment['gudang'])?> · <?=e(date('d M Y H:i',strtotime($assignment['ditugaskan_pada'])))?></small></span><form method="post" action="aksi_admin.php"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="manager_proyek_hapus"><input type="hidden" name="manager_id" value="<?=$assignment['manager_id']?>"><input type="hidden" name="gudang_id" value="<?=$assignment['gudang_id']?>"><button class="btn btn-outline-danger btn-sm" type="submit" data-confirm="Hapus catatan penugasan manajer untuk proyek ini?">Hapus penugasan</button></form></li><?php endforeach ?></ul>
          <?php else: ?><p class="small text-secondary mb-0">Belum ada catatan penugasan manajer.</p><?php endif ?>
        </div>
      </div>
    </div>
  </section>

  <section class="app-card card shadow-sm mb-4" id="akun-legacy">
    <div class="card-body p-3 p-lg-4">
      <span class="section-kicker">Akun legacy</span><h2 class="h4 fw-bold text-primary-emphasis mt-1">Klasifikasi admin gudang lama</h2>
      <p class="text-secondary">Pilih lokasi dan role berdasarkan konfirmasi perusahaan. Akun hanya diaktifkan kembali sesuai status sebelum migrasi.</p>
      <?php if($pendingAccounts): ?><div class="row g-3"><?php foreach($pendingAccounts as $account): ?>
        <div class="col-lg-6"><form method="post" action="aksi_admin.php" class="border rounded-3 p-3">
          <input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="akun_petakan"><input type="hidden" name="user_id" value="<?=$account['user_id']?>">
          <strong class="d-block"><?=e($account['nama'])?></strong><small class="text-secondary"><?=e($account['email'])?> · ID <?=e((string)$account['user_id'])?></small>
          <label class="form-label small mt-3" for="role-tujuan-<?=$account['user_id']?>">Role yang dikonfirmasi</label><select class="form-select" id="role-tujuan-<?=$account['user_id']?>" name="role_tujuan" required><option value="">Pilih role</option><option value="admin_pusat">Admin Pusat</option><option value="admin_gudang">Admin Gudang Proyek</option></select>
          <label class="form-label small mt-2" for="map-gudang-<?=$account['user_id']?>">Gudang proyek (wajib untuk Admin Gudang)</label><select class="form-select" id="map-gudang-<?=$account['user_id']?>" name="gudang_id"><option value="">Pilih bila Admin Gudang</option><?php foreach($warehouses as $warehouse): if((int)$warehouse['aktif']): ?><option value="<?=$warehouse['id']?>"><?=e($warehouse['nama'])?></option><?php endif; endforeach ?></select>
          <button class="btn btn-outline-primary btn-sm mt-3" type="submit" data-confirm="Terapkan klasifikasi ini ke akun?">Simpan klasifikasi</button>
        </form></div>
      <?php endforeach ?></div><?php else: ?><div class="alert alert-light border mb-0">Tidak ada akun admin lama yang menunggu klasifikasi.</div><?php endif ?>
    </div>
  </section>

  <section class="app-card card shadow-sm" id="riwayat">
    <div class="card-body p-3 p-lg-4">
      <span class="section-kicker">Audit akun</span><h2 class="h4 fw-bold text-primary-emphasis mt-1">Riwayat perubahan terbaru</h2>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Waktu</th><th>Perubahan</th><th>Akun</th><th>Pelaku</th><th>Rincian</th></tr></thead><tbody>
        <?php foreach($auditRows as $entry): ?><tr><td class="text-nowrap"><?=e(date('d M Y H:i',strtotime($entry['dibuat_pada'])))?></td><td><?=e($auditLabels[$entry['aksi']]??$entry['aksi'])?></td><td><?=e($entry['sasaran_nama'])?><small class="d-block text-secondary"><?=e($entry['sasaran_email'])?></small></td><td><?=e($entry['pelaku_nama'])?><?php if($entry['pelaku_email']): ?><small class="d-block text-secondary"><?=e($entry['pelaku_email'])?></small><?php endif ?></td><td><?=e($entry['detail'])?></td></tr><?php endforeach ?>
        <?php if(!$auditRows): ?><tr><td colspan="5" class="text-center text-secondary py-4">Belum ada riwayat pengelolaan akun.</td><?php endif ?>
      </tbody></table></div>
      <p class="small text-secondary mt-3 mb-0">Menampilkan paling banyak 100 perubahan terbaru. Kata sandi tidak pernah dicatat dalam riwayat.</p>
    </div>
  </section>
</main>
<script>
document.querySelectorAll('[data-role-select]').forEach(function(roleSelect){
  const warehouseSelect=roleSelect.closest('form')?.querySelector('[data-warehouse-select]');
  if(!warehouseSelect)return;
  const updateWarehouse=()=>{
    const needsWarehouse=roleSelect.value==='admin_gudang';
    warehouseSelect.disabled=!needsWarehouse;
    warehouseSelect.required=needsWarehouse;
    if(!needsWarehouse)warehouseSelect.value='';
  };
  roleSelect.addEventListener('change',updateWarehouse);
  updateWarehouse();
});
</script>
<script src="assets/app.js?v=<?= filemtime(public_path('assets/app.js')) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
