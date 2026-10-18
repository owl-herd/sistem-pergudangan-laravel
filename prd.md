# Product Requirements Document (PRD)

## Navigasi Dokumentasi

- [AGENTS](AGENTS.md) — aturan wajib proyek
- [Architecture](architecture.md) — rancangan teknis sistem
- [Skill Guide](skill.md) — panduan kerja agent/developer
- [Workflow](workflow.md) — alur kerja pengembangan
- [TODO](todo.md) — daftar pekerjaan dan status

## 1. Informasi Dokumen

- **Produk:** Sistem Pergudangan
- **Dokumen:** Product Requirements Document
- **Versi:** 1.0
- **Tanggal:** 8 Oktober 2026
- **Status:** Draft

## 2. Ringkasan Produk

Sistem Pergudangan adalah aplikasi web untuk membantu pengelolaan persediaan barang, aktivitas gudang, pengguna, serta dokumentasi transaksi secara terpusat. Sistem mempertahankan alur bisnis dari aplikasi sumber, termasuk struktur data, peran pengguna, aturan stok, endpoint legacy berakhiran `.php`, dan tampilan berbasis Bootstrap.

Produk ditujukan untuk mengurangi pencatatan manual, meningkatkan akurasi stok, memperjelas riwayat pergerakan barang, dan membatasi akses sesuai tanggung jawab setiap pengguna.

## 3. Latar Belakang dan Masalah

Operasional gudang membutuhkan pencatatan barang masuk, barang keluar, dan kondisi stok yang konsisten. Tanpa sistem terpusat, masalah yang umum terjadi adalah:

- jumlah stok sulit dipastikan secara cepat;
- transaksi dan perubahan stok sulit ditelusuri;
- akses terhadap data atau berkas gudang tidak terkontrol;
- proses kerja bergantung pada pencatatan manual;
- laporan operasional membutuhkan waktu untuk disusun.

## 4. Tujuan Produk

1. Menyediakan sumber data persediaan yang konsisten.
2. Mencatat pergerakan stok secara terstruktur dan dapat ditelusuri.
3. Mendukung pekerjaan pengguna gudang sesuai peran dan hak aksesnya.
4. Mempertahankan kompatibilitas dengan perilaku bisnis aplikasi sumber.
5. Melindungi data operasional, foto, dan berkas privat dari akses tanpa izin.

## 5. Sasaran Pengguna

### 5.1 Administrator

Mengelola pengguna, peran, master data, serta memantau aktivitas sistem.

### 5.2 Petugas Gudang

Mengelola data barang dan mencatat aktivitas penerimaan, pengeluaran, atau penyesuaian stok sesuai kewenangan.

### 5.3 Pengawas atau Manajemen

Melihat kondisi persediaan, riwayat transaksi, dan informasi operasional untuk kebutuhan pengawasan dan pengambilan keputusan.

## 6. Ruang Lingkup MVP

### 6.1 Autentikasi dan Otorisasi

- Login dan logout pengguna.
- Penyimpanan password menggunakan hash yang kompatibel dengan aplikasi sumber.
- Pembatasan akses berdasarkan role.
- Pemeriksaan akses melalui `AccessService`.
- Perlindungan seluruh aksi perubahan data menggunakan CSRF.

### 6.2 Dashboard

- Ringkasan kondisi gudang.
- Informasi jumlah atau status stok yang relevan.
- Menandai barang yang stoknya berada pada atau di bawah batas minimum.
- Akses cepat ke fungsi operasional utama.
- Tampilan responsif berbasis Bootstrap.

### 6.3 Master Data Barang

- Menampilkan daftar barang.
- Melihat detail barang.
- Menambah, mengubah, dan menghapus data sesuai kewenangan.
- Menyimpan identitas, atribut, dan informasi pendukung barang sesuai skema sumber.
- Mendukung foto atau berkas barang bila tersedia.

### 6.4 Persediaan dan Pergerakan Stok

- Mencatat barang masuk.
- Mencatat barang keluar.
- Menampilkan saldo stok.
- Mendukung stock opname dengan pencatatan stok sistem, hasil hitung fisik, selisih, alasan, petugas, dan waktu.
- Membatasi penyesuaian stok kepada Admin Pusat untuk Gudang Pusat dan Admin Gudang untuk gudang proyeknya sendiri.
- Mencegah transaksi yang melanggar aturan stok sumber, termasuk pengeluaran melebihi stok tersedia bila aturan sumber melarangnya.
- Menyediakan riwayat perubahan stok dan stock opname.
- Menjaga konsistensi antara transaksi dan saldo persediaan.

### 6.5 Pencarian dan Penyaringan

- Mencari barang berdasarkan informasi yang relevan.
- Menyaring data berdasarkan status, kategori, periode, atau atribut yang tersedia.
- Menampilkan hasil dengan jelas pada perangkat desktop maupun layar yang lebih kecil.

### 6.6 Berkas dan Foto Privat

- Menyimpan foto dan berkas privat pada penyimpanan privat.
- Melayani berkas hanya setelah pemeriksaan autentikasi dan otorisasi.
- Tidak mengekspos path penyimpanan privat secara langsung.

### 6.7 Kompatibilitas Legacy

- Mempertahankan nama tabel, ID, role, endpoint `.php`, dan perilaku bisnis aplikasi sumber.
- Memindahkan alur prosedural sumber ke layanan `WarehousePages` dan `WarehouseActions` tanpa mengubah hasil bisnis.

### 6.8 Laporan dan Audit Aktivitas

- Manajer dapat melihat dan mengunduh laporan PDF atau CSV dengan jenis laporan dan periode yang dipilih.
- CSV menggunakan kolom konsisten untuk jenis laporan yang dipilih; nilai teks yang dapat ditafsirkan sebagai formula spreadsheet dinetralkan.
- Riwayat aktivitas menyatukan mutasi stok, hasil stock opname, perubahan katalog, dan—khusus Super Admin—perubahan akun.
- Aktivitas dibatasi menurut gudang dan role; tampilan memuat paling banyak 200 aktivitas terbaru untuk rentang tanggal yang dipilih.

## 7. Alur Utama Pengguna

### 7.1 Login dan Akses Dashboard

1. Pengguna membuka halaman login.
2. Pengguna memasukkan kredensial.
3. Sistem memvalidasi kredensial dan role.
4. Sistem mengarahkan pengguna ke halaman yang sesuai.
5. Sistem menolak akses ke halaman atau aksi yang tidak diizinkan.

### 7.2 Pencatatan Pergerakan Barang

1. Petugas membuka fungsi transaksi stok.
2. Petugas memilih barang dan memasukkan jumlah serta informasi transaksi.
3. Sistem memvalidasi input dan aturan stok.
4. Sistem menyimpan transaksi.
5. Sistem memperbarui atau menghitung saldo stok sesuai perilaku aplikasi sumber.
6. Sistem menampilkan hasil dan menyimpan riwayat yang dapat ditelusuri.

### 7.3 Akses Foto atau Berkas

1. Pengguna meminta foto atau berkas.
2. Sistem memeriksa autentikasi dan hak akses.
3. Jika diizinkan, sistem menyajikan berkas dari penyimpanan privat.
4. Jika tidak diizinkan, sistem mengembalikan respons penolakan tanpa membocorkan berkas.

### 7.4 Stock Opname

1. Admin membuka fungsi stock opname untuk gudang yang menjadi kewenangannya.
2. Admin memilih barang, memasukkan jumlah fisik, dan menjelaskan alasan penghitungan.
3. Sistem mengunci saldo terkait dan menghitung selisih terhadap stok sistem.
4. Sistem menyimpan hasil hitung, selisih, perubahan saldo, dan mutasi bila ada dalam satu transaksi.
5. Sistem menampilkan hasil dan menyediakan catatannya pada riwayat aktivitas.

## 8. Persyaratan Fungsional

| ID | Persyaratan | Prioritas |
|---|---|---|
| FR-01 | Sistem menyediakan autentikasi pengguna | Must |
| FR-02 | Sistem menerapkan otorisasi berbasis role | Must |
| FR-03 | Sistem menampilkan dashboard operasional | Must |
| FR-04 | Sistem mengelola master data barang | Must |
| FR-05 | Sistem mencatat barang masuk dan keluar | Must |
| FR-06 | Sistem menerapkan aturan saldo dan validasi stok | Must |
| FR-07 | Sistem menyediakan riwayat pergerakan stok | Must |
| FR-08 | Sistem menyediakan pencarian dan penyaringan | Should |
| FR-09 | Sistem melindungi foto dan berkas privat | Must |
| FR-10 | Sistem mempertahankan endpoint dan perilaku legacy yang diperlukan | Must |
| FR-11 | Sistem menandai stok pada atau di bawah batas minimum | Should |
| FR-12 | Sistem mencatat stock opname dan menerapkan selisih secara atomik | Should |
| FR-13 | Sistem menyediakan ekspor laporan CSV selain PDF | Should |
| FR-14 | Sistem menyediakan riwayat aktivitas stok, katalog, dan pengelolaan akun sesuai izin | Should |

## 9. Persyaratan Nonfungsional

### Keamanan

- Rahasia konfigurasi tidak boleh dimasukkan ke source code, tes, log, atau dokumentasi.
- Semua aksi mutasi data harus dilindungi CSRF.
- Akses harus diperiksa di sisi server.
- Berkas privat tidak boleh dapat diakses tanpa otorisasi.

### Keandalan Data

- Transaksi stok harus konsisten dan dapat ditelusuri.
- Migrasi tambahan hanya dilakukan bila benar-benar diperlukan.
- Database sumber tidak boleh diubah selama pengembangan atau pengujian.
- Pengujian menggunakan database salinan yang ditetapkan, khususnya database `_test`.

### Kompatibilitas

- Aplikasi berjalan pada Laravel 12.
- Nama tabel, ID, hash password, role, endpoint legacy, dan aturan bisnis yang sudah ada tetap kompatibel.

### Usability

- Antarmuka menggunakan Bootstrap yang konsisten dengan aplikasi sumber.
- Pesan validasi dan hasil aksi harus jelas bagi pengguna operasional.
- Halaman penting harus dapat digunakan pada desktop dan layar yang lebih kecil.

### Maintainability

- Logika akses menggunakan `AccessService`.
- Alur halaman dan aksi gudang menggunakan `WarehousePages` dan `WarehouseActions`.
- Perubahan dibuat sekecil mungkin dan disertai tes untuk perilaku yang berubah.

## 10. Kriteria Penerimaan

- Pengguna dengan kredensial valid dapat login dan melihat halaman yang diizinkan.
- Pengguna tanpa role yang sesuai tidak dapat membuka halaman atau menjalankan aksi terlarang.
- Data barang dapat ditampilkan dan dikelola sesuai kewenangan.
- Transaksi stok valid tersimpan dan tercermin pada saldo sesuai aturan sumber.
- Transaksi stok invalid ditolak dengan pesan yang sesuai.
- Stock opname menyimpan jumlah sebelum, hasil fisik, selisih, alasan, petugas, serta mutasi saldo dalam satu transaksi database.
- Pengguna hanya dapat menyesuaikan stok gudang sesuai role dan penugasannya.
- Riwayat transaksi dapat ditelusuri.
- Laporan CSV mengikuti filter dan otorisasi laporan PDF serta aman dibuka sebagai spreadsheet.
- Riwayat aktivitas hanya menampilkan data gudang yang dapat diakses role peminta.
- Foto atau berkas privat hanya dapat diakses oleh pengguna yang berwenang.
- Endpoint legacy yang termasuk cakupan tetap berjalan.
- Test Laravel lulus menggunakan database pengujian yang ditetapkan.
- Laravel Pint lulus pada file PHP yang diubah.

## 11. Di Luar Ruang Lingkup

- Perubahan besar pada struktur database lama tanpa kebutuhan yang disetujui.
- Penggantian aturan bisnis aplikasi sumber.
- Migrasi atau penghapusan data pada database sumber.
- Integrasi layanan eksternal yang belum ditentukan.
- Pengadaan perangkat keras gudang atau sistem barcode khusus.

## 12. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Perbedaan perilaku antara aplikasi sumber dan Laravel | Transaksi tidak konsisten | Gunakan aplikasi sumber sebagai acuan dan tambahkan tes perilaku |
| Akses langsung ke berkas privat | Kebocoran data | Gunakan penyimpanan privat dan pemeriksaan akses server-side |
| Kesalahan perhitungan stok | Laporan persediaan tidak akurat | Validasi aturan stok dan uji transaksi batas |
| Pengujian memakai database yang salah | Data operasional rusak | Pastikan konfigurasi pengujian menunjuk database `_test` |
| Perubahan legacy tidak terdokumentasi | Kompatibilitas terganggu | Catat endpoint, role, tabel, dan aturan yang dipertahankan |

## 13. Strategi Pengujian

- Unit test untuk validasi dan aturan stok.
- Feature test untuk autentikasi, otorisasi, CRUD, dan transaksi.
- Test akses untuk foto atau berkas privat.
- Test kompatibilitas untuk endpoint legacy yang tercakup.
- Jalankan `C:\xampp\php\php.exe artisan test` menggunakan database pengujian.
- Jalankan Laravel Pint pada file PHP yang diubah.
- Jangan menjalankan `migrate:fresh`, `db:wipe`, seeder contoh, atau impor ulang `database.sql` ke database yang sudah terisi.

## 14. Metrik Keberhasilan

- Seluruh alur MVP dapat digunakan tanpa pelanggaran akses.
- Tidak ada transaksi stok yang menghasilkan saldo tidak valid.
- Tes regresi untuk perilaku penting aplikasi sumber lulus.
- Pengguna dapat menemukan informasi stok dan riwayat transaksi dengan cepat.
- Tidak ada berkas privat yang dapat diakses tanpa otorisasi.

## 15. Pertanyaan Terbuka

1. Daftar role final dan matriks izin tiap role perlu dikonfirmasi.
2. Daftar field master barang perlu diselaraskan dengan skema produksi yang berlaku.
3. Kebijakan retensi dan paginasi audit log perlu ditentukan bila volume aktivitas meningkat.
4. Kebutuhan barcode dan integrasi pihak ketiga perlu diprioritaskan pada fase berikutnya.
