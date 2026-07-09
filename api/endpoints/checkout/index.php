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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'error', 'Method tidak diizinkan, gunakan POST');
}

// Hanya user biasa yang boleh melakukan checkout
$user = requireAuth(['user']);
$body = json_decode(file_get_contents('php://input'), true);
$db   = getDB();

// ── TC_API_07: semua field wajib harus ada ─────────────────────────────────
$required = ['id_metode'];
foreach ($required as $field) {
    if (empty($body[$field])) {
        logAPI('POST', '/checkout', 400, $user['username'], "Field '$field' kosong");
        sendResponse(400, 'error', "Field '$field' wajib diisi");
    }
}

// Ambil data user dari database (bukan dari token — untuk keamanan)
$stmtUser = $db->prepare(
    "SELECT nama_pelanggan, alamat, no_telp FROM pelanggan WHERE username = ?"
);
$stmtUser->bind_param('s', $user['username']);
$stmtUser->execute();
$dataUser = $stmtUser->get_result()->fetch_assoc();

if (!$dataUser) {
    sendResponse(404, 'error', 'Data user tidak ditemukan');
}

// Ambil isi keranjang dari database
$stmtCart = $db->prepare(
    "SELECT c.id_menu, m.nama_menu, m.harga_porsi, c.qty, 
            (m.harga_porsi * c.qty) AS subtotal 
     FROM cart c 
     JOIN menu m ON c.id_menu = m.id_menu 
     WHERE c.username = ?"
);
$stmtCart->bind_param('s', $user['username']);
$stmtCart->execute();
$cartItems = $stmtCart->get_result()->fetch_all(MYSQLI_ASSOC);

// ── TC_API_06: keranjang tidak boleh kosong ────────────────────────────────
if (empty($cartItems)) {
    logAPI('POST', '/checkout', 400, $user['username'], 'Keranjang kosong');
    sendResponse(400, 'error', 'Keranjang masih kosong, tambahkan menu terlebih dahulu');
}

// Hitung total dari DB — jangan percaya total dari client
$totalBayar = array_sum(array_column($cartItems, 'subtotal'));

// Validasi metode pembayaran
$id_metode = (int)$body['id_metode'];
$cekMetode = $db->prepare("SELECT id_metode FROM metode WHERE id_metode = ?");
$cekMetode->bind_param('i', $id_metode);
$cekMetode->execute();

if ($cekMetode->get_result()->num_rows === 0) {
    sendResponse(400, 'error', 'Metode pembayaran tidak valid');
}

// ── TC_API_06: Gunakan transaksi DB untuk atomicity ────────────────────────
// Jika insert pembayaran sukses tapi hapus cart gagal -> semua dibatalkan
$db->begin_transaction();
try {
    // Insert ke tabel pembayaran
    // Sesuai dengan kolom: id_pelanggan, id_metode, total_bayar, tanggal
    $stmtBayar = $db->prepare(
        "INSERT INTO pembayaran 
            (id_pelanggan, id_metode, total_bayar, tanggal) 
         VALUES (?, ?, ?, NOW())"
    );
    $stmtBayar->bind_param(
        'iii',
        $user['user_id'], // Mengambil ID user langsung dari Token JWT
        $id_metode,
        $totalBayar
    );
    $stmtBayar->execute();
    $id_pembayaran = $db->insert_id;

    // Kosongkan keranjang setelah checkout berhasil
    $stmtClear = $db->prepare("DELETE FROM cart WHERE username = ?");
    $stmtClear->bind_param('s', $user['username']);
    $stmtClear->execute();

    $db->commit();
    logAPI('POST', '/checkout', 201, $user['username']);
    sendResponse(201, 'success', 'Checkout berhasil', [
        'id_pembayaran' => $id_pembayaran,
        'total_bayar'   => $totalBayar,
        'items'         => $cartItems
    ]);

} catch (Exception $e) {
    // Batalkan semua operasi jika ada yang gagal
    $db->rollback();
    logAPI('POST', '/checkout', 500, $user['username'], $e->getMessage());
    sendResponse(500, 'error', 'Checkout gagal, tidak ada data yang disimpan');
}
?>