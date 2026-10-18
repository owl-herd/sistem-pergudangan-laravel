<div class="navbar-account navbar-account-warehouse account-panel">
  <div class="account-panel-identity">
    @include('warehouse.partials.account-avatar')
    <div class="account-panel-person"><span class="account-panel-name">{{ user()['nama'] }}</span><span class="account-panel-location">{{ $accountLocation }}</span></div>
    <span class="badge rounded-pill text-bg-warning account-panel-role">{{ $accountRole }}</span>
    <details class="account-panel-notification-menu">
      <summary class="account-panel-notifications position-relative" aria-label="Notifikasi, {{ $accountNotificationCount }} belum dibaca" title="Notifikasi">
        <svg class="account-panel-notifications-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
        </svg>
        <span class="visually-hidden">Notifikasi</span>
        <span class="badge rounded-pill text-bg-danger account-panel-notifications-badge">{{ $accountNotificationCount }}</span>
      </summary>
      <div class="account-panel-notification-dropdown" aria-label="Daftar notifikasi">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><strong>Notifikasi terbaru</strong><span class="badge rounded-pill text-bg-danger">{{ $accountNotificationCount }}</span></div>
        @forelse($accountNotifications ?? [] as $notification)
          <article class="account-panel-notification-item">
            @if(isset($notification['message']))
              <p class="mb-1">{{ $notification['message'] }}</p><small class="text-secondary">{{ $notification['created_at'] }}</small><a href="{{ $notification['url'] }}">Lihat detail</a>
            @else
              <p class="mb-1"><strong>{{ $notification['peminta'] }}</strong> mengajukan {{ $notification['kode'] }} · {{ $notification['barang'] }}</p><small class="text-secondary">{{ date('d M Y H:i', strtotime($notification['created_at'])) }}</small><a href="#permintaan-{{ $notification['request_id'] }}">Lihat permintaan</a>
            @endif
          </article>
        @empty
          <p class="text-secondary small mb-0">Tidak ada notifikasi baru.</p>
        @endforelse
        <a class="account-panel-notification-all" href="#notifikasi">Lihat semua notifikasi</a>
      </div>
    </details>
  </div>
  <div class="account-panel-actions">
    <a class="btn btn-outline-light btn-sm rounded-pill px-3" href="profil.php">Profil saya</a>
    <form method="post" action="logout.php" class="d-inline">@csrf<button class="btn btn-outline-light btn-sm rounded-pill px-3" type="submit">Keluar</button></form>
  </div>
</div>
