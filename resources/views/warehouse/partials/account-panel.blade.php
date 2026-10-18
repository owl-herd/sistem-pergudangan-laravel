<div class="navbar-account navbar-account-warehouse account-panel">
  <div class="account-panel-identity">
    @include('warehouse.partials.account-avatar')
    <div class="account-panel-person"><span class="account-panel-name">{{ user()['nama'] }}</span><span class="account-panel-location">{{ $accountLocation }}</span></div>
    <span class="badge rounded-pill text-bg-warning account-panel-role">{{ $accountRole }}</span>
  </div>
  <a class="btn btn-outline-light btn-sm rounded-pill px-3 account-panel-notifications" href="#notifikasi" aria-label="Notifikasi, {{ $accountNotificationCount }} belum dibaca">Notifikasi <span class="badge rounded-pill text-bg-danger ms-1">{{ $accountNotificationCount }}</span></a>
  <div class="account-panel-actions">
    <a class="btn btn-outline-light btn-sm rounded-pill px-3" href="profil.php">Profil saya</a>
    <form method="post" action="logout.php" class="d-inline">@csrf<button class="btn btn-outline-light btn-sm rounded-pill px-3" type="submit">Keluar</button></form>
  </div>
</div>
