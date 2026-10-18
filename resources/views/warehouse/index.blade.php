<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#123a68">
  <meta name="description" content="Trikasa Energi Tama adalah mitra konstruksi untuk pekerjaan gedung, sipil, renovasi, dan pemeliharaan.">
  <title>Trikasa Energi Tama · Mitra Konstruksi Anda</title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(public_path('assets/style.css')) ?>">
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-light sticky-top bg-white shadow-sm" aria-label="Navigasi utama">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2 me-lg-5" href="index.php" aria-label="Trikasa Energi Tama, beranda">
        <img src="assets/logo.png?v=<?= filemtime(public_path('assets/logo.png')) ?>" alt="">
        <span class="brand-name">Trikasa Energi Tama<small>MITRA KONSTRUKSI</small></span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation" aria-controls="mainNavigation" aria-expanded="false" aria-label="Buka navigasi">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNavigation">
        <ul class="navbar-nav mx-auto gap-lg-3 py-3 py-lg-0">
          <li class="nav-item"><a class="nav-link" href="#tentang">Tentang</a></li>
          <li class="nav-item"><a class="nav-link" href="#layanan">Layanan</a></li>
          <li class="nav-item"><a class="nav-link" href="#pendekatan">Pendekatan</a></li>
        </ul>
        <a class="btn btn-primary rounded-pill px-4" href="login.php">Masuk <span aria-hidden="true">↗</span></a>
      </div>
    </div>
  </nav>

  <main>
    <section class="home-hero">
      <div class="container-fluid px-0">
        <div class="row g-0 align-items-stretch">
          <div class="col-lg-5 d-flex align-items-center">
            <div class="hero-copy px-4 px-xl-5 py-5 ms-xl-auto">
              <span class="hero-kicker">Konstruksi · Sipil · Pemeliharaan</span>
              <h1 class="hero-title mt-4 mb-3">Membangun dengan cermat.<br><em>Menjaga kepercayaan.</em></h1>
              <p class="hero-copy-text mb-4">Trikasa Energi Tama mendampingi kebutuhan konstruksi melalui perencanaan yang terarah, pelaksanaan yang bertanggung jawab, dan perhatian pada setiap detail pekerjaan.</p>
              <div class="d-flex flex-wrap align-items-center gap-3">
                <a class="btn btn-warning btn-lg rounded-pill px-4" href="login.php">Masuk ke portal <span aria-hidden="true">↗</span></a>
                <a class="link-light link-offset-2 link-underline-opacity-0" href="#tentang">Kenali perusahaan <span class="text-warning" aria-hidden="true">↓</span></a>
              </div>
              <div class="hero-assurance border-top mt-5 pt-4 d-flex align-items-center gap-3">
                <span class="hero-assurance-mark" aria-hidden="true">✓</span>
                <span><strong class="d-block">Ruang lingkup yang jelas</strong><small class="text-white-50">Koordinasi rapi dari awal hingga akhir pekerjaan</small></span>
              </div>
            </div>
          </div>
          <div class="col-lg-7 hero-image-column">
            <img src="https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&amp;fit=crop&amp;w=1400&amp;q=85" alt="Pekerja konstruksi mengenakan perlengkapan keselamatan di lokasi proyek" fetchpriority="high">
            <div class="position-absolute bottom-0 start-0 p-4 p-lg-5 text-white z-1">
              <span class="badge rounded-pill text-bg-warning mb-2">Trikasa Energi Tama</span>
              <p class="mb-0 fw-semibold">Mengubah rencana menjadi ruang nyata</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="brand-ribbon py-3" aria-label="Prinsip kerja Trikasa Energi Tama">
      <div class="container d-flex flex-wrap justify-content-center justify-content-lg-between align-items-center gap-3 gap-lg-4">
        <span class="section-kicker">Prinsip kerja kami</span>
        <span>Perencanaan terukur</span><span class="ribbon-dot" aria-hidden="true"></span>
        <span>Keselamatan kerja</span><span class="ribbon-dot" aria-hidden="true"></span>
        <span>Kualitas yang dijaga</span><span class="ribbon-dot" aria-hidden="true"></span>
        <span>Komunikasi terbuka</span>
      </div>
    </section>

    <section class="container py-5" id="tentang">
      <div class="row gy-4 align-items-start py-lg-5">
        <div class="col-lg-6">
          <span class="section-kicker">01 · Tentang kami</span>
          <h2 class="display-5 fw-bold text-primary-emphasis mt-3">Setiap bangunan yang baik dimulai dari <span class="text-primary">kepercayaan.</span></h2>
        </div>
        <div class="col-lg-5 offset-lg-1">
          <p class="fs-5 text-secondary">Kami percaya pekerjaan konstruksi terbaik lahir dari tujuan yang dipahami bersama, rencana yang matang, dan pelaksanaan yang disiplin.</p>
          <p class="text-secondary">Trikasa Energi Tama hadir sebagai mitra untuk kebutuhan pembangunan, pekerjaan sipil, renovasi, dan pemeliharaan. Kami mengutamakan koordinasi yang jelas, perhatian terhadap keselamatan, dan mutu pada setiap tahap pekerjaan.</p>
          <a class="btn btn-outline-primary rounded-pill mt-2" href="#layanan">Lihat layanan kami <span aria-hidden="true">↗</span></a>
          <div class="border-start border-4 border-warning ps-3 mt-5">
            <strong class="d-block">Rencana yang baik memberi arah.</strong>
            <span class="text-secondary">Kerja sama yang baik mewujudkannya.</span>
          </div>
        </div>
      </div>
    </section>

    <section class="py-5 bg-white" id="layanan">
      <div class="container py-lg-4">
        <div class="row align-items-end g-3 mb-4">
          <div class="col-lg-7">
            <span class="section-kicker">02 · Bidang layanan</span>
            <h2 class="display-6 fw-bold text-primary-emphasis mt-2 mb-0">Keahlian untuk setiap tahap.</h2>
          </div>
          <p class="col-lg-4 offset-lg-1 text-secondary mb-1">Dukungan konstruksi yang menyeluruh, disesuaikan dengan kebutuhan dan ruang lingkup pekerjaan Anda.</p>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
          <div class="col">
            <article class="card service-card h-100 shadow-sm rounded-4">
              <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center mb-4"><span class="service-number">01</span><span class="service-symbol" aria-hidden="true">⌂</span></div>
                <h3 class="h4 fw-bold text-primary-emphasis">Gedung &amp; fasilitas</h3>
                <p class="text-secondary mb-0">Pembangunan dan pekerjaan fasilitas dengan perhatian pada fungsi, detail, serta mutu pelaksanaan.</p>
              </div>
            </article>
          </div>
          <div class="col">
            <article class="card service-card h-100 shadow-sm rounded-4">
              <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center mb-4"><span class="service-number">02</span><span class="service-symbol" aria-hidden="true">▦</span></div>
                <h3 class="h4 fw-bold text-primary-emphasis">Pekerjaan sipil</h3>
                <p class="text-secondary mb-0">Pekerjaan struktur dan infrastruktur yang disiapkan dengan perencanaan teknis serta koordinasi lapangan.</p>
              </div>
            </article>
          </div>
          <div class="col">
            <article class="card service-card h-100 shadow-sm rounded-4">
              <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center mb-4"><span class="service-number">03</span><span class="service-symbol" aria-hidden="true">⌕</span></div>
                <h3 class="h4 fw-bold text-primary-emphasis">Renovasi &amp; pemeliharaan</h3>
                <p class="text-secondary mb-0">Perbaikan dan pemeliharaan untuk menjaga fungsi ruang dan memperpanjang usia bangunan.</p>
              </div>
            </article>
          </div>
        </div>
      </div>
    </section>

    <section class="container py-5" id="pendekatan">
      <div class="row gy-4 py-lg-4">
        <div class="col-lg-5">
          <span class="section-kicker">03 · Pendekatan kami</span>
          <h2 class="display-6 fw-bold text-primary-emphasis mt-3">Langkah jelas.<br><span class="text-primary">Hasil yang terjaga.</span></h2>
          <p class="text-secondary">Kami menyusun proses kerja agar semua pihak memahami tujuan, tanggung jawab, dan perkembangan pekerjaan.</p>
          <a class="btn btn-primary rounded-pill mt-2" href="login.php">Masuk ke portal <span aria-hidden="true">↗</span></a>
        </div>
        <div class="col-lg-6 offset-lg-1">
          <ol class="list-group list-group-flush approach-list">
            <li class="list-group-item d-flex gap-3"><span class="approach-step">01</span><div><h3 class="h6 fw-bold mb-1">Pahami kebutuhan</h3><p class="text-secondary mb-0">Menyepakati tujuan, fungsi, batas pekerjaan, dan kebutuhan di lapangan.</p></div></li>
            <li class="list-group-item d-flex gap-3"><span class="approach-step">02</span><div><h3 class="h6 fw-bold mb-1">Rencanakan pekerjaan</h3><p class="text-secondary mb-0">Menyiapkan lingkup, urutan kerja, sumber daya, serta koordinasi yang diperlukan.</p></div></li>
            <li class="list-group-item d-flex gap-3"><span class="approach-step">03</span><div><h3 class="h6 fw-bold mb-1">Laksanakan dengan disiplin</h3><p class="text-secondary mb-0">Menjaga komunikasi, keselamatan, dan pemeriksaan mutu selama pekerjaan berjalan.</p></div></li>
            <li class="list-group-item d-flex gap-3"><span class="approach-step">04</span><div><h3 class="h6 fw-bold mb-1">Tinjau dan serah terima</h3><p class="text-secondary mb-0">Memeriksa hasil pekerjaan bersama dan menutup pekerjaan dengan tertib.</p></div></li>
          </ol>
        </div>
      </div>
    </section>

    <section class="brand-cta py-5">
      <div class="container py-lg-4 d-lg-flex align-items-center justify-content-between gap-4">
        <div><span class="hero-kicker">Trikasa Energi Tama · Mitra konstruksi</span><h2 class="display-6 fw-bold mt-3 mb-0">Bangun langkah berikutnya bersama Trikasa Energi Tama.</h2></div>
        <a class="btn btn-warning btn-lg rounded-pill px-4 mt-4 mt-lg-0 flex-shrink-0" href="login.php">Masuk ke portal <span aria-hidden="true">↗</span></a>
      </div>
    </section>
  </main>

  <footer class="site-footer py-4">
    <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
      <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="index.php">
        <img src="assets/logo.png?v=<?= filemtime(public_path('assets/logo.png')) ?>" alt="">
        <span class="brand-name">Trikasa Energi Tama<small>MITRA KONSTRUKSI</small></span>
      </a>
      <span>Perencanaan · Konstruksi · Pemeliharaan</span>
      <span>© <?= date('Y') ?> Trikasa Energi Tama</span>
    </div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>

</html>
