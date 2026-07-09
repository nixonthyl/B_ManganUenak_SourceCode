<?php

/**
 * Catat satu baris log aktivitas API ke file harian.
 *
 * @param string $method      HTTP method: GET, POST, PUT, DELETE
 * @param string $endpoint    Path endpoint: /auth/login, /menu, dll.
 * @param int    $statusCode  HTTP status code yang dikirim ke client
 * @param string $userInfo    Username yang melakukan request (opsional)
 * @param string $note        Catatan tambahan (opsional, misal: pesan error)
 */
function logAPI(
    string $method,
    string $endpoint,
    int    $statusCode,
    string $userInfo = 'anonymous',
    string $note = ''
): void {
    // Folder logs/ berada dua level di atas file ini (api/helpers/ -> api/ -> Resto1/)
    $logDir = __DIR__ . '/../../logs/';

    // Buat folder logs/ jika ternyata belum ada
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Satu file log per hari: logs/api_YYYY-MM-DD.log
    $logFile = $logDir . 'api_' . date('Y-m-d') . '.log';

    // Format baris log agar rapi dan sejajar
    $entry = sprintf(
        "[%s] %-6s %-25s | %d | user:%-15s | ip:%s%s\n",
        date('Y-m-d H:i:s'),
        $method,
        $endpoint,
        $statusCode,
        $userInfo,
        $_SERVER['REMOTE_ADDR'] ?? '-',
        $note ? ' | ' . $note : ''
    );

    // FILE_APPEND: tambahkan di akhir file, jangan timpa file yang sudah ada
    // LOCK_EX: kunci file saat menulis agar tidak bentrok jika ada request masuk bersamaan
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
?>