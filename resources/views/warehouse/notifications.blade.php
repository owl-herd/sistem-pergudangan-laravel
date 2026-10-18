<section class="app-card card shadow-sm mb-4 section-anchor" id="notifikasi"><div class="card-body p-3 p-lg-4">
<h2 class="h4 fw-bold text-primary-emphasis">Notifikasi <span class="badge text-bg-warning">{{ $notificationInbox['count'] }} belum dibaca</span></h2>
@forelse($notificationInbox['items'] as $notification)
<div class="border-bottom py-3 d-flex flex-wrap justify-content-between gap-2">
<div><p class="mb-1">{{ $notification['message'] }}</p><small class="text-secondary">{{ $notification['created_at'] }}</small><a class="btn btn-outline-primary btn-sm ms-2" href="{{ $notification['url'] }}">Lihat detail</a></div>
<form method="post" action="{{ url('notifikasi.php/'.$notification['id'].'/baca') }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit">Tandai dibaca</button></form>
</div>
@empty<p class="text-secondary mb-0">Tidak ada notifikasi baru.</p>@endforelse
</div></section>
