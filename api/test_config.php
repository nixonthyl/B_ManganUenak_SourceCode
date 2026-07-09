<?php
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'config/jwt_helper.php';
require_once 'helpers/response.php';
require_once 'helpers/logger.php';

// Tes 1: koneksi DB
$db = getDB();
echo "DB OK: " . $db->server_info . "\n";

// Tes 2: generate token
$token = generateToken(1, 'test_user', 'user');
echo "Token: " . substr($token, 0, 30) . "...\n";

// Tes 3: verify token
$decoded = verifyToken($token);
echo "Decoded username: " . $decoded['username'] . "\n";

// Tes 4: logger
logAPI('GET', '/test', 200, 'test_user', 'Tes logger');
echo "Log ditulis ke: logs/api_" . date('Y-m-d') . ".log\n";