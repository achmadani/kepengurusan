# Panduan Deploy — Kepengurusan

Aplikasi ini terdiri dari:

- **`public/`** → web root (yang boleh diakses browser: `index.html`, `style.css`, `app.js`, `.htaccess`, `api.php`)
- **`api/`** → logika API + koneksi database (**di luar** web root, tidak boleh diakses langsung)
- **`router.php`** → hanya untuk pengembangan lokal (`php -S`), **tidak dipakai** di Apache/Nginx

> Prinsip penting: di server sungguhan, **DocumentRoot diarahkan ke `public/`**,
> bukan ke root proyek. Request `/api/*` dialihkan ke `public/api.php`
> yang meneruskan ke `api/index.php`.

## Prasyarat server

- PHP 8.2+ dengan ekstensi `pdo_mysql`, `mbstring`, `json`
- MySQL 8 (atau MariaDB 10.4+)
- Apache (mod_rewrite + mod_proxy_fcgi) **atau** Nginx + PHP-FPM

---

## 1. Konfigurasi database & login (semua metode deploy)

Jangan pakai kredensial default di server. Ada 2 cara override:

### Cara A — file `config.local.php` (paling mudah, cocok shared hosting)

```bash
cd api
cp config.local.php.example config.local.php
# lalu edit config.local.php: isi host/db/user/password + password admin
```

File ini otomatis dibaca `db.php` dan **tidak ikut ke git**.

### Cara B — environment variables (cocok VPS / Docker)

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=kepengurusan
DB_USER=kepengurusan_user
DB_PASS=rahasia-kuat
APP_USER=admin
APP_PASS=rahasia-kuat
```

Urutan prioritas: `config.local.php` → environment → default dev.

Skema tabel & data contoh dibuat **otomatis** saat aplikasi pertama diakses
(butuh user DB yang punya izin `CREATE TABLE`). Buat database kosong dulu:

```sql
CREATE DATABASE kepengurusan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kepengurusan_user'@'localhost' IDENTIFIED BY 'rahasia-kuat';
GRANT ALL PRIVILEGES ON kepengurusan.* TO 'kepengurusan_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## 2. VHost Apache lokal (`kepengurusan.test`)

Ini versi **yang sudah diperbaiki** dari config-mu (perhatikan `DocumentRoot`
menunjuk ke `public/` dan `Indexes` dihapus):

```apache
<VirtualHost *:8080>
    ServerName kepengurusan.test

    # PENTING: arahkan ke folder public/, bukan root proyek
    DocumentRoot "/Users/yayan/Work/personal/organinisasi/public"

    <Directory "/Users/yayan/Work/personal/organinisasi/public">
        Options FollowSymLinks          # tanpa Indexes
        AllowOverride All               # agar .htaccess aktif (rewrite)
        Require all granted
    </Directory>

    # Kirim file .php ke PHP-FPM 8.2 (port sesuai punyamu)
    <FilesMatch \.php$>
        SetHandler "proxy:fcgi://127.0.0.1:9082"
    </FilesMatch>

    ErrorLog  "/opt/homebrew/var/log/httpd/kepengurusan-error.log"
    CustomLog "/opt/homebrew/var/log/httpd/kepengurusan-access.log" common
</VirtualHost>
```

Perubahan dari config-mu:
| Semula | Diperbaiki | Alasan |
|--------|-----------|--------|
| `DocumentRoot .../organinisasi` | `.../organinisasi/public` | jangan ekspos `.git`, `api/`, `db.php` |
| `Options Indexes FollowSymLinks` | `Options FollowSymLinks` | matikan listing folder |
| (tanpa .htaccess) | butuh `.htaccess` di `public/` | routing `/api` + SPA fallback |

### Langkah mengaktifkan (Homebrew Apache di macOS)

1. Pastikan modul aktif di `httpd.conf` (uncomment baris ini):
   ```apache
   LoadModule rewrite_module lib/httpd/modules/mod_rewrite.so
   LoadModule proxy_module lib/httpd/modules/mod_proxy.so
   LoadModule proxy_fcgi_module lib/httpd/modules/mod_proxy_fcgi.so
   ```
2. Include vhost di `httpd.conf` (mis. `Include /opt/homebrew/etc/httpd/extra/kepengurusan.conf`).
3. Tambah ke `/etc/hosts`:
   ```
   127.0.0.1   kepengurusan.test
   ```
4. Pastikan PHP-FPM 8.2 jalan di `127.0.0.1:9082`:
   ```bash
   brew services start php@8.2      # cek: lsof -iTCP:9082 -sTCP:LISTEN
   ```
5. Restart Apache & buka http://kepengurusan.test:8080
   ```bash
   brew services restart httpd
   ```

---

## 3. VPS produksi — Apache

```bash
# 1. Install (Ubuntu/Debian)
sudo apt update
sudo apt install -y apache2 php8.2-fpm php8.2-mysql mysql-server
sudo a2enmod rewrite proxy proxy_fcgi setenvif
sudo a2enconf php8.2-fpm

# 2. Ambil kode
sudo git clone git@github.com:achmadani/kepengurusan.git /var/www/kepengurusan
cd /var/www/kepengurusan/api && sudo cp config.local.php.example config.local.php
sudo nano config.local.php          # isi kredensial produksi
sudo chown -R www-data:www-data /var/www/kepengurusan
```

VHost `/etc/apache2/sites-available/kepengurusan.conf`:

```apache
<VirtualHost *:80>
    ServerName kepengurusan.example.com
    DocumentRoot /var/www/kepengurusan/public

    <Directory /var/www/kepengurusan/public>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
```

```bash
sudo a2ensite kepengurusan && sudo systemctl reload apache2
# HTTPS gratis:
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d kepengurusan.example.com
```

---

## 4. VPS produksi — Nginx (alternatif)

`/etc/nginx/sites-available/kepengurusan`:

```nginx
server {
    listen 80;
    server_name kepengurusan.example.com;
    root /var/www/kepengurusan/public;
    index index.html;

    # API -> front controller
    location /api/ {
        try_files $uri /api.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }

    # jangan sajikan file tersembunyi
    location ~ /\. { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/kepengurusan /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 5. Shared hosting (cPanel / DirectAdmin)

1. **Buat database** lewat *MySQL Databases*: buat DB, user, password, lalu
   *Add User To Database* dengan *ALL PRIVILEGES*.
2. **Upload** isi proyek ke luar `public_html` (mis. `~/kepengurusan`), lalu:
   - Arahkan Document Root domain/subdomain ke `~/kepengurusan/public`
     (menu *Domains* → *Document Root*), **atau**
   - Jika tak bisa ubah Document Root: pindahkan isi `public/` ke `public_html/`,
     taruh folder `api/` di atas `public_html`, dan sesuaikan `require` di
     `public_html/api.php` menjadi `require '../api/index.php';`.
3. Buat `api/config.local.php` (salin dari `.example`) berisi kredensial DB dari
   langkah 1 — biasanya host `localhost`, port `3306`.
4. Pastikan versi PHP di *Select PHP Version* = **8.2**, ekstensi `pdo_mysql` aktif.
5. Buka domain → login. Tabel dibuat otomatis.

> Catatan: `.htaccess` sudah disiapkan di `public/` untuk hosting berbasis Apache
> (mayoritas shared hosting). Tidak perlu `router.php` di produksi.

---

## 6. Checklist keamanan sebelum go-live

- [ ] Ganti `app_pass` (login admin) — jangan `admin/admin`.
- [ ] Ganti kredensial DB, pakai user khusus (bukan `root`).
- [ ] DocumentRoot = `public/` (folder `api/`, `.git/`, `db.php` tak terekspos).
- [ ] `Options -Indexes` (tidak ada directory listing).
- [ ] Aktifkan HTTPS (Let's Encrypt).
- [ ] `config.local.php` tidak ikut git (sudah di `.gitignore`).
- [ ] Batasi izin file: folder 755, file 644, `config.local.php` 640.

---

## 7. Troubleshooting

| Gejala | Penyebab & solusi |
|--------|-------------------|
| CSS/JS 404 | DocumentRoot belum ke `public/`. |
| `/api/*` balikan HTML/404 | `mod_rewrite` mati / `AllowOverride All` belum aktif. |
| 500 saat buka | Cek log Apache/FPM; sering karena kredensial DB salah di `config.local.php`. |
| "Kesalahan server" JSON | Koneksi MySQL gagal (host/port/user/izin `CREATE TABLE`). |
| Login gagal terus | Session/cookie — pastikan diakses via 1 host yang sama (bukan campur IP & domain). |
