# TODO Sistem Pergudangan

## Navigasi Dokumentasi

- [AGENTS](AGENTS.md) — aturan wajib proyek
- [PRD](prd.md) — kebutuhan dan tujuan produk
- [Architecture](architecture.md) — rancangan teknis sistem
- [Skill Guide](skill.md) — panduan kerja agent/developer
- [Workflow](workflow.md) — alur kerja pengembangan

Legenda status: `Todo` · `In Progress` · `Blocked` · `Done`

## Prioritas P0 — Fondasi dan Keamanan

- [ ] `Todo` Verifikasi struktur proyek Laravel 12 dan dokumentasikan route, controller, service, model, middleware, helper, serta Blade yang benar-benar tersedia.
- [ ] `Todo` Pastikan konfigurasi pengembangan menggunakan database salinan yang ditentukan di `.env`.
- [ ] `Todo` Pastikan konfigurasi pengujian menggunakan database `_test`.
- [ ] `Todo` Verifikasi autentikasi login/logout dan kompatibilitas hash password dari aplikasi sumber.
- [ ] `Todo` Petakan role dan susun matriks izin setiap role.
- [ ] `Todo` Pastikan semua route terlindungi middleware autentikasi dan otorisasi yang sesuai.
- [ ] `Todo` Pastikan seluruh form mutasi menggunakan perlindungan CSRF.
- [ ] `Todo` Audit agar rahasia dari `.env` tidak muncul di source code, tes, log, atau dokumentasi.
- [ ] `Todo` Verifikasi foto dan berkas privat tidak dapat diakses melalui path publik langsung.
- [ ] `Todo` Tambahkan test untuk penolakan akses terhadap halaman, aksi, dan berkas privat.

## Prioritas P0 — Data dan Aturan Stok

- [ ] `Todo` Bandingkan skema aktif dengan `database.sql` sebagai acuan.
- [ ] `Todo` Inventarisasi tabel lama, nama kolom, primary key, foreign key, dan relasi penting.
- [ ] `Todo` Pastikan tidak ada migrasi scaffold yang mencoba menggantikan tabel lama.
- [ ] `Todo` Dokumentasikan aturan barang masuk, barang keluar, penyesuaian, dan saldo stok dari aplikasi sumber.
- [ ] `Todo` Verifikasi transaksi stok menggunakan validasi jumlah dan barang yang benar.
- [ ] `Todo` Verifikasi pengeluaran yang melebihi stok ditangani sesuai aturan aplikasi sumber.
- [ ] `Todo` Pastikan kegagalan transaksi tidak meninggalkan perubahan saldo parsial.
- [ ] `Todo` Tambahkan unit dan feature test untuk kasus stok normal, stok nol, jumlah invalid, dan stok tidak mencukupi.
- [ ] `Todo` Pastikan tidak menjalankan `migrate:fresh`, `db:wipe`, seeder contoh, atau impor ulang `database.sql` pada database berisi data.

## Prioritas P1 — Fitur Operasional

### Dashboard

- [ ] `Todo` Verifikasi ringkasan kondisi gudang pada dashboard.
- [ ] `Todo` Verifikasi link dan navigasi menuju fungsi operasional utama.
- [ ] `Todo` Pastikan dashboard menampilkan data sesuai role pengguna.

### Master Data Barang

- [ ] `Todo` Inventarisasi seluruh field master barang.
- [ ] `Todo` Verifikasi daftar dan detail barang.
- [ ] `Todo` Verifikasi tambah, ubah, dan hapus barang sesuai izin.
- [ ] `Todo` Verifikasi validasi field wajib, format, dan nilai duplikat.
- [ ] `Todo` Verifikasi upload, perubahan, dan penghapusan foto atau berkas barang bila fitur tersedia.
- [ ] `Todo` Tambahkan feature test untuk alur CRUD master barang.

### Pergerakan Stok

- [ ] `Todo` Verifikasi alur barang masuk.
- [ ] `Todo` Verifikasi alur barang keluar.
- [ ] `Todo` Verifikasi riwayat pergerakan stok.
- [ ] `Todo` Verifikasi tampilan saldo stok setelah setiap jenis transaksi.
- [ ] `Todo` Tambahkan pesan validasi dan hasil aksi yang jelas bagi pengguna.

### Pencarian dan Filter

- [ ] `Todo` Verifikasi pencarian berdasarkan atribut barang yang tersedia.
- [ ] `Todo` Verifikasi filter berdasarkan status, kategori, periode, atau atribut yang tersedia.
- [ ] `Todo` Uji hasil pencarian pada data kosong, satu hasil, dan banyak hasil.

## Prioritas P1 — Kompatibilitas Legacy

- [ ] `Todo` Inventarisasi endpoint `.php` yang masih digunakan.
- [ ] `Todo` Verifikasi setiap endpoint legacy memiliki perilaku dan response yang kompatibel.
- [ ] `Todo` Petakan alur prosedural sumber ke `WarehousePages`.
- [ ] `Todo` Petakan aksi mutasi sumber ke `WarehouseActions`.
- [ ] `Todo` Pastikan `AccessService` digunakan pada pemeriksaan akses.
- [ ] `Todo` Bandingkan tampilan Bootstrap dengan aplikasi sumber untuk halaman yang termasuk cakupan.
- [ ] `Todo` Tambahkan regression test untuk perilaku penting yang dipindahkan.

## Prioritas P1 — Kualitas Kode

- [ ] `Todo` Pisahkan query dan aturan bisnis yang masih berada di controller.
- [ ] `Todo` Pastikan controller hanya menangani request, validasi, pemanggilan service, dan response.
- [ ] `Todo` Pastikan Blade tidak memuat aturan bisnis atau query langsung.
- [ ] `Todo` Pastikan helper dan service mengikuti pola proyek yang sudah ada.
- [ ] `Todo` Hindari penambahan dependensi tanpa kebutuhan yang jelas.
- [ ] `Todo` Jalankan Laravel Pint hanya pada file PHP yang diubah.
- [ ] `Todo` Tinjau error handling agar tidak membocorkan path internal atau data sensitif.

## Prioritas P2 — Pengujian dan Verifikasi

- [ ] `Todo` Jalankan test Laravel menggunakan:

  ```text
  C:\xampp\php\php.exe artisan test
  ```

- [ ] `Todo` Pastikan test hanya menulis ke database pengujian `_test`.
- [ ] `Todo` Tambahkan test autentikasi berhasil dan gagal.
- [ ] `Todo` Tambahkan test otorisasi per role.
- [ ] `Todo` Tambahkan test CSRF untuk aksi mutasi.
- [ ] `Todo` Tambahkan test akses file privat.
- [ ] `Todo` Tambahkan test validasi input dan pesan error.
- [ ] `Todo` Tambahkan test regresi endpoint legacy.
- [ ] `Todo` Catat hasil test dan kegagalan yang belum terselesaikan.
- [ ] `Todo` Catat hasil Laravel Pint pada file yang diubah.

## Prioritas P2 — Dokumentasi

- [ ] `Todo` Lengkapi `prd.md` berdasarkan hasil inspeksi fitur aktual.
- [ ] `Todo` Lengkapi `architecture.md` dengan nama file dan diagram aktual setelah struktur proyek diverifikasi.
- [ ] `Todo` Dokumentasikan matriks role dan izin.
- [ ] `Todo` Dokumentasikan daftar endpoint legacy.
- [ ] `Todo` Dokumentasikan prosedur menjalankan aplikasi secara aman.
- [ ] `Todo` Dokumentasikan prosedur pengujian tanpa menyertakan rahasia konfigurasi.
- [ ] `Todo` Dokumentasikan keputusan arsitektur dan perubahan yang berdampak pada kompatibilitas.

## Definition of Done

Sebuah pekerjaan dapat ditandai `Done` jika:

- implementasi sesuai scope dan tidak mengubah perilaku bisnis yang tidak diminta;
- akses dan validasi sisi server sudah diterapkan;
- test yang relevan sudah ditambah atau diperbarui;
- test Laravel berjalan pada database `_test`;
- Laravel Pint lulus pada PHP yang diubah;
- tidak ada rahasia yang masuk ke source code, tes, log, atau dokumentasi;
- dokumentasi terkait sudah diperbarui bila kontrak atau perilaku berubah.

## Catatan Kerja

- Jangan mengubah aplikasi sumber di `../sistem-pergudangan`.
- Jangan melakukan operasi destruktif terhadap database sumber.
- Tandai item sebagai `Blocked` jika membutuhkan keputusan atau data yang belum tersedia, lalu tambahkan alasan singkat di bagian ini.
- Perbarui checklist setelah setiap perubahan yang sudah diverifikasi.
