<?php
namespace App\Services;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use DateTimeImmutable;
use finfo;
use Illuminate\Support\Facades\RateLimiter;
class WarehousePages {
public function bukti_persetujuan() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];

require_role('purchasing','manager','admin_pusat','admin_gudang');

$currentUser = user();
$rawId = $queryInput['id'] ?? null;
if (!is_string($rawId) || !ctype_digit($rawId)) {
    warehouse_status(404);
    warehouse_abort('Dokumen tidak ditemukan.');
}
$normalizedId = ltrim($rawId, '0');
$maxId = (string)PHP_INT_MAX;
if ($normalizedId === '' || strlen($normalizedId) > strlen($maxId) || (strlen($normalizedId) === strlen($maxId) && strcmp($normalizedId, $maxId) > 0)) {
    warehouse_status(404);
    warehouse_abort('Dokumen tidak ditemukan.');
}
$requestId = (int)$normalizedId;

$pdo = db();
$query = $pdo->prepare('SELECT r.id,r.user_id,r.jumlah,r.catatan,r.status,r.tanggal,r.diproses_pada,b.kode,b.nama barang,b.satuan,requester.nama pengaju,processor.nama purchasing FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id JOIN users requester ON requester.id=r.user_id LEFT JOIN users processor ON processor.id=r.diproses_oleh WHERE r.id=? LIMIT 1');
$query->execute([$requestId]);
$request = $query->fetch();

$canViewAllRequests = in_array($currentUser['role'], ['purchasing','manager'], true);
$isApplicant = $request && (int)$request['user_id'] === (int)$currentUser['id'];
if (!$request || (!$canViewAllRequests && !$isApplicant)) {
    warehouse_status(404);
    warehouse_abort('Dokumen tidak ditemukan.');
}
if ($request['status'] !== 'disetujui') {
    warehouse_status(404);
    warehouse_abort('Dokumen tidak ditemukan.');
}

$documentNumber = 'BAPP-'.str_pad((string)$request['id'], 8, '0', STR_PAD_LEFT);
$submittedDate = date('d M Y H:i', strtotime($request['tanggal']));
$approvedDate = $request['diproses_pada'] ? date('d M Y H:i', strtotime($request['diproses_pada'])) : 'Tidak tercatat';
$backUrl = $currentUser['role'] === 'purchasing' ? 'purchasing.php#permintaan' : 'dashboard.php#permintaan-pembelian';

return view('warehouse.bukti_persetujuan', get_defined_vars());
}
public function dashboard() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];

require_role('admin_pusat','admin_gudang','manager');
$pdo = db();
$role = user()['role'];
$notificationService = app(WarehouseNotifications::class);
if ($role === 'manager') $notificationService->weekly($pdo);
$notificationInbox = $notificationService->inbox($pdo, (int)user()['id']);
$reportTypes = ['semua'=>'Semua laporan','stok'=>'Stok per gudang','permintaan'=>'Permintaan barang','pengiriman'=>'Pengiriman dan penerimaan','pembelian'=>'Pengajuan pembelian','penerimaan_pembelian'=>'Penerimaan pembelian','penambahan'=>'Penambahan barang proyek','retur'=>'Retur peralatan','mutasi'=>'Mutasi dan pemakaian barang'];
$centerId = (int)$pdo->query("SELECT id FROM gudang WHERE kode='PUSAT' AND jenis='pusat' AND aktif=1 LIMIT 1")->fetchColumn();
if ($centerId < 1) { warehouse_status(503); warehouse_abort('Gudang Pusat belum disiapkan.'); }
$warehouseId = $role === 'manager' ? null : ($role === 'admin_pusat' ? $centerId : (int)user()['gudang_id']);
$warehouseName = 'Semua gudang';
$managerWarehouses = [];
$selectedManagerWarehouseId = 0;
if ($role === 'manager') {
    $warehouseName = 'Seluruh gudang aktif';
    $managerWarehouses = $pdo->query("SELECT id,nama FROM gudang WHERE aktif=1 ORDER BY nama")->fetchAll();
    $requestedWarehouseId = filter_var($queryInput['gudang_id'] ?? null,FILTER_VALIDATE_INT);
    if ($requestedWarehouseId !== false && $requestedWarehouseId !== null && $requestedWarehouseId > 0) {
        foreach ($managerWarehouses as $managerWarehouse) {
            if ((int)$managerWarehouse['id'] === $requestedWarehouseId) {
                $selectedManagerWarehouseId = $requestedWarehouseId;
                break;
            }
        }
    }
} else {
    $q = $pdo->prepare('SELECT nama FROM gudang WHERE id=?');
    $q->execute([$warehouseId]);
    $warehouseName = (string)$q->fetchColumn();
}
$today = date('Y-m-d');
$defaultStart = date('Y-m-01');
$validDate = static function(mixed $value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) return false;
    [$year,$month,$day] = array_map('intval',explode('-',$value));
    return checkdate($month,$day,$year);
};
$start = $queryInput['tanggal_mulai'] ?? $defaultStart;
$end = $queryInput['tanggal_akhir'] ?? $today;
if (!$validDate($start)) $start = $defaultStart;
if (!$validDate($end)) $end = $today;
if ($start > $end) [$start,$end] = [$end,$start];
$endExclusive = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');

if ($role === 'manager') {
    $q = $pdo->query("SELECT COUNT(DISTINCT b.id) jenis,COALESCE(SUM(s.stok),0) unit,COUNT(CASE WHEN COALESCE(s.stok,0)<=b.stok_minimum THEN 1 END) rendah FROM barang b CROSS JOIN gudang g LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id=g.id WHERE g.aktif=1 AND b.aktif=1");
    $inventory = $q->fetch();
    $stockRows = [];
    if ($selectedManagerWarehouseId > 0) {
        $stockQuery = $pdo->prepare("SELECT g.id gudang_id,g.nama gudang,b.id barang_id,b.kode,b.nama,b.kategori_barang,b.satuan,b.stok_minimum,COALESCE(s.stok,0) stok FROM gudang g CROSS JOIN barang b LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE g.id=? AND g.aktif=1 AND b.aktif=1 ORDER BY b.kode");
        $stockQuery->execute([$selectedManagerWarehouseId]);
        $stockRows = $stockQuery->fetchAll();
    }
} else {
    $q = $pdo->prepare('SELECT COUNT(*) jenis,COALESCE(SUM(COALESCE(s.stok,0)),0) unit,COUNT(CASE WHEN COALESCE(s.stok,0)<=b.stok_minimum THEN 1 END) rendah FROM barang b LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id=? WHERE b.aktif=1');
    $q->execute([$warehouseId]); $inventory = $q->fetch();
    $q = $pdo->prepare('SELECT ? gudang_id,? gudang,b.id barang_id,b.kode,b.nama,b.kategori_barang,b.satuan,b.stok_minimum,COALESCE(s.stok,0) stok FROM barang b LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id=? WHERE b.aktif=1 ORDER BY b.kode');
    $q->execute([$warehouseId,$warehouseName,$warehouseId]); $stockRows = $q->fetchAll();
}
$scopeSql = '';
$scopeParams = [];
if ($role === 'manager') {
    $scopeSql = ' AND EXISTS (SELECT 1 FROM gudang g WHERE g.id=t.gudang_id AND g.aktif=1)';
} else { $scopeSql = ' AND t.gudang_id=?'; $scopeParams[] = $warehouseId; }
$movementSql = 'SELECT t.jenis,COALESCE(SUM(t.jumlah),0) jumlah FROM transaksi_gudang t WHERE t.tanggal>=? AND t.tanggal<?'.$scopeSql.' GROUP BY t.jenis';
$movementQuery = $pdo->prepare($movementSql);
$movementQuery->execute(array_merge([$start,$endExclusive],$scopeParams));
$movementTotals = ['masuk'=>0,'keluar'=>0,'kembali'=>0,'pemakaian'=>0];
foreach ($movementQuery->fetchAll() as $movement) $movementTotals[$movement['jenis']] = (int)$movement['jumlah'];

$requestConditions = ['p.gudang_tujuan_id IS NOT NULL','((p.tanggal>=? AND p.tanggal<?) OR p.status=\'diajukan\')'];
$requestParams = [$start,$endExclusive];
if ($role === 'admin_pusat') { $requestConditions[] = 'p.gudang_asal_id=?'; $requestParams[] = $centerId; }
if ($role === 'admin_gudang') { $requestConditions[] = 'p.gudang_tujuan_id=?'; $requestParams[] = $warehouseId; }
if ($role === 'manager') {
    $requestConditions[] = "EXISTS (SELECT 1 FROM gudang g WHERE g.id=p.gudang_tujuan_id AND g.aktif=1)";
}
$requestSql = "SELECT p.id,p.tanggal,p.status,p.catatan,p.nama_proyek,ga.nama gudang_asal,gt.nama gudang_tujuan,u.nama peminta,GROUP_CONCAT(CONCAT(b.kode,' · ',b.nama,' × ',dp.jumlah,' ',b.satuan) ORDER BY b.kode SEPARATOR ', ') barang,MAX(pe.id) pengiriman_id,MAX(pe.status) status_pengiriman FROM permintaan p JOIN users u ON u.id=p.user_id LEFT JOIN gudang ga ON ga.id=p.gudang_asal_id LEFT JOIN gudang gt ON gt.id=p.gudang_tujuan_id LEFT JOIN detail_permintaan dp ON dp.permintaan_id=p.id LEFT JOIN barang b ON b.id=dp.barang_id LEFT JOIN pengiriman pe ON pe.permintaan_id=p.id WHERE ";
$requestSql .= implode(' AND ',$requestConditions)." GROUP BY p.id ORDER BY FIELD(p.status,'diajukan','diproses','selesai','ditolak'),p.tanggal DESC";
if ($role !== 'manager') $requestSql .= ' LIMIT 100';
$requestQuery = $pdo->prepare($requestSql);
$requestQuery->execute($requestParams);
$requestRows = $requestQuery->fetchAll();
$pendingSql = "SELECT COUNT(*) FROM permintaan p WHERE p.gudang_tujuan_id IS NOT NULL AND p.status='diajukan'";
$pendingParams = [];
if ($role === 'admin_pusat') { $pendingSql .= ' AND p.gudang_asal_id=?'; $pendingParams[] = $centerId; }
if ($role === 'admin_gudang') { $pendingSql .= ' AND p.gudang_tujuan_id=?'; $pendingParams[] = $warehouseId; }
if ($role === 'manager') {
    $pendingSql .= " AND EXISTS (SELECT 1 FROM gudang g WHERE g.id=p.gudang_tujuan_id AND g.aktif=1)";
}
$pendingQuery = $pdo->prepare($pendingSql); $pendingQuery->execute($pendingParams); $pending = (int)$pendingQuery->fetchColumn();
$purchasingRequests = [];
$pendingPurchasingRequests = 0;
if ($role === 'manager') {
    $purchasingRequests = $pdo->query('SELECT r.id,r.user_id,r.jumlah,r.catatan,r.status,r.tanggal,r.diproses_pada,b.kode,b.nama barang,b.satuan,requester.nama peminta,processor.nama pemroses,COALESCE(received.jumlah_diterima,0) jumlah_diterima,COALESCE(received.jumlah_layak,0) jumlah_layak FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id JOIN users requester ON requester.id=r.user_id LEFT JOIN users processor ON processor.id=r.diproses_oleh LEFT JOIN (SELECT permintaan_pembelian_id,SUM(jumlah_diterima) jumlah_diterima,SUM(jumlah_layak) jumlah_layak FROM penerimaan_pembelian GROUP BY permintaan_pembelian_id) received ON received.permintaan_pembelian_id=r.id ORDER BY FIELD(r.status,\'menunggu\',\'disetujui\',\'ditolak\'),r.tanggal DESC,r.id DESC')->fetchAll();
    $pendingPurchasingRequests = (int)$pdo->query("SELECT COUNT(*) FROM permintaan_pembelian WHERE status='menunggu'")->fetchColumn();
} else {
    $purchaseRequestQuery = $pdo->prepare('SELECT r.id,r.jumlah,r.catatan,r.status,r.tanggal,r.diproses_pada,b.kode,b.nama barang,b.satuan,processor.nama pemroses,COALESCE(received.jumlah_diterima,0) jumlah_diterima,COALESCE(received.jumlah_layak,0) jumlah_layak FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id LEFT JOIN users processor ON processor.id=r.diproses_oleh LEFT JOIN (SELECT permintaan_pembelian_id,SUM(jumlah_diterima) jumlah_diterima,SUM(jumlah_layak) jumlah_layak FROM penerimaan_pembelian GROUP BY permintaan_pembelian_id) received ON received.permintaan_pembelian_id=r.id WHERE r.user_id=? ORDER BY r.tanggal DESC,r.id DESC');
    $purchaseRequestQuery->execute([(int)user()['id']]);
    $purchasingRequests = $purchaseRequestQuery->fetchAll();
    $purchasePendingQuery = $pdo->prepare("SELECT COUNT(*) FROM permintaan_pembelian WHERE user_id=? AND status='menunggu'");
    $purchasePendingQuery->execute([(int)user()['id']]);
    $pendingPurchasingRequests = (int)$purchasePendingQuery->fetchColumn();
}
$receiptSummary = static function(array $request): string {
    $ordered = (int)$request['jumlah'];
    $received = (int)($request['jumlah_diterima'] ?? 0);
    $ready = (int)($request['jumlah_layak'] ?? 0);
    $remaining = max(0,$ordered-$received);
    $status = $remaining === 0 ? 'Semua jumlah tercatat' : ($received > 0 ? 'Diterima sebagian' : 'Belum diterima');
    return $status.' · diperiksa '.number_format($received).'/'.number_format($ordered).'; layak stok '.number_format($ready).'; sisa '.number_format($remaining);
};

$legacyRequests = [];
$shipmentConditions = [];
$shipmentParams = [];
if ($role === 'manager') { $shipmentConditions[] = 'pe.dikirim_pada>=?'; $shipmentConditions[] = 'pe.dikirim_pada<?'; $shipmentParams = [$start,$endExclusive]; }
if ($role === 'admin_pusat') { $shipmentConditions[] = 'pe.gudang_asal_id=?'; $shipmentParams[] = $centerId; }
if ($role === 'admin_gudang') { $shipmentConditions[] = 'pe.gudang_tujuan_id=?'; $shipmentParams[] = $warehouseId; }
if ($role === 'manager') {
    $shipmentConditions[] = "EXISTS (SELECT 1 FROM gudang g WHERE g.id=pe.gudang_asal_id AND g.aktif=1)";
    $shipmentConditions[] = "EXISTS (SELECT 1 FROM gudang g WHERE g.id=pe.gudang_tujuan_id AND g.aktif=1)";
}
$shipmentQuery = $pdo->prepare('SELECT pe.id,pe.permintaan_id,pe.gudang_asal_id,pe.gudang_tujuan_id,pe.status,pe.dikirim_pada,pe.diterima_pada,pe.catatan_penerimaan,ga.nama gudang_asal,gt.nama gudang_tujuan,us.nama pengirim,ur.nama penerima FROM pengiriman pe JOIN gudang ga ON ga.id=pe.gudang_asal_id JOIN gudang gt ON gt.id=pe.gudang_tujuan_id JOIN users us ON us.id=pe.dikirim_oleh LEFT JOIN users ur ON ur.id=pe.diterima_oleh WHERE '.implode(' AND ',$shipmentConditions).' ORDER BY pe.dikirim_pada DESC');
$shipmentQuery->execute($shipmentParams); $shipments = $shipmentQuery->fetchAll();

$movementScope = $role === 'manager'
    ? " AND EXISTS (SELECT 1 FROM gudang g WHERE g.id=t.gudang_id AND g.aktif=1)"
    : ' AND t.gudang_id=?';
$movementQuery = $pdo->prepare('SELECT t.id,t.tanggal,t.jenis,t.jumlah,t.referensi,t.nomor_transaksi,t.nama_supplier,t.tujuan_keluar,t.satuan_transaksi,t.gudang_id,t.gudang_asal_id,t.gudang_tujuan_id,b.kode,b.nama barang,COALESCE(t.satuan_transaksi,b.satuan) satuan,g.nama gudang,ga.nama gudang_asal,gt.nama gudang_tujuan,u.nama petugas FROM transaksi_gudang t JOIN barang b ON b.id=t.barang_id JOIN gudang g ON g.id=t.gudang_id LEFT JOIN gudang ga ON ga.id=t.gudang_asal_id LEFT JOIN gudang gt ON gt.id=t.gudang_tujuan_id LEFT JOIN users u ON u.id=t.user_id WHERE t.tanggal>=? AND t.tanggal<?'.$movementScope.' ORDER BY t.tanggal DESC,t.id DESC');
$movementQuery->execute(array_merge([$start,$endExclusive],$role==='manager'?[]:[$warehouseId]));
$movementRows = $movementQuery->fetchAll();

$additionConditions = ['pa.ditambahkan_pada>=?','pa.ditambahkan_pada<?'];
$additionParams = [$start,$endExclusive];
if ($role === 'admin_gudang') { $additionConditions[] = 'pa.gudang_id=?'; $additionParams[] = $warehouseId; }
$additionConditions[] = "EXISTS (SELECT 1 FROM gudang ag WHERE ag.id=pa.gudang_id AND ag.jenis='proyek')";
$additionQuery = $pdo->prepare('SELECT pa.id,pa.jumlah,pa.catatan,pa.ditambahkan_pada,pa.kode_barang kode,pa.nama_barang barang,pa.satuan_barang satuan,pa.nama_proyek gudang,pa.nama_penambah petugas FROM penambahan_barang_proyek pa WHERE '.implode(' AND ',$additionConditions).' ORDER BY pa.ditambahkan_pada DESC,pa.id DESC');
$additionQuery->execute($additionParams); $projectAdditions = $additionQuery->fetchAll();

$returnConditions = ["(rp.status='menunggu_diterima' OR (rp.dikirim_pada>=? AND rp.dikirim_pada<?))"];
$returnParams = [$start,$endExclusive];
if ($role === 'admin_gudang') { $returnConditions[] = 'rp.gudang_asal_id=?'; $returnParams[] = $warehouseId; }
$returnConditions[] = "EXISTS (SELECT 1 FROM gudang rg WHERE rg.id=rp.gudang_asal_id AND rg.jenis='proyek')";
$returnQuery = $pdo->prepare('SELECT rp.id,rp.barang_id,rp.gudang_asal_id,rp.jumlah,rp.catatan_kondisi,rp.status,rp.dikirim_pada,rp.diterima_pada,rp.kode_barang kode,rp.nama_barang barang,rp.satuan_barang satuan,rp.nama_proyek gudang,rp.nama_admin_proyek pengirim,ur.nama penerima FROM pengembalian_peralatan_proyek rp LEFT JOIN users ur ON ur.id=rp.diterima_oleh WHERE '.implode(' AND ',$returnConditions).' ORDER BY rp.dikirim_pada DESC,rp.id DESC');
$returnQuery->execute($returnParams); $equipmentReturns = $returnQuery->fetchAll();

$goods = [];
if ($role !== 'manager') {
    $goodsQuery = $pdo->prepare('SELECT b.id,b.kode,b.nama,b.kategori,b.kategori_barang,b.satuan,COALESCE(s.stok,0) stok FROM barang b LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id=? WHERE b.aktif=1 ORDER BY b.nama');
    $goodsQuery->execute([$warehouseId]); $goods = $goodsQuery->fetchAll();
}
$equipmentGoods = array_values(array_filter($goods,static fn(array $item): bool => $item['kategori_barang']==='peralatan' && (int)$item['stok']>0));

$catalog = [];
$editing = null;
if ($role === 'admin_pusat') {
    $q = $pdo->query('SELECT b.id,b.kode,b.nama,b.kategori,b.kategori_barang,b.satuan,b.aktif,COALESCE(s.stok,0) stok,r.catatan catatan_penghapusan,r.dibuat_pada dihapus_pada FROM barang b LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id='.$centerId.' LEFT JOIN (SELECT rh.* FROM riwayat_hapus_barang rh JOIN (SELECT barang_id,MAX(id) id FROM riwayat_hapus_barang GROUP BY barang_id) latest ON latest.id=rh.id) r ON r.barang_id=b.id ORDER BY b.kode ASC');
    $catalog = $q->fetchAll();
    if (!empty($queryInput['edit_barang']) && is_scalar($queryInput['edit_barang'])) {
        $q = $pdo->prepare('SELECT b.id,b.kode,b.nama,b.kategori,b.kategori_barang,b.satuan,COALESCE(s.stok,0) stok FROM barang b LEFT JOIN stok_gudang s ON s.barang_id=b.id AND s.gudang_id=? WHERE b.id=?');
        $q->execute([$centerId,(int)$queryInput['edit_barang']]); $editing = $q->fetch() ?: null;
    }
}
$flashMessage = take_flash();
$categoryLabels = ['barang_siap'=>'Material Project','barang_dipakai'=>'Barang Material Habis / Consumable','peralatan'=>'Peralatan'];
$statusLabels = ['diajukan'=>'Menunggu diproses','disetujui'=>'Disetujui','diproses'=>'Dalam perjalanan','selesai'=>'Selesai diterima','ditolak'=>'Ditolak'];
$purchasingStatusLabels = ['menunggu'=>'Menunggu diproses Purchasing','disetujui'=>'Disetujui','ditolak'=>'Ditolak'];
$purchasingStatusClasses = ['menunggu'=>'text-bg-warning','disetujui'=>'text-bg-success','ditolak'=>'text-bg-danger'];
$shipmentLabels = ['dalam_perjalanan'=>'Dalam perjalanan','diterima'=>'Diterima','diterima_dengan_selisih'=>'Diterima dengan selisih'];
$movementLabels = ['masuk'=>'Barang masuk','keluar'=>'Dikirim keluar','kembali'=>'Barang kembali','pemakaian'=>'Pemakaian proyek'];
$movementDisplayLabel = static function(array $row) use ($movementLabels): string {
    if (($row['referensi'] ?? '') === 'Penyesuaian stok melalui katalog') return $row['jenis'] === 'masuk' ? 'Penyesuaian stok (+)' : 'Penyesuaian stok (-)';
    if (($row['jenis'] ?? '') === 'kembali' && str_starts_with((string)($row['referensi'] ?? ''),'Peralatan kembali dari proyek #')) return 'Pengembalian peralatan proyek';
    return $movementLabels[$row['jenis']] ?? $row['jenis'];
};
$roleLabel = ['admin_pusat'=>'Admin Pusat','admin_gudang'=>'Admin Gudang Proyek','manager'=>'Manajer'][$role];

return view('warehouse.dashboard', get_defined_vars());
}
public function detail_penerimaan_pembelian() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];

require_role('purchasing','manager');

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
if (!$request) {
    warehouse_status(404);
    warehouse_abort('Permintaan pembelian tidak ditemukan.');
}

$historyQuery = $pdo->prepare('SELECT id,kode_barang,nama_barang,satuan_barang,nama_gudang,nama_petugas,jumlah_diterima,jumlah_layak,jumlah_rusak,jumlah_tidak_sesuai,catatan_kondisi,diterima_pada FROM penerimaan_pembelian WHERE permintaan_pembelian_id=? ORDER BY diterima_pada DESC,id DESC');
$historyQuery->execute([$requestId]);
$receipts = $historyQuery->fetchAll();
$photoQuery = $pdo->prepare('SELECT f.id foto_id,f.penerimaan_pembelian_id FROM penerimaan_pembelian_foto f JOIN penerimaan_pembelian p ON p.id=f.penerimaan_pembelian_id WHERE p.permintaan_pembelian_id=? ORDER BY p.diterima_pada DESC,p.id DESC,f.urutan');
$photoQuery->execute([$requestId]);
$photosByReceipt = [];
foreach ($photoQuery->fetchAll() as $photo) $photosByReceipt[(int)$photo['penerimaan_pembelian_id']][] = (int)$photo['foto_id'];

$ordered = (int)$request['jumlah'];
$received = (int)$request['jumlah_diterima'];
$ready = (int)$request['jumlah_layak'];
$damaged = (int)$request['jumlah_rusak'];
$mismatched = (int)$request['jumlah_tidak_sesuai'];
$remaining = max(0, $ordered - $received);
$statusLabels = ['menunggu' => 'Menunggu diproses', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
$statusClasses = ['menunggu' => 'text-bg-warning', 'disetujui' => 'text-bg-success', 'ditolak' => 'text-bg-danger'];
$flashMessage = take_flash();

return view('warehouse.detail_penerimaan_pembelian', get_defined_vars());
}
public function foto_penerimaan_pembelian() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];
ob_start();
try {
require_role('purchasing','admin_pusat','admin_gudang','manager');

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
if (!$photo || $photo['status'] !== 'disetujui') {
    warehouse_status(404);
    warehouse_abort('Foto bukti tidak ditemukan.');
}

$currentUser = user();
$allowed = in_array($currentUser['role'], ['purchasing','manager'], true);
if ($currentUser['role'] === 'admin_pusat') {
    $centerId = (int)$pdo->query("SELECT id FROM gudang WHERE kode='PUSAT' AND jenis='pusat' AND aktif=1 LIMIT 1")->fetchColumn();
    $allowed = $centerId > 0 && (int)$photo['gudang_id'] === $centerId && $photo['role_pengaju'] === 'admin_pusat' && $photo['gudang_pengaju_id'] === null;
} elseif ($currentUser['role'] === 'admin_gudang') {
    $warehouseId = (int)$currentUser['gudang_id'];
    $allowed = (int)$photo['gudang_id'] === $warehouseId && $photo['role_pengaju'] === 'admin_gudang' && (int)$photo['gudang_pengaju_id'] === $warehouseId;
}
if (!$allowed) {
    warehouse_status(404);
    warehouse_abort('Foto bukti tidak ditemukan.');
}

$mimeExtensions = ['image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp'];
if (!isset($mimeExtensions[$photo['mime_type']]) ||
    !is_string($photo['lokasi_file']) ||
    !preg_match('/^[a-f0-9]{64}\.(?:jpg|png|webp)$/D',$photo['lokasi_file']) ||
    !str_ends_with($photo['lokasi_file'],'.'.$mimeExtensions[$photo['mime_type']])) {
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
$normalizePath = static fn(string $path): string => strtolower(rtrim(str_replace('\\','/',$path),'/'));
if ($filePath === false || $resolvedStorageDirectory === false || !is_file($filePath) || !is_readable($filePath) ||
    $normalizePath(dirname($filePath)) !== $normalizePath($resolvedStorageDirectory)) {
    warehouse_status(404);
    warehouse_abort('Foto bukti tidak ditemukan.');
}
$actualSize = filesize($filePath);
if ($actualSize === false || $actualSize !== (int)$photo['ukuran_bytes']) {
    warehouse_status(404);
    warehouse_abort('Foto bukti tidak ditemukan.');
}

warehouse_header('Content-Type: '.$photo['mime_type']);
warehouse_header('Content-Length: '.(string)$actualSize);
warehouse_header('Content-Disposition: inline; filename="foto-penerimaan-'.$photoId.'.'.$mimeExtensions[$photo['mime_type']].'"');
warehouse_header('X-Content-Type-Options: nosniff');
warehouse_header("Content-Security-Policy: default-src 'none'; sandbox");
warehouse_header('X-Frame-Options: DENY');
warehouse_header('Referrer-Policy: no-referrer');
warehouse_header('Cross-Origin-Resource-Policy: same-origin');
warehouse_header('Cache-Control: private, no-store, max-age=0');
readfile($filePath);
warehouse_finish();

$body=ob_get_clean(); return warehouse_response($body);
} catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { $body=ob_get_clean(); if($e->getResponse()->getContent()==='') $e->getResponse()->setContent($body); throw $e; } catch (Throwable $e) { ob_end_clean(); throw $e; }
}

public function index() { return view('warehouse.index'); }
public function laporan_pdf() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];
ob_start();
try {
require_role('manager');
$pdo = db();
$reportTypes = ['semua'=>'Semua laporan','stok'=>'Stok per gudang','permintaan'=>'Permintaan barang','pengiriman'=>'Pengiriman dan penerimaan','pembelian'=>'Pengajuan pembelian','penerimaan_pembelian'=>'Penerimaan pembelian','penambahan'=>'Penambahan barang proyek','retur'=>'Retur peralatan','mutasi'=>'Mutasi dan pemakaian barang'];
$reportType = $queryInput['jenis_laporan'] ?? 'semua';
abort_unless(is_string($reportType) && isset($reportTypes[$reportType]), 422);
$reportTitle = $reportTypes[$reportType];
$today = date('Y-m-d');
$defaultStart = date('Y-m-01');
$validDate = function(mixed $value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) return false;
    [$year,$month,$day] = array_map('intval',explode('-',$value));
    return checkdate($month,$day,$year);
};
$start = $queryInput['tanggal_mulai'] ?? $defaultStart;
$end = $queryInput['tanggal_akhir'] ?? $today;
if (!$validDate($start)) $start = $defaultStart;
if (!$validDate($end)) $end = $today;
if ($start > $end) [$start,$end] = [$end,$start];
$endExclusive = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');

$inventoryQuery = $pdo->query("SELECT COUNT(DISTINCT b.id) jenis,COALESCE(SUM(s.stok),0) unit,COUNT(CASE WHEN COALESCE(s.stok,0)<=b.stok_minimum THEN 1 END) rendah FROM barang b CROSS JOIN gudang g LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE b.aktif=1 AND g.aktif=1");
$inventory = $inventoryQuery->fetch();
$movementQuery = $pdo->prepare("SELECT t.jenis,COALESCE(SUM(t.jumlah),0) jumlah FROM transaksi_gudang t JOIN gudang g ON g.id=t.gudang_id WHERE t.tanggal>=? AND t.tanggal<? AND g.aktif=1 GROUP BY t.jenis");
$movementQuery->execute([$start,$endExclusive]);
$movementTotals = ['masuk'=>0,'keluar'=>0,'kembali'=>0,'pemakaian'=>0];
foreach ($movementQuery->fetchAll() as $row) $movementTotals[$row['jenis']] = (int)$row['jumlah'];
$pendingQuery = $pdo->query("SELECT COUNT(*) FROM permintaan p JOIN gudang g ON g.id=p.gudang_tujuan_id WHERE p.gudang_tujuan_id IS NOT NULL AND p.status='diajukan' AND g.aktif=1");
$pending = (int)$pendingQuery->fetchColumn();
$transitQuery = $pdo->query("SELECT COUNT(*) FROM pengiriman pe JOIN gudang ga ON ga.id=pe.gudang_asal_id JOIN gudang gt ON gt.id=pe.gudang_tujuan_id WHERE pe.status='dalam_perjalanan' AND ga.aktif=1 AND gt.aktif=1");
$transitCount = (int)$transitQuery->fetchColumn();

$stockQuery = $pdo->query("SELECT g.id gudang_id,g.nama gudang,b.kode,b.nama,b.satuan,b.stok_minimum,COALESCE(s.stok,0) stok FROM gudang g CROSS JOIN barang b LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE g.aktif=1 AND b.aktif=1 ORDER BY g.nama,b.kode");
$stockRows = $stockQuery->fetchAll();

$requestQuery = $pdo->prepare("SELECT p.id,p.tanggal,p.status,p.nama_proyek,gt.nama gudang,u.nama peminta,GROUP_CONCAT(CONCAT(b.kode,' · ',b.nama,' × ',dp.jumlah,' ',b.satuan) ORDER BY b.kode SEPARATOR ', ') barang FROM permintaan p JOIN users u ON u.id=p.user_id LEFT JOIN gudang gt ON gt.id=p.gudang_tujuan_id LEFT JOIN detail_permintaan dp ON dp.permintaan_id=p.id LEFT JOIN barang b ON b.id=dp.barang_id WHERE p.gudang_tujuan_id IS NOT NULL AND p.tanggal>=? AND p.tanggal<? AND gt.aktif=1 GROUP BY p.id ORDER BY p.tanggal DESC,p.id DESC");
$requestQuery->execute([$start,$endExclusive]);
$requestRows = $requestQuery->fetchAll();

$shipmentQuery = $pdo->prepare("SELECT pe.id,pe.status,pe.dikirim_pada,ga.nama gudang_asal,gt.nama gudang_tujuan,us.nama pengirim,ur.nama penerima,GROUP_CONCAT(CONCAT(b.kode,' · ',b.nama,': ',d.jumlah_dikirim,' dikirim / ',COALESCE(d.jumlah_diterima,'belum dikonfirmasi'),' diterima ',b.satuan) ORDER BY b.kode SEPARATOR '; ') rincian FROM pengiriman pe JOIN gudang ga ON ga.id=pe.gudang_asal_id JOIN gudang gt ON gt.id=pe.gudang_tujuan_id JOIN users us ON us.id=pe.dikirim_oleh LEFT JOIN users ur ON ur.id=pe.diterima_oleh JOIN detail_pengiriman d ON d.pengiriman_id=pe.id JOIN barang b ON b.id=d.barang_id WHERE pe.dikirim_pada>=? AND pe.dikirim_pada<? AND ga.aktif=1 AND gt.aktif=1 GROUP BY pe.id ORDER BY pe.dikirim_pada DESC,pe.id DESC");
$shipmentQuery->execute([$start,$endExclusive]);
$shipmentRows = $shipmentQuery->fetchAll();

$movementQuery = $pdo->prepare("SELECT t.tanggal,t.jenis,t.jumlah,t.referensi,t.nomor_transaksi,t.satuan_transaksi,t.nama_supplier,t.nama_petugas,b.kode,b.nama barang,COALESCE(t.satuan_transaksi,b.satuan) satuan,g.nama gudang,ga.nama gudang_asal,gt.nama gudang_tujuan,u.nama operator FROM transaksi_gudang t JOIN barang b ON b.id=t.barang_id JOIN gudang g ON g.id=t.gudang_id LEFT JOIN gudang ga ON ga.id=t.gudang_asal_id LEFT JOIN gudang gt ON gt.id=t.gudang_tujuan_id LEFT JOIN users u ON u.id=t.user_id WHERE t.tanggal>=? AND t.tanggal<? AND g.aktif=1 ORDER BY t.tanggal DESC,t.id DESC");
$movementQuery->execute([$start,$endExclusive]);
$movementRows = $movementQuery->fetchAll();

$additionQuery = $pdo->prepare("SELECT pa.jumlah,pa.catatan,pa.ditambahkan_pada,pa.kode_barang kode,pa.nama_barang barang,pa.satuan_barang satuan,pa.nama_proyek gudang,pa.nama_penambah petugas FROM penambahan_barang_proyek pa JOIN gudang g ON g.id=pa.gudang_id WHERE g.jenis='proyek' AND g.aktif=1 AND pa.ditambahkan_pada>=? AND pa.ditambahkan_pada<? ORDER BY pa.ditambahkan_pada DESC,pa.id DESC");
$additionQuery->execute([$start,$endExclusive]); $additionRows = $additionQuery->fetchAll();
$returnQuery = $pdo->prepare("SELECT rp.id,rp.jumlah,rp.catatan_kondisi,rp.status,rp.dikirim_pada,rp.diterima_pada,rp.kode_barang kode,rp.nama_barang barang,rp.satuan_barang satuan,rp.nama_proyek gudang,rp.nama_admin_proyek pengirim,ur.nama penerima FROM pengembalian_peralatan_proyek rp JOIN gudang g ON g.id=rp.gudang_asal_id LEFT JOIN users ur ON ur.id=rp.diterima_oleh WHERE g.jenis='proyek' AND g.aktif=1 AND (rp.status='menunggu_diterima' OR (rp.dikirim_pada>=? AND rp.dikirim_pada<?)) ORDER BY rp.dikirim_pada DESC,rp.id DESC");
$returnQuery->execute([$start,$endExclusive]); $returnRows = $returnQuery->fetchAll();











$navy=[0.071,0.227,0.408];
$gold=[0.969,0.678,0.125];
$white=[1.000,1.000,1.000];
$light=[0.955,0.973,0.992];
$grid=[0.842,0.882,0.929];
$textColor=[0.090,0.157,0.243];
$muted=[0.420,0.478,0.553];
$headerMuted=[0.745,0.808,0.886];
$pages=[];
$commands=[];
$cursor=0.0;
$beginPage = function(string $title,bool $summary=false) use (&$pages,&$commands,&$cursor,$navy,$gold,$white,$light,$grid,$muted,$headerMuted,$inventory,$movementTotals,$pending,$transitCount,$start,$end): void {
    if ($commands) $pages[] = $commands;
    $commands = [];
    $this->reportPdfRect($commands,0,494,841.89,101.28,$navy);
    $this->reportPdfRect($commands,0,490,841.89,4,$gold);
    $commands[] = "q 58 0 0 58 36 514 cm /Logo Do Q\n";
    $this->reportPdfText($commands,108,557,'TRIKASA ENERGI TAMA','F2',15,$white);
    $this->reportPdfText($commands,109,538,'SISTEM INFORMASI PERGUDANGAN','F1',8,[0.835,0.890,0.957]);
    $this->reportPdfText($commands,558,557,'LAPORAN LINTAS GUDANG','F2',13,$white);
    $this->reportPdfText($commands,558,538,'Periode: '.date('d M Y',strtotime($start)).' - '.date('d M Y',strtotime($end)),'F1',8,$white);
    $this->reportPdfText($commands,558,520,'Dibuat: '.date('d M Y H:i'),'F1',7,$headerMuted);
    if ($summary) {
        $metrics = [
            ['Jenis barang aktif',number_format((int)$inventory['jenis'])],
            ['Stok tersedia',number_format((int)$inventory['unit']).' unit'],
            ['Stok di bawah minimum',number_format((int)$inventory['rendah'])],
            ['Dalam perjalanan',$transitCount.' pengiriman'],
        ];
        foreach ($metrics as $index=>$metric) {
            $x = 36+($index*193);
            $this->reportPdfRect($commands,$x,414,180,54,$light,$grid,0.55);
            $this->reportPdfRect($commands,$x,465,180,3,$gold);
            $this->reportPdfText($commands,$x+9,448,$metric[0],'F1',7,$muted);
            $this->reportPdfText($commands,$x+9,427,$metric[1],'F2',12,$navy);
        }
        $this->reportPdfText($commands,36,393,'Masuk '.$movementTotals['masuk'].' | Keluar '.$movementTotals['keluar'].' | Pemakaian proyek '.$movementTotals['pemakaian'].' | Permintaan menunggu '.$pending,'F1',8,$muted);
        $this->reportPdfText($commands,36,373,$title,'F2',11,$navy);
        $cursor = 356;
    } else {
        $this->reportPdfText($commands,36,464,$title,'F2',11,$navy);
        $cursor = 443;
    }
};
$drawSection = function(string $title,array $columns,array $rows) use (&$commands,&$pages,&$cursor,$beginPage,$navy,$white,$light,$grid,$textColor): void {
    if (!$commands || $cursor < 95) $beginPage($title,false);
    else {
        $this->reportPdfText($commands,36,$cursor-17,$title,'F2',11,$navy);
        $cursor -= 27;
    }
    $cursor = $this->reportTableHeader($commands,$cursor,$columns,$navy,$white,$grid);
    if (!$rows) {
        $emptyColumns = [['Informasi',770,145]];
        $prepared = $this->reportPrepareRow($emptyColumns,['Tidak ada data pada periode ini.']);
        $cursor = $this->reportDrawRow($commands,$cursor,$emptyColumns,$prepared,0,$white,$light,$grid,$textColor);
        return;
    }
    foreach ($rows as $index=>$values) {
        $prepared = $this->reportPrepareRow($columns,$values);
        if ($cursor-$prepared['height'] < 45) {
            $beginPage($title.' · lanjutan',false);
            $cursor = $this->reportTableHeader($commands,$cursor,$columns,$navy,$white,$grid);
        }
        $cursor = $this->reportDrawRow($commands,$cursor,$columns,$prepared,$index,$white,$light,$grid,$textColor);
    }
};

$beginPage($reportTitle, $reportType === 'semua');
if ($reportType === 'semua' || $reportType === 'stok') {
$stockColumns = [['Kode',75,14],['Barang',335,70],['Stok tersedia',110,20],['Minimum',100,18],['Satuan',150,26]];
$stockByWarehouse = [];
foreach ($stockRows as $stockRow) {
    $stockByWarehouse[(int)$stockRow['gudang_id']]['nama'] = $stockRow['gudang'];
    $stockByWarehouse[(int)$stockRow['gudang_id']]['rows'][] = [
        $stockRow['kode'],
        $stockRow['nama'],
        number_format((int)$stockRow['stok']),
        number_format((int)$stockRow['stok_minimum']),
        $stockRow['satuan'],
    ];
}
if (!$stockByWarehouse) {
    $drawSection('Stok per gudang', [['Informasi',770,145]], []);
} else {
    foreach ($stockByWarehouse as $warehouseStock) {
        $drawSection('Stok gudang: '.$warehouseStock['nama'],$stockColumns,$warehouseStock['rows']);
    }
}

}
$requestColumns = [['ID',42,8],['Tanggal',76,16],['Gudang',122,22],['Peminta',110,22],['Rincian barang',320,70],['Status',100,20]];
$requestValues = array_map(static fn(array $row): array => ['#'.$row['id'],date('d M Y',strtotime($row['tanggal'])),$row['gudang'] ?: 'Arsip lama; lokasi tidak dipetakan',$row['peminta'],$row['barang'] ?: '-',(string)$row['status']],$requestRows);
if ($reportType === 'semua' || $reportType === 'permintaan') $drawSection('Permintaan barang',$requestColumns,$requestValues);

$shipmentColumns = [['ID',45,8],['Dikirim',82,18],['Gudang asal',112,20],['Gudang tujuan',112,20],['Status',112,22],['Rincian dikirim / diterima',307,66]];
$shipmentValues = array_map(static fn(array $row): array => ['#'.$row['id'],date('d M Y H:i',strtotime($row['dikirim_pada'])),$row['gudang_asal'],$row['gudang_tujuan'],str_replace('_',' ',$row['status']),$row['rincian']],$shipmentRows);
if ($reportType === 'semua' || $reportType === 'pengiriman') $drawSection('Pengiriman, penerimaan, dan barang dalam perjalanan',$shipmentColumns,$shipmentValues);

$additionColumns = [['Tanggal',82,18],['Proyek',112,22],['Barang',152,30],['Jumlah',62,12],['Keterangan',242,48],['Petugas',120,22]];
$additionValues = array_map(static fn(array $row): array => [date('d M Y H:i',strtotime($row['ditambahkan_pada'])),$row['gudang'],trim($row['kode'].' · '.$row['barang']),number_format((int)$row['jumlah']).' '.$row['satuan'],$row['catatan'],$row['petugas']],$additionRows);
if ($reportType === 'semua' || $reportType === 'penambahan') $drawSection('Penambahan barang proyek',$additionColumns,$additionValues);

$returnColumns = [['Dikirim',82,18],['Proyek',102,20],['Peralatan',126,26],['Jumlah',58,12],['Catatan kondisi',226,44],['Petugas',112,22],['Status',64,14]];
$returnValues = array_map(static fn(array $row): array => [date('d M Y H:i',strtotime($row['dikirim_pada'])),$row['gudang'],trim($row['kode'].' · '.$row['barang']),number_format((int)$row['jumlah']).' '.$row['satuan'],$row['catatan_kondisi'],trim($row['pengirim'].($row['penerima'] ? ' / '.$row['penerima'] : '')),$row['status']==='menunggu_diterima'?'Menunggu admin pusat':'Diterima'], $returnRows);
if ($reportType === 'semua' || $reportType === 'retur') $drawSection('Riwayat kondisi pengembalian peralatan',$returnColumns,$returnValues);

$movementLabels = ['masuk'=>'Barang masuk','keluar'=>'Dikirim keluar','kembali'=>'Barang kembali','pemakaian'=>'Pemakaian proyek'];
$movementColumns = [['Tanggal',82,18],['Jenis',88,20],['Gudang',112,22],['Barang',142,30],['Jumlah',58,12],['Asal / tujuan',112,23],['Keterangan',118,25],['Petugas',58,12]];
$movementValues = array_map(function(array $row) use ($movementLabels): array {
    $note = trim(($row['nomor_transaksi'] ? 'No. '.$row['nomor_transaksi'].' · ' : '').($row['referensi'] ?: '').($row['nama_supplier'] ? ' · Supplier: '.$row['nama_supplier'] : ''));
    $type = $row['referensi'] === 'Penyesuaian stok melalui katalog' ? ($row['jenis'] === 'masuk' ? 'Penyesuaian stok (+)' : 'Penyesuaian stok (-)') : ($movementLabels[$row['jenis']] ?? $row['jenis']);
    if ($row['jenis']==='kembali' && str_starts_with((string)$row['referensi'],'Peralatan kembali dari proyek #')) $type = 'Retur peralatan';
    return [date('d M Y H:i',strtotime($row['tanggal'])),$type,$row['gudang'],trim($row['kode'].' · '.$row['barang']),number_format((int)$row['jumlah']).' '.$row['satuan'],($row['gudang_asal'] ?: '-').' -> '.($row['gudang_tujuan'] ?: '-'),$note ?: '-',($row['nama_petugas'] ?: $row['operator'] ?: '-')];
},$movementRows);
if ($reportType === 'semua' || $reportType === 'mutasi') $drawSection('Penerimaan aktual dan pemakaian proyek',$movementColumns,$movementValues);


if ($reportType === 'semua' || $reportType === 'pembelian') {
 $q=$pdo->prepare("SELECT r.id,r.tanggal,r.jumlah,r.status,b.kode,b.nama,b.satuan,u.nama peminta,COALESCE(g.nama,'Gudang Pusat') gudang FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id JOIN users u ON u.id=r.user_id LEFT JOIN gudang g ON g.id=u.gudang_id WHERE r.tanggal>=? AND r.tanggal<? AND (u.role='admin_pusat' OR g.aktif=1) ORDER BY r.tanggal DESC,r.id DESC"); $q->execute([$start,$endExclusive]);
 $values=array_map(static fn(array $r): array=>['#'.$r['id'],date('d M Y',strtotime($r['tanggal'])),$r['gudang'],$r['peminta'],$r['kode'].' · '.$r['nama'].' × '.$r['jumlah'].' '.$r['satuan'],$r['status']],$q->fetchAll());
 $drawSection('Pengajuan pembelian',$requestColumns,$values);
}
if ($reportType === 'semua' || $reportType === 'penerimaan_pembelian') {
 $q=$pdo->prepare('SELECT r.* FROM penerimaan_pembelian r JOIN gudang g ON g.id=r.gudang_id WHERE r.diterima_pada>=? AND r.diterima_pada<? AND g.aktif=1 ORDER BY r.diterima_pada DESC,r.id DESC'); $q->execute([$start,$endExclusive]);
 $values=array_map(static fn(array $r): array=>['#'.$r['id'],date('d M Y',strtotime($r['diterima_pada'])),$r['nama_gudang'],$r['kode_barang'].' · '.$r['nama_barang'],$r['jumlah_layak'].' / '.$r['jumlah_rusak'].' / '.$r['jumlah_tidak_sesuai'],$r['nama_petugas']],$q->fetchAll());
 $drawSection('Penerimaan pembelian', [['ID',42,8],['Tanggal',76,16],['Gudang',122,22],['Barang',260,55],['Layak / Rusak / Tidak sesuai',160,32],['Petugas',110,22]],$values);
}

if ($commands) $pages[] = $commands;
$logoPath = public_path('assets/logo-report.jpg');
$logoData = file_get_contents($logoPath);
$logoSize = getimagesize($logoPath);
if ($logoData === false || $logoSize === false) throw new RuntimeException('Logo laporan tidak dapat dibaca.');
$objects = [
    1=>'<< /Type /Catalog /Pages 2 0 R >>',
    3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
    6=>'<< /Type /XObject /Subtype /Image /Width '.$logoSize[0].' /Height '.$logoSize[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($logoData)." >>\nstream\n".$logoData."\nendstream",
];
$pageIds = [];
foreach ($pages as $index=>$pageCommands) {
    $pageId = 7+($index*2);
    $streamId = $pageId+1;
    $pageIds[] = $pageId.' 0 R';
    $this->reportPdfLine($pageCommands,36,34,806,34,$grid,0.65);
    $this->reportPdfText($pageCommands,36,21,'GudangKita | Periode '.date('d M Y',strtotime($start)).' - '.date('d M Y',strtotime($end)),'F1',7,$muted);
    $this->reportPdfText($pageCommands,750,21,'Halaman '.($index+1).' dari '.count($pages),'F1',7,$muted);
    $stream = implode('',$pageCommands);
    $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 841.89 595.28] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /Logo 6 0 R >> >> /Contents '.$streamId.' 0 R >>';
    $objects[$streamId] = '<< /Length '.strlen($stream).">>\nstream\n".$stream.'endstream';
}
$objects[2] = '<< /Type /Pages /Kids ['.implode(' ',$pageIds).'] /Count '.count($pages).' >>';
ksort($objects);
$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $id=>$object) {
    $offsets[$id] = strlen($pdf);
    $pdf .= $id." 0 obj\n".$object."\nendobj\n";
}
$xrefOffset = strlen($pdf);
$maxObject = max(array_keys($objects));
$pdf .= "xref\n0 ".($maxObject+1)."\n0000000000 65535 f \n";
for ($id=1;$id<=$maxObject;$id++) $pdf .= isset($offsets[$id]) ? sprintf("%010d 00000 n \n",$offsets[$id]) : "0000000000 00000 f \n";
$pdf .= "trailer\n<< /Size ".($maxObject+1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

$filename = 'laporan-'.$reportType.'-'.$start.'-sampai-'.$end.'.pdf';
warehouse_header('Content-Type: application/pdf');
warehouse_header('Content-Disposition: '.(($queryInput['tampilan'] ?? '') === 'lihat' ? 'inline' : 'attachment').'; filename="'.$filename.'"');
warehouse_header('Content-Length: '.strlen($pdf));
warehouse_header('Cache-Control: private, max-age=0, must-revalidate');
echo $pdf;

$body=ob_get_clean(); return warehouse_response($body);
} catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { $body=ob_get_clean(); if($e->getResponse()->getContent()==='') $e->getResponse()->setContent($body); throw $e; } catch (Throwable $e) { ob_end_clean(); throw $e; }
}
protected function reportAscii(?string $value): string {
    $value = str_replace(["\r","\n","\t"],' ',$value ?? '');
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
        if ($converted !== false) $value = $converted;
    }
    return preg_replace('/[^\x20-\x7E]/','?',$value) ?? '';
}
protected function reportEscape(string $value): string {
    return str_replace(['\\','(',')'],['\\\\','\\(', '\\)'],$value);
}
protected function reportWrap(?string $value,int $width): array {
    $value = trim($this->reportAscii($value));
    return $value === '' ? ['-'] : explode("\n",wordwrap($value,max(1,$width),"\n",true));
}
protected function reportPdfText(array &$commands,float $x,float $y,string $text,string $font,int $size,array $color): void {
    $rgb = implode(' ',array_map(static fn(float $channel): string => number_format($channel,3,'.',''),$color));
    $commands[] = 'BT /'.$font.' '.$size.' Tf '.$rgb.' rg '.number_format($x,2,'.','').' '.number_format($y,2,'.','').' Td ('.$this->reportEscape($this->reportAscii($text)).") Tj ET\n";
}
protected function reportPdfRect(array &$commands,float $x,float $y,float $width,float $height,array $fill,?array $stroke=null,float $strokeWidth=0.5): void {
    $fillRgb = implode(' ',array_map(static fn(float $channel): string => number_format($channel,3,'.',''),$fill));
    $command = 'q '.$fillRgb.' rg '.number_format($x,2,'.','').' '.number_format($y,2,'.','').' '.number_format($width,2,'.','').' '.number_format($height,2,'.','').' re f';
    if ($stroke !== null) {
        $strokeRgb = implode(' ',array_map(static fn(float $channel): string => number_format($channel,3,'.',''),$stroke));
        $command .= ' '.$strokeRgb.' RG '.number_format($strokeWidth,2,'.','').' w '.number_format($x,2,'.','').' '.number_format($y,2,'.','').' '.number_format($width,2,'.','').' '.number_format($height,2,'.','').' re S';
    }
    $commands[] = $command." Q\n";
}
protected function reportPdfLine(array &$commands,float $x1,float $y1,float $x2,float $y2,array $color,float $width=0.5): void {
    $rgb = implode(' ',array_map(static fn(float $channel): string => number_format($channel,3,'.',''),$color));
    $commands[] = 'q '.$rgb.' RG '.number_format($width,2,'.','').' w '.number_format($x1,2,'.','').' '.number_format($y1,2,'.','').' m '.number_format($x2,2,'.','').' '.number_format($y2,2,'.','').' l S Q' . "\n";
}
protected function reportTableHeader(array &$commands,float $top,array $columns,array $navy,array $white,array $grid): float {
    $height = 26.0;
    $this->reportPdfRect($commands,36,$top-$height,770,$height,$navy);
    $x = 36.0;
    foreach ($columns as $column) {
        $this->reportPdfText($commands,$x+6,$top-17,(string)$column[0],'F2',7,$white);
        $x += (float)$column[1];
        if ($x < 806) $this->reportPdfLine($commands,$x,$top-$height,$x,$top,$grid,0.45);
    }
    return $top-$height;
}
protected function reportPrepareRow(array $columns,array $values): array {
    $cells = [];
    $maxLines = 1;
    foreach ($columns as $index=>$column) {
        $cells[] = $this->reportWrap((string)($values[$index] ?? ''),(int)$column[2]);
        $maxLines = max($maxLines,count($cells[count($cells)-1]));
    }
    return ['cells'=>$cells,'height'=>max(21.0,10.0+($maxLines*8.2))];
}
protected function reportDrawRow(array &$commands,float $top,array $columns,array $prepared,int $rowNumber,array $white,array $alternate,array $grid,array $textColor): float {
    $height = $prepared['height'];
    $bottom = $top-$height;
    $this->reportPdfRect($commands,36,$bottom,770,$height,$rowNumber%2===0?$alternate:$white,$grid,0.45);
    $x = 36.0;
    foreach ($columns as $index=>$column) {
        foreach ($prepared['cells'][$index] as $lineIndex=>$value) {
            $this->reportPdfText($commands,$x+6,$top-11-($lineIndex*8.2),$value,'F1',8,$textColor);
        }
        $x += (float)$column[1];
        if ($x < 806) $this->reportPdfLine($commands,$x,$bottom,$x,$top,$grid,0.4);
    }
    return $bottom;
}

public function login() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];

if (user()) {
    $userRole = user()['role'] ?? '';
    if ($userRole === 'super_admin') go('super_admin.php');
    if ($userRole === 'purchasing') go('purchasing.php');
    if (in_array($userRole, ['admin_pusat','admin_gudang','manager'], true)) go('dashboard.php');
    auth()->logout();
}
$error='';
if ($server['REQUEST_METHOD']==='POST') {
    check_csrf();
    $email=is_string($post['email']??null)?trim($post['email']):'';
    $password=is_string($post['password']??null)?$post['password']:'';
    $ip=(string)(request()->ip()??'unknown');
    $ipKey='login:ip:'.hash('sha256',$ip);
    $emailKey='login:email:'.hash('sha256',strtolower($email).'|'.$ip);
    $error='Email atau kata sandi tidak sesuai.';
    if (!RateLimiter::tooManyAttempts($ipKey,30) && !RateLimiter::tooManyAttempts($emailKey,5)) {
        $q=db()->prepare('SELECT id,nama,email,password_hash,role,gudang_id FROM users WHERE email=? AND aktif=1 LIMIT 1');
        $q->execute([$email]); $u=$q->fetch();
        if ($u && password_verify($password, $u['password_hash'])) {
            $allowedRoles=['super_admin','admin_pusat','admin_gudang','manager','purchasing'];
            $warehouseReady=true;
            if($u['role']==='admin_gudang'){
                $warehouse=db()->prepare("SELECT id FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 LIMIT 1");
                $warehouse->execute([(int)($u['gudang_id']??0)]);
                $warehouseReady=(bool)$warehouse->fetchColumn();
            }
            if(in_array($u['role'],$allowedRoles,true)&&$warehouseReady){
                RateLimiter::clear($emailKey);
                RateLimiter::clear($ipKey);
                session()->regenerate(); unset($u['password_hash']); auth()->login(\App\Models\User::findOrFail($u['id']));
                go($u['role']==='super_admin'?'super_admin.php':($u['role']==='purchasing'?'purchasing.php':'dashboard.php'));
            }
        }
        RateLimiter::hit($emailKey,60);
        RateLimiter::hit($ipKey,60);
    }
}

return view('warehouse.login', get_defined_vars());
}
public function penerimaan_pembelian() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];

require_role('admin_pusat','admin_gudang');

$pdo = db();
$currentUser = user();
$warehouseId = $currentUser['role'] === 'admin_pusat' ? (int)$pdo->query("SELECT id FROM gudang WHERE kode='PUSAT' AND jenis='pusat' AND aktif=1 LIMIT 1")->fetchColumn() : (int)$currentUser['gudang_id'];
$warehouseQuery = $pdo->prepare('SELECT id,nama,jenis FROM gudang WHERE id=? AND aktif=1 LIMIT 1');
$warehouseQuery->execute([$warehouseId]);
$warehouse = $warehouseQuery->fetch();
if (!$warehouse || ($currentUser['role'] === 'admin_pusat' && $warehouse['jenis'] !== 'pusat') || ($currentUser['role'] === 'admin_gudang' && $warehouse['jenis'] !== 'proyek')) {
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
foreach ($historyQuery->fetchAll() as $receipt) $receiptsByRequest[(int)$receipt['permintaan_pembelian_id']][] = $receipt;
$photoQuery = $pdo->prepare('SELECT f.id foto_id,x.id penerimaan_id FROM penerimaan_pembelian_foto f JOIN penerimaan_pembelian x ON x.id=f.penerimaan_pembelian_id JOIN permintaan_pembelian r ON r.id=x.permintaan_pembelian_id JOIN users requester ON requester.id=r.user_id WHERE r.status=\'disetujui\' AND '.$ownerScopeSql.' ORDER BY x.permintaan_pembelian_id,x.diterima_pada DESC,x.id DESC,f.urutan');
$photoQuery->execute($scopeParams);
$photosByReceipt = [];
foreach ($photoQuery->fetchAll() as $photo) $photosByReceipt[(int)$photo['penerimaan_id']][] = (int)$photo['foto_id'];

$formTokens = [];
foreach ($requests as $request) {
    if ((int)$request['jumlah'] > (int)$request['jumlah_diterima'] && (int)$request['barang_aktif'] === 1) {
        $formTokens[(int)$request['id']] = bin2hex(random_bytes(32));
    }
}
$flashMessage = take_flash();
$dashboardAnchor = 'dashboard.php#pengajuan-pembelian';

return view('warehouse.penerimaan_pembelian', get_defined_vars());
}
public function purchasing() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];

require_role('purchasing');
$pdo = db();
$currentUser = user();
$items = $pdo->query("SELECT id,kode,nama,kategori,kategori_barang,satuan,stok_minimum FROM barang WHERE aktif=1 ORDER BY kode")->fetchAll();
$stockQuery = $pdo->query("SELECT g.nama gudang,b.kode,b.nama barang,b.kategori_barang,b.satuan,b.stok_minimum,COALESCE(s.stok,0) stok FROM gudang g CROSS JOIN barang b LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE g.aktif=1 AND b.aktif=1 ORDER BY g.nama,b.kode");
$stockRows = $stockQuery->fetchAll();
$requestQuery = $pdo->query('SELECT r.id,r.user_id,r.jumlah,r.catatan,r.status,r.tanggal,r.diproses_pada,b.kode,b.nama barang,b.satuan,requester.nama peminta,processor.nama pemroses,COALESCE(received.jumlah_diterima,0) jumlah_diterima,COALESCE(received.jumlah_layak,0) jumlah_layak,COALESCE(received.jumlah_rusak,0) jumlah_rusak,COALESCE(received.jumlah_tidak_sesuai,0) jumlah_tidak_sesuai FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id JOIN users requester ON requester.id=r.user_id LEFT JOIN users processor ON processor.id=r.diproses_oleh LEFT JOIN (SELECT permintaan_pembelian_id,SUM(jumlah_diterima) jumlah_diterima,SUM(jumlah_layak) jumlah_layak,SUM(jumlah_rusak) jumlah_rusak,SUM(jumlah_tidak_sesuai) jumlah_tidak_sesuai FROM penerimaan_pembelian GROUP BY permintaan_pembelian_id) received ON received.permintaan_pembelian_id=r.id ORDER BY FIELD(r.status,\'menunggu\',\'disetujui\',\'ditolak\'),r.tanggal DESC,r.id DESC');
$requests = $requestQuery->fetchAll();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM permintaan_pembelian WHERE status='menunggu'")->fetchColumn();
$notificationCountQuery = $pdo->prepare('SELECT COUNT(*) FROM purchasing_notifications WHERE recipient_id=? AND read_at IS NULL');
$notificationCountQuery->execute([(int)$currentUser['id']]);
$unreadNotificationCount = (int)$notificationCountQuery->fetchColumn();
$notificationQuery = $pdo->prepare("SELECT n.id notification_id,n.created_at,r.id request_id,r.status,r.tanggal,r.jumlah,u.nama peminta,u.role pengaju_role,b.kode,b.nama barang,b.satuan FROM purchasing_notifications n JOIN permintaan_pembelian r ON r.id=n.permintaan_pembelian_id JOIN users u ON u.id=r.user_id JOIN barang b ON b.id=r.barang_id WHERE n.recipient_id=? AND n.read_at IS NULL ORDER BY n.created_at DESC,n.id DESC LIMIT 20");
$notificationQuery->execute([(int)$currentUser['id']]);
$unreadNotifications = $notificationQuery->fetchAll();
$flashMessage = take_flash();
$statusLabels = ['menunggu'=>'Menunggu diproses','disetujui'=>'Disetujui','ditolak'=>'Ditolak'];
$statusClasses = ['menunggu'=>'text-bg-warning','disetujui'=>'text-bg-success','ditolak'=>'text-bg-danger'];
$receiptSummary = static function(array $request): string {
    $ordered = (int)$request['jumlah'];
    $ready = (int)($request['jumlah_layak'] ?? 0);
    $damaged = (int)($request['jumlah_rusak'] ?? 0);
    $mismatched = (int)($request['jumlah_tidak_sesuai'] ?? 0);
    $received = (int)($request['jumlah_diterima'] ?? 0);
    $remaining = max(0,$ordered-$received);
    return 'Dipesan '.number_format($ordered).' '.(string)$request['satuan'].'; layak '.number_format($ready).'; rusak '.number_format($damaged).'; tidak sesuai '.number_format($mismatched).'; belum diterima '.number_format($remaining);
};
$categoryLabels = ['barang_siap'=>'Material Project','barang_dipakai'=>'Barang Material Habis / Consumable','peralatan'=>'Peralatan'];

return view('warehouse.purchasing', get_defined_vars());
}
public function super_admin() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];

require_role('super_admin');
$pdo = db();
$currentUserId = (int)user()['id'];
$roleLabels = [
    'pengguna'=>'Pengguna',
    'admin_pusat'=>'Admin Pusat',
    'admin_gudang'=>'Admin Gudang Proyek',
    'manager'=>'Manajer',
    'purchasing'=>'Purchasing',
    'super_admin'=>'Super Admin',
];
$accounts = $pdo->query("SELECT u.id,u.nama,u.email,u.role,u.gudang_id,u.aktif,g.nama gudang,g.aktif gudang_aktif,p.status pemetaan_status FROM users u LEFT JOIN gudang g ON g.id=u.gudang_id LEFT JOIN pemetaan_akun_lama p ON p.user_id=u.id AND p.status='menunggu_konfirmasi' ORDER BY u.aktif DESC,u.nama,u.id")->fetchAll();
$warehouses = $pdo->query("SELECT id,kode,nama,aktif FROM gudang WHERE jenis='proyek' ORDER BY aktif DESC,nama")->fetchAll();
$managerAccounts = $pdo->query("SELECT id,nama,email FROM users WHERE role='manager' AND aktif=1 ORDER BY nama,id")->fetchAll();
$managerAssignments = $pdo->query("SELECT mg.manager_id,mg.gudang_id,mg.ditugaskan_pada,u.nama manager,g.nama gudang FROM manager_gudang mg JOIN users u ON u.id=mg.manager_id JOIN gudang g ON g.id=mg.gudang_id ORDER BY u.nama,g.nama")->fetchAll();
$pendingAccounts = $pdo->query("SELECT p.user_id,p.aktif_sebelumnya,u.nama,u.email FROM pemetaan_akun_lama p JOIN users u ON u.id=p.user_id WHERE p.role_lama='admin_gudang' AND p.status='menunggu_konfirmasi' ORDER BY u.nama,u.id")->fetchAll();
$auditRows = $pdo->query('SELECT id,pelaku_nama,pelaku_email,sasaran_nama,sasaran_email,aksi,detail,dibuat_pada FROM riwayat_pengelolaan_akun ORDER BY dibuat_pada DESC,id DESC LIMIT 100')->fetchAll();
$flashMessage = take_flash();
$auditLabels = [
    'bootstrap'=>'Bootstrap Super Admin',
    'akun_dibuat'=>'Akun dibuat',
    'akun_diperbarui'=>'Akun diperbarui',
    'role_diubah'=>'Role diubah',
    'akun_dinonaktifkan'=>'Akun dinonaktifkan',
    'akun_diaktifkan'=>'Akun diaktifkan',
];

$notificationInbox = app(WarehouseNotifications::class)->inbox($pdo, (int)user()['id']);
return view('warehouse.super_admin', get_defined_vars());
}
public function surat_jalan() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];

require_role('admin_pusat','admin_gudang','manager');

$currentUser = user();
$rawId = $queryInput['id'] ?? null;
$normalizedId = is_string($rawId) && ctype_digit($rawId) ? ltrim($rawId,'0') : '';
$maxId = (string)PHP_INT_MAX;
if ($normalizedId === '' || strlen($normalizedId) > strlen($maxId) || (strlen($normalizedId) === strlen($maxId) && strcmp($normalizedId,$maxId) > 0)) {
    warehouse_status(404);
    warehouse_abort('Surat jalan tidak ditemukan.');
}
$pdo = db();
$query = $pdo->prepare("SELECT pe.*,ga.nama gudang_asal,ga.kode kode_asal,ga.jenis jenis_asal,ga.aktif asal_aktif,gt.nama gudang_tujuan,gt.jenis jenis_tujuan,gt.aktif tujuan_aktif,u.nama pengirim,ur.nama penerima,p.catatan,p.nomor_kendaraan,p.nama_driver FROM pengiriman pe JOIN gudang ga ON ga.id=pe.gudang_asal_id JOIN gudang gt ON gt.id=pe.gudang_tujuan_id JOIN users u ON u.id=pe.dikirim_oleh LEFT JOIN users ur ON ur.id=pe.diterima_oleh JOIN permintaan p ON p.id=pe.permintaan_id WHERE pe.id=? LIMIT 1");
$query->execute([(int)$normalizedId]);
$shipment = $query->fetch();
$canView = $shipment && $shipment['jenis_tujuan'] === 'proyek' && match ($currentUser['role']) {
    'admin_pusat' => $shipment['kode_asal'] === 'PUSAT' && $shipment['jenis_asal'] === 'pusat',
    'admin_gudang' => (int)$shipment['gudang_tujuan_id'] === (int)$currentUser['gudang_id'],
    'manager' => (bool)$shipment['asal_aktif'] && (bool)$shipment['tujuan_aktif'],
    default => false,
};
if (!$canView) {
    warehouse_status(404);
    warehouse_abort('Surat jalan tidak ditemukan.');
}
$query = $pdo->prepare("SELECT d.jumlah_dikirim,b.kode,b.nama,b.satuan,COALESCE((SELECT t.satuan_transaksi FROM transaksi_gudang t WHERE t.pengiriman_id=d.pengiriman_id AND t.barang_id=d.barang_id AND t.jenis='keluar' ORDER BY t.id LIMIT 1),b.satuan) satuan_kirim FROM detail_pengiriman d JOIN barang b ON b.id=d.barang_id WHERE d.pengiriman_id=? ORDER BY d.id");
$query->execute([(int)$shipment['id']]);
$items = $query->fetchAll();
if (!$items) {
    warehouse_status(404);
    warehouse_abort('Rincian surat jalan tidak ditemukan.');
}
$documentNumber = 'SJ-'.str_pad((string)$shipment['id'],8,'0',STR_PAD_LEFT);
$statusLabels = ['dalam_perjalanan'=>'Dalam perjalanan','diterima'=>'Diterima','diterima_dengan_selisih'=>'Diterima dengan selisih'];

return view('warehouse.surat_jalan', get_defined_vars());
}
}