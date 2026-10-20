
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
                <div class="input-group input-group-lg">
                  <input class="form-control" id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi">
                  <button class="btn btn-outline-secondary" id="password-visibility-toggle" type="button" aria-label="Tampilkan kata sandi" aria-pressed="false" hidden>
                    <svg id="password-eye-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                      <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8ZM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.71 5.88 3.5 8 3.5s3.88 1.21 5.168 2.457A13.133 13.133 0 0 1 14.828 8a13.133 13.133 0 0 1-1.66 2.043C11.88 11.29 10.12 12.5 8 12.5s-3.88-1.21-5.168-2.457A13.133 13.133 0 0 1 1.172 8Z" />
                      <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5ZM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0Z" />
                    </svg>
                    <svg id="password-eye-slash-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false" hidden>
                      <path d="M13.359 11.238C15.06 9.71 16 8 16 8s-3-5.5-8-5.5a7.9 7.9 0 0 0-3.359.762l.817.817A6.8 6.8 0 0 1 8 3.5c2.12 0 3.88 1.21 5.168 2.457A13.133 13.133 0 0 1 14.828 8a13.5 13.5 0 0 1-2.2 2.422l.73.816ZM11.297 9.176a3.5 3.5 0 0 0-4.473-4.473l.87.87a2.5 2.5 0 0 1 2.733 2.733l.87.87ZM5.04 6.333a3.5 3.5 0 0 0 4.627 4.627l-.87-.87a2.5 2.5 0 0 1-2.887-2.887l-.87-.87Z" />
                      <path d="M3.527 4.233C1.439 5.73 0 8 0 8s3 5.5 8 5.5a7.9 7.9 0 0 0 4.087-1.133l-.822-.822A6.8 6.8 0 0 1 8 12.5c-2.12 0-3.88-1.21-5.168-2.457A13.133 13.133 0 0 1 1.172 8a13.7 13.7 0 0 1 2.7-2.946l-.345-.821Z" />
                      <path d="m1.646.646.708-.708 13.708 13.708-.708.708z" />
                    </svg>
                  </button>
                </div>
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
  <script>
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('password-visibility-toggle');
    const passwordEyeIcon = document.getElementById('password-eye-icon');
    const passwordEyeSlashIcon = document.getElementById('password-eye-slash-icon');

    if (passwordInput && passwordToggle && passwordEyeIcon && passwordEyeSlashIcon) {
      passwordToggle.hidden = false;
      passwordToggle.addEventListener('click', () => {
        const passwordIsVisible = passwordInput.type === 'password';

        passwordInput.type = passwordIsVisible ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', String(passwordIsVisible));
        passwordToggle.setAttribute('aria-label', passwordIsVisible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        passwordEyeIcon.hidden = passwordIsVisible;
        passwordEyeSlashIcon.hidden = !passwordIsVisible;
      });
    }
  </script>
</body>

</html>
