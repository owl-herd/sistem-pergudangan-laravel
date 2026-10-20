
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#123a68">
  <title>Pembelian · GudangKita</title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?=filemtime(public_path('assets/style.css'))?>">
</head>
<body class="warehouse-dashboard-top warehouse-dashboard-shell">
<nav class="navbar navbar-expand-xl navbar-dark dashboard-navbar dashboard-top-navigation sticky-top shadow-sm" style="background: var(--brand-blue-dark);" aria-label="Navigasi purchasing">
  <div class="container-fluid px-lg-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="purchasing.php"><img src="assets/logo.png" alt=""><span class="dashboard-brand-copy"><span class="fw-bold">Trikasa Energi Tama</span><small>Pusat kendali · Purchasing</small></span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#purchasingNavigation" aria-controls="purchasingNavigation" aria-expanded="false" aria-label="Buka navigasi purchasing"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="purchasingNavigation">
      <ul class="navbar-nav me-auto ms-lg-4 gap-lg-2 section-nav py-2 py-lg-0" aria-label="Bagian purchasing">
        <li class="nav-item"><a class="nav-link active" aria-current="location" href="#ringkasan">Ringkasan</a></li>
        <li class="nav-item"><a class="nav-link" href="#stok">Stok per gudang</a></li>
        <li class="nav-item"><a class="nav-link" href="#permintaan">Permintaan pembelian<?php if($pendingCount>0): ?> <span class="badge rounded-pill text-bg-warning ms-1"><?=$pendingCount?></span><?php endif ?></a></li>
        <li class="nav-item"><a class="nav-link" href="#notifikasi">Notifikasi<?php if($unreadNotificationCount>0): ?> <span class="badge rounded-pill text-bg-danger ms-1"><?=$unreadNotificationCount?></span><?php endif ?></a></li>
      </ul>
    </div>
  </div>
</nav>
<header class="dashboard-account-bar" aria-label="Akun dan notifikasi">
  <div class="container-fluid px-3 px-lg-4">
    <div class="dashboard-account-context">
      <span class="dashboard-page-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false"><path d="M3.5 5.5h2l2.1 10.1a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4l1.3-6.3H7" /><circle cx="10" cy="20" r="1" /><circle cx="18" cy="20" r="1" /></svg>
      </span>
      <strong>Purchasing</strong>
    </div>
    @include('warehouse.partials.account-panel', ['accountLocation' => 'Pembelian', 'accountRole' => 'Purchasing', 'accountNotificationCount' => $unreadNotificationCount, 'accountNotifications' => $unreadNotifications])
  </div>
</header>
<main class="container-fluid px-3 px-lg-4 py-4">
  <?php if($flashMessage): ?><div class="alert alert-<?=e($flashMessage[1]==='error'?'danger':$flashMessage[1])?> alert-dismissible fade show" role="alert"><?=e($flashMessage[0])?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div><?php endif ?>
  <?php if($unreadNotificationCount>0): ?>
    <section class="section-anchor app-card card shadow-sm mb-4" id="notifikasi" aria-labelledby="notificationHeading">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><span class="section-kicker">Pemberitahuan baru</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-0" id="notificationHeading">Permintaan pembelian belum dibaca</h2></div><span class="badge rounded-pill text-bg-danger"><?=$unreadNotificationCount?> belum dibaca</span></div>
        <div class="list-group">
          <?php foreach($unreadNotifications as $notification): ?>
            <article class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-3">
              <div><strong><?=e($notification['peminta'])?></strong> (<?=e($notification['pengaju_role']==='admin_pusat'?'Gudang Pusat':'Gudang Proyek')?>) mengajukan <strong><?=e($notification['kode'].' · '.$notification['barang'])?></strong> — <?=number_format((int)$notification['jumlah'])?> <?=e($notification['satuan'])?><small class="d-block text-secondary"><?=e(date('d M Y H:i',strtotime($notification['created_at'])))?></small></div>
              <div class="d-flex gap-2"><a class="btn btn-outline-primary btn-sm" href="#permintaan-<?=$notification['request_id']?>">Lihat permintaan</a><form method="post" action="notifikasi_pembelian.php/<?=$notification['notification_id']?>/baca"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><button class="btn btn-primary btn-sm" type="submit">Tandai dibaca</button></form></div>
            </article>
          <?php endforeach ?>
        </div>
        <?php if($unreadNotificationCount>count($unreadNotifications)): ?><p class="small text-secondary mt-2 mb-0">Menampilkan 20 notifikasi terbaru dari <?=$unreadNotificationCount?> notifikasi belum dibaca.</p><?php endif ?>
      </div>
    </section>
  <?php else: ?><section class="section-anchor app-card card shadow-sm mb-4" id="notifikasi"><div class="card-body p-3 p-lg-4"><h2 class="h4 fw-bold text-primary-emphasis">Notifikasi</h2><p class="text-secondary mb-0">Tidak ada notifikasi baru.</p></div></section><?php endif ?>
  <section class="section-anchor mb-4" id="ringkasan">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><span class="section-kicker">Ruang kerja · Pembelian</span><h1 class="h2 fw-bold text-primary-emphasis mt-1 mb-0">Stok dan permintaan pembelian</h1></div><span class="text-secondary"><?=e(date('d M Y'))?></span></div>
    <div class="row row-cols-2 row-cols-xl-3 g-3">
      <div class="col"><article class="app-card card stat-card h-100 shadow-sm"><div class="card-body"><span class="small text-secondary">Barang aktif</span><div class="stat-value mt-2"><?=number_format(count($items))?></div><small class="text-secondary">item di katalog</small></div></article></div>
      <div class="col"><article class="app-card card stat-card stat-yellow h-100 shadow-sm"><div class="card-body"><span class="small text-secondary">Permintaan menunggu</span><div class="stat-value mt-2"><?=$pendingCount?></div><small class="text-secondary">perlu diproses</small></div></article></div>
      <div class="col"><article class="app-card card stat-card stat-cyan h-100 shadow-sm"><div class="card-body"><span class="small text-secondary">Lokasi stok</span><div class="stat-value mt-2"><?=count(array_unique(array_column($stockRows,'gudang')))?></div><small class="text-secondary">gudang aktif</small></div></article></div>
    </div>
  </section>

  @include('warehouse.partials.location-stock-summary')

  <section class="section-anchor app-card card shadow-sm mb-4" id="stok"><div class="card-body p-3 p-lg-4">
    <div class="mb-3"><span class="section-kicker">Informasi persediaan</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-0">Stok per gudang</h2><p class="text-secondary mb-0">Informasi ini hanya untuk dibaca. Penyesuaian stok dilakukan melalui alur gudang.</p></div>
    <div class="row g-2 align-items-end mb-3">
      <div class="col-sm-6 col-lg-4"><label class="form-label small fw-semibold" for="purchasingWarehouseFilter">Pilih gudang</label><select class="form-select" id="purchasingWarehouseFilter" data-warehouse-filter aria-controls="purchasingStockTable"><option value="">Semua gudang</option><?php foreach($warehouseStockSummary as $warehouse): ?><option value="<?=e($warehouse['nama'])?>"><?=e($warehouse['nama'])?></option><?php endforeach ?></select></div>
      <div class="col-sm-6 col-lg-5"><label class="form-label small fw-semibold" for="purchasingStockSearch">Cari barang</label><input class="form-control" id="purchasingStockSearch" type="search" placeholder="Kode atau nama barang" autocomplete="off" data-warehouse-search aria-controls="purchasingStockTable"></div>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0" id="purchasingStockTable"><thead><tr><th>Gudang</th><th>Kode</th><th>Barang</th><th>Jenis</th><th>Stok tersedia</th><th>Minimum</th></tr></thead><tbody>
      <?php foreach($stockRows as $stock): ?><tr data-warehouse-row data-warehouse-name="<?=e($stock['gudang'])?>"><td><?=e($stock['gudang'])?></td><td><?=e($stock['kode'])?></td><td class="fw-semibold"><?=e($stock['barang'])?></td><td><?=e($categoryLabels[$stock['kategori_barang']]??'—')?></td><td><?=number_format((int)$stock['stok'])?> <?=e($stock['satuan'])?></td><td><?=number_format((int)$stock['stok_minimum'])?></td></tr><?php endforeach ?>
      <?php if(!$stockRows): ?><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada barang atau gudang aktif.</td></tr><?php endif ?>
    </tbody></table></div>
  </div></section>

  <section class="section-anchor app-card card shadow-sm mb-4" id="permintaan"><div class="card-body p-3 p-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><span class="section-kicker">Pengajuan dari admin proyek dan pusat</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-0">Proses permintaan pembelian</h2></div><span class="text-secondary"><?=count($requests)?> permintaan</span></div>
    <p class="text-secondary">Tinjau pengajuan lalu setujui atau tolak. Perubahan status tidak mengubah stok gudang.</p>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Waktu</th><th>Pengaju</th><th>Barang</th><th>Jumlah</th><th>Catatan</th><th>Status</th><th>Aksi / pemroses</th></tr></thead><tbody>
      <?php foreach ($requests as $request): ?>
        <tr id="permintaan-<?=$request['id']?>">
          <td class="text-nowrap"><?=e(date('d M Y H:i',strtotime($request['tanggal'])))?></td>
          <td><?=e($request['peminta'])?></td>
          <td><strong><?=e($request['kode'])?></strong><span class="d-block"><?=e($request['barang'])?></span></td>
          <td class="text-nowrap"><?=number_format((int)$request['jumlah'])?> <?=e($request['satuan'])?></td>
          <td><?=e($request['catatan'] ?: '—')?></td>
          <td>
            <span class="badge rounded-pill <?=e($statusClasses[$request['status']] ?? 'text-bg-secondary')?>"><?=e($statusLabels[$request['status']] ?? $request['status'])?></span>
            <small class="d-block text-secondary mt-1"><?=e($receiptSummary($request))?></small>
            <a class="small d-inline-block mt-1" href="detail_penerimaan_pembelian.php?id=<?=e((string)$request['id'])?>">Lihat ringkasan dan riwayat penerimaan</a>
          </td>
          <td>
            <?php if ($request['status'] === 'menunggu' && (int)$request['user_id'] !== (int)$currentUser['id']): ?>
              <div class="d-flex gap-2">
                <form method="post" action="aksi_pembelian.php"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="permintaan_setujui"><input type="hidden" name="permintaan_id" value="<?=$request['id']?>"><button class="btn btn-primary btn-sm" type="submit" data-confirm="Setujui permintaan ini? Stok tidak akan berubah.">Setujui</button></form>
                <form method="post" action="aksi_pembelian.php"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="permintaan_tolak"><input type="hidden" name="permintaan_id" value="<?=$request['id']?>"><button class="btn btn-outline-danger btn-sm" type="submit" data-confirm="Tolak permintaan ini?">Tolak</button></form>
              </div>
            <?php elseif ($request['status'] === 'menunggu'): ?>
              Tidak dapat memutuskan pengajuan sendiri.
            <?php else: ?>
              <?=e($request['pemroses'] ?: '—')?>
              <?php if ($request['diproses_pada']): ?><small class="d-block text-secondary"><?=e(date('d M Y H:i',strtotime($request['diproses_pada'])))?></small><?php endif ?>
              <?php if ($request['status'] === 'disetujui'): ?><a class="btn btn-outline-primary btn-sm mt-2" href="bukti_persetujuan.php?id=<?=e((string)$request['id'])?>">Lihat/Cetak Bukti Persetujuan</a><?php endif ?>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if(!$requests): ?><tr><td colspan="7" class="text-center text-secondary py-4">Belum ada pengajuan pembelian.</td></tr><?php endif ?>
    </tbody></table></div>
  </div></section>
  <footer class="site-footer rounded-3 p-3 mt-4 text-center">Trikasa Energi Tama · Informasi pergudangan</footer>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script defer src="assets/app.js?v=<?=filemtime(public_path('assets/app.js'))?>"></script>
</body>
</html>
