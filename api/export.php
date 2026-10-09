<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_admin_api();

$examId = (int)($_GET['exam_id'] ?? 0);
$roomId = (int)($_GET['room_id'] ?? 0);
if ($examId <= 0) {
    json_response(['error' => 'exam_id required'], 400);
}

$sql = "SELECT r.room_no, r.block, se.row_num, se.col_num, se.bench_no, se.seat_index,
               se.roll_no, s.name, s.branch, s.semester, COALESCE(se.exam_code, s.exam_code) AS exam_code
        FROM seating se
        JOIN rooms r ON r.id = se.room_id
        LEFT JOIN students s ON s.roll_no = se.roll_no
        WHERE se.exam_id = ?" . ($roomId > 0 ? " AND se.room_id = " . $roomId : "") .
        " ORDER BY r.room_no, se.row_num, se.col_num";
$stmt = db()->prepare($sql);
$stmt->execute([$examId]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="seating_exam' . $examId . '.csv"');
$out = fopen('php://output', 'w');

fputcsv($out, ['Room', 'Block', 'Row', 'Column', 'Bench', 'Seat', 'Roll No', 'Name', 'Branch', 'Semester', 'Exam Code']);

if (!function_exists('sanitize_csv_cell')) {
    function sanitize_csv_cell(mixed $val): mixed {
        if (is_string($val) && strlen($val) > 0 && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $val;
        }
        return $val;
    }
}

while ($row = $stmt->fetch()) {
    $cleanRow = array_map('sanitize_csv_cell', $row);
    fputcsv($out, $cleanRow);
}
fclose($out);