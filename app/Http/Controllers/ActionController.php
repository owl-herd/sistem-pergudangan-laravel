<?php
namespace App\Http\Controllers;
use App\Services\WarehouseActions;
class ActionController extends Controller {
public function aksi_admin(WarehouseActions $actions) { return $actions->aksi_admin(); }
public function aksi_pembelian(WarehouseActions $actions) { return $actions->aksi_pembelian(); }
public function aksi_pengguna(WarehouseActions $actions) { return $actions->aksi_pengguna(); }
}