@php
    $avatarAccount = auth()->user();
    $avatarPath = \Illuminate\Support\Facades\DB::table('user_profiles')->where('user_id', $avatarAccount->id)->value('photo_path');
    $avatarExists = $avatarPath && \Illuminate\Support\Facades\Storage::disk('local')->exists($avatarPath);
@endphp
<a href="profil.php" class="account-avatar d-inline-flex align-items-center justify-content-center rounded-circle text-decoration-none border border-light" style="width:40px;height:40px;min-width:40px;max-width:40px;flex:0 0 40px;color:white;overflow:hidden;background:rgba(255,255,255,.15)" aria-label="Buka profil {{ $avatarAccount->nama }}" title="Profil {{ $avatarAccount->nama }}">
    @if($avatarExists)
        <img src="foto_profil.php" alt="Foto profil {{ $avatarAccount->nama }}" width="40" height="40" style="object-fit:cover">
    @else
        <span class="fw-bold" aria-hidden="true">{{ mb_strtoupper(mb_substr($avatarAccount->nama,0,1)) }}</span>
    @endif
</a>
