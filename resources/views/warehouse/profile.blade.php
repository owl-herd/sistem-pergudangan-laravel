<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Profil saya · Trikasa Energi Tama</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/style.css" rel="stylesheet"></head>
<body><nav class="navbar navbar-dark shadow-sm" style="background:var(--brand-blue-dark)"><div class="container"><a class="navbar-brand d-flex align-items-center gap-2" href="portal.php"><img src="assets/logo.png" alt="Logo Trikasa Energi Tama" width="40" height="40" style="object-fit:contain"><span class="fw-bold">Trikasa Energi Tama</span></a><a class="btn btn-outline-light rounded-pill" href="portal.php">Kembali ke dashboard</a></div></nav>
<main class="container py-4" style="max-width:760px"><h1 class="h2 fw-bold">Profil saya</h1><p class="text-secondary">Kelola foto, nama, dan kata sandi akun Anda.</p>
@if(session('status'))<div class="alert alert-success" role="alert">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="profil.php" enctype="multipart/form-data" class="card shadow-sm"><div class="card-body p-4">@csrf
<div class="d-flex align-items-center gap-3 mb-4">@if($hasPhoto)<button type="button" id="openProfilePhoto" class="btn p-0 rounded-circle" aria-label="Perbesar foto profil" aria-haspopup="dialog" aria-controls="profilePhotoDialog" title="Klik untuk memperbesar foto"><img src="foto_profil.php" alt="Foto profil Anda" class="rounded-circle border" width="96" height="96" style="object-fit:cover"></button>@else<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fs-1" style="width:96px;height:96px">{{ mb_substr($account->nama,0,1) }}</div>@endif<div><strong>{{ $account->nama }}</strong><div class="text-secondary">{{ $account->email }}</div><small class="text-secondary">{{ str_replace('_',' ',$account->role) }}</small></div></div>
<div class="mb-3"><label for="nama" class="form-label">Nama</label><input id="nama" name="nama" class="form-control" maxlength="120" required value="{{ old('nama',$account->nama) }}" autocomplete="name"></div>
<div class="mb-3"><label for="photo" class="form-label">Foto profil</label><input id="photo" name="photo" type="file" class="form-control" accept="image/jpeg,image/png,image/webp"><div class="form-text">JPEG, PNG, atau WebP. Maksimal 4 MB dan 4096 × 4096 piksel.</div></div>
@if($hasPhoto)<div class="form-check mb-3"><input id="remove_photo" class="form-check-input" type="checkbox" name="remove_photo" value="1"><label class="form-check-label" for="remove_photo">Hapus foto profil</label></div>@endif
<hr><h2 class="h5">Ganti kata sandi</h2><p class="text-secondary small">Kosongkan kolom berikut jika tidak ingin mengganti kata sandi.</p>
<div class="mb-3"><label for="current_password" class="form-label">Kata sandi saat ini</label><input id="current_password" name="current_password" type="password" class="form-control" autocomplete="current-password"></div>
<div class="mb-3"><label for="password" class="form-label">Kata sandi baru</label><input id="password" name="password" type="password" class="form-control" minlength="8" maxlength="200" autocomplete="new-password"><div class="form-text">Minimal 8 karakter.</div></div>
<div class="mb-4"><label for="password_confirmation" class="form-label">Konfirmasi kata sandi baru</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password"></div>
<button class="btn btn-primary rounded-pill px-4" type="submit">Simpan profil</button></div></form></main>@if($hasPhoto)
<style>#profilePhotoDialog { border:0; border-radius:1rem; padding:1.25rem; width:min(640px,92vw); max-height:90vh; } #profilePhotoDialog::backdrop { background:rgba(0,0,0,.7); }</style>
<dialog id="profilePhotoDialog" aria-labelledby="profilePhotoTitle">
<div class="d-flex align-items-center justify-content-between gap-3 mb-3"><h2 id="profilePhotoTitle" class="h5 mb-0">Foto profil {{ $account->nama }}</h2><button type="button" id="closeProfilePhoto" class="btn-close" aria-label="Tutup foto profil" autofocus></button></div>
<img src="foto_profil.php" alt="Foto profil {{ $account->nama }}" class="d-block mx-auto" style="max-width:100%;max-height:70vh;object-fit:contain">
</dialog>
<script>
const profilePhotoDialog = document.getElementById('profilePhotoDialog');
document.getElementById('openProfilePhoto').addEventListener('click', () => profilePhotoDialog.showModal());
document.getElementById('closeProfilePhoto').addEventListener('click', () => profilePhotoDialog.close());
profilePhotoDialog.addEventListener('click', (event) => {
    const bounds = profilePhotoDialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) profilePhotoDialog.close();
});
</script>
@endif
</body></html>
