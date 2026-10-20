# TODO Sistem Pergudangan

## Navigasi Dokumentasi

- [AGENTS](AGENTS.md) — aturan wajib proyek
- [PRD](PRD.md) — kebutuhan dan tujuan produk
- [Architecture](ARCHITECTURE.md) — rancangan teknis sistem
- [Skill Guide](SKILL.md) — panduan kerja agent/developer
- [Workflow](WORKFLOW.md) — alur kerja pengembangan
- [README](README.md) — petunjuk operasional, akses role, endpoint, dan pemulihan

Legenda status: `Todo` · `In Progress` · `Blocked` · `Done`

Status di bawah membedakan implementasi yang sudah memiliki dokumentasi/tes dari pekerjaan
yang masih memerlukan audit atau cakupan tambahan. `Done` tidak berarti seluruh kombinasi
data dan role telah diuji.

## Prioritas P0 — Fondasi dan Keamanan

- [x] `Done` Dokumentasikan komponen utama dan alur Laravel yang tersedia di README.
- [x] `Done` Konfigurasikan pengujian ke database `_test` dan verifikasi isolasinya dalam feature test.
- [x] `Done` Uji login/logout dan kompatibilitas kolom hash password lama.
- [x] `Done` Dokumentasikan matriks role dan endpoint `.php` di README.
- [ ] `Todo` Lengkapi audit seluruh kombinasi route, aksi, role, dan cakupan gudang; pertahankan feature test untuk setiap batas akses penting.
- [x] `Done` Uji CSRF pada aksi admin, pembelian, pengelolaan akun, dan logout.
- [ ] `Todo` Audit kebocoran rahasia secara menyeluruh pada source, log, artefak build, dan dokumentasi tanpa menampilkan nilai rahasia.
- [x] `Done` Uji bahwa foto profil dan bukti penerimaan dilayani melalui endpoint privat dengan pemeriksaan akses.
- [ ] `Todo` Tambahkan regresi untuk setiap endpoint/file privat tambahan yang diperkenalkan.
- [ ] `Todo` Verifikasi konfigurasi development menunjuk ke database salinan yang benar tanpa membaca atau mencatat rahasia `.env`.

## Prioritas P0 — Data dan Aturan Stok

- [x] `Done` Bandingkan inventaris tabel aktif dengan `database.sql`; salinan lokal memiliki 27 tabel aplikasi, sementara SQL mendefinisikan 25 dan dua notifikasi berasal dari migrasi. Tiga tabel SQL juga dibuat oleh migrasi profil/aktivitas, sehingga status migrasi perlu dicocokkan saat menyiapkan salinan baru.
- [x] `Done` Cocokkan nama kolom pada 25 tabel SQL dengan metadata database lokal; tidak ditemukan selisih nama kolom.
- [ ] `Todo` Inventarisasi primary key, foreign key, indeks unik, dan relasi penting pada seluruh tabel.
- [x] `Done` Pastikan migrasi scaffold tabel lama dinonaktifkan dan migrasi tambahan tidak mengganti skema sumber.
- [ ] `Todo` Lengkapi dokumentasi aturan barang masuk, barang keluar, penyesuaian, serta saldo dibandingkan aplikasi sumber.
- [x] `Done` Uji alur pengiriman/penerimaan, penerimaan pembelian, retur/pemakaian, dan penyesuaian stok dengan transaksi serta riwayat.
- [x] `Done` Uji kegagalan stok tidak mencukupi agar perubahan terkait dibatalkan secara atomik.
- [ ] `Todo` Tambahkan cakupan eksplisit untuk jumlah nol/negatif, batas maksimum, barang tidak ditemukan/nonaktif, dan konkurensi pada tiap jenis aksi stok.
- [x] `Done` Aturan larangan `migrate:fresh`, `db:wipe`, seeder contoh, serta impor ulang database berisi data sudah didokumentasikan.

## Prioritas P1 — Fitur Operasional

### Dashboard

- [ ] `Todo` Verifikasi angka ringkasan, status stok minimum, dan keadaan kosong untuk kombinasi role/lokasi yang didukung.
- [x] `Done` Uji render halaman berdasarkan role yang didukung dan struktur navigasi dashboard.
- [ ] `Todo` Uji seluruh tautan dan alur operasional dashboard pada browser setelah tersedia fixture akun E2E terisolasi.

### Stock Opname, Laporan, dan Audit

- [x] `Done` Stock opname mencatat jumlah fisik, alasan, selisih, mutasi atomik, dan role gudang yang diizinkan.
- [x] `Done` Ekspor CSV tersedia untuk laporan dan periode yang didukung.
- [x] `Done` Riwayat aktivitas menggabungkan mutasi, stock opname, perubahan katalog, dan audit akun sesuai cakupan.
- [ ] `Todo` Verifikasi filter, rentang tanggal, pagination, dan keadaan kosong pada semua jenis laporan.

### Master Data Barang

- [ ] `Todo` Inventarisasi field master barang berdasarkan skema dan perilaku sumber.
- [ ] `Todo` Verifikasi daftar/detail serta operasi tambah, ubah, arsip, validasi, dan duplikasi sesuai izin.
- [ ] `Todo` Verifikasi upload/perubahan/penghapusan foto atau berkas barang jika fitur terkait tersedia.
- [ ] `Todo` Tambahkan feature test CRUD barang dan field yang wajib dipertahankan.

### Pergerakan Stok

- [x] `Done` Alur utama barang masuk/keluar, pengiriman, penerimaan, dan saldo setelah transaksi memiliki feature test.
- [ ] `Todo` Lengkapi uji stok nol, jumlah invalid, barang nonaktif, selisih, dan retry/duplikasi pada seluruh jalur.
- [ ] `Todo` Tinjau pesan validasi dan hasil aksi pada semua form operasional.

### Pencarian dan Filter

- [ ] `Todo` Verifikasi pencarian dan filter barang berdasarkan field yang tersedia.
- [ ] `Todo` Uji hasil pencarian untuk nol, satu, dan banyak hasil pada layar desktop dan ponsel.

## Prioritas P1 — Kompatibilitas Legacy dan Kualitas Kode

- [x] `Done` Daftar endpoint legacy dan role akses ringkas tersedia di README.
- [ ] `Todo` Bandingkan seluruh endpoint, response, dan perilaku dengan aplikasi sumber.
- [ ] `Todo` Lengkapi peta alur prosedural dari sumber ke `WarehousePages`, `WarehouseActions`, dan `AccessService`.
- [ ] `Todo` Bandingkan tampilan Bootstrap seluruh halaman dalam scope dengan aplikasi sumber.
- [ ] `Todo` Pisahkan query/aturan bisnis dari controller atau Blade hanya jika perubahan dapat mempertahankan perilaku.
- [ ] `Todo` Tinjau error handling dan pesan gagal agar tidak membocorkan path/data sensitif.

## Prioritas P2 — Pengujian dan Verifikasi

- [x] `Done` Test Laravel menggunakan database `_test` mencakup login, penolakan akses, CSRF, validasi tertentu, foto privat, stok, notifikasi, dan transaksi utama.
- [ ] `Todo` Perluas matriks tes otorisasi sehingga setiap kombinasi endpoint dan role penting tercakup.
- [x] `Done` Playwright telah diarahkan ke aplikasi lokal dan memiliki smoke test beranda, login, redirect pengunjung, serta layout login ponsel.
- [ ] `Todo` Tambahkan E2E terautentikasi setelah fixture akun dan data terisolasi dirancang; jangan menggunakan akun atau data produksi.
- [x] `Done` Jalankan smoke test Chromium lokal; jalankan ulang setelah perubahan test/config.
- [ ] `Todo` Catat kegagalan validasi yang belum terselesaikan pada perubahan terkait.

## Prioritas P2 — Dokumentasi dan Operasional

- [ ] `Todo` Lengkapi PRD berdasarkan fitur aktual dan batas fitur yang belum tersedia.
- [ ] `Todo` Selaraskan ARCHITECTURE dengan pemetaan file/komponen yang telah diverifikasi.
- [x] `Done` Matriks role, ringkasan endpoint legacy, pengujian browser lokal, backup, pemulihan, cutover/rollback, dan scheduler Windows didokumentasikan di README.
- [ ] `Todo` Tinjau runbook bersama operator sebelum dipakai untuk data produksi.
- [ ] `Todo` Tentukan target retensi, lokasi backup terenkripsi, jadwal restore drill, RPO/RTO, dan pemilik persetujuan cutover.

## Definition of Done

Sebuah pekerjaan dapat ditandai `Done` jika:

- implementasi sesuai scope dan tidak mengubah perilaku bisnis yang tidak diminta;
- akses dan validasi sisi server sudah diterapkan;
- test yang relevan sudah ditambah atau diperbarui;
- test Laravel berjalan pada database `_test`;
- Laravel Pint lulus pada file PHP yang diubah;
- tidak ada rahasia yang masuk ke source code, tes, log, atau dokumentasi;
- dokumentasi terkait sudah selaras dengan perilaku aktual;
- hasil pemeriksaan dan batas cakupan verifikasi dilaporkan.

## Catatan Kerja

- Jangan mengubah aplikasi sumber di `../sistem-pergudangan`.
- Jangan melakukan operasi destruktif terhadap database sumber atau database berisi data.
- Operasi backup/restore/cutover pada README adalah prosedur panduan; belum dijalankan pada lingkungan produksi.
- Tandai item `Blocked` beserta alasan bila memerlukan keputusan, akses, atau data yang belum tersedia.
- Perbarui checklist setelah setiap perubahan yang sudah diverifikasi.
