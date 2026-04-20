<?php
// ═══════════════════════════════════════════════
//  api/tests.php
//  GET    → list tests / single test with questions
//  POST   → create test (teacher)
//  PUT    → update test (teacher)
//  DELETE → delete test (teacher)
// ═══════════════════════════════════════════════

require_once '../config/helpers.php';
require_once '../config/db.php';

$user   = requireAuth();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ════════════════════════════════
//  GET
// ════════════════════════════════
if ($method === 'GET') {

    // Single test with questions
    if (!empty($_GET['id'])) {
        $id   = (int)$_GET['id'];
        $stmt = $db->prepare('SELECT * FROM tests WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $test = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$test) respond(false, 'Test not found.');

        // Get questions (hide correct answer for students)
        $stmt = $db->prepare('SELECT id, question, option_a, option_b, option_c, option_d' .
            ($user['role'] === 'teacher' ? ', correct_ans' : '') .
            ', marks FROM questions WHERE test_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $db->close();

        $test['questions'] = $questions;
        respond(true, 'Test fetched.', $test);
    }

    // List tests
    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if (!empty($_GET['department'])) {
        $where[] = 'department = ?'; $params[] = clean($_GET['department']); $types .= 's';
    }
    if (!empty($_GET['semester'])) {
        $where[] = 'semester = ?';   $params[] = clean($_GET['semester']);   $types .= 's';
    }
    if ($user['role'] === 'teacher') {
        $where[] = 'created_by = ?'; $params[] = $user['id'];               $types .= 'i';
    }

    $whereStr = implode(' AND ', $where);
    $sql = "SELECT t.*, u.name AS created_by_name,
            (SELECT COUNT(*) FROM questions WHERE test_id = t.id) AS question_count,
            (SELECT score FROM test_attempts WHERE test_id = t.id AND student_id = {$user['id']} LIMIT 1) AS my_score
            FROM tests t JOIN users u ON t.created_by = u.id
            WHERE $whereStr ORDER BY t.scheduled_at DESC";

    $stmt = $db->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();
    respond(true, 'Tests fetched.', $rows);
}

// ════════════════════════════════
//  POST — Create test
// ════════════════════════════════
if ($method === 'POST') {
    if ($user['role'] !== 'teacher') respond(false, 'Only teachers can create tests.');

    $body        = getBody();
    $title       = clean($body['title']       ?? '');
    $subject     = clean($body['subject']     ?? '');
    $department  = clean($body['department']  ?? '');
    $semester    = clean($body['semester']    ?? '');
    $duration    = (int)($body['duration']    ?? 30);
    $totalMarks  = (int)($body['total_marks'] ?? 20);
    $scheduledAt = clean($body['scheduled_at'] ?? '');
    $questions   = $body['questions'] ?? [];

    if (!$title || !$subject || !$department || !$semester) {
        respond(false, 'Title, subject, department, and semester are required.');
    }

    // Insert test
    $stmt = $db->prepare('INSERT INTO tests (title, subject, department, semester, duration, total_marks, scheduled_at, status, created_by) VALUES (?,?,?,?,?,?,?,?,?)');
    $status = 'active';
    $stmt->bind_param('ssssiissi', $title, $subject, $department, $semester, $duration, $totalMarks, $scheduledAt, $status, $user['id']);
    if (!$stmt->execute()) respond(false, 'Failed to create test.');
    $testId = $stmt->insert_id;
    $stmt->close();

    // Insert questions
    if (!empty($questions)) {
        $qStmt = $db->prepare('INSERT INTO questions (test_id, question, option_a, option_b, option_c, option_d, correct_ans, marks) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($questions as $q) {
            $qText = clean($q['question'] ?? '');
            $optA  = clean($q['option_a'] ?? '');
            $optB  = clean($q['option_b'] ?? '');
            $optC  = clean($q['option_c'] ?? '');
            $optD  = clean($q['option_d'] ?? '');
            $ans   = strtoupper(clean($q['correct_ans'] ?? 'A'));
            $marks = (int)($q['marks'] ?? 1);
            if ($qText && $optA && $optB && $optC && $optD) {
                $qStmt->bind_param('issssssi', $testId, $qText, $optA, $optB, $optC, $optD, $ans, $marks);
                $qStmt->execute();
            }
        }
        $qStmt->close();
    }

    $db->close();
    respond(true, 'Test created successfully!', ['test_id' => $testId]);
}

// ════════════════════════════════
//  DELETE — Delete test
// ════════════════════════════════
if ($method === 'DELETE') {
    if ($user['role'] !== 'teacher') respond(false, 'Only teachers can delete tests.');
    $body = getBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) respond(false, 'Test ID required.');

    $stmt = $db->prepare('SELECT created_by FROM tests WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || $row['created_by'] != $user['id']) respond(false, 'Not found or unauthorized.');

    $stmt = $db->prepare('DELETE FROM tests WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    respond(true, 'Test deleted.');
}
