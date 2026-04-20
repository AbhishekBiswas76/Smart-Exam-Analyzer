<?php
// ═══════════════════════════════════════════════
//  api/students.php  (Teacher only)
//  GET  → list students with performance data
//  GET ?id=X → single student full details
// ═══════════════════════════════════════════════

require_once '../config/helpers.php';
require_once '../config/db.php';

$user = requireAuth('teacher');
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(false, 'Method not allowed.');
}

// ── Single student detail ──
if (!empty($_GET['id'])) {
    $studentId = (int)$_GET['id'];

    $stmt = $db->prepare('SELECT id, name, email, photo, department, semester, created_at FROM users WHERE id = ? AND role = "student"');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$student) respond(false, 'Student not found.');

    // Test attempts
    $stmt = $db->prepare('
        SELECT ta.*, t.title AS test_title, t.subject
        FROM test_attempts ta
        JOIN tests t ON ta.test_id = t.id
        WHERE ta.student_id = ?
        ORDER BY ta.submitted_at DESC
    ');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $attempts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Average score
    $stmt = $db->prepare('SELECT AVG(percentage) AS avg_score, COUNT(*) AS total_tests FROM test_attempts WHERE student_id = ?');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Attendance
    $stmt = $db->prepare('SELECT COUNT(*) AS total, SUM(status="present") AS present FROM attendance WHERE student_id = ?');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $att = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    $attPct = $att['total'] > 0 ? round(($att['present'] / $att['total']) * 100) : 0;

    respond(true, 'Student details.', [
        'student'     => $student,
        'attempts'    => $attempts,
        'avg_score'   => round($stats['avg_score'] ?? 0, 1),
        'total_tests' => $stats['total_tests'],
        'attendance'  => $attPct,
    ]);
}

// ── List all students ──
$where  = ['u.role = "student"'];
$params = [];
$types  = '';

if (!empty($_GET['department'])) {
    $where[]  = 'u.department = ?';
    $params[] = clean($_GET['department']);
    $types   .= 's';
}
if (!empty($_GET['semester'])) {
    $where[]  = 'u.semester = ?';
    $params[] = clean($_GET['semester']);
    $types   .= 's';
}
if (!empty($_GET['search'])) {
    $s        = '%' . clean($_GET['search']) . '%';
    $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = $s; $params[] = $s;
    $types   .= 'ss';
}

$whereStr = implode(' AND ', $where);
$sql = "
    SELECT
        u.id, u.name, u.email, u.photo, u.department, u.semester,
        ROUND(AVG(ta.percentage), 1)  AS avg_score,
        COUNT(ta.id)                  AS tests_taken,
        ROUND(
          (SELECT COUNT(*) FROM attendance a WHERE a.student_id = u.id AND a.status = 'present') * 100.0 /
          NULLIF((SELECT COUNT(*) FROM attendance a2 WHERE a2.student_id = u.id), 0)
        , 0) AS attendance_pct
    FROM users u
    LEFT JOIN test_attempts ta ON ta.student_id = u.id
    WHERE $whereStr
    GROUP BY u.id
    ORDER BY avg_score DESC
";

$stmt = $db->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db->close();

respond(true, 'Students fetched.', $rows);
