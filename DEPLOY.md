# Panduan Deploy — Kepengurusan

Folder proyek ini **adalah** web root. Tidak ada sub-folder `public/` yang
perlu diarahkan secara khusus — DocumentRoot server tinggal menunjuk ke
folder proyek ini langsung. Ini dipilih supaya deploy semudah mungkin di
berbagai jenis server, termasuk shared hosting yang DocumentRoot-nya
sudah tetap ke `public_html`.

Keamanan folder `api/` (berisi kredensial DB) tetap terjaga lewat
`api/.htaccess`, yang hanya mengizinkan `api/index.php` dieksekusi —
`db.php` dan `config.local.php` tidak bisa diakses langsung dari browser.

## Prasyarat server

- PHP 8.2+ dengan ekstensi `pdo_mysql`, `mbstring`, `json`
- MySQL 8 (atau MariaDB 10.4+)
- Apache dengan `mod_rewrite` **atau** Nginx + PHP-FPM

---

## 1. Konfigurasi database & login (semua metode deploy)

Jangan pakai kredensial default di server. Ada 2 cara override:

### Cara A — file `config.local.php` (paling mudah, cocok shared hosting)

```bash
cd api
cp config.local.php.example config.local.php
# lalu edit config.local.php: isi host/db/user/password + password admin
```

File ini otomatis dibaca `db.php` dan **tidak ikut ke git**, serta tidak
bisa diakses langsung lewat browser (diblokir `api/.htaccess`).

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

## 2. Shared hosting (cPanel / DirectAdmin) — cara paling mudah

Ini alasan utama struktur proyek dibuat flat:

1. **Buat database** lewat *MySQL Databases*: buat DB, user, password, lalu
   *Add User To Database* dengan *ALL PRIVILEGES*.
2. **Upload semua isi proyek** langsung ke `public_html` (atau
   `public_html/nama-domain` untuk addon domain) — tidak perlu mengatur
   ulang Document Root sama sekali.
3. Buat `api/config.local.php` (salin dari `.example`) berisi kredensial DB
   dari langkah 1 — biasanya host `localhost`, port `3306`.
4. Pastikan versi PHP di *Select PHP Version* = **8.2**, ekstensi `pdo_mysql`
   aktif, `mod_rewrite` aktif (default di hampir semua hosting cPanel).
5. Buka domain → login. Tabel dibuat otomatis.

> `.htaccess` di root dan di `api/` sudah disiapkan dan ikut ter-upload —
> tidak perlu konfigurasi tambahan di panel hosting.

---

## 3. VHost Apache lokal (`kepengurusan.test`)

```apache
<VirtualHost *:8080>
    ServerName kepengurusan.test
    DocumentRoot "/Users/yayan/Work/personal/organinisasi"

    <Directory "/Users/yayan/Work/personal/organinisasi">
        Options FollowSymLinks          # tanpa Indexes
        AllowOverride All               # agar .htaccess (rewrite) aktif
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:fcgi://127.0.0.1:9082"
    </FilesMatch>

    ErrorLog  "/opt/homebrew/var/log/httpd/kepengurusan-error.log"
    CustomLog "/opt/homebrew/var/log/httpd/kepengurusan-access.log" common
</VirtualHost>
```

DocumentRoot kini langsung ke root proyek — tidak ada lagi sub-folder
`public/` untuk diarahkan.

### Langkah mengaktifkan (Homebrew Apache di macOS)

1. Pastikan modul aktif di `httpd.conf`:
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

## 4. VPS produksi — Apache

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
    DocumentRoot /var/www/kepengurusan

    <Directory /var/www/kepengurusan>
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

## 5. VPS produksi — Nginx (alternatif)

`/etc/nginx/sites-available/kepengurusan`:

```nginx
server {
    listen 80;
    server_name kepengurusan.example.com;
    root /var/www/kepengurusan;
    index index.html;

    # API -> api/index.php (URI asli dipertahankan untuk routing internal)
    location /api/ {
        rewrite ^/api(/.*)?$ /api/index.php last;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # SPA fallback (halaman non-API)
    location / {
        try_files $uri $uri/ /index.html;
    }

    # db.php & config.local.php tidak boleh diakses langsung
    location ~ ^/api/(db\.php|config\.local\.php.*)$ { deny all; }

    # jangan sajikan file dev/dokumentasi & tersembunyi
    location ~ \.(md|sh|log|example)$ { deny all; }
    location ~ /\. { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/kepengurusan /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 6. Checklist keamanan sebelum go-live

- [ ] Ganti `app_pass` (login admin) — jangan `admin/admin`.
- [ ] Ganti kredensial DB, pakai user khusus (bukan `root`).
- [ ] `api/.htaccess` (Apache) atau blok `deny` (Nginx) aktif — pastikan
      `api/db.php` dan `api/config.local.php` mengembalikan 403 jika diakses
      langsung dari browser.
- [ ] `Options -Indexes` (tidak ada directory listing).
- [ ] Aktifkan HTTPS (Let's Encrypt).
- [ ] `config.local.php` tidak ikut git (sudah di `.gitignore`).
- [ ] Batasi izin file: folder 755, file 644, `config.local.php` 640.

---

## 7. Troubleshooting

| Gejala | Penyebab & solusi |
|--------|-------------------|
| CSS/JS 404 | Pastikan `.htaccess` ikut ter-upload (beberapa FTP client menyembunyikan file berawalan titik — aktifkan "show hidden files"). |
| `/api/*` balikan HTML/404 | `mod_rewrite` mati / `AllowOverride All` belum aktif di `<Directory>`. |
| Buka `/api/db.php` malah jalan | `api/.htaccess` tidak ter-upload atau `AllowOverride` tidak mengizinkan `Require`. |
| 500 saat buka | Cek log Apache/FPM; sering karena kredensial DB salah di `config.local.php`. |
| "Kesalahan server" JSON | Koneksi MySQL gagal (host/port/user/izin `CREATE TABLE`). |
| Login gagal terus | Session/cookie — pastikan diakses via 1 host yang sama (bukan campur IP & domain). |
