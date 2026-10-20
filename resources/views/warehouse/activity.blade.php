<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#123a68">
  <title>Aktivitas Gudang · GudangKita</title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(public_path('assets/style.css')) ?>">
</head>
<body>
<nav class="navbar navbar-expand-xl navbar-dark dashboard-navbar sticky-top shadow-sm" style="background: var(--brand-blue-dark);" aria-label="Navigasi aktivitas gudang">
  <div class="container-fluid px-lg-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $role === 'super_admin' ? 'super_admin.php' : 'dashboard.php' ?>"><img src="assets/logo.png" alt=""><span class="fw-bold">Trikasa Energi Tama</span></a>
    <div class="ms-auto">@include('warehouse.partials.account-panel', ['accountLocation' => 'Aktivitas gudang', 'accountRole' => ucfirst(str_replace('_',' ',$role)), 'accountNotificationCount' => $notificationInbox['count'], 'accountNotifications' => $notificationInbox['items']])</div>
  </div>
</nav>
<main class="container-fluid px-3 px-lg-4 py-4">
  <section class="mb-4">
    <span class="section-kicker">Jejak perubahan</span>
    <h1 class="h2 fw-bold text-primary-emphasis mt-1">Aktivitas gudang</h1>
    <p class="text-secondary mb-0">Riwayat stok, hasil pemeriksaan dan penyesuaian stok, perubahan katalog, dan—untuk Super Admin—pengelolaan akun. Daftar dibatasi hingga 200 aktivitas terbaru.</p>
  </section>
  <section class="app-card card shadow-sm mb-4">
    <div class="card-body p-3 p-lg-4">
      <form method="get" action="aktivitas.php" class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label" for="aktivitas_jenis">Jenis aktivitas</label><select class="form-select" id="aktivitas_jenis" name="jenis" onchange="this.form.submit()" aria-label="Pilih jenis aktivitas"><?php foreach($eventTypes as $value=>$label): ?><option value="<?=e($value)?>" <?=$eventType===$value?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></div>
        <div class="col-md-3"><label class="form-label" for="aktivitas_mulai">Dari tanggal</label><input class="form-control" type="date" id="aktivitas_mulai" name="tanggal_mulai" value="<?=e($start)?>" required></div>
        <div class="col-md-3"><label class="form-label" for="aktivitas_akhir">Sampai tanggal</label><input class="form-control" type="date" id="aktivitas_akhir" name="tanggal_akhir" value="<?=e($end)?>" required></div>
        <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Tampilkan aktivitas</button></div>
      </form>
    </div>
  </section>
  <section class="app-card card shadow-sm">
    <div class="card-body p-3 p-lg-4">
      <div class="d-flex justify-content-between align-items-end gap-2 mb-3"><div><span class="section-kicker">Catatan sistem</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-0">Riwayat aktivitas</h2></div><span class="text-secondary"><?=count($rows)?> aktivitas</span></div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Waktu</th><th>Aktivitas</th><th>Gudang</th><th>Barang / sasaran</th><th>Petugas</th><th>Rincian</th></tr></thead>
        <tbody>
          <?php foreach($rows as $row): ?><tr><td class="text-nowrap"><?=e(date('d M Y H:i',strtotime($row['dibuat_pada'])))?></td><td><span class="badge text-bg-primary"><?=e($row['aktivitas'])?></span></td><td><?=e($row['gudang'])?></td><td><?=e($row['subjek'])?></td><td><?=e($row['aktor'])?></td><td><?=e($row['detail'])?></td></tr><?php endforeach ?>
          <?php if(!$rows): ?><tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada aktivitas pada filter ini.</td></tr><?php endif ?>
        </tbody>
      </table></div>
    </div>
  </section>
</main>
</body>
</html>
