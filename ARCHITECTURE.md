# Arsitektur Sistem Pergudangan

## Navigasi Dokumentasi

- [AGENTS](AGENTS.md) — aturan wajib proyek
- [PRD](PRD.md) — kebutuhan dan tujuan produk
- [Skill Guide](SKILL.md) — panduan kerja agent/developer
- [Workflow](WORKFLOW.md) — alur kerja pengembangan
- [TODO](TODO.md) — daftar pekerjaan dan status

## 1. Status Dokumen

- **Produk:** Sistem Pergudangan
- **Versi dokumen:** 1.0
- **Tanggal:** 8 Oktober 2026
- **Status:** Dokumentasi arsitektur awal
- **Platform:** Laravel 12

Dokumen ini menjelaskan batas tanggung jawab komponen, alur request, keamanan, penyimpanan data, dan aturan implementasi utama. Detail nama file atau endpoint yang belum terverifikasi dari source code harus diselaraskan kembali saat inspeksi proyek tersedia.

## 2. Prinsip Arsitektur

1. Pertahankan perilaku bisnis aplikasi sumber.
2. Buat perubahan sekecil mungkin pada aplikasi yang sudah berjalan.
3. Pisahkan penerimaan request, pemeriksaan akses, orkestrasi alur bisnis, dan tampilan.
4. Gunakan database salinan yang ditentukan oleh `.env`; pengujian menggunakan database `_test`.
5. Jangan mengubah database sumber.
6. Simpan file privat di private storage dan layani hanya setelah pemeriksaan akses.
7. Jangan menaruh rahasia konfigurasi di source code, tes, log, atau dokumentasi.

## 3. Gambaran Lapisan

```text
Browser / Client
       |
       v
Routes (.php dan route Laravel)
       |
       v
Middleware
       |
       v
Controllers / Entry Points
       |
       +-------------------+----------------------+
       v                   v                      v
WarehousePages     WarehouseReports     Aksi operasional
                                               |
                                      +--------+--------+
                                      v        v        v
                                WarehouseActions  WarehousePurchasingActions
                                WarehouseUserAdministrationActions
                                      |        |        |
                                      +--------+--------+
                                               v
                                  AccessService dan helper
                                               |
                                               v
                                  PDO / Eloquent / Database
                                               |
                                               v
                                  Private Storage untuk berkas
```

Blade dan komponen Bootstrap berada pada sisi presentasi. Controller tidak boleh menjadi tempat utama aturan bisnis; controller meneruskan input tervalidasi ke service dan mengembalikan response atau view.

## 4. Komponen Utama

### 4.1 Routes

Routes memetakan URL ke controller atau entry point yang sesuai. Route harus:

- mempertahankan endpoint legacy `.php` yang masih menjadi bagian dari kontrak aplikasi;
- menggunakan middleware yang relevan;
- tidak mengandung logika bisnis atau query kompleks;
- melindungi aksi mutasi dengan CSRF melalui mekanisme Laravel.

### 4.2 Middleware

Middleware menangani kebutuhan lintas fitur, seperti:

- autentikasi;
- pemeriksaan session;
- pembatasan role atau izin;
- perlindungan request;
- kebutuhan kompatibilitas endpoint.

Pemeriksaan izin tidak boleh hanya dilakukan di navigasi atau Blade. Aksi harus tetap diperiksa di sisi server.

### 4.3 Controllers

Controller bertanggung jawab untuk:

1. menerima request;
2. memvalidasi input atau meneruskan ke mekanisme validasi;
3. memanggil `WarehousePages`, `WarehouseActions`, atau service terkait;
4. memilih response, redirect, atau view;
5. meneruskan pesan hasil operasi kepada pengguna.

Controller tidak boleh melewati pemeriksaan akses atau mengubah aturan stok secara lokal.

### 4.4 WarehousePages

`WarehousePages` mempertahankan alur halaman dari aplikasi sumber yang telah dipindahkan ke Laravel. Tanggung jawabnya mencakup orkestrasi kebutuhan halaman, pengambilan data yang diperlukan, dan penyusunan konteks untuk view.

Saat merapikan kode, urutan proses dan hasil bisnis dari aplikasi sumber tidak boleh berubah tanpa tes dan keputusan yang terdokumentasi.

### 4.5 WarehouseActions

`WarehouseActions` menangani operasi yang mengubah keadaan sistem, termasuk operasi master data dan pergerakan stok. Setiap action harus:

- memvalidasi input;
- memeriksa izin melalui `AccessService` atau mekanisme otorisasi yang telah ditetapkan;
- menerapkan aturan stok dari aplikasi sumber;
- menjaga konsistensi transaksi database;
- mengembalikan hasil yang dapat dipahami oleh controller.

`WarehouseReports` memisahkan ekspor laporan PDF/CSV. `WarehousePurchasingActions` menangani pengajuan dan keputusan pembelian, sedangkan `WarehouseUserAdministrationActions` menangani administrasi akun. Pemisahan ini mempertahankan metode prosedural hasil migrasi sambil memberi batas file yang lebih jelas. `WarehouseActions` masih menampung rangkaian aksi administrasi gudang lama; pemecahan berikutnya perlu dilakukan per alur dengan regresi bisnis yang sesuai.

### 4.6 AccessService

`AccessService` adalah titik terpusat untuk pemeriksaan akses aplikasi. Komponen ini digunakan untuk memastikan bahwa pengguna berhak:

- membuka halaman tertentu;
- melihat data tertentu;
- mengubah atau menghapus data;
- mengakses foto atau berkas privat.

Perubahan pada matriks role atau izin harus dilakukan secara konsisten pada middleware, controller, action, dan delivery file.

### 4.7 Models dan Data Access

Model atau query layer bertanggung jawab untuk berinteraksi dengan database tanpa mengubah makna data sumber. Nama tabel, ID, role, dan hubungan yang sudah menjadi kontrak harus dipertahankan.

Query harus menggunakan parameter binding atau API query builder/Eloquent yang aman. Query yang terkait transaksi stok harus dijalankan dalam batas transaksi database jika diperlukan untuk menjaga konsistensi.

### 4.8 Blade dan Bootstrap

Blade bertanggung jawab atas presentasi, bukan aturan bisnis. View harus:

- mempertahankan tampilan Bootstrap aplikasi sumber;
- menampilkan aksi hanya bila pengguna berwenang, tanpa menjadikan itu satu-satunya perlindungan;
- menyertakan token CSRF pada form mutasi;
- menampilkan validasi dan pesan hasil secara jelas;
- tidak membocorkan path private storage atau rahasia konfigurasi.

## 5. Alur Request

### 5.1 Halaman Read

```text
Request
  -> Route
  -> Authentication / Authorization Middleware
  -> Controller
  -> WarehousePages
  -> AccessService dan query data
  -> Blade View
  -> Response
```

### 5.2 Aksi Mutasi

```text
Form Request + CSRF
  -> Route
  -> Middleware
  -> Controller
  -> Validasi input
  -> WarehouseActions
  -> AccessService
  -> Database Transaction / Query
  -> Redirect + status message
```

Jika validasi, otorisasi, atau aturan stok gagal, perubahan tidak boleh dilakukan dan pengguna harus menerima respons yang sesuai.

### 5.3 Akses Berkas Privat

```text
Request file
  -> Route / File Controller
  -> Authentication
  -> AccessService
  -> Pemeriksaan kepemilikan atau izin
  -> Private Storage
  -> File Response
```

Path penyimpanan tidak boleh dipublikasikan secara langsung. Request yang tidak sah harus ditolak tanpa mengungkap keberadaan atau isi file.

## 6. Model Data dan Migrasi

- `database.sql` menjadi acuan skema lama.
- Migrasi scaffold untuk tabel lama tidak digunakan untuk menggantikan skema yang telah ada.
- Migrasi baru hanya boleh menambah perubahan yang memang berada dalam cakupan dan telah dipahami dampaknya.
- `stock_adjustments` menyimpan stok sebelum stock opname, hasil fisik, selisih bertanda, alasan, gudang, petugas, dan waktu. Tabel tambahan ini tidak mengubah enum transaksi legacy.
- `warehouse_activity_logs` menyimpan perubahan master katalog beserta snapshot pelaku, gudang, objek, rincian, dan waktu. Riwayat akun yang sudah ada tetap disimpan pada `riwayat_pengelolaan_akun`.
- Jangan menjalankan `migrate:fresh`, `db:wipe`, seeder contoh, atau impor ulang `database.sql` ke database yang telah berisi data.
- Database sumber bersifat read-only untuk kebutuhan acuan; jangan menjalankan operasi destruktif terhadapnya.
- Konfigurasi `.env` tidak boleh ditampilkan atau disalin ke dokumentasi.

## 7. Aturan Stok dan Konsistensi

1. Semua perubahan saldo berasal dari action yang ditetapkan.
2. Data barang dan jumlah harus divalidasi sebelum diproses.
3. Pengeluaran yang melanggar batas stok harus ditolak bila aturan sumber melarangnya.
4. Penyimpanan transaksi dan perubahan saldo harus konsisten.
5. Kegagalan di tengah proses tidak boleh meninggalkan saldo parsial.
6. Riwayat transaksi harus dapat ditelusuri sesuai data yang tersedia dari sistem sumber.
7. Stock opname mengunci baris barang dan saldo gudang, lalu menyimpan catatan stock adjustment, perubahan saldo, serta mutasi masuk/keluar dalam satu transaksi.
8. Admin Pusat hanya dapat menyesuaikan Gudang Pusat; Admin Gudang hanya dapat menyesuaikan gudang proyek yang ditautkan; Manager hanya dapat melihat.
9. Hasil hitung yang sama dengan saldo tetap dicatat sebagai stock opname, tetapi tidak membuat transaksi masuk/keluar.

### Laporan dan Audit Aktivitas

- PDF dan CSV menggunakan role Manajer serta filter rentang tanggal dan jenis laporan yang sama.
- CSV menggunakan format UTF-8 dengan BOM dan menetralkan teks berawalan formula spreadsheet.
- Halaman aktivitas menggabungkan transaksi gudang, stock adjustment, perubahan katalog, dan catatan pengelolaan akun yang sudah ada.
- Aktivitas dibatasi ke gudang aktif untuk Manager, gudang pusat untuk Admin Pusat, gudang akun untuk Admin Gudang, dan seluruh data untuk Super Admin.
- Halaman aktivitas membatasi hasil menjadi 200 catatan terbaru untuk setiap filter.

## 8. Keamanan

### Autentikasi dan Otorisasi

- Pertahankan hash password dan role yang kompatibel dengan aplikasi sumber.
- Terapkan autentikasi pada route yang memerlukan login.
- Gunakan `AccessService` untuk keputusan akses.
- Jangan mempercayai role atau identifier yang dikirim dari client.

### CSRF dan Input

- Semua form mutasi menggunakan perlindungan CSRF.
- Validasi input dilakukan sebelum action dijalankan.
- Output yang berasal dari input pengguna harus di-escape sesuai konteks.

### Data dan File

- Rahasia tidak boleh masuk ke repository, test, log, atau dokumentasi.
- Foto dan berkas privat berada di private storage.
- Delivery file harus melewati autentikasi dan otorisasi.
- Error response tidak boleh membocorkan kredensial, path internal, atau detail sensitif.

## 9. Kompatibilitas Legacy

Kompatibilitas yang harus dijaga meliputi:

- nama tabel;
- identifier dan relasi data;
- hash password;
- role dan hak akses;
- endpoint `.php` yang masih digunakan;
- tampilan Bootstrap yang menjadi bagian dari pengalaman pengguna;
- aturan stok;
- perilaku bisnis `WarehousePages` dan `WarehouseActions`.

Perubahan kompatibilitas harus disertai uji regresi yang menunjukkan perilaku sebelum dan sesudah tetap sesuai.

## 10. Pengujian dan Verifikasi

### Jenis Pengujian

- Unit test untuk validasi, service, dan aturan stok.
- Feature test untuk login, role, halaman, action, dan endpoint legacy.
- Test keamanan untuk CSRF, otorisasi, dan file privat.
- Test regresi untuk alur yang dipindahkan dari aplikasi sumber.

### Perintah Verifikasi

```text
C:\xampp\php\php.exe artisan test
```

Tes harus memakai database pengujian `_test` yang ditentukan. Setelah perubahan PHP, Laravel Pint harus dijalankan hanya pada file PHP yang diubah.

Validasi dianggap lengkap jika hasil tes dan pemeriksaan format dicatat, atau kegagalan dan penyebabnya disebutkan secara jelas.

## 11. Observability dan Penanganan Error

- Gunakan logging aplikasi secukupnya untuk diagnosis operasional.
- Jangan mencatat password, API key, nilai rahasia `.env`, atau data sensitif yang tidak diperlukan.
- Error yang ditampilkan kepada pengguna harus aman dan dapat ditindaklanjuti.
- Detail teknis disimpan hanya pada lokasi logging yang sesuai dan mengikuti kebijakan privasi aplikasi.

## 12. Aturan Perubahan Arsitektur

Sebelum mengubah komponen inti:

1. identifikasi kontrak perilaku aplikasi sumber;
2. tentukan route, view, service, dan tes yang terdampak;
3. hindari perubahan skema bila tidak diperlukan;
4. tambahkan atau perbarui tes untuk perilaku yang berubah;
5. jalankan test Laravel dan formatter pada file yang diubah;
6. dokumentasikan dampak kompatibilitas dan risiko migrasi.

## 13. Keputusan yang Masih Perlu Dikonfirmasi

- Daftar aktual route dan endpoint legacy yang masih aktif.
- Matriks lengkap role dan izin.
- Nama model, tabel, dan hubungan yang digunakan pada implementasi saat ini.
- Strategi transaksi saldo: saldo tersimpan, dihitung dari riwayat, atau kombinasi keduanya.
- Kebijakan retensi dan audit log.
- Batas ukuran, format, dan aturan akses foto atau berkas.
