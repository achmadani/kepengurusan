<?php
/**
 * Front controller untuk deploy di Apache / Nginx.
 *
 * Di server sungguhan, DocumentRoot diarahkan ke folder public/ ini.
 * Semua request /api/* dialihkan ke file ini oleh .htaccess (Apache)
 * atau blok location (Nginx), lalu diteruskan ke API sebenarnya
 * yang berada DI LUAR web root (folder api/) demi keamanan.
 *
 * Untuk pengembangan lokal, router.php (PHP built-in server) yang dipakai.
 */
require __DIR__ . '/../api/index.php';
