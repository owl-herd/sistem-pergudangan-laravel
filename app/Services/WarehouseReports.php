<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use RuntimeException;
use Throwable;

class WarehouseReports
{
    public function laporan_pdf()
    {
        $post = request()->post();
        $queryInput = request()->query();
        $uploads = warehouse_uploads();
        $server = ['REQUEST_METHOD' => request()->method(), 'CONTENT_LENGTH' => request()->server('CONTENT_LENGTH', 0)];
        ob_start();
        try {
            require_role('manager');
            $pdo = db();
            $reportTypes = ['semua' => 'Semua laporan', 'stok' => 'Stok per gudang', 'permintaan' => 'Permintaan barang', 'pengiriman' => 'Pengiriman dan penerimaan', 'pembelian' => 'Pengajuan pembelian', 'penerimaan_pembelian' => 'Penerimaan pembelian', 'penambahan' => 'Penambahan barang proyek', 'retur' => 'Retur peralatan', 'mutasi' => 'Mutasi dan pemakaian barang'];
            $reportType = $queryInput['jenis_laporan'] ?? 'semua';
            abort_unless(is_string($reportType) && isset($reportTypes[$reportType]), 422);
            $reportTitle = $reportTypes[$reportType];
            $today = date('Y-m-d');
            $defaultStart = date('Y-m-01');
            $validDate = function (mixed $value): bool {
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

            $inventoryQuery = $pdo->query('SELECT COUNT(DISTINCT b.id) jenis,COALESCE(SUM(s.stok),0) unit,COUNT(CASE WHEN COALESCE(s.stok,0)<=b.stok_minimum THEN 1 END) rendah FROM barang b CROSS JOIN gudang g LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE b.aktif=1 AND g.aktif=1');
            $inventory = $inventoryQuery->fetch();
            $movementQuery = $pdo->prepare('SELECT t.jenis,COALESCE(SUM(t.jumlah),0) jumlah FROM transaksi_gudang t JOIN gudang g ON g.id=t.gudang_id WHERE t.tanggal>=? AND t.tanggal<? AND g.aktif=1 GROUP BY t.jenis');
            $movementQuery->execute([$start, $endExclusive]);
            $movementTotals = ['masuk' => 0, 'keluar' => 0, 'kembali' => 0, 'pemakaian' => 0];
            foreach ($movementQuery->fetchAll() as $row) {
                $movementTotals[$row['jenis']] = (int) $row['jumlah'];
            }
            $pendingQuery = $pdo->query("SELECT COUNT(*) FROM permintaan p JOIN gudang g ON g.id=p.gudang_tujuan_id WHERE p.gudang_tujuan_id IS NOT NULL AND p.status='diajukan' AND g.aktif=1");
            $pending = (int) $pendingQuery->fetchColumn();
            $transitQuery = $pdo->query("SELECT COUNT(*) FROM pengiriman pe JOIN gudang ga ON ga.id=pe.gudang_asal_id JOIN gudang gt ON gt.id=pe.gudang_tujuan_id WHERE pe.status='dalam_perjalanan' AND ga.aktif=1 AND gt.aktif=1");
            $transitCount = (int) $transitQuery->fetchColumn();

            $stockQuery = $pdo->query('SELECT g.id gudang_id,g.nama gudang,b.kode,b.nama,b.satuan,b.stok_minimum,COALESCE(s.stok,0) stok FROM gudang g CROSS JOIN barang b LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE g.aktif=1 AND b.aktif=1 ORDER BY g.nama,b.kode');
            $stockRows = $stockQuery->fetchAll();

            $requestQuery = $pdo->prepare("SELECT p.id,p.tanggal,p.status,p.nama_proyek,gt.nama gudang,u.nama peminta,GROUP_CONCAT(CONCAT(b.kode,' · ',b.nama,' × ',dp.jumlah,' ',b.satuan) ORDER BY b.kode SEPARATOR ', ') barang FROM permintaan p JOIN users u ON u.id=p.user_id LEFT JOIN gudang gt ON gt.id=p.gudang_tujuan_id LEFT JOIN detail_permintaan dp ON dp.permintaan_id=p.id LEFT JOIN barang b ON b.id=dp.barang_id WHERE p.gudang_tujuan_id IS NOT NULL AND p.tanggal>=? AND p.tanggal<? AND gt.aktif=1 GROUP BY p.id ORDER BY p.tanggal DESC,p.id DESC");
            $requestQuery->execute([$start, $endExclusive]);
            $requestRows = $requestQuery->fetchAll();

            $shipmentQuery = $pdo->prepare("SELECT pe.id,pe.status,pe.dikirim_pada,ga.nama gudang_asal,gt.nama gudang_tujuan,us.nama pengirim,ur.nama penerima,GROUP_CONCAT(CONCAT(b.kode,' · ',b.nama,': ',d.jumlah_dikirim,' dikirim / ',COALESCE(d.jumlah_diterima,'belum dikonfirmasi'),' diterima ',b.satuan) ORDER BY b.kode SEPARATOR '; ') rincian FROM pengiriman pe JOIN gudang ga ON ga.id=pe.gudang_asal_id JOIN gudang gt ON gt.id=pe.gudang_tujuan_id JOIN users us ON us.id=pe.dikirim_oleh LEFT JOIN users ur ON ur.id=pe.diterima_oleh JOIN detail_pengiriman d ON d.pengiriman_id=pe.id JOIN barang b ON b.id=d.barang_id WHERE pe.dikirim_pada>=? AND pe.dikirim_pada<? AND ga.aktif=1 AND gt.aktif=1 GROUP BY pe.id ORDER BY pe.dikirim_pada DESC,pe.id DESC");
            $shipmentQuery->execute([$start, $endExclusive]);
            $shipmentRows = $shipmentQuery->fetchAll();

            $movementQuery = $pdo->prepare('SELECT t.tanggal,t.jenis,t.jumlah,t.referensi,t.nomor_transaksi,t.satuan_transaksi,t.nama_supplier,t.nama_petugas,b.kode,b.nama barang,COALESCE(t.satuan_transaksi,b.satuan) satuan,g.nama gudang,ga.nama gudang_asal,gt.nama gudang_tujuan,u.nama operator FROM transaksi_gudang t JOIN barang b ON b.id=t.barang_id JOIN gudang g ON g.id=t.gudang_id LEFT JOIN gudang ga ON ga.id=t.gudang_asal_id LEFT JOIN gudang gt ON gt.id=t.gudang_tujuan_id LEFT JOIN users u ON u.id=t.user_id WHERE t.tanggal>=? AND t.tanggal<? AND g.aktif=1 ORDER BY t.tanggal DESC,t.id DESC');
            $movementQuery->execute([$start, $endExclusive]);
            $movementRows = $movementQuery->fetchAll();

            $additionQuery = $pdo->prepare("SELECT pa.jumlah,pa.catatan,pa.ditambahkan_pada,pa.kode_barang kode,pa.nama_barang barang,pa.satuan_barang satuan,pa.nama_proyek gudang,pa.nama_penambah petugas FROM penambahan_barang_proyek pa JOIN gudang g ON g.id=pa.gudang_id WHERE g.jenis='proyek' AND g.aktif=1 AND pa.ditambahkan_pada>=? AND pa.ditambahkan_pada<? ORDER BY pa.ditambahkan_pada DESC,pa.id DESC");
            $additionQuery->execute([$start, $endExclusive]);
            $additionRows = $additionQuery->fetchAll();
            $returnQuery = $pdo->prepare("SELECT rp.id,rp.jumlah,rp.catatan_kondisi,rp.status,rp.dikirim_pada,rp.diterima_pada,rp.kode_barang kode,rp.nama_barang barang,rp.satuan_barang satuan,rp.nama_proyek gudang,rp.nama_admin_proyek pengirim,ur.nama penerima FROM pengembalian_peralatan_proyek rp JOIN gudang g ON g.id=rp.gudang_asal_id LEFT JOIN users ur ON ur.id=rp.diterima_oleh WHERE g.jenis='proyek' AND g.aktif=1 AND (rp.status='menunggu_diterima' OR (rp.dikirim_pada>=? AND rp.dikirim_pada<?)) ORDER BY rp.dikirim_pada DESC,rp.id DESC");
            $returnQuery->execute([$start, $endExclusive]);
            $returnRows = $returnQuery->fetchAll();

            $navy = [0.071, 0.227, 0.408];
            $gold = [0.969, 0.678, 0.125];
            $white = [1.000, 1.000, 1.000];
            $light = [0.955, 0.973, 0.992];
            $grid = [0.842, 0.882, 0.929];
            $textColor = [0.090, 0.157, 0.243];
            $muted = [0.420, 0.478, 0.553];
            $headerMuted = [0.745, 0.808, 0.886];
            $pages = [];
            $commands = [];
            $cursor = 0.0;
            $beginPage = function (string $title, bool $summary = false) use (&$pages, &$commands, &$cursor, $navy, $gold, $white, $light, $grid, $muted, $headerMuted, $inventory, $movementTotals, $pending, $transitCount, $start, $end): void {
                if ($commands) {
                    $pages[] = $commands;
                }
                $commands = [];
                $this->reportPdfRect($commands, 0, 494, 841.89, 101.28, $navy);
                $this->reportPdfRect($commands, 0, 490, 841.89, 4, $gold);
                $commands[] = "q 58 0 0 58 36 514 cm /Logo Do Q\n";
                $this->reportPdfText($commands, 108, 557, 'TRIKASA ENERGI TAMA', 'F2', 15, $white);
                $this->reportPdfText($commands, 109, 538, 'SISTEM INFORMASI PERGUDANGAN', 'F1', 8, [0.835, 0.890, 0.957]);
                $this->reportPdfText($commands, 558, 557, 'LAPORAN LINTAS GUDANG', 'F2', 13, $white);
                $this->reportPdfText($commands, 558, 538, 'Periode: '.date('d M Y', strtotime($start)).' - '.date('d M Y', strtotime($end)), 'F1', 8, $white);
                $this->reportPdfText($commands, 558, 520, 'Dibuat: '.date('d M Y H:i'), 'F1', 7, $headerMuted);
                if ($summary) {
                    $metrics = [
                        ['Jenis barang aktif', number_format((int) $inventory['jenis'])],
                        ['Stok tersedia', number_format((int) $inventory['unit']).' unit'],
                        ['Stok di bawah minimum', number_format((int) $inventory['rendah'])],
                        ['Dalam perjalanan', $transitCount.' pengiriman'],
                    ];
                    foreach ($metrics as $index => $metric) {
                        $x = 36 + ($index * 193);
                        $this->reportPdfRect($commands, $x, 414, 180, 54, $light, $grid, 0.55);
                        $this->reportPdfRect($commands, $x, 465, 180, 3, $gold);
                        $this->reportPdfText($commands, $x + 9, 448, $metric[0], 'F1', 7, $muted);
                        $this->reportPdfText($commands, $x + 9, 427, $metric[1], 'F2', 12, $navy);
                    }
                    $this->reportPdfText($commands, 36, 393, 'Masuk '.$movementTotals['masuk'].' | Keluar '.$movementTotals['keluar'].' | Pemakaian proyek '.$movementTotals['pemakaian'].' | Permintaan menunggu '.$pending, 'F1', 8, $muted);
                    $this->reportPdfText($commands, 36, 373, $title, 'F2', 11, $navy);
                    $cursor = 356;
                } else {
                    $this->reportPdfText($commands, 36, 464, $title, 'F2', 11, $navy);
                    $cursor = 443;
                }
            };
            $drawSection = function (string $title, array $columns, array $rows) use (&$commands, &$pages, &$cursor, $beginPage, $navy, $white, $light, $grid, $textColor): void {
                if (! $commands || $cursor < 95) {
                    $beginPage($title, false);
                } else {
                    $this->reportPdfText($commands, 36, $cursor - 17, $title, 'F2', 11, $navy);
                    $cursor -= 27;
                }
                $cursor = $this->reportTableHeader($commands, $cursor, $columns, $navy, $white, $grid);
                if (! $rows) {
                    $emptyColumns = [['Informasi', 770, 145]];
                    $prepared = $this->reportPrepareRow($emptyColumns, ['Tidak ada data pada periode ini.']);
                    $cursor = $this->reportDrawRow($commands, $cursor, $emptyColumns, $prepared, 0, $white, $light, $grid, $textColor);

                    return;
                }
                foreach ($rows as $index => $values) {
                    $prepared = $this->reportPrepareRow($columns, $values);
                    if ($cursor - $prepared['height'] < 45) {
                        $beginPage($title.' · lanjutan', false);
                        $cursor = $this->reportTableHeader($commands, $cursor, $columns, $navy, $white, $grid);
                    }
                    $cursor = $this->reportDrawRow($commands, $cursor, $columns, $prepared, $index, $white, $light, $grid, $textColor);
                }
            };

            $beginPage($reportTitle, $reportType === 'semua');
            if ($reportType === 'semua' || $reportType === 'stok') {
                $stockColumns = [['Kode', 75, 14], ['Barang', 335, 70], ['Stok tersedia', 110, 20], ['Minimum', 100, 18], ['Satuan', 150, 26]];
                $stockByWarehouse = [];
                foreach ($stockRows as $stockRow) {
                    $stockByWarehouse[(int) $stockRow['gudang_id']]['nama'] = $stockRow['gudang'];
                    $stockByWarehouse[(int) $stockRow['gudang_id']]['rows'][] = [
                        $stockRow['kode'],
                        $stockRow['nama'],
                        number_format((int) $stockRow['stok']),
                        number_format((int) $stockRow['stok_minimum']),
                        $stockRow['satuan'],
                    ];
                }
                if (! $stockByWarehouse) {
                    $drawSection('Stok per gudang', [['Informasi', 770, 145]], []);
                } else {
                    foreach ($stockByWarehouse as $warehouseStock) {
                        $drawSection('Stok gudang: '.$warehouseStock['nama'], $stockColumns, $warehouseStock['rows']);
                    }
                }

            }
            $requestColumns = [['ID', 42, 8], ['Tanggal', 76, 16], ['Gudang', 122, 22], ['Peminta', 110, 22], ['Rincian barang', 320, 70], ['Status', 100, 20]];
            $requestValues = array_map(static fn (array $row): array => ['#'.$row['id'], date('d M Y', strtotime($row['tanggal'])), $row['gudang'] ?: 'Arsip lama; lokasi tidak dipetakan', $row['peminta'], $row['barang'] ?: '-', (string) $row['status']], $requestRows);
            if ($reportType === 'semua' || $reportType === 'permintaan') {
                $drawSection('Permintaan barang', $requestColumns, $requestValues);
            }

            $shipmentColumns = [['ID', 45, 8], ['Dikirim', 82, 18], ['Gudang asal', 112, 20], ['Gudang tujuan', 112, 20], ['Status', 112, 22], ['Rincian dikirim / diterima', 307, 66]];
            $shipmentValues = array_map(static fn (array $row): array => ['#'.$row['id'], date('d M Y H:i', strtotime($row['dikirim_pada'])), $row['gudang_asal'], $row['gudang_tujuan'], str_replace('_', ' ', $row['status']), $row['rincian']], $shipmentRows);
            if ($reportType === 'semua' || $reportType === 'pengiriman') {
                $drawSection('Pengiriman, penerimaan, dan barang dalam perjalanan', $shipmentColumns, $shipmentValues);
            }

            $additionColumns = [['Tanggal', 82, 18], ['Proyek', 112, 22], ['Barang', 152, 30], ['Jumlah', 62, 12], ['Keterangan', 242, 48], ['Petugas', 120, 22]];
            $additionValues = array_map(static fn (array $row): array => [date('d M Y H:i', strtotime($row['ditambahkan_pada'])), $row['gudang'], trim($row['kode'].' · '.$row['barang']), number_format((int) $row['jumlah']).' '.$row['satuan'], $row['catatan'], $row['petugas']], $additionRows);
            if ($reportType === 'semua' || $reportType === 'penambahan') {
                $drawSection('Penambahan barang proyek', $additionColumns, $additionValues);
            }

            $returnColumns = [['Dikirim', 82, 18], ['Proyek', 102, 20], ['Peralatan', 126, 26], ['Jumlah', 58, 12], ['Catatan kondisi', 226, 44], ['Petugas', 112, 22], ['Status', 64, 14]];
            $returnValues = array_map(static fn (array $row): array => [date('d M Y H:i', strtotime($row['dikirim_pada'])), $row['gudang'], trim($row['kode'].' · '.$row['barang']), number_format((int) $row['jumlah']).' '.$row['satuan'], $row['catatan_kondisi'], trim($row['pengirim'].($row['penerima'] ? ' / '.$row['penerima'] : '')), $row['status'] === 'menunggu_diterima' ? 'Menunggu admin pusat' : 'Diterima'], $returnRows);
            if ($reportType === 'semua' || $reportType === 'retur') {
                $drawSection('Riwayat kondisi pengembalian peralatan', $returnColumns, $returnValues);
            }

            $movementLabels = ['masuk' => 'Barang masuk', 'keluar' => 'Dikirim keluar', 'kembali' => 'Barang kembali', 'pemakaian' => 'Pemakaian proyek'];
            $movementColumns = [['Tanggal', 82, 18], ['Jenis', 88, 20], ['Gudang', 112, 22], ['Barang', 142, 30], ['Jumlah', 58, 12], ['Asal / tujuan', 112, 23], ['Keterangan', 118, 25], ['Petugas', 58, 12]];
            $movementValues = array_map(function (array $row) use ($movementLabels): array {
                $note = trim(($row['nomor_transaksi'] ? 'No. '.$row['nomor_transaksi'].' · ' : '').($row['referensi'] ?: '').($row['nama_supplier'] ? ' · Supplier: '.$row['nama_supplier'] : ''));
                $isAdjustment = $row['referensi'] === 'Penyesuaian stok melalui katalog' || str_starts_with((string) $row['referensi'], 'Stock opname #');
                $type = $isAdjustment ? ($row['jenis'] === 'masuk' ? 'Penyesuaian stok (+)' : 'Penyesuaian stok (-)') : ($movementLabels[$row['jenis']] ?? $row['jenis']);
                if ($row['jenis'] === 'kembali' && str_starts_with((string) $row['referensi'], 'Peralatan kembali dari proyek #')) {
                    $type = 'Retur peralatan';
                }

                return [date('d M Y H:i', strtotime($row['tanggal'])), $type, $row['gudang'], trim($row['kode'].' · '.$row['barang']), number_format((int) $row['jumlah']).' '.$row['satuan'], ($row['gudang_asal'] ?: '-').' -> '.($row['gudang_tujuan'] ?: '-'), $note ?: '-', ($row['nama_petugas'] ?: $row['operator'] ?: '-')];
            }, $movementRows);
            if ($reportType === 'semua' || $reportType === 'mutasi') {
                $drawSection('Penerimaan aktual dan pemakaian proyek', $movementColumns, $movementValues);
            }

            if ($reportType === 'semua' || $reportType === 'pembelian') {
                $q = $pdo->prepare("SELECT r.id,r.tanggal,r.jumlah,r.status,b.kode,b.nama,b.satuan,u.nama peminta,COALESCE(g.nama,'Gudang Pusat') gudang FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id JOIN users u ON u.id=r.user_id LEFT JOIN gudang g ON g.id=u.gudang_id WHERE r.tanggal>=? AND r.tanggal<? AND (u.role='admin_pusat' OR g.aktif=1) ORDER BY r.tanggal DESC,r.id DESC");
                $q->execute([$start, $endExclusive]);
                $values = array_map(static fn (array $r): array => ['#'.$r['id'], date('d M Y', strtotime($r['tanggal'])), $r['gudang'], $r['peminta'], $r['kode'].' · '.$r['nama'].' × '.$r['jumlah'].' '.$r['satuan'], $r['status']], $q->fetchAll());
                $drawSection('Pengajuan pembelian', $requestColumns, $values);
            }
            if ($reportType === 'semua' || $reportType === 'penerimaan_pembelian') {
                $q = $pdo->prepare('SELECT r.* FROM penerimaan_pembelian r JOIN gudang g ON g.id=r.gudang_id WHERE r.diterima_pada>=? AND r.diterima_pada<? AND g.aktif=1 ORDER BY r.diterima_pada DESC,r.id DESC');
                $q->execute([$start, $endExclusive]);
                $values = array_map(static fn (array $r): array => ['#'.$r['id'], date('d M Y', strtotime($r['diterima_pada'])), $r['nama_gudang'], $r['kode_barang'].' · '.$r['nama_barang'], $r['jumlah_layak'].' / '.$r['jumlah_rusak'].' / '.$r['jumlah_tidak_sesuai'], $r['nama_petugas']], $q->fetchAll());
                $drawSection('Penerimaan pembelian', [['ID', 42, 8], ['Tanggal', 76, 16], ['Gudang', 122, 22], ['Barang', 260, 55], ['Layak / Rusak / Tidak sesuai', 160, 32], ['Petugas', 110, 22]], $values);
            }

            if ($commands) {
                $pages[] = $commands;
            }
            $logoPath = public_path('assets/logo-report.jpg');
            $logoData = file_get_contents($logoPath);
            $logoSize = getimagesize($logoPath);
            if ($logoData === false || $logoSize === false) {
                throw new RuntimeException('Logo laporan tidak dapat dibaca.');
            }
            $objects = [
                1 => '<< /Type /Catalog /Pages 2 0 R >>',
                3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
                4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
                6 => '<< /Type /XObject /Subtype /Image /Width '.$logoSize[0].' /Height '.$logoSize[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($logoData)." >>\nstream\n".$logoData."\nendstream",
            ];
            $pageIds = [];
            foreach ($pages as $index => $pageCommands) {
                $pageId = 7 + ($index * 2);
                $streamId = $pageId + 1;
                $pageIds[] = $pageId.' 0 R';
                $this->reportPdfLine($pageCommands, 36, 34, 806, 34, $grid, 0.65);
                $this->reportPdfText($pageCommands, 36, 21, 'GudangKita | Periode '.date('d M Y', strtotime($start)).' - '.date('d M Y', strtotime($end)), 'F1', 7, $muted);
                $this->reportPdfText($pageCommands, 750, 21, 'Halaman '.($index + 1).' dari '.count($pages), 'F1', 7, $muted);
                $stream = implode('', $pageCommands);
                $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 841.89 595.28] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /Logo 6 0 R >> >> /Contents '.$streamId.' 0 R >>';
                $objects[$streamId] = '<< /Length '.strlen($stream).">>\nstream\n".$stream.'endstream';
            }
            $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageIds).'] /Count '.count($pages).' >>';
            ksort($objects);
            $pdf = "%PDF-1.4\n";
            $offsets = [0];
            foreach ($objects as $id => $object) {
                $offsets[$id] = strlen($pdf);
                $pdf .= $id." 0 obj\n".$object."\nendobj\n";
            }
            $xrefOffset = strlen($pdf);
            $maxObject = max(array_keys($objects));
            $pdf .= "xref\n0 ".($maxObject + 1)."\n0000000000 65535 f \n";
            for ($id = 1; $id <= $maxObject; $id++) {
                $pdf .= isset($offsets[$id]) ? sprintf("%010d 00000 n \n", $offsets[$id]) : "0000000000 00000 f \n";
            }
            $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

            $filename = 'laporan-'.$reportType.'-'.$start.'-sampai-'.$end.'.pdf';
            warehouse_header('Content-Type: application/pdf');
            warehouse_header('Content-Disposition: '.(($queryInput['tampilan'] ?? '') === 'lihat' ? 'inline' : 'attachment').'; filename="'.$filename.'"');
            warehouse_header('Content-Length: '.strlen($pdf));
            warehouse_header('Cache-Control: private, max-age=0, must-revalidate');
            echo $pdf;

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

    public function laporan_csv()
    {
        $queryInput = request()->query();
        require_role('manager');
        $pdo = db();
        $reportTypes = ['semua' => 'Semua laporan', 'stok' => 'Stok per gudang', 'permintaan' => 'Permintaan barang', 'pengiriman' => 'Pengiriman dan penerimaan', 'pembelian' => 'Pengajuan pembelian', 'penerimaan_pembelian' => 'Penerimaan pembelian', 'penambahan' => 'Penambahan barang proyek', 'retur' => 'Retur peralatan', 'mutasi' => 'Mutasi dan pemakaian barang'];
        $reportType = $queryInput['jenis_laporan'] ?? 'semua';
        abort_unless(is_string($reportType) && isset($reportTypes[$reportType]), 422);
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
        $rows = [];
        $append = static function (array $row) use (&$rows): void {
            $rows[] = $row;
        };
        $include = static function (string $type) use ($reportType): bool {
            return $reportType === 'semua' || $reportType === $type;
        };

        if ($include('stok')) {
            $query = $pdo->query('SELECT g.nama gudang,b.kode,b.nama,b.satuan,b.stok_minimum,COALESCE(s.stok,0) stok FROM gudang g CROSS JOIN barang b LEFT JOIN stok_gudang s ON s.gudang_id=g.id AND s.barang_id=b.id WHERE g.aktif=1 AND b.aktif=1 ORDER BY g.nama,b.kode');
            foreach ($query->fetchAll() as $row) {
                $append(['Stok per gudang', '', '', $row['gudang'], $row['kode'], $row['nama'], (int) $row['stok'], $row['satuan'], (int) $row['stok'] <= (int) $row['stok_minimum'] ? 'Di bawah minimum' : 'Aman', '', 'Batas minimum: '.$row['stok_minimum']]);
            }
        }
        if ($include('permintaan')) {
            $query = $pdo->prepare("SELECT p.id,p.tanggal,p.status,gt.nama gudang,u.nama peminta,GROUP_CONCAT(CONCAT(b.kode,' · ',b.nama,' × ',dp.jumlah,' ',b.satuan) ORDER BY b.kode SEPARATOR ', ') barang FROM permintaan p JOIN users u ON u.id=p.user_id LEFT JOIN gudang gt ON gt.id=p.gudang_tujuan_id LEFT JOIN detail_permintaan dp ON dp.permintaan_id=p.id LEFT JOIN barang b ON b.id=dp.barang_id WHERE p.gudang_tujuan_id IS NOT NULL AND p.tanggal>=? AND p.tanggal<? AND gt.aktif=1 GROUP BY p.id ORDER BY p.tanggal DESC,p.id DESC");
            $query->execute([$start, $endExclusive]);
            foreach ($query->fetchAll() as $row) {
                $append(['Permintaan barang', $row['id'], $row['tanggal'], $row['gudang'] ?: 'Arsip lama; lokasi tidak dipetakan', '', '', '', '', $row['status'], $row['peminta'], $row['barang']]);
            }
        }
        if ($include('pengiriman')) {
            $query = $pdo->prepare("SELECT pe.id,pe.status,pe.dikirim_pada,ga.nama gudang_asal,gt.nama gudang_tujuan,us.nama pengirim,ur.nama penerima,GROUP_CONCAT(CONCAT(b.kode,' · ',b.nama,': ',d.jumlah_dikirim,' dikirim / ',COALESCE(d.jumlah_diterima,'belum dikonfirmasi'),' diterima ',b.satuan) ORDER BY b.kode SEPARATOR '; ') rincian FROM pengiriman pe JOIN gudang ga ON ga.id=pe.gudang_asal_id JOIN gudang gt ON gt.id=pe.gudang_tujuan_id JOIN users us ON us.id=pe.dikirim_oleh LEFT JOIN users ur ON ur.id=pe.diterima_oleh JOIN detail_pengiriman d ON d.pengiriman_id=pe.id JOIN barang b ON b.id=d.barang_id WHERE pe.dikirim_pada>=? AND pe.dikirim_pada<? AND ga.aktif=1 AND gt.aktif=1 GROUP BY pe.id ORDER BY pe.dikirim_pada DESC,pe.id DESC");
            $query->execute([$start, $endExclusive]);
            foreach ($query->fetchAll() as $row) {
                $append(['Pengiriman dan penerimaan', $row['id'], $row['dikirim_pada'], $row['gudang_asal'].' → '.$row['gudang_tujuan'], '', '', '', '', str_replace('_', ' ', $row['status']), $row['penerima'] ?: $row['pengirim'], $row['rincian']]);
            }
        }
        if ($include('pembelian')) {
            $query = $pdo->prepare("SELECT r.id,r.tanggal,r.jumlah,r.status,b.kode,b.nama,b.satuan,u.nama peminta,COALESCE(g.nama,'Gudang Pusat') gudang FROM permintaan_pembelian r JOIN barang b ON b.id=r.barang_id JOIN users u ON u.id=r.user_id LEFT JOIN gudang g ON g.id=u.gudang_id WHERE r.tanggal>=? AND r.tanggal<? AND (u.role='admin_pusat' OR g.aktif=1) ORDER BY r.tanggal DESC,r.id DESC");
            $query->execute([$start, $endExclusive]);
            foreach ($query->fetchAll() as $row) {
                $append(['Pengajuan pembelian', $row['id'], $row['tanggal'], $row['gudang'], $row['kode'], $row['nama'], (int) $row['jumlah'], $row['satuan'], $row['status'], $row['peminta'], '']);
            }
        }
        if ($include('penerimaan_pembelian')) {
            $query = $pdo->prepare('SELECT r.* FROM penerimaan_pembelian r JOIN gudang g ON g.id=r.gudang_id WHERE r.diterima_pada>=? AND r.diterima_pada<? AND g.aktif=1 ORDER BY r.diterima_pada DESC,r.id DESC');
            $query->execute([$start, $endExclusive]);
            foreach ($query->fetchAll() as $row) {
                $append(['Penerimaan pembelian', $row['id'], $row['diterima_pada'], $row['nama_gudang'], $row['kode_barang'], $row['nama_barang'], (int) $row['jumlah_layak'], $row['satuan_barang'], '', $row['nama_petugas'], $row['jumlah_layak'].' layak; '.$row['jumlah_rusak'].' rusak; '.$row['jumlah_tidak_sesuai'].' tidak sesuai. '.$row['catatan_kondisi']]);
            }
        }
        if ($include('penambahan')) {
            $query = $pdo->prepare("SELECT pa.jumlah,pa.catatan,pa.ditambahkan_pada,pa.kode_barang kode,pa.nama_barang barang,pa.satuan_barang satuan,pa.nama_proyek gudang,pa.nama_penambah petugas FROM penambahan_barang_proyek pa JOIN gudang g ON g.id=pa.gudang_id WHERE g.jenis='proyek' AND g.aktif=1 AND pa.ditambahkan_pada>=? AND pa.ditambahkan_pada<? ORDER BY pa.ditambahkan_pada DESC,pa.id DESC");
            $query->execute([$start, $endExclusive]);
            foreach ($query->fetchAll() as $row) {
                $append(['Penambahan barang proyek', '', $row['ditambahkan_pada'], $row['gudang'], $row['kode'], $row['barang'], (int) $row['jumlah'], $row['satuan'], '', $row['petugas'], $row['catatan']]);
            }
        }
        if ($include('retur')) {
            $query = $pdo->prepare("SELECT rp.id,rp.jumlah,rp.catatan_kondisi,rp.status,rp.dikirim_pada,rp.kode_barang kode,rp.nama_barang barang,rp.satuan_barang satuan,rp.nama_proyek gudang,rp.nama_admin_proyek pengirim,ur.nama penerima FROM pengembalian_peralatan_proyek rp JOIN gudang g ON g.id=rp.gudang_asal_id LEFT JOIN users ur ON ur.id=rp.diterima_oleh WHERE g.jenis='proyek' AND g.aktif=1 AND (rp.status='menunggu_diterima' OR (rp.dikirim_pada>=? AND rp.dikirim_pada<?)) ORDER BY rp.dikirim_pada DESC,rp.id DESC");
            $query->execute([$start, $endExclusive]);
            foreach ($query->fetchAll() as $row) {
                $append(['Retur peralatan', $row['id'], $row['dikirim_pada'], $row['gudang'], $row['kode'], $row['barang'], (int) $row['jumlah'], $row['satuan'], $row['status'], $row['penerima'] ?: $row['pengirim'], $row['catatan_kondisi']]);
            }
        }
        if ($include('mutasi')) {
            $query = $pdo->prepare('SELECT t.tanggal,t.jenis,t.jumlah,t.referensi,t.nomor_transaksi,t.satuan_transaksi,t.nama_supplier,t.nama_petugas,b.kode,b.nama barang,COALESCE(t.satuan_transaksi,b.satuan) satuan,g.nama gudang,u.nama operator FROM transaksi_gudang t JOIN barang b ON b.id=t.barang_id JOIN gudang g ON g.id=t.gudang_id LEFT JOIN users u ON u.id=t.user_id WHERE t.tanggal>=? AND t.tanggal<? AND g.aktif=1 ORDER BY t.tanggal DESC,t.id DESC');
            $query->execute([$start, $endExclusive]);
            $movementLabels = ['masuk' => 'Barang masuk', 'keluar' => 'Barang keluar', 'kembali' => 'Barang kembali', 'pemakaian' => 'Pemakaian proyek'];
            foreach ($query->fetchAll() as $row) {
                $isAdjustment = $row['referensi'] === 'Penyesuaian stok melalui katalog' || str_starts_with((string) $row['referensi'], 'Stock opname #');
                $type = $isAdjustment ? ($row['jenis'] === 'masuk' ? 'Penyesuaian stok (+)' : 'Penyesuaian stok (-)') : ($movementLabels[$row['jenis']] ?? $row['jenis']);
                if ($row['jenis'] === 'kembali' && str_starts_with((string) $row['referensi'], 'Peralatan kembali dari proyek #')) {
                    $type = 'Retur peralatan';
                }
                $details = trim(($row['nomor_transaksi'] ? 'No. '.$row['nomor_transaksi'].' · ' : '').($row['referensi'] ?: '').($row['nama_supplier'] ? ' · Supplier: '.$row['nama_supplier'] : ''));
                $append(['Mutasi stok', $row['nomor_transaksi'] ?: '', $row['tanggal'], $row['gudang'], $row['kode'], $row['barang'], (int) $row['jumlah'], $row['satuan'], $type, $row['nama_petugas'] ?: $row['operator'], $details]);
            }
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Berkas laporan CSV tidak dapat disiapkan.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        $headers = ['Jenis data', 'ID', 'Tanggal', 'Gudang', 'Kode barang', 'Nama barang', 'Jumlah / nilai', 'Satuan', 'Status', 'Petugas', 'Keterangan'];
        fputcsv($stream, $headers, ',', '"', '', "\r\n");
        foreach ($rows as $row) {
            $safeRow = array_map(static function (mixed $value): mixed {
                if (! is_string($value)) {
                    return $value ?? '';
                }

                return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
            }, $row);
            fputcsv($stream, $safeRow, ',', '"', '', "\r\n");
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        $filename = 'laporan-'.$reportType.'-'.$start.'-sampai-'.$end.'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    protected function reportAscii(?string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value ?? '');
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
    }

    protected function reportEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    protected function reportWrap(?string $value, int $width): array
    {
        $value = trim($this->reportAscii($value));

        return $value === '' ? ['-'] : explode("\n", wordwrap($value, max(1, $width), "\n", true));
    }

    protected function reportPdfText(array &$commands, float $x, float $y, string $text, string $font, int $size, array $color): void
    {
        $rgb = implode(' ', array_map(static fn (float $channel): string => number_format($channel, 3, '.', ''), $color));
        $commands[] = 'BT /'.$font.' '.$size.' Tf '.$rgb.' rg '.number_format($x, 2, '.', '').' '.number_format($y, 2, '.', '').' Td ('.$this->reportEscape($this->reportAscii($text)).") Tj ET\n";
    }

    protected function reportPdfRect(array &$commands, float $x, float $y, float $width, float $height, array $fill, ?array $stroke = null, float $strokeWidth = 0.5): void
    {
        $fillRgb = implode(' ', array_map(static fn (float $channel): string => number_format($channel, 3, '.', ''), $fill));
        $command = 'q '.$fillRgb.' rg '.number_format($x, 2, '.', '').' '.number_format($y, 2, '.', '').' '.number_format($width, 2, '.', '').' '.number_format($height, 2, '.', '').' re f';
        if ($stroke !== null) {
            $strokeRgb = implode(' ', array_map(static fn (float $channel): string => number_format($channel, 3, '.', ''), $stroke));
            $command .= ' '.$strokeRgb.' RG '.number_format($strokeWidth, 2, '.', '').' w '.number_format($x, 2, '.', '').' '.number_format($y, 2, '.', '').' '.number_format($width, 2, '.', '').' '.number_format($height, 2, '.', '').' re S';
        }
        $commands[] = $command." Q\n";
    }

    protected function reportPdfLine(array &$commands, float $x1, float $y1, float $x2, float $y2, array $color, float $width = 0.5): void
    {
        $rgb = implode(' ', array_map(static fn (float $channel): string => number_format($channel, 3, '.', ''), $color));
        $commands[] = 'q '.$rgb.' RG '.number_format($width, 2, '.', '').' w '.number_format($x1, 2, '.', '').' '.number_format($y1, 2, '.', '').' m '.number_format($x2, 2, '.', '').' '.number_format($y2, 2, '.', '').' l S Q'."\n";
    }

    protected function reportTableHeader(array &$commands, float $top, array $columns, array $navy, array $white, array $grid): float
    {
        $height = 26.0;
        $this->reportPdfRect($commands, 36, $top - $height, 770, $height, $navy);
        $x = 36.0;
        foreach ($columns as $column) {
            $this->reportPdfText($commands, $x + 6, $top - 17, (string) $column[0], 'F2', 7, $white);
            $x += (float) $column[1];
            if ($x < 806) {
                $this->reportPdfLine($commands, $x, $top - $height, $x, $top, $grid, 0.45);
            }
        }

        return $top - $height;
    }

    protected function reportPrepareRow(array $columns, array $values): array
    {
        $cells = [];
        $maxLines = 1;
        foreach ($columns as $index => $column) {
            $cells[] = $this->reportWrap((string) ($values[$index] ?? ''), (int) $column[2]);
            $maxLines = max($maxLines, count($cells[count($cells) - 1]));
        }

        return ['cells' => $cells, 'height' => max(21.0, 10.0 + ($maxLines * 8.2))];
    }

    protected function reportDrawRow(array &$commands, float $top, array $columns, array $prepared, int $rowNumber, array $white, array $alternate, array $grid, array $textColor): float
    {
        $height = $prepared['height'];
        $bottom = $top - $height;
        $this->reportPdfRect($commands, 36, $bottom, 770, $height, $rowNumber % 2 === 0 ? $alternate : $white, $grid, 0.45);
        $x = 36.0;
        foreach ($columns as $index => $column) {
            foreach ($prepared['cells'][$index] as $lineIndex => $value) {
                $this->reportPdfText($commands, $x + 6, $top - 11 - ($lineIndex * 8.2), $value, 'F1', 8, $textColor);
            }
            $x += (float) $column[1];
            if ($x < 806) {
                $this->reportPdfLine($commands, $x, $bottom, $x, $top, $grid, 0.4);
            }
        }

        return $bottom;
    }
}
