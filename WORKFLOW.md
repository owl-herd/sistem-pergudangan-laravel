# Workflow Pengembangan Sistem Pergudangan

## Navigasi Dokumentasi

- [AGENTS](AGENTS.md) — aturan wajib proyek
- [PRD](PRD.md) — kebutuhan dan tujuan produk
- [Architecture](ARCHITECTURE.md) — rancangan teknis sistem
- [Skill Guide](SKILL.md) — panduan kerja agent/developer
- [Designs](DESIGNS.md) — pedoman konsistensi antarmuka
- [TODO](TODO.md) — daftar pekerjaan dan status

## 1. Tujuan

Dokumen ini mendefinisikan alur kerja standar untuk perubahan fitur, perbaikan bug, refactoring, dokumentasi, dan pengujian pada aplikasi Sistem Pergudangan.

Workflow ini harus dibaca bersama:

- `AGENTS.md` untuk aturan wajib proyek;
- `PRD.md` untuk kebutuhan produk;
- `ARCHITECTURE.md` untuk batas tanggung jawab komponen;
- `SKILL.md` untuk panduan kerja agent atau developer.
- `DESIGNS.md` untuk token visual, komponen, dan aturan konsistensi UI.

## 2. Status Pekerjaan

Gunakan status berikut untuk melacak pekerjaan:

```text
Backlog -> Ready -> In Progress -> Verification -> Done
                         |
                       Blocked
```

- **Backlog:** pekerjaan sudah dicatat, tetapi belum diprioritaskan.
- **Ready:** scope dan kriteria penerimaan sudah jelas.
- **In Progress:** pekerjaan sedang dianalisis atau diimplementasikan.
- **Verification:** implementasi selesai dan sedang diuji atau direview.
- **Blocked:** pekerjaan tertahan oleh keputusan, akses, data, atau kondisi eksternal.
- **Done:** implementasi dan verifikasi telah selesai.

## 3. Tahap 1 — Penerimaan Tugas

1. Baca permintaan pengguna secara lengkap.
2. Tentukan apakah tugas berupa fitur, bug fix, refactoring, dokumentasi, atau pengujian.
3. Identifikasi hasil akhir yang diminta.
4. Pastikan perubahan berada dalam ruang lingkup aplikasi Sistem Pergudangan.
5. Jika kebutuhan material masih ambigu, tanyakan hanya keputusan yang benar-benar diperlukan.
6. Catat tugas pada `TODO.md` bila pekerjaan terdiri dari beberapa langkah.

### Output

- tujuan tugas;
- scope yang disepakati;
- kriteria penerimaan awal;
- daftar file atau komponen yang kemungkinan terdampak.

## 3a. Aturan branch dan promosi

Sebelum mengubah file, buat branch pekerjaan dari branch dasar yang sesuai. Pilih awalan
berdasarkan jenis perubahan:

```text
feature/   fitur baru
fix/       perbaikan perilaku yang salah
bugfix/    perbaikan bug yang teridentifikasi
hotfix/    perbaikan darurat yang disetujui
refactor/  perapian tanpa perubahan perilaku
chore/     pemeliharaan tooling atau dependensi
docs/      dokumentasi
test/      penambahan atau perbaikan test
```

Urutan wajibnya adalah:

```text
branch pekerjaan -> local -> develop -> staging -> main/master
```

Tahap `local` berarti seluruh pemeriksaan dijalankan sebelum push atau membuat pull request.
Branch terlindungi tidak boleh diubah langsung. Pull request harus mengikuti pasangan berikut:

Branch pekerjaan tetap bebas untuk commit dan push selama implementasi. Pull request baru wajib
dibuat ketika perubahan siap dipromosikan dari branch pekerjaan ke `local`.

| Branch sumber | Branch tujuan | Syarat utama |
| --- | --- | --- |
| branch pekerjaan | `local` | pemeriksaan lokal dan CI lulus |
| `local` | `develop` | CI lulus dan review disetujui |
| `develop` | `staging` | CI lulus dan review disetujui |
| `staging` | `main` atau `master` | CI lulus, review rilis, dan persetujuan pemilik |

Setiap commit harus fokus pada satu perubahan dan ditulis dalam bahasa Indonesia. Format yang
digunakan adalah `jenis: ringkasan`, contohnya `fix: validasi stok negatif pada penerimaan`.
Jangan memakai commit sementara seperti `WIP`, `fix lagi`, atau `update`.

Pemeriksaan lokal minimum:

```text
C:\\xampp\\php\\php.exe artisan test
vendor/bin/pint --test
npm run build
```

Jika perubahan PHP dilakukan, jalankan Pint pada file yang berubah tanpa opsi `--test` sebelum
commit. CI mengulangi pemeriksaan ini agar hasil lokal tidak menjadi satu-satunya pengaman.

## 4. Tahap 2 — Pemeriksaan Awal

1. Baca `AGENTS.md` sebelum melakukan perubahan.
2. Baca bagian relevan dari `PRD.md`, `ARCHITECTURE.md`, dan `SKILL.md`.
3. Periksa status perubahan lokal agar pekerjaan pengguna tidak tertimpa.
4. Cari route, controller, service, model, middleware, helper, view, test, dan konfigurasi yang terkait.
5. Jika alur menyangkut perilaku bisnis, bandingkan dengan aplikasi sumber di `../sistem-pergudangan`.
6. Jangan mengubah aplikasi sumber.

### Hal yang Harus Diidentifikasi

- route dan endpoint legacy yang terlibat;
- role dan pemeriksaan akses;
- tabel atau data yang terdampak;
- aturan stok yang terkait;
- foto atau berkas privat yang mungkin terlibat;
- test yang sudah tersedia;
- risiko kompatibilitas.

## 5. Tahap 3 — Perencanaan Perubahan

Sebelum mengedit, tentukan:

1. komponen utama yang perlu diubah;
2. perubahan minimum untuk memenuhi kebutuhan;
3. test yang perlu ditambah atau diperbarui;
4. dokumentasi yang perlu diselaraskan;
5. risiko terhadap database, role, endpoint, dan perilaku legacy.

Untuk perubahan database, pastikan terlebih dahulu bahwa migrasi benar-benar diperlukan. Jangan membuat migrasi hanya untuk menggantikan skema tabel lama yang sudah menjadi kontrak aplikasi.

## 6. Tahap 4 — Implementasi

### Aturan Umum

- Ikuti pola kode yang sudah ada.
- Gunakan perubahan kecil dan terarah.
- Pertahankan nama tabel, ID, hash password, role, endpoint `.php`, dan aturan stok.
- Gunakan `AccessService` untuk pemeriksaan akses.
- Gunakan `WarehousePages` untuk orkestrasi halaman.
- Gunakan `WarehouseActions` untuk aksi mutasi dan alur bisnis.
- Pertahankan perlindungan CSRF.
- Jangan menambahkan dependensi tanpa alasan yang jelas.

### Pembagian Tanggung Jawab

- **Route:** pemetaan URL dan middleware.
- **Middleware:** autentikasi dan pemeriksaan lintas request.
- **Controller:** request, validasi, pemanggilan service, dan response.
- **Service:** orkestrasi dan aturan lintas komponen.
- **WarehouseActions:** mutasi data dan aturan stok.
- **WarehousePages:** kebutuhan halaman dan data untuk view.
- **Blade:** presentasi dan Bootstrap.
- **Model/query:** akses data tanpa mengubah makna skema sumber.

## 7. Tahap 5 — Keamanan dan Data

Sebelum menganggap implementasi selesai, verifikasi:

- tidak ada rahasia `.env` yang dibaca untuk ditampilkan atau dicatat;
- tidak ada password, API key, token, atau credential di source code, test, log, atau dokumentasi;
- semua aksi mutasi memakai CSRF;
- pemeriksaan akses dilakukan di sisi server;
- foto dan berkas privat berada di private storage;
- akses file selalu melewati autentikasi dan otorisasi;
- error tidak membocorkan path internal atau data sensitif;
- database yang digunakan adalah database salinan yang ditentukan;
- database sumber tidak disentuh oleh operasi destruktif.

## 8. Tahap 6 — Pengujian

### Pengujian Perubahan

Tambahkan atau perbarui test untuk:

- kasus berhasil;
- input invalid;
- akses tanpa login;
- akses dengan role yang tidak sesuai;
- CSRF pada mutasi;
- batas stok dan saldo;
- data kosong atau tidak ditemukan;
- akses foto atau berkas privat;
- kompatibilitas endpoint legacy bila terdampak.

### Perintah Wajib

```text
C:\xampp\php\php.exe artisan test
```

Test hanya boleh menulis ke database pengujian `_test`. Jangan menjalankan perintah berikut pada database berisi data:

```text
migrate:fresh
db:wipe
```

Setelah perubahan PHP, jalankan Laravel Pint pada file PHP yang diubah.

### Smoke Test Browser

Jalankan smoke test Playwright hanya terhadap server loopback dengan environment testing dan
database `_test`. Server dan tes dijalankan pada terminal terpisah:

```powershell
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8001 --env=testing
npm.cmd run test:e2e
```

`E2E_BASE_URL` hanya boleh menunjuk ke `localhost` atau alamat loopback. Smoke test saat ini
hanya melakukan GET; jangan gunakan akun, cookie, atau database produksi. Untuk memperluas tes
ke halaman terautentikasi, siapkan fixture E2E yang terisolasi terlebih dahulu.

## 9. Tahap 7 — Review Perubahan

Review hasil kerja untuk memastikan:

1. perubahan sesuai scope;
2. tidak ada file tidak relevan yang ikut berubah;
3. aturan bisnis tidak berubah tanpa sengaja;
4. otorisasi tidak dapat dilewati melalui request manual;
5. query dan input aman;
6. file privat tetap terlindungi;
7. endpoint legacy tetap tersedia bila diperlukan;
8. pesan validasi dan error dapat dipahami;
9. dokumentasi terkait sudah diperbarui;
10. tidak ada rahasia dalam diff.

## 10. Tahap 8 — Penyelesaian

Pekerjaan dapat ditandai `Done` jika:

- implementasi selesai;
- kriteria penerimaan terpenuhi;
- test relevan lulus;
- Laravel Pint lulus pada PHP yang diubah;
- tidak ada risiko keamanan yang belum ditangani;
- dokumentasi sudah selaras;
- hasil verifikasi dapat dijelaskan.

Jika validasi tidak dapat dijalankan, pekerjaan tidak boleh diklaim tervalidasi penuh. Catat perintah yang gagal dan alasan kegagalannya.

## 11. Penanganan Blocked

Tandai pekerjaan sebagai `Blocked` jika kemajuan tidak dapat dilanjutkan tanpa keputusan, akses, data, atau perubahan kondisi eksternal.

Catat:

- penyebab blokir;
- pemeriksaan yang sudah dilakukan;
- dampak terhadap scope atau jadwal;
- keputusan atau akses yang diperlukan untuk melanjutkan.

Jangan menyelesaikan pekerjaan dengan asumsi yang dapat mengubah perilaku bisnis, merusak data, atau memperluas scope secara material.

## 12. Workflow Khusus Perubahan Database

1. Pastikan kebutuhan perubahan skema benar-benar ada.
2. Periksa dampak pada database sumber dan database salinan.
3. Jangan mengubah database sumber.
4. Jangan menggunakan `migrate:fresh`, `db:wipe`, seeder contoh, atau impor ulang `database.sql` pada database terisi.
5. Buat migrasi tambahan sekecil mungkin jika memang diperlukan.
6. Tambahkan test untuk skema dan perilaku yang terdampak.
7. Verifikasi menggunakan database pengujian `_test`.
8. Dokumentasikan keputusan dan risiko kompatibilitas.

## 13. Workflow Khusus Perubahan Stok

1. Identifikasi aturan transaksi pada aplikasi sumber.
2. Temukan action yang menangani mutasi.
3. Pastikan validasi barang dan jumlah dilakukan sebelum perubahan.
4. Pastikan aturan stok tidak dapat dilewati melalui request manual.
5. Gunakan transaksi database bila beberapa perubahan harus atomik.
6. Uji stok cukup, stok nol, stok kurang, jumlah nol, jumlah negatif, dan barang tidak ditemukan.
7. Verifikasi saldo dan riwayat setelah transaksi.
8. Tambahkan regression test jika perilaku dipindahkan atau dirapikan.

## 14a. Checklist pull request

- [ ] branch menggunakan awalan yang diizinkan dan tidak mengubah branch terlindungi langsung;
- [ ] tujuan pull request sesuai urutan promosi;
- [ ] setiap perubahan bermakna memiliki commit terpisah;
- [ ] subjek commit menggunakan bahasa Indonesia dan format yang disepakati;
- [ ] test Laravel, Pint, build frontend, dan pemeriksaan Playwright sesuai scope lulus;
- [ ] tidak ada rahasia, file `.env`, data privat, atau artefak build dalam diff;
- [ ] pemeriksaan sensitive-file guard, Semgrep, dan CodeQL lulus;
- [ ] perubahan database, keamanan, akses, stok, dan endpoint legacy sudah direview;
- [ ] status CI dan review wajib lulus sebelum merge.

## 14. Format Laporan Akhir

```text
Status: Done / Blocked

Ringkasan:
- <apa yang diubah>

File utama:
- <file atau dokumen yang berubah>

Verifikasi:
- Test: <hasil>
- Pint: <hasil>
- Pemeriksaan lain: <hasil>

Catatan:
- <risiko, asumsi, atau pekerjaan lanjutan>
```
