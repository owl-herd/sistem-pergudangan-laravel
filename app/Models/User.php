<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable {
    protected $table='users';
    public $timestamps=false;
    protected $guarded=['id'];
    protected $hidden=['password_hash'];
    protected function casts(): array { return ['aktif'=>'boolean','gudang_id'=>'integer']; }
    public function getAuthPasswordName() { return 'password_hash'; }
    public function getRememberTokenName() { return ''; }
}
