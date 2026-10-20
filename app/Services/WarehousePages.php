<?php

namespace App\Services;

use App\Models\User;
use DateTimeImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class WarehousePages
{
    public function bukti_persetujuan()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];

        require_role('purchasing', 'manager', 'admin_pusat', 'admin_gudang');

        $currentUser = user();
        $rawId = $queryInput['id'] ?? null;
        if (! is_string($rawId) || ! ctype_digit($rawId)) {
            warehouse_status(404);
            warehouse_abort('Dokumen tidak ditemukan.');
        }
        $normalizedId = ltrim($rawId, '0');
        $maxId = (string) PHP_INT_MAX;
        if ($normalizedId === '' || strlen($normalizedId) > strlen($maxId) || (strlen($normalizedId) === strlen($maxId) && strcmp($normalizedId, $maxId) > 0)) {
            warehouse_status(404);
            warehouse_abort('Dokumen tidak ditemukan.');
        }
        $requestId = (int) $normalizedId;

        $pdo = db();
        $query = $pdo->prepare('SELECT r.id,r.user_id,r.jumlah,r.catatan,r.status,r.tanggal,r.diproses_pada,b.kode,b.nama barang,b.satuan,requester.nama pengaju,processor.nama purchasing FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id JOIN users requester ON requester.id=r.user_id LEFT JOIN users processor ON processor.id=r.diproses_oleh WHERE r.id=? LIMIT 1');
        $query->execute([$requestId]);
        $request = $query->fetch();

        $canViewAllRequests = in_array($currentUser['role'], ['purchasing', 'manager'], true);
        $isApplicant = $request && (int) $request['user_id'] === (int) $currentUser['id'];
        if (! $request || (! $canViewAllRequests && ! $isApplicant)) {
            warehouse_status(404);
            warehouse_abort('Dokumen tidak ditemukan.');
        }
        if ($request['status'] !== 'disetujui') {
            warehouse_status(404);
            warehouse_abort('Dokumen tidak ditemukan.');
        }

        $documentNumber = 'BAPP-'.str_pad((string) $request['id'], 8, '0', STR_PAD_LEFT);
        $submittedDate = date('d M Y H:i', strtotime($request['tanggal']));
        $approvedDate = $request['diproses_pada'] ? date('d M Y H:i', strtotime($request['diproses_pada'])) : 'Tidak tercatat';
        $backUrl = $currentUser['role'] === 'purchasing' ? 'purchasing.php#permintaan' : 'dashboard.php#permintaan-pembelian';

        return view('warehouse.bukti_persetujuan', get_defined_vars());
    }

    public function dashboard()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];

        require_role('admin_pusat', 'admin_gudang', 'manager');
        $pdo = db();
        $role = user()['role'];
        $notificationService = app(WarehouseNotifications::class);
        if ($role === 'manager') {
            $notificationService->weekly($pdo);
        }
        $notificationInbox = $notificationService->inbox($pdo, (int) user()['id']);
        $reportTypes = ['semua' => 'Semua laporan', 'stok' => 'Stok per gudang', 'permintaan' => 'Permintaan barang', 'pengiriman' => 'Pengiriman dan penerimaan', 'pembelian' => 'Pengajuan pembelian', 'penerimaan_pembelian' => 'Penerimaan pembelian', 'penambahan' => 'Penambahan barang proyek', 'retur' => 'Retur peralatan', 'mutasi' => 'Mutasi dan pemakaian barang'];
        $centerId = (int) $pdo->query("SELECT id FROM gudang WHERE kode='PUSAT' AND jenis='pusat' AND aktif=1 LIMIT 1")->fetchColumn();
        if ($centerId < 1) {
            warehouse_status(503);
            warehouse_abort('Gudang Pusat belum disiapkan.');
        }
        $warehouseId = $role === 'manager' ? null : ($role === 'admin_pusat' ? $centerId : (int) user()['gudang_id']);
        $warehouseName = 'Semua gudang';
        $managerWarehouses = [];
        $selectedManagerWarehouseId = 0;
        if ($role === 'manager') {
            $warehouseName = 'Seluruh gudang aktif';
            $managerWarehouses = $pdo->query('SELECT id,nama FROM gudang WHERE aktif=1 ORDER BY nama')->fetchAll();
            $requestedWarehouseId = filter_var($queryInput['gudang_id'] ?? null, FILTER_VALIDATE_INT);
            if ($requestedWarehouseId !== false && $requestedWarehouseId !== null && $requestedWarehouseId > 0) {
                foreach ($managerWarehouses as $managerWarehouse) {
                    if ((int) $managerWarehouse['id'] === $requestedWarehouseId) {
                        $selectedManagerWarehouseId = $requestedWarehouseId;
                        break;
                    }
                }
            }
        } else {
            $q = $pdo->prepare('SELECT nama FROM gudang WHERE id=?');
            $q->execute([$warehouseId]);
            $warehouseName = (string) $q->fetchColumn();
        }
        $today = date('Y-m-d');
        $defaultStart = date('Y-m-01');
        $validDate = static function (mixed $value): bool {
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                return false;
            }
            [$year,$month,$day] = array_map('intval', explode('-', $value));

            return checkdate($month, $day, $year);
        };
        $start = $queryInput['tanggal_mulai'] ?? $defaultStart;
        $end = $queryInput['tanggal_akhir'] ?? $today;
        if (! $validDate($start)) {
            $start = $defaultStart;
        }
        if (! $validDate($end)) {
            $end = $today;
        }
        if ($start > $end) {
            [$start,$end] = [$end, $start];
        }
        $endExclusive = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');

        if ($role === 'manager') {
            $q = $pdo->query('SELECT COUNT(DISTINCT b.id) jenis,COALESCE(SUM(s.stok),0) unit,COUNT(CASE WHEN COALESCE(s.stok,0)<=b.stok_minimum THEN 1 END) rendah FROM barang b CROSS JOIN gudang g LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id=g.id WHERE g.aktif=1 AND b.aktif=1');
            $inventory = $q->fetch();
            $stockRows = [];
            if ($selectedManagerWarehouseId > 0) {
                $stockQuery = $pdo->prepare('SELECT g.id gudang_id,g.nama gudang,b.id barang_id,b.kode,b.nama,b.kategori_barang,b.satuan,b.stok_minimum,COALESCE(s.stok,0) stok FROM gudang g CROSS JOIN barang b LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE g.id=? AND g.aktif=1 AND b.aktif=1 ORDER BY b.kode');
                $stockQuery->execute([$selectedManagerWarehouseId]);
                $stockRows = $stockQuery->fetchAll();
            }
        } else {
            $q = $pdo->prepare('SELECT COUNT(*) jenis,COALESCE(SUM(COALESCE(s.stok,0)),0) unit,COUNT(CASE WHEN COALESCE(s.stok,0)<=b.stok_minimum THEN 1 END) rendah FROM barang b LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id=? WHERE b.aktif=1');
            $q->execute([$warehouseId]);
            $inventory = $q->fetch();
            $q = $pdo->prepare('SELECT ? gudang_id,? gudang,b.id barang_id,b.kode,b.nama,b.kategori_barang,b.satuan,b.stok_minimum,COALESCE(s.stok,0) stok FROM barang b LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id=? WHERE b.aktif=1 ORDER BY b.kode');
            $q->execute([$warehouseId, $warehouseName, $warehouseId]);
            $stockRows = $q->fetchAll();
        }
        $warehouseStockSummary = [];
        if (in_array($role, ['manager', 'admin_pusat'], true)) {
            $warehouseSummarySql = 'SELECT g.id,g.nama,COALESCE(SUM(COALESCE(s.stok,0)),0) unit,COUNT(CASE WHEN COALESCE(s.stok,0)<=b.stok_minimum THEN 1 END) rendah FROM gudang g CROSS JOIN barang b LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE g.aktif=1 AND b.aktif=1';
            if ($role === 'admin_pusat') {
                $warehouseSummarySql .= ' AND g.id=?';
                $warehouseSummarySql .= ' GROUP BY g.id,g.nama ORDER BY g.nama';
                $warehouseSummaryQuery = $pdo->prepare($warehouseSummarySql);
                $warehouseSummaryQuery->execute([$centerId]);
            } else {
                $warehouseSummarySql .= ' GROUP BY g.id,g.nama ORDER BY g.nama';
                $warehouseSummaryQuery = $pdo->query($warehouseSummarySql);
            }
            $warehouseStockSummary = $warehouseSummaryQuery->fetchAll();
        }
        $scopeSql = '';
        $scopeParams = [];
        if ($role === 'manager') {
            $scopeSql = ' AND EXISTS (SELECT 1 FROM gudang g WHERE g.id=t.gudang_id AND g.aktif=1)';
        } else {
            $scopeSql = ' AND t.gudang_id=?';
            $scopeParams[] = $warehouseId;
        }
        $movementSql = 'SELECT t.jenis,COALESCE(SUM(t.jumlah),0) jumlah FROM transaksi_gudang t WHERE t.tanggal>=? AND t.tanggal<?'.$scopeSql.' GROUP BY t.jenis';
        $movementQuery = $pdo->prepare($movementSql);
        $movementQuery->execute(array_merge([$start, $endExclusive], $scopeParams));
        $movementTotals = ['masuk' => 0, 'keluar' => 0, 'kembali' => 0, 'pemakaian' => 0];
        foreach ($movementQuery->fetchAll() as $movement) {
            $movementTotals[$movement['jenis']] = (int) $movement['jumlah'];
        }

        $requestConditions = ['p.gudang_tujuan_id IS NOT NULL', '((p.tanggal>=? AND p.tanggal<?) OR p.status=\'diajukan\')'];
        $requestParams = [$start, $endExclusive];
        if ($role === 'admin_pusat') {
            $requestConditions[] = 'p.gudang_asal_id=?';
            $requestParams[] = $centerId;
        }
        if ($role === 'admin_gudang') {
            $requestConditions[] = 'p.gudang_tujuan_id=?';
            $requestParams[] = $warehouseId;
        }
        if ($role === 'manager') {
            $requestConditions[] = 'EXISTS (SELECT 1 FROM gudang g WHERE g.id=p.gudang_tujuan_id AND g.aktif=1)';
        }
        $requestSql = "SELECT p.id,p.tanggal,p.status,p.catatan,p.nama_proyek,ga.nama gudang_asal,gt.nama gudang_tujuan,u.nama peminta,GROUP_CONCAT(CONCAT(b.kode,' · ',b.nama,' × ',dp.jumlah,' ',b.satuan) ORDER BY b.kode SEPARATOR ', ') barang,MAX(pe.id) pengiriman_id,MAX(pe.status) status_pengiriman FROM permintaan p JOIN users u ON u.id=p.user_id LEFT JOIN gudang ga ON ga.id=p.gudang_asal_id LEFT JOIN gudang gt ON gt.id=p.gudang_tujuan_id LEFT JOIN detail_permintaan dp ON dp.permintaan_id=p.id LEFT JOIN barang b ON b.id=dp.barang_id LEFT JOIN pengiriman pe ON pe.permintaan_id=p.id WHERE ";
        $requestSql .= implode(' AND ', $requestConditions)." GROUP BY p.id ORDER BY FIELD(p.status,'diajukan','diproses','selesai','ditolak'),p.tanggal DESC";
        if ($role !== 'manager') {
            $requestSql .= ' LIMIT 100';
        }
        $requestQuery = $pdo->prepare($requestSql);
        $requestQuery->execute($requestParams);
        $requestRows = $requestQuery->fetchAll();
        $pendingSql = "SELECT COUNT(*) FROM permintaan p WHERE p.gudang_tujuan_id IS NOT NULL AND p.status='diajukan'";
        $pendingParams = [];
        if ($role === 'admin_pusat') {
            $pendingSql .= ' AND p.gudang_asal_id=?';
            $pendingParams[] = $centerId;
        }
        if ($role === 'admin_gudang') {
            $pendingSql .= ' AND p.gudang_tujuan_id=?';
            $pendingParams[] = $warehouseId;
        }
        if ($role === 'manager') {
            $pendingSql .= ' AND EXISTS (SELECT 1 FROM gudang g WHERE g.id=p.gudang_tujuan_id AND g.aktif=1)';
        }
        $pendingQuery = $pdo->prepare($pendingSql);
        $pendingQuery->execute($pendingParams);
        $pending = (int) $pendingQuery->fetchColumn();
        $purchasingRequests = [];
        $pendingPurchasingRequests = 0;
        if ($role === 'manager') {
            $purchasingRequests = $pdo->query('SELECT r.id,r.user_id,r.jumlah,r.catatan,r.status,r.tanggal,r.diproses_pada,b.kode,b.nama barang,b.satuan,requester.nama peminta,processor.nama pemroses,COALESCE(received.jumlah_diterima,0) jumlah_diterima,COALESCE(received.jumlah_layak,0) jumlah_layak FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id JOIN users requester ON requester.id=r.user_id LEFT JOIN users processor ON processor.id=r.diproses_oleh LEFT JOIN (SELECT permintaan_pembelian_id,SUM(jumlah_diterima) jumlah_diterima,SUM(jumlah_layak) jumlah_layak FROM penerimaan_pembelian GROUP BY permintaan_pembelian_id) received ON received.permintaan_pembelian_id=r.id ORDER BY FIELD(r.status,\'menunggu\',\'disetujui\',\'ditolak\'),r.tanggal DESC,r.id DESC')->fetchAll();
            $pendingPurchasingRequests = (int) $pdo->query("SELECT COUNT(*) FROM permintaan_pembelian WHERE status='menunggu'")->fetchColumn();
        } else {
            $purchaseRequestQuery = $pdo->prepare('SELECT r.id,r.jumlah,r.catatan,r.status,r.tanggal,r.diproses_pada,b.kode,b.nama barang,b.satuan,processor.nama pemroses,COALESCE(received.jumlah_diterima,0) jumlah_diterima,COALESCE(received.jumlah_layak,0) jumlah_layak FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id LEFT JOIN users processor ON processor.id=r.diproses_oleh LEFT JOIN (SELECT permintaan_pembelian_id,SUM(jumlah_diterima) jumlah_diterima,SUM(jumlah_layak) jumlah_layak FROM penerimaan_pembelian GROUP BY permintaan_pembelian_id) received ON received.permintaan_pembelian_id=r.id WHERE r.user_id=? ORDER BY r.tanggal DESC,r.id DESC');
            $purchaseRequestQuery->execute([(int) user()['id']]);
            $purchasingRequests = $purchaseRequestQuery->fetchAll();
            $purchasePendingQuery = $pdo->prepare("SELECT COUNT(*) FROM permintaan_pembelian WHERE user_id=? AND status='menunggu'");
            $purchasePendingQuery->execute([(int) user()['id']]);
            $pendingPurchasingRequests = (int) $purchasePendingQuery->fetchColumn();
        }
        $receiptSummary = static function (array $request): string {
            $ordered = (int) $request['jumlah'];
            $received = (int) ($request['jumlah_diterima'] ?? 0);
            $ready = (int) ($request['jumlah_layak'] ?? 0);
            $remaining = max(0, $ordered - $received);
            $status = $remaining === 0 ? 'Semua jumlah tercatat' : ($received > 0 ? 'Diterima sebagian' : 'Belum diterima');

            return $status.' · diperiksa '.number_format($received).'/'.number_format($ordered).'; layak stok '.number_format($ready).'; sisa '.number_format($remaining);
        };

        $legacyRequests = [];
        $shipmentConditions = [];
        $shipmentParams = [];
        if ($role === 'manager') {
            $shipmentConditions[] = 'pe.dikirim_pada>=?';
            $shipmentConditions[] = 'pe.dikirim_pada<?';
            $shipmentParams = [$start, $endExclusive];
        }
        if ($role === 'admin_pusat') {
            $shipmentConditions[] = 'pe.gudang_asal_id=?';
            $shipmentParams[] = $centerId;
        }
        if ($role === 'admin_gudang') {
            $shipmentConditions[] = 'pe.gudang_tujuan_id=?';
            $shipmentParams[] = $warehouseId;
        }
        if ($role === 'manager') {
            $shipmentConditions[] = 'EXISTS (SELECT 1 FROM gudang g WHERE g.id=pe.gudang_asal_id AND g.aktif=1)';
            $shipmentConditions[] = 'EXISTS (SELECT 1 FROM gudang g WHERE g.id=pe.gudang_tujuan_id AND g.aktif=1)';
        }
        $shipmentQuery = $pdo->prepare('SELECT pe.id,pe.permintaan_id,pe.gudang_asal_id,pe.gudang_tujuan_id,pe.status,pe.dikirim_pada,pe.diterima_pada,pe.catatan_penerimaan,ga.nama gudang_asal,gt.nama gudang_tujuan,us.nama pengirim,ur.nama penerima FROM pengiriman pe JOIN gudang ga ON ga.id=pe.gudang_asal_id JOIN gudang gt ON gt.id=pe.gudang_tujuan_id JOIN users us ON us.id=pe.dikirim_oleh LEFT JOIN users ur ON ur.id=pe.diterima_oleh WHERE '.implode(' AND ', $shipmentConditions).' ORDER BY pe.dikirim_pada DESC');
        $shipmentQuery->execute($shipmentParams);
        $shipments = $shipmentQuery->fetchAll();

        $movementScope = $role === 'manager'
            ? ' AND EXISTS (SELECT 1 FROM gudang g WHERE g.id=t.gudang_id AND g.aktif=1)'
            : ' AND t.gudang_id=?';
        $movementQuery = $pdo->prepare('SELECT t.id,t.tanggal,t.jenis,t.jumlah,t.referensi,t.nomor_transaksi,t.nama_supplier,t.tujuan_keluar,t.satuan_transaksi,t.gudang_id,t.gudang_asal_id,t.gudang_tujuan_id,b.kode,b.nama barang,COALESCE(t.satuan_transaksi,b.satuan) satuan,g.nama gudang,ga.nama gudang_asal,gt.nama gudang_tujuan,u.nama petugas FROM transaksi_gudang t JOIN barang b ON b.id=t.barang_id JOIN gudang g ON g.id=t.gudang_id LEFT JOIN gudang ga ON ga.id=t.gudang_asal_id LEFT JOIN gudang gt ON gt.id=t.gudang_tujuan_id LEFT JOIN users u ON u.id=t.user_id WHERE t.tanggal>=? AND t.tanggal<?'.$movementScope.' ORDER BY t.tanggal DESC,t.id DESC');
        $movementQuery->execute(array_merge([$start, $endExclusive], $role === 'manager' ? [] : [$warehouseId]));
        $movementRows = $movementQuery->fetchAll();

        $additionConditions = ['pa.ditambahkan_pada>=?', 'pa.ditambahkan_pada<?'];
        $additionParams = [$start, $endExclusive];
        if ($role === 'admin_gudang') {
            $additionConditions[] = 'pa.gudang_id=?';
            $additionParams[] = $warehouseId;
        }
        $additionConditions[] = "EXISTS (SELECT 1 FROM gudang ag WHERE ag.id=pa.gudang_id AND ag.jenis='proyek')";
        $additionQuery = $pdo->prepare('SELECT pa.id,pa.jumlah,pa.catatan,pa.ditambahkan_pada,pa.kode_barang kode,pa.nama_barang barang,pa.satuan_barang satuan,pa.nama_proyek gudang,pa.nama_penambah petugas FROM penambahan_barang_proyek pa WHERE '.implode(' AND ', $additionConditions).' ORDER BY pa.ditambahkan_pada DESC,pa.id DESC');
        $additionQuery->execute($additionParams);
        $projectAdditions = $additionQuery->fetchAll();

        $returnConditions = ["(rp.status='menunggu_diterima' OR (rp.dikirim_pada>=? AND rp.dikirim_pada<?))"];
        $returnParams = [$start, $endExclusive];
        if ($role === 'admin_gudang') {
            $returnConditions[] = 'rp.gudang_asal_id=?';
            $returnParams[] = $warehouseId;
        }
        $returnConditions[] = "EXISTS (SELECT 1 FROM gudang rg WHERE rg.id=rp.gudang_asal_id AND rg.jenis='proyek')";
        $returnQuery = $pdo->prepare('SELECT rp.id,rp.barang_id,rp.gudang_asal_id,rp.jumlah,rp.catatan_kondisi,rp.status,rp.dikirim_pada,rp.diterima_pada,rp.kode_barang kode,rp.nama_barang barang,rp.satuan_barang satuan,rp.nama_proyek gudang,rp.nama_admin_proyek pengirim,ur.nama penerima FROM pengembalian_peralatan_proyek rp LEFT JOIN users ur ON ur.id=rp.diterima_oleh WHERE '.implode(' AND ', $returnConditions).' ORDER BY rp.dikirim_pada DESC,rp.id DESC');
        $returnQuery->execute($returnParams);
        $equipmentReturns = $returnQuery->fetchAll();

        $goods = [];
        if ($role !== 'manager') {
            $goodsQuery = $pdo->prepare('SELECT b.id,b.kode,b.nama,b.kategori,b.kategori_barang,b.satuan,COALESCE(s.stok,0) stok FROM barang b LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id=? WHERE b.aktif=1 ORDER BY b.nama');
            $goodsQuery->execute([$warehouseId]);
            $goods = $goodsQuery->fetchAll();
        }
        $equipmentGoods = array_values(array_filter($goods, static fn (array $item): bool => $item['kategori_barang'] === 'peralatan' && (int) $item['stok'] > 0));

        $catalog = [];
        $editing = null;
        if ($role === 'admin_pusat') {
            $q = $pdo->query('SELECT b.id,b.kode,b.nama,b.kategori,b.kategori_barang,b.satuan,b.aktif,COALESCE(s.stok,0) stok,r.catatan catatan_penghapusan,r.dibuat_pada dihapus_pada FROM barang b LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id='.$centerId.' LEFT JOIN (SELECT rh.* FROM riwayat_hapus_barang rh JOIN (SELECT barang_id,MAX(id) id FROM riwayat_hapus_barang GROUP BY barang_id) latest ON latest.id=rh.id) r ON r.barang_id=b.id ORDER BY b.kode ASC');
            $catalog = $q->fetchAll();
            if (! empty($queryInput['edit_barang']) && is_scalar($queryInput['edit_barang'])) {
                $q = $pdo->prepare('SELECT b.id,b.kode,b.nama,b.kategori,b.kategori_barang,b.satuan,COALESCE(s.stok,0) stok FROM barang b LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id=? WHERE b.id=?');
                $q->execute([$centerId, (int) $queryInput['edit_barang']]);
                $editing = $q->fetch() ?: null;
            }
        }
        $flashMessage = take_flash();
        $categoryLabels = ['barang_siap' => 'Material Project', 'barang_dipakai' => 'Barang Material Habis / Consumable', 'peralatan' => 'Peralatan'];
        $statusLabels = ['diajukan' => 'Menunggu diproses', 'disetujui' => 'Disetujui', 'diproses' => 'Dalam perjalanan', 'selesai' => 'Selesai diterima', 'ditolak' => 'Ditolak'];
        $purchasingStatusLabels = ['menunggu' => 'Menunggu diproses Purchasing', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
        $purchasingStatusClasses = ['menunggu' => 'text-bg-warning', 'disetujui' => 'text-bg-success', 'ditolak' => 'text-bg-danger'];
        $shipmentLabels = ['dalam_perjalanan' => 'Dalam perjalanan', 'diterima' => 'Diterima', 'diterima_dengan_selisih' => 'Diterima dengan selisih'];
        $movementLabels = ['masuk' => 'Barang masuk', 'keluar' => 'Dikirim keluar', 'kembali' => 'Barang kembali', 'pemakaian' => 'Pemakaian proyek'];
        $movementDisplayLabel = static function (array $row) use ($movementLabels): string {
            if (($row['referensi'] ?? '') === 'Penyesuaian stok melalui katalog') {
                return $row['jenis'] === 'masuk' ? 'Penyesuaian stok (+)' : 'Penyesuaian stok (-)';
            }
            if (($row['jenis'] ?? '') === 'kembali' && str_starts_with((string) ($row['referensi'] ?? ''), 'Peralatan kembali dari proyek #')) {
                return 'Pengembalian peralatan proyek';
            }

            return $movementLabels[$row['jenis']] ?? $row['jenis'];
        };
        $roleLabel = ['admin_pusat' => 'Admin Pusat', 'admin_gudang' => 'Admin Gudang Proyek', 'manager' => 'Manajer'][$role];

        return view('warehouse.dashboard', get_defined_vars());
    }

    public function detail_penerimaan_pembelian()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];

        require_role('purchasing', 'manager');

        $pdo = db();
        $currentUser = user();
        $isManager = $currentUser['role'] === 'manager';
        $requestId = isset($queryInput['id']) && is_string($queryInput['id'])
            ? filter_var($queryInput['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if ($requestId === false) {
            warehouse_status(404);
            warehouse_abort('Permintaan pembelian tidak ditemukan.');
        }

        $requestQuery = $pdo->prepare('SELECT r.id,r.jumlah,r.catatan,r.status,r.tanggal,r.diproses_pada,b.kode,b.nama barang,b.satuan,requester.nama peminta,processor.nama pemroses,COALESCE(received.jumlah_diterima,0) jumlah_diterima,COALESCE(received.jumlah_layak,0) jumlah_layak,COALESCE(received.jumlah_rusak,0) jumlah_rusak,COALESCE(received.jumlah_tidak_sesuai,0) jumlah_tidak_sesuai FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id JOIN users requester ON requester.id=r.user_id LEFT JOIN users processor ON processor.id=r.diproses_oleh LEFT JOIN (SELECT permintaan_pembelian_id,SUM(jumlah_diterima) jumlah_diterima,SUM(jumlah_layak) jumlah_layak,SUM(jumlah_rusak) jumlah_rusak,SUM(jumlah_tidak_sesuai) jumlah_tidak_sesuai FROM penerimaan_pembelian GROUP BY permintaan_pembelian_id) received ON received.permintaan_pembelian_id=r.id WHERE r.id=? LIMIT 1');
        $requestQuery->execute([$requestId]);
        $request = $requestQuery->fetch();
        if (! $request) {
            warehouse_status(404);
            warehouse_abort('Permintaan pembelian tidak ditemukan.');
        }

        $historyQuery = $pdo->prepare('SELECT id,kode_barang,nama_barang,satuan_barang,nama_gudang,nama_petugas,jumlah_diterima,jumlah_layak,jumlah_rusak,jumlah_tidak_sesuai,catatan_kondisi,diterima_pada FROM penerimaan_pembelian WHERE permintaan_pembelian_id=? ORDER BY diterima_pada DESC,id DESC');
        $historyQuery->execute([$requestId]);
        $receipts = $historyQuery->fetchAll();
        $photoQuery = $pdo->prepare('SELECT f.id foto_id,f.penerimaan_pembelian_id FROM penerimaan_pembelian_foto f JOIN penerimaan_pembelian p ON p.id=f.penerimaan_pembelian_id WHERE p.permintaan_pembelian_id=? ORDER BY p.diterima_pada DESC,p.id DESC,f.urutan');
        $photoQuery->execute([$requestId]);
        $photosByReceipt = [];
        foreach ($photoQuery->fetchAll() as $photo) {
            $photosByReceipt[(int) $photo['penerimaan_pembelian_id']][] = (int) $photo['foto_id'];
        }

        $ordered = (int) $request['jumlah'];
        $received = (int) $request['jumlah_diterima'];
        $ready = (int) $request['jumlah_layak'];
        $damaged = (int) $request['jumlah_rusak'];
        $mismatched = (int) $request['jumlah_tidak_sesuai'];
        $remaining = max(0, $ordered - $received);
        $statusLabels = ['menunggu' => 'Menunggu diproses', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
        $statusClasses = ['menunggu' => 'text-bg-warning', 'disetujui' => 'text-bg-success', 'ditolak' => 'text-bg-danger'];
        $flashMessage = take_flash();

        return view('warehouse.detail_penerimaan_pembelian', get_defined_vars());
    }

    public function foto_penerimaan_pembelian()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];
        ob_start();
        try {
            require_role('purchasing', 'admin_pusat', 'admin_gudang', 'manager');

            if (($server['REQUEST_METHOD'] ?? '') !== 'GET') {
                warehouse_status(404);
                warehouse_abort('Foto bukti tidak ditemukan.');
            }
            $photoId = isset($queryInput['id']) && is_string($queryInput['id'])
                ? filter_var($queryInput['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            if ($photoId === false) {
                warehouse_status(404);
                warehouse_abort('Foto bukti tidak ditemukan.');
            }

            $pdo = db();
            $photoQuery = $pdo->prepare('SELECT f.lokasi_file,f.mime_type,f.ukuran_bytes,p.gudang_id,r.status,requester.role role_pengaju,requester.gudang_id gudang_pengaju_id FROM penerimaan_pembelian_foto f JOIN penerimaan_pembelian p ON p.id=f.penerimaan_pembelian_id JOIN permintaan_pembelian r ON r.id=p.permintaan_pembelian_id JOIN users requester ON requester.id=r.user_id WHERE f.id=? LIMIT 1');
            $photoQuery->execute([$photoId]);
            $photo = $photoQuery->fetch();
            if (! $photo || $photo['status'] !== 'disetujui') {
                warehouse_status(404);
                warehouse_abort('Foto bukti tidak ditemukan.');
            }

            $currentUser = user();
            $allowed = in_array($currentUser['role'], ['purchasing', 'manager'], true);
            if ($currentUser['role'] === 'admin_pusat') {
                $centerId = (int) $pdo->query("SELECT id FROM gudang WHERE kode='PUSAT' AND jenis='pusat' AND aktif=1 LIMIT 1")->fetchColumn();
                $allowed = $centerId > 0 && (int) $photo['gudang_id'] === $centerId && $photo['role_pengaju'] === 'admin_pusat' && $photo['gudang_pengaju_id'] === null;
            } elseif ($currentUser['role'] === 'admin_gudang') {
                $warehouseId = (int) $currentUser['gudang_id'];
                $allowed = (int) $photo['gudang_id'] === $warehouseId && $photo['role_pengaju'] === 'admin_gudang' && (int) $photo['gudang_pengaju_id'] === $warehouseId;
            }
            if (! $allowed) {
                warehouse_status(404);
                warehouse_abort('Foto bukti tidak ditemukan.');
            }

            $mimeExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (! isset($mimeExtensions[$photo['mime_type']]) ||
                ! is_string($photo['lokasi_file']) ||
                ! preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D', $photo['lokasi_file']) ||
                ! str_ends_with($photo['lokasi_file'], '.'.$mimeExtensions[$photo['mime_type']])) {
                warehouse_status(404);
                warehouse_abort('Foto bukti tidak ditemukan.');
            }

            try {
                $storageDirectory = receipt_photo_storage_directory();
            } catch (Throwable) {
                warehouse_status(404);
                warehouse_abort('Foto bukti tidak ditemukan.');
            }
            $filePath = realpath($storageDirectory.DIRECTORY_SEPARATOR.$photo['lokasi_file']);
            $resolvedStorageDirectory = realpath($storageDirectory);
            $normalizePath = static fn (string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '/'));
            if ($filePath === false || $resolvedStorageDirectory === false || ! is_file($filePath) || ! is_readable($filePath) ||
                $normalizePath(dirname($filePath)) !== $normalizePath($resolvedStorageDirectory)) {
                warehouse_status(404);
                warehouse_abort('Foto bukti tidak ditemukan.');
            }
            $actualSize = filesize($filePath);
            if ($actualSize === false || $actualSize !== (int) $photo['ukuran_bytes']) {
                warehouse_status(404);
                warehouse_abort('Foto bukti tidak ditemukan.');
            }

            warehouse_header('Content-Type: '.$photo['mime_type']);
            warehouse_header('Content-Length: '.(string) $actualSize);
            warehouse_header('Content-Disposition: inline; filename="foto-penerimaan-'.$photoId.'.'.$mimeExtensions[$photo['mime_type']].'"');
            warehouse_header('X-Content-Type-Options: nosniff');
            warehouse_header("Content-Security-Policy: default-src 'none'; sandbox");
            warehouse_header('X-Frame-Options: DENY');
            warehouse_header('Referrer-Policy: no-referrer');
            warehouse_header('Cross-Origin-Resource-Policy: same-origin');
            warehouse_header('Cache-Control: private, no-store, max-age=0');
            readfile($filePath);
            warehouse_finish();

            $body = ob_get_clean();

            return warehouse_response($body);
        } catch (HttpResponseException $e) {
            $body = ob_get_clean();
            if ($e->getResponse()->getContent() === '') {
                $e->getResponse()->setContent($body);
            } throw $e;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    public function index()
    {
        return view('warehouse.index');
    }

    public function login()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];

        if (user()) {
            $userRole = user()['role'] ?? '';
            if ($userRole === 'super_admin') {
                go('super_admin.php');
            }
            if ($userRole === 'purchasing') {
                go('purchasing.php');
            }
            if (in_array($userRole, ['admin_pusat', 'admin_gudang', 'manager'], true)) {
                go('dashboard.php');
            }
            auth()->logout();
        }
        $error = '';
        if ($server['REQUEST_METHOD'] === 'POST') {
            check_csrf();
            $email = is_string($post['email'] ?? null) ? trim($post['email']) : '';
            $password = is_string($post['password'] ?? null) ? $post['password'] : '';
            $ip = (string) (request()->ip() ?? 'unknown');
            $ipKey = 'login:ip:'.hash('sha256', $ip);
            $emailKey = 'login:email:'.hash('sha256', strtolower($email).'|'.$ip);
            $error = 'Email atau kata sandi tidak sesuai.';
            if (! RateLimiter::tooManyAttempts($ipKey, 30) && ! RateLimiter::tooManyAttempts($emailKey, 5)) {
                $q = db()->prepare('SELECT id,nama,email,password_hash,role,gudang_id FROM users WHERE email=? AND aktif=1 LIMIT 1');
                $q->execute([$email]);
                $u = $q->fetch();
                if ($u && password_verify($password, $u['password_hash'])) {
                    $allowedRoles = ['super_admin', 'admin_pusat', 'admin_gudang', 'manager', 'purchasing'];
                    $warehouseReady = true;
                    if ($u['role'] === 'admin_gudang') {
                        $warehouse = db()->prepare("SELECT id FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 LIMIT 1");
                        $warehouse->execute([(int) ($u['gudang_id'] ?? 0)]);
                        $warehouseReady = (bool) $warehouse->fetchColumn();
                    }
                    if (in_array($u['role'], $allowedRoles, true) && $warehouseReady) {
                        RateLimiter::clear($emailKey);
                        RateLimiter::clear($ipKey);
                        session()->regenerate();
                        unset($u['password_hash']);
                        auth()->login(User::findOrFail($u['id']));
                        go($u['role'] === 'super_admin' ? 'super_admin.php' : ($u['role'] === 'purchasing' ? 'purchasing.php' : 'dashboard.php'));
                    }
                }
                RateLimiter::hit($emailKey, 60);
                RateLimiter::hit($ipKey, 60);
            }
        }

        return view('warehouse.login', get_defined_vars());
    }

    public function penerimaan_pembelian()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];

        require_role('admin_pusat', 'admin_gudang');

        $pdo = db();
        $currentUser = user();
        $warehouseId = $currentUser['role'] === 'admin_pusat' ? (int) $pdo->query("SELECT id FROM gudang WHERE kode='PUSAT' AND jenis='pusat' AND aktif=1 LIMIT 1")->fetchColumn() : (int) $currentUser['gudang_id'];
        $warehouseQuery = $pdo->prepare('SELECT id,nama,jenis FROM gudang WHERE id=? AND aktif=1 LIMIT 1');
        $warehouseQuery->execute([$warehouseId]);
        $warehouse = $warehouseQuery->fetch();
        if (! $warehouse || ($currentUser['role'] === 'admin_pusat' && $warehouse['jenis'] !== 'pusat') || ($currentUser['role'] === 'admin_gudang' && $warehouse['jenis'] !== 'proyek')) {
            warehouse_status(403);
            warehouse_abort('Gudang tujuan akun ini tidak aktif atau tidak ditemukan.');
        }

        $ownerScopeSql = $currentUser['role'] === 'admin_pusat'
            ? "requester.role='admin_pusat' AND requester.gudang_id IS NULL"
            : "requester.role='admin_gudang' AND requester.gudang_id=?";
        $scopeParams = $currentUser['role'] === 'admin_pusat' ? [] : [$warehouseId];
        $requestQuery = $pdo->prepare('SELECT r.id,r.user_id,r.barang_id,r.jumlah,r.catatan,r.tanggal,r.diproses_pada,requester.nama peminta,b.kode,b.nama barang,b.satuan,b.aktif barang_aktif,COALESCE(t.jumlah_diterima,0) jumlah_diterima,COALESCE(t.jumlah_layak,0) jumlah_layak,COALESCE(t.jumlah_rusak,0) jumlah_rusak,COALESCE(t.jumlah_tidak_sesuai,0) jumlah_tidak_sesuai FROM permintaan_pembelian r JOIN users requester ON requester.id=r.user_id JOIN barang b ON b.id=r.barang_id LEFT JOIN (SELECT permintaan_pembelian_id,SUM(jumlah_diterima) jumlah_diterima,SUM(jumlah_layak) jumlah_layak,SUM(jumlah_rusak) jumlah_rusak,SUM(jumlah_tidak_sesuai) jumlah_tidak_sesuai FROM penerimaan_pembelian GROUP BY permintaan_pembelian_id) t ON t.permintaan_pembelian_id=r.id WHERE r.status=\'disetujui\' AND '.$ownerScopeSql.' ORDER BY r.diproses_pada DESC,r.id DESC');
        $requestQuery->execute($scopeParams);
        $requests = $requestQuery->fetchAll();

        $historyQuery = $pdo->prepare('SELECT x.id,x.permintaan_pembelian_id,x.kode_barang,x.nama_barang,x.satuan_barang,x.nama_gudang,x.nama_petugas,x.jumlah_diterima,x.jumlah_layak,x.jumlah_rusak,x.jumlah_tidak_sesuai,x.catatan_kondisi,x.diterima_pada,x.transaksi_gudang_id FROM penerimaan_pembelian x JOIN permintaan_pembelian r ON r.id=x.permintaan_pembelian_id JOIN users requester ON requester.id=r.user_id WHERE r.status=\'disetujui\' AND '.$ownerScopeSql.' ORDER BY x.permintaan_pembelian_id,x.diterima_pada DESC,x.id DESC');
        $historyQuery->execute($scopeParams);
        $receiptsByRequest = [];
        foreach ($historyQuery->fetchAll() as $receipt) {
            $receiptsByRequest[(int) $receipt['permintaan_pembelian_id']][] = $receipt;
        }
        $photoQuery = $pdo->prepare('SELECT f.id foto_id,x.id penerimaan_id FROM penerimaan_pembelian_foto f JOIN penerimaan_pembelian x ON x.id=f.penerimaan_pembelian_id JOIN permintaan_pembelian r ON r.id=x.permintaan_pembelian_id JOIN users requester ON requester.id=r.user_id WHERE r.status=\'disetujui\' AND '.$ownerScopeSql.' ORDER BY x.permintaan_pembelian_id,x.diterima_pada DESC,x.id DESC,f.urutan');
        $photoQuery->execute($scopeParams);
        $photosByReceipt = [];
        foreach ($photoQuery->fetchAll() as $photo) {
            $photosByReceipt[(int) $photo['penerimaan_id']][] = (int) $photo['foto_id'];
        }

        $formTokens = [];
        foreach ($requests as $request) {
            if ((int) $request['jumlah'] > (int) $request['jumlah_diterima'] && (int) $request['barang_aktif'] === 1) {
                $formTokens[(int) $request['id']] = bin2hex(random_bytes(32));
            }
        }
        $flashMessage = take_flash();
        $dashboardAnchor = 'dashboard.php#pengajuan-pembelian';

        return view('warehouse.penerimaan_pembelian', get_defined_vars());
    }

    public function purchasing()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];

        require_role('purchasing');
        $pdo = db();
        $currentUser = user();
        $items = $pdo->query('SELECT id,kode,nama,kategori,kategori_barang,satuan,stok_minimum FROM barang WHERE aktif=1 ORDER BY kode')->fetchAll();
        $stockQuery = $pdo->query('SELECT g.nama gudang,b.kode,b.nama barang,b.kategori_barang,b.satuan,b.stok_minimum,COALESCE(s.stok,0) stok FROM gudang g CROSS JOIN barang b LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE g.aktif=1 AND b.aktif=1 ORDER BY g.nama,b.kode');
        $stockRows = $stockQuery->fetchAll();
        $warehouseStockSummary = [];
        foreach ($stockRows as $stockRow) {
            $warehouseName = $stockRow['gudang'];
            $warehouseStockSummary[$warehouseName] ??= ['nama' => $warehouseName, 'unit' => 0, 'rendah' => 0];
            $warehouseStockSummary[$warehouseName]['unit'] += (int) $stockRow['stok'];
            if ((int) $stockRow['stok'] <= (int) $stockRow['stok_minimum']) {
                $warehouseStockSummary[$warehouseName]['rendah']++;
            }
        }
        $warehouseStockSummary = array_values($warehouseStockSummary);
        $requestQuery = $pdo->query('SELECT r.id,r.user_id,r.jumlah,r.catatan,r.status,r.tanggal,r.diproses_pada,b.kode,b.nama barang,b.satuan,requester.nama peminta,processor.nama pemroses,COALESCE(received.jumlah_diterima,0) jumlah_diterima,COALESCE(received.jumlah_layak,0) jumlah_layak,COALESCE(received.jumlah_rusak,0) jumlah_rusak,COALESCE(received.jumlah_tidak_sesuai,0) jumlah_tidak_sesuai FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id JOIN users requester ON requester.id=r.user_id LEFT JOIN users processor ON processor.id=r.diproses_oleh LEFT JOIN (SELECT permintaan_pembelian_id,SUM(jumlah_diterima) jumlah_diterima,SUM(jumlah_layak) jumlah_layak,SUM(jumlah_rusak) jumlah_rusak,SUM(jumlah_tidak_sesuai) jumlah_tidak_sesuai FROM penerimaan_pembelian GROUP BY permintaan_pembelian_id) received ON received.permintaan_pembelian_id=r.id ORDER BY FIELD(r.status,\'menunggu\',\'disetujui\',\'ditolak\'),r.tanggal DESC,r.id DESC');
        $requests = $requestQuery->fetchAll();
        $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM permintaan_pembelian WHERE status='menunggu'")->fetchColumn();
        $notificationCountQuery = $pdo->prepare('SELECT COUNT(*) FROM purchasing_notifications WHERE recipient_id=? AND read_at IS NULL');
        $notificationCountQuery->execute([(int) $currentUser['id']]);
        $unreadNotificationCount = (int) $notificationCountQuery->fetchColumn();
        $notificationQuery = $pdo->prepare('SELECT n.id notification_id,n.created_at,r.id request_id,r.status,r.tanggal,r.jumlah,u.nama peminta,u.role pengaju_role,b.kode,b.nama barang,b.satuan FROM purchasing_notifications n JOIN permintaan_pembelian r ON r.id=n.permintaan_pembelian_id JOIN users u ON u.id=r.user_id JOIN barang b ON b.id=r.barang_id WHERE n.recipient_id=? AND n.read_at IS NULL ORDER BY n.created_at DESC,n.id DESC LIMIT 20');
        $notificationQuery->execute([(int) $currentUser['id']]);
        $unreadNotifications = $notificationQuery->fetchAll();
        $flashMessage = take_flash();
        $statusLabels = ['menunggu' => 'Menunggu diproses', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
        $statusClasses = ['menunggu' => 'text-bg-warning', 'disetujui' => 'text-bg-success', 'ditolak' => 'text-bg-danger'];
        $receiptSummary = static function (array $request): string {
            $ordered = (int) $request['jumlah'];
            $ready = (int) ($request['jumlah_layak'] ?? 0);
            $damaged = (int) ($request['jumlah_rusak'] ?? 0);
            $mismatched = (int) ($request['jumlah_tidak_sesuai'] ?? 0);
            $received = (int) ($request['jumlah_diterima'] ?? 0);
            $remaining = max(0, $ordered - $received);

            return 'Dipesan '.number_format($ordered).' '.(string) $request['satuan'].'; layak '.number_format($ready).'; rusak '.number_format($damaged).'; tidak sesuai '.number_format($mismatched).'; belum diterima '.number_format($remaining);
        };
        $categoryLabels = ['barang_siap' => 'Material Project', 'barang_dipakai' => 'Barang Material Habis / Consumable', 'peralatan' => 'Peralatan'];

        return view('warehouse.purchasing', get_defined_vars());
    }

    public function activity()
    {
        $queryInput = request()->query();
        require_role('super_admin', 'admin_pusat', 'admin_gudang', 'manager');
        $pdo = db();
        $actor = user();
        $role = $actor['role'];
        $today = date('Y-m-d');
        $defaultStart = date('Y-m-01');
        $validDate = static function (mixed $value): bool {
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                return false;
            }
            [$year,$month,$day] = array_map('intval', explode('-', $value));

            return checkdate($month, $day, $year);
        };
        $start = $queryInput['tanggal_mulai'] ?? $defaultStart;
        $end = $queryInput['tanggal_akhir'] ?? $today;
        if (! $validDate($start)) {
            $start = $defaultStart;
        }
        if (! $validDate($end)) {
            $end = $today;
        }
        if ($start > $end) {
            [$start,$end] = [$end, $start];
        }
        $endExclusive = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');
        $eventType = $queryInput['jenis'] ?? 'semua';
        $eventTypes = ['semua' => 'Semua aktivitas', 'mutasi' => 'Mutasi stok', 'stocktake' => 'Pemeriksaan dan Penyesuaian Stok', 'katalog' => 'Perubahan katalog'];
        if ($role === 'super_admin') {
            $eventTypes['akun'] = 'Pengelolaan akun';
        }
        abort_unless(is_string($eventType) && isset($eventTypes[$eventType]), 422);
        $warehouseId = null;
        if ($role === 'admin_pusat') {
            $warehouseId = (int) $pdo->query("SELECT id FROM gudang WHERE kode='PUSAT' AND jenis='pusat' AND aktif=1 LIMIT 1")->fetchColumn();
        } elseif ($role === 'admin_gudang') {
            $warehouseId = (int) $actor['gudang_id'];
        }
        $rows = [];
        $warehouseScope = static function (string $column) use ($role, $warehouseId): array {
            return match ($role) {
                'admin_pusat','admin_gudang' => [$column.'=?', [$warehouseId]],
                'manager' => ['EXISTS (SELECT 1 FROM gudang scope_gudang WHERE scope_gudang.id='.$column.' AND scope_gudang.aktif=1)', []],
                default => ['1=1', []],
            };
        };
        $eventLabel = [
            'item_created' => 'Barang dibuat',
            'item_updated' => 'Barang diperbarui',
            'item_activated' => 'Barang diaktifkan',
            'item_deactivated' => 'Barang dinonaktifkan',
            'item_archived' => 'Barang diarsipkan',
        ];
        $baseParams = [$start, $endExclusive];
        if ($eventType === 'semua' || $eventType === 'katalog') {
            [$scope,$scopeParams] = $warehouseScope('a.gudang_id');
            $query = $pdo->prepare("SELECT a.id,a.dibuat_pada,a.actor_name,g.nama gudang,a.event_key,a.subject_label,a.details FROM warehouse_activity_logs a LEFT JOIN gudang g ON g.id=a.gudang_id WHERE a.dibuat_pada>=? AND a.dibuat_pada<? AND a.subject_type='barang' AND ".$scope.' ORDER BY a.dibuat_pada DESC,a.id DESC LIMIT 200');
            $query->execute(array_merge($baseParams, $scopeParams));
            foreach ($query->fetchAll() as $row) {
                $rows[] = ['id' => (int) $row['id'], 'dibuat_pada' => $row['dibuat_pada'], 'aktor' => $row['actor_name'], 'gudang' => $row['gudang'] ?: '—', 'aktivitas' => $eventLabel[$row['event_key']] ?? $row['event_key'], 'subjek' => $row['subject_label'], 'detail' => $row['details'], 'sumber' => 'katalog'];
            }
        }
        if ($eventType === 'semua' || $eventType === 'stocktake') {
            [$scope,$scopeParams] = $warehouseScope('s.gudang_id');
            $query = $pdo->prepare('SELECT s.id,s.dibuat_pada,s.nama_petugas,g.nama gudang,b.kode,b.nama,s.stok_sebelum,s.stok_fisik,s.selisih,s.alasan FROM stock_adjustments s JOIN gudang g ON g.id=s.gudang_id JOIN barang b ON b.id=s.barang_id WHERE s.dibuat_pada>=? AND s.dibuat_pada<? AND '.$scope.' ORDER BY s.dibuat_pada DESC,s.id DESC LIMIT 200');
            $query->execute(array_merge($baseParams, $scopeParams));
            foreach ($query->fetchAll() as $row) {
                $rows[] = ['id' => (int) $row['id'], 'dibuat_pada' => $row['dibuat_pada'], 'aktor' => $row['nama_petugas'], 'gudang' => $row['gudang'], 'aktivitas' => 'Pemeriksaan dan Penyesuaian Stok', 'subjek' => $row['kode'].' · '.$row['nama'], 'detail' => 'Sistem '.$row['stok_sebelum'].'; fisik '.$row['stok_fisik'].'; selisih '.$row['selisih'].'. Alasan: '.$row['alasan'], 'sumber' => 'stocktake'];
            }
        }
        if ($eventType === 'semua' || $eventType === 'mutasi') {
            [$scope,$scopeParams] = $warehouseScope('t.gudang_id');
            $query = $pdo->prepare("SELECT t.id,t.tanggal,t.jenis,t.jumlah,t.referensi,t.nama_petugas,u.nama operator,g.nama gudang,b.kode,b.nama barang,COALESCE(t.satuan_transaksi,b.satuan) satuan FROM transaksi_gudang t JOIN gudang g ON g.id=t.gudang_id JOIN barang b ON b.id=t.barang_id LEFT JOIN users u ON u.id=t.user_id WHERE t.tanggal>=? AND t.tanggal<? AND (t.referensi IS NULL OR t.referensi NOT LIKE 'Stock opname #%') AND ".$scope.' ORDER BY t.tanggal DESC,t.id DESC LIMIT 200');
            $query->execute(array_merge($baseParams, $scopeParams));
            $movementLabels = ['masuk' => 'Barang masuk', 'keluar' => 'Barang keluar', 'kembali' => 'Barang kembali', 'pemakaian' => 'Pemakaian proyek'];
            foreach ($query->fetchAll() as $row) {
                $rows[] = ['id' => (int) $row['id'], 'dibuat_pada' => $row['tanggal'], 'aktor' => $row['nama_petugas'] ?: $row['operator'] ?: '—', 'gudang' => $row['gudang'], 'aktivitas' => $movementLabels[$row['jenis']] ?? $row['jenis'], 'subjek' => $row['kode'].' · '.$row['barang'], 'detail' => number_format((int) $row['jumlah']).' '.$row['satuan'].($row['referensi'] ? '. '.$row['referensi'] : ''), 'sumber' => 'mutasi'];
            }
        }
        if ($role === 'super_admin' && ($eventType === 'semua' || $eventType === 'akun')) {
            $query = $pdo->prepare('SELECT id,dibuat_pada,pelaku_nama,sasaran_nama,aksi,detail FROM riwayat_pengelolaan_akun WHERE dibuat_pada>=? AND dibuat_pada<? ORDER BY dibuat_pada DESC,id DESC LIMIT 200');
            $query->execute($baseParams);
            $accountEventLabels = [
                'bootstrap' => 'Bootstrap Super Admin',
                'akun_dibuat' => 'Akun dibuat',
                'akun_diperbarui' => 'Akun diperbarui',
                'role_diubah' => 'Role diubah',
                'akun_dinonaktifkan' => 'Akun dinonaktifkan',
                'akun_diaktifkan' => 'Akun diaktifkan',
            ];
            foreach ($query->fetchAll() as $row) {
                $rows[] = ['id' => (int) $row['id'], 'dibuat_pada' => $row['dibuat_pada'], 'aktor' => $row['pelaku_nama'], 'gudang' => '—', 'aktivitas' => $accountEventLabels[$row['aksi']] ?? $row['aksi'], 'subjek' => $row['sasaran_nama'], 'detail' => $row['detail'], 'sumber' => 'akun'];
            }
        }
        usort($rows, static fn (array $left, array $right): int => strcmp((string) $right['dibuat_pada'], (string) $left['dibuat_pada']) ?: ($right['id'] <=> $left['id']));
        $rows = array_slice($rows, 0, 200);
        $notificationInbox = app(WarehouseNotifications::class)->inbox($pdo, (int) $actor['id']);

        return response()->view('warehouse.activity', get_defined_vars())
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function super_admin()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];

        require_role('super_admin');
        $pdo = db();
        $currentUserId = (int) user()['id'];
        $warehouseStockSummary = $pdo->query('SELECT g.nama,COALESCE(SUM(s.stok),0) unit,SUM(CASE WHEN COALESCE(s.stok,0)<=b.stok_minimum THEN 1 ELSE 0 END) rendah FROM gudang g CROSS JOIN barang b LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE g.aktif=1 AND b.aktif=1 GROUP BY g.id,g.nama ORDER BY g.nama')->fetchAll();
        $roleLabels = [
            'pengguna' => 'Pengguna',
            'admin_pusat' => 'Admin Pusat',
            'admin_gudang' => 'Admin Gudang Proyek',
            'manager' => 'Manajer',
            'purchasing' => 'Purchasing',
            'super_admin' => 'Super Admin',
        ];
        $accounts = $pdo->query("SELECT u.id,u.nama,u.email,u.role,u.gudang_id,u.aktif,g.nama gudang,g.aktif gudang_aktif,p.status pemetaan_status FROM users u LEFT JOIN gudang g ON g.id=u.gudang_id LEFT JOIN pemetaan_akun_lama p ON p.user_id=u.id AND p.status='menunggu_konfirmasi' ORDER BY u.aktif DESC,u.nama,u.id")->fetchAll();
        $warehouses = $pdo->query("SELECT id,kode,nama,aktif FROM gudang WHERE jenis='proyek' ORDER BY aktif DESC,nama")->fetchAll();
        $managerAccounts = $pdo->query("SELECT id,nama,email FROM users WHERE role='manager' AND aktif=1 ORDER BY nama,id")->fetchAll();
        $managerAssignments = $pdo->query('SELECT mg.manager_id,mg.gudang_id,mg.ditugaskan_pada,u.nama manager,g.nama gudang FROM manager_gudang mg JOIN users u ON u.id=mg.manager_id JOIN gudang g ON g.id=mg.gudang_id ORDER BY u.nama,g.nama')->fetchAll();
        $pendingAccounts = $pdo->query("SELECT p.user_id,p.aktif_sebelumnya,u.nama,u.email FROM pemetaan_akun_lama p JOIN users u ON u.id=p.user_id WHERE p.role_lama='admin_gudang' AND p.status='menunggu_konfirmasi' ORDER BY u.nama,u.id")->fetchAll();
        $auditRows = $pdo->query('SELECT id,pelaku_nama,pelaku_email,sasaran_nama,sasaran_email,aksi,detail,dibuat_pada FROM riwayat_pengelolaan_akun ORDER BY dibuat_pada DESC,id DESC LIMIT 100')->fetchAll();
        $flashMessage = take_flash();
        $auditLabels = [
            'bootstrap' => 'Bootstrap Super Admin',
            'akun_dibuat' => 'Akun dibuat',
            'akun_diperbarui' => 'Akun diperbarui',
            'role_diubah' => 'Role diubah',
            'akun_dinonaktifkan' => 'Akun dinonaktifkan',
            'akun_diaktifkan' => 'Akun diaktifkan',
        ];

        $notificationInbox = app(WarehouseNotifications::class)->inbox($pdo, (int) user()['id']);

        return view('warehouse.super_admin', get_defined_vars());
    }

    public function surat_jalan()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];

        require_role('admin_pusat', 'admin_gudang', 'manager');

        $currentUser = user();
        $rawId = $queryInput['id'] ?? null;
        $normalizedId = is_string($rawId) && ctype_digit($rawId) ? ltrim($rawId, '0') : '';
        $maxId = (string) PHP_INT_MAX;
        if ($normalizedId === '' || strlen($normalizedId) > strlen($maxId) || (strlen($normalizedId) === strlen($maxId) && strcmp($normalizedId, $maxId) > 0)) {
            warehouse_status(404);
            warehouse_abort('Surat jalan tidak ditemukan.');
        }
        $pdo = db();
        $query = $pdo->prepare('SELECT pe.*,ga.nama gudang_asal,ga.kode kode_asal,ga.jenis jenis_asal,ga.aktif asal_aktif,gt.nama gudang_tujuan,gt.jenis jenis_tujuan,gt.aktif tujuan_aktif,u.nama pengirim,ur.nama penerima,p.catatan,p.nomor_kendaraan,p.nama_driver FROM pengiriman pe JOIN gudang ga ON ga.id=pe.gudang_asal_id JOIN gudang gt ON gt.id=pe.gudang_tujuan_id JOIN users u ON u.id=pe.dikirim_oleh LEFT JOIN users ur ON ur.id=pe.diterima_oleh JOIN permintaan p ON p.id=pe.permintaan_id WHERE pe.id=? LIMIT 1');
        $query->execute([(int) $normalizedId]);
        $shipment = $query->fetch();
        $canView = $shipment && $shipment['jenis_tujuan'] === 'proyek' && match ($currentUser['role']) {
            'admin_pusat' => $shipment['kode_asal'] === 'PUSAT' && $shipment['jenis_asal'] === 'pusat',
            'admin_gudang' => (int) $shipment['gudang_tujuan_id'] === (int) $currentUser['gudang_id'],
            'manager' => (bool) $shipment['asal_aktif'] && (bool) $shipment['tujuan_aktif'],
            default => false,
        };
        if (! $canView) {
            warehouse_status(404);
            warehouse_abort('Surat jalan tidak ditemukan.');
        }
        $query = $pdo->prepare("SELECT d.jumlah_dikirim,b.kode,b.nama,b.satuan,COALESCE((SELECT t.satuan_transaksi FROM transaksi_gudang t WHERE t.pengiriman_id=d.pengiriman_id AND t.barang_id=d.barang_id AND t.jenis='keluar' ORDER BY t.id LIMIT 1),b.satuan) satuan_kirim FROM detail_pengiriman d JOIN barang b ON b.id=d.barang_id WHERE d.pengiriman_id=? ORDER BY d.id");
        $query->execute([(int) $shipment['id']]);
        $items = $query->fetchAll();
        if (! $items) {
            warehouse_status(404);
            warehouse_abort('Rincian surat jalan tidak ditemukan.');
        }
        $documentNumber = 'SJ-'.str_pad((string) $shipment['id'], 8, '0', STR_PAD_LEFT);
        $statusLabels = ['dalam_perjalanan' => 'Dalam perjalanan', 'diterima' => 'Diterima', 'diterima_dengan_selisih' => 'Diterima dengan selisih'];

        return view('warehouse.surat_jalan', get_defined_vars());
    }
}
