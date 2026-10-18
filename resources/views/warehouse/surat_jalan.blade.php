
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Surat Jalan · <?=e($documentNumber)?></title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?=filemtime(public_path('assets/style.css'))?>">
  <style>@page { size: A4 portrait; margin: 16mm; }</style>
</head>
<body class="approval-document-page">
<main class="container py-4 py-lg-5">
  <div class="document-actions no-print d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="dashboard.php#pengiriman">Kembali ke pengiriman</a>
    <button class="btn btn-primary" type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
  </div>
<?php if($currentUser['role']==='admin_pusat'): ?>
  <details class="no-print app-card card p-3 mb-3 mx-auto approval-document">
    <summary class="fw-semibold">Isi / ubah kendaraan dan sopir</summary>
    <form method="post" action="aksi_admin.php" class="row g-3 mt-1">
      <input type="hidden" name="csrf" value="<?=e(csrf())?>">
      <input type="hidden" name="aksi" value="surat_jalan_transport_simpan">
      <input type="hidden" name="pengiriman_id" value="<?=(int)$shipment['id']?>">
      <div class="col-sm-6"><label class="form-label" for="sj_kendaraan">Nomor kendaraan</label><input class="form-control" id="sj_kendaraan" name="nomor_kendaraan" maxlength="40" value="<?=e($shipment['nomor_kendaraan'] ?? '')?>" required></div>
      <div class="col-sm-6"><label class="form-label" for="sj_sopir">Nama sopir</label><input class="form-control" id="sj_sopir" name="nama_driver" maxlength="120" value="<?=e($shipment['nama_driver'] ?? '')?>" required></div>
      <div><button class="btn btn-primary" type="submit">Simpan data surat jalan</button></div>
    </form>
  </details>
<?php endif ?>
  <article class="approval-document app-card card shadow-sm mx-auto">
    <div class="card-body p-4 p-lg-5">
      <header class="document-heading pb-4 mb-4">
        <div class="document-brand mb-3"><img class="document-logo" src="assets/logo.png" alt="Logo Trikasa Energi Tama" width="64" height="64"><p class="document-organization mb-0">TRIKASA ENERGI TAMA</p></div>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
          <div><h1 class="h2 fw-bold text-primary-emphasis mb-1">Surat Jalan</h1><p class="text-secondary mb-0">Pengiriman barang ke gudang proyek</p></div>
          <div class="document-number"><span class="small text-secondary d-block">Nomor surat jalan</span><strong><?=e($documentNumber)?></strong></div>
        </div>
      </header>
      <section class="mb-4" aria-labelledby="pengiriman-title">
        <h2 id="pengiriman-title" class="h5 fw-bold mb-3">Informasi pengiriman</h2>
        <dl class="document-details row g-3 mb-0">
          <div class="col-sm-6"><dt>Gudang asal</dt><dd><?=e($shipment['gudang_asal'])?></dd></div>
          <div class="col-sm-6"><dt>Gudang proyek tujuan</dt><dd><?=e($shipment['gudang_tujuan'])?></dd></div>
          <div class="col-sm-6"><dt>Tanggal kirim</dt><dd><?=e(date('d M Y H:i',strtotime($shipment['dikirim_pada'])))?></dd></div>
          <div class="col-sm-6"><dt>Referensi permintaan</dt><dd>#<?=(int)$shipment['permintaan_id']?></dd></div>
          <div class="col-sm-6"><dt>Pengirim</dt><dd><?=e($shipment['pengirim'])?></dd></div>
          <div class="col-sm-6"><dt>Status pengiriman</dt><dd><?=e($statusLabels[$shipment['status']] ?? $shipment['status'])?></dd></div>
          <div class="col-sm-6"><dt>Nomor kendaraan</dt><dd><?=e($shipment['nomor_kendaraan'] ?: '________________________')?></dd></div>
          <div class="col-sm-6"><dt>Nama sopir</dt><dd><?=e($shipment['nama_driver'] ?: '________________________')?></dd></div>
        </dl>
      </section>
      <section class="mb-4" aria-labelledby="barang-title">
        <h2 id="barang-title" class="h5 fw-bold mb-3">Rincian barang yang dikirim</h2>
        <div class="table-responsive"><table class="table document-items align-middle mb-0">
          <thead><tr><th scope="col">No.</th><th scope="col">Kode</th><th scope="col">Nama barang</th><th scope="col" class="text-end">Jumlah</th><th scope="col">Satuan</th></tr></thead>
          <tbody><?php foreach($items as $index=>$item): ?><tr><td><?=$index+1?></td><td><?=e($item['kode'])?></td><td class="fw-semibold"><?=e($item['nama'])?></td><td class="text-end"><?=number_format((int)$item['jumlah_dikirim'])?></td><td><?=e($item['satuan_kirim'])?></td></tr><?php endforeach ?></tbody>
        </table></div>
      </section>
      <section class="document-note p-3 mb-4" aria-labelledby="catatan-title"><h2 id="catatan-title" class="h6 fw-bold">Catatan pengiriman</h2><p class="mb-0"><?=nl2br(e($shipment['catatan'] ?: 'Tidak ada catatan.'))?></p></section>
      <section class="delivery-signatures" aria-label="Tanda tangan serah terima">
        <div><h2 class="h6">Pengirim</h2><div class="delivery-signature-space"></div><p><?=e($shipment['pengirim'])?></p></div>
        <div><h2 class="h6">Sopir</h2><div class="delivery-signature-space"></div><p><?=e($shipment['nama_driver'] ?: '(____________________)')?></p></div>
        <div><h2 class="h6">Penerima</h2><div class="delivery-signature-space"></div><p><?=e($shipment['penerima'] ?: '(____________________)')?></p></div>
      </section>
      <footer class="document-footer mt-4 pt-3 small text-secondary">Periksa jumlah dan kondisi barang saat diterima. Penerimaan tetap dikonfirmasi melalui aplikasi oleh admin gudang proyek. Mencetak surat jalan tidak mengubah stok.</footer>
    </div>
  </article>
</main>
</body>
</html>
