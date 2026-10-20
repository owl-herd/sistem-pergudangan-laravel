<?php

namespace App\Http\Controllers;

use App\Services\WarehousePages;
use App\Services\WarehouseReports;

class PageController extends Controller
{
    public function bukti_persetujuan(WarehousePages $pages)
    {
        return $pages->bukti_persetujuan();
    }

    public function dashboard(WarehousePages $pages)
    {
        return $pages->dashboard();
    }

    public function activity(WarehousePages $pages)
    {
        return $pages->activity();
    }

    public function detail_penerimaan_pembelian(WarehousePages $pages)
    {
        return $pages->detail_penerimaan_pembelian();
    }

    public function foto_penerimaan_pembelian(WarehousePages $pages)
    {
        return $pages->foto_penerimaan_pembelian();
    }

    public function index(WarehousePages $pages)
    {
        return $pages->index();
    }

    public function laporan_pdf(WarehouseReports $pages)
    {
        return $pages->laporan_pdf();
    }

    public function laporan_csv(WarehouseReports $pages)
    {
        return $pages->laporan_csv();
    }

    public function login(WarehousePages $pages)
    {
        return $pages->login();
    }

    public function penerimaan_pembelian(WarehousePages $pages)
    {
        return $pages->penerimaan_pembelian();
    }

    public function purchasing(WarehousePages $pages)
    {
        return $pages->purchasing();
    }

    public function super_admin(WarehousePages $pages)
    {
        return $pages->super_admin();
    }

    public function surat_jalan(WarehousePages $pages)
    {
        return $pages->surat_jalan();
    }
}
