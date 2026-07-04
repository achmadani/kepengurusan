# Dokumentasi API — Kepengurusan

Semua endpoint di-prefix `/api`, mengembalikan **JSON**
(`Content-Type: application/json; charset=utf-8`), dan menggunakan
**session cookie** (PHP `session_start()`) untuk autentikasi — bukan token.
Jadi klien HTTP (curl/Postman) harus menyimpan & mengirim ulang cookie
antar-request (`-c cookie.txt -b cookie.txt` di curl).

Base URL contoh: `http://127.0.0.1:8099` (dev) atau domain produksi.

## Autentikasi

Endpoint ditandai **🔒** membutuhkan sesi login aktif (lihat
[`POST /api/login`](#post-apilogin)). Jika belum login, semua endpoint 🔒
selalu membalas:

```json
{ "error": "Tidak terautentikasi" }
```

**Status:** `401 Unauthorized`

## Format error umum

| Status | Kapan terjadi |
|--------|---------------|
| `401`  | Login salah, atau belum/tidak login saat mengakses endpoint 🔒 |
| `404`  | Path tidak cocok dengan endpoint mana pun |
| `422`  | Body request tidak valid (field wajib kosong / relasi tidak valid) |
| `500`  | Kesalahan tak terduga di server (mis. koneksi database gagal) |

Semua respons error berbentuk `{ "error": "<pesan>" }`, kecuali `404` yang
menyertakan `path`, dan `500` yang menyertakan `detail`.

---

## Daftar Endpoint

| Method | Endpoint            | Auth | Keterangan                  |
|--------|----------------------|:---:|------------------------------|
| POST   | `/api/login`         |  –  | Login                        |
| POST   | `/api/logout`        |  –  | Logout                       |
| GET    | `/api/session`       |  –  | Cek status login             |
| GET    | `/api/settings`      | 🔒  | Ambil pengaturan (nama org)  |
| PUT    | `/api/settings`      | 🔒  | Ubah nama organisasi         |
| GET    | `/api/members`       | 🔒  | Daftar semua anggota         |
| POST   | `/api/members`       | 🔒  | Tambah anggota               |
| PUT    | `/api/members/{id}`  | 🔒  | Ubah anggota                 |
| DELETE | `/api/members/{id}`  | 🔒  | Hapus anggota (+ bawahannya) |

---

## POST `/api/login`

Login dan membuat sesi. Kredensial dibandingkan dengan `APP_USER`/`APP_PASS`
(lihat [DEPLOY.md](DEPLOY.md) untuk cara mengganti kredensial produksi).

**Body**

```json
{
  "username": "admin",
  "password": "admin"
}
```

**✅ Sukses — `200 OK`**

```json
{ "ok": true, "user": "admin" }
```

**❌ Gagal (username/password salah) — `401 Unauthorized`**

```json
{ "error": "Username atau password salah" }
```

```bash
curl -c cookie.txt -X POST http://127.0.0.1:8099/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin"}'
```

---

## POST `/api/logout`

Menghapus sesi aktif. Selalu berhasil, tidak butuh body.

**✅ Sukses — `200 OK`**

```json
{ "ok": true }
```

```bash
curl -b cookie.txt -X POST http://127.0.0.1:8099/api/logout
```

---

## GET `/api/session`

Mengecek apakah sesi saat ini terautentikasi. Tidak pernah gagal (bukan 🔒) —
dipakai frontend untuk memutuskan menampilkan halaman login atau dashboard.

**✅ Sudah login — `200 OK`**

```json
{ "authenticated": true, "user": "admin" }
```

**✅ Belum login — `200 OK`**

```json
{ "authenticated": false, "user": null }
```

```bash
curl -b cookie.txt http://127.0.0.1:8099/api/session
```

---

## GET `/api/settings` 🔒

Mengambil semua pengaturan aplikasi sebagai object `{key: value}`.
Saat ini hanya berisi `org_name`.

**✅ Sukses — `200 OK`**

```json
{ "org_name": "Struktur Organisasi Kepengurusan" }
```

**❌ Belum login — `401 Unauthorized`**

```json
{ "error": "Tidak terautentikasi" }
```

```bash
curl -b cookie.txt http://127.0.0.1:8099/api/settings
```

---

## PUT `/api/settings` 🔒

Mengubah nama organisasi (upsert — dibuat jika belum ada).

**Body**

```json
{ "org_name": "Karang Taruna Maju Bersama" }
```

**✅ Sukses — `200 OK`**

```json
{ "ok": true, "org_name": "Karang Taruna Maju Bersama" }
```

**❌ `org_name` kosong — `422 Unprocessable Entity`**

```json
{ "error": "Nama organisasi wajib diisi" }
```

**❌ Belum login — `401 Unauthorized`**

```json
{ "error": "Tidak terautentikasi" }
```

```bash
curl -b cookie.txt -X PUT http://127.0.0.1:8099/api/settings \
  -H "Content-Type: application/json" \
  -d '{"org_name":"Karang Taruna Maju Bersama"}'
```

---

## GET `/api/members` 🔒

Mengambil seluruh anggota (flat list — bukan pohon/tree). Frontend yang
menyusunnya menjadi hierarki berdasarkan `parent_id`. Urutan hasil:
`sort_order ASC, id ASC`.

**Skema field**

| Field        | Tipe            | Keterangan                                   |
|--------------|-----------------|-----------------------------------------------|
| `id`         | integer         | ID anggota                                     |
| `name`       | string          | Nama lengkap                                   |
| `position`   | string          | Jabatan                                        |
| `parent_id`  | integer \| null | ID atasan; `null` jika berada di puncak         |
| `sort_order` | integer         | Urutan tampilan di antara saudara sejawat      |
| `photo`      | string \| null  | URL foto/avatar; `null` jika tidak diisi        |

**✅ Sukses — `200 OK`**

```json
[
  {
    "id": 1,
    "name": "Ahmad Dani",
    "position": "Ketua Umum",
    "parent_id": null,
    "sort_order": 0,
    "photo": null
  },
  {
    "id": 2,
    "name": "Siti Rahma",
    "position": "Wakil Ketua",
    "parent_id": 1,
    "sort_order": 0,
    "photo": null
  }
]
```

**✅ Belum ada data — `200 OK`**

```json
[]
```

**❌ Belum login — `401 Unauthorized`**

```json
{ "error": "Tidak terautentikasi" }
```

```bash
curl -b cookie.txt http://127.0.0.1:8099/api/members
```

---

## POST `/api/members` 🔒

Menambah anggota baru.

**Body**

| Field        | Wajib | Tipe            | Keterangan                                  |
|--------------|:-----:|-----------------|-----------------------------------------------|
| `name`       |  ✅   | string          | Nama lengkap                                  |
| `position`   |  ✅   | string          | Jabatan                                       |
| `parent_id`  |       | integer \| null | ID atasan; kosong/`null` = di puncak           |
| `sort_order` |       | integer         | Default `0`                                   |
| `photo`      |       | string \| null  | URL foto/avatar (opsional)                    |

```json
{
  "name": "Rian Hidayat",
  "position": "Wakil Sekretaris",
  "parent_id": 3,
  "sort_order": 0,
  "photo": null
}
```

**✅ Sukses — `201 Created`**

```json
{ "ok": true, "id": 13 }
```

**❌ `name`/`position` kosong — `422 Unprocessable Entity`**

```json
{ "error": "Nama dan jabatan wajib diisi" }
```

**❌ Belum login — `401 Unauthorized`**

```json
{ "error": "Tidak terautentikasi" }
```

```bash
curl -b cookie.txt -X POST http://127.0.0.1:8099/api/members \
  -H "Content-Type: application/json" \
  -d '{"name":"Rian Hidayat","position":"Wakil Sekretaris","parent_id":3,"sort_order":0}'
```

> **Catatan:** `parent_id` yang mengarah ke ID tidak ada tidak divalidasi di
> level API (constraint foreign key di database yang akan menolaknya dan
> memicu respons `500`). Pastikan frontend hanya mengirim `parent_id` dari
> daftar anggota yang benar-benar ada.

---

## PUT `/api/members/{id}` 🔒

Mengubah data anggota. Body sama seperti `POST /api/members`
(field wajib tetap `name` & `position`).

```json
{
  "name": "Rian Hidayat",
  "position": "Koordinator Divisi IT",
  "parent_id": 1,
  "sort_order": 2,
  "photo": null
}
```

**✅ Sukses — `200 OK`**

```json
{ "ok": true }
```

**❌ `name`/`position` kosong — `422 Unprocessable Entity`**

```json
{ "error": "Nama dan jabatan wajib diisi" }
```

**❌ `parent_id` sama dengan `{id}` sendiri — `422 Unprocessable Entity`**

```json
{ "error": "Sebuah anggota tidak bisa menjadi atasan dirinya sendiri" }
```

**❌ Belum login — `401 Unauthorized`**

```json
{ "error": "Tidak terautentikasi" }
```

```bash
curl -b cookie.txt -X PUT http://127.0.0.1:8099/api/members/5 \
  -H "Content-Type: application/json" \
  -d '{"name":"Rian Hidayat","position":"Koordinator Divisi IT","parent_id":1,"sort_order":2}'
```

> **Catatan:** validasi hanya mengecek parent langsung diri sendiri,
> **bukan** siklus tidak langsung (mis. A → B → A). Hindari membuat
> hierarki melingkar dari sisi frontend.
>
> Meng-update `id` yang tidak ada tidak menghasilkan error — query
> `UPDATE` hanya mempengaruhi 0 baris dan API tetap membalas
> `{ "ok": true }`.

---

## DELETE `/api/members/{id}` 🔒

Menghapus anggota. Karena `parent_id` memakai `ON DELETE CASCADE`,
**seluruh bawahannya (langsung maupun tidak langsung) ikut terhapus.**

**✅ Sukses — `200 OK`**

```json
{ "ok": true }
```

**❌ Belum login — `401 Unauthorized`**

```json
{ "error": "Tidak terautentikasi" }
```

```bash
curl -b cookie.txt -X DELETE http://127.0.0.1:8099/api/members/5
```

> **Catatan:** menghapus `id` yang tidak ada juga membalas
> `{ "ok": true }` (bersifat idempoten) — tidak ada pengecekan
> keberadaan data sebelum `DELETE`.

---

## Endpoint tidak ditemukan

Path apa pun di luar daftar di atas (atau method yang salah untuk path yang
valid, mis. `GET /api/login`):

**❌ `404 Not Found`**

```json
{ "error": "Endpoint tidak ditemukan", "path": "/foobar" }
```

```bash
curl http://127.0.0.1:8099/api/foobar
```

---

## Kesalahan server tak terduga

Jika terjadi exception (mis. koneksi database gagal, query error):

**❌ `500 Internal Server Error`**

```json
{ "error": "Kesalahan server", "detail": "SQLSTATE[HY000] [2002] Connection refused" }
```

> `detail` berisi pesan exception PHP mentah — berguna saat development,
> tapi sebaiknya jangan diekspos ke pengguna akhir di UI produksi.
