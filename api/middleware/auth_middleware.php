<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/jwt_helper.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/logger.php';

/**
 * Pastikan request memiliki token JWT yang valid.
 * Jika tidak valid, kirim response error dan hentikan eksekusi.
 *
 * @param  array  $allowedLevels  Level user yang boleh akses: ['user'], ['admin'], atau ['user', 'admin']
 * @return array                  Data user dari token (user_id, username, level)
 */
function requireAuth(array $allowedLevels = ['user', 'admin']): array {
    // Ambil semua header dari request
    $headers = getallheaders();

    // Token dikirim di header: Authorization: Bearer eyJ...
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    // Validasi: header Authorization harus ada dan diawali "Bearer "
    if (!str_starts_with($authHeader, 'Bearer ')) {
        logAPI($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], 401, 'anonymous', 'Tidak ada token');
        sendResponse(401, 'error', 'Akses ditolak: token tidak ditemukan di header Authorization');
    }

    // Ambil token-nya saja (hapus "Bearer " di depan)
    $token   = substr($authHeader, 7);
    $decoded = verifyToken($token);

    // Validasi: token harus bisa didekripsi
    if ($decoded === null) {
        logAPI($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], 401, 'anonymous', 'Token invalid/expired');
        sendResponse(401, 'error', 'Token tidak valid atau sudah kadaluarsa, silakan login ulang');
    }

    // Validasi: level user harus termasuk yang diizinkan
    if (!in_array($decoded['level'], $allowedLevels, true)) {
        logAPI($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], 403, $decoded['username'], 'Level tidak cukup');
        sendResponse(403, 'error', 'Akses ditolak: hak akses tidak mencukupi');
    }

    // Semua valid — kembalikan data user untuk dipakai di endpoint
    return $decoded;
}
?>