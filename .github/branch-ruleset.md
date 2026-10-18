# Konfigurasi GitHub Ruleset

Ruleset berikut harus dibuat pada pengaturan repository GitHub karena aturan ini adalah
konfigurasi server, bukan file yang otomatis aktif dari repository.

## Ruleset `protected-branch-flow`

Buat atau import `.github/rulesets/protected-branch-flow.json`. Ruleset ini hanya melindungi
branch `local`, `develop`, `staging`, `main`, dan `master`.

Ruleset ini tidak menargetkan branch pekerjaan dan tidak menggunakan `~ALL`. Branch `feature/*`, `fix/*`, `bugfix/*`,
`hotfix/*`, `refactor/*`, `chore/*`, `docs/*`, dan `test/*` tetap dapat menerima commit dan push.
PR pertama dibuat hanya saat branch pekerjaan siap digabung ke `local`.

- Target: `develop`, `staging`, `main`, dan `master`.
- Bypass list: kosong atau hanya administrator rilis yang disetujui.
- Wajib pull request sebelum merge.
- Wajib minimal 1 approval untuk `develop` dan `staging`, serta minimal 2 approval untuk
  `main/master` bila kebijakan tim memerlukannya.
- Wajib semua percakapan pull request diselesaikan.
- Wajib status checks berikut lulus:
  - `Validasi alur branch dan commit`;
  - `Test dan Pint Laravel`;
  - `Build frontend`;
  - `Playwright Tests / test` jika perubahan frontend atau end-to-end terdampak;
  - `Security / Sensitive file guard`;
  - `Security / Semgrep`;
  - `Security / CodeQL (php)`;
  - `Security / CodeQL (javascript-typescript)`.
- Wajib branch diperbarui sebelum merge bila target sudah berubah.
- Tolak force push dan penghapusan branch terlindungi.
- Aktifkan linear history bila strategi merge tim menggunakan squash atau rebase.

## Arah pull request

Workflow `branch-policy.yml` menegakkan nama branch dan arah berikut:

```text
feature/*, fix/*, bugfix/*, hotfix/*, refactor/*, chore/*, docs/*, test/* -> local
local -> develop
develop -> staging
staging -> main/master
```

Workflow ini berjalan pada semua pull request. Pull request ke branch lain akan gagal, begitu
juga pull request yang melewati salah satu tahap. Dengan demikian branch yang valid pun tetap
harus mengikuti urutan promosi.

Jalankan pemeriksaan lokal sebelum membuka pull request. Ruleset GitHub dan workflow harus
diaktifkan pada repository sebelum branch `local`, `develop`, `staging`, dan `main/master` dipakai tim.
