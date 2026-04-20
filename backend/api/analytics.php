<?php
// ═══════════════════════════════════════════════
//  api/analytics.php
//  GET ?type=student  → logged-in student's stats
//  GET ?type=class    → class-wide stats (teacher)
// ═══════════════════════════════════════════════

require_once '../config/helpers.php';
require_once '../config/db.php';

$user = requireAuth();
$db   = getDB();
$type = clean($_GET['type'] ?? 'student');

// ════════════════════════════════
//  Student Analytics
// ════════════════════════════════
if ($type === 'student') {
    $id = $user['id'];

    // Overall stats
    $stmt = $db->prepare('
        SELECT
            COUNT(*)           AS total_tests,
            ROUND(AVG(percentage),1) AS avg_score,
            MAX(percentage)    AS best_score,
            MIN(percentage)    AS lowest_score,
            SUM(score)         AS total_marks_earned
        FROM test_attempts WHERE student_id = ?
    ');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $overall = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Per-subject average
    $stmt = $db->prepare('
        SELECT t.subject,
               ROUND(AVG(ta.percentage),1) AS avg_pct,
               COUNT(*)                    AS attempt_count
        FROM test_attempts ta
        JOIN tests t ON ta.test_id = t.id
        WHERE ta.student_id = ?
        GROUP BY t.subject
        ORDER BY avg_pct DESC
    ');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $bySubject = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Test score trend
    $stmt = $db->prepare('
        SELECT t.title, ta.percentage, ta.submitted_at
        FROM test_attempts ta
        JOIN tests t ON ta.test_id = t.id
        WHERE ta.student_id = ?
        ORDER BY ta.submitted_at ASC
        LIMIT 10
    ');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $trend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Weak subjects (below 60%)
    $weak = array_filter($bySubject, fn($s) => $s['avg_pct'] < 60);

    // Class rank
    $stmt = $db->prepare('
        SELECT COUNT(*) + 1 AS my_rank FROM (
            SELECT student_id, AVG(percentage) AS avg_p
            FROM test_attempts
            GROUP BY student_id
            HAVING avg_p > (SELECT AVG(percentage) FROM test_attempts WHERE student_id = ?)
        ) AS ranked
    ');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $rank = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    respond(true, 'Student analytics.', [
        'overall'    => $overall,
        'by_subject' => $bySubject,
        'trend'      => $trend,
        'weak'       => array_values($weak),
        'rank'       => $rank['my_rank'] ?? 1,
    ]);
}

// ════════════════════════════════
//  Class Analytics (teacher only)
// ════════════════════════════════
if ($type === 'class') {
    if ($user['role'] !== 'teacher') respond(false, 'Teachers only.');

    $dept = clean($_GET['department'] ?? '');
    $sem  = clean($_GET['semester']   ?? '');

    // Score distribution buckets
    $stmt = $db->prepare('
        SELECT
            SUM(percentage BETWEEN 0  AND 39)  AS f_range,
            SUM(percentage BETWEEN 40 AND 59)  AS d_range,
            SUM(percentage BETWEEN 60 AND 74)  AS c_range,
            SUM(percentage BETWEEN 75 AND 84)  AS b_range,
            SUM(percentage BETWEEN 85 AND 100) AS a_range
        FROM test_attempts ta
        JOIN users u ON ta.student_id = u.id
        WHERE (? = "" OR u.department = ?) AND (? = "" OR u.semester = ?)
    ');
    $stmt->bind_param('ssss', $dept, $dept, $sem, $sem);
    $stmt->execute();
    $dist = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Subject averages
    $stmt = $db->prepare('
        SELECT t.subject, ROUND(AVG(ta.percentage),1) AS class_avg, COUNT(DISTINCT ta.student_id) AS students
        FROM test_attempts ta
        JOIN tests t ON ta.test_id = t.id
        JOIN users u ON ta.student_id = u.id
        WHERE (? = "" OR u.department = ?) AND (? = "" OR u.semester = ?)
        GROUP BY t.subject ORDER BY class_avg DESC
    ');
    $stmt->bind_param('ssss', $dept, $dept, $sem, $sem);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Leaderboard top 10
    $stmt = $db->prepare('
        SELECT u.id, u.name, u.photo, ROUND(AVG(ta.percentage),1) AS avg_score,
               COUNT(ta.id) AS tests_taken
        FROM test_attempts ta
        JOIN users u ON ta.student_id = u.id
        WHERE u.role = "student" AND (? = "" OR u.department = ?) AND (? = "" OR u.semester = ?)
        GROUP BY u.id ORDER BY avg_score DESC LIMIT 10
    ');
    $stmt->bind_param('ssss', $dept, $dept, $sem, $sem);
    $stmt->execute();
    $leaderboard = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Overall class average
    $stmt = $db->prepare('
        SELECT ROUND(AVG(ta.percentage),1) AS class_avg,
               COUNT(DISTINCT ta.student_id) AS active_students
        FROM test_attempts ta JOIN users u ON ta.student_id = u.id
        WHERE (? = "" OR u.department = ?) AND (? = "" OR u.semester = ?)
    ');
    $stmt->bind_param('ssss', $dept, $dept, $sem, $sem);
    $stmt->execute();
    $classStats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    respond(true, 'Class analytics.', [
        'distribution'    => $dist,
        'subjects'        => $subjects,
        'leaderboard'     => $leaderboard,
        'class_avg'       => $classStats['class_avg'] ?? 0,
        'active_students' => $classStats['active_students'] ?? 0,
    ]);
}
