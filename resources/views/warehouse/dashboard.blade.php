
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#123a68">
  <title>Dashboard · GudangKita</title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(public_path('assets/style.css')) ?>">
</head>
<body class="warehouse-dashboard-top">
<nav class="navbar navbar-expand-xl navbar-dark dashboard-navbar dashboard-top-navigation sticky-top shadow-sm" style="background: var(--brand-blue-dark);" aria-label="Navigasi dashboard">
  <div class="container-fluid px-lg-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php"><img src="assets/logo.png" alt=""><span class="fw-bold">Trikasa Energi Tama</span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#dashboardNavigation" aria-controls="dashboardNavigation" aria-expanded="false" aria-label="Buka navigasi dashboard"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="dashboardNavigation">
      <ul class="navbar-nav me-auto ms-lg-4 gap-lg-2 section-nav py-2 py-lg-0">
        <li class="nav-item"><a class="nav-link active" aria-current="location" href="#ringkasan">Ringkasan</a></li>
        <li class="nav-item"><a class="nav-link" href="#stok">Stok gudang</a></li>
        <?php if($role==='admin_gudang'): ?><li class="nav-item"><a class="nav-link" href="#penambahan">Tambah barang</a></li><li class="nav-item"><a class="nav-link" href="#permintaan">Permintaan</a></li><li class="nav-item"><a class="nav-link" href="#pengajuan-pembelian">Pengajuan pembelian</a></li><li class="nav-item"><a class="nav-link" href="#pengiriman">Penerimaan</a></li><li class="nav-item"><a class="nav-link" href="#pengembalian-peralatan">Retur peralatan</a></li><li class="nav-item"><a class="nav-link" href="#pemakaian">Pemakaian</a></li><?php endif ?>
        <?php if($role==='admin_pusat'): ?><li class="nav-item"><a class="nav-link" href="#penambahan">Barang proyek</a></li><li class="nav-item"><a class="nav-link" href="#pengembalian-peralatan">Retur peralatan</a></li><li class="nav-item"><a class="nav-link" href="#permintaan">Permintaan <span class="badge rounded-pill text-bg-warning ms-1"><?=$pending?></span></a></li><li class="nav-item"><a class="nav-link" href="#pengajuan-pembelian">Pengajuan pembelian</a></li><li class="nav-item"><a class="nav-link" href="#katalog">Katalog</a></li><?php endif ?>
        <?php if($role==='manager'): ?><li class="nav-item"><a class="nav-link" href="#penambahan">Penambahan proyek</a></li><li class="nav-item"><a class="nav-link" href="#pengembalian-peralatan">Kondisi peralatan</a></li><li class="nav-item"><a class="nav-link" href="#permintaan">Permintaan</a></li><li class="nav-item"><a class="nav-link" href="#permintaan-pembelian">Permintaan pembelian <span class="badge rounded-pill text-bg-warning ms-1"><?=$pendingPurchasingRequests?></span></a></li><li class="nav-item"><a class="nav-link" href="#pengiriman">Pengiriman</a></li><?php endif ?>
        <li class="nav-item"><a class="nav-link" href="#mutasi">Riwayat &amp; laporan</a></li>
      </ul>
      @include('warehouse.partials.account-panel', ['accountLocation' => $warehouseName, 'accountRole' => $roleLabel, 'accountNotificationCount' => $notificationInbox['count']])
    </div>
  </div>
</nav>
<main class="container-fluid px-3 px-lg-4 py-4">
@include('warehouse.notifications')
  <?php if($flashMessage): ?><div class="alert alert-<?=e($flashMessage[1]==='error'?'danger':$flashMessage[1])?> alert-dismissible fade show" role="alert"><?=e($flashMessage[0])?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div><?php endif ?>
  <section class="section-anchor mb-4" id="ringkasan">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><span class="section-kicker">Ruang kerja · Pergudangan</span><h1 class="h2 fw-bold text-primary-emphasis mt-1 mb-0">Ringkasan <?=e($warehouseName)?></h1></div><span class="text-secondary"><?=e(date('d M Y'))?></span></div>
    <div class="row row-cols-2 row-cols-xl-4 g-3">
      <div class="col"><article class="app-card card stat-card h-100 shadow-sm"><div class="card-body"><span class="small text-secondary">Jenis barang aktif</span><div class="stat-value mt-2"><?=number_format((int)$inventory['jenis'])?></div><small class="text-secondary">item terdaftar</small></div></article></div>
      <div class="col"><article class="app-card card stat-card stat-cyan h-100 shadow-sm"><div class="card-body"><span class="small text-secondary">Stok tersedia</span><div class="stat-value mt-2"><?=number_format((int)$inventory['unit'])?></div><small class="text-secondary">unit di lokasi yang terlihat</small></div></article></div>
      <div class="col"><article class="app-card card stat-card stat-yellow h-100 shadow-sm"><div class="card-body"><span class="small text-secondary">Permintaan menunggu</span><div class="stat-value mt-2"><?=$pending?></div><small class="text-secondary">belum diproses</small></div></article></div>
      <div class="col"><article class="app-card card stat-card stat-red h-100 shadow-sm"><div class="card-body"><span class="small text-secondary">Stok minimum</span><div class="stat-value mt-2"><?=number_format((int)$inventory['rendah'])?></div><small class="text-secondary">item per gudang</small></div></article></div>
    </div>
    <?php if((int)$inventory['rendah']>0): ?><div class="alert alert-warning mt-3 mb-0" role="alert"><?=number_format((int)$inventory['rendah'])?> stok barang berada pada atau di bawah batas minimum.</div><?php endif ?>
  </section>

  @include('warehouse.report-selector')
  <section class="section-anchor app-card card shadow-sm mb-4" id="analisis">
    <div class="card-body p-3 p-lg-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
        <div><span class="section-kicker">Analisis periode</span><h2 class="h4 fw-bold text-primary-emphasis mt-1">Pergerakan stok · <?=e($warehouseName)?></h2><p class="text-secondary mb-0">Pengiriman keluar dan penerimaan proyek ditampilkan sebagai peristiwa terpisah; barang dalam perjalanan tidak masuk stok gudang.</p></div>
        <form class="row row-cols-1 row-cols-sm-auto g-2 align-items-end" method="get">
          <div class="col"><label class="form-label small fw-semibold" for="tanggal_mulai">Dari tanggal</label><input class="form-control" id="tanggal_mulai" type="date" name="tanggal_mulai" value="<?=e($start)?>" max="<?=e($end)?>" required></div>
          <div class="col"><label class="form-label small fw-semibold" for="tanggal_akhir">Sampai tanggal</label><input class="form-control" id="tanggal_akhir" type="date" name="tanggal_akhir" value="<?=e($end)?>" min="<?=e($start)?>" required></div>
          <div class="col"><button type="submit" class="btn btn-primary w-100">Tampilkan</button></div>
          
        </form>
      </div>
      <div class="row row-cols-2 row-cols-lg-5 g-2 mt-3">
        <div class="col"><div class="analysis-stat analysis-stat-in rounded-3 p-3 h-100"><small class="analysis-stat-label">Barang masuk</small><div class="h5 fw-bold mt-1 mb-0 analysis-stat-value"><?=number_format($movementTotals['masuk'])?> <small class="fw-normal analysis-stat-unit">unit</small></div></div></div>
        <div class="col"><div class="analysis-stat analysis-stat-out rounded-3 p-3 h-100"><small class="analysis-stat-label">Dikirim keluar</small><div class="h5 fw-bold mt-1 mb-0 analysis-stat-value"><?=number_format($movementTotals['keluar'])?> <small class="fw-normal analysis-stat-unit">unit</small></div></div></div>
        <div class="col"><div class="analysis-stat analysis-stat-return rounded-3 p-3 h-100"><small class="analysis-stat-label">Pemakaian proyek</small><div class="h5 fw-bold mt-1 mb-0 analysis-stat-value"><?=number_format($movementTotals['pemakaian'])?> <small class="fw-normal analysis-stat-unit">unit</small></div></div></div>
        <div class="col"><div class="analysis-stat analysis-stat-net rounded-3 p-3 h-100"><small class="analysis-stat-label">Barang kembali</small><div class="h5 fw-bold mt-1 mb-0 analysis-stat-value"><?=number_format($movementTotals['kembali'])?> <small class="fw-normal analysis-stat-unit">unit</small></div></div></div>
        <div class="col"><div class="analysis-stat analysis-stat-pending rounded-3 p-3 h-100"><small class="analysis-stat-label">Permintaan menunggu</small><div class="h5 fw-bold mt-1 mb-0 analysis-stat-value"><?=$pending?> <small class="fw-normal analysis-stat-unit">permintaan</small></div></div></div>
      </div>
    </div>
  </section>

  <section class="section-anchor app-card card shadow-sm mb-4" id="stok">
    <div class="card-body p-3 p-lg-4"><div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><span class="section-kicker">Stok tersedia</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-0">Stok per gudang</h2></div><span class="text-secondary"><?=count($stockRows)?> baris stok</span></div>
      <?php if($role==='manager'): ?><form method="get" action="dashboard.php#stok" class="row g-2 align-items-end mb-3" data-auto-submit><div class="col-sm-8 col-lg-5"><label class="form-label small fw-semibold" for="gudang_id">Pilih gudang</label><select class="form-select" id="gudang_id" name="gudang_id" required><option value="">Pilih gudang</option><?php foreach($managerWarehouses as $managerWarehouse): ?><option value="<?=$managerWarehouse['id']?>" <?=$selectedManagerWarehouseId===(int)$managerWarehouse['id']?'selected':''?>><?=e($managerWarehouse['nama'])?></option><?php endforeach ?></select></div></form><?php endif ?>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><?php if($role==='manager'): ?><th>Gudang</th><?php endif ?><th>Kode</th><th>Barang</th><th>Jenis</th><th>Stok tersedia</th><th>Minimum</th></tr></thead><tbody>
      <?php foreach($stockRows as $stock): ?><tr><?php if($role==='manager'): ?><td><?=e($stock['gudang'])?></td><?php endif ?><td><?=e($stock['kode'])?></td><td class="fw-semibold"><?=e($stock['nama'])?></td><td><?=e($categoryLabels[$stock['kategori_barang']]??'—')?></td><td><?=number_format((int)$stock['stok'])?> <?=e($stock['satuan'])?></td><td><?=number_format((int)$stock['stok_minimum'])?></td></tr><?php endforeach ?>
      <?php if(!$stockRows): ?><tr><td colspan="<?= $role==='manager'?6:5 ?>" class="text-center text-secondary py-4"><?=$role==='manager'&&$selectedManagerWarehouseId===0?'Pilih gudang untuk melihat stok.':'Belum ada stok barang.'?></td></tr><?php endif ?>
      </tbody></table></div>
    </div>
  </section>

  <?php if($role==='admin_pusat'): ?>
  <section class="section-anchor app-card card shadow-sm mb-4" id="penerimaan-pusat"><div class="card-body p-3 p-lg-4"><span class="section-kicker">Gudang Pusat</span><h2 class="h4 fw-bold text-primary-emphasis mt-1">Catat barang masuk dari supplier</h2><p class="text-secondary">Penerimaan hanya menambah stok Gudang Pusat.</p>
    <form method="post" action="aksi_admin.php" class="row g-3"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="barang_masuk">
      <div class="col-md-6 col-xl-2"><label class="form-label" for="nomor_transaksi">No. transaksi</label><input class="form-control" id="nomor_transaksi" name="nomor_transaksi" maxlength="50" required></div>
      <div class="col-md-6 col-xl-2"><label class="form-label" for="tanggal_masuk">Tanggal</label><input class="form-control" id="tanggal_masuk" type="date" name="tanggal_masuk" value="<?=e($today)?>" required></div>
      <div class="col-md-6 col-xl-2"><label class="form-label" for="nama_supplier">Supplier</label><input class="form-control" id="nama_supplier" name="nama_supplier" maxlength="150" required></div>
      <div class="col-md-6 col-xl-2"><label class="form-label" for="barang_masuk">Barang</label><select class="form-select" id="barang_masuk" name="barang_id" required><option value="">Pilih barang</option><?php foreach($goods as $g): ?><option value="<?=$g['id']?>"><?=e($g['kode'].' · '.$g['nama'])?></option><?php endforeach ?></select></div>
      <div class="col-md-6 col-xl-2"><label class="form-label" for="jumlah_masuk">Jumlah diterima</label><input class="form-control" id="jumlah_masuk" type="number" name="jumlah" min="1" step="1" required></div>
      <div class="col-md-6 col-xl-2"><label class="form-label" for="referensi_masuk">Catatan</label><input class="form-control" id="referensi_masuk" name="referensi" maxlength="255"></div>
      <div class="col-12"><button class="btn btn-primary" type="submit">Simpan penerimaan</button></div>
    </form>
  </div></section>
  <?php endif ?>

  <section class="section-anchor app-card card shadow-sm mb-4" id="penambahan"><div class="card-body p-3 p-lg-4">
    <div class="mb-3"><span class="section-kicker">Stok proyek</span><h2 class="h4 fw-bold text-primary-emphasis mt-1">Penambahan barang proyek</h2><p class="text-secondary mb-0"><?=$role==='admin_pusat'?'Pantau penambahan yang dicatat oleh admin proyek. Setiap baris memperlihatkan proyek, barang, jumlah, petugas, waktu, dan keterangannya.':($role==='admin_gudang'?'Pilih barang dari katalog yang sudah ada. Stok proyek dan riwayat mutasi diperbarui dalam satu transaksi.':'Riwayat menampilkan penambahan dari seluruh gudang proyek.')?> Daftar mengikuti periode analisis di atas.</p></div>
    <?php if($role==='admin_gudang'): ?><form method="post" action="aksi_admin.php" class="row g-3 mb-4"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="penambahan_proyek_simpan">
      <div class="col-md-6 col-xl-4"><label class="form-label" for="barang_penambahan">Barang terdaftar</label><select class="form-select" id="barang_penambahan" name="barang_id" required><option value="">Pilih barang</option><?php foreach($goods as $g): ?><option value="<?=$g['id']?>"><?=e($g['kode'].' · '.$g['nama'].' (stok saat ini '.number_format((int)$g['stok']).' '.$g['satuan'].')')?></option><?php endforeach ?></select></div>
      <div class="col-md-3 col-xl-2"><label class="form-label" for="jumlah_penambahan">Jumlah ditambahkan</label><input class="form-control" id="jumlah_penambahan" type="number" name="jumlah" min="1" max="4294967295" step="1" required></div>
      <div class="col-md-9 col-xl-4"><label class="form-label" for="catatan_penambahan">Sumber atau keterangan</label><input class="form-control" id="catatan_penambahan" name="catatan_penambahan" maxlength="255" required></div>
      <div class="col-md-3 col-xl-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Catat penambahan</button></div>
    </form><?php endif ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Waktu</th><?php if($role!=='admin_gudang'): ?><th>Proyek</th><?php endif ?><th>Barang</th><th>Jumlah</th><th>Keterangan</th><th>Ditambahkan oleh</th></tr></thead><tbody>
      <?php foreach($projectAdditions as $addition): ?><tr><td class="text-nowrap"><?=e(date('d M Y H:i',strtotime($addition['ditambahkan_pada'])))?></td><?php if($role!=='admin_gudang'): ?><td><?=e($addition['gudang'])?></td><?php endif ?><td><strong><?=e($addition['kode'])?></strong><span class="d-block"><?=e($addition['barang'])?></span></td><td class="text-nowrap"><?=number_format((int)$addition['jumlah'])?> <?=e($addition['satuan'])?></td><td><?=e($addition['catatan'])?></td><td><?=e($addition['petugas'])?></td></tr><?php endforeach ?>
      <?php if(!$projectAdditions): ?><tr><td colspan="<?=$role==='admin_gudang'?5:6?>" class="text-center text-secondary py-4">Belum ada penambahan barang pada periode ini.</td></tr><?php endif ?>
    </tbody></table></div>
  </div></section>

  <section class="section-anchor app-card card shadow-sm mb-4" id="pengembalian-peralatan"><div class="card-body p-3 p-lg-4">
    <div class="mb-3"><span class="section-kicker">Riwayat kondisi peralatan</span><h2 class="h4 fw-bold text-primary-emphasis mt-1">Pengiriman kembali ke Gudang Pusat</h2><p class="text-secondary mb-0"><?=$role==='admin_pusat'?'Tinjau catatan kondisi dan konfirmasi penerimaan. Stok pusat bertambah setelah konfirmasi.':($role==='admin_gudang'?'Catatan kondisi wajib diisi sebelum peralatan dikirim. Stok proyek berkurang saat dikirim, lalu status menunggu penerimaan admin pusat.':'Catatan kondisi dan status pengembalian dari seluruh gudang proyek.')?></p></div>
    <?php if($role==='admin_gudang'): ?><form method="post" action="aksi_admin.php" class="row g-3 mb-4"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="peralatan_kirim_kembali">
      <div class="col-md-6 col-xl-4"><label class="form-label" for="peralatan_kembali">Peralatan dari stok proyek</label><select class="form-select" id="peralatan_kembali" name="barang_id" required><option value="">Pilih peralatan</option><?php foreach($equipmentGoods as $g): ?><option value="<?=$g['id']?>"><?=e($g['kode'].' · '.$g['nama'].' (tersedia '.number_format((int)$g['stok']).' '.$g['satuan'].')')?></option><?php endforeach ?></select></div>
      <div class="col-md-3 col-xl-2"><label class="form-label" for="jumlah_peralatan_kembali">Jumlah dikirim</label><input class="form-control" id="jumlah_peralatan_kembali" type="number" name="jumlah" min="1" step="1" required></div>
      <div class="col-md-9 col-xl-4"><label class="form-label" for="catatan_kondisi">Catatan kondisi</label><textarea class="form-control" id="catatan_kondisi" name="catatan_kondisi" rows="2" maxlength="2000" required></textarea></div>
      <div class="col-md-3 col-xl-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit" data-confirm="Kirim peralatan kembali dan kurangi stok proyek?">Kirim kembali</button></div>
    </form><?php endif ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Waktu kirim</th><?php if($role!=='admin_gudang'): ?><th>Proyek</th><?php endif ?><th>Peralatan</th><th>Jumlah</th><th>Catatan kondisi</th><th>Admin proyek</th><th>Status / penerimaan</th></tr></thead><tbody>
      <?php foreach($equipmentReturns as $return): ?><tr><td class="text-nowrap"><?=e(date('d M Y H:i',strtotime($return['dikirim_pada'])))?></td><?php if($role!=='admin_gudang'): ?><td><?=e($return['gudang'])?></td><?php endif ?><td><strong><?=e($return['kode'])?></strong><span class="d-block"><?=e($return['barang'])?></span></td><td class="text-nowrap"><?=number_format((int)$return['jumlah'])?> <?=e($return['satuan'])?></td><td class="text-break"><?=e($return['catatan_kondisi'])?></td><td><?=e($return['pengirim'])?></td><td><span class="badge rounded-pill <?=$return['status']==='menunggu_diterima'?'text-bg-warning':'text-bg-success'?>"><?=$return['status']==='menunggu_diterima'?'Menunggu diterima admin pusat':'Diterima admin pusat'?></span><?php if($return['diterima_pada']): ?><small class="d-block mt-1"><?=e(date('d M Y H:i',strtotime($return['diterima_pada'])))?> · <?=e($return['penerima']?:'')?></small><?php endif ?>
        <?php if($role==='admin_pusat'&&$return['status']==='menunggu_diterima'): ?><form method="post" action="aksi_admin.php" class="mt-2"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="peralatan_kembali_konfirmasi"><input type="hidden" name="pengembalian_peralatan_id" value="<?=$return['id']?>"><button class="btn btn-primary btn-sm" type="submit" data-confirm="Konfirmasi penerimaan peralatan dan tambahkan ke stok Gudang Pusat?">Konfirmasi diterima</button></form><?php endif ?></td></tr><?php endforeach ?>
      <?php if(!$equipmentReturns): ?><tr><td colspan="<?=$role==='admin_gudang'?6:7?>" class="text-center text-secondary py-4">Belum ada riwayat pengembalian peralatan.</td></tr><?php endif ?>
    </tbody></table></div>
  </div></section>

  <?php if($role==='admin_gudang'): ?>
  <section class="section-anchor app-card card shadow-sm mb-4" id="permintaan-baru"><div class="card-body p-3 p-lg-4"><span class="section-kicker">Permintaan ke Gudang Pusat</span><h2 class="h4 fw-bold text-primary-emphasis mt-1">Ajukan barang untuk proyek</h2><p class="text-secondary">Barang belum menambah stok proyek saat diajukan atau dikirim. Stok bertambah setelah Anda mengonfirmasi jumlah yang diterima.</p>
    <form method="post" action="aksi_admin.php" class="row g-3"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="permintaan_simpan">
      <div class="col-md-6 col-xl-4"><label class="form-label" for="barang_permintaan">Barang</label><select class="form-select" id="barang_permintaan" name="barang_id" required><option value="">Pilih barang</option><?php foreach($goods as $g): ?><option value="<?=$g['id']?>"><?=e($g['kode'].' · '.$g['nama'].' ('.$g['satuan'].')')?></option><?php endforeach ?></select></div>
      <div class="col-md-4 col-xl-2"><label class="form-label" for="jumlah_permintaan">Jumlah diminta</label><input class="form-control" id="jumlah_permintaan" type="number" name="jumlah" min="1" step="1" required></div>
      <div class="col-md-8 col-xl-4"><label class="form-label" for="catatan_permintaan">Catatan</label><input class="form-control" id="catatan_permintaan" name="catatan" maxlength="2000"></div>
      <div class="col-md-4 col-xl-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Kirim permintaan</button></div>
    </form>
  </div></section>
  <section class="section-anchor app-card card shadow-sm mb-4" id="pemakaian"><div class="card-body p-3 p-lg-4"><span class="section-kicker">Operasional proyek</span><h2 class="h4 fw-bold text-primary-emphasis mt-1">Catat pemakaian barang</h2><p class="text-secondary">Pemakaian mengurangi stok gudang proyek dan masuk ke riwayat mutasi.</p>
    <form method="post" action="aksi_admin.php" class="row g-3"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="pemakaian_simpan">
      <div class="col-md-6 col-xl-4"><label class="form-label" for="barang_pemakaian">Barang</label><select class="form-select" id="barang_pemakaian" name="barang_id" required><option value="">Pilih barang</option><?php foreach($goods as $g): if((int)$g['stok']<1)continue; ?><option value="<?=$g['id']?>"><?=e($g['kode'].' · '.$g['nama'].' — tersedia '.number_format((int)$g['stok']).' '.$g['satuan'])?></option><?php endforeach ?></select></div>
      <div class="col-md-4 col-xl-2"><label class="form-label" for="jumlah_pemakaian">Jumlah dipakai</label><input class="form-control" id="jumlah_pemakaian" type="number" name="jumlah" min="1" step="1" required></div>
      <div class="col-md-8 col-xl-4"><label class="form-label" for="catatan_pemakaian">Keterangan</label><input class="form-control" id="catatan_pemakaian" name="catatan" maxlength="255"></div>
      <div class="col-md-4 col-xl-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Simpan pemakaian</button></div>
    </form>
  </div></section>
  <?php endif ?>

  <section class="section-anchor app-card card shadow-sm mb-4" id="permintaan"><div class="card-body p-3 p-lg-4"><div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><span class="section-kicker">Permintaan proyek</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-0">Permintaan barang</h2></div><span class="text-secondary"><?=$pending?> menunggu</span></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Waktu</th><th>Proyek</th><?php if($role!=='admin_gudang'): ?><th>Peminta</th><?php endif ?><th>Barang</th><th>Status</th><?php if($role==='admin_pusat'): ?><th>Aksi</th><?php endif ?></tr></thead><tbody>
    <?php foreach($requestRows as $request): ?><tr><td class="text-nowrap"><?=e(date('d M Y H:i',strtotime($request['tanggal'])))?></td><td><?=e($request['gudang_tujuan']?:$request['nama_proyek']?:'—')?></td><?php if($role!=='admin_gudang'): ?><td><?=e($request['peminta'])?></td><?php endif ?><td><?=e($request['barang']?:'—')?></td><td><span class="badge rounded-pill status <?=e($request['status'])?>"><?=e($statusLabels[$request['status']]??$request['status'])?><?php if($request['status_pengiriman']): ?> · <?=e($shipmentLabels[$request['status_pengiriman']]??$request['status_pengiriman'])?><?php endif ?></span></td><?php if($role==='admin_pusat'): ?><td><?php if($request['status']==='diajukan'): ?><div class="d-flex gap-2"><form method="post" action="aksi_admin.php"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="permintaan_kirim"><input type="hidden" name="permintaan_id" value="<?=$request['id']?>"><div class="mb-2"><label class="form-label small" for="kendaraan-<?=(int)$request['id']?>">Nomor kendaraan</label><input class="form-control form-control-sm" id="kendaraan-<?=(int)$request['id']?>" name="nomor_kendaraan" maxlength="40" placeholder="Contoh: B 1234 ABC" required></div><div class="mb-2"><label class="form-label small" for="sopir-<?=(int)$request['id']?>">Nama sopir</label><input class="form-control form-control-sm" id="sopir-<?=(int)$request['id']?>" name="nama_driver" maxlength="120" required></div><button class="btn btn-primary btn-sm" type="submit" data-confirm="Kirim seluruh rincian permintaan dari Gudang Pusat?">Kirim barang</button></form><form method="post" action="aksi_admin.php"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="permintaan_tolak"><input type="hidden" name="permintaan_id" value="<?=$request['id']?>"><button class="btn btn-outline-danger btn-sm" type="submit" data-confirm="Tolak permintaan ini?">Tolak</button></form></div><?php else: ?>—<?php endif ?></td><?php endif ?></tr><?php endforeach ?>
    <?php if(!$requestRows): ?><tr><td colspan="<?= $role==='admin_pusat'?6:($role==='admin_gudang'?4:5) ?>" class="text-center text-secondary py-4">Belum ada permintaan pada periode ini.</td></tr><?php endif ?>
    </tbody></table></div>
  </div></section>

  <?php if($role!=='manager'): ?><section class="section-anchor app-card card shadow-sm mb-4" id="pengajuan-pembelian"><div class="card-body p-3 p-lg-4">
    <span class="section-kicker">Pengajuan ke Purchasing</span><h2 class="h4 fw-bold text-primary-emphasis mt-1">Ajukan pembelian barang</h2><p class="text-secondary">Purchasing akan memproses pengajuan ini. Pengajuan tidak mengubah stok gudang.</p>
    <form method="post" action="aksi_pembelian.php" class="row g-3 mb-4"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="permintaan_simpan">
      <div class="col-md-6 col-xl-4"><label class="form-label" for="barang_pembelian">Barang</label><select class="form-select" id="barang_pembelian" name="barang_id" required><option value="">Pilih barang</option><?php foreach($goods as $item): ?><option value="<?=$item['id']?>"><?=e($item['kode'].' · '.$item['nama'].' ('.$item['satuan'].')')?></option><?php endforeach ?></select></div>
      <div class="col-md-3 col-xl-2"><label class="form-label" for="jumlah_pembelian">Jumlah</label><input class="form-control" id="jumlah_pembelian" type="text" name="jumlah" inputmode="numeric" pattern="[0-9]{1,10}" maxlength="10" placeholder="Masukkan jumlah" title="Masukkan bilangan bulat positif" required></div>
      <div class="col-md-9 col-xl-4"><label class="form-label" for="catatan_pembelian">Catatan</label><textarea class="form-control" id="catatan_pembelian" name="catatan" rows="2" maxlength="2000"></textarea></div>
      <div class="col-md-3 col-xl-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Kirim pengajuan</button></div>
    </form>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><h3 class="h5 fw-bold text-primary-emphasis mb-0">Status pengajuan saya</h3><div class="d-flex flex-wrap align-items-center gap-2"><span class="text-secondary"><?=$pendingPurchasingRequests?> menunggu diproses</span><a class="btn btn-outline-primary btn-sm" href="penerimaan_pembelian.php">Buka penerimaan pembelian</a></div></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Waktu</th><th>Barang</th><th>Jumlah</th><th>Catatan</th><th>Status</th><th>Diproses oleh</th></tr></thead><tbody>
      <?php foreach ($purchasingRequests as $purchaseRequest): ?>
        <tr>
          <td class="text-nowrap"><?=e(date('d M Y H:i',strtotime($purchaseRequest['tanggal'])))?></td>
          <td><strong><?=e($purchaseRequest['kode'])?></strong><span class="d-block"><?=e($purchaseRequest['barang'])?></span></td>
          <td class="text-nowrap"><?=number_format((int)$purchaseRequest['jumlah'])?> <?=e($purchaseRequest['satuan'])?></td>
          <td><?=e($purchaseRequest['catatan'] ?: '—')?></td>
          <td>
            <span class="badge rounded-pill <?=e($purchasingStatusClasses[$purchaseRequest['status']] ?? 'text-bg-secondary')?>"><?=e($purchasingStatusLabels[$purchaseRequest['status']] ?? $purchaseRequest['status'])?></span>
            <?php if ($purchaseRequest['status'] === 'disetujui'): ?>
              <small class="d-block text-secondary mt-1"><?=e($receiptSummary($purchaseRequest))?></small>
              <?php if ((int)$purchaseRequest['jumlah_diterima'] < (int)$purchaseRequest['jumlah']): ?><a class="btn btn-primary btn-sm mt-2" href="penerimaan_pembelian.php#pembelian-<?=(int)$purchaseRequest['id']?>">Catat penerimaan</a><?php endif ?>
            <?php endif ?>
          </td>
          <td><?=e($purchaseRequest['pemroses'] ?: '—')?><?php if ($purchaseRequest['diproses_pada']): ?><small class="d-block text-secondary"><?=e(date('d M Y H:i',strtotime($purchaseRequest['diproses_pada'])))?></small><?php endif ?><?php if ($purchaseRequest['status'] === 'disetujui'): ?><a class="btn btn-outline-primary btn-sm mt-2" href="bukti_persetujuan.php?id=<?=(int)$purchaseRequest['id']?>">Lihat/Cetak Bukti Persetujuan</a><?php endif ?></td>
        </tr>
      <?php endforeach ?>
      <?php if(!$purchasingRequests): ?><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada pengajuan pembelian.</td><?php endif ?>
    </tbody></table></div>
  </div></section><?php endif ?>

  <?php if($role==='manager'): ?><section class="section-anchor app-card card shadow-sm mb-4" id="permintaan-pembelian"><div class="card-body p-3 p-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><span class="section-kicker">Informasi untuk manager</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-0">Pengajuan pembelian</h2></div><span class="text-secondary"><?=$pendingPurchasingRequests?> menunggu diproses Purchasing</span></div>
    <p class="text-secondary">Manager dapat memantau pengajuan dan statusnya serta membaca ringkasan dan riwayat penerimaan untuk permintaan yang disetujui. Pengajuan diproses oleh Purchasing.</p>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Waktu</th><th>Pengaju</th><th>Barang</th><th>Jumlah</th><th>Catatan</th><th>Status</th><th>Diproses oleh</th></tr></thead><tbody>
      <?php foreach ($purchasingRequests as $purchaseRequest): ?>
        <tr>
          <td class="text-nowrap"><?=e(date('d M Y H:i',strtotime($purchaseRequest['tanggal'])))?></td>
          <td><?=e($purchaseRequest['peminta'])?></td>
          <td><strong><?=e($purchaseRequest['kode'])?></strong><span class="d-block"><?=e($purchaseRequest['barang'])?></span></td>
          <td class="text-nowrap"><?=number_format((int)$purchaseRequest['jumlah'])?> <?=e($purchaseRequest['satuan'])?></td>
          <td><?=e($purchaseRequest['catatan'] ?: '—')?></td>
          <td>
            <span class="badge rounded-pill <?=e($purchasingStatusClasses[$purchaseRequest['status']] ?? 'text-bg-secondary')?>"><?=e($purchasingStatusLabels[$purchaseRequest['status']] ?? $purchaseRequest['status'])?></span>
            <?php if ($purchaseRequest['status'] === 'disetujui'): ?><small class="d-block text-secondary mt-1"><?=e($receiptSummary($purchaseRequest))?></small><a class="btn btn-outline-primary btn-sm mt-2" href="detail_penerimaan_pembelian.php?id=<?=(int)$purchaseRequest['id']?>">Lihat ringkasan dan riwayat penerimaan</a><?php endif ?>
          </td>
          <td><?=e($purchaseRequest['pemroses'] ?: '—')?><?php if ($purchaseRequest['diproses_pada']): ?><small class="d-block text-secondary"><?=e(date('d M Y H:i',strtotime($purchaseRequest['diproses_pada'])))?></small><?php endif ?><?php if ($purchaseRequest['status'] === 'disetujui'): ?><a class="btn btn-outline-primary btn-sm mt-2" href="bukti_persetujuan.php?id=<?=(int)$purchaseRequest['id']?>">Lihat/Cetak Bukti Persetujuan</a><?php endif ?></td>
        </tr>
      <?php endforeach ?>
      <?php if(!$purchasingRequests): ?><tr><td colspan="7" class="text-center text-secondary py-4">Belum ada permintaan pembelian.</td></tr><?php endif ?>
    </tbody></table></div>
  </div></section><?php endif ?>

  <section class="section-anchor app-card card shadow-sm mb-4" id="pengiriman"><div class="card-body p-3 p-lg-4"><div class="mb-3"><span class="section-kicker">Riwayat pengiriman</span><h2 class="h4 fw-bold text-primary-emphasis mt-1">Pengiriman dan penerimaan</h2><p class="text-secondary mb-0">Stok pusat berkurang saat barang dikirim. Stok proyek hanya bertambah setelah penerimaan dikonfirmasi.</p></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>No.</th><th>Gudang asal</th><th>Gudang tujuan</th><th>Status</th><th>Dikirim</th><th>Diterima</th><th>Rincian / konfirmasi</th></tr></thead><tbody>
    <?php foreach($shipments as $shipment): ?><tr><td>#<?=$shipment['id']?> <small class="d-block text-secondary">Permintaan #<?=$shipment['permintaan_id']?></small></td><td><?=e($shipment['gudang_asal'])?></td><td><?=e($shipment['gudang_tujuan'])?></td><td><span class="badge rounded-pill <?=$shipment['status']==='dalam_perjalanan'?'text-bg-warning':'text-bg-success'?>"><?=e($shipmentLabels[$shipment['status']]??$shipment['status'])?></span></td><td><?=e(date('d M Y H:i',strtotime($shipment['dikirim_pada'])))?><small class="d-block text-secondary"><?=e($shipment['pengirim'])?></small></td><td><?=$shipment['diterima_pada']?e(date('d M Y H:i',strtotime($shipment['diterima_pada']))):'—'?><?php if($shipment['penerima']): ?><small class="d-block text-secondary"><?=e($shipment['penerima'])?></small><?php endif ?></td><td>
      <a class="btn btn-outline-primary btn-sm mb-2" href="surat_jalan.php?id=<?=(int)$shipment['id']?>">Lihat / Cetak Surat Jalan</a>
      <?php $detailQuery=$pdo->prepare('SELECT d.id,d.jumlah_dikirim,d.jumlah_diterima,b.kode,b.nama,b.satuan FROM detail_pengiriman d JOIN barang b ON b.id=d.barang_id WHERE d.pengiriman_id=? ORDER BY b.kode');$detailQuery->execute([$shipment['id']]);$shipmentItems=$detailQuery->fetchAll(); ?>
      <?php if($role==='admin_gudang'&&$shipment['status']==='dalam_perjalanan'): ?><form method="post" action="aksi_admin.php" class="d-flex flex-column gap-2"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="penerimaan_konfirmasi"><input type="hidden" name="pengiriman_id" value="<?=$shipment['id']?>"><?php foreach($shipmentItems as $item): ?><label class="small fw-semibold" for="terima-<?=$item['id']?>"><?=e($item['kode'].' · '.$item['nama'].' — dikirim '.number_format((int)$item['jumlah_dikirim']).' '.$item['satuan'])?><input class="form-control form-control-sm mt-1" id="terima-<?=$item['id']?>" type="number" min="0" max="<?=$item['jumlah_dikirim']?>" name="jumlah_diterima[<?=$item['id']?>]" value="<?=$item['jumlah_dikirim']?>" required></label><?php endforeach ?><input class="form-control form-control-sm" name="catatan_penerimaan" maxlength="2000" placeholder="Catatan selisih (opsional)"><button class="btn btn-primary btn-sm" type="submit" data-confirm="Konfirmasi jumlah yang benar-benar diterima?">Konfirmasi penerimaan</button></form>
      <?php else: ?><ul class="mb-0 ps-3"><?php foreach($shipmentItems as $item): ?><li><?=e($item['kode'].' · '.$item['nama'])?>: <?=number_format((int)$item['jumlah_dikirim'])?> dikirim<?php if($item['jumlah_diterima']!==null): ?>, <?=number_format((int)$item['jumlah_diterima'])?> diterima<?php endif ?> <?=e($item['satuan'])?></li><?php endforeach ?></ul><?php if($shipment['catatan_penerimaan']): ?><small class="d-block text-secondary mt-1"><?=e($shipment['catatan_penerimaan'])?></small><?php endif ?><?php endif ?>
    </td></tr><?php endforeach ?>
    <?php if(!$shipments): ?><tr><td colspan="7" class="text-center text-secondary py-4">Belum ada pengiriman pada periode ini.</td><?php endif ?>
    </tbody></table></div>
  </div></section>

  <?php if($role==='manager'&&$legacyRequests): ?><section class="app-card card shadow-sm mb-4"><div class="card-body p-3 p-lg-4"><span class="section-kicker">Arsip sebelum multi-gudang</span><h2 class="h5 fw-bold text-primary-emphasis mt-1">Permintaan lama</h2><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Tanggal</th><th>Pengaju</th><th>Nama proyek lama</th><th>Barang</th><th>Status</th></tr></thead><tbody><?php foreach($legacyRequests as $legacy): ?><tr><td><?=e(date('d M Y H:i',strtotime($legacy['tanggal'])))?></td><td><?=e($legacy['peminta'])?></td><td><?=e($legacy['nama_proyek']?:'Tidak tercatat')?></td><td><?=e($legacy['barang']?:'—')?></td><td><?=e($legacy['status'])?></td></tr><?php endforeach ?></tbody></table></div></div></section><?php endif ?>

  <?php if($role==='admin_pusat'): ?>
  <section class="section-anchor app-card card shadow-sm mb-4" id="katalog"><div class="card-body p-3 p-lg-4"><span class="section-kicker">Master barang</span><h2 class="h4 fw-bold text-primary-emphasis mt-1"><?=$editing?'Ubah barang':'Katalog barang'?></h2><?php if($editing): ?><p class="small text-secondary">Perubahan satuan langsung berlaku pada angka stok yang ada dan jumlah yang masih berjalan tanpa konversi; riwayat mutasi lama tetap memakai satuan saat dicatat.</p><?php endif ?>
    <form method="post" action="aksi_admin.php" class="row g-3 mb-4"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="<?=$editing?'barang_ubah':'barang_simpan'?>"><?php if($editing): ?><input type="hidden" name="barang_id" value="<?=$editing['id']?>"><?php endif ?>
      <div class="col-md-6 col-xl-2"><label class="form-label" for="kode_barang">Kode otomatis</label><input class="form-control" id="kode_barang" readonly aria-describedby="kode_barang_info" value="<?=e($editing['kode']??'')?>"><small class="form-text" id="kode_barang_info">Kode dibuat saat disimpan: MP (Material Project), MH (Material Habis), PR (Peralatan). Menyesuaikan jika jenis diubah.</small></div>
      <div class="col-md-6 col-xl-3"><label class="form-label" for="nama_barang">Nama</label><input class="form-control" id="nama_barang" name="nama" maxlength="160" value="<?=e($editing['nama']??'')?>" required></div>
      <div class="col-md-6 col-xl-2"><label class="form-label" for="kategori_barang">Jenis barang</label><select class="form-select" id="kategori_barang" name="kategori_barang" required><?php foreach($categoryLabels as $value=>$label): ?><option value="<?=e($value)?>" <?=($editing['kategori_barang']??'')===$value?'selected':''?>><?=e($label)?></option><?php endforeach ?></select></div>
      <div class="col-md-6 col-xl-2"><label class="form-label" for="kategori">Kategori</label><input class="form-control" id="kategori" name="kategori" maxlength="100" value="<?=e($editing['kategori']??'Umum')?>" required></div>
      <div class="col-md-6 col-xl-1"><label class="form-label" for="satuan">Satuan</label><input class="form-control" id="satuan" name="satuan" maxlength="30" value="<?=e($editing['satuan']??'unit')?>" required></div>
      <?php if(!$editing): ?><div class="col-md-6 col-xl-2"><label class="form-label" for="stok_awal">Stok awal Gudang Pusat</label><input class="form-control" id="stok_awal" name="stok_awal" type="number" min="0" max="4294967295" step="1" value="0"><small class="form-text">Opsional. Penambahan berikutnya dicatat melalui form barang masuk.</small></div><?php else: ?><div class="col-md-6 col-xl-2"><label class="form-label" for="stok">Stok Gudang Pusat</label><input class="form-control" id="stok" name="stok" type="number" min="0" max="4294967295" step="1" value="<?=e((string)$editing['stok'])?>" required><small class="form-text">Selisih dicatat sebagai mutasi stok.</small></div><?php endif ?>
      <div class="col-md-6 col-xl-2 d-flex align-items-end gap-2"><button class="btn btn-primary" type="submit"><?=$editing?'Simpan':'Tambah barang'?></button><?php if($editing): ?><a class="btn btn-outline-secondary" href="dashboard.php#katalog">Batal</a><?php endif ?></div>
    </form>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Kode</th><th>Barang</th><th>Jenis</th><th>Kategori</th><th>Satuan</th><th>Stok pusat</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach($catalog as $item): ?><tr><td><?=e($item['kode'])?></td><td class="fw-semibold"><?=e($item['nama'])?></td><td><?=e($categoryLabels[$item['kategori_barang']]??'—')?></td><td><?=e($item['kategori']?:'—')?></td><td><?=e($item['satuan'])?></td><td><?=number_format((int)$item['stok'])?></td><td><?=((int)$item['aktif'])?'Aktif':'Nonaktif'?><?php if(!(int)$item['aktif']&&$item['catatan_penghapusan']): ?><small class="d-block text-secondary"><?=e($item['catatan_penghapusan'])?></small><?php endif ?></td><td class="text-nowrap"><a class="btn btn-outline-primary btn-sm" href="dashboard.php?edit_barang=<?=$item['id']?>#katalog">Ubah</a><?php if((int)$item['aktif']): ?><form class="d-inline" method="post" action="aksi_admin.php"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="barang_hapus"><input type="hidden" name="barang_id" value="<?=$item['id']?>"><button class="btn btn-outline-danger btn-sm" type="submit" data-confirm="Nonaktifkan barang yang seluruh stoknya sudah habis?">Nonaktifkan</button></form><details class="d-inline-block mt-2"><summary class="btn btn-outline-secondary btn-sm">Arsipkan</summary><form class="border rounded p-2 mt-1" method="post" action="aksi_admin.php"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="barang_delete"><input type="hidden" name="barang_id" value="<?=$item['id']?>"><label class="small" for="arsip-<?=$item['id']?>">Alasan arsip</label><textarea class="form-control form-control-sm" id="arsip-<?=$item['id']?>" name="catatan_penghapusan" maxlength="1000" required></textarea><button class="btn btn-danger btn-sm mt-1" type="submit">Simpan arsip</button></form></details><?php else: ?><form class="d-inline" method="post" action="aksi_admin.php"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="aksi" value="barang_aktif"><input type="hidden" name="barang_id" value="<?=$item['id']?>"><button class="btn btn-primary btn-sm" type="submit">Aktifkan</button></form><?php endif ?></td></tr><?php endforeach ?>
    <?php if(!$catalog): ?><tr><td colspan="8" class="text-center text-secondary py-4">Belum ada barang di katalog.</td><?php endif ?>
    </tbody></table></div>
  </div></section>
  <?php endif ?>

  <section class="section-anchor app-card card shadow-sm mb-4" id="mutasi"><div class="card-body p-3 p-lg-4"><div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><span class="section-kicker">Riwayat persediaan</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-0">Mutasi barang lintas gudang</h2></div><span class="text-secondary"><?=e(date('d M Y',strtotime($start)).' – '.date('d M Y',strtotime($end)))?></span></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Gudang</th><th>Barang</th><th>Jumlah</th><th>Asal / tujuan</th><th>Nomor / keterangan</th><th>Petugas</th></tr></thead><tbody>
    <?php foreach($movementRows as $movement): ?><tr><td class="text-nowrap"><?=e(date('d M Y H:i',strtotime($movement['tanggal'])))?></td><td><?=e($movementDisplayLabel($movement))?></td><td><?=e($movement['gudang'])?></td><td><strong><?=e($movement['kode'])?></strong><span class="d-block"><?=e($movement['barang'])?></span></td><td class="text-nowrap"><?=number_format((int)$movement['jumlah'])?> <?=e($movement['satuan'])?></td><td><?=e(($movement['gudang_asal']?:'—').' → '.($movement['gudang_tujuan']?:'—'))?></td><td><?=e(trim(($movement['nomor_transaksi']?'No. '.$movement['nomor_transaksi'].' · ':'').($movement['referensi']?:'—')))?></td><td><?=e($movement['petugas']?:'—')?></td></tr><?php endforeach ?>
    <?php if(!$movementRows): ?><tr><td colspan="8" class="text-center text-secondary py-4">Belum ada mutasi pada periode ini.</td><?php endif ?>
    </tbody></table></div>
  </div></section>
  <footer class="site-footer rounded-3 p-3 mt-4 text-center">Trikasa Energi Tama · Informasi pergudangan</footer>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script defer src="assets/app.js?v=<?= filemtime(public_path('assets/app.js')) ?>"></script>
</body>
</html>
