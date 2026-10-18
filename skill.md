# Skill Guide — Sistem Pergudangan

## Navigasi Dokumentasi

- [AGENTS](AGENTS.md) — aturan wajib proyek
- [PRD](prd.md) — kebutuhan dan tujuan produk
- [Architecture](architecture.md) — rancangan teknis sistem
- [Workflow](workflow.md) — alur kerja pengembangan
- [TODO](todo.md) — daftar pekerjaan dan status

## 1. Tujuan

Dokumen ini adalah panduan kerja agent atau developer saat membaca, mengubah, menguji, dan mendokumentasikan aplikasi Sistem Pergudangan berbasis Laravel 12.

Panduan ini melengkapi `AGENTS.md`, `prd.md`, dan `architecture.md`. Jika terjadi konflik, aturan pengguna dan `AGENTS.md` menjadi acuan utama.

## 2. Konteks Produk

Aplikasi mengelola data barang, persediaan, pergerakan stok, pengguna, role, foto, dan berkas privat. Aplikasi sumber di `../sistem-pergudangan` adalah acuan perilaku bisnis dan tidak boleh diubah.

Kompatibilitas yang harus dipertahankan:

- nama tabel dan identifier;
- hash password;
- role dan hak akses;
- endpoint legacy berakhiran `.php`;
- tampilan Bootstrap;
- aturan stok;
- alur dan hasil bisnis dari aplikasi sumber.

## 3. Alur Kerja Wajib

### 3.1 Sebelum Mengubah Kode

1. Baca `AGENTS.md` dan dokumen terkait.
2. Pahami permintaan dan tentukan file atau komponen yang terdampak.
3. Periksa perubahan lokal yang sudah ada agar tidak menimpa pekerjaan pengguna.
4. Cari implementasi terkait menggunakan pencarian file atau teks.
5. Bandingkan perilaku dengan aplikasi sumber bila menyangkut alur bisnis.
6. Tentukan test yang perlu ditambah atau diperbarui.

### 3.2 Saat Mengubah Kode

1. Buat perubahan sekecil mungkin.
2. Ikuti pola controller, middleware, service, helper, model, dan Blade yang sudah ada.
3. Gunakan `AccessService` untuk pemeriksaan akses.
4. Tempatkan alur halaman pada `WarehousePages` dan aksi mutasi pada `WarehouseActions`.
5. Pertahankan perlindungan CSRF.
6. Jangan mengubah aturan bisnis hanya untuk merapikan kode.
7. Jangan menambahkan dependensi atau migrasi tanpa kebutuhan yang jelas.

### 3.3 Setelah Mengubah Kode

1. Tinjau diff dan pastikan hanya file yang relevan berubah.
2. Tambahkan atau perbarui test untuk perilaku yang berubah.
3. Jalankan test Laravel menggunakan database `_test`.
4. Jalankan Laravel Pint pada file PHP yang diubah.
5. Periksa akses file privat dan otorisasi bila perubahan menyentuh data atau file.
6. Perbarui dokumentasi jika kontrak, endpoint, alur, atau keputusan arsitektur berubah.
7. Laporkan hasil verifikasi secara jujur, termasuk validasi yang tidak dapat dijalankan.

## 4. Pemetaan Tanggung Jawab Komponen

### Route

- Memetakan URL ke entry point.
- Mempertahankan endpoint `.php` yang diperlukan.
- Tidak memuat aturan bisnis.

### Middleware

- Menangani autentikasi dan pemeriksaan lintas request.
- Membatasi route sesuai kebutuhan.

### Controller

- Menerima request dan mengembalikan response.
- Menggunakan validasi dan memanggil service yang sesuai.
- Tidak menjadi tempat utama query atau aturan stok.

### WarehousePages

- Mengorkestrasi kebutuhan halaman.
- Menjaga alur halaman dari aplikasi sumber.
- Menyediakan data untuk Blade.

### WarehouseActions

- Menangani mutasi master data dan stok.
- Memvalidasi input dan menjalankan aturan bisnis.
- Menjaga konsistensi perubahan database.

### AccessService

- Menjadi sumber pemeriksaan akses terpusat.
- Digunakan untuk halaman, aksi, data, foto, dan berkas privat.

### Blade

- Menangani presentasi dan Bootstrap.
- Menampilkan aksi sesuai izin, tetapi tidak menggantikan otorisasi server-side.
- Tidak menampilkan rahasia atau path private storage.

## 5. Aturan Keamanan

- Jangan membaca, menampilkan, mencatat, atau mengirim nilai rahasia dari `.env`.
- Jangan menaruh API key, password, token, atau rahasia lain di source code, test, log, atau dokumentasi.
- Jangan mengirim source code atau data operasional ke layanan AI eksternal.
- Gunakan endpoint model lokal yang dikonfigurasi bila fitur AI diperlukan.
- Semua aksi mutasi harus memakai CSRF.
- Semua akses penting harus diverifikasi di sisi server.
- Foto dan berkas privat harus berada di private storage.
- Jangan melayani file sebelum autentikasi dan otorisasi berhasil.
- Jangan membocorkan path internal atau keberadaan file melalui error response.

## 6. Aturan Database dan Migrasi

- Gunakan database salinan yang ditentukan di `.env`.
- Pengujian wajib memakai database `_test`.
- Jangan mengubah database sumber.
- `database.sql` adalah acuan skema.
- Jangan menjalankan `migrate:fresh`.
- Jangan menjalankan `db:wipe`.
- Jangan menjalankan seeder contoh.
- Jangan mengimpor ulang `database.sql` ke database yang sudah berisi data.
- Migrasi tambahan hanya dibuat jika dibutuhkan dan cakupannya jelas.
- Jangan mengubah nama tabel, ID, role, atau hash password tanpa keputusan eksplisit.

## 7. Aturan Implementasi Stok

1. Validasi barang dan jumlah sebelum mutasi.
2. Terapkan aturan stok dari aplikasi sumber.
3. Tolak transaksi yang tidak valid sesuai perilaku sumber.
4. Pastikan perubahan transaksi dan saldo konsisten.
5. Gunakan transaksi database jika beberapa perubahan harus berhasil atau gagal bersama.
6. Tambahkan test untuk stok normal, stok nol, jumlah invalid, dan stok tidak mencukupi.
7. Jangan mengubah perhitungan saldo tanpa regression test.

## 8. Panduan Pengujian

Prioritaskan test untuk:

- login berhasil dan gagal;
- role dan izin;
- akses route;
- CSRF pada aksi mutasi;
- CRUD master barang;
- barang masuk dan barang keluar;
- validasi saldo stok;
- riwayat pergerakan stok;
- akses foto atau berkas privat;
- endpoint legacy;
- perilaku yang dipindahkan dari aplikasi sumber.

Perintah verifikasi:

```text
C:\xampp\php\php.exe artisan test
```

Laravel Pint dijalankan hanya pada file PHP yang diubah. Jangan menyatakan perubahan selesai sebelum hasil test diperiksa atau kegagalan validasi dijelaskan.

## 9. Pola Pencarian dan Diagnosis

Saat menyelidiki fitur:

1. Cari route atau endpoint.
2. Cari controller atau entry point.
3. Ikuti pemanggilan `WarehousePages`, `WarehouseActions`, atau service terkait.
4. Cari pemeriksaan `AccessService` dan middleware.
5. Ikuti query atau model sampai tabel database.
6. Periksa Blade dan form yang menghasilkan request.
7. Bandingkan dengan aplikasi sumber bila perilaku belum jelas.
8. Tulis test minimal yang mereproduksi perilaku atau masalah.

Saat mendiagnosis, jangan langsung mengubah kode sebelum penyebab dan dampak perubahan dipahami.

## 10. Checklist Sebelum Menyerahkan Pekerjaan

- [ ] Scope perubahan sesuai permintaan.
- [ ] Tidak ada perubahan pada aplikasi sumber.
- [ ] Tidak ada operasi destruktif pada database.
- [ ] Tidak ada rahasia dalam file yang berubah.
- [ ] Otorisasi dan CSRF tetap terlindungi.
- [ ] File privat tetap disajikan melalui pemeriksaan akses.
- [ ] Aturan stok tetap kompatibel.
- [ ] Test relevan sudah ditambah atau diperbarui.
- [ ] `C:\xampp\php\php.exe artisan test` sudah dijalankan atau kegagalannya dijelaskan.
- [ ] Laravel Pint sudah dijalankan pada PHP yang diubah atau kegagalannya dijelaskan.
- [ ] Dokumentasi terkait sudah diperbarui.
- [ ] Ringkasan perubahan dan hasil validasi siap disampaikan.

## 11. Format Laporan Perubahan

Gunakan format ringkas berikut saat menyerahkan pekerjaan:

```text
Perubahan:
- <ringkasan file dan perilaku yang berubah>

Verifikasi:
- Test: <lulus / gagal / tidak dapat dijalankan + alasan>
- Pint: <lulus / gagal / tidak dapat dijalankan + alasan>

Catatan:
- <risiko, keputusan, atau pekerjaan lanjutan bila ada>
```
