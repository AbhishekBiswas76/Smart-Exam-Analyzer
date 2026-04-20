<?php
// ═══════════════════════════════════════════════
//  api/materials.php
//  GET  → fetch materials (with filters)
//  POST → upload new material (teacher only)
//  DELETE → remove material (teacher only)
// ═══════════════════════════════════════════════

require_once '../config/helpers.php';
require_once '../config/db.php';

$user   = requireAuth();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ════════════════════════════════
//  GET — Fetch materials
// ════════════════════════════════
if ($method === 'GET') {
    $where  = ['1=1'];
    $params = [];
    $types  = '';

    // Filter by department
    if (!empty($_GET['department'])) {
        $where[]  = 'department = ?';
        $params[] = clean($_GET['department']);
        $types   .= 's';
    }
    // Filter by semester
    if (!empty($_GET['semester'])) {
        $where[]  = 'semester = ?';
        $params[] = clean($_GET['semester']);
        $types   .= 's';
    }
    // Filter by type
    if (!empty($_GET['type'])) {
        $where[]  = 'type = ?';
        $params[] = clean($_GET['type']);
        $types   .= 's';
    }
    // Search by title/subject
    if (!empty($_GET['search'])) {
        $s        = '%' . clean($_GET['search']) . '%';
        $where[]  = '(m.title LIKE ? OR m.subject LIKE ?)';
        $params[] = $s;
        $params[] = $s;
        $types   .= 'ss';
    }

    $whereStr = implode(' AND ', $where);
    $sql = "SELECT m.*, u.name AS uploaded_by_name
            FROM materials m
            JOIN users u ON m.uploaded_by = u.id
            WHERE $whereStr
            ORDER BY m.created_at DESC";

    $stmt = $db->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();
    respond(true, 'Materials fetched.', $rows);
}

// ════════════════════════════════
//  POST — Upload material (teacher only)
// ════════════════════════════════
if ($method === 'POST') {
    if ($user['role'] !== 'teacher') {
        respond(false, 'Only teachers can upload materials.');
    }

    $title      = clean($_POST['title']      ?? '');
    $subject    = clean($_POST['subject']    ?? '');
    $department = clean($_POST['department'] ?? '');
    $semester   = clean($_POST['semester']   ?? '');
    $type       = clean($_POST['type']       ?? '');

    if (!$title || !$subject || !$department || !$semester || !$type) {
        respond(false, 'All fields are required.');
    }
    if (!in_array($type, ['pdf', 'video', 'pyq'])) {
        respond(false, 'Invalid material type.');
    }
    if (empty($_FILES['file']['tmp_name'])) {
        respond(false, 'No file uploaded.');
    }

    // Validate file
    $allowed = [
        'pdf'   => ['application/pdf'],
        'video' => ['video/mp4', 'video/mpeg', 'video/quicktime'],
        'pyq'   => ['application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];
    $mime = mime_content_type($_FILES['file']['tmp_name']);
    if (!in_array($mime, $allowed[$type])) {
        respond(false, 'Invalid file type for the selected material type.');
    }

    // Check file size (max 50MB)
    if ($_FILES['file']['size'] > 52428800) {
        respond(false, 'File too large. Maximum 50MB allowed.');
    }

    // Save file
    $ext      = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $filename = 'mat_' . uniqid() . '.' . $ext;
    $dir      = '../uploads/materials/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    move_uploaded_file($_FILES['file']['tmp_name'], $dir . $filename);

    $filePath = 'uploads/materials/' . $filename;
    $fileSize = formatSize($_FILES['file']['size']);

    // Insert to DB
    $stmt = $db->prepare('INSERT INTO materials (title, subject, department, semester, type, file_path, file_size, uploaded_by) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->bind_param('sssssssi', $title, $subject, $department, $semester, $type, $filePath, $fileSize, $user['id']);

    if (!$stmt->execute()) {
        respond(false, 'Failed to save material.');
    }
    $id = $stmt->insert_id;
    $stmt->close();
    $db->close();

    respond(true, 'Material uploaded successfully!', [
        'id' => $id, 'title' => $title, 'file_path' => $filePath, 'file_size' => $fileSize
    ]);
}

// ════════════════════════════════
//  DELETE — Remove material
// ════════════════════════════════
if ($method === 'DELETE') {
    if ($user['role'] !== 'teacher') {
        respond(false, 'Only teachers can delete materials.');
    }

    $body = getBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) respond(false, 'Material ID required.');

    // Get file path first
    $stmt = $db->prepare('SELECT file_path, uploaded_by FROM materials WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $mat = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$mat) respond(false, 'Material not found.');
    if ($mat['uploaded_by'] != $user['id']) respond(false, 'You can only delete your own materials.');

    // Delete file from disk
    $diskPath = '../' . $mat['file_path'];
    if (file_exists($diskPath)) unlink($diskPath);

    // Delete from DB
    $stmt = $db->prepare('DELETE FROM materials WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $db->close();

    respond(true, 'Material deleted.');
}
