<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$roomId = (int)($_GET['room_id'] ?? 0);
$examId = (int)($_GET['exam_id'] ?? 0);
$searchRoll = strtoupper(trim((string)($_GET['search_roll'] ?? $_GET['highlight'] ?? '')));

if ($roomId <= 0) {
    json_response(['error' => 'room_id is required'], 400);
}

$pdo = db();
$room = $pdo->prepare("SELECT id, room_no, block, capacity, rows_count, cols_count, active FROM rooms WHERE id = ?");
$room->execute([$roomId]);
$room = $room->fetch();
if (!$room) {
    json_response(['error' => 'Room not found'], 404);
}

if ($examId <= 0) {
    $q = $pdo->prepare("SELECT exam_id FROM seating WHERE room_id = ? ORDER BY exam_id DESC LIMIT 1");
    $q->execute([$roomId]);
    $examId = (int)($q->fetchColumn() ?: 0);
}

$exam = null;
if ($examId > 0) {
    $q = $pdo->prepare("SELECT id AS exam_id, exam_name, exam_date, start_time FROM exams WHERE id = ?");
    $q->execute([$examId]);
    $exam = $q->fetch();
}

$q = $pdo->prepare("
    SELECT se.row_num, se.col_num, se.roll_no, se.bench_no, se.seat_index,
           COALESCE(se.exam_code, s.exam_code) AS exam_code,
           s.name, s.branch, s.semester, s.year
    FROM seating se
    LEFT JOIN students s ON s.roll_no = se.roll_no
    WHERE se.room_id = ? AND se.exam_id = ?
    ORDER BY se.row_num, se.col_num");
$q->execute([$roomId, $examId]);
$rawSeats = $q->fetchAll();

$isAdmin = is_admin();
$verifiedRoll = '';

if ($searchRoll !== '') {
    if ($isAdmin) {
        $verifiedRoll = $searchRoll;
    } else {
        $dob = trim((string)($_GET['dob'] ?? ''));
        if ($dob === '') {
            json_response(['error' => 'Date of birth is required for student verification'], 400);
        }

        // Apply rate limiting per IP+roll
        $clientIp = get_client_ip();
        $rollKey = 'room_search:' . substr($searchRoll, 0, 36);
        if (!check_ip_rate_limit($rollKey, 30, 60, $clientIp) || !check_ip_rate_limit('seat_search_ip', 300, 60, $clientIp)) {
            json_response(['error' => 'Too many search requests. Please wait a moment before searching again.'], 429);
        }
        record_ip_failed_attempt($rollKey, 30, 60, 60, $clientIp);
        record_ip_failed_attempt('seat_search_ip', 300, 60, 60, $clientIp);

        $stuCheck = $pdo->prepare("SELECT dob FROM students WHERE UPPER(TRIM(roll_no)) = ?");
        $stuCheck->execute([$searchRoll]);
        $actualDob = $stuCheck->fetchColumn();

        $noMatchMsg = 'No student found matching the provided roll number and date of birth.';
        if ($actualDob === false || $actualDob === null || trim((string)$actualDob) === '' || strtotime((string)$actualDob) === false || strtotime($dob) === false) {
            json_response(['error' => $noMatchMsg], 404);
        }

        $actualNorm = date('Y-m-d', (int)strtotime((string)$actualDob));
        $inputNorm = date('Y-m-d', (int)strtotime($dob));
        if ($actualNorm !== $inputNorm) {
            json_response(['error' => $noMatchMsg], 404);
        }
        $verifiedRoll = $searchRoll;
    }
}

$seats = [];
foreach ($rawSeats as $s) {
    $isOwner = ($verifiedRoll !== '' && strtoupper(trim($s['roll_no'])) === $verifiedRoll);
    if (!$isAdmin && !$isOwner) {
        $s['name'] = null; // Protected: student privacy
    }
    $seats[] = $s;
}

json_response([
    'success' => true,
    'data' => [
        'room'  => $room,
        'exam'  => $exam,
        'seats' => $seats,
    ],
]);