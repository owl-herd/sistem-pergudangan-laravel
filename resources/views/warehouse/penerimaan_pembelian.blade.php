
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#123a68">
  <title>Penerimaan pembelian · GudangKita</title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?=filemtime(public_path('assets/style.css'))?>">
</head>
<body>
<nav class="navbar navbar-dark dashboard-navbar sticky-top shadow-sm" style="background: var(--brand-blue-dark);" aria-label="Navigasi penerimaan">
  <div class="container-fluid px-lg-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php"><img src="assets/logo.png" alt=""><span class="fw-bold">Trikasa Energi Tama</span></a>
    <div class="navbar-account d-flex align-items-center gap-3"><span class="badge rounded-pill text-bg-info"><?=e($currentUser['role'] === 'admin_pusat' ? 'Admin Pusat' : 'Admin Gudang Proyek')?></span>@include('warehouse.partials.account-avatar') <span class="text-white small d-none d-sm-inline"><?=e($currentUser['nama'])?></span><a class="btn btn-outline-light btn-sm rounded-pill px-3" href="profil.php">Profil saya</a><form method="post" action="logout.php" class="d-inline"><input type="hidden" name="_token" value="<?=e(csrf())?>"><button class="btn btn-outline-light btn-sm rounded-pill px-3" type="submit">Keluar</button></form></div>
  </div>
</nav>
<main class="container-fluid px-3 px-lg-4 py-4">
  <?php if($flashMessage): ?><div class="alert alert-<?=e($flashMessage[1]==='error'?'danger':$flashMessage[1])?> alert-dismissible fade show" role="alert"><?=e($flashMessage[0])?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div><?php endif ?>
  <section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
      <div><span class="section-kicker">Barang yang sudah tiba</span><h1 class="h2 fw-bold text-primary-emphasis mt-1 mb-1">Penerimaan pembelian</h1><p class="text-secondary mb-0">Tujuan penerimaan: <strong><?=e($warehouse['nama'])?></strong>. Hanya jumlah layak yang menambah stok; jumlah rusak atau tidak sesuai dicatat tanpa menambah stok.</p></div>
      <a class="btn btn-outline-secondary" href="<?=e($dashboardAnchor)?>">Kembali ke dashboard</a>
    </div>
    <?php if(!$requests): ?><div class="alert alert-light border">Belum ada permintaan pembelian yang disetujui untuk gudang ini.</div><?php endif ?>

    <?php foreach($requests as $request): ?>
      <?php
        $requestId = (int)$request['id'];
        $ordered = (int)$request['jumlah'];
        $received = (int)$request['jumlah_diterima'];
        $remaining = max(0,$ordered-$received);
        $receiptStatus = $remaining === 0 ? 'Semua jumlah sudah dicatat' : ($received > 0 ? 'Diterima sebagian' : 'Belum diterima');
        $receiptClass = $remaining === 0 ? 'text-bg-success' : ($received > 0 ? 'text-bg-info' : 'text-bg-secondary');
        $history = $receiptsByRequest[$requestId] ?? [];
      ?>
      <article class="app-card card shadow-sm mb-4" id="pembelian-<?=$requestId?>">
        <div class="card-body p-3 p-lg-4">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div><span class="section-kicker">Permintaan #<?=$requestId?></span><h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-1"><?=e($request['kode'].' · '.$request['barang'])?></h2><p class="text-secondary mb-0">Pengaju: <?=e($request['peminta'])?> · Jumlah dipesan: <strong><?=number_format($ordered)?> <?=e($request['satuan'])?></strong> · Tujuan: <?=e($warehouse['nama'])?></p></div>
            <div class="text-md-end"><span class="badge rounded-pill <?=e($receiptClass)?>"><?=e($receiptStatus)?></span><div class="small text-secondary mt-2">Sudah dicatat: <?=number_format($received)?> · Sisa belum diterima: <?=number_format($remaining)?> <?=e($request['satuan'])?></div><?php if((int)$request['user_id']===(int)$currentUser['id']): ?><a class="small" href="bukti_persetujuan.php?id=<?=$requestId?>">Lihat bukti persetujuan</a><?php endif ?></div>
          </div>

          <?php if($history): ?>
            <h3 class="h6 fw-bold mt-4">Riwayat penerimaan</h3>
            <div class="table-responsive mb-4"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Tanggal</th><th>Lokasi</th><th>Dicatat oleh</th><th>Total diperiksa</th><th>Layak masuk stok</th><th>Rusak</th><th>Tidak sesuai</th><th>Catatan kondisi</th><th>Foto bukti</th></tr></thead><tbody>
              <?php foreach($history as $receipt): ?><tr><td class="text-nowrap"><?=e(date('d M Y H:i',strtotime($receipt['diterima_pada'])))?></td><td><?=e($receipt['nama_gudang'])?></td><td><?=e($receipt['nama_petugas'])?></td><td><?=number_format((int)$receipt['jumlah_diterima'])?> <?=e($receipt['satuan_barang'])?></td><td><?=number_format((int)$receipt['jumlah_layak'])?></td><td><?=number_format((int)$receipt['jumlah_rusak'])?></td><td><?=number_format((int)$receipt['jumlah_tidak_sesuai'])?></td><td><?=e($receipt['catatan_kondisi'] ?: '—')?><?php if($receipt['transaksi_gudang_id']): ?><small class="d-block text-secondary">Mutasi stok #<?=$receipt['transaksi_gudang_id']?></small><?php endif ?></td><td><div class="d-flex flex-wrap gap-2"><?php foreach($photosByReceipt[(int)$receipt['id']] ?? [] as $photoIndex => $photoId): ?><button type="button" class="btn p-0 border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#fotoBuktiModal" data-foto-src="<?=e('foto_penerimaan_pembelian.php?id='.$photoId)?>" data-foto-alt="Foto bukti <?=($photoIndex + 1)?>" aria-label="Lihat foto bukti <?=($photoIndex + 1)?>"><img src="foto_penerimaan_pembelian.php?id=<?=$photoId?>" width="80" height="60" class="img-thumbnail" style="object-fit: cover;" loading="lazy" alt="Foto bukti <?=($photoIndex + 1)?>"></button><?php endforeach ?><?php if(empty($photosByReceipt[(int)$receipt['id']])): ?>—<?php endif ?></div></td></tr><?php endforeach ?>
            </tbody></table></div>
          <?php else: ?>
            <p class="small text-secondary mt-3 mb-4">Belum ada penerimaan yang dicatat.</p>
          <?php endif ?>

          <?php if($remaining > 0 && (int)$request['barang_aktif'] !== 1): ?>
            <div class="alert alert-warning mb-0">Barang ini nonaktif di katalog. Admin pusat perlu mengaktifkannya kembali sebelum penerimaan dapat menambah stok.</div>
          <?php elseif($remaining > 0): ?>
            <form method="post" action="aksi_admin.php" enctype="multipart/form-data" class="border rounded-3 p-3 p-lg-4 bg-light-subtle">
              <input type="hidden" name="csrf" value="<?=e(csrf())?>">
              <input type="hidden" name="aksi" value="pembelian_penerimaan_simpan">
              <input type="hidden" name="permintaan_pembelian_id" value="<?=$requestId?>">
              <input type="hidden" name="token_form" value="<?=e($formTokens[$requestId])?>">
              <h3 class="h6 fw-bold mb-3">Catat barang yang tiba · sisa <?=number_format($remaining)?> <?=e($request['satuan'])?></h3>
              <div class="row g-3">
                <div class="col-sm-6 col-xl-3"><label class="form-label" for="layak-<?=$requestId?>">Layak masuk stok</label><input class="form-control" id="layak-<?=$requestId?>" type="number" name="jumlah_layak" min="0" max="4294967295" step="1" value="0" required></div>
                <div class="col-sm-6 col-xl-3"><label class="form-label" for="rusak-<?=$requestId?>">Rusak</label><input class="form-control" id="rusak-<?=$requestId?>" type="number" name="jumlah_rusak" min="0" max="4294967295" step="1" value="0" required></div>
                <div class="col-sm-6 col-xl-3"><label class="form-label" for="tidak-sesuai-<?=$requestId?>">Tidak sesuai</label><input class="form-control" id="tidak-sesuai-<?=$requestId?>" type="number" name="jumlah_tidak_sesuai" min="0" max="4294967295" step="1" value="0" required></div>
                <div class="col-sm-6 col-xl-3"><label class="form-label" for="tanggal-terima-<?=$requestId?>">Tanggal dan waktu tiba</label><input class="form-control" id="tanggal-terima-<?=$requestId?>" type="datetime-local" name="tanggal_diterima" value="<?=e(date('Y-m-d\\TH:i'))?>" required></div>
                <div class="col-12"><label class="form-label" for="catatan-kondisi-<?=$requestId?>">Catatan kondisi</label><textarea class="form-control" id="catatan-kondisi-<?=$requestId?>" name="catatan_kondisi" rows="2" maxlength="2000" placeholder="Wajib diisi bila ada barang rusak atau tidak sesuai"></textarea></div>
                <div class="col-12"><label class="form-label" for="foto-bukti-<?=$requestId?>">Foto bukti kondisi</label><input class="form-control" id="foto-bukti-<?=$requestId?>" type="file" name="foto_bukti[]" accept="image/jpeg,image/png,image/webp" multiple><small class="form-text text-secondary">Wajib jika jumlah rusak atau tidak sesuai lebih dari 0. Maksimal 5 foto JPEG, PNG, atau WebP, masing-masing 4 MB.</small></div>
                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2"><small class="text-secondary">Jumlah layak, rusak, dan tidak sesuai dijumlahkan sebagai penerimaan fisik. Total tidak boleh melebihi sisa pesanan.</small><button class="btn btn-primary" type="submit" data-confirm="Simpan penerimaan ini? Hanya jumlah layak yang akan ditambahkan ke stok.">Simpan penerimaan</button></div>
              </div>
            </form>
          <?php else: ?>
            <div class="alert alert-success mb-0">Semua jumlah pesanan sudah tercatat diterima. Jumlah layak masuk stok: <?=number_format((int)$request['jumlah_layak'])?> <?=e($request['satuan'])?>.</div>
          <?php endif ?>
        </div>
      </article>
    <?php endforeach ?>
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
