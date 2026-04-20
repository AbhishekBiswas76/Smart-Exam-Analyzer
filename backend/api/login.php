<?php
// ═══════════════════════════════════════════════
//  api/login.php
//  METHOD: POST
//  BODY:   { email, password }
// ═══════════════════════════════════════════════

require_once '../config/helpers.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.');
}

$body     = getBody();
$email    = clean($body['email']    ?? '');
$password =       $body['password'] ?? '';

if (!$email || !$password) {
    respond(false, 'Email and password are required.');
}

// ── Find user by email ──
$db   = getDB();
$stmt = $db->prepare('SELECT id, name, email, password, role, photo, department, semester FROM users WHERE email = ? AND is_active = 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();
$db->close();

if (!$user) {
    respond(false, 'No account found with this email.');
}

if (!verifyPassword($password, $user['password'])) {
    respond(false, 'Incorrect password. Please try again.');
}

// ── Set session ──
unset($user['password']); // never send password back
$_SESSION['user'] = $user;

respond(true, 'Login successful!', $user);
