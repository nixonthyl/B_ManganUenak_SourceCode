<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt_helper.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/logger.php';

header('Content-Type: application/json');

// Tangani preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); 
    exit;
}

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logAPI('', '/auth/login', 405);
    sendResponse(405, 'error', 'Method tidak diizinkan, gunakan POST');
}

// Baca body JSON
$body     = json_decode(file_get_contents('php://input'), true);
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');

// ── TC_API_07: Parameter wajib ──────────────────────────────────────────────
if ($username === '') {
    logAPI('POST', '/auth/login', 400, 'anonymous', 'Username kosong');
    sendResponse(400, 'error', 'Username wajib diisi');
}
if ($password === '') {
    logAPI('POST', '/auth/login', 400, 'anonymous', 'Password kosong');
    sendResponse(400, 'error', 'Password wajib diisi');
}

// ── TC_API_01: Validasi tipe data (username & password harus string) ─────────
if (!is_string($username) || !is_string($password)) {
    logAPI('POST', '/auth/login', 400, 'anonymous', 'Tipe data tidak valid');
    sendResponse(400, 'error', 'Username dan password harus berupa teks');
}

// ── TC_API_02: Batas ukuran payload ─────────────────────────────────────────
if (strlen($username) > 100 || strlen($password) > 255) {
    logAPI('POST', '/auth/login', 413, 'anonymous', 'Payload terlalu besar');
    sendResponse(413, 'error', 'Ukuran input melebihi batas maksimal');
}

// ── TC_API_08: Cegah SQL Injection — pakai prepared statement ────────────────
$db   = getDB();
$stmt = $db->prepare(
    "SELECT id_pelanggan, username, level FROM pelanggan 
     WHERE username = ? AND password = ? LIMIT 1"
);
$stmt->bind_param('ss', $username, $password);
$stmt->execute();
$result = $stmt->get_result();

// ── TC_API_03: Respon berdasarkan status login ───────────────────────────────
if ($result->num_rows === 0) {
    logAPI('POST', '/auth/login', 401, $username, 'Kredensial salah');
    sendResponse(401, 'error', 'Username atau password salah');
}

$user  = $result->fetch_assoc();
$token = generateToken((int)$user['id_pelanggan'], $user['username'], $user['level']);

logAPI('POST', '/auth/login', 200, $user['username']);
sendResponse(200, 'success', 'Login berhasil', [
    'token'      => $token,
    'username'   => $user['username'],
    'level'      => $user['level'],
    'expires_in' => JWT_EXPIRY
]);
?>