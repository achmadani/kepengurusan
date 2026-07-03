# Struktur Organisasi — Generator Kepengurusan

Aplikasi web untuk membuat & memvisualisasikan struktur organisasi kepengurusan.

- **Backend:** PHP 8.2 (REST API, PDO MySQL) — tanpa framework/dependency.
- **Frontend:** HTML + CSS + JavaScript murni (tanpa build step). Ringan & cepat.
- **Database:** MySQL 8 (port `3308`, user `root`, password `toor`), database `organinisasi`.

## Menjalankan

```bash
./start.sh            # default http://127.0.0.1:8080
# atau port lain:
./start.sh 9000
```

Atau manual:

```bash
php82 -S 127.0.0.1:8080 router.php
```

Buka http://127.0.0.1:8080 lalu login:

```
username : admin
password : admin
```

Skema tabel dibuat otomatis saat pertama kali dijalankan, lengkap dengan
contoh struktur organisasi (bisa langsung diubah/dihapus).

## Fitur

- **Login** — sesi berbasis cookie (`admin` / `admin`).
- **Dashboard / Struktur** — bagan organisasi hierarkis (zoom in/out).
- **Kelola Data** — tambah, ubah, hapus anggota & jabatan; pilih atasan
  (parent) untuk membentuk hierarki; atur urutan; foto/avatar opsional.
- **Nama organisasi** dapat diubah dari menu Kelola Data.

## Struktur berkas

Folder proyek ini **adalah** web root — tidak ada sub-folder `public/`.
Ini sengaja dibuat begitu supaya mudah dideploy di server jenis apa pun
(termasuk shared hosting yang DocumentRoot-nya tidak bisa diubah): tinggal
upload semua isi ke `public_html`. Lihat [DEPLOY.md](DEPLOY.md) untuk detail
dan cara mengamankan folder `api/`.

```
index.html      UI
style.css       Tampilan (glassmorphism, bagan CSS)
app.js          Logika frontend (fetch API, render bagan, CRUD)
.htaccess       Rewrite Apache: /api/* -> api/index.php, SPA fallback
api/
  db.php        Koneksi PDO + auto-create schema + seed contoh
  index.php     REST API (login, members, settings)
  .htaccess     Blokir akses langsung ke db.php/config, hanya index.php
  config.local.php.example   Template kredensial produksi
router.php      Router untuk PHP built-in server (dev only)
start.sh        Skrip menjalankan server (dev only)
```

## API ringkas

| Method | Endpoint            | Keterangan                    |
|--------|---------------------|-------------------------------|
| POST   | `/api/login`        | `{username, password}`        |
| POST   | `/api/logout`       | Keluar                        |
| GET    | `/api/session`      | Cek status login              |
| GET    | `/api/members`      | Daftar anggota                |
| POST   | `/api/members`      | Tambah anggota                |
| PUT    | `/api/members/{id}` | Ubah anggota                  |
| DELETE | `/api/members/{id}` | Hapus anggota (+bawahannya)   |
| GET    | `/api/settings`     | Ambil pengaturan (nama org)   |
| PUT    | `/api/settings`     | Ubah nama organisasi          |
