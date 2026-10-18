
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#123a68">
  <title>Masuk · Trikasa Energi Tama</title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(public_path('assets/style.css')) ?>">
</head>

<body>
  <main class="container-fluid p-0">
    <div class="row g-0 min-vh-100">
      <section class="col-lg-6 login-hero d-flex flex-column justify-content-between p-4 p-md-5" aria-label="Tentang Trikasa Energi Tama">
        <a class="d-inline-flex align-items-center gap-2 text-decoration-none" href="index.php" aria-label="Kembali ke beranda Trikasa Energi Tama">
          <img class="brand-logo" src="assets/logo.png?v=<?= filemtime(public_path('assets/logo.png')) ?>" alt="">
          <span class="text-white fw-bold fs-5">Trikasa Energi Tama</span>
        </a>
        <div class="my-5 my-lg-auto py-lg-5">
          <span class="badge rounded-pill text-bg-warning mb-3">Sistem informasi pergudangan</span>
          <h1 class="display-4 fw-bold">Semua kebutuhan gudang, dalam satu tempat.</h1>
          <p class="lead mt-3 mb-0">Ajukan barang dengan mudah dan pantau alur persediaan dengan transparan.</p>
          <div class="d-flex flex-wrap gap-2 mt-4">
            <span class="badge rounded-pill text-bg-light text-primary-emphasis">Kelola persediaan</span>
            <span class="badge rounded-pill text-bg-light text-primary-emphasis">Pantau peminjaman</span>
            <span class="badge rounded-pill text-bg-light text-primary-emphasis">Lihat laporan</span>
          </div>
        </div>
        <small class="text-white-50">© <?= date('Y') ?> Trikasa Energi Tama</small>
      </section>

      <section class="col-lg-6 d-flex align-items-center justify-content-center p-4 p-md-5">
        <div class="login-card card shadow-sm w-100" style="max-width: 440px;">
          <div class="card-body p-4 p-md-5">
            <span class="section-kicker">Selamat datang</span>
            <h2 class="h3 fw-bold text-primary-emphasis mt-2">Masuk ke akun Anda</h2>
            <p class="text-secondary mb-4">Gunakan email dan kata sandi yang terdaftar.</p>
            <?php if($error): ?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?=e(csrf())?>">
              <div class="mb-3">
                <label class="form-label fw-semibold" for="email">Email</label>
                <input class="form-control form-control-lg" id="email" type="email" name="email" required autocomplete="username" placeholder="nama@perusahaan.com">
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold" for="password">Kata sandi</label>
                <input class="form-control form-control-lg" id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi">
              </div>
              <button class="btn btn-primary btn-lg w-100 rounded-pill" type="submit">Masuk <span aria-hidden="true">→</span></button>
            </form>
            <div class="alert alert-light border mt-4 mb-0 small" role="note"><strong>Butuh akun?</strong> Hubungi administrator perusahaan. Halaman dan menu tersedia sesuai peran akun Anda.</div>
            <a class="d-inline-block mt-4 link-secondary" href="index.php"><span aria-hidden="true">←</span> Kembali ke beranda</a>
          </div>
        </div>
      </section>
    </div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>

</html>
