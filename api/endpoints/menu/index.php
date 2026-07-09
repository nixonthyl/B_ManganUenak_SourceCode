<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/logger.php';
require_once __DIR__ . '/../../middleware/auth_middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); 
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB(); // TC_API_09: jika DB mati, getDB() langsung kirim 503

// ═══════════════════════════════════════════════════════
// GET — ambil daftar menu (semua user login boleh akses)
// ═══════════════════════════════════════════════════════
if ($method === 'GET') {
    $user = requireAuth(['user', 'admin']); // TC_API_03: cek level
    
    $jenis  = $_GET['jenis'] ?? null;    // filter opsional: ?jenis=makanan
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20))); // TC_API_02: BVA limit
    $offset = ($page - 1) * $limit;

    if ($jenis !== null) {
        // ── TC_API_01: validasi nilai enum ─────────────────────────────────
        if (!in_array($jenis, ['makanan', 'minuman'], true)) {
            logAPI('GET', '/menu', 400, $user['username'], 'Jenis tidak valid');
            sendResponse(400, 'error', "Nilai 'jenis' harus 'makanan' atau 'minuman'");
        }
        $stmt = $db->prepare("SELECT * FROM menu WHERE jenis = ? LIMIT ? OFFSET ?");
        $stmt->bind_param('sii', $jenis, $limit, $offset);
    } else {
        $stmt = $db->prepare("SELECT * FROM menu LIMIT ? OFFSET ?");
        $stmt->bind_param('ii', $limit, $offset);
    }

    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Hitung total untuk pagination
    $totalRow   = $db->query("SELECT COUNT(*) AS total FROM menu" . ($jenis ? " WHERE jenis='$jenis'" : ""))->fetch_assoc();
    $totalPages = (int) ceil($totalRow['total'] / $limit);

    logAPI('GET', '/menu', 200, $user['username']);
    sendResponse(200, 'success', 'Data menu berhasil diambil', [
        'items'       => $data,
        'page'        => $page,
        'total_pages' => $totalPages,
        'total_items' => (int)$totalRow['total']
    ]);
}

// ═══════════════════════════════════════════════════════
// POST — tambah menu baru (admin only)
// ═══════════════════════════════════════════════════════
if ($method === 'POST') {
    $user = requireAuth(['admin']); // TC_API_05: hanya admin
    $body = json_decode(file_get_contents('php://input'), true);

    // ── TC_API_07: validasi field wajib ────────────────────────────────────
    foreach (['nama_menu', 'jenis', 'harga_porsi'] as $field) {
        if (empty($body[$field])) {
            logAPI('POST', '/menu', 400, $user['username'], "Field '$field' kosong");
            sendResponse(400, 'error', "Field '$field' wajib diisi");
        }
    }

    // ── TC_API_01: validasi tipe data ──────────────────────────────────────
    if (!is_numeric($body['harga_porsi']) || (int)$body['harga_porsi'] <= 0) {
        sendResponse(400, 'error', 'harga_porsi harus angka positif');
    }
    if (!in_array($body['jenis'], ['makanan', 'minuman'], true)) {
        sendResponse(400, 'error', "jenis harus 'makanan' atau 'minuman'");
    }

    // ── TC_API_02: BVA harga (batas bawah: 1000, batas atas: 10.000.000) ──
    $harga = (int)$body['harga_porsi'];
    if ($harga < 1000 || $harga > 10000000) {
        logAPI('POST', '/menu', 400, $user['username'], "Harga di luar batas: $harga");
        sendResponse(400, 'error', 'Harga harus antara Rp1.000 dan Rp10.000.000');
    }

    $nama  = trim($body['nama_menu']);
    $jenis = $body['jenis'];

    $stmt  = $db->prepare("INSERT INTO menu (nama_menu, jenis, harga_porsi) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $nama, $jenis, $harga);

    if ($stmt->execute()) {
        logAPI('POST', '/menu', 201, $user['username']);
        sendResponse(201, 'success', 'Menu berhasil ditambahkan', [
            'id_menu' => $db->insert_id
        ]);
    }
    logAPI('POST', '/menu', 500, $user['username'], 'Gagal insert');
    sendResponse(500, 'error', 'Gagal menambahkan menu');
}

// ═══════════════════════════════════════════════════════
// PUT — update menu (admin only) — TC_API_04: State Transition
// ═══════════════════════════════════════════════════════
if ($method === 'PUT') {
    $user = requireAuth(['admin']);
    $body = json_decode(file_get_contents('php://input'), true);
    
    $id_menu = (int)($body['id_menu'] ?? 0);
    if ($id_menu <= 0) {
        sendResponse(400, 'error', 'id_menu wajib diisi dan harus angka positif');
    }

    // Cek apakah menu yang akan diubah ada
    $cek = $db->prepare("SELECT id_menu FROM menu WHERE id_menu = ?");
    $cek->bind_param('i', $id_menu);
    $cek->execute();
    if ($cek->get_result()->num_rows === 0) {
        logAPI('PUT', '/menu', 404, $user['username'], "id_menu $id_menu tidak ditemukan");
        sendResponse(404, 'error', 'Menu tidak ditemukan');
    }

    // Bangun query dinamis — hanya update field yang dikirim
    $fields = [];
    $types  = '';
    $vals   = [];

    if (isset($body['nama_menu'])) {
        $fields[] = 'nama_menu = ?';
        $types   .= 's';
        $vals[]   = trim($body['nama_menu']);
    }
    if (isset($body['jenis'])) {
        if (!in_array($body['jenis'], ['makanan', 'minuman'], true)) {
            sendResponse(400, 'error', "jenis harus 'makanan' atau 'minuman'");
        }
        $fields[] = 'jenis = ?';
        $types   .= 's';        $vals[]   = $body['jenis'];
    }
    if (isset($body['harga_porsi'])) {
        $harga = (int)$body['harga_porsi'];
        if ($harga < 1000 || $harga > 10000000) {
            sendResponse(400, 'error', 'Harga harus antara Rp1.000 dan Rp10.000.000');
        }
        $fields[] = 'harga_porsi = ?';
        $types   .= 'i';
        $vals[]   = $harga;
    }

    if (empty($fields)) {
        sendResponse(400, 'error', 'Tidak ada field yang diupdate');
    }

    $types .= 'i';
    $vals[] = $id_menu;
    $sql    = "UPDATE menu SET " . implode(', ', $fields) . " WHERE id_menu = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();

    logAPI('PUT', '/menu', 200, $user['username']);
    sendResponse(200, 'success', 'Menu berhasil diupdate');
}

// ═══════════════════════════════════════════════════════
// DELETE — hapus menu (admin only)
// ═══════════════════════════════════════════════════════
if ($method === 'DELETE') {
    $user    = requireAuth(['admin']);
    $id_menu = (int)($_GET['id'] ?? 0);
    
    if ($id_menu <= 0) {
        sendResponse(400, 'error', 'Parameter id wajib ada');
    }

    $cek = $db->prepare("SELECT id_menu FROM menu WHERE id_menu = ?");
    $cek->bind_param('i', $id_menu);
    $cek->execute();
    if ($cek->get_result()->num_rows === 0) {
        logAPI('DELETE', '/menu', 404, $user['username'], "id $id_menu tidak ada");
        sendResponse(404, 'error', 'Menu tidak ditemukan');
    }

    $stmt = $db->prepare("DELETE FROM menu WHERE id_menu = ?");
    $stmt->bind_param('i', $id_menu);
    $stmt->execute();

    logAPI('DELETE', '/menu', 200, $user['username']);
    sendResponse(200, 'success', 'Menu berhasil dihapus');
}

sendResponse(405, 'error', 'Method tidak diizinkan');
?>