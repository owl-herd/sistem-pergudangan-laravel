# Panduan Agent Project

## Navigasi Dokumentasi

- [PRD](PRD.md) — kebutuhan dan tujuan produk
- [Architecture](ARCHITECTURE.md) — rancangan teknis sistem
- [Skill Guide](SKILL.md) — panduan kerja agent/developer
- [Workflow](WORKFLOW.md) — alur kerja pengembangan
- [Designs](DESIGNS.md) — pedoman konsistensi antarmuka
- [TODO](TODO.md) — daftar pekerjaan dan status

## Strategi branch dan commit

- Jangan mengerjakan perubahan langsung pada `develop`, `staging`, `main`, atau `master`.
- Setiap perubahan harus dimulai dari branch pekerjaan dengan pola `feature/<nama>`,
  `fix/<nama>`, `bugfix/<nama>`, `hotfix/<nama>`, `refactor/<nama>`, `chore/<nama>`,
  `docs/<nama>`, atau `test/<nama>`.
- Alur integrasi wajib: `branch pekerjaan -> local -> develop -> staging -> main/master`.
  `local` adalah tahap pemeriksaan di komputer developer, bukan branch remote.
- Pull request hanya boleh mengarah ke `local` dari branch pekerjaan, ke `develop` dari
  `local`, ke `staging` dari `develop`, dan ke `main` atau `master` dari `staging`.
- Setiap perubahan yang bermakna harus memiliki commit tersendiri. Gunakan bahasa Indonesia
  pada subjek commit dengan format `<jenis>: <ringkasan bahasa Indonesia>`.
- Branch pekerjaan tidak dikunci: developer boleh membuat commit dan push berkali-kali pada
  branch tersebut. Pull request pertama dibuat saat menggabungkan branch pekerjaan ke `local`.
- Jangan menggabungkan pull request jika pemeriksaan branch, commit, keamanan, test, dan lint
  belum lulus.

## Batas migrasi dan data

- Aplikasi sumber di `../sistem-pergudangan` adalah acuan dan tidak boleh diubah.
- Gunakan hanya database salinan yang ditentukan di `.env`. Tes harus memakai database `_test`.
- Jangan menjalankan `migrate:fresh`, `db:wipe`, seeder contoh, atau mengimpor ulang `database.sql` ke database yang sudah terisi. Jangan pernah menjalankan operasi destruktif pada database sumber.
- `database.sql` adalah acuan skema. Migrasi scaffold Laravel untuk tabel lama dinonaktifkan; migrasi tambahan hanya boleh diterapkan sesuai cakupan yang dimaksud.
- Pertahankan nama tabel, ID, hash password, role, endpoint `.php`, tampilan Bootstrap, aturan stok, serta perilaku bisnis sumber.
- Foto dan berkas privat harus tetap berada di penyimpanan privat dan hanya dilayani setelah pemeriksaan akses.

## Arsitektur dan implementasi

- Aplikasi memakai Laravel 12, tetapi layanan `WarehousePages` dan `WarehouseActions` mempertahankan alur serta aturan bisnis prosedural yang dipindahkan dari aplikasi sumber. Jangan mengubah perilaku bisnis saat merapikan kode.
- Ikuti pola controller, middleware, service, helper, dan Blade yang sudah ada. Gunakan `AccessService` untuk pemeriksaan akses dan pertahankan perlindungan CSRF.
- Buat perubahan sekecil mungkin, selaraskan semua route/view/service yang terkait, dan tambahkan atau perbarui tes untuk perilaku yang berubah.
- Jangan menambahkan dependensi atau migrasi skema tanpa kebutuhan yang jelas.

## Keamanan dan AI

- Jangan membaca, menampilkan, mencatat, atau mengirim nilai rahasia dari `.env`, termasuk API key dan password. Jangan pernah menaruh rahasia di source code, tes, log, atau dokumentasi.
- Jangan mengirim source code atau data operasional ke layanan AI eksternal. Asisten development hanya boleh memakai endpoint model lokal yang dikonfigurasi.
- Tindakan yang mengubah file atau menjalankan perintah harus dijelaskan dan meminta persetujuan pengguna terlebih dahulu.

## Verifikasi

- Jalankan tes Laravel dengan `C:\xampp\php\php.exe artisan test`; tes hanya boleh menulis ke database pengujian yang ditetapkan.
- Jalankan Laravel Pint pada file PHP yang diubah.
- Jangan menyatakan tugas selesai sebelum memeriksa hasil tes atau menyebutkan dengan jelas jika validasi tidak dapat dijalankan.
- Lihat [README.md](README.md) untuk cara menjalankan aplikasi, konfigurasi database, dan prosedur operasional lainnya.
