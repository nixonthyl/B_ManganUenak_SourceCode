<?php
// Kunci rahasia untuk enkripsi/dekripsi JWT
// WAJIB diganti dengan string acak panjang (minimal 32 karakter)
define('JWT_SECRET', 'Mded08845244445f0856addc986611af5285572180e0b65ac68189e88ee80df57');

// Berapa lama token JWT berlaku (dalam detik)
// 3600 = 1 jam. Setelah ini, user harus login ulang.
define('JWT_EXPIRY', 3600);

// Versi API — berguna untuk header & debugging
define('API_VERSION', 'v1');

// Batas waktu koneksi database (detik)
// Kalau DB tidak merespons dalam 10 detik -> error 503 (untuk TC_API_09)
define('TIMEOUT_SECONDS', 10);

// Jumlah maksimal percobaan ulang jika koneksi gagal (untuk TC_API_10)
define('MAX_RETRY', 3);

// Mode development: true = tampilkan error detail, false = sembunyikan di production
define('DEBUG_MODE', true);
?>