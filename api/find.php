<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// Rate limiting: max 15 searches per minute per client
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$now = time();
$rateKey = 'find_rate_' . md5($ip);
$history = $_SESSION[$rateKey] ?? [];
$history = array_filter($history, fn($t) => ($now - $t) < 60);
if (count($history) >= 15) {
    json_response(['error' => 'Too many search requests. Please wait a moment before searching again.'], 429);
}
$history[] = $now;
$_SESSION[$rateKey] = $history;

$roll = strtoupper(trim((string)($_GET['roll'] ?? '')));
$dob = trim((string)($_GET['dob'] ?? ''));
$examId = (int)($_GET['exam_id'] ?? 0);

if ($roll === '') {
    json_response(['error' => 'Roll number is required'], 400);
}

$pdo = db();

// Optional verification: check Date of Birth if provided
if ($dob !== '') {
    $stuCheck = $pdo->prepare("SELECT dob FROM students WHERE UPPER(TRIM(roll_no)) = ?");
    $stuCheck->execute([$roll]);
    $actualDob = $stuCheck->fetchColumn();
    if ($actualDob && $actualDob !== $dob) {
        json_response(['error' => 'Date of birth does not match our records for this roll number.'], 403);
    }
}

$whereExtra = "";
$params = [$roll];
if ($examId > 0) {
    $whereExtra = " AND se.exam_id = ?";
    $params[] = $examId;
}

$stmt = $pdo->prepare("
    SELECT s.roll_no, s.name, s.branch, s.semester, s.year, s.dob,
           COALESCE(se.exam_code, s.exam_code) AS exam_code,
           se.row_num, se.col_num, se.bench_no, se.seat_index,
           r.id AS room_id, r.room_no, r.block, r.rows_count, r.cols_count,
           e.id AS exam_id, e.exam_name, e.exam_date, e.start_time, e.end_time
    FROM seating se
    JOIN students s ON s.roll_no = se.roll_no
    JOIN rooms r    ON r.id = se.room_id
    JOIN exams e    ON e.id = se.exam_id
    WHERE UPPER(TRIM(se.roll_no)) = ? $whereExtra
    ORDER BY e.exam_date DESC
    LIMIT 1
");
$stmt->execute($params);
$seat = $stmt->fetch();

if (!$seat) {
    json_response(['error' => "No seat found for roll number '{$roll}'"], 404);
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

json_response(['success' => true, 'data' => $seat]);