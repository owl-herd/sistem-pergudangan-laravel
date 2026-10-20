<?php

namespace App\Services;

use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class WarehouseUserAdministrationActions
{
    public function aksi_pengguna()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];
        require_role('super_admin');
        if ($server['REQUEST_METHOD'] !== 'POST') {
            go('super_admin.php');
        }
        check_csrf();

        $pdo = db();
        $text = static fn (string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
        $textLength = static function (string $value): int {
            $length = preg_match_all('/./us', $value);

            return $length === false ? PHP_INT_MAX : $length;
        };
        $roles = [
            'pengguna' => 'Pengguna',
            'admin_pusat' => 'Admin Pusat',
            'admin_gudang' => 'Admin Gudang Proyek',
            'manager' => 'Manajer',
            'purchasing' => 'Purchasing',
            'super_admin' => 'Super Admin',
        ];
        $action = $text('aksi');

        try {
            if (! in_array($action, ['buat', 'simpan'], true)) {
                throw new RuntimeException('Aksi pengelolaan akun tidak dikenal.');
            }
            $name = $text('nama');
            $email = strtolower($text('email'));
            $role = $text('role');
            $activeInput = $post['aktif'] ?? null;
            $warehouseInput = $post['gudang_id'] ?? '';
            $password = is_string($post['password'] ?? null) ? $post['password'] : '';
            $userId = $action === 'simpan' ? (is_string($post['user_id'] ?? null) && ctype_digit($post['user_id']) ? (int) $post['user_id'] : 0) : 0;
            $active = is_string($activeInput) && in_array($activeInput, ['0', '1'], true) ? (int) $activeInput : -1;

            if ($name === '' || $textLength($name) > 120 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $textLength($email) > 190) {
                throw new RuntimeException('Nama dan email harus diisi dengan format yang benar.');
            }
            if (! isset($roles[$role]) || $active < 0) {
                throw new RuntimeException('Peran atau status akun tidak valid.');
            }
            if ($action === 'buat' && in_array($role, ['pengguna', 'super_admin'], true)) {
                throw new RuntimeException('Role Pengguna dan Super Admin tidak tersedia saat membuat akun. Untuk Super Admin, ubah role akun yang sudah ada.');
            }
            if ($action === 'buat' && (strlen($password) < 8 || strlen($password) > 200)) {
                throw new RuntimeException('Kata sandi awal harus berisi 8 sampai 200 karakter.');
            }
            if ($action === 'simpan' && $userId < 1) {
                throw new RuntimeException('ID akun tidak valid.');
            }
            if ($action === 'simpan' && $userId === (int) user()['id'] && ($role !== 'super_admin' || $active !== 1)) {
                throw new RuntimeException('Anda tidak dapat mengganti peran atau menonaktifkan akun sendiri.');
            }

            $warehouseId = null;
            if ($role === 'admin_gudang') {
                $warehouseId = is_string($warehouseInput) && ctype_digit($warehouseInput) ? (int) $warehouseInput : 0;
                if ($warehouseId < 1) {
                    throw new RuntimeException('Pilih gudang proyek aktif untuk akun Admin Gudang.');
                }
                $warehouseQuery = $pdo->prepare("SELECT id FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 LIMIT 1");
                $warehouseQuery->execute([$warehouseId]);
                if (! $warehouseQuery->fetchColumn()) {
                    throw new RuntimeException('Gudang proyek yang dipilih tidak aktif atau tidak ditemukan.');
                }
            } elseif ($warehouseInput !== '' && $warehouseInput !== null) {
                throw new RuntimeException('Hanya role Admin Gudang Proyek yang dapat ditautkan ke gudang.');
            }

            if ($action === 'buat') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if ($passwordHash === false) {
                    throw new RuntimeException('Kata sandi tidak dapat diproses.');
                }
                $pdo->beginTransaction();
                $insert = $pdo->prepare('INSERT INTO users (nama,email,password_hash,role,gudang_id,aktif) VALUES (?,?,?,?,?,?)');
                $insert->execute([$name, $email, $passwordHash, $role, $warehouseId, $active]);
                $newUserId = (int) $pdo->lastInsertId();
                record_user_management_audit($pdo, $newUserId, $name, $email, 'akun_dibuat', 'Akun dibuat dengan role '.$roles[$role].' dan status '.($active ? 'Aktif' : 'Nonaktif').'.');
                $pdo->commit();
                flash('Akun baru berhasil dibuat.');
            } else {
                $pdo->beginTransaction();
                $lockedAdmins = array_map('intval', $pdo->query("SELECT id FROM users WHERE role='super_admin' AND aktif=1 FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN));
                $find = $pdo->prepare('SELECT id,nama,email,role,gudang_id,aktif FROM users WHERE id=? FOR UPDATE');
                $find->execute([$userId]);
                $current = $find->fetch();
                if (! $current) {
                    throw new RuntimeException('Akun yang akan diubah tidak ditemukan.');
                }

                $roleChanged = $current['role'] !== $role;
                $statusChanged = (int) $current['aktif'] !== $active;
                if ($current['role'] === 'super_admin' && ($roleChanged || ((int) $current['aktif'] === 1 && $active === 0))) {
                    $remainingAdmins = array_filter($lockedAdmins, static fn (int $id): bool => $id !== $userId);
                    if (count($remainingAdmins) < 1) {
                        throw new RuntimeException('Akun Super Admin aktif terakhir tidak dapat dinonaktifkan atau kehilangan rolenya.');
                    }
                }

                $profileChanges = [];
                if ($current['nama'] !== $name) {
                    $profileChanges[] = 'Nama: '.$current['nama'].' â†’ '.$name;
                }
                if ($current['email'] !== $email) {
                    $profileChanges[] = 'Email: '.$current['email'].' â†’ '.$email;
                }
                if ((int) ($current['gudang_id'] ?? 0) !== (int) ($warehouseId ?? 0)) {
                    $profileChanges[] = 'Pemetaan gudang diperbarui.';
                }
                if ($password !== '' && (strlen($password) < 12 || strlen($password) > 200)) {
                    throw new RuntimeException('Kata sandi baru harus berisi 12 sampai 200 karakter.');
                }

                $values = [$name, $email, $role, $warehouseId, $active];
                $passwordHash = null;
                if ($password !== '') {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    if ($passwordHash === false) {
                        throw new RuntimeException('Kata sandi tidak dapat diproses.');
                    }
                    $values[] = $passwordHash;
                }
                $values[] = $userId;
                $sql = 'UPDATE users SET nama=?,email=?,role=?,gudang_id=?,aktif=?'.($passwordHash !== null ? ',password_hash=?' : '').' WHERE id=?';
                $pdo->prepare($sql)->execute($values);

                if ($roleChanged) {
                    record_user_management_audit($pdo, $userId, $name, $email, 'role_diubah', 'Role diubah dari '.$roles[$current['role']].' menjadi '.$roles[$role].'.');
                }
                if ($statusChanged) {
                    record_user_management_audit($pdo, $userId, $name, $email, $active ? 'akun_diaktifkan' : 'akun_dinonaktifkan', 'Status akun diubah dari '.((int) $current['aktif'] ? 'Aktif' : 'Nonaktif').' menjadi '.($active ? 'Aktif' : 'Nonaktif').'.');
                }
                if ($password !== '') {
                    $profileChanges[] = 'Kata sandi diperbarui.';
                }
                if ($profileChanges) {
                    record_user_management_audit($pdo, $userId, $name, $email, 'akun_diperbarui', implode(' ', $profileChanges));
                }
                $pdo->commit();
                flash($roleChanged || $statusChanged || $profileChanges ? 'Perubahan akun berhasil disimpan.' : 'Tidak ada perubahan pada akun.');
            }
        } catch (HttpResponseException $error) {
            throw $error;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $error instanceof PDOException
                ? 'Perubahan akun gagal disimpan. Pastikan email belum digunakan dan skema database sudah diperbarui.'
                : $error->getMessage();
            flash($message, 'error');
        }
        go('super_admin.php#akun');

    }
}
