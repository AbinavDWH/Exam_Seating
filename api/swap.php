<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_admin_api();

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$examId = (int)($in['exam_id'] ?? 0);
$rollA  = trim($in['roll_a'] ?? '');
$rollB  = trim($in['roll_b'] ?? '');
if (!$examId || !$rollA || !$rollB) json_response(['error' => 'exam_id, roll_a, roll_b required'], 400);

$pdo = db();
$get = $pdo->prepare("SELECT * FROM seating WHERE exam_id = ? AND roll_no = ?");
$get->execute([$examId, $rollA]); $a = $get->fetch();
$get->execute([$examId, $rollB]); $b = $get->fetch();
if (!$a || !$b) json_response(['error' => 'One or both seats not found'], 404);

$pdo->beginTransaction();
$upd = $pdo->prepare("UPDATE seating SET room_id=?, row_num=?, col_num=?, bench_no=?, seat_index=?
                      WHERE exam_id=? AND roll_no=?");
$upd->execute([$b['room_id'], $b['row_num'], $b['col_num'], $b['bench_no'], $b['seat_index'], $examId, $rollA]);
$upd->execute([$a['room_id'], $a['row_num'], $a['col_num'], $a['bench_no'], $a['seat_index'], $examId, $rollB]);
$pdo->commit();
json_response(['success' => true, 'message' => "Swapped $rollA ⇄ $rollB"]);