<?php
namespace App\Services;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use DateTimeImmutable;
use finfo;
class WarehouseActions {
public function aksi_admin() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];
if ($server['REQUEST_METHOD'] !== 'POST') go('dashboard.php');
$contentLength = (int)($server['CONTENT_LENGTH'] ?? 0);
$postLimitText = trim((string)ini_get('post_max_size'));
if (preg_match('/^(\d+)\s*([KMG])?$/iD',$postLimitText,$postLimitParts)) {
    $postLimitBytes = (int)$postLimitParts[1] * match (strtoupper($postLimitParts[2] ?? '')) {
        'G' => 1024 * 1024 * 1024,
        'M' => 1024 * 1024,
        'K' => 1024,
        default => 1,
    };
    if ($contentLength > 0 && $postLimitBytes > 0 && $contentLength > $postLimitBytes && empty($post) && empty($uploads)) {
        warehouse_status(413);
        warehouse_abort('Unggahan melebihi batas ukuran formulir pada server. Kurangi ukuran atau jumlah foto lalu coba kembali.');
    }
}
check_csrf();
$pdo = db();
$aksi = is_string($post['aksi'] ?? null) ? $post['aksi'] : '';

$text = static fn(string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
$positiveInt = static function(mixed $value): int {
    return is_string($value) && ctype_digit($value) ? (int)$value : 0;
};
$unsignedQuantity = static function(mixed $value): ?int {
    if (!is_string($value) || !ctype_digit($value) || strlen($value) > 10) return null;
    $quantity = (int)$value;
    return $quantity <= 4294967295 ? $quantity : null;
};
$textLength = static function(string $value): int {
    $length = preg_match_all('/./us', $value);
    return $length === false ? PHP_INT_MAX : $length;
};
$automaticItemCode = static function(PDO $pdo, string $category, int $itemId): string {
    $prefixes = ['barang_siap'=>'MP','barang_dipakai'=>'MH','peralatan'=>'PR'];
    if (!isset($prefixes[$category])) throw new RuntimeException('Jenis barang tidak valid.');
    $pdo->prepare('INSERT IGNORE INTO nomor_kode_barang (kategori_barang,nomor_terakhir) VALUES (?,0)')->execute([$category]);
    $query = $pdo->prepare('SELECT nomor_terakhir FROM nomor_kode_barang WHERE kategori_barang=? FOR UPDATE');
    $query->execute([$category]);
    $number = (int)$query->fetchColumn();
    $exists = $pdo->prepare('SELECT id FROM barang WHERE kode=? AND id<>? LIMIT 1 FOR UPDATE');
    do {
        if ($number >= 4294967295) throw new RuntimeException('Nomor kode barang sudah mencapai batas.');
        $code = $prefixes[$category].'-'.str_pad((string)++$number,6,'0',STR_PAD_LEFT);
        $exists->execute([$code,$itemId]);
    } while ($exists->fetchColumn() !== false);
    $pdo->prepare('UPDATE nomor_kode_barang SET nomor_terakhir=? WHERE kategori_barang=?')->execute([$number,$category]);
    return $code;
};
$validDate = static function(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
    [$year,$month,$day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
};
$centerId = static function(PDO $pdo): int {
    $q = $pdo->query("SELECT id FROM gudang WHERE kode='PUSAT' AND jenis='pusat' AND aktif=1 LIMIT 1");
    $id = (int)$q->fetchColumn();
    if ($id < 1) throw new RuntimeException('Gudang Pusat belum tersedia.');
    return $id;
};
$lockStock = static function(PDO $pdo, int $warehouseId, int $itemId): int {
    $pdo->prepare('INSERT IGNORE INTO stok_gudang (gudang_id,barang_id,stok) VALUES (?,?,0)')
        ->execute([$warehouseId,$itemId]);
    $q = $pdo->prepare('SELECT stok FROM stok_gudang WHERE gudang_id=? AND barang_id=? FOR UPDATE');
    $q->execute([$warehouseId,$itemId]);
    $stock = $q->fetchColumn();
    if ($stock === false) throw new RuntimeException('Stok gudang tidak ditemukan.');
    return (int)$stock;
};
$hasOpenPurchaseRequest = static function(PDO $pdo, int $itemId): bool {
    $q = $pdo->prepare("SELECT COUNT(*) FROM permintaan_pembelian p WHERE p.barang_id=? AND (p.status='menunggu' OR (p.status='disetujui' AND p.jumlah>COALESCE((SELECT SUM(r.jumlah_diterima) FROM penerimaan_pembelian r WHERE r.permintaan_pembelian_id=p.id),0)))");
    $q->execute([$itemId]);
    return (int)$q->fetchColumn() > 0;
};
$recordMovement = static function(PDO $pdo, array $movement): int {
    $q = $pdo->prepare('INSERT INTO transaksi_gudang
        (barang_id,gudang_id,gudang_asal_id,gudang_tujuan_id,permintaan_id,pengiriman_id,user_id,jenis,jumlah,referensi,nomor_transaksi,nama_supplier,tujuan_keluar,satuan_transaksi,nama_petugas,tanggal)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $q->execute([
        $movement['barang_id'],$movement['gudang_id'],$movement['gudang_asal_id'] ?? null,
        $movement['gudang_tujuan_id'] ?? null,$movement['permintaan_id'] ?? null,
        $movement['pengiriman_id'] ?? null,$movement['user_id'] ?? null,$movement['jenis'],
        $movement['jumlah'],$movement['referensi'] ?? null,$movement['nomor_transaksi'] ?? null,
        $movement['nama_supplier'] ?? null,$movement['tujuan_keluar'] ?? null,
        $movement['satuan_transaksi'] ?? null,$movement['nama_petugas'] ?? null,
        $movement['tanggal'] ?? date('Y-m-d H:i:s'),
    ]);
    return (int)$pdo->lastInsertId();
};

$returnUrl = 'dashboard.php';
$newReceiptPhotoPaths = [];
try {
    if ($aksi === 'barang_masuk') {
        require_role('admin_pusat');
        $warehouseId = $centerId($pdo);
        $itemId = $positiveInt($post['barang_id'] ?? null);
        $quantity = $positiveInt($post['jumlah'] ?? null);
        $transactionNo = $text('nomor_transaksi');
        $transactionDate = $text('tanggal_masuk');
        $supplier = $text('nama_supplier');
        $note = $text('referensi');
        if ($itemId < 1 || $quantity < 1 || $quantity > 4294967295 || $transactionNo === '' || $textLength($transactionNo) > 50 ||
            !$validDate($transactionDate) || $supplier === '' || $textLength($supplier) > 150 || $textLength($note) > 255) {
            throw new RuntimeException('Nomor transaksi, tanggal, supplier, barang, dan jumlah masuk harus diisi dengan benar.');
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT id,nama,satuan FROM barang WHERE id=? AND aktif=1 FOR UPDATE');
        $q->execute([$itemId]); $item = $q->fetch();
        if (!$item) throw new RuntimeException('Barang tidak ditemukan atau sudah nonaktif.');
        $q = $pdo->prepare('SELECT id FROM transaksi_gudang WHERE nomor_transaksi=? LIMIT 1');
        $q->execute([$transactionNo]);
        if ($q->fetchColumn()) throw new RuntimeException('Nomor transaksi sudah pernah digunakan.');
        $stock = $lockStock($pdo,$warehouseId,$itemId);
        if ($stock + $quantity > 4294967295) throw new RuntimeException('Jumlah melebihi batas stok yang dapat disimpan.');
        $pdo->prepare('UPDATE stok_gudang SET stok=stok+? WHERE gudang_id=? AND barang_id=?')
            ->execute([$quantity,$warehouseId,$itemId]);
        $recordMovement($pdo,[
            'barang_id'=>$itemId,'gudang_id'=>$warehouseId,'gudang_tujuan_id'=>$warehouseId,
            'user_id'=>user()['id'],'jenis'=>'masuk','jumlah'=>$quantity,
            'referensi'=>$note ?: 'Penerimaan supplier','nomor_transaksi'=>$transactionNo,
            'nama_supplier'=>$supplier,'satuan_transaksi'=>$item['satuan'],'tanggal'=>$transactionDate.' 00:00:00',
        ]);
        $pdo->commit();
        flash('Barang diterima di Gudang Pusat dan stok pusat diperbarui.');
    } elseif ($aksi === 'pembelian_penerimaan_simpan') {
        require_role('admin_pusat','admin_gudang');
        $requestId = $positiveInt($post['permintaan_pembelian_id'] ?? null);
        $returnUrl = 'penerimaan_pembelian.php'.($requestId > 0 ? '#pembelian-'.$requestId : '');
        $formToken = $text('token_form');
        $goodQuantity = $unsignedQuantity($post['jumlah_layak'] ?? null);
        $damagedQuantity = $unsignedQuantity($post['jumlah_rusak'] ?? null);
        $mismatchQuantity = $unsignedQuantity($post['jumlah_tidak_sesuai'] ?? null);
        $receivedDateInput = $text('tanggal_diterima');
        $conditionNote = $text('catatan_kondisi');
        $receivedDate = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i',$receivedDateInput);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $receivedDateValue = $receivedDate && (!is_array($dateErrors) || (!$dateErrors['warning_count'] && !$dateErrors['error_count'])) &&
            $receivedDate->format('Y-m-d\\TH:i') === $receivedDateInput ? $receivedDate->format('Y-m-d H:i:s') : null;
        $totalReceived = ($goodQuantity ?? 0) + ($damagedQuantity ?? 0) + ($mismatchQuantity ?? 0);
        if ($requestId < 1 || !preg_match('/^[a-f0-9]{64}$/D',$formToken) || $goodQuantity === null ||
            $damagedQuantity === null || $mismatchQuantity === null || $totalReceived < 1 || $totalReceived > 4294967295 ||
            !$receivedDateValue || $textLength($conditionNote) > 2000 ||
            (($damagedQuantity + $mismatchQuantity) > 0 && $conditionNote === '')) {
            throw new RuntimeException('Jumlah kondisi barang, tanggal penerimaan, dan catatan kondisi harus diisi dengan benar. Catatan wajib bila ada barang rusak atau tidak sesuai.');
        }

        $photoUploads = [];
        $photoFileLimit = 5;
        $photoSizeLimit = 4 * 1024 * 1024;
        $photoTotalSizeLimit = $photoFileLimit * $photoSizeLimit;
        $photoTypes = ['image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp'];
        $photoInput = $uploads['foto_bukti'] ?? null;
        if ($photoInput !== null) {
            if (!is_array($photoInput) || !is_array($photoInput['name'] ?? null) || !is_array($photoInput['tmp_name'] ?? null) || !is_array($photoInput['error'] ?? null)) {
                throw new RuntimeException('Data unggahan foto tidak valid. Pilih ulang foto bukti.');
            }
            $fileCount = count($photoInput['name']);
            if ($fileCount > $photoFileLimit || count($photoInput['tmp_name']) !== $fileCount || count($photoInput['error']) !== $fileCount) {
                throw new RuntimeException('Unggah maksimal '.$photoFileLimit.' foto bukti untuk satu penerimaan.');
            }
            if (!class_exists('finfo')) throw new RuntimeException('Validasi format gambar tidak tersedia di server. Hubungi administrator.');
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $totalPhotoBytes = 0;
            for ($index = 0; $index < $fileCount; $index++) {
                $uploadError = filter_var($photoInput['error'][$index] ?? null, FILTER_VALIDATE_INT);
                if ($uploadError === UPLOAD_ERR_NO_FILE) continue;
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $uploadErrorMessage = match ($uploadError) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran foto melebihi batas unggahan server atau formulir.',
                        UPLOAD_ERR_PARTIAL => 'Unggahan foto terputus. Pilih ulang foto dan kirim kembali.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara unggahan tidak tersedia di server.',
                        UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file foto.',
                        UPLOAD_ERR_EXTENSION => 'Ekstensi server menghentikan unggahan foto.',
                        default => 'Unggahan foto tidak dapat diproses.',
                    };
                    throw new RuntimeException($uploadErrorMessage);
                }

                $temporaryPath = $photoInput['tmp_name'][$index] ?? null;
                if (!is_string($temporaryPath) || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
                    throw new RuntimeException('File foto tidak dikenali sebagai unggahan yang sah. Pilih ulang foto bukti.');
                }
                $fileBytes = filesize($temporaryPath);
                if ($fileBytes === false || $fileBytes < 1 || $fileBytes > $photoSizeLimit) {
                    throw new RuntimeException('Setiap foto harus berukuran maksimal 4 MB.');
                }
                $totalPhotoBytes += $fileBytes;
                if ($totalPhotoBytes > $photoTotalSizeLimit) throw new RuntimeException('Total ukuran foto melebihi batas 20 MB per penerimaan.');

                $detectedMime = $fileInfo->file($temporaryPath);
                $imageInfo = @getimagesize($temporaryPath);
                if (!is_string($detectedMime) || !isset($photoTypes[$detectedMime]) || !$imageInfo ||
                    ($imageInfo['mime'] ?? null) !== $detectedMime || (int)($imageInfo[0] ?? 0) < 1 || (int)($imageInfo[1] ?? 0) < 1 ||
                    (int)$imageInfo[0] > 10000 || (int)$imageInfo[1] > 10000 || (int)$imageInfo[0] > intdiv(40000000,(int)$imageInfo[1])) {
                    throw new RuntimeException('Format foto tidak valid. Gunakan gambar JPEG, PNG, atau WebP yang dapat dibaca.');
                }
                $photoUploads[] = [
                    'temporary_path'=>$temporaryPath,
                    'extension'=>$photoTypes[$detectedMime],
                    'mime_type'=>$detectedMime,
                    'size'=>$fileBytes,
                ];
            }
        }
        if (($damagedQuantity > 0 || $mismatchQuantity > 0) && !$photoUploads) {
            throw new RuntimeException('Unggah minimal satu foto bukti bila terdapat barang rusak atau tidak sesuai.');
        }

        $pdo->beginTransaction();
        $requestQuery = $pdo->prepare('SELECT r.id,r.user_id,r.barang_id,r.jumlah,r.status,requester.role role_pengaju,requester.gudang_id gudang_pengaju_id FROM permintaan_pembelian r JOIN users requester ON requester.id=r.user_id WHERE r.id=? FOR UPDATE');
        $requestQuery->execute([$requestId]);
        $purchaseRequest = $requestQuery->fetch();
        if (!$purchaseRequest || $purchaseRequest['status'] !== 'disetujui') {
            throw new RuntimeException('Penerimaan hanya dapat dicatat untuk permintaan pembelian yang disetujui.');
        }

        if (user()['role'] === 'admin_pusat') {
            if ($purchaseRequest['role_pengaju'] !== 'admin_pusat' || $purchaseRequest['gudang_pengaju_id'] !== null) {
                throw new RuntimeException('Admin pusat hanya dapat menerima pengajuan yang ditujukan ke Gudang Pusat.');
            }
            $warehouseId = $centerId($pdo);
            $warehouseQuery = $pdo->prepare("SELECT id,nama FROM gudang WHERE id=? AND jenis='pusat' AND aktif=1 FOR UPDATE");
        } else {
            $warehouseId = (int)user()['gudang_id'];
            if ($purchaseRequest['role_pengaju'] !== 'admin_gudang' || (int)$purchaseRequest['gudang_pengaju_id'] !== $warehouseId) {
                throw new RuntimeException('Admin proyek hanya dapat menerima pengajuan untuk gudang proyek yang ditautkan ke akunnya.');
            }
            $warehouseQuery = $pdo->prepare("SELECT id,nama FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 FOR UPDATE");
        }
        $warehouseQuery->execute([$warehouseId]);
        $warehouse = $warehouseQuery->fetch();
        if (!$warehouse) throw new RuntimeException('Gudang tujuan akun ini tidak aktif atau tidak ditemukan.');

        $itemQuery = $pdo->prepare('SELECT id,kode,nama,satuan FROM barang WHERE id=? AND aktif=1 FOR UPDATE');
        $itemQuery->execute([(int)$purchaseRequest['barang_id']]);
        $item = $itemQuery->fetch();
        if (!$item) throw new RuntimeException('Barang pengajuan tidak aktif. Aktifkan kembali barang sebelum mencatat penerimaan.');

        $tokenQuery = $pdo->prepare('SELECT id FROM penerimaan_pembelian WHERE token_form=? LIMIT 1');
        $tokenQuery->execute([$formToken]);
        if ($tokenQuery->fetchColumn()) throw new RuntimeException('Form penerimaan ini sudah pernah disimpan. Muat ulang halaman untuk membuat pencatatan baru.');

        $receivedQuery = $pdo->prepare('SELECT COALESCE(SUM(jumlah_diterima),0) FROM penerimaan_pembelian WHERE permintaan_pembelian_id=?');
        $receivedQuery->execute([$requestId]);
        $alreadyReceived = (int)$receivedQuery->fetchColumn();
        $remaining = (int)$purchaseRequest['jumlah'] - $alreadyReceived;
        if ($totalReceived > $remaining) {
            throw new RuntimeException('Jumlah penerimaan melebihi sisa pesanan sebanyak '.number_format(max(0,$remaining)).'. Muat ulang halaman dan periksa jumlah terbaru.');
        }

        $stock = 0;
        if ($goodQuantity > 0) {
            $stock = $lockStock($pdo,$warehouseId,(int)$item['id']);
            if ($stock + $goodQuantity > 4294967295) throw new RuntimeException('Jumlah layak diterima melebihi batas stok gudang.');
            $pdo->prepare('UPDATE stok_gudang SET stok=stok+? WHERE gudang_id=? AND barang_id=?')
                ->execute([$goodQuantity,$warehouseId,(int)$item['id']]);
        }

        $insertReceipt = $pdo->prepare('INSERT INTO penerimaan_pembelian
            (permintaan_pembelian_id,barang_id,gudang_id,dicatat_oleh,token_form,kode_barang,nama_barang,satuan_barang,nama_gudang,nama_petugas,jumlah_diterima,jumlah_layak,jumlah_rusak,jumlah_tidak_sesuai,catatan_kondisi,diterima_pada)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insertReceipt->execute([
            $requestId,(int)$item['id'],$warehouseId,user()['id'],$formToken,$item['kode'],$item['nama'],$item['satuan'],
            $warehouse['nama'],user()['nama'],$totalReceived,$goodQuantity,$damagedQuantity,$mismatchQuantity,
            $conditionNote ?: null,$receivedDateValue,
        ]);
        $receiptId = (int)$pdo->lastInsertId();

        if ($photoUploads) {
            $storageDirectory = receipt_photo_storage_directory(true);
            $insertPhoto = $pdo->prepare('INSERT INTO penerimaan_pembelian_foto (penerimaan_pembelian_id,lokasi_file,mime_type,ukuran_bytes,urutan) VALUES (?,?,?,?,?)');
            foreach ($photoUploads as $photoIndex => $photo) {
                $fileKey = bin2hex(random_bytes(32)).'.'.$photo['extension'];
                $destinationPath = $storageDirectory.DIRECTORY_SEPARATOR.$fileKey;
                if (file_exists($destinationPath) || !move_uploaded_file($photo['temporary_path'],$destinationPath)) {
                    throw new RuntimeException('Foto bukti gagal disimpan. Penerimaan dan stok dibatalkan; silakan coba kembali.');
                }
                $newReceiptPhotoPaths[] = $destinationPath;
                @chmod($destinationPath,0600);
                $insertPhoto->execute([$receiptId,$fileKey,$photo['mime_type'],$photo['size'],$photoIndex + 1]);
            }
        }

        if ($goodQuantity > 0) {
            $movementId = $recordMovement($pdo,[
                'barang_id'=>(int)$item['id'],'gudang_id'=>$warehouseId,'gudang_tujuan_id'=>$warehouseId,
                'user_id'=>user()['id'],'nama_petugas'=>user()['nama'],'jenis'=>'masuk','jumlah'=>$goodQuantity,
                'referensi'=>'Penerimaan pembelian #'.$requestId.' (penerimaan #'.$receiptId.')',
                'satuan_transaksi'=>$item['satuan'],'tanggal'=>$receivedDateValue,
            ]);
            $pdo->prepare('UPDATE penerimaan_pembelian SET transaksi_gudang_id=? WHERE id=?')
                ->execute([$movementId,$receiptId]);
        }

        $pdo->commit();
        $newReceiptPhotoPaths = [];
        $remaining -= $totalReceived;
        $message = 'Penerimaan #'.$receiptId.' berhasil dicatat. '.number_format($goodQuantity).' '.(string)$item['satuan'].' layak masuk stok.';
        if ($damagedQuantity > 0 || $mismatchQuantity > 0) {
            $message .= ' Rusak: '.number_format($damagedQuantity).'; tidak sesuai: '.number_format($mismatchQuantity).' — tidak ditambahkan ke stok.';
        }
        if ($photoUploads) $message .= ' Foto bukti: '.count($photoUploads).' file tersimpan.';
        $message .= ' Sisa belum diterima: '.number_format($remaining).'.';
        flash($message);
    } elseif ($aksi === 'permintaan_simpan') {
        require_role('admin_gudang');
        $warehouseId = (int)user()['gudang_id'];
        $centralId = $centerId($pdo);
        $itemId = $positiveInt($post['barang_id'] ?? null);
        $quantity = $positiveInt($post['jumlah'] ?? null);
        $note = $text('catatan');
        if ($itemId < 1 || $quantity < 1 || $quantity > 4294967295 || $textLength($note) > 2000) {
            throw new RuntimeException('Barang, jumlah, atau catatan permintaan tidak valid.');
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT id FROM barang WHERE id=? AND aktif=1 FOR UPDATE');
        $q->execute([$itemId]);
        if (!$q->fetchColumn()) throw new RuntimeException('Barang tidak ditemukan atau sudah nonaktif.');
        $q = $pdo->prepare("INSERT INTO permintaan (user_id,kategori_barang,status,catatan,gudang_asal_id,gudang_tujuan_id,nama_proyek) VALUES (?,'barang_siap','diajukan',?,?,?,(SELECT nama FROM gudang WHERE id=?))");
        $q->execute([user()['id'],$note ?: null,$centralId,$warehouseId,$warehouseId]);
        $requestId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO detail_permintaan (permintaan_id,barang_id,jumlah) VALUES (?,?,?)')
            ->execute([$requestId,$itemId,$quantity]);
        app(WarehouseNotifications::class)->notify($pdo, 'admin_pusat', null, 'project-request:'.$requestId, 'Permintaan barang #'.$requestId.' dari gudang proyek menunggu diproses.', 'dashboard.php#permintaan');
        $pdo->commit();
        flash('Permintaan barang dikirim ke Gudang Pusat.');
    } elseif ($aksi === 'permintaan_kirim' || $aksi === 'permintaan_tolak') {
        require_role('admin_pusat');
        $warehouseId = $centerId($pdo);
        $requestId = $positiveInt($post['permintaan_id'] ?? null);
        if ($requestId < 1) throw new RuntimeException('Permintaan tidak ditemukan.');
        $vehicle = strtoupper($text('nomor_kendaraan'));
        $driver = $text('nama_driver');
        if ($aksi === 'permintaan_kirim' && ($vehicle === '' || $textLength($vehicle) > 40 || $driver === '' || $textLength($driver) > 120)) {
            throw new RuntimeException('Nomor kendaraan dan nama sopir wajib diisi dengan benar sebelum mengirim barang.');
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT id,status,gudang_tujuan_id FROM permintaan WHERE id=? AND gudang_asal_id=? AND gudang_tujuan_id IS NOT NULL AND status='diajukan' FOR UPDATE");
        $q->execute([$requestId,$warehouseId]); $request = $q->fetch();
        if (!$request) throw new RuntimeException('Permintaan sudah diproses atau tidak ditemukan.');
        if ($aksi === 'permintaan_tolak') {
            $pdo->prepare("UPDATE permintaan SET status='ditolak',diproses_oleh=?,diproses_pada=NOW() WHERE id=? AND status='diajukan'")
                ->execute([user()['id'],$requestId]);
            $pdo->commit();
            flash('Permintaan proyek ditolak.');
        } else {
            $q = $pdo->prepare('SELECT dp.id detail_id,dp.barang_id,dp.jumlah,b.nama,b.satuan FROM detail_permintaan dp JOIN barang b ON b.id=dp.barang_id WHERE dp.permintaan_id=? AND b.aktif=1 ORDER BY dp.barang_id FOR UPDATE');
            $q->execute([$requestId]); $items = $q->fetchAll();
            $q = $pdo->prepare('SELECT COUNT(*) FROM detail_permintaan WHERE permintaan_id=?');
            $q->execute([$requestId]);
            if (!$items || count($items) !== (int)$q->fetchColumn()) throw new RuntimeException('Rincian permintaan tidak lengkap atau terdapat barang nonaktif.');
            foreach ($items as &$item) {
                $item['stok'] = $lockStock($pdo,$warehouseId,(int)$item['barang_id']);
                if ((int)$item['stok'] < (int)$item['jumlah']) {
                    throw new RuntimeException('Stok Gudang Pusat untuk '.$item['nama'].' tidak mencukupi.');
                }
            }
            unset($item);
            $pdo->prepare('INSERT INTO pengiriman (permintaan_id,gudang_asal_id,gudang_tujuan_id,status,dikirim_oleh,dikirim_pada) VALUES (?,?,?,\'dalam_perjalanan\',?,NOW())')
                ->execute([$requestId,$warehouseId,(int)$request['gudang_tujuan_id'],user()['id']]);
            $shipmentId = (int)$pdo->lastInsertId();
            $insertDetail = $pdo->prepare('INSERT INTO detail_pengiriman (pengiriman_id,detail_permintaan_id,barang_id,jumlah_dikirim) VALUES (?,?,?,?)');
            $decrement = $pdo->prepare('UPDATE stok_gudang SET stok=stok-? WHERE gudang_id=? AND barang_id=? AND stok>=?');
            foreach ($items as $item) {
                $decrement->execute([$item['jumlah'],$warehouseId,$item['barang_id'],$item['jumlah']]);
                if ($decrement->rowCount() !== 1) throw new RuntimeException('Stok pusat berubah atau tidak mencukupi. Muat ulang halaman.');
                $insertDetail->execute([$shipmentId,$item['detail_id'],$item['barang_id'],$item['jumlah']]);
                $recordMovement($pdo,[
                    'barang_id'=>$item['barang_id'],'gudang_id'=>$warehouseId,
                    'gudang_asal_id'=>$warehouseId,'gudang_tujuan_id'=>$request['gudang_tujuan_id'],
                    'permintaan_id'=>$requestId,'pengiriman_id'=>$shipmentId,'user_id'=>user()['id'],
                    'jenis'=>'keluar','jumlah'=>$item['jumlah'],'referensi'=>'Pengiriman #'.$shipmentId,
                    'tujuan_keluar'=>'proyek','satuan_transaksi'=>$item['satuan'],
                ]);
            }
            $pdo->prepare("UPDATE permintaan SET status='diproses',diproses_oleh=?,diproses_pada=NOW(),nomor_kendaraan=?,nama_driver=? WHERE id=? AND status='diajukan'")
                ->execute([user()['id'],$vehicle,$driver,$requestId]);
            app(WarehouseNotifications::class)->notify($pdo, 'admin_gudang', (int)$request['gudang_tujuan_id'], 'shipment-sent:'.$shipmentId, 'Pengiriman #'.$shipmentId.' dari Gudang Pusat sedang dalam perjalanan.', 'dashboard.php#pengiriman');
            $pdo->commit();
            flash('Pengiriman dicatat. Stok Gudang Pusat berkurang dan status menjadi dalam perjalanan.');
        }
    } elseif ($aksi === 'surat_jalan_transport_simpan') {
        require_role('admin_pusat');
        $shipmentId = $positiveInt($post['pengiriman_id'] ?? null);
        $vehicle = strtoupper($text('nomor_kendaraan'));
        $driver = $text('nama_driver');
        if ($shipmentId < 1 || $vehicle === '' || $textLength($vehicle) > 40 || $driver === '' || $textLength($driver) > 120) {
            throw new RuntimeException('Nomor kendaraan dan nama sopir wajib diisi dengan benar.');
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT pe.permintaan_id FROM pengiriman pe JOIN gudang ga ON ga.id=pe.gudang_asal_id JOIN gudang gt ON gt.id=pe.gudang_tujuan_id WHERE pe.id=? AND ga.kode='PUSAT' AND ga.jenis='pusat' AND gt.jenis='proyek' FOR UPDATE");
        $q->execute([$shipmentId]);
        $requestId = $q->fetchColumn();
        if ($requestId === false) throw new RuntimeException('Pengiriman tidak ditemukan.');
        $pdo->prepare('UPDATE permintaan SET nomor_kendaraan=?,nama_driver=? WHERE id=?')->execute([$vehicle,$driver,$requestId]);
        $pdo->commit();
        flash('Data kendaraan dan sopir pada surat jalan disimpan.');
        go('surat_jalan.php?id='.$shipmentId);
    } elseif ($aksi === 'penerimaan_konfirmasi') {
        require_role('admin_gudang');
        $warehouseId = (int)user()['gudang_id'];
        $shipmentId = $positiveInt($post['pengiriman_id'] ?? null);
        $postedQuantities = $post['jumlah_diterima'] ?? null;
        $note = $text('catatan_penerimaan');
        if ($shipmentId < 1 || !is_array($postedQuantities) || $textLength($note) > 2000) {
            throw new RuntimeException('Data penerimaan tidak valid.');
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT id,permintaan_id,gudang_asal_id,gudang_tujuan_id,status FROM pengiriman WHERE id=? AND gudang_tujuan_id=? AND status='dalam_perjalanan' FOR UPDATE");
        $q->execute([$shipmentId,$warehouseId]); $shipment = $q->fetch();
        if (!$shipment) throw new RuntimeException('Pengiriman tidak ditemukan atau sudah dikonfirmasi.');
        $q = $pdo->prepare('SELECT id,barang_id,jumlah_dikirim FROM detail_pengiriman WHERE pengiriman_id=? ORDER BY barang_id FOR UPDATE');
        $q->execute([$shipmentId]); $items = $q->fetchAll();
        if (!$items || count($postedQuantities) !== count($items)) throw new RuntimeException('Semua jumlah barang diterima harus dicatat.');
        foreach ($items as $item) {
            $key = (string)$item['id'];
            $value = $postedQuantities[$key] ?? null;
            if (!is_string($value) || !ctype_digit($value) || (int)$value > (int)$item['jumlah_dikirim']) {
                throw new RuntimeException('Jumlah diterima harus berupa bilangan 0 sampai jumlah yang dikirim.');
            }
        }
        $partial = false;
        $updateDetail = $pdo->prepare('UPDATE detail_pengiriman SET jumlah_diterima=? WHERE id=? AND jumlah_diterima IS NULL');
        foreach ($items as $item) {
            $received = (int)$postedQuantities[(string)$item['id']];
            $partial = $partial || $received !== (int)$item['jumlah_dikirim'];
            $stock = $lockStock($pdo,$warehouseId,(int)$item['barang_id']);
            if ($stock + $received > 4294967295) throw new RuntimeException('Jumlah diterima melebihi batas stok gudang.');
            $pdo->prepare('UPDATE stok_gudang SET stok=stok+? WHERE gudang_id=? AND barang_id=?')
                ->execute([$received,$warehouseId,$item['barang_id']]);
            $updateDetail->execute([$received,$item['id']]);
            if ($updateDetail->rowCount() !== 1) throw new RuntimeException('Rincian penerimaan sudah pernah dicatat.');
            $unitQ = $pdo->prepare('SELECT satuan FROM barang WHERE id=?');
            $unitQ->execute([$item['barang_id']]); $unit = (string)$unitQ->fetchColumn();
            $recordMovement($pdo,[
                'barang_id'=>$item['barang_id'],'gudang_id'=>$warehouseId,
                'gudang_asal_id'=>$shipment['gudang_asal_id'],'gudang_tujuan_id'=>$warehouseId,
                'permintaan_id'=>$shipment['permintaan_id'],'pengiriman_id'=>$shipmentId,
                'user_id'=>user()['id'],'jenis'=>'masuk','jumlah'=>$received,
                'referensi'=>'Penerimaan pengiriman #'.$shipmentId,'satuan_transaksi'=>$unit,
            ]);
        }
        $shipmentStatus = $partial ? 'diterima_dengan_selisih' : 'diterima';
        $pdo->prepare('UPDATE pengiriman SET status=?,diterima_oleh=?,diterima_pada=NOW(),catatan_penerimaan=? WHERE id=? AND status=\'dalam_perjalanan\'')
            ->execute([$shipmentStatus,user()['id'],$note ?: null,$shipmentId]);
        $pdo->prepare("UPDATE permintaan SET status='selesai',selesai_terkirim_pada=NOW(),selesai_terkirim_oleh=? WHERE id=? AND status='diproses'")
            ->execute([user()['id'],$shipment['permintaan_id']]);
        app(WarehouseNotifications::class)->notify($pdo, 'admin_pusat', null, 'shipment-received:'.$shipmentId, 'Pengiriman #'.$shipmentId.' telah dikonfirmasi sampai di gudang proyek'.($partial ? ' dengan selisih jumlah.' : '.'), 'dashboard.php#pengiriman');
        $pdo->commit();
        flash($partial ? 'Penerimaan dengan selisih dicatat. Stok proyek bertambah sesuai jumlah yang benar-benar diterima.' : 'Penerimaan barang dikonfirmasi dan stok proyek diperbarui.');
    } elseif ($aksi === 'pemakaian_simpan') {
        require_role('admin_gudang');
        $warehouseId = (int)user()['gudang_id'];
        $itemId = $positiveInt($post['barang_id'] ?? null);
        $quantity = $positiveInt($post['jumlah'] ?? null);
        $note = $text('catatan');
        if ($itemId < 1 || $quantity < 1 || $textLength($note) > 255) throw new RuntimeException('Barang, jumlah, atau keterangan pemakaian tidak valid.');
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT id,satuan FROM barang WHERE id=? AND aktif=1 FOR UPDATE');
        $q->execute([$itemId]); $item = $q->fetch();
        if (!$item) throw new RuntimeException('Barang tidak ditemukan atau nonaktif.');
        $stock = $lockStock($pdo,$warehouseId,$itemId);
        if ($quantity > $stock) throw new RuntimeException('Stok gudang proyek tidak cukup.');
        $pdo->prepare('UPDATE stok_gudang SET stok=stok-? WHERE gudang_id=? AND barang_id=? AND stok>=?')
            ->execute([$quantity,$warehouseId,$itemId,$quantity]);
        $recordMovement($pdo,[
            'barang_id'=>$itemId,'gudang_id'=>$warehouseId,'gudang_asal_id'=>$warehouseId,
            'user_id'=>user()['id'],'jenis'=>'pemakaian','jumlah'=>$quantity,
            'referensi'=>$note ?: 'Pemakaian barang proyek','satuan_transaksi'=>$item['satuan'],
        ]);
        $pdo->commit();
        flash('Pemakaian dicatat dan stok proyek dikurangi.');
    } elseif ($aksi === 'penambahan_proyek_simpan') {
        require_role('admin_gudang');
        $warehouseId = (int)user()['gudang_id'];
        $itemId = $positiveInt($post['barang_id'] ?? null);
        $quantity = $positiveInt($post['jumlah'] ?? null);
        $note = $text('catatan_penambahan');
        if ($itemId < 1 || $quantity < 1 || $quantity > 4294967295 || $note === '' || $textLength($note) > 255) {
            throw new RuntimeException('Barang, jumlah, dan keterangan sumber wajib diisi dengan benar.');
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT nama FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 FOR UPDATE");
        $q->execute([$warehouseId]); $warehouseName = $q->fetchColumn();
        if ($warehouseName === false) throw new RuntimeException('Gudang proyek akun ini tidak aktif atau tidak ditemukan.');
        $q = $pdo->prepare('SELECT id,kode,nama,satuan FROM barang WHERE id=? AND aktif=1 FOR UPDATE');
        $q->execute([$itemId]); $item = $q->fetch();
        if (!$item) throw new RuntimeException('Barang tidak ditemukan atau sudah nonaktif.');
        $stock = $lockStock($pdo,$warehouseId,$itemId);
        if ($stock + $quantity > 4294967295) throw new RuntimeException('Jumlah melebihi batas stok proyek yang dapat disimpan.');
        $pdo->prepare('UPDATE stok_gudang SET stok=stok+? WHERE gudang_id=? AND barang_id=?')
            ->execute([$quantity,$warehouseId,$itemId]);
        $movementId = $recordMovement($pdo,[
            'barang_id'=>$itemId,'gudang_id'=>$warehouseId,'gudang_tujuan_id'=>$warehouseId,
            'user_id'=>user()['id'],'jenis'=>'masuk','jumlah'=>$quantity,
            'referensi'=>'Penambahan barang proyek','satuan_transaksi'=>$item['satuan'],
        ]);
        $pdo->prepare('INSERT INTO penambahan_barang_proyek (gudang_id,barang_id,kode_barang,nama_barang,satuan_barang,nama_proyek,nama_penambah,jumlah,catatan,user_id,transaksi_gudang_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$warehouseId,$itemId,$item['kode'],$item['nama'],$item['satuan'],$warehouseName,user()['nama'],$quantity,$note,user()['id'],$movementId]);
        $pdo->commit();
        flash('Penambahan barang proyek dicatat dan stok proyek diperbarui.');
    } elseif ($aksi === 'peralatan_kirim_kembali') {
        require_role('admin_gudang');
        $warehouseId = (int)user()['gudang_id'];
        $itemId = $positiveInt($post['barang_id'] ?? null);
        $quantity = $positiveInt($post['jumlah'] ?? null);
        $condition = $text('catatan_kondisi');
        if ($itemId < 1 || $quantity < 1 || $quantity > 4294967295 || $condition === '' || $textLength($condition) > 2000) {
            throw new RuntimeException('Peralatan, jumlah, dan catatan kondisi wajib diisi dengan benar.');
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT nama FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 FOR UPDATE");
        $q->execute([$warehouseId]); $warehouseName = $q->fetchColumn();
        if ($warehouseName === false) throw new RuntimeException('Gudang proyek akun ini tidak aktif atau tidak ditemukan.');
        $q = $pdo->prepare("SELECT id,kode,nama,satuan FROM barang WHERE id=? AND aktif=1 AND kategori_barang='peralatan' FOR UPDATE");
        $q->execute([$itemId]); $item = $q->fetch();
        if (!$item) throw new RuntimeException('Peralatan tidak ditemukan, nonaktif, atau bukan kategori peralatan.');
        $stock = $lockStock($pdo,$warehouseId,$itemId);
        if ($quantity > $stock) throw new RuntimeException('Stok peralatan proyek tidak mencukupi.');
        $decrement = $pdo->prepare('UPDATE stok_gudang SET stok=stok-? WHERE gudang_id=? AND barang_id=? AND stok>=?');
        $decrement->execute([$quantity,$warehouseId,$itemId,$quantity]);
        if ($decrement->rowCount() !== 1) throw new RuntimeException('Stok peralatan berubah. Muat ulang halaman dan coba kembali.');
        $pdo->prepare("INSERT INTO pengembalian_peralatan_proyek (barang_id,kode_barang,nama_barang,satuan_barang,nama_proyek,nama_admin_proyek,gudang_asal_id,jumlah,catatan_kondisi,status,dikirim_oleh,dikirim_pada) VALUES (?,?,?,?,?,?, ?,?,?,'menunggu_diterima',?,NOW())")
            ->execute([$itemId,$item['kode'],$item['nama'],$item['satuan'],$warehouseName,user()['nama'],$warehouseId,$quantity,$condition,user()['id']]);
        $returnId = (int)$pdo->lastInsertId();
        $movementId = $recordMovement($pdo,[
            'barang_id'=>$itemId,'gudang_id'=>$warehouseId,'gudang_asal_id'=>$warehouseId,
            'gudang_tujuan_id'=>$centerId($pdo),'user_id'=>user()['id'],'jenis'=>'keluar',
            'jumlah'=>$quantity,'referensi'=>'Pengiriman kembali peralatan #'.$returnId,
            'tujuan_keluar'=>'proyek','satuan_transaksi'=>$item['satuan'],
        ]);
        $pdo->prepare('UPDATE pengembalian_peralatan_proyek SET transaksi_keluar_id=? WHERE id=?')
            ->execute([$movementId,$returnId]);
        $pdo->commit();
        flash('Peralatan dikirim kembali. Status menunggu diterima admin pusat.');
    } elseif ($aksi === 'peralatan_kembali_konfirmasi') {
        require_role('admin_pusat');
        $warehouseId = $centerId($pdo);
        $returnId = $positiveInt($post['pengembalian_peralatan_id'] ?? null);
        if ($returnId < 1) throw new RuntimeException('Riwayat pengembalian peralatan tidak ditemukan.');
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT id,barang_id,satuan_barang,gudang_asal_id,jumlah,status FROM pengembalian_peralatan_proyek WHERE id=? AND status='menunggu_diterima' FOR UPDATE");
        $q->execute([$returnId]); $return = $q->fetch();
        if (!$return) throw new RuntimeException('Pengembalian peralatan tidak ditemukan atau sudah diterima.');
        $q = $pdo->prepare("SELECT id,satuan FROM barang WHERE id=? AND aktif=1 AND kategori_barang='peralatan' FOR UPDATE");
        $q->execute([(int)$return['barang_id']]); $item = $q->fetch();
        if (!$item) throw new RuntimeException('Peralatan nonaktif atau tidak lagi terdaftar sebagai peralatan.');
        $stock = $lockStock($pdo,$warehouseId,(int)$return['barang_id']);
        if ($stock + (int)$return['jumlah'] > 4294967295) throw new RuntimeException('Jumlah melebihi batas stok pusat yang dapat disimpan.');
        $pdo->prepare('UPDATE stok_gudang SET stok=stok+? WHERE gudang_id=? AND barang_id=?')
            ->execute([(int)$return['jumlah'],$warehouseId,(int)$return['barang_id']]);
        $movementId = $recordMovement($pdo,[
            'barang_id'=>$return['barang_id'],'gudang_id'=>$warehouseId,
            'gudang_asal_id'=>$return['gudang_asal_id'],'gudang_tujuan_id'=>$warehouseId,
            'user_id'=>user()['id'],'jenis'=>'kembali','jumlah'=>$return['jumlah'],
            'referensi'=>'Peralatan kembali dari proyek #'.$returnId,
            'satuan_transaksi'=>$return['satuan_barang'],
        ]);
        $update = $pdo->prepare("UPDATE pengembalian_peralatan_proyek SET status='diterima',diterima_oleh=?,diterima_pada=NOW(),transaksi_kembali_id=? WHERE id=? AND status='menunggu_diterima'");
        $update->execute([user()['id'],$movementId,$returnId]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Status pengembalian berubah. Muat ulang halaman.');
        $pdo->commit();
        flash('Penerimaan peralatan dikonfirmasi dan stok Gudang Pusat diperbarui.');
    } elseif ($aksi === 'barang_simpan') {
        require_role('admin_pusat');
        $warehouseId = $centerId($pdo);
        $name = $text('nama');
        $category = $text('kategori');
        $itemCategory = $text('kategori_barang');
        $unit = $text('satuan');
        $initialStockRaw = $post['stok_awal'] ?? '0';
        if (!is_string($initialStockRaw)) throw new RuntimeException('Jumlah stok awal tidak valid.');
        $initialStockRaw = trim($initialStockRaw);
        if ($initialStockRaw !== '' && !ctype_digit($initialStockRaw)) throw new RuntimeException('Jumlah stok awal harus berupa bilangan bulat.');
        $initialStock = $initialStockRaw === '' ? 0 : (int)$initialStockRaw;
        $allowedCategories = ['barang_siap','barang_dipakai','peralatan'];
        if ($name === '' || $textLength($name) > 160 ||
            $category === '' || $textLength($category) > 100 || !in_array($itemCategory,$allowedCategories,true) ||
            $unit === '' || $textLength($unit) > 30 || $initialStock < 0 || $initialStock > 4294967295) {
            throw new RuntimeException('Nama, kategori, jenis, satuan, atau stok awal barang tidak valid.');
        }
        $pdo->beginTransaction();
        $code = 'TMP-'.bin2hex(random_bytes(16));
        $pdo->prepare('INSERT INTO barang (kode,nama,kategori,kategori_barang,satuan) VALUES (?,?,?,?,?)')
            ->execute([$code,$name,$category,$itemCategory,$unit]);
        $itemId = (int)$pdo->lastInsertId();
        $code = $automaticItemCode($pdo,$itemCategory,$itemId);
        $pdo->prepare('UPDATE barang SET kode=? WHERE id=?')->execute([$code,$itemId]);
        $pdo->prepare('INSERT INTO stok_gudang (gudang_id,barang_id,stok) VALUES (?,?,?)')
            ->execute([$warehouseId,$itemId,$initialStock]);
        if ($initialStock > 0) {
            $recordMovement($pdo,[
                'barang_id'=>$itemId,'gudang_id'=>$warehouseId,'gudang_tujuan_id'=>$warehouseId,
                'user_id'=>user()['id'],'jenis'=>'masuk','jumlah'=>$initialStock,
                'referensi'=>'Stok awal dari katalog','satuan_transaksi'=>$unit,
            ]);
        }
        $pdo->commit();
        flash($initialStock > 0 ? 'Barang ditambahkan. Stok awal Gudang Pusat dan mutasinya sudah dicatat.' : 'Barang ditambahkan ke katalog dengan stok awal nol.');
    } elseif ($aksi === 'barang_ubah') {
        require_role('admin_pusat');
        $itemId = $positiveInt($post['barang_id'] ?? null);
        $name = $text('nama');
        $category = $text('kategori');
        $itemCategory = $text('kategori_barang');
        $unit = $text('satuan');
        $stockRaw = $post['stok'] ?? null;
        if (!is_string($stockRaw) || !ctype_digit($stockRaw)) throw new RuntimeException('Stok Gudang Pusat harus berupa bilangan bulat.');
        $targetStock = (int)$stockRaw;
        $allowedCategories = ['barang_siap','barang_dipakai','peralatan'];
        if ($itemId < 1 || $name === '' || $textLength($name) > 160 ||
            $category === '' || $textLength($category) > 100 || !in_array($itemCategory,$allowedCategories,true) ||
            $unit === '' || $textLength($unit) > 30 || $targetStock < 0 || $targetStock > 4294967295) {
            throw new RuntimeException('Data barang harus diisi dengan benar.');
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT kode,kategori_barang,satuan FROM barang WHERE id=? FOR UPDATE');
        $q->execute([$itemId]); $item = $q->fetch();
        if (!$item) throw new RuntimeException('Barang tidak ditemukan.');
        if ($itemCategory !== 'peralatan') {
            $q = $pdo->prepare("SELECT COUNT(*) FROM pengembalian_peralatan_proyek WHERE barang_id=? AND status='menunggu_diterima'");
            $q->execute([$itemId]);
            if ((int)$q->fetchColumn() > 0) throw new RuntimeException('Jenis barang tidak dapat diubah selama pengembalian peralatan masih menunggu penerimaan.');
        }
        $currentStock = $lockStock($pdo,$centerId($pdo),$itemId);
        $unitChanged = $unit !== $item['satuan'];
        $code = $itemCategory === $item['kategori_barang'] ? $item['kode'] : $automaticItemCode($pdo,$itemCategory,$itemId);
        $pdo->prepare('UPDATE barang SET kode=?,nama=?,kategori=?,kategori_barang=?,satuan=? WHERE id=?')
            ->execute([$code,$name,$category,$itemCategory,$unit,$itemId]);
        if ($unitChanged) {
            $pdo->prepare("UPDATE pengembalian_peralatan_proyek SET satuan_barang=? WHERE barang_id=? AND status='menunggu_diterima'")
                ->execute([$unit,$itemId]);
        }
        $warehouseId = $centerId($pdo);
        $pdo->prepare('UPDATE stok_gudang SET stok=? WHERE gudang_id=? AND barang_id=?')
            ->execute([$targetStock,$warehouseId,$itemId]);
        $stockDifference = $targetStock - $currentStock;
        if ($stockDifference !== 0) {
            $isIncrease = $stockDifference > 0;
            $recordMovement($pdo,[
                'barang_id'=>$itemId,'gudang_id'=>$warehouseId,
                'gudang_asal_id'=>$isIncrease ? null : $warehouseId,
                'gudang_tujuan_id'=>$isIncrease ? $warehouseId : null,
                'user_id'=>user()['id'],'jenis'=>$isIncrease ? 'masuk' : 'keluar',
                'jumlah'=>abs($stockDifference),'referensi'=>'Penyesuaian stok melalui katalog',
                'tujuan_keluar'=>$isIncrease ? null : 'internal','satuan_transaksi'=>$unit,
            ]);
        }
        $pdo->commit();
        flash($unitChanged
            ? 'Data barang diperbarui. Angka stok dan jumlah yang sedang berjalan tetap; semuanya sekarang memakai satuan baru. Mutasi lama tetap memakai satuan saat dicatat.'
            : ($stockDifference === 0 ? 'Data barang diperbarui. Stok Gudang Pusat tidak berubah.' : 'Data barang dan stok Gudang Pusat diperbarui; mutasi penyesuaian sudah dicatat.'));
    } elseif (in_array($aksi,['barang_hapus','barang_aktif','barang_delete'],true)) {
        require_role('admin_pusat');
        $itemId = $positiveInt($post['barang_id'] ?? null);
        if ($itemId < 1) throw new RuntimeException('Barang tidak ditemukan.');
        if ($aksi === 'barang_aktif') {
            $pdo->prepare('UPDATE barang SET aktif=1 WHERE id=?')->execute([$itemId]);
            flash('Barang diaktifkan kembali.');
        } elseif ($aksi === 'barang_hapus') {
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT id FROM barang WHERE id=? FOR UPDATE');
            $q->execute([$itemId]);
            if (!$q->fetchColumn()) throw new RuntimeException('Barang tidak ditemukan.');
            if ($hasOpenPurchaseRequest($pdo,$itemId)) throw new RuntimeException('Barang masih memiliki pengajuan pembelian menunggu atau jumlah disetujui yang belum diterima. Selesaikan atau tolak pengajuan sebelum menonaktifkan barang.');
            $q = $pdo->prepare('SELECT stok FROM stok_gudang WHERE barang_id=? FOR UPDATE');
            $q->execute([$itemId]);
            $stockRows = $q->fetchAll(PDO::FETCH_COLUMN);
            $q = $pdo->prepare("SELECT COUNT(*) FROM detail_pengiriman d JOIN pengiriman p ON p.id=d.pengiriman_id WHERE d.barang_id=? AND p.status='dalam_perjalanan'");
            $q->execute([$itemId]);
            $inTransit = (int)$q->fetchColumn();
            $q = $pdo->prepare("SELECT COUNT(*) FROM pengembalian_peralatan_proyek WHERE barang_id=? AND status='menunggu_diterima'");
            $q->execute([$itemId]);
            $inTransit += (int)$q->fetchColumn();
            if (array_sum(array_map('intval',$stockRows)) > 0 || $inTransit > 0) throw new RuntimeException('Stok tersedia atau barang dalam perjalanan masih ada di gudang.');
            $pdo->prepare('UPDATE barang SET aktif=0 WHERE id=?')->execute([$itemId]);
            $pdo->commit();
            flash('Barang dinonaktifkan dari katalog.');
        } else {
            $note = $text('catatan_penghapusan');
            if ($note === '' || $textLength($note) > 1000) throw new RuntimeException('Catatan penghapusan wajib diisi dan maksimal 1.000 karakter.');
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT id,aktif FROM barang WHERE id=? FOR UPDATE');
            $q->execute([$itemId]); $item = $q->fetch();
            if (!$item || !(int)$item['aktif']) throw new RuntimeException('Barang tidak ditemukan atau sudah nonaktif.');
            if ($hasOpenPurchaseRequest($pdo,$itemId)) throw new RuntimeException('Barang masih memiliki pengajuan pembelian menunggu atau jumlah disetujui yang belum diterima. Selesaikan atau tolak pengajuan sebelum mengarsipkan barang.');
            $q = $pdo->prepare('SELECT stok FROM stok_gudang WHERE barang_id=? FOR UPDATE');
            $q->execute([$itemId]);
            $stockTotal = array_sum(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)));
            $q = $pdo->prepare("SELECT COUNT(*) FROM detail_pengiriman d JOIN pengiriman p ON p.id=d.pengiriman_id WHERE d.barang_id=? AND p.status='dalam_perjalanan'");
            $q->execute([$itemId]);
            $inTransit = (int)$q->fetchColumn();
            $q = $pdo->prepare("SELECT COUNT(*) FROM pengembalian_peralatan_proyek WHERE barang_id=? AND status='menunggu_diterima'");
            $q->execute([$itemId]);
            $inTransit += (int)$q->fetchColumn();
            if ($stockTotal > 0 || $inTransit > 0) throw new RuntimeException('Stok tersedia atau barang dalam perjalanan masih ada di gudang.');
            $pdo->prepare('INSERT INTO riwayat_hapus_barang (barang_id,user_id,stok_saat_dihapus,catatan) VALUES (?,?,?,?)')
                ->execute([$itemId,user()['id'],$stockTotal,$note]);
            $pdo->prepare('UPDATE barang SET aktif=0 WHERE id=?')->execute([$itemId]);
            $pdo->commit();
            flash('Barang diarsipkan; katalog dan seluruh riwayat tetap disimpan.');
        }
    } elseif ($aksi === 'manager_proyek_simpan') {
        require_role('super_admin');
        $returnUrl = 'super_admin.php#pengelolaan-gudang';
        $managerId = $positiveInt($post['manager_id'] ?? null);
        $warehouseId = $positiveInt($post['gudang_id'] ?? null);
        if ($managerId < 1 || $warehouseId < 1) throw new RuntimeException('Pilih manajer dan gudang proyek.');
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='manager' AND aktif=1 FOR UPDATE");
        $q->execute([$managerId]);
        if (!$q->fetchColumn()) throw new RuntimeException('Akun manajer tidak ditemukan atau tidak aktif.');
        $q = $pdo->prepare("SELECT id FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 FOR UPDATE");
        $q->execute([$warehouseId]);
        if (!$q->fetchColumn()) throw new RuntimeException('Pilih gudang proyek aktif yang benar.');
        $q = $pdo->prepare('INSERT IGNORE INTO manager_gudang (manager_id,gudang_id,ditugaskan_oleh,ditugaskan_pada) VALUES (?,?,?,NOW())');
        $q->execute([$managerId,$warehouseId,user()['id']]);
        if ($q->rowCount() !== 1) throw new RuntimeException('Manajer sudah ditugaskan ke gudang proyek tersebut.');
        $pdo->commit();
        flash('Manajer ditugaskan ke gudang proyek.');
    } elseif ($aksi === 'manager_proyek_hapus') {
        require_role('super_admin');
        $returnUrl = 'super_admin.php#pengelolaan-gudang';
        $managerId = $positiveInt($post['manager_id'] ?? null);
        $warehouseId = $positiveInt($post['gudang_id'] ?? null);
        if ($managerId < 1 || $warehouseId < 1) throw new RuntimeException('Penugasan manajer tidak valid.');
        $pdo->beginTransaction();
        $q = $pdo->prepare('DELETE FROM manager_gudang WHERE manager_id=? AND gudang_id=?');
        $q->execute([$managerId,$warehouseId]);
        if ($q->rowCount() !== 1) throw new RuntimeException('Penugasan manajer tidak ditemukan.');
        $pdo->commit();
        flash('Penugasan manajer ke gudang proyek dihapus.');
    } elseif ($aksi === 'gudang_simpan') {
        require_role('super_admin');
        $returnUrl = 'super_admin.php#pengelolaan-gudang';
        $code = strtoupper($text('kode_gudang'));
        $name = $text('nama_gudang');
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,39}$/D',$code) || $name === '' || $textLength($name) > 160) {
            throw new RuntimeException('Kode proyek dan nama gudang harus diisi dengan benar.');
        }
        $pdo->prepare("INSERT INTO gudang (kode,nama,jenis) VALUES (?,?,'proyek')")->execute([$code,$name]);
        flash('Gudang proyek ditambahkan. Tautkan atau buat akun admin gudangnya.');
    } elseif ($aksi === 'akun_baru') {
        require_role('super_admin');
        $returnUrl = 'super_admin.php#buat-akun';
        $name = $text('nama_admin');
        $email = strtolower($text('email_admin'));
        $password = $text('password_admin');
        $warehouseId = $positiveInt($post['gudang_id'] ?? null);
        if ($name === '' || $textLength($name) > 120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || $textLength($email) > 190 ||
            strlen($password) < 8 || strlen($password) > 200 || $warehouseId < 1) {
            throw new RuntimeException('Nama, email, kata sandi minimal 8 karakter, dan gudang proyek wajib valid.');
        }
        $q = $pdo->prepare("SELECT id FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1");
        $q->execute([$warehouseId]);
        if (!$q->fetchColumn()) throw new RuntimeException('Gudang proyek tidak ditemukan.');
        $hash = password_hash($password,PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO users (nama,email,password_hash,role,gudang_id,aktif) VALUES (?,?,?,'admin_gudang',?,1)")
            ->execute([$name,$email,$hash,$warehouseId]);
        $newUserId = (int)$pdo->lastInsertId();
        record_user_management_audit($pdo,$newUserId,$name,$email,'akun_dibuat','Akun dibuat dengan role Admin Gudang Proyek dan status Aktif.');
        $pdo->commit();
        flash('Akun admin gudang proyek dibuat untuk gudang yang dipilih.');
    } elseif ($aksi === 'akun_purchasing_baru') {
        require_role('super_admin');
        $returnUrl = 'super_admin.php#buat-akun';
        $name = $text('nama_purchasing');
        $email = strtolower($text('email_purchasing'));
        $password = $text('password_purchasing');
        if ($name === '' || $textLength($name) > 120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || $textLength($email) > 190 ||
            strlen($password) < 8 || strlen($password) > 200) {
            throw new RuntimeException('Nama, email, dan kata sandi minimal 8 karakter harus diisi dengan benar.');
        }
        $hash = password_hash($password,PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO users (nama,email,password_hash,role,gudang_id,aktif) VALUES (?,?,?,'purchasing',NULL,1)")
            ->execute([$name,$email,$hash]);
        $newUserId = (int)$pdo->lastInsertId();
        record_user_management_audit($pdo,$newUserId,$name,$email,'akun_dibuat','Akun dibuat dengan role Purchasing dan status Aktif.');
        $pdo->commit();
        flash('Akun purchasing berhasil dibuat.');
    } elseif ($aksi === 'akun_petakan') {
        require_role('super_admin');
        $returnUrl = 'super_admin.php#akun-legacy';
        $userId = $positiveInt($post['user_id'] ?? null);
        $targetRole = $text('role_tujuan');
        $warehouseId = $positiveInt($post['gudang_id'] ?? null);
        if ($userId < 1 || !in_array($targetRole,['admin_pusat','admin_gudang'],true)) {
            throw new RuntimeException('Klasifikasi akun tidak valid.');
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT p.user_id,p.aktif_sebelumnya,u.role,u.aktif,u.nama,u.email FROM pemetaan_akun_lama p JOIN users u ON u.id=p.user_id WHERE p.user_id=? AND p.status='menunggu_konfirmasi' AND p.role_lama='admin_gudang' FOR UPDATE");
        $q->execute([$userId]); $candidate = $q->fetch();
        if (!$candidate || $candidate['role'] !== 'admin_gudang') throw new RuntimeException('Akun sudah dipetakan atau bukan kandidat admin gudang lama.');
        if ($targetRole === 'admin_gudang') {
            $q = $pdo->prepare("SELECT id FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 FOR UPDATE");
            $q->execute([$warehouseId]);
            if (!$q->fetchColumn()) throw new RuntimeException('Pilih gudang proyek aktif yang benar.');
        } else {
            $warehouseId = null;
        }
        $pdo->prepare('UPDATE users SET role=?,gudang_id=?,aktif=? WHERE id=?')
            ->execute([$targetRole,$warehouseId,(int)$candidate['aktif_sebelumnya'],$userId]);
        $status = $targetRole === 'admin_pusat' ? 'dipetakan_pusat' : 'dipetakan_proyek';
        $pdo->prepare('UPDATE pemetaan_akun_lama SET status=?,gudang_id=?,dipetakan_oleh=?,dipetakan_pada=NOW() WHERE user_id=?')
            ->execute([$status,$warehouseId,user()['id'],$userId]);
        $roleLabel = $targetRole === 'admin_pusat' ? 'Admin Pusat' : 'Admin Gudang Proyek';
        record_user_management_audit($pdo,$userId,$candidate['nama'],$candidate['email'],'role_diubah','Role akun legacy dikonfirmasi sebagai '.$roleLabel.'.');
        if ((int)$candidate['aktif'] !== (int)$candidate['aktif_sebelumnya']) {
            record_user_management_audit($pdo,$userId,$candidate['nama'],$candidate['email'],(int)$candidate['aktif_sebelumnya'] ? 'akun_diaktifkan' : 'akun_dinonaktifkan','Status akun dipulihkan sesuai catatan sebelum migrasi.');
        }
        $pdo->commit();
        flash($targetRole === 'admin_pusat' ? 'Akun diklasifikasikan sebagai admin_pusat.' : 'Akun ditautkan ke gudang proyek yang dipilih.');
    } else {
        throw new RuntimeException('Aksi tidak dikenal.');
    }
} catch (\Illuminate\Http\Exceptions\HttpResponseException $error) { throw $error; } catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($newReceiptPhotoPaths as $photoPath) {
        if (is_file($photoPath)) @unlink($photoPath);
    }
    $message = $error instanceof PDOException
        ? ($aksi === 'pembelian_penerimaan_simpan' ? 'Penerimaan gagal disimpan. Catatan, foto, dan perubahan stok dibatalkan; periksa skema database dan coba kembali.' : 'Perubahan gagal disimpan. Periksa data dan struktur database.')
        : $error->getMessage();
    flash($message,'error');
}
go($returnUrl);

}
public function aksi_pembelian() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];
if ($server['REQUEST_METHOD'] !== 'POST') {
    if (user() && in_array(user()['role'] ?? '', ['admin_pusat','admin_gudang','manager'], true)) go('dashboard.php');
    go('purchasing.php');
}
check_csrf();
require_role('admin_pusat','admin_gudang','purchasing');
$pdo = db();
$action = is_string($post['aksi'] ?? null) ? $post['aksi'] : '';
$positiveInt = static function(mixed $value): int {
    return is_string($value) && ctype_digit($value) ? (int)$value : 0;
};
$text = static fn(string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
$textLength = static function(string $value): int {
    $length = preg_match_all('/./us',$value);
    return $length === false ? PHP_INT_MAX : $length;
};
$returnUrl = user()['role']==='purchasing' ? 'purchasing.php#permintaan' : 'dashboard.php#permintaan-pembelian';

try {
    if ($action === 'permintaan_simpan') {
        require_role('admin_pusat','admin_gudang');
        $itemId = $positiveInt($post['barang_id'] ?? null);
        $quantity = $positiveInt($post['jumlah'] ?? null);
        $note = $text('catatan');
        if ($itemId < 1 || $quantity < 1 || $quantity > 4294967295 || $textLength($note) > 2000) {
            throw new RuntimeException('Barang, jumlah, atau catatan permintaan tidak valid.');
        }
        $pdo->beginTransaction();
        $itemQuery = $pdo->prepare('SELECT id FROM barang WHERE id=? AND aktif=1 FOR UPDATE');
        $itemQuery->execute([$itemId]);
        if (!$itemQuery->fetchColumn()) throw new RuntimeException('Barang tidak ditemukan atau sudah nonaktif.');
        $pdo->prepare("INSERT INTO permintaan_pembelian (user_id,barang_id,jumlah,catatan,status) VALUES (?,?,?,?,'menunggu')")
            ->execute([user()['id'],$itemId,$quantity,$note ?: null]);
        $requestId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO purchasing_notifications (recipient_id,permintaan_pembelian_id) SELECT id,? FROM users WHERE role='purchasing' AND aktif=1")
            ->execute([$requestId]);
        $pdo->commit();
        flash('Permintaan pembelian dikirim kepada Purchasing untuk diproses.');
    } elseif ($action === 'permintaan_setujui' || $action === 'permintaan_tolak') {
        require_role('purchasing');
        $requestId = $positiveInt($post['permintaan_id'] ?? null);
        if ($requestId < 1) throw new RuntimeException('Permintaan pembelian tidak ditemukan.');
        $status = $action === 'permintaan_setujui' ? 'disetujui' : 'ditolak';
        $pdo->beginTransaction();
        $requestQuery = $pdo->prepare('SELECT id,user_id,barang_id,status FROM permintaan_pembelian WHERE id=? FOR UPDATE');
        $requestQuery->execute([$requestId]);
        $request = $requestQuery->fetch();
        if (!$request || $request['status'] !== 'menunggu') throw new RuntimeException('Permintaan sudah diproses atau tidak ditemukan.');
        if ((int)$request['user_id'] === (int)user()['id']) throw new RuntimeException('Anda tidak dapat memutuskan permintaan pembelian sendiri.');
        if ($status === 'disetujui') {
            $itemQuery = $pdo->prepare('SELECT id FROM barang WHERE id=? AND aktif=1 FOR UPDATE');
            $itemQuery->execute([(int)$request['barang_id']]);
            if (!$itemQuery->fetchColumn()) throw new RuntimeException('Barang sudah nonaktif dan tidak dapat disetujui.');
        }
        $update = $pdo->prepare("UPDATE permintaan_pembelian SET status=?,diproses_oleh=?,diproses_pada=NOW() WHERE id=? AND status='menunggu' AND user_id<>?");
        $update->execute([$status,user()['id'],$requestId,user()['id']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Permintaan berubah atau sudah diproses. Muat ulang halaman.');
        if ($status === 'disetujui') app(WarehouseNotifications::class)->purchaseApproved($pdo, $requestId, (int)$request['user_id']);
        $pdo->commit();
        flash($status === 'disetujui' ? 'Permintaan pembelian disetujui oleh Purchasing. Stok tidak berubah.' : 'Permintaan pembelian ditolak oleh Purchasing. Stok tidak berubah.');
    } else {
        throw new RuntimeException('Aksi permintaan pembelian tidak dikenal.');
    }
} catch (\Illuminate\Http\Exceptions\HttpResponseException $error) { throw $error; } catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = $error instanceof PDOException ? 'Perubahan gagal disimpan. Periksa data dan struktur database.' : $error->getMessage();
    flash($message,'error');
}
go($returnUrl);

}
public function aksi_pengguna() {
$post = request()->post();
$queryInput = request()->query();
$uploads = warehouse_uploads();
$server = ['REQUEST_METHOD'=>request()->method(),'CONTENT_LENGTH'=>request()->server('CONTENT_LENGTH',0)];
require_role('super_admin');
if ($server['REQUEST_METHOD'] !== 'POST') go('super_admin.php');
check_csrf();

$pdo = db();
$text = static fn(string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
$textLength = static function(string $value): int {
    $length = preg_match_all('/./us',$value);
    return $length === false ? PHP_INT_MAX : $length;
};
$roles = [
    'pengguna'=>'Pengguna',
    'admin_pusat'=>'Admin Pusat',
    'admin_gudang'=>'Admin Gudang Proyek',
    'manager'=>'Manajer',
    'purchasing'=>'Purchasing',
    'super_admin'=>'Super Admin',
];
$action = $text('aksi');

try {
    if (!in_array($action,['buat','simpan'],true)) throw new RuntimeException('Aksi pengelolaan akun tidak dikenal.');
    $name = $text('nama');
    $email = strtolower($text('email'));
    $role = $text('role');
    $activeInput = $post['aktif'] ?? null;
    $warehouseInput = $post['gudang_id'] ?? '';
    $password = is_string($post['password'] ?? null) ? $post['password'] : '';
    $userId = $action === 'simpan' ? (is_string($post['user_id'] ?? null) && ctype_digit($post['user_id']) ? (int)$post['user_id'] : 0) : 0;
    $active = is_string($activeInput) && in_array($activeInput,['0','1'],true) ? (int)$activeInput : -1;

    if ($name === '' || $textLength($name) > 120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || $textLength($email) > 190) {
        throw new RuntimeException('Nama dan email harus diisi dengan format yang benar.');
    }
    if (!isset($roles[$role]) || $active < 0) throw new RuntimeException('Peran atau status akun tidak valid.');
    if ($action === 'buat' && in_array($role,['pengguna','super_admin'],true)) {
        throw new RuntimeException('Role Pengguna dan Super Admin tidak tersedia saat membuat akun. Untuk Super Admin, ubah role akun yang sudah ada.');
    }
    if ($action === 'buat' && (strlen($password) < 8 || strlen($password) > 200)) {
        throw new RuntimeException('Kata sandi awal harus berisi 8 sampai 200 karakter.');
    }
    if ($action === 'simpan' && $userId < 1) throw new RuntimeException('ID akun tidak valid.');
    if ($action === 'simpan' && $userId === (int)user()['id'] && ($role !== 'super_admin' || $active !== 1)) {
        throw new RuntimeException('Anda tidak dapat mengganti peran atau menonaktifkan akun sendiri.');
    }

    $warehouseId = null;
    if ($role === 'admin_gudang') {
        $warehouseId = is_string($warehouseInput) && ctype_digit($warehouseInput) ? (int)$warehouseInput : 0;
        if ($warehouseId < 1) throw new RuntimeException('Pilih gudang proyek aktif untuk akun Admin Gudang.');
        $warehouseQuery = $pdo->prepare("SELECT id FROM gudang WHERE id=? AND jenis='proyek' AND aktif=1 LIMIT 1");
        $warehouseQuery->execute([$warehouseId]);
        if (!$warehouseQuery->fetchColumn()) throw new RuntimeException('Gudang proyek yang dipilih tidak aktif atau tidak ditemukan.');
    } elseif ($warehouseInput !== '' && $warehouseInput !== null) {
        throw new RuntimeException('Hanya role Admin Gudang Proyek yang dapat ditautkan ke gudang.');
    }

    if ($action === 'buat') {
        $passwordHash = password_hash($password,PASSWORD_DEFAULT);
        if ($passwordHash === false) throw new RuntimeException('Kata sandi tidak dapat diproses.');
        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT INTO users (nama,email,password_hash,role,gudang_id,aktif) VALUES (?,?,?,?,?,?)');
        $insert->execute([$name,$email,$passwordHash,$role,$warehouseId,$active]);
        $newUserId = (int)$pdo->lastInsertId();
        record_user_management_audit($pdo,$newUserId,$name,$email,'akun_dibuat','Akun dibuat dengan role '.$roles[$role].' dan status '.($active ? 'Aktif' : 'Nonaktif').'.');
        $pdo->commit();
        flash('Akun baru berhasil dibuat.');
    } else {
        $pdo->beginTransaction();
        $lockedAdmins = array_map('intval',$pdo->query("SELECT id FROM users WHERE role='super_admin' AND aktif=1 FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN));
        $find = $pdo->prepare('SELECT id,nama,email,role,gudang_id,aktif FROM users WHERE id=? FOR UPDATE');
        $find->execute([$userId]);
        $current = $find->fetch();
        if (!$current) throw new RuntimeException('Akun yang akan diubah tidak ditemukan.');

        $roleChanged = $current['role'] !== $role;
        $statusChanged = (int)$current['aktif'] !== $active;
        if ($current['role'] === 'super_admin' && ($roleChanged || ((int)$current['aktif'] === 1 && $active === 0))) {
            $remainingAdmins = array_filter($lockedAdmins,static fn(int $id): bool => $id !== $userId);
            if (count($remainingAdmins) < 1) throw new RuntimeException('Akun Super Admin aktif terakhir tidak dapat dinonaktifkan atau kehilangan rolenya.');
        }

        $profileChanges = [];
        if ($current['nama'] !== $name) $profileChanges[] = 'Nama: '.$current['nama'].' → '.$name;
        if ($current['email'] !== $email) $profileChanges[] = 'Email: '.$current['email'].' → '.$email;
        if ((int)($current['gudang_id'] ?? 0) !== (int)($warehouseId ?? 0)) $profileChanges[] = 'Pemetaan gudang diperbarui.';
        if ($password !== '' && (strlen($password) < 12 || strlen($password) > 200)) throw new RuntimeException('Kata sandi baru harus berisi 12 sampai 200 karakter.');

        $values = [$name,$email,$role,$warehouseId,$active];
        $passwordHash = null;
        if ($password !== '') {
            $passwordHash = password_hash($password,PASSWORD_DEFAULT);
            if ($passwordHash === false) throw new RuntimeException('Kata sandi tidak dapat diproses.');
            $values[] = $passwordHash;
        }
        $values[] = $userId;
        $sql = 'UPDATE users SET nama=?,email=?,role=?,gudang_id=?,aktif=?'.($passwordHash !== null ? ',password_hash=?' : '').' WHERE id=?';
        $pdo->prepare($sql)->execute($values);

        if ($roleChanged) {
            record_user_management_audit($pdo,$userId,$name,$email,'role_diubah','Role diubah dari '.$roles[$current['role']].' menjadi '.$roles[$role].'.');
        }
        if ($statusChanged) {
            record_user_management_audit($pdo,$userId,$name,$email,$active ? 'akun_diaktifkan' : 'akun_dinonaktifkan','Status akun diubah dari '.((int)$current['aktif'] ? 'Aktif' : 'Nonaktif').' menjadi '.($active ? 'Aktif' : 'Nonaktif').'.');
        }
        if ($password !== '') $profileChanges[] = 'Kata sandi diperbarui.';
        if ($profileChanges) {
            record_user_management_audit($pdo,$userId,$name,$email,'akun_diperbarui',implode(' ',$profileChanges));
        }
        $pdo->commit();
        flash($roleChanged || $statusChanged || $profileChanges ? 'Perubahan akun berhasil disimpan.' : 'Tidak ada perubahan pada akun.');
    }
} catch (\Illuminate\Http\Exceptions\HttpResponseException $error) { throw $error; } catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = $error instanceof PDOException
        ? 'Perubahan akun gagal disimpan. Pastikan email belum digunakan dan skema database sudah diperbarui.'
        : $error->getMessage();
    flash($message,'error');
}
go('super_admin.php#akun');

}
}