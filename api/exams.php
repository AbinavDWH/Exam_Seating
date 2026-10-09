<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();

$exams = $pdo->query("
    SELECT e.*,
      (SELECT COUNT(*) FROM students st WHERE st.exam_id = e.id) AS students,
      (SELECT COUNT(*) FROM seating se  WHERE se.exam_id = e.id) AS assigned
    FROM exams e ORDER BY e.exam_date DESC")->fetchAll();

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM students)                          AS students,
    (SELECT COUNT(*) FROM rooms WHERE active = 1)            AS rooms,
    (SELECT IFNULL(SUM(rows_count * cols_count),0) FROM rooms WHERE active = 1) AS capacity,
    (SELECT COUNT(DISTINCT branch) FROM students)            AS branches")->fetch();

json_response(['success' => true, 'data' => ['exams' => $exams, 'stats' => $stats]]);