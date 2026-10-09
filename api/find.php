<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$roll = trim($_GET['roll'] ?? '');
if ($roll === '') {
    json_response(['error' => 'Roll number is required'], 400);
}

$stmt = db()->prepare("
    SELECT s.roll_no, s.name, s.branch, s.semester, s.year,
           COALESCE(se.exam_code, s.exam_code) AS exam_code,
           se.row_num, se.col_num, se.bench_no, se.seat_index,
           r.id AS room_id, r.room_no, r.block, r.rows_count, r.cols_count,
           e.id AS exam_id, e.exam_name, e.exam_date, e.start_time
    FROM seating se
    JOIN students s ON s.roll_no = se.roll_no AND s.exam_id = se.exam_id
    JOIN rooms r    ON r.id = se.room_id
    JOIN exams e    ON e.id = se.exam_id
    WHERE UPPER(TRIM(s.roll_no)) = UPPER(?)
    ORDER BY e.exam_date DESC
    LIMIT 1
");
$stmt->execute([$roll]);
$seat = $stmt->fetch();

if (!$seat) {
    json_response(['error' => "No seat found for roll number '{$roll}'"], 404);
}

json_response(['success' => true, 'data' => $seat]);