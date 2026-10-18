
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#123a68">
  <title>Rincian penerimaan pembelian · GudangKita</title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?=filemtime(public_path('assets/style.css'))?>">
</head>
<body>
  <nav class="navbar navbar-dark dashboard-navbar sticky-top shadow-sm" style="background: var(--brand-blue-dark);" aria-label="Navigasi ringkasan penerimaan">
  <div class="container-fluid px-lg-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?=$isManager?'dashboard.php#permintaan-pembelian':'purchasing.php'?>"><img src="assets/logo.png" alt=""><span class="fw-bold">Trikasa Energi Tama</span></a>
    <div class="navbar-account d-flex align-items-center gap-3"><span class="badge rounded-pill text-bg-warning"><?=$isManager?'Manager · baca-saja':'Purchasing · baca-saja'?></span>@include('warehouse.partials.account-avatar') <span class="text-white small d-none d-sm-inline"><?=e($currentUser['nama'])?></span><a class="btn btn-outline-light btn-sm rounded-pill px-3" href="profil.php">Profil saya</a><form method="post" action="logout.php" class="d-inline"><input type="hidden" name="_token" value="<?=e(csrf())?>"><button class="btn btn-outline-light btn-sm rounded-pill px-3" type="submit">Keluar</button></form></div>
  </div>
</nav>
<main class="container-fluid px-3 px-lg-4 py-4">
  <?php if ($flashMessage): ?><div class="alert alert-<?=e($flashMessage[1] === 'error' ? 'danger' : $flashMessage[1])?> alert-dismissible fade show" role="alert"><?=e($flashMessage[0])?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div><?php endif ?>
  <section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
      <div><span class="section-kicker">Rincian pembelian · Permintaan #<?=e((string)$request['id'])?></span><h1 class="h2 fw-bold text-primary-emphasis mt-1 mb-1">Ringkasan penerimaan</h1><p class="text-secondary mb-0">Data diperbarui dari catatan penerimaan tersimpan setiap kali halaman dibuka.</p></div>
      <a class="btn btn-outline-secondary" href="<?=$isManager?'dashboard.php#permintaan-pembelian':'purchasing.php#permintaan'?>">Kembali ke permintaan</a>
    </div>

    <article class="app-card card shadow-sm mb-4">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div><span class="section-kicker">Permintaan pembelian</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-1"><?=e($request['kode'].' · '.$request['barang'])?></h2><p class="text-secondary mb-0">Pengaju: <strong><?=e($request['peminta'])?></strong> · Diajukan <?=e(date('d M Y H:i', strtotime($request['tanggal'])))?></p></div>
          <span class="badge rounded-pill <?=e($statusClasses[$request['status']] ?? 'text-bg-secondary')?>"><?=e($statusLabels[$request['status']] ?? $request['status'])?></span>
        </div>
        <div class="row row-cols-2 row-cols-lg-5 g-3">
          <div class="col"><div class="border rounded-3 h-100 p-3"><span class="small text-secondary">Jumlah dipesan</span><div class="h5 fw-bold mt-2 mb-0"><?=number_format($ordered)?> <?=e($request['satuan'])?></div></div></div>
          <div class="col"><div class="border rounded-3 h-100 p-3"><span class="small text-secondary">Diterima layak pakai</span><div class="h5 fw-bold text-success mt-2 mb-0"><?=number_format($ready)?> <?=e($request['satuan'])?></div></div></div>
          <div class="col"><div class="border rounded-3 h-100 p-3"><span class="small text-secondary">Rusak</span><div class="h5 fw-bold text-danger mt-2 mb-0"><?=number_format($damaged)?> <?=e($request['satuan'])?></div></div></div>
          <div class="col"><div class="border rounded-3 h-100 p-3"><span class="small text-secondary">Tidak sesuai</span><div class="h5 fw-bold text-warning-emphasis mt-2 mb-0"><?=number_format($mismatched)?> <?=e($request['satuan'])?></div></div></div>
          <div class="col"><div class="border rounded-3 h-100 p-3"><span class="small text-secondary">Belum diterima</span><div class="h5 fw-bold text-primary mt-2 mb-0"><?=number_format($remaining)?> <?=e($request['satuan'])?></div></div></div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-6"><span class="small text-secondary">Catatan pengajuan</span><div><?=e($request['catatan'] ?: '—')?></div></div>
          <div class="col-md-6"><span class="small text-secondary">Keputusan Purchasing</span><div><?=e($request['pemroses'] ?: 'Belum diproses')?><?php if ($request['diproses_pada']): ?> · <?=e(date('d M Y H:i', strtotime($request['diproses_pada'])))?><?php endif ?></div></div>
        </div>
        <?php if ($request['status'] !== 'disetujui'): ?><div class="alert alert-light border mt-3 mb-0">Penerimaan hanya dapat dicatat setelah permintaan disetujui. Ringkasan di atas tetap dihitung dari catatan yang tersimpan.</div><?php endif ?>
      </div>
    </article>

    <article class="app-card card shadow-sm">
      <div class="card-body p-3 p-lg-4">
        <div class="mb-3"><span class="section-kicker">Catatan barang tiba</span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-0">Riwayat penerimaan</h2><p class="text-secondary mb-0">Purchasing dan manager dapat membaca rincian ini; pencatatan penerimaan dan perubahan stok dilakukan oleh admin gudang berwenang.</p></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Tanggal penerimaan</th><th>Lokasi</th><th>Dicatat oleh</th><th>Jumlah fisik</th><th>Layak pakai</th><th>Rusak</th><th>Tidak sesuai</th><th>Catatan kondisi</th><th>Foto bukti</th></tr></thead><tbody>
          <?php foreach ($receipts as $receipt): ?>
            <tr><td class="text-nowrap"><?=e(date('d M Y H:i', strtotime($receipt['diterima_pada'])))?></td><td><?=e($receipt['nama_gudang'])?></td><td><?=e($receipt['nama_petugas'])?></td><td><?=number_format((int)$receipt['jumlah_diterima'])?> <?=e($receipt['satuan_barang'])?></td><td><?=number_format((int)$receipt['jumlah_layak'])?> <?=e($receipt['satuan_barang'])?></td><td><?=number_format((int)$receipt['jumlah_rusak'])?> <?=e($receipt['satuan_barang'])?></td><td><?=number_format((int)$receipt['jumlah_tidak_sesuai'])?> <?=e($receipt['satuan_barang'])?></td><td><?=e($receipt['catatan_kondisi'] ?: '—')?></td><td><div class="d-flex flex-wrap gap-2"><?php foreach ($photosByReceipt[(int)$receipt['id']] ?? [] as $photoIndex => $photoId): ?><button type="button" class="btn p-0 border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#fotoBuktiModal" data-foto-src="<?=e('foto_penerimaan_pembelian.php?id='.$photoId)?>" data-foto-alt="Foto bukti penerimaan <?=($photoIndex + 1)?>" aria-label="Lihat foto bukti penerimaan <?=($photoIndex + 1)?>"><img src="foto_penerimaan_pembelian.php?id=<?=$photoId?>" width="96" height="72" class="img-thumbnail" style="object-fit: cover;" loading="lazy" alt="Foto bukti penerimaan <?=($photoIndex + 1)?>"></button><?php endforeach ?><?php if (empty($photosByReceipt[(int)$receipt['id']])): ?>—<?php endif ?></div></td></tr>
          <?php endforeach ?>
          <?php if (!$receipts): ?><tr><td colspan="9" class="text-center text-secondary py-4">Belum ada penerimaan yang dicatat.</td></tr><?php endif ?>
        </tbody></table></div>
      </div>
    </article>
  </section>
</main>
<div class="modal fade" id="fotoBuktiModal" tabindex="-1" aria-labelledby="fotoBuktiModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5" id="fotoBuktiModalLabel">Foto bukti penerimaan</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
      <div class="modal-body text-center"><img id="fotoBuktiModalImage" class="img-fluid rounded" style="max-height:80vh;" alt=""></div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
const fotoBuktiModal = document.getElementById('fotoBuktiModal');
const fotoBuktiModalImage = document.getElementById('fotoBuktiModalImage');
fotoBuktiModal.addEventListener('show.bs.modal', event => {
  const trigger = event.relatedTarget;
  fotoBuktiModalImage.src = trigger.dataset.fotoSrc;
  fotoBuktiModalImage.alt = trigger.dataset.fotoAlt || 'Foto bukti penerimaan';
});
fotoBuktiModal.addEventListener('hidden.bs.modal', () => {
  fotoBuktiModalImage.removeAttribute('src');
  fotoBuktiModalImage.alt = '';
});
</script>
</body>
</html>
