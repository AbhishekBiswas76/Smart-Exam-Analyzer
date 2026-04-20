<?php
// ═══════════════════════════════════════════════
//  config/helpers.php — Shared Utility Functions
// ═══════════════════════════════════════════════

// Allow requests from your frontend (adjust origin in production)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit();
}

session_start();

// ── Send JSON response ──
function respond($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ]);
    exit();
}

// ── Get current logged-in user from session ──
function requireAuth($role = null) {
    if (empty($_SESSION['user'])) {
        respond(false, 'Unauthorized. Please login.');
    }
    if ($role && $_SESSION['user']['role'] !== $role) {
        respond(false, 'Access denied. Insufficient permissions.');
    }
    return $_SESSION['user'];
}

// ── Sanitize input ──
function clean($value) {
    return htmlspecialchars(strip_tags(trim($value)));
}

// ── Get POST body as JSON ──
function getBody() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

// ── Hash password ──
function hashPassword($pass) {
    return password_hash($pass, PASSWORD_BCRYPT);
}

// ── Verify password ──
function verifyPassword($pass, $hash) {
    return password_verify($pass, $hash);
}

// ── Format file size ──
function formatSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
