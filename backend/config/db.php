<?php
// ═══════════════════════════════════════════════
//  config/db.php — Database Connection
//  EDIT these 4 values to match your server
// ═══════════════════════════════════════════════

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // your MySQL username
define('DB_PASS', '');            // your MySQL password (blank for XAMPP default)
define('DB_NAME', 'smart_semester');

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
