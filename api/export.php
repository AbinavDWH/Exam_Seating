<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_admin_api();

$examId = (int)($_GET['exam_id'] ?? 0);
$roomId = (int)($_GET['room_id'] ?? 0);
if (!$examId) { json_response(['error' => 'exam_id required'], 400); }

$sql = "SELECT r.room_no, r.block, se.row_num, se.col_num, se.bench_no, se.seat_index,
               se.roll_no, s.name, s.branch, s.semester, COALESCE(se.exam_code, s.exam_code) AS exam_code
        FROM seating se
        JOIN rooms r ON r.id = se.room_id
        LEFT JOIN students s ON s.roll_no = se.roll_no AND s.exam_id = se.exam_id
        WHERE se.exam_id = ?" . ($roomId ? " AND se.room_id = " . $roomId : "") .
        " ORDER BY r.room_no, se.row_num, se.col_num";
$stmt = db()->prepare($sql);
$stmt->execute([$examId]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="seating_exam' . $examId . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Room', 'Block', 'Row', 'Column', 'Bench', 'Seat', 'Roll No', 'Name', 'Branch', 'Semester', 'Exam Code']);
while ($row = $stmt->fetch()) fputcsv($out, $row);
fclose($out);