---
name: frontend-design
description: Merancang dan mengubah antarmuka frontend aplikasi Sistem Pergudangan Laravel dengan mempertahankan pola Blade, Bootstrap, aksesibilitas, dan perilaku aplikasi sumber.
---

# Skill Desain Frontend — Sistem Pergudangan

Gunakan panduan ini saat membuat atau mengubah halaman, komponen Blade, CSS, JavaScript, form, navigasi, atau tata letak antarmuka aplikasi.

## Prinsip utama

- Pertahankan tampilan Bootstrap dan pola antarmuka yang sudah digunakan. Jangan mengganti framework atau melakukan redesign menyeluruh kecuali diminta.
- Jaga kompatibilitas dengan aplikasi sumber: jangan mengubah endpoint `.php`, aturan stok, role, izin, atau perilaku bisnis sebagai bagian dari perubahan tampilan.
- Ikuti struktur Blade, komponen, aset, dan konvensi CSS/JavaScript yang sudah ada sebelum menambahkan pola baru.
- Buat perubahan sekecil mungkin dan selaraskan semua halaman atau komponen terkait.
- Jangan menambahkan dependensi hanya untuk mempercantik atau mempermudah satu komponen.

## Alur kerja

### Sebelum mengubah antarmuka

1. Baca `AGENTS.md`, `prd.md`, `architecture.md`, dan panduan proyek yang relevan.
2. Periksa halaman, layout, komponen, stylesheet, script, dan route yang terkait.
3. Periksa perubahan lokal sebelum mengedit agar pekerjaan pengguna tidak tertimpa.
4. Jika suatu halaman menyalin perilaku dari aplikasi sumber, gunakan sumber hanya sebagai acuan dan jangan mengubahnya.
5. Identifikasi keadaan normal, kosong, memuat, validasi gagal, sukses, dan akses ditolak yang perlu ditampilkan.

### Saat merancang atau mengubah antarmuka

- Gunakan struktur semantik dan hierarki visual yang jelas; prioritaskan keterbacaan dan konsistensi.
- Pastikan halaman tetap dapat digunakan pada layar kecil dan dengan keyboard.
- Berikan label yang jelas pada input, tombol, tautan, dan kontrol; jangan mengandalkan warna atau ikon saja untuk menyampaikan makna.
- Tampilkan pesan validasi dan status dari server secara jelas, tanpa menyembunyikan kegagalan.
- Pertahankan nilai lama dan konteks form sesuai pola Laravel yang sudah digunakan.
- Pertahankan token CSRF pada form mutasi sesuai pola proyek.
- Tampilkan tombol atau menu sesuai izin untuk pengalaman pengguna, tetapi jangan menganggap penyembunyian elemen sebagai otorisasi.
- Jangan menampilkan rahasia, path penyimpanan privat, atau data yang tidak diperlukan di antarmuka.
- Jangan memuat atau menyajikan foto dan berkas privat secara langsung; gunakan endpoint yang sudah memeriksa akses.
- Hindari JavaScript yang mengubah aturan bisnis atau melewati validasi dan otorisasi server.

### Setelah mengubah antarmuka

1. Tinjau diff dan pastikan perubahan hanya mencakup antarmuka yang diminta.
2. Uji tampilan dan interaksi pada ukuran layar yang relevan jika lingkungan tersedia.
3. Jalankan test Laravel yang terkait; untuk validasi proyek gunakan `C:\xampp\php\php.exe artisan test` dan database pengujian `_test`.
4. Jika file PHP diubah, jalankan Laravel Pint hanya pada file PHP yang berubah.
5. Laporkan hasil verifikasi dan jelaskan validasi yang tidak dapat dijalankan.

## Batas proyek

- Aplikasi sumber di `../sistem-pergudangan` adalah acuan dan tidak boleh diubah.
- Gunakan hanya database salinan yang ditentukan di `.env`; tes hanya boleh memakai database `_test`.
- Jangan menjalankan `migrate:fresh`, `db:wipe`, seeder contoh, atau mengimpor ulang `database.sql` ke database berisi data.
- Jangan membaca, menampilkan, mencatat, atau mengirim nilai rahasia dari `.env`.
- Jangan mengirim source code atau data operasional ke layanan AI eksternal.
