<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/logger.php';
require_once __DIR__ . '/../../middleware/auth_middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

// ── TC_API_05: endpoint ini HANYA untuk admin ──────────────────────────────
$user   = requireAuth(['admin']);
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ─── GET — daftar semua user ───────────────────────────────────────────────
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    if ($id !== null) {
        // GET detail satu user
        $stmt = $db->prepare(
            "SELECT id_pelanggan, nama_pelanggan, username, alamat, no_telp, level
             FROM pelanggan WHERE id_pelanggan = ?"
        );
        $stmt->bind_param('i', (int)$id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        if (!$data) {
            sendResponse(404, 'error', 'User tidak ditemukan');
        }
        logAPI('GET', '/users/' . $id, 200, $user['username']);
        sendResponse(200, 'success', 'Data user ditemukan', $data);
    }

    // GET semua user (password dikecualikan)
    $result = $db->query(
        "SELECT id_pelanggan, nama_pelanggan, username, alamat, no_telp, level
         FROM pelanggan ORDER BY id_pelanggan ASC"
    );
    $data = $result->fetch_all(MYSQLI_ASSOC);

    logAPI('GET', '/users', 200, $user['username']);
    sendResponse(200, 'success', 'Data user berhasil diambil', $data);
}

// ─── PUT — update data user ────────────────────────────────────────────────
if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($body['id_pelanggan'] ?? 0);

    if ($id <= 0) {
        sendResponse(400, 'error', 'id_pelanggan wajib diisi');
    }

    $cek = $db->prepare("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan = ?");
    $cek->bind_param('i', $id);
    $cek->execute();
    if ($cek->get_result()->num_rows === 0) {
        sendResponse(404, 'error', 'User tidak ditemukan');
    }

    // Bangun query dinamis
    $fields = [];
    $types  = '';
    $vals   = [];
    $allowed = ['nama_pelanggan', 'alamat', 'no_telp', 'level'];

    foreach ($allowed as $f) {
        if (isset($body[$f])) {
            $fields[] = "$f = ?";
            $types   .= 's';
            $vals[]   = $body[$f];
        }
    }
    // Update password jika dikirim
    if (!empty($body['password'])) {
        $fields[] = "password = ?";
        $types   .= 's';
        $vals[]   = $body['password'];
    }

    if (empty($fields)) {
        sendResponse(400, 'error', 'Tidak ada field yang diupdate');
    }

    $types .= 'i';
    $vals[] = $id;
    $stmt   = $db->prepare("UPDATE pelanggan SET " . implode(', ', $fields) . " WHERE id_pelanggan = ?");
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();

    logAPI('PUT', '/users', 200, $user['username']);
    sendResponse(200, 'success', 'Data user berhasil diupdate');
}

// ─── DELETE — hapus user ───────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        sendResponse(400, 'error', 'Parameter id wajib ada');
    }

    // Jangan hapus diri sendiri
    if ($id === (int)$user['user_id']) {
        sendResponse(400, 'error', 'Tidak bisa menghapus akun sendiri');
    }

    $cek = $db->prepare("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan = ?");
    $cek->bind_param('i', $id);
    $cek->execute();
    if ($cek->get_result()->num_rows === 0) {
        sendResponse(404, 'error', 'User tidak ditemukan');
    }

    $stmt = $db->prepare("DELETE FROM pelanggan WHERE id_pelanggan = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    logAPI('DELETE', '/users', 200, $user['username']);
    sendResponse(200, 'success', 'User berhasil dihapus');
}

sendResponse(405, 'error', 'Method tidak diizinkan');