
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#123a68">
  <title>Bukti Persetujuan Permintaan Pembelian · <?=e($documentNumber)?></title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?=filemtime(public_path('assets/style.css'))?>">
  <style>@page { size: A4 portrait; margin: 16mm; }</style>
</head>
<body class="approval-document-page">
<main class="container py-4 py-lg-5">
  <div class="document-actions no-print d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="<?=e($backUrl)?>">Kembali ke daftar permintaan</a>
    <button class="btn btn-primary" type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
  </div>

  <article class="approval-document app-card card shadow-sm mx-auto">
    <div class="card-body p-4 p-lg-5">
      <header class="document-heading d-flex flex-wrap justify-content-between align-items-start gap-4 pb-4 mb-4">
        <div>
          <div class="document-brand mb-3">
            <img class="document-logo" src="assets/logo.png" alt="Logo Trikasa Energi Tama" width="64" height="64">
            <p class="document-organization mb-0">TRIKASA ENERGI TAMA</p>
          </div>
          <h1 class="h2 fw-bold text-primary-emphasis mb-2">Bukti Persetujuan Permintaan Pembelian</h1>
          <p class="text-secondary mb-0">Catatan keputusan Purchasing atas pengajuan pembelian barang.</p>
        </div>
        <div class="document-number text-lg-end">
          <span class="small text-secondary d-block">Nomor dokumen</span>
          <strong><?=e($documentNumber)?></strong>
        </div>
      </header>

      <section class="mb-4" aria-labelledby="informasi-title">
        <h2 id="informasi-title" class="h5 fw-bold mb-3">Informasi pengajuan</h2>
        <dl class="document-details row g-3 mb-0">
          <div class="col-sm-6"><dt>Tanggal pengajuan</dt><dd><?=e($submittedDate)?></dd></div>
          <div class="col-sm-6"><dt>Tanggal persetujuan</dt><dd><?=e($approvedDate)?></dd></div>
          <div class="col-sm-6"><dt>Nama pengaju</dt><dd><?=e($request['pengaju'])?></dd></div>
          <div class="col-sm-6"><dt>Purchasing yang memproses</dt><dd><?=e($request['purchasing'] ?: 'Tidak tercatat')?></dd></div>
        </dl>
      </section>

      <section class="mb-4" aria-labelledby="barang-title">
        <h2 id="barang-title" class="h5 fw-bold mb-3">Rincian barang</h2>
        <div class="table-responsive">
          <table class="table document-items align-middle mb-0">
            <thead><tr><th scope="col">Kode</th><th scope="col">Barang</th><th scope="col" class="text-end">Jumlah</th><th scope="col">Satuan</th></tr></thead>
            <tbody><tr><td><?=e($request['kode'])?></td><td class="fw-semibold"><?=e($request['barang'])?></td><td class="text-end"><?=number_format((int)$request['jumlah'])?></td><td><?=e($request['satuan'])?></td></tr></tbody>
          </table>
        </div>
      </section>

      <section class="document-note mb-4" aria-labelledby="catatan-title">
        <h2 id="catatan-title" class="h6 fw-bold mb-2">Catatan pengajuan</h2>
        <p class="mb-0"><?=nl2br(e($request['catatan'] ?: 'Tidak ada catatan.'))?></p>
      </section>

      <section class="approval-stamp p-3 p-md-4" aria-label="Keputusan Purchasing">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div><span class="small text-secondary d-block">Keputusan Purchasing</span><strong class="approval-approved">DISETUJUI</strong></div>
          <div class="text-md-end"><span class="small text-secondary d-block">Diproses oleh</span><strong><?=e($request['purchasing'] ?: 'Tidak tercatat')?></strong><span class="d-block small text-secondary"><?=e($approvedDate)?></span></div>
        </div>
      </section>

      <footer class="document-footer mt-4 pt-3 small text-secondary">
        Dokumen ini mencatat persetujuan permintaan pembelian. Persetujuan tidak mengubah stok; barang masuk tetap dicatat melalui alur gudang.
      </footer>
    </div>
  </article>
</main>
</body>
</html>
