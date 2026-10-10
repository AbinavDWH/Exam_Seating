<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$roll = strtoupper(trim((string)($_GET['roll'] ?? '')));
$examId = (int)($_GET['exam_id'] ?? 0);

if ($roll === '') {
    json_response(['error' => 'Roll number is required'], 400);
}

// Campus network protection rate limiting
$clientIp = get_client_ip();
$rollKey = 'search_roll:' . substr($roll, 0, 36);
if (!check_ip_rate_limit($rollKey, 60, 60, $clientIp) || !check_ip_rate_limit('seat_search_ip', 300, 60, $clientIp)) {
    json_response(['error' => 'Too many tries. Wait a minute and try again.'], 429);
}
record_ip_failed_attempt($rollKey, 60, 60, 60, $clientIp);
record_ip_failed_attempt('seat_search_ip', 300, 60, 60, $clientIp);

$pdo = db();

$noMatchMsg = 'We couldn’t find that roll number. Check the number and try again.';

// Fetch all seated exams for this student, ordered by next upcoming exam first
$allStmt = $pdo->prepare("
    SELECT se.exam_id, e.exam_name, e.exam_date, e.start_time, e.end_time,
           COALESCE(se.exam_code, s.exam_code) AS exam_code,
           r.id AS room_id, r.room_no, r.block,
           se.row_num, se.col_num, se.bench_no, se.seat_index
    FROM seating se
    JOIN exams e ON e.id = se.exam_id
    JOIN students s ON s.roll_no = se.roll_no
    JOIN rooms r ON r.id = se.room_id
    WHERE UPPER(TRIM(se.roll_no)) = ?
    ORDER BY
        CASE WHEN e.exam_date >= DATE('now') THEN 0 ELSE 1 END ASC,
        CASE WHEN e.exam_date >= DATE('now') THEN e.exam_date END ASC,
        e.exam_date DESC,
        e.start_time ASC
");
$allStmt->execute([$roll]);
$allExams = $allStmt->fetchAll();

if (empty($allExams)) {
    json_response(['error' => $noMatchMsg], 404);
}

$targetExamId = $examId > 0 ? $examId : (int)$allExams[0]['exam_id'];

$stmt = $pdo->prepare("
    SELECT s.roll_no, s.name, s.branch, s.semester, s.year,
           COALESCE(se.exam_code, s.exam_code) AS exam_code,
           se.row_num, se.col_num, se.bench_no, se.seat_index,
           r.id AS room_id, r.room_no, r.block, r.rows_count, r.cols_count,
           e.id AS exam_id, e.exam_name, e.exam_date, e.start_time, e.end_time
    FROM seating se
    JOIN students s ON s.roll_no = se.roll_no
    JOIN rooms r    ON r.id = se.room_id
    JOIN exams e    ON e.id = se.exam_id
    WHERE UPPER(TRIM(se.roll_no)) = ? AND se.exam_id = ?
    LIMIT 1
");
$stmt->execute([$roll, $targetExamId]);
$seat = $stmt->fetch();

if (!$seat) {
    $seat = $allExams[0];
}

// Calculate separate reporting time (30 minutes prior to exam start)
$startTimeTs = strtotime((string)$seat['start_time']);
if ($startTimeTs !== false) {
    $seat['reporting_time'] = date('H:i:s', $startTimeTs - (30 * 60));
    $seat['reporting_mins_early'] = 30;
} else {
    $seat['reporting_time'] = $seat['start_time'];
    $seat['reporting_mins_early'] = 0;
}

$seat['all_exams'] = $allExams;

json_response(['success' => true, 'data' => $seat]);