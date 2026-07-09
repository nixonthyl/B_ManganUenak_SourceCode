<?php

/**
 * Kirim response JSON terstandarisasi dan hentikan eksekusi.
 *
 * @param int    $statusCode  HTTP status code (200, 201, 400, 401, 403, 404, 500, 503)
 * @param string $status      'success' atau 'error'
 * @param string $message     Pesan yang bisa dibaca manusia
 * @param mixed  $data        Data payload (opsional, untuk response sukses)
 */
function sendResponse(int $statusCode, string $status, string $message, mixed $data = null): void {
    // Set HTTP status code di header (Penting untuk Black-Box Testing Error 4xx/5xx)
    http_response_code($statusCode);
    
    // Pastikan browser/front-end membaca ini sebagai JSON
    header('Content-Type: application/json; charset=utf-8');

    // Struktur response selalu konsisten
    $body = [
        'status'  => $status,
        'message' => $message,
    ];

    // Field 'data' hanya muncul kalau ada isinya
    if ($data !== null) {
        $body['data'] = $data;
    }

    // Ubah array PHP menjadi string JSON
    // JSON_UNESCAPED_UNICODE mencegah karakter khusus berubah menjadi kode yang sulit dibaca
    echo json_encode($body, JSON_UNESCAPED_UNICODE);

    // Hentikan eksekusi — tidak ada kode di bawahnya yang boleh jalan setelah response dikirim
    exit;
}
?>