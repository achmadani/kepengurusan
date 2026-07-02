<?php
/**
 * Database connection + auto schema/seed.
 * MySQL 8 on port 3308, user root, password toor.
 */

/*
 * Configuration precedence (highest first):
 *   1. api/config.local.php   (gitignored — easiest for shared hosting)
 *   2. Environment variables  (ideal for VPS / Docker / CI)
 *   3. Local dev defaults     (this laptop: php82 -S ... on MySQL :3308)
 */
$__cfg = is_file(__DIR__ . '/config.local.php')
    ? (array) require __DIR__ . '/config.local.php'
    : [];

$__conf = static function (string $key, string $env, string $default) use ($__cfg): string {
    if (array_key_exists($key, $__cfg) && $__cfg[$key] !== null) {
        return (string) $__cfg[$key];
    }
    $v = getenv($env);
    return $v !== false ? $v : $default;
};

define('DB_HOST', $__conf('db_host', 'DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) $__conf('db_port', 'DB_PORT', '3308'));
define('DB_NAME', $__conf('db_name', 'DB_NAME', 'organinisasi'));
define('DB_USER', $__conf('db_user', 'DB_USER', 'root'));
define('DB_PASS', $__conf('db_pass', 'DB_PASS', 'toor'));

// App login credentials — OVERRIDE THESE IN PRODUCTION!
define('APP_USER', $__conf('app_user', 'APP_USER', 'admin'));
define('APP_PASS', $__conf('app_pass', 'APP_PASS', 'admin'));

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    init_schema($pdo);

    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS members (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(150) NOT NULL,
            position    VARCHAR(150) NOT NULL,
            parent_id   INT NULL,
            sort_order  INT NOT NULL DEFAULT 0,
            photo       VARCHAR(500) NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_parent FOREIGN KEY (parent_id)
                REFERENCES members(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            skey   VARCHAR(50) PRIMARY KEY,
            svalue VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Seed the organization title once.
    $pdo->exec("
        INSERT IGNORE INTO settings (skey, svalue)
        VALUES ('org_name', 'Struktur Organisasi Kepengurusan')
    ");

    // Seed a sample structure only if the table is empty.
    $count = (int) $pdo->query('SELECT COUNT(*) AS c FROM members')->fetch()['c'];
    if ($count === 0) {
        seed_sample($pdo);
    }
}

function seed_sample(PDO $pdo): void
{
    $insert = $pdo->prepare(
        'INSERT INTO members (name, position, parent_id, sort_order) VALUES (?, ?, ?, ?)'
    );

    $insert->execute(['Ahmad Dani', 'Ketua Umum', null, 0]);
    $ketua = (int) $pdo->lastInsertId();

    $insert->execute(['Siti Rahma', 'Wakil Ketua', $ketua, 0]);

    $insert->execute(['Budi Santoso', 'Sekretaris', $ketua, 1]);
    $sekretaris = (int) $pdo->lastInsertId();

    $insert->execute(['Dewi Lestari', 'Bendahara', $ketua, 2]);
    $bendahara = (int) $pdo->lastInsertId();

    $insert->execute(['Rian Hidayat', 'Wakil Sekretaris', $sekretaris, 0]);
    $insert->execute(['Maya Putri', 'Wakil Bendahara', $bendahara, 0]);

    $insert->execute(['Eko Prasetyo', 'Koordinator Divisi Humas', $ketua, 3]);
    $humas = (int) $pdo->lastInsertId();
    $insert->execute(['Nadia Sari', 'Anggota Humas', $humas, 0]);
    $insert->execute(['Fajar Nugroho', 'Anggota Humas', $humas, 1]);

    $insert->execute(['Lina Marlina', 'Koordinator Divisi Acara', $ketua, 4]);
    $acara = (int) $pdo->lastInsertId();
    $insert->execute(['Yoga Pratama', 'Anggota Acara', $acara, 0]);
    $insert->execute(['Rina Wati', 'Anggota Acara', $acara, 1]);
}
