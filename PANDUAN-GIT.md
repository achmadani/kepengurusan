# Panduan Kirim ke Git (GitHub)

Repo: **`git@github.com:achmadani/kepengurusan.git`**

Panduan ini menjelaskan cara mengirim (push) kode ke GitHub — untuk pertama kali
maupun untuk perubahan sehari-hari.

---

## 0. Syarat (satu kali saja)

Pastikan SSH ke GitHub sudah aktif:

```bash
ssh -T git@github.com
# Balasan sukses: "Hi achmadani! You've successfully authenticated..."
```

Kalau gagal, buat SSH key lalu daftarkan ke GitHub:

```bash
ssh-keygen -t ed25519 -C "achmadani@gmail.com"     # Enter sampai selesai
cat ~/.ssh/id_ed25519.pub                           # salin isinya
# Tempel ke: GitHub → Settings → SSH and GPG keys → New SSH key
```

---

## 1. Push pertama kali (setup awal)

Dijalankan sekali di folder proyek. **Langkah ini sudah dikerjakan** untuk repo
ini, jadi hanya sebagai referensi bila memulai dari nol:

```bash
cd /Users/yayan/Work/personal/organinisasi

git init                                             # jika belum ada .git
git remote add origin git@github.com:achmadani/kepengurusan.git

git add .
git commit -m "Initial commit: generator struktur organisasi"

git branch -M main                                   # pakai nama branch 'main'
git push -u origin main
```

`-u` membuat branch lokal `main` terhubung ke `origin/main`, sehingga cukup
`git push` saja untuk push berikutnya.

---

## 2. Workflow sehari-hari (setiap ada perubahan)

```bash
git status                     # lihat file yang berubah
git add .                      # tandai semua perubahan (atau: git add <file>)
git commit -m "Pesan perubahan yang jelas"
git push                       # kirim ke GitHub
```

Contoh pesan commit yang baik:

```bash
git commit -m "Tambah fitur cetak struktur ke PDF"
git commit -m "Perbaiki bug parent tidak tersimpan"
```

---

## 3. Ambil perubahan terbaru dari GitHub

Kalau bekerja di komputer lain / ada perubahan di remote:

```bash
git pull                       # tarik perubahan terbaru dari origin/main
```

---

## 4. Clone di komputer lain

```bash
git clone git@github.com:achmadani/kepengurusan.git
cd kepengurusan

# lalu jalankan aplikasinya (lihat README.md):
./start.sh                     # http://127.0.0.1:8099  (login: admin / admin)
```

> Catatan: pastikan MySQL 8 aktif di port `3308` (user `root`, password `toor`)
> dan PHP 8.2 tersedia. Skema tabel dibuat otomatis saat pertama dijalankan.

---

## 5. Perintah yang sering dipakai

| Perintah | Fungsi |
|----------|--------|
| `git status` | Lihat status perubahan |
| `git add .` | Stage semua perubahan |
| `git commit -m "..."` | Simpan perubahan + pesan |
| `git push` | Kirim commit ke GitHub |
| `git pull` | Ambil perubahan dari GitHub |
| `git log --oneline` | Lihat riwayat commit ringkas |
| `git diff` | Lihat detail perubahan yang belum di-commit |

---

## 6. Yang tidak ikut dikirim

File di `.gitignore` tidak akan di-push, antara lain:

- `.DS_Store` (file sistem macOS)
- `.claude/settings.local.json` (izin lokal Claude Code, khusus mesin ini)
- `*.log`
