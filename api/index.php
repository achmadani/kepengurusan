<?php
/**
 * REST API for the organization-structure app.
 * All routes are prefixed with /api by the front router (router.php).
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/db.php';

// --- helpers ---------------------------------------------------------------

function body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_auth(): void
{
    if (empty($_SESSION['auth'])) {
        json(['error' => 'Tidak terautentikasi'], 401);
    }
}

// --- routing ---------------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$path   = preg_replace('#^/api#', '', rtrim($path, '/'));
if ($path === '') {
    $path = '/';
}

try {
    // Auth ------------------------------------------------------------------
    if ($path === '/login' && $method === 'POST') {
        $b = body();
        $user = trim((string) ($b['username'] ?? ''));
        $pass = (string) ($b['password'] ?? '');
        if ($user === APP_USER && $pass === APP_PASS) {
            $_SESSION['auth'] = true;
            $_SESSION['user'] = $user;
            json(['ok' => true, 'user' => $user]);
        }
        json(['error' => 'Username atau password salah'], 401);
    }

    if ($path === '/logout' && $method === 'POST') {
        $_SESSION = [];
        session_destroy();
        json(['ok' => true]);
    }

    if ($path === '/session' && $method === 'GET') {
        json([
            'authenticated' => !empty($_SESSION['auth']),
            'user'          => $_SESSION['user'] ?? null,
        ]);
    }

    // Settings --------------------------------------------------------------
    if ($path === '/settings' && $method === 'GET') {
        require_auth();
        $rows = db()->query('SELECT skey, svalue FROM settings')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['skey']] = $r['svalue'];
        }
        json($out);
    }

    if ($path === '/settings' && $method === 'PUT') {
        require_auth();
        $b = body();
        $name = trim((string) ($b['org_name'] ?? ''));
        if ($name === '') {
            json(['error' => 'Nama organisasi wajib diisi'], 422);
        }
        $stmt = db()->prepare(
            'INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
        );
        $stmt->execute(['org_name', $name]);
        json(['ok' => true, 'org_name' => $name]);
    }

    // Members ---------------------------------------------------------------
    if ($path === '/members' && $method === 'GET') {
        require_auth();
        $rows = db()->query(
            'SELECT id, name, position, parent_id, sort_order, photo
             FROM members
             ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['id']        = (int) $r['id'];
            $r['parent_id'] = $r['parent_id'] !== null ? (int) $r['parent_id'] : null;
            $r['sort_order'] = (int) $r['sort_order'];
        }
        json($rows);
    }

    if ($path === '/members' && $method === 'POST') {
        require_auth();
        $b = body();
        $name     = trim((string) ($b['name'] ?? ''));
        $position = trim((string) ($b['position'] ?? ''));
        $parentId = $b['parent_id'] ?? null;
        $parentId = ($parentId === '' || $parentId === null) ? null : (int) $parentId;
        $sort     = (int) ($b['sort_order'] ?? 0);
        $photo    = trim((string) ($b['photo'] ?? '')) ?: null;

        if ($name === '' || $position === '') {
            json(['error' => 'Nama dan jabatan wajib diisi'], 422);
        }

        $stmt = db()->prepare(
            'INSERT INTO members (name, position, parent_id, sort_order, photo)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $position, $parentId, $sort, $photo]);
        json(['ok' => true, 'id' => (int) db()->lastInsertId()], 201);
    }

    if (preg_match('#^/members/(\d+)$#', $path, $m) && $method === 'PUT') {
        require_auth();
        $id = (int) $m[1];
        $b  = body();
        $name     = trim((string) ($b['name'] ?? ''));
        $position = trim((string) ($b['position'] ?? ''));
        $parentId = $b['parent_id'] ?? null;
        $parentId = ($parentId === '' || $parentId === null) ? null : (int) $parentId;
        $sort     = (int) ($b['sort_order'] ?? 0);
        $photo    = trim((string) ($b['photo'] ?? '')) ?: null;

        if ($name === '' || $position === '') {
            json(['error' => 'Nama dan jabatan wajib diisi'], 422);
        }
        if ($parentId === $id) {
            json(['error' => 'Sebuah anggota tidak bisa menjadi atasan dirinya sendiri'], 422);
        }

        $stmt = db()->prepare(
            'UPDATE members
             SET name = ?, position = ?, parent_id = ?, sort_order = ?, photo = ?
             WHERE id = ?'
        );
        $stmt->execute([$name, $position, $parentId, $sort, $photo, $id]);
        json(['ok' => true]);
    }

    if (preg_match('#^/members/(\d+)$#', $path, $m) && $method === 'DELETE') {
        require_auth();
        $id = (int) $m[1];
        // ON DELETE CASCADE removes the whole sub-tree.
        $stmt = db()->prepare('DELETE FROM members WHERE id = ?');
        $stmt->execute([$id]);
        json(['ok' => true]);
    }

    json(['error' => 'Endpoint tidak ditemukan', 'path' => $path], 404);
} catch (Throwable $e) {
    json(['error' => 'Kesalahan server', 'detail' => $e->getMessage()], 500);
}
