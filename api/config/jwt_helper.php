<?php
// Muat library Firebase JWT dari Composer
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Buat token JWT baru setelah login berhasil.
 * Token berisi identitas user dan waktu kadaluarsa.
 */
function generateToken(int $userId, string $username, string $level): string {
    $payload = [
        'iss'      => 'resto-api',       // Issuer: siapa yang membuat token
        'iat'      => time(),            // Issued At: waktu token dibuat (Unix timestamp)
        'exp'      => time() + JWT_EXPIRY, // Expiry: waktu token kadaluarsa
        'user_id'  => $userId,
        'username' => $username,
        'level'    => $level             // 'admin' atau 'user'
    ];

    // Enkripsi payload dengan algoritma HS256 + secret key
    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

/**
 * Validasi token JWT dari header Authorization.
 * Mengembalikan array data user jika valid, atau null jika tidak valid/kadaluarsa.
 */
function verifyToken(string $token): ?array {
    try {
        // Dekripsi dan validasi token
        // Library otomatis cek: signature, exp (kadaluarsa), iat
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        
        // Kembalikan data payload sebagai array
        return (array) $decoded;
        
    } catch (\Firebase\JWT\ExpiredException $e) {
        // Token sudah lewat waktu kadaluarsanya
        return null;
        
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        // Token dipalsukan / dimodifikasi
        return null;
        
    } catch (Exception $e) {
        // Error lainnya (format salah, dll.)
        return null;
    }
}
?>