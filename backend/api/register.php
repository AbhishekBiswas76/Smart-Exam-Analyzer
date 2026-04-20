<?php
// ═══════════════════════════════════════════════
//  api/register.php
//  METHOD: POST
//  BODY:   { name, email, password, role, department, semester }
// ═══════════════════════════════════════════════

require_once '../config/helpers.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.');
}

// ── 1. Read input ──
$body = getBody();
$name       = clean($body['name']       ?? '');
$email      = clean($body['email']      ?? '');
$password   =       $body['password']   ?? '';
$role       = clean($body['role']       ?? 'student');
$department = clean($body['department'] ?? '');
$semester   = clean($body['semester']   ?? '');

// ── 2. Validate ──
if (!$name || !$email || !$password) {
    respond(false, 'Name, email, and password are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Invalid email address.');
}
if (strlen($password) < 6) {
    respond(false, 'Password must be at least 6 characters.');
}
if (!in_array($role, ['student', 'teacher'])) {
    respond(false, 'Role must be student or teacher.');
}

// ── 3. Check if email already exists ──
$db   = getDB();
$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    respond(false, 'Email already registered. Please login.');
}
$stmt->close();

// ── 4. Handle profile photo upload ──
$photoPath = null;
if (!empty($_FILES['photo']['tmp_name'])) {
    $allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mimeType  = mime_content_type($_FILES['photo']['tmp_name']);
    if (!in_array($mimeType, $allowed)) {
        respond(false, 'Invalid photo format. Use JPG, PNG, or GIF.');
    }
    $ext       = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename  = 'photo_' . uniqid() . '.' . $ext;
    $uploadDir = '../uploads/photos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename);
    $photoPath = 'uploads/photos/' . $filename;
}

// ── 5. Insert user ──
$hashed = hashPassword($password);
$stmt   = $db->prepare('INSERT INTO users (name, email, password, role, photo, department, semester) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('sssssss', $name, $email, $hashed, $role, $photoPath, $department, $semester);

if (!$stmt->execute()) {
    respond(false, 'Registration failed. Try again.');
}

$userId = $stmt->insert_id;
$stmt->close();
$db->close();

// ── 6. Auto-login after register ──
$_SESSION['user'] = [
    'id'         => $userId,
    'name'       => $name,
    'email'      => $email,
    'role'       => $role,
    'photo'      => $photoPath,
    'department' => $department,
    'semester'   => $semester,
];

respond(true, 'Registration successful!', $_SESSION['user']);
