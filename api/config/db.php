<?php
// Wajib memanggil config.php agar konstanta TIMEOUT_SECONDS & DEBUG_MODE terbaca
require_once __DIR__ . '/config.php';

function getDB(): mysqli {
    // Variabel static: nilainya diingat antara pemanggilan fungsi
    // Sehingga koneksi hanya dibuat SEKALI per request HTTP
    static $conn = null;

    if ($conn === null) {
        // Buat koneksi baru 
        // Catatan: Pastikan port 3308 dan db "resto" sudah sesuai dengan XAMPP-mu
        $conn = new mysqli("localhost:3308", "root", "", "resto");

        // Set timeout koneksi (untuk TC_API_09 dan TC_API_10)
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, TIMEOUT_SECONDS);

        // Cek apakah koneksi berhasil
        if ($conn->connect_error) {
            // Kembalikan error sebagai JSON, bukan halaman HTML error default PHP
            http_response_code(503); // 503 = Service Unavailable
            header('Content-Type: application/json');
            
            echo json_encode([
                'status'  => 'error',
                'message' => 'Database tidak tersedia, coba lagi nanti',
                // Hanya tampilkan detail error di mode development
                'detail'  => defined('DEBUG_MODE') && DEBUG_MODE 
                             ? $conn->connect_error 
                             : null
            ]);
            exit;
        }

        // Paksa encoding UTF-8 agar karakter bahasa Indonesia aman
        $conn->set_charset("utf8mb4");
    }

    return $conn;
}
?>