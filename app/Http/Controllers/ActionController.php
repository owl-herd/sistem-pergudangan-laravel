<?php

namespace App\Http\Controllers;

use App\Services\WarehouseActions;
use App\Services\WarehousePurchasingActions;
use App\Services\WarehouseUserAdministrationActions;

class ActionController extends Controller
{
    public function aksi_admin(WarehouseActions $actions)
    {
        return $actions->aksi_admin();
    }

    public function aksi_pembelian(WarehousePurchasingActions $actions)
    {
        return $actions->aksi_pembelian();
    }

    public function aksi_pengguna(WarehouseUserAdministrationActions $actions)
    {
        return $actions->aksi_pengguna();
    }
}
