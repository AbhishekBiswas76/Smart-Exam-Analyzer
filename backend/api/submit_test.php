<?php
// ═══════════════════════════════════════════════
//  api/submit_test.php
//  METHOD: POST
//  PURPOSE: Student submits quiz answers → auto-graded
//  BODY: { test_id, answers: [{question_id, selected_ans}], time_taken }
// ═══════════════════════════════════════════════

require_once '../config/helpers.php';
require_once '../config/db.php';

$user = requireAuth('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.');
}

$body      = getBody();
$testId    = (int)($body['test_id']   ?? 0);
$answers   = $body['answers']          ?? [];
$timeTaken = (int)($body['time_taken'] ?? 0);

if (!$testId || empty($answers)) {
    respond(false, 'Test ID and answers are required.');
}

$db = getDB();

// ── Check test exists ──
$stmt = $db->prepare('SELECT id, total_marks FROM tests WHERE id = ?');
$stmt->bind_param('i', $testId);
$stmt->execute();
$test = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$test) respond(false, 'Test not found.');

// ── Check already submitted ──
$stmt = $db->prepare('SELECT id FROM test_attempts WHERE test_id = ? AND student_id = ?');
$stmt->bind_param('ii', $testId, $user['id']);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    respond(false, 'You have already submitted this test.');
}
$stmt->close();

// ── Fetch correct answers ──
$stmt = $db->prepare('SELECT id, correct_ans, marks FROM questions WHERE test_id = ?');
$stmt->bind_param('i', $testId);
$stmt->execute();
$correctMap = [];
$totalPossible = 0;
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $correctMap[$row['id']] = ['ans' => $row['correct_ans'], 'marks' => $row['marks']];
    $totalPossible += $row['marks'];
}
$stmt->close();

// ── Grade answers ──
$score          = 0;
$gradedAnswers  = [];
foreach ($answers as $a) {
    $qId      = (int)($a['question_id'] ?? 0);
    $selected = strtoupper(clean($a['selected_ans'] ?? ''));
    $correct  = $correctMap[$qId] ?? null;
    $isCorrect = 0;

    if ($correct && $selected === $correct['ans']) {
        $score    += $correct['marks'];
        $isCorrect = 1;
    }
    $gradedAnswers[] = ['question_id' => $qId, 'selected' => $selected, 'correct' => $isCorrect];
}

$totalMarks = $test['total_marks'] ?: $totalPossible;
$percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0;

// ── Save attempt ──
$stmt = $db->prepare('INSERT INTO test_attempts (test_id, student_id, score, total_marks, percentage, time_taken) VALUES (?,?,?,?,?,?)');
$stmt->bind_param('iiiidi', $testId, $user['id'], $score, $totalMarks, $percentage, $timeTaken);
if (!$stmt->execute()) respond(false, 'Failed to save attempt.');
$attemptId = $stmt->insert_id;
$stmt->close();

// ── Save individual answers ──
$aStmt = $db->prepare('INSERT INTO attempt_answers (attempt_id, question_id, selected_ans, is_correct) VALUES (?,?,?,?)');
foreach ($gradedAnswers as $g) {
    $aStmt->bind_param('iisi', $attemptId, $g['question_id'], $g['selected'], $g['correct']);
    $aStmt->execute();
}
$aStmt->close();
$db->close();

respond(true, 'Test submitted successfully!', [
    'score'      => $score,
    'total'      => $totalMarks,
    'percentage' => $percentage,
    'attempt_id' => $attemptId,
    'grade'      => $percentage >= 90 ? 'A+' : ($percentage >= 75 ? 'A' : ($percentage >= 60 ? 'B' : ($percentage >= 45 ? 'C' : 'F'))),
]);
