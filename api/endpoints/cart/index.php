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

// Semua operasi cart butuh login sebagai user
$user   = requireAuth(['user', 'admin']);
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ═══════════════════════════════════════════════════════
// GET — ambil isi keranjang milik user yang login
// ═══════════════════════════════════════════════════════
if ($method === 'GET') {
    $stmt = $db->prepare(
        "SELECT c.id_cart, c.id_menu, m.nama_menu, m.jenis, 
                m.harga_porsi, c.qty, 
                (m.harga_porsi * c.qty) AS subtotal 
         FROM cart c 
         JOIN menu m ON c.id_menu = m.id_menu 
         WHERE c.username = ? 
         ORDER BY c.created_at ASC"
    );
    $stmt->bind_param('s', $user['username']);
    $stmt->execute();
    
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $grandTotal = array_sum(array_column($items, 'subtotal'));
    $totalQty = array_sum(array_column($items, 'qty'));

    logAPI('GET', '/cart', 200, $user['username']);
    sendResponse(200, 'success', 'Data keranjang berhasil diambil', [
        'items'       => $items,
        'grand_total' => $grandTotal,
        'total_item'  => (int)$totalQty
    ]);
}

// ═══════════════════════════════════════════════════════
// POST — tambah item ke keranjang
// ═══════════════════════════════════════════════════════
if ($method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $id_menu = (int)($body['id_menu'] ?? 0);
    $qty     = (int)($body['qty']     ?? 1);

    // ── TC_API_07: field wajib ─────────────────────────────────────────────
    if ($id_menu <= 0) {
        sendResponse(400, 'error', 'id_menu wajib diisi');
    }

    // ── TC_API_02: BVA qty — batas bawah 1, batas atas 99 ─────────────────
    if ($qty < 1 || $qty > 99) {
        logAPI('POST', '/cart', 400, $user['username'], "qty=$qty di luar batas");
        sendResponse(400, 'error', 'Jumlah (qty) harus antara 1 dan 99');
    }

    // Cek apakah menu ada
    $cekMenu = $db->prepare("SELECT id_menu FROM menu WHERE id_menu = ?");
    $cekMenu->bind_param('i', $id_menu);
    $cekMenu->execute();
    if ($cekMenu->get_result()->num_rows === 0) {
        sendResponse(404, 'error', 'Menu tidak ditemukan');
    }

    // Jika menu sudah di keranjang -> update qty, jika belum -> insert
    // ON DUPLICATE KEY memanfaatkan UNIQUE KEY (username, id_menu) dari DDL
    $stmt = $db->prepare(
        "INSERT INTO cart (username, id_menu, qty) 
         VALUES (?, ?, ?) 
         ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)"
    );
    $stmt->bind_param('sii', $user['username'], $id_menu, $qty);

    if ($stmt->execute()) {
        logAPI('POST', '/cart', 201, $user['username']);
        sendResponse(201, 'success', 'Item berhasil ditambahkan ke keranjang');
    }
    sendResponse(500, 'error', 'Gagal menambahkan item');
}

// ═══════════════════════════════════════════════════════
// PUT — update qty item di keranjang
// ═══════════════════════════════════════════════════════
if ($method === 'PUT') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $id_cart = (int)($body['id_cart'] ?? 0);
    $qty     = (int)($body['qty']     ?? 0);

    if ($id_cart <= 0) {
        sendResponse(400, 'error', 'id_cart wajib diisi');
    }

    // ── TC_API_02: BVA qty ─────────────────────────────────────────────────
    if ($qty < 1 || $qty > 99) {
        logAPI('PUT', '/cart', 400, $user['username'], "qty=$qty tidak valid");
        sendResponse(400, 'error', 'Jumlah (qty) harus antara 1 dan 99');
    }

    // Pastikan item milik user yang login (jangan bisa ubah cart orang lain)
    $cek = $db->prepare(
        "SELECT id_cart FROM cart WHERE id_cart = ? AND username = ?"
    );
    $cek->bind_param('is', $id_cart, $user['username']);
    $cek->execute();
    if ($cek->get_result()->num_rows === 0) {
        sendResponse(404, 'error', 'Item tidak ditemukan di keranjang kamu');
    }

    $stmt = $db->prepare("UPDATE cart SET qty = ? WHERE id_cart = ?");
    $stmt->bind_param('ii', $qty, $id_cart);
    $stmt->execute();

    logAPI('PUT', '/cart', 200, $user['username']);
    sendResponse(200, 'success', 'Jumlah item berhasil diupdate');
}

// ═══════════════════════════════════════════════════════
// DELETE — hapus item dari keranjang
// ═══════════════════════════════════════════════════════
if ($method === 'DELETE') {
    $id_cart = (int)($_GET['id'] ?? 0);
    
    if ($id_cart <= 0) {
        sendResponse(400, 'error', 'Parameter id wajib ada');
    }

    $cek = $db->prepare(
        "SELECT id_cart FROM cart WHERE id_cart = ? AND username = ?"
    );
    $cek->bind_param('is', $id_cart, $user['username']);
    $cek->execute();
    if ($cek->get_result()->num_rows === 0) {
        sendResponse(404, 'error', 'Item tidak ditemukan');
    }

    $stmt = $db->prepare("DELETE FROM cart WHERE id_cart = ?");
    $stmt->bind_param('i', $id_cart);
    $stmt->execute();

    logAPI('DELETE', '/cart', 200, $user['username']);
    sendResponse(200, 'success', 'Item berhasil dihapus dari keranjang');
}

sendResponse(405, 'error', 'Method tidak diizinkan');
?>