<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Asisten Pergudangan · Trikasa Energi Tama</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(public_path('assets/style.css')) ?>">
</head>
<body>
<main class="container py-4" style="max-width: 850px">
  <a class="btn btn-outline-primary btn-sm mb-4" href="dashboard.php">Kembali ke dashboard</a>
  <section class="app-card card shadow-sm">
    <div class="card-body p-4">
      <span class="section-kicker">Bantuan proses · Pergudangan</span>
      <h1 class="h3 fw-bold text-primary-emphasis mt-1">Asisten Pergudangan</h1>
      <p class="text-secondary">Tanyakan tentang alur dan aturan pergudangan. Asisten tidak membaca stok terkini atau melakukan perubahan data.</p>
      <div class="alert alert-warning small" role="note">Jangan masukkan password, data pribadi, atau informasi rahasia. Pertanyaan dikirim ke layanan AI OpenAI.</div>
      @if ($errors->has('assistant'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('assistant') }}</div>
      @endif
      @if ($errors->has('question'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('question') }}</div>
      @endif
      @if (session('assistant_answer'))
        <div class="alert alert-primary" role="status"><strong>Jawaban:</strong><br>{!! nl2br(e(session('assistant_answer'))) !!}</div>
      @endif
      <form method="post" action="asisten.php">
        @csrf
        <label for="question" class="form-label fw-semibold">Pertanyaan</label>
        <textarea class="form-control mb-3" id="question" name="question" rows="4" maxlength="2000" required>{{ old('question') }}</textarea>
        <button class="btn btn-primary" type="submit">Tanyakan</button>
      </form>
    </div>
  </section>
</main>
</body>
</html>
