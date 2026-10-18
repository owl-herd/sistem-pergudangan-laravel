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

- .env menggunakan sistem_pergudangan_laravel, salinan mandiri 22 tabel.
- .env.testing dan phpunit.xml menggunakan sistem_pergudangan_laravel_test.
- Database sumber ditolak oleh pengaman aplikasi, termasuk pada bootstrap Artisan.
- Backup snapshot berada di storage/app/private/source-baseline.sql; berisi data privat dan tidak boleh dipublikasikan atau dimasukkan Git.
- Foto bukti disalin ke storage/app/private/receipt-photos dan hanya dilayani setelah pemeriksaan akses.
- Jangan menjalankan migrate:fresh, db:wipe, seed contoh, atau mengimpor seluruh database.sql ke database yang sudah terisiAsia/Jakarta. Migrasi bawaan scaffold dinonaktifkan; database.sql tetap acuan skema.
- PHP CLI sumber memakai . APP_TIMEZONE mempertahankan nilai itu agar migrasi tidak diam-diam menggeser tanggal. Penyesuaian ke Asia/Jakarta perlu diuji dan disetujui terpisah.
- Cookie session Laravel berbeda; pengguna login ulang pada aplikasi baru.

## Struktur migrasi

routes/web.php mempertahankan endpoint sumber. PageController menggunakan WarehousePages untuk query dan Blade di resources/views/warehouse. ActionController menggunakan WarehouseActions untuk aturan bisnis prosedural yang dipindahkan ke layanan Laravel. Autentikasi memakai guard Laravel dan model User dengan password_hash. AccessService memeriksa akun aktif, role dan gudang pada setiap endpoint terlindungi. PDO menggunakan koneksi yang dikelola Laravel; prepared statements, penguncian dan transaksi sumber dipertahankan. Adaptor helper berada di app/Support/warehouse_helpers.php. Aset asli ada di public/assets. Tidak ada SPA atau perubahan framework tampilan.

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
