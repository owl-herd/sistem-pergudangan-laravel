# Pergudangan Trikasa — Laravel 12

Proyek ini terpisah dari aplikasi PHP prosedural di ../sistem-pergudangan. Laravel 12.69.3 berjalan pada PHP 8.2 dan mempertahankan URL .php, tampilan Bootstrap, role, data, nomor MP/MH/PR, dan aturan transaksi sumber.

## Menjalankan

Dari direktori proyek, jalankan:

    C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8001

Buka http://127.0.0.1:8001. Akun dan password sama dengan akun pada snapshot sumber; tidak ada reset password. Untuk XAMPP gunakan VirtualHost dengan DocumentRoot menuju public. Jangan mengekspos root proyek, storage, vendor atau .env. Root .htaccess mengarahkan permintaan ke public sebagai kompatibilitas lokal, tetapi VirtualHost public merupakan konfigurasi yang dianjurkan.

## Asisten AI

- Menu **Asisten** pada dashboard menjawab pertanyaan tentang alur pergudangan. Asisten hanya memakai panduan proses statis, tidak membaca database atau melakukan aksi bisnis. Pertanyaan dikirim ke OpenAI; atur `OPENAI_API_KEY` dan, bila perlu, `OPENAI_MODEL` di `.env`. Jangan masukkan password, data pribadi, atau informasi rahasia.
- Asisten development dijalankan dari terminal dengan `php artisan agent:dev`. Pasang Ollama, ambil model yang mendukung tool calling (misalnya `ollama pull qwen2.5-coder:7b`), lalu atur `DEV_AGENT_BASE_URL` dan `DEV_AGENT_MODEL` di `.env`. Source code hanya dikirim ke endpoint loopback lokal yang dikonfigurasi, bukan OpenAI.
- Asisten development dapat mencari/membaca file project yang diizinkan. Setiap perubahan file dan eksekusi `php artisan test --compact` memerlukan persetujuan interaktif; file `.env`, `database.sql`, `vendor`, `storage/private`, dan direktori sensitif lain diblokir. Tes menggunakan konfigurasi database testing dan tidak menjalankan migrasi destruktif.

## Database dan keselamatan

- Salinan database aplikasi memiliki 27 tabel aplikasi; tabel `migrations` menjadi tabel ke-28.
- .env.testing dan phpunit.xml menggunakan sistem_pergudangan_laravel_test.
- Database sumber ditolak oleh pengaman aplikasi, termasuk pada bootstrap Artisan.
- Backup snapshot berada di storage/app/private/source-baseline.sql; berisi data privat dan tidak boleh dipublikasikan atau dimasukkan Git.
- Foto bukti disalin ke storage/app/private/receipt-photos dan hanya dilayani setelah pemeriksaan akses.
- Jangan menjalankan migrate:fresh, db:wipe, seed contoh, atau mengimpor seluruh database.sql ke database yang sudah terisiAsia/Jakarta. Migrasi bawaan scaffold dinonaktifkan; database.sql tetap acuan skema.
- PHP CLI sumber memakai . APP_TIMEZONE mempertahankan nilai itu agar migrasi tidak diam-diam menggeser tanggal. Penyesuaian ke Asia/Jakarta perlu diuji dan disetujui terpisah.
- Cookie session Laravel berbeda; pengguna login ulang pada aplikasi baru.

## Struktur migrasi

routes/web.php mempertahankan endpoint sumber. PageController menggunakan WarehousePages untuk halaman operasional dan WarehouseReports untuk PDF/CSV. ActionController meneruskan aksi operasional gudang ke WarehouseActions, aksi purchasing ke WarehousePurchasingActions, dan administrasi akun ke WarehouseUserAdministrationActions. Autentikasi memakai guard Laravel dan model User dengan password_hash. AccessService memeriksa akun aktif, role dan gudang pada setiap endpoint terlindungi. PDO menggunakan koneksi yang dikelola Laravel; prepared statements, penguncian dan transaksi sumber dipertahankan. Adaptor helper berada di app/Support/warehouse_helpers.php. Aset asli ada di public/assets. Tidak ada SPA atau perubahan framework tampilan.

Migrasi ini menempatkan aplikasi pada Laravel; layanan bisnis tetap berbentuk prosedural di dalam metode agar perilaku sumber terjaga. Pemisahan layanan lebih rinci, Eloquent untuk seluruh query, dan FormRequest per aksi dapat dilakukan setelah hasil migrasi diterima, tanpa menjadi syarat menjalankan aplikasi ini.

## Verifikasi

    C:\xampp\php\php.exe artisan test

Pengujian menulis hanya ke database *_test. Fixture memakai identitas unik dan tidak mereset database. Pengujian mencakup login/role, CSRF, penomoran, pengiriman multi-barang, penerimaan sebagian dan pengulangan, pembelian, retur/pemakaian, dokumen PDF, foto privat, dan akses lintas proyek. Database salinan aplikasi tetap bersih dari fixture.

## Alur yang dipertahankan

Admin proyek mengajukan barang; Admin Pusat mengisi kendaraan dan sopir lalu mengirim. Stok pusat berkurang saat kirim, stok proyek bertambah saat penerimaan. Surat jalan mencakup semua detail dan tidak mengubah stok. Purchasing menyetujui pembelian tanpa mutasi stok; penerimaan hanya menambah jumlah layak. Barang rusak/tidak sesuai wajib memiliki catatan dan foto. Retur peralatan mengurangi stok proyek saat dikirim dan menambah stok pusat setelah diterima. Manager tetap membaca lintas gudang aktif, bukan hanya penugasan manager_gudang. Super Admin mengelola akun/role/gudang dan dilindungi dari kehilangan Super Admin aktif terakhir.

## Batas operasional

Aplikasi lama tetap berjalan dan menjadi acuan. Snapshot baru tidak mengikuti perubahan yang dibuat kemudian di aplikasi lama. Jangan mengoperasikan keduanya sebagai dua sumber data produksi. Sebelum perpindahan resmi, hentikan penulisan sementara, ambil snapshot terbaru beserta foto, rekonsiliasi ID/jumlah/stok, lalu alihkan satu kali dengan rencana pemulihan. Belum ada perubahan routing Apache global atau perpindahan produksi.

## Profil pengguna

Semua role aktif dapat membuka profil.php melalui menu Profil saya untuk mengubah nama, foto, dan kata sandi sendiri. Email, role, serta gudang tetap dikelola administrator. Penggantian kata sandi memerlukan kata sandi saat ini dan konfirmasi; hash akun lain tidak berubah. Foto JPEG/PNG/WebP maksimal 4 MB dan 4096 × 4096 piksel disimpan di storage/app/private/profile-photos dan dilayani hanya untuk pemilik akun melalui foto_profil.php. Foto lama dihapus setelah penggantian berhasil. Perubahan dicatat pada audit akun.

## Notifikasi Purchasing

Saat Admin Pusat atau Admin Gudang Proyek mengirim permintaan pembelian, setiap akun Purchasing aktif menerima notifikasi dalam aplikasi. Jumlah belum dibaca dan notifikasi tampil pada halaman Purchasing; status baca disimpan per akun dan hanya dapat diubah pemilik notifikasi. Email tidak dikirim. Pengiriman email dapat ditambahkan kemudian melalui kanal notifikasi Laravel saat konfigurasi email peluncuran tersedia.

Migrasi tambahan `2026_10_07_000001_create_user_profiles_table` membuat tabel `user_profiles`, dan `2026_10_07_000002_create_purchasing_notifications_table` membuat tabel `purchasing_notifications`; migrasi tabel lama tetap dinonaktifkan. Terapkan migrasi tambahan pada database salinan aplikasi dan database pengujian dengan `artisan migrate` serta `artisan migrate --env=testing`. Tidak perlu mengimpor ulang `database.sql`.

## Notifikasi gudang dan laporan manajer

Notifikasi dalam aplikasi disimpan per akun aktif. Admin Pusat menerima pemberitahuan persetujuan pembelian untuk pusat, permintaan barang proyek, dan konfirmasi penerimaan pengiriman di proyek (termasuk selisih). Admin Gudang Proyek menerima persetujuan pembelian untuk gudangnya serta pengiriman dari pusat menuju gudangnya. Status dibaca hanya bisa diubah pemilik akun melalui POST dengan CSRF. Notifikasi operasional dibuat dalam transaksi yang sama dengan aksi terkait; aksi gagal atau diulang tidak membuat pemberitahuan ganda.

Manajer menerima satu notifikasi per minggu untuk periode Senin–Minggu sebelumnya. Pengingat dijadwalkan Senin pukul 08.00 mengikuti timezone aplikasi yang sudah ada. Jalankan Laravel scheduler melalui tugas server yang memanggil artisan schedule:run setiap menit. Bila scheduler belum berjalan, pengingat minggu berjalan dibuat saat manajer membuka dashboard, dengan kunci unik per akun/minggu. Pengingat menautkan periode laporan, bukan menyimpan snapshot PDF.

Bagian Pilihan laporan menyediakan laporan terpisah untuk stok, permintaan barang, pengiriman/penerimaan, pengajuan pembelian, penerimaan pembelian, penambahan barang proyek, retur peralatan, serta mutasi/pemakaian. Pilih periode lalu Lihat laporan atau Unduh PDF. Stok adalah kondisi saat laporan dibuka, bukan stok historis; retur yang masih menunggu diterima tetap ditampilkan sebagaimana aturan laporan sebelumnya. Pilihan Semua laporan tetap tersedia.

Migrasi tambahan 2026_10_07_000003_create_warehouse_notifications_table membuat tabel baru warehouse_notifications tanpa mengubah tabel lama. Terapkan dengan artisan migrate pada database salinan dan artisan migrate --env=testing pada database pengujian.

Pemeriksaan metadata lokal menemukan 27 tabel aplikasi dan seluruh migrasi tercatat sudah berjalan. `database.sql` mendefinisikan 25 tabel; nama kolom pada tabel-tabel itu cocok dengan salinan lokal. Dua tabel notifikasi aktif ditambahkan melalui migrasi. Tiga tabel yang didefinisikan dalam `database.sql` juga dibuat oleh migrasi profil dan aktivitas. Saat menyiapkan salinan baru atau memulihkan backup, cocokkan struktur dan tabel `migrations` sebelum menjalankan migrasi agar tabel yang sudah ada tidak dibuat ulang.

## Matriks role dan endpoint

Kode role yang dipakai aplikasi adalah `super_admin`, `admin_pusat`, `admin_gudang`,
`manager`, dan `purchasing`. Semua halaman dan aksi tetap memeriksa role di server;
menyembunyikan tautan di Blade bukan pengganti otorisasi. `AccessService` juga menolak akun
nonaktif, admin gudang proyek tanpa gudang proyek aktif, dan admin pusat yang ditautkan ke
gudang proyek.

| Role | Cakupan utama |
| --- | --- |
| Super Admin (`super_admin`) | Pengelolaan akun, role, gudang, dan penugasan manajer; aktivitas/audit; profil sendiri. Tidak memakai dashboard operasional gudang. |
| Admin Pusat (`admin_pusat`) | Dashboard gudang pusat; katalog dan stok pusat; barang masuk; pemrosesan permintaan/pengiriman; konfirmasi penerimaan pembelian dan retur peralatan; profil sendiri. |
| Admin Gudang Proyek (`admin_gudang`) | Dashboard dan stok gudang proyek yang ditautkan; permintaan barang/pembelian; konfirmasi penerimaan pengiriman; pemakaian dan pengembalian peralatan; profil sendiri. Data gudang dibatasi ke gudang akun. |
| Manager (`manager`) | Dashboard, pemantauan lintas gudang aktif, aktivitas, laporan PDF/CSV, dan surat jalan; tidak mengubah stok; profil sendiri. |
| Purchasing (`purchasing`) | Halaman Purchasing, pengajuan/persetujuan pembelian sesuai alur, detail dan foto penerimaan pembelian, serta profil sendiri. |

Pemetaan endpoint kompatibilitas `.php` yang didaftarkan di `routes/web.php`:

| Endpoint | Akses dan kegunaan |
| --- | --- |
| `/`, `/index.php` | Publik; halaman beranda. |
| `/login.php` | GET publik untuk form; POST untuk autentikasi. |
| `/dashboard.php` | `admin_pusat`, `admin_gudang`, `manager`. |
| `/aktivitas.php` | `super_admin`, `admin_pusat`, `admin_gudang`, `manager`; data dibatasi sesuai role/gudang. |
| `/purchasing.php` | `purchasing`. |
| `/super_admin.php` | `super_admin`. |
| `/penerimaan_pembelian.php` | `admin_pusat`, `admin_gudang`. |
| `/detail_penerimaan_pembelian.php` | `purchasing`, `manager`. |
| `/foto_penerimaan_pembelian.php` | `purchasing`, `admin_pusat`, `admin_gudang`, `manager`; foto tetap melalui pemeriksaan akses. |
| `/bukti_persetujuan.php` | `purchasing`, `manager`, `admin_pusat`, `admin_gudang`. |
| `/laporan_pdf.php`, `/laporan_csv.php` | `manager`. |
| `/surat_jalan.php` | `admin_pusat`, `admin_gudang`, `manager`; ruang lingkup pengiriman tetap diperiksa. |
| `/profil.php`, `/foto_profil.php` | Semua role aktif; profil/foto hanya milik akun yang sedang login. |
| `/asisten.php` | Semua role aktif; POST dibatasi throttle. |
| `/portal.php` | Semua role operasional aktif; mengarahkan ke halaman sesuai role. |
| `/aksi_admin.php`, `/aksi_pembelian.php`, `/aksi_pengguna.php`, `/stocktake.php` | POST saja; role diperiksa per aksi. Pengelolaan akun hanya `super_admin`; penyesuaian stok hanya `admin_pusat`/`admin_gudang`. |
| `/notifikasi.php/{id}/baca` | POST dengan CSRF; `admin_pusat`, `admin_gudang`, `manager`, `super_admin`; hanya notifikasi penerima. |
| `/notifikasi_pembelian.php/{id}/baca` | POST dengan CSRF; `purchasing`; hanya notifikasi penerima. |
| `/logout.php` | POST dengan CSRF; mengakhiri sesi aktif. |

Daftar ini merangkum role yang terlihat pada route dan `WarehousePages`, controller, serta
`WarehouseActions`; aturan per aksi dan kepemilikan data tetap menjadi sumber keputusan final.
Jika route atau role berubah, perbarui tabel ini dan feature test otorisasi terkait.

## Pengujian browser lokal

Playwright hanya diarahkan ke loopback agar smoke test tidak mengirim request ke situs luar
atau lingkungan produksi. Jalankan server pada konfigurasi testing/database `_test`, lalu di
terminal lain:

```powershell
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8001 --env=testing
npm.cmd run test:e2e
```

Smoke test membuka beranda, form login, akses dashboard sebagai pengunjung, dan layout login
ponsel. Tes browser hanya melakukan GET dan tidak mengisi kredensial atau mengubah data.
`E2E_BASE_URL` boleh dipakai untuk mengganti port, tetapi host harus tetap `localhost` atau
loopback. Tes role terautentikasi masih mengandalkan feature test Laravel sampai tersedia
fixture akun E2E yang terisolasi.

## Backup, pemulihan, dan cutover

### Backup terjadwal/manual

1. Tentukan database salinan aplikasi dan lokasi backup yang terenkripsi serta hanya dapat
   diakses operator berwenang. Jangan menyimpan backup di repository, `public`, atau direktori
   yang dapat diunduh web.
2. Untuk snapshot konsisten, hentikan sementara penulisan aplikasi atau lakukan backup saat
   maintenance. Database dan file privat harus berasal dari titik waktu yang sama.
3. Buat dump database dengan akun backup berhak minimum. Ganti placeholder setelah memeriksa
   target secara manual; opsi `-p` meminta password secara interaktif dan password tidak boleh
   ditulis di command line:

   ```text
   mysqldump --host=127.0.0.1 --user=<akun-backup> -p --single-transaction --routines --triggers --default-character-set=utf8mb4 --result-file="D:\Backup\pergudangan-YYYYMMDD-HHmm.sql" <database-salinan>
   ```

4. Salin `storage/app/private` ke media backup privat. Contoh Windows (sesuaikan tujuan
   penyimpanan yang terlindungi):

   ```text
   robocopy ".\storage\app\private" "D:\Backup\pergudangan-private" /E /COPY:DAT /R:2 /W:2
   ```

5. Enkripsi backup saat tersimpan maupun dipindahkan, batasi akses, tetapkan retensi, dan
   lakukan uji restore berkala pada lingkungan terisolasi. Backup berisi data operasional dan
   foto privat.

### Pemulihan

1. Siapkan host/database pemulihan yang terisolasi dan database kosong dengan versi skema yang
   sesuai. Periksa nama database dan pastikan bukan database sumber atau database produksi
   aktif sebelum restore.
2. Pulihkan dump ke database pemulihan baru, lalu pulihkan berkas privat ke private storage
   aplikasi dengan izin filesystem yang ketat. Jangan menaruh berkas tersebut di `public`.
3. Konfigurasikan `.env` pemulihan secara terpisah melalui pengelolaan rahasia yang disetujui;
   jangan menyalin rahasia ke dokumentasi atau repository. Pastikan `APP_KEY` yang diperlukan
   tersedia secara aman.
4. Verifikasi login, status role, jumlah stok, transaksi terakhir, laporan, dan akses foto
   privat dengan akun uji berizin. Pastikan file tidak dapat dibuka langsung dari URL publik.
5. Jangan alihkan pengguna ke host pemulihan sebelum hasil rekonsiliasi disetujui. Catat titik
   pemulihan, selisih, dan persetujuan operator.

### Cutover dan rollback

1. Aplikasi sumber tetap menjadi satu-satunya sumber penulisan sampai jendela cutover disetujui.
   Jangan menjalankan dua aplikasi sebagai penulis bersamaan.
2. Hentikan penulisan pada aplikasi sumber, ambil backup final database dan file privat, lalu
   rekonsiliasi ID, jumlah stok, transaksi terakhir, dan jumlah file.
3. Terapkan snapshot final ke target terisolasi dan jalankan smoke test serta pemeriksaan role
   sebelum membuka akses pengguna.
4. Tentukan pemilik keputusan dan batas waktu rollback sebelum cutover. Jika validasi gagal,
   hentikan penulisan di target dan pulihkan layanan ke sumber sesuai rencana yang disetujui;
   jangan menggabungkan dua set perubahan secara otomatis.
5. Setelah target menerima transaksi baru, rollback memerlukan rekonsiliasi transaksi sejak
   cutover. Jangan mengalihkan kembali begitu saja karena dapat kehilangan perubahan.

### Scheduler pada Windows

Untuk pengingat laporan mingguan, buat task Windows Task Scheduler yang menjalankan setiap
menit:

- **Program:** `C:\xampp\php\php.exe`
- **Arguments:** `artisan schedule:run`
- **Start in:** direktori root proyek
- Gunakan akun Windows khusus dengan izin minimum pada proyek dan private storage.
- Pantau `Last Run Result`, riwayat task, dan log Laravel; jangan menambahkan credential ke
  arguments.

Pastikan timezone aplikasi sesuai keputusan operasional sebelum mengaktifkan jadwal. Pengingat
mingguan jatuh Senin pukul 08.00 menurut timezone aplikasi yang dikonfigurasi.
