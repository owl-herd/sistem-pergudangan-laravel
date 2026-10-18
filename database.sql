-- GudangKita: skema aplikasi gudang pusat dan gudang proyek.
-- Untuk upgrade database lama, baca bagian migrasi di README.md dan cadangkan database terlebih dahulu.
CREATE DATABASE IF NOT EXISTS sistem_pergudangan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistem_pergudangan;

CREATE TABLE IF NOT EXISTS gudang (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(40) NOT NULL UNIQUE,
  nama VARCHAR(160) NOT NULL,
  jenis ENUM('pusat','proyek') NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_gudang_jenis_aktif (jenis,aktif,nama)
) ENGINE=InnoDB;
INSERT IGNORE INTO gudang (kode,nama,jenis) VALUES ('PUSAT','Gudang Pusat','pusat');

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('pengguna','admin_pusat','admin_gudang','manager','purchasing','super_admin') NOT NULL,
  gudang_id BIGINT UNSIGNED NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_gudang FOREIGN KEY (gudang_id) REFERENCES gudang(id),
  INDEX idx_users_role_aktif (role,aktif),
  INDEX idx_users_gudang (gudang_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS riwayat_pengelolaan_akun (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pelaku_id BIGINT UNSIGNED NULL,
  pelaku_nama VARCHAR(120) NOT NULL,
  pelaku_email VARCHAR(190) NULL,
  sasaran_id BIGINT UNSIGNED NOT NULL,
  sasaran_nama VARCHAR(120) NOT NULL,
  sasaran_email VARCHAR(190) NOT NULL,
  aksi ENUM('bootstrap','akun_dibuat','akun_diperbarui','role_diubah','akun_dinonaktifkan','akun_diaktifkan') NOT NULL,
  detail VARCHAR(1000) NOT NULL,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_riwayat_akun_pelaku FOREIGN KEY (pelaku_id) REFERENCES users(id),
  CONSTRAINT fk_riwayat_akun_sasaran FOREIGN KEY (sasaran_id) REFERENCES users(id),
  INDEX idx_riwayat_akun_sasaran (sasaran_id,dibuat_pada),
  INDEX idx_riwayat_akun_pelaku (pelaku_id,dibuat_pada)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS manager_gudang (
  manager_id BIGINT UNSIGNED NOT NULL,
  gudang_id BIGINT UNSIGNED NOT NULL,
  ditugaskan_oleh BIGINT UNSIGNED NOT NULL,
  ditugaskan_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (manager_id,gudang_id),
  CONSTRAINT fk_manager_gudang_manager FOREIGN KEY (manager_id) REFERENCES users(id),
  CONSTRAINT fk_manager_gudang_gudang FOREIGN KEY (gudang_id) REFERENCES gudang(id),
  CONSTRAINT fk_manager_gudang_admin FOREIGN KEY (ditugaskan_oleh) REFERENCES users(id),
  INDEX idx_manager_gudang_gudang (gudang_id,manager_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS barang (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(40) NOT NULL UNIQUE,
  nama VARCHAR(160) NOT NULL,
  kategori VARCHAR(100) NULL,
  kategori_barang ENUM('barang_siap','barang_dipakai','peralatan') NOT NULL DEFAULT 'peralatan',
  satuan VARCHAR(30) NOT NULL DEFAULT 'unit',
  stok INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Stok lama; setelah migrasi stok aktif ada di stok_gudang',
  stok_minimum INT UNSIGNED NOT NULL DEFAULT 0,
  deskripsi TEXT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_barang_aktif_nama (aktif,nama),
  INDEX idx_barang_kategori_aktif (kategori_barang,aktif,nama)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS nomor_kode_barang (
  kategori_barang VARCHAR(30) PRIMARY KEY,
  nomor_terakhir INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO nomor_kode_barang (kategori_barang,nomor_terakhir)
SELECT jenis.kategori,COALESCE(MAX(CAST(SUBSTRING(b.kode,4) AS UNSIGNED)),0)
FROM (SELECT 'barang_siap' kategori,'MP' prefix UNION ALL SELECT 'barang_dipakai','MH' UNION ALL SELECT 'peralatan','PR') jenis
LEFT JOIN barang b ON b.kode REGEXP CONCAT('^',jenis.prefix,'-[0-9]+$')
GROUP BY jenis.kategori
ON DUPLICATE KEY UPDATE nomor_terakhir=GREATEST(nomor_terakhir,VALUES(nomor_terakhir));

CREATE TABLE IF NOT EXISTS permintaan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  kategori_barang ENUM('barang_siap','barang_dipakai','peralatan') NOT NULL DEFAULT 'peralatan',
  status ENUM('diajukan','disetujui','diproses','selesai','ditolak') NOT NULL DEFAULT 'diajukan',
  catatan TEXT NULL,
  gudang_asal_id BIGINT UNSIGNED NULL,
  gudang_tujuan_id BIGINT UNSIGNED NULL,
  tanggal_pengeluaran DATE NULL,
  nomor_dokumen VARCHAR(50) NULL,
  nama_petugas VARCHAR(150) NULL,
  nama_proyek VARCHAR(160) NULL,
  nomor_kendaraan VARCHAR(40) NULL,
  nama_driver VARCHAR(120) NULL,
  nama_pengambil VARCHAR(120) NULL,
  diproses_oleh BIGINT UNSIGNED NULL,
  tanggal TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diproses_pada DATETIME NULL,
  selesai_terkirim_pada DATETIME NULL,
  selesai_terkirim_oleh BIGINT UNSIGNED NULL,
  CONSTRAINT fk_permintaan_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_permintaan_admin FOREIGN KEY (diproses_oleh) REFERENCES users(id),
  CONSTRAINT fk_permintaan_pengiriman_admin FOREIGN KEY (selesai_terkirim_oleh) REFERENCES users(id),
  CONSTRAINT fk_permintaan_gudang_asal FOREIGN KEY (gudang_asal_id) REFERENCES gudang(id),
  CONSTRAINT fk_permintaan_gudang_tujuan FOREIGN KEY (gudang_tujuan_id) REFERENCES gudang(id),
  INDEX idx_permintaan_status_tanggal (status,tanggal),
  INDEX idx_permintaan_user_tanggal (user_id,tanggal),
  INDEX idx_permintaan_gudang_tujuan (gudang_tujuan_id,status,tanggal),
  UNIQUE INDEX uq_permintaan_nomor_dokumen (nomor_dokumen)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_permintaan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permintaan_id BIGINT UNSIGNED NOT NULL,
  barang_id BIGINT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  CONSTRAINT fk_detail_permintaan FOREIGN KEY (permintaan_id) REFERENCES permintaan(id) ON DELETE CASCADE,
  CONSTRAINT fk_detail_barang FOREIGN KEY (barang_id) REFERENCES barang(id),
  UNIQUE KEY uq_permintaan_barang (permintaan_id,barang_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permintaan_pembelian (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  barang_id BIGINT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  catatan TEXT NULL,
  status ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  diproses_oleh BIGINT UNSIGNED NULL,
  tanggal TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diproses_pada DATETIME NULL,
  CONSTRAINT fk_permintaan_pembelian_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_permintaan_pembelian_barang FOREIGN KEY (barang_id) REFERENCES barang(id),
  CONSTRAINT fk_permintaan_pembelian_manager FOREIGN KEY (diproses_oleh) REFERENCES users(id),
  INDEX idx_permintaan_pembelian_status_tanggal (status,tanggal),
  INDEX idx_permintaan_pembelian_user_tanggal (user_id,tanggal)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pengembalian (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('diajukan','diterima','ditolak') NOT NULL DEFAULT 'diajukan',
  catatan TEXT NULL,
  diproses_oleh BIGINT UNSIGNED NULL,
  tanggal TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diproses_pada DATETIME NULL,
  CONSTRAINT fk_pengembalian_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_pengembalian_admin FOREIGN KEY (diproses_oleh) REFERENCES users(id),
  INDEX idx_pengembalian_status_tanggal (status,tanggal),
  INDEX idx_pengembalian_user_tanggal (user_id,tanggal)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_pengembalian (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengembalian_id BIGINT UNSIGNED NOT NULL,
  detail_permintaan_id BIGINT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  CONSTRAINT fk_detail_kembali FOREIGN KEY (pengembalian_id) REFERENCES pengembalian(id) ON DELETE CASCADE,
  CONSTRAINT fk_detail_kembali_pinjaman FOREIGN KEY (detail_permintaan_id) REFERENCES detail_permintaan(id),
  INDEX idx_detail_kembali_pinjaman (detail_permintaan_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stok_gudang (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gudang_id BIGINT UNSIGNED NOT NULL,
  barang_id BIGINT UNSIGNED NOT NULL,
  stok INT UNSIGNED NOT NULL DEFAULT 0,
  diperbarui_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_stok_gudang_gudang FOREIGN KEY (gudang_id) REFERENCES gudang(id),
  CONSTRAINT fk_stok_gudang_barang FOREIGN KEY (barang_id) REFERENCES barang(id),
  UNIQUE KEY uq_stok_gudang_barang (gudang_id,barang_id),
  INDEX idx_stok_barang (barang_id,gudang_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pengiriman (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permintaan_id BIGINT UNSIGNED NOT NULL UNIQUE,
  gudang_asal_id BIGINT UNSIGNED NOT NULL,
  gudang_tujuan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('dalam_perjalanan','diterima','diterima_dengan_selisih') NOT NULL DEFAULT 'dalam_perjalanan',
  dikirim_oleh BIGINT UNSIGNED NOT NULL,
  dikirim_pada DATETIME NOT NULL,
  diterima_oleh BIGINT UNSIGNED NULL,
  diterima_pada DATETIME NULL,
  catatan_penerimaan TEXT NULL,
  CONSTRAINT fk_pengiriman_permintaan FOREIGN KEY (permintaan_id) REFERENCES permintaan(id),
  CONSTRAINT fk_pengiriman_asal FOREIGN KEY (gudang_asal_id) REFERENCES gudang(id),
  CONSTRAINT fk_pengiriman_tujuan FOREIGN KEY (gudang_tujuan_id) REFERENCES gudang(id),
  CONSTRAINT fk_pengiriman_admin_kirim FOREIGN KEY (dikirim_oleh) REFERENCES users(id),
  CONSTRAINT fk_pengiriman_admin_terima FOREIGN KEY (diterima_oleh) REFERENCES users(id),
  INDEX idx_pengiriman_tujuan_status (gudang_tujuan_id,status,dikirim_pada)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_pengiriman (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengiriman_id BIGINT UNSIGNED NOT NULL,
  detail_permintaan_id BIGINT UNSIGNED NOT NULL,
  barang_id BIGINT UNSIGNED NOT NULL,
  jumlah_dikirim INT UNSIGNED NOT NULL,
  jumlah_diterima INT UNSIGNED NULL,
  CONSTRAINT fk_detail_pengiriman FOREIGN KEY (pengiriman_id) REFERENCES pengiriman(id),
  CONSTRAINT fk_detail_pengiriman_permintaan FOREIGN KEY (detail_permintaan_id) REFERENCES detail_permintaan(id),
  CONSTRAINT fk_detail_pengiriman_barang FOREIGN KEY (barang_id) REFERENCES barang(id),
  UNIQUE KEY uq_pengiriman_detail_permintaan (pengiriman_id,detail_permintaan_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaksi_stok (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barang_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  nama_petugas VARCHAR(150) NULL,
  jenis ENUM('masuk','keluar','kembali') NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  referensi VARCHAR(255) NULL,
  nomor_transaksi VARCHAR(50) NULL,
  nama_supplier VARCHAR(150) NULL,
  tujuan_keluar ENUM('permintaan','proyek','retur_supplier','internal') NULL,
  satuan_transaksi VARCHAR(30) NULL,
  tanggal TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transaksi_barang FOREIGN KEY (barang_id) REFERENCES barang(id),
  CONSTRAINT fk_transaksi_user FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE INDEX uq_transaksi_stok_nomor (nomor_transaksi),
  INDEX idx_transaksi_tanggal_jenis (tanggal,jenis),
  INDEX idx_transaksi_barang_tanggal (barang_id,tanggal)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaksi_gudang (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barang_id BIGINT UNSIGNED NOT NULL,
  gudang_id BIGINT UNSIGNED NOT NULL,
  gudang_asal_id BIGINT UNSIGNED NULL,
  gudang_tujuan_id BIGINT UNSIGNED NULL,
  permintaan_id BIGINT UNSIGNED NULL,
  pengiriman_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  jenis ENUM('masuk','keluar','kembali','pemakaian') NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  referensi VARCHAR(255) NULL,
  nomor_transaksi VARCHAR(50) NULL,
  nama_supplier VARCHAR(150) NULL,
  tujuan_keluar ENUM('permintaan','proyek','retur_supplier','internal') NULL,
  satuan_transaksi VARCHAR(30) NULL,
  nama_petugas VARCHAR(150) NULL,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  transaksi_lama_id BIGINT UNSIGNED NULL UNIQUE,
  UNIQUE KEY uq_transaksi_gudang_nomor (nomor_transaksi),
  CONSTRAINT fk_transaksi_gudang_barang FOREIGN KEY (barang_id) REFERENCES barang(id),
  CONSTRAINT fk_transaksi_gudang_gudang FOREIGN KEY (gudang_id) REFERENCES gudang(id),
  CONSTRAINT fk_transaksi_gudang_asal FOREIGN KEY (gudang_asal_id) REFERENCES gudang(id),
  CONSTRAINT fk_transaksi_gudang_tujuan FOREIGN KEY (gudang_tujuan_id) REFERENCES gudang(id),
  CONSTRAINT fk_transaksi_gudang_permintaan FOREIGN KEY (permintaan_id) REFERENCES permintaan(id),
  CONSTRAINT fk_transaksi_gudang_pengiriman FOREIGN KEY (pengiriman_id) REFERENCES pengiriman(id),
  CONSTRAINT fk_transaksi_gudang_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_transaksi_gudang_tanggal (gudang_id,tanggal,jenis),
  INDEX idx_transaksi_gudang_permintaan (permintaan_id),
  INDEX idx_transaksi_gudang_pengiriman (pengiriman_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penerimaan_pembelian (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permintaan_pembelian_id BIGINT UNSIGNED NOT NULL,
  barang_id BIGINT UNSIGNED NOT NULL,
  gudang_id BIGINT UNSIGNED NOT NULL,
  dicatat_oleh BIGINT UNSIGNED NOT NULL,
  token_form CHAR(64) NOT NULL,
  kode_barang VARCHAR(40) NOT NULL,
  nama_barang VARCHAR(160) NOT NULL,
  satuan_barang VARCHAR(30) NOT NULL,
  nama_gudang VARCHAR(160) NOT NULL,
  nama_petugas VARCHAR(120) NOT NULL,
  jumlah_diterima INT UNSIGNED NOT NULL,
  jumlah_layak INT UNSIGNED NOT NULL,
  jumlah_rusak INT UNSIGNED NOT NULL DEFAULT 0,
  jumlah_tidak_sesuai INT UNSIGNED NOT NULL DEFAULT 0,
  catatan_kondisi TEXT NULL,
  diterima_pada DATETIME NOT NULL,
  transaksi_gudang_id BIGINT UNSIGNED NULL UNIQUE,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_penerimaan_pembelian_permintaan FOREIGN KEY (permintaan_pembelian_id) REFERENCES permintaan_pembelian(id),
  CONSTRAINT fk_penerimaan_pembelian_barang FOREIGN KEY (barang_id) REFERENCES barang(id),
  CONSTRAINT fk_penerimaan_pembelian_gudang FOREIGN KEY (gudang_id) REFERENCES gudang(id),
  CONSTRAINT fk_penerimaan_pembelian_petugas FOREIGN KEY (dicatat_oleh) REFERENCES users(id),
  CONSTRAINT fk_penerimaan_pembelian_transaksi FOREIGN KEY (transaksi_gudang_id) REFERENCES transaksi_gudang(id),
  UNIQUE KEY uq_penerimaan_pembelian_token (token_form),
  INDEX idx_penerimaan_pembelian_permintaan_waktu (permintaan_pembelian_id,diterima_pada,id),
  INDEX idx_penerimaan_pembelian_gudang_waktu (gudang_id,diterima_pada)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penerimaan_pembelian_foto (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  penerimaan_pembelian_id BIGINT UNSIGNED NOT NULL,
  lokasi_file VARCHAR(80) NOT NULL UNIQUE,
  mime_type ENUM('image/jpeg','image/png','image/webp') NOT NULL,
  ukuran_bytes INT UNSIGNED NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_penerimaan_pembelian_foto_penerimaan FOREIGN KEY (penerimaan_pembelian_id) REFERENCES penerimaan_pembelian(id) ON DELETE CASCADE,
  UNIQUE KEY uq_penerimaan_pembelian_foto_urutan (penerimaan_pembelian_id,urutan),
  INDEX idx_penerimaan_pembelian_foto_penerimaan (penerimaan_pembelian_id,id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penambahan_barang_proyek (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gudang_id BIGINT UNSIGNED NOT NULL,
  barang_id BIGINT UNSIGNED NOT NULL,
  kode_barang VARCHAR(40) NOT NULL,
  nama_barang VARCHAR(160) NOT NULL,
  satuan_barang VARCHAR(30) NOT NULL,
  nama_proyek VARCHAR(160) NOT NULL,
  nama_penambah VARCHAR(120) NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  catatan VARCHAR(255) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  transaksi_gudang_id BIGINT UNSIGNED NOT NULL UNIQUE,
  ditambahkan_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_penambahan_proyek_gudang FOREIGN KEY (gudang_id) REFERENCES gudang(id),
  CONSTRAINT fk_penambahan_proyek_barang FOREIGN KEY (barang_id) REFERENCES barang(id),
  CONSTRAINT fk_penambahan_proyek_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_penambahan_proyek_transaksi FOREIGN KEY (transaksi_gudang_id) REFERENCES transaksi_gudang(id),
  INDEX idx_penambahan_proyek_waktu (gudang_id,ditambahkan_pada)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pengembalian_peralatan_proyek (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barang_id BIGINT UNSIGNED NOT NULL,
  kode_barang VARCHAR(40) NOT NULL,
  nama_barang VARCHAR(160) NOT NULL,
  satuan_barang VARCHAR(30) NOT NULL,
  nama_proyek VARCHAR(160) NOT NULL,
  nama_admin_proyek VARCHAR(120) NOT NULL,
  gudang_asal_id BIGINT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  catatan_kondisi TEXT NOT NULL,
  status ENUM('menunggu_diterima','diterima') NOT NULL DEFAULT 'menunggu_diterima',
  dikirim_oleh BIGINT UNSIGNED NOT NULL,
  dikirim_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diterima_oleh BIGINT UNSIGNED NULL,
  diterima_pada DATETIME NULL,
  transaksi_keluar_id BIGINT UNSIGNED NULL UNIQUE,
  transaksi_kembali_id BIGINT UNSIGNED NULL UNIQUE,
  CONSTRAINT fk_pengembalian_peralatan_barang FOREIGN KEY (barang_id) REFERENCES barang(id),
  CONSTRAINT fk_pengembalian_peralatan_gudang FOREIGN KEY (gudang_asal_id) REFERENCES gudang(id),
  CONSTRAINT fk_pengembalian_peralatan_pengirim FOREIGN KEY (dikirim_oleh) REFERENCES users(id),
  CONSTRAINT fk_pengembalian_peralatan_penerima FOREIGN KEY (diterima_oleh) REFERENCES users(id),
  CONSTRAINT fk_pengembalian_peralatan_keluar FOREIGN KEY (transaksi_keluar_id) REFERENCES transaksi_gudang(id),
  CONSTRAINT fk_pengembalian_peralatan_kembali FOREIGN KEY (transaksi_kembali_id) REFERENCES transaksi_gudang(id),
  INDEX idx_pengembalian_peralatan_status (status,dikirim_pada),
  INDEX idx_pengembalian_peralatan_proyek (gudang_asal_id,dikirim_pada)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pemetaan_akun_lama (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  role_lama ENUM('admin_gudang','pengguna') NOT NULL,
  aktif_sebelumnya TINYINT(1) NOT NULL,
  status ENUM('menunggu_konfirmasi','dipetakan_pusat','dipetakan_proyek','pengguna_lama_nonaktif') NOT NULL DEFAULT 'menunggu_konfirmasi',
  gudang_id BIGINT UNSIGNED NULL,
  dipetakan_oleh BIGINT UNSIGNED NULL,
  dipetakan_pada DATETIME NULL,
  catatan VARCHAR(255) NULL,
  CONSTRAINT fk_pemetaan_akun_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_pemetaan_akun_gudang FOREIGN KEY (gudang_id) REFERENCES gudang(id),
  CONSTRAINT fk_pemetaan_akun_admin FOREIGN KEY (dipetakan_oleh) REFERENCES users(id),
  INDEX idx_pemetaan_akun_status (status,user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS riwayat_hapus_barang (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barang_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  stok_saat_dihapus INT UNSIGNED NOT NULL,
  catatan TEXT NOT NULL,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_riwayat_hapus_barang FOREIGN KEY (barang_id) REFERENCES barang(id),
  CONSTRAINT fk_riwayat_hapus_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_riwayat_hapus_barang_tanggal (barang_id,dibuat_pada)
) ENGINE=InnoDB;

INSERT IGNORE INTO users (nama,email,password_hash,role) VALUES
('Administrator Gudang Pusat','admin@gudangkita.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.','admin_pusat'),
('Manajer Operasional','manager@gudangkita.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.','manager');

-- Katalog barang tidak menambahkan data contoh.

-- Pemetaan stok dan riwayat lama ke Gudang Pusat bersifat idempoten.
INSERT IGNORE INTO stok_gudang (gudang_id,barang_id,stok)
SELECT g.id,b.id,b.stok FROM gudang g CROSS JOIN barang b WHERE g.kode='PUSAT';

INSERT IGNORE INTO transaksi_gudang
  (barang_id,gudang_id,gudang_asal_id,gudang_tujuan_id,user_id,jenis,jumlah,referensi,nomor_transaksi,nama_supplier,tujuan_keluar,satuan_transaksi,nama_petugas,tanggal,transaksi_lama_id)
SELECT
  t.barang_id,g.id,
  CASE WHEN t.jenis='keluar' THEN g.id ELSE NULL END,
  CASE WHEN t.jenis IN ('masuk','kembali') THEN g.id ELSE NULL END,
  t.user_id,t.jenis,t.jumlah,t.referensi,t.nomor_transaksi,t.nama_supplier,t.tujuan_keluar,
  COALESCE(t.satuan_transaksi,b.satuan),t.nama_petugas,t.tanggal,t.id
FROM transaksi_stok t
JOIN barang b ON b.id=t.barang_id
JOIN gudang g ON g.kode='PUSAT';

-- Akun legacy tidak mendapat akses sebelum klasifikasi perusahaan.
INSERT IGNORE INTO pemetaan_akun_lama (user_id,role_lama,aktif_sebelumnya,status)
SELECT id,role,aktif,'menunggu_konfirmasi'
FROM users WHERE role='admin_gudang' AND gudang_id IS NULL;

INSERT IGNORE INTO pemetaan_akun_lama (user_id,role_lama,aktif_sebelumnya,status)
SELECT id,role,aktif,'pengguna_lama_nonaktif'
FROM users WHERE role='pengguna';

UPDATE users u
JOIN pemetaan_akun_lama p ON p.user_id=u.id
SET u.aktif=0,u.gudang_id=NULL
WHERE u.role='admin_gudang' AND p.status='menunggu_konfirmasi';

UPDATE users SET aktif=0,gudang_id=NULL WHERE role='pengguna';

-- Untuk upgrade, jalankan ALTER satu kali sesuai urutan di README.md.
-- CREATE TABLE IF NOT EXISTS tidak menambah atau mengubah kolom pada tabel yang sudah ada.
--
-- BLOK MIGRASI MANUAL UNTUK DATABASE LAMA (backup dahulu; jangan jalankan ulang).
-- Jalankan tahap A sebelum mengimpor sisa database.sql:
-- ALTER TABLE users
--   MODIFY role ENUM('pengguna','admin_pusat','admin_gudang','manager','purchasing','super_admin') NOT NULL,
--   ADD COLUMN gudang_id BIGINT UNSIGNED NULL AFTER role,
--   ADD INDEX idx_users_gudang (gudang_id);
-- ALTER TABLE permintaan
--   ADD COLUMN gudang_asal_id BIGINT UNSIGNED NULL AFTER catatan,
--   ADD COLUMN gudang_tujuan_id BIGINT UNSIGNED NULL AFTER gudang_asal_id,
--   ADD INDEX idx_permintaan_gudang_tujuan (gudang_tujuan_id,status,tanggal);
-- Setelah tabel baru dibuat, jalankan tahap B:
-- ALTER TABLE users
--   ADD CONSTRAINT fk_users_gudang FOREIGN KEY (gudang_id) REFERENCES gudang(id);
-- ALTER TABLE permintaan
--   ADD CONSTRAINT fk_permintaan_gudang_asal FOREIGN KEY (gudang_asal_id) REFERENCES gudang(id),
--   ADD CONSTRAINT fk_permintaan_gudang_tujuan FOREIGN KEY (gudang_tujuan_id) REFERENCES gudang(id);
-- Untuk instalasi multi-gudang yang sudah berjalan, jalankan sekali sebelum mengimpor:
-- ALTER TABLE users MODIFY role ENUM('pengguna','admin_pusat','admin_gudang','manager','purchasing','super_admin') NOT NULL;


-- PENYESUAIAN KODE BARANG LAMA KE URUTAN PER JENIS (sekali saja; backup dahulu).
-- Buat dan isi nomor_kode_barang menggunakan blok di atas terlebih dahulu.
-- Jalankan hanya perintah berikut setelah menghapus awalan komentar.
-- START TRANSACTION;
-- SELECT id FROM barang ORDER BY id FOR UPDATE;
-- SELECT kategori_barang FROM nomor_kode_barang ORDER BY kategori_barang FOR UPDATE;
-- CREATE TEMPORARY TABLE penyesuaian_kode_barang AS SELECT b.id, CONCAT(CASE b.kategori_barang WHEN 'barang_siap' THEN 'MP' WHEN 'barang_dipakai' THEN 'MH' WHEN 'peralatan' THEN 'PR' END,'-',LPAD(CAST((SELECT COUNT(*) FROM barang sebelumnya WHERE sebelumnya.kategori_barang=b.kategori_barang AND sebelumnya.id<=b.id) AS CHAR),6,'0')) kode FROM barang b;
-- UPDATE barang SET kode=CONCAT('TMP-',UUID());
-- UPDATE barang b JOIN penyesuaian_kode_barang p ON p.id=b.id SET b.kode=p.kode;
-- UPDATE nomor_kode_barang n SET nomor_terakhir=(SELECT COUNT(*) FROM barang b WHERE b.kategori_barang=n.kategori_barang);
-- DROP TEMPORARY TABLE penyesuaian_kode_barang;
-- COMMIT;

-- GANTI AWALAN MATERIAL PROJECT (sekali saja; backup dahulu).
-- Mempertahankan nomor urut dan penghitung jenis barang_siap.
-- START TRANSACTION;
-- SELECT id FROM barang ORDER BY id FOR UPDATE;
-- SELECT kategori_barang FROM nomor_kode_barang ORDER BY kategori_barang FOR UPDATE;
-- UPDATE barang SET kode=CONCAT('MP-',SUBSTRING(kode,4)) WHERE kategori_barang='barang_siap' AND kode REGEXP '^BS-[0-9]+$';
-- COMMIT;

-- Profil tambahan pada proyek Laravel; tabel lama tetap dipertahankan.
CREATE TABLE IF NOT EXISTS user_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  photo_path VARCHAR(255) NULL,
  CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
