<?php
header('Content-Type: application/json');

// Include helpers
require_once 'helpers/response.php';
require_once 'helpers/logger.php';

// Ambil URL yang di-request oleh Postman
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// --- PENGATURAN ROUTING ---
// Sesuaikan base_path dengan nama folder di XAMPP kamu
$base_path = '/uts-testing-main/kelompok_gamasuk/Resto1/api';

// Bersihkan path agar tersisa ujungnya saja (misal: 'auth/login' atau 'cart')
$endpoint = str_replace($base_path, '', $request_uri);
$endpoint = trim($endpoint, '/');

// Daftar Rute API (Polisi Lalu Lintas)
if ($endpoint == '' || $endpoint == 'index.php') {
    // Kalau cuma panggil /api/ saja
    sendResponse(200, 'success', 'Resto API v1 aktif');

} elseif ($endpoint == 'auth/login') {
    // Arahkan ke file login.php
    require 'endpoints/auth/login.php';

} elseif ($endpoint == 'menu') {
    require 'endpoints/menu/index.php';

} elseif ($endpoint == 'cart') {
    require 'endpoints/cart/index.php';

} elseif ($endpoint == 'checkout') {
    require 'endpoints/checkout/index.php';

} else {
    // Menangani Error 404 (Endpoint tidak ada) menggunakan fungsi logAPI yang baru
    logAPI($method, '/' . $endpoint, 404, 'anonymous', 'Endpoint tidak ditemukan');
    sendResponse(404, 'error', 'Endpoint tidak ditemukan');
}
?>