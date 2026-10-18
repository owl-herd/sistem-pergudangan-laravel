<?php
namespace App\Services;
class AccessService { public function ensure(array $roles): void {

    $sessionUser = user();
    if (!$sessionUser) { go('login.php'); }
    $q = db()->prepare('SELECT id,nama,email,role,gudang_id,aktif FROM users WHERE id=? LIMIT 1');
    $q->execute([(int)($sessionUser['id'] ?? 0)]);
    $currentUser = $q->fetch();
    if (!$currentUser || !(int)$currentUser['aktif']) {
        auth()->logout();
        warehouse_status(403);
        warehouse_abort('Akun belum aktif atau belum dipetakan oleh administrator perusahaan.');
    }
    if (!in_array($currentUser['role'], $roles, true)) { warehouse_status(403); warehouse_abort('Anda tidak memiliki akses ke halaman ini.'); }
    if ($currentUser['role'] === 'admin_gudang') {
        if (!$currentUser['gudang_id']) { warehouse_status(403); warehouse_abort('Akun admin proyek belum ditautkan ke gudang.'); }
        $warehouse = db()->prepare("SELECT id FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 LIMIT 1");
        $warehouse->execute([(int)$currentUser['gudang_id']]);
        if (!$warehouse->fetchColumn()) { warehouse_status(403); warehouse_abort('Gudang proyek akun ini tidak aktif atau tidak ditemukan.'); }
    } elseif ($currentUser['role'] === 'admin_pusat' && $currentUser['gudang_id'] !== null) {
        warehouse_status(403);
        warehouse_abort('Akun ini tidak boleh ditautkan ke gudang proyek.');
    }
    auth()->user()?->refresh();

} }