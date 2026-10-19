# Pedoman Desain Antarmuka GudangKita

Dokumen ini menjadi acuan bersama untuk menjaga tampilan aplikasi Sistem Pergudangan tetap
konsisten. Pedoman berlaku untuk halaman Blade, komponen, form, tabel, navigasi, CSS, dan
JavaScript frontend.

## 1. Prinsip desain

- Pertahankan Bootstrap 5 sebagai fondasi komponen dan grid.
- Utamakan keterbacaan, alur kerja gudang, dan kejelasan status operasional.
- Gunakan perubahan visual sekecil mungkin dan jangan mengubah endpoint, role, otorisasi,
  aturan stok, atau perilaku bisnis.
- Gunakan bahasa Indonesia untuk label, pesan validasi, status, tombol, dan bantuan pengguna.
- Tampilkan keadaan normal, kosong, memuat, berhasil, gagal, dan tidak berwenang secara jelas.
- Warna dan ikon tidak boleh menjadi satu-satunya pembawa makna; selalu sertakan teks atau label.

## 2. Identitas visual

Token warna utama sudah didefinisikan di `public/assets/style.css` pada `:root`.

| Token | Nilai saat ini | Penggunaan |
| --- | --- | --- |
| `--brand-blue` | `#0867bd` | tombol utama, tautan, aksen aktif |
| `--brand-blue-dark` | `#123a68` | navbar, hover, teks identitas |
| `--brand-cyan` | `#13a8e2` | informasi dan variasi statistik |
| `--brand-yellow` | `#ffbd08` | perhatian dan status menunggu |
| `--brand-orange` | `#f28a00` | aksen peringatan tambahan |
| `--brand-red` | `#e51d2a` | bahaya, stok rendah, tindakan destruktif |
| `--bs-body-bg` | `#f5f8fc` | latar halaman |
| `--bs-body-color` | `#26374a` | teks utama |

Aturan penggunaan:

- Gunakan token yang tersedia sebelum membuat warna baru.
- Gunakan `btn-primary` untuk tindakan utama dan `btn-outline-secondary` untuk tindakan
  sekunder.
- Gunakan `alert-success`, `alert-warning`, `alert-danger`, dan `alert-info` sesuai makna
  statusnya.
- Gunakan `text-bg-warning` atau `text-bg-danger` untuk badge ringkas yang tetap memiliki teks.
- Pastikan kontras teks dan latar dapat dibaca pada layar kecil maupun besar.

## 3. Tipografi dan hierarki

- Font utama mengikuti `--bs-font-sans-serif` dan tidak boleh diganti per halaman.
- Gunakan satu `h1` sebagai judul halaman; gunakan `h2` atau `h3` untuk bagian di dalamnya.
- Gunakan `section-kicker` untuk konteks singkat, bukan sebagai pengganti judul.
- Gunakan kalimat pendek pada tombol: `Simpan`, `Kirim`, `Lihat laporan`, `Tutup`.
- Gunakan `fw-semibold` atau `fw-bold` secara hemat untuk menekankan informasi penting.
- Format angka, tanggal, dan satuan harus konsisten dengan helper atau pola yang sudah ada.

## 4. Tata letak dan spacing

- Gunakan `.container` atau `.container-fluid` sesuai kepadatan halaman yang sudah ada.
- Gunakan grid Bootstrap (`row`, `col`, `g-*`) dan utility spacing (`p-*`, `m-*`, `gap-*`).
- Pertahankan jarak visual bertingkat: halaman, section, card, lalu isi komponen.
- Kelompokkan informasi terkait dalam `.card` atau `.app-card`.
- Dashboard boleh menggunakan statistik ringkas di bagian atas, lalu tabel atau aktivitas detail.
- Dashboard memakai sidebar untuk navigasi bagian kerja dan bar atas untuk identitas akun serta notifikasi. Halaman operasional lain dapat mempertahankan navbar sesuai kebutuhan halamannya.

## 5. Navigasi dan navbar

- Pertahankan `dashboard-navbar` dan `dashboard-top-navigation` sebagai pola dashboard.
- Tautan aktif harus memiliki `active` dan, bila relevan, `aria-current`.
- Label navigasi harus menjelaskan tujuan, bukan hanya ikon.
- Tombol menu mobile harus memiliki `aria-label`, `aria-controls`, dan `aria-expanded` yang benar.
- Menu dan tombol akun harus mengikuti pembatasan role di server; penyembunyian visual bukan
  pengganti pemeriksaan otorisasi.

## 6. Card, statistik, dan status

- Gunakan `.app-card` sebagai wadah standar untuk panel.
- Gunakan `.stat-card` untuk angka ringkasan; satu kartu menyampaikan satu metrik utama.
- Variasi `stat-cyan`, `stat-yellow`, dan `stat-red` hanya dipakai untuk membedakan makna status.
- Tampilkan label, nilai, dan keterangan dalam urutan yang konsisten.
- Status stok rendah harus memiliki teks penjelas dan tautan tindakan, bukan hanya warna merah.

## 7. Form dan validasi

- Setiap input wajib memiliki `label` yang terlihat dan terkait dengan `for`/`id`.
- Pertahankan nilai input saat validasi gagal bila aman untuk dilakukan.
- Tampilkan error server dekat form dan gunakan `role="alert"` untuk ringkasan error penting.
- Tombol submit harus menjelaskan aksi dan tidak memakai label ambigu seperti `Submit` atau
  `Update` tanpa konteks.
- Form mutasi wajib mempertahankan CSRF dan method yang sudah digunakan aplikasi.
- Jangan menampilkan password, token, path private storage, atau data sensitif pada pesan error.

## 8. Tabel, daftar, dan laporan

- Bungkus tabel lebar dengan `.table-responsive`.
- Gunakan header tabel yang jelas dan urutan kolom yang mengikuti alur kerja pengguna.
- Gunakan `scope="col"` pada header kolom jika markup memungkinkan.
- Sediakan keadaan kosong yang menjelaskan mengapa belum ada data dan tindakan berikutnya.
- Tampilkan filter aktif, periode laporan, dan status hasil secara eksplisit.
- Untuk data stok, tampilkan satuan dan konteks gudang agar angka tidak ambigu.

## 9. Responsif dan aksesibilitas

- Uji paling tidak lebar mobile, tablet, dan desktop.
- Jangan mengandalkan hover untuk membuka informasi penting.
- Semua kontrol harus dapat dijangkau dengan keyboard dan memiliki focus state yang terlihat.
- Gunakan elemen semantik (`nav`, `main`, `section`, `article`, `button`) sesuai fungsi.
- Gunakan `alt` yang bermakna untuk gambar informatif dan `alt=""` untuk gambar dekoratif.
- Pastikan modal, dropdown, navbar collapse, dan alert Bootstrap memiliki label aksesibilitas.
- Jangan menghapus outline focus tanpa pengganti yang setara atau lebih jelas.

## 10. JavaScript dan aset

- Gunakan JavaScript hanya untuk interaksi tampilan; aturan bisnis dan otorisasi tetap di server.
- Gunakan aset yang sudah ada sebelum menambahkan dependensi atau library baru.
- Jangan memasukkan rahasia, data operasional, atau isi `.env` ke bundle frontend.
- Beri fallback yang tetap dapat digunakan jika JavaScript gagal dimuat.
- Perubahan CSS global harus diuji pada dashboard, login, laporan, form, dan halaman aktivitas.

## 11. Checklist sebelum merge

- [ ] Tampilan mengikuti token warna dan pola Bootstrap yang sudah ada.
- [ ] Struktur heading, label form, status, dan pesan error jelas.
- [ ] Layout diuji pada mobile dan desktop.
- [ ] Navigasi keyboard dan focus state tetap berfungsi.
- [ ] Role, CSRF, endpoint legacy, dan aturan bisnis tidak berubah.
- [ ] Tidak ada rahasia atau data privat di markup, CSS, JavaScript, atau screenshot.
- [ ] Test Laravel terkait lulus.
- [ ] `npm run build` lulus jika aset frontend berubah.
- [ ] Laravel Pint dijalankan jika file PHP berubah.
