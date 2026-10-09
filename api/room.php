<?php
require_once __DIR__ . '/../config/db.php';

$roomId = (int)($_GET['room_id'] ?? 0);
$examId = (int)($_GET['exam_id'] ?? 0);
if ($roomId <= 0) json_response(['error' => 'room_id is required'], 400);

$pdo = db();
$room = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$room->execute([$roomId]);
$room = $room->fetch();
if (!$room) json_response(['error' => 'Room not found'], 404);

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
    LEFT JOIN students s ON s.roll_no = se.roll_no AND s.exam_id = se.exam_id
    WHERE se.room_id = ? AND se.exam_id = ?
    ORDER BY se.row_num, se.col_num");
$q->execute([$roomId, $examId]);

json_response(['success' => true, 'data' => [
    'room' => $room, 'exam' => $exam, 'seats' => $q->fetchAll(),
]]);