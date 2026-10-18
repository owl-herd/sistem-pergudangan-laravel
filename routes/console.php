<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
Artisan::command('warehouse:bootstrap-super-admin', function () {
    if(DB::table('users')->where('role','super_admin')->exists()) { $this->error('Super Admin sudah tersedia.'); return 1; }
    $name=$this->ask('Nama Super Admin');$email=strtolower(trim($this->ask('Email')));$password=$this->secret('Kata sandi');$confirm=$this->secret('Konfirmasi kata sandi');
    if(!$name || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12 || strlen($password)>200 || $password!==$confirm) { $this->error('Data tidak valid.');return 1; }
    $pdo=db();$lock=(int)$pdo->query("SELECT GET_LOCK('gudangkita_bootstrap_super_admin',10)")->fetchColumn();if($lock!==1) return 1;
    try { $pdo->beginTransaction();if((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn()>0) throw new RuntimeException('Super Admin sudah tersedia.');
        $pdo->prepare("INSERT INTO users (nama,email,password_hash,role,aktif) VALUES (?,?,?,'super_admin',1)")->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO riwayat_pengelolaan_akun (pelaku_nama,sasaran_id,sasaran_nama,sasaran_email,aksi,detail) VALUES ('Bootstrap CLI',?,?,?,'bootstrap','Super Admin pertama dibuat melalui Artisan.')")->execute([$id,$name,$email]);$pdo->commit();$this->info('Super Admin dibuat.');
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();$this->error($e->getMessage());return 1; } finally { $pdo->query("SELECT RELEASE_LOCK('gudangkita_bootstrap_super_admin')"); }
})->purpose('Bootstrap Super Admin pertama pada database terpisah');

Artisan::command('warehouse:weekly-reports', function () { app(App\Services\WarehouseNotifications::class)->weekly(db()); $this->info('Notifikasi laporan mingguan tersedia.'); });
Illuminate\Support\Facades\Schedule::command('warehouse:weekly-reports')->weeklyOn(1, '08:00')->timezone(config('app.timezone'));
