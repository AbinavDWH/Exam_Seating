<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/seating_engine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}
require_admin_api();

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$examId = (int)($in['exam_id'] ?? 0);
if ($examId <= 0) {
    json_response(['error' => 'exam_id is required'], 400);
}

@ini_set('memory_limit', '512M');
@set_time_limit(300);

try {
    $options = [
        'num_rooms'          => !empty($in['num_rooms']) ? max(1, min(5000, (int)$in['num_rooms'])) : 0,
        'benches_per_room'   => !empty($in['benches_per_room']) ? max(1, min(100, (int)$in['benches_per_room'])) : 0,
        'students_per_bench' => !empty($in['students_per_bench']) ? max(1, min(20, (int)$in['students_per_bench'])) : 0,
    ];
    $result = generateSeating(db(), $examId, $options);
    json_response(['success' => true, 'data' => $result]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 422);
}