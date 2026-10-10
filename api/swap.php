<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_admin_api();
verify_csrf();

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$examId   = (int)($in['exam_id'] ?? 0);
$rollA    = strtoupper(trim($in['roll_a'] ?? ''));
$rollB    = strtoupper(trim($in['roll_b'] ?? ''));
$checkOnly = !empty($in['check_only']);
$force    = !empty($in['force']);

// Support live instant student seat lookup (Item 21)
if (($in['action'] ?? '') === 'lookup') {
    $roll = strtoupper(trim($in['roll'] ?? ''));
    if (!$examId || !$roll) {
        json_response(['error' => 'exam_id and roll are required'], 400);
    }
    $stmt = db()->prepare("
        SELECT se.*, s.name, s.branch, s.semester, s.year,
               COALESCE(se.exam_code, s.exam_code) AS exam_code,
               r.room_no, r.block
        FROM seating se
        JOIN students s ON s.roll_no = se.roll_no
        JOIN rooms r    ON r.id = se.room_id
        WHERE se.exam_id = ? AND UPPER(TRIM(se.roll_no)) = ?
    ");
    $stmt->execute([$examId, $roll]);
    $stu = $stmt->fetch();
    if (!$stu) {
        json_response(['error' => 'No allocated seat found for this student in this exam.'], 404);
    }
    json_response(['success' => true, 'student' => $stu]);
}

if (!$examId || !$rollA || !$rollB) {
    json_response(['error' => 'exam_id, roll_a, and roll_b are required'], 400);
}

if ($rollA === $rollB) {
    json_response(['error' => 'Cannot swap a student seat with themselves.'], 400);
}

$pdo = db();

$get = $pdo->prepare("
    SELECT se.*, s.name, s.branch, s.semester, s.year,
           COALESCE(se.exam_code, s.exam_code) AS exam_code,
           r.room_no, r.block
    FROM seating se
    JOIN students s ON s.roll_no = se.roll_no
    JOIN rooms r    ON r.id = se.room_id
    WHERE se.exam_id = ? AND UPPER(TRIM(se.roll_no)) = ?
");
$get->execute([$examId, $rollA]);
$a = $get->fetch();
$get->execute([$examId, $rollB]);
$b = $get->fetch();

if (!$a || !$b) {
    json_response(['error' => 'One or both allocated seats could not be found for this exam.'], 404);
}

// Fetch all seats in room(s) to check adjacency conflicts after swap
$roomIds = array_unique([(int)$a['room_id'], (int)$b['room_id']]);
$placeholders = implode(',', array_fill(0, count($roomIds), '?'));
$roomSeatsStmt = $pdo->prepare("
    SELECT se.room_id, se.row_num, se.col_num, se.roll_no,
           COALESCE(se.exam_code, s.exam_code) AS exam_code,
           r.room_no
    FROM seating se
    JOIN students s ON s.roll_no = se.roll_no
    JOIN rooms r    ON r.id = se.room_id
    WHERE se.exam_id = ? AND se.room_id IN ($placeholders)
");
$roomSeatsStmt->execute(array_merge([$examId], $roomIds));
$allRoomSeats = $roomSeatsStmt->fetchAll();

// Construct grid after the proposed swap
// key: "roomId:row:col" => ['roll_no', 'exam_code', 'room_no']
$grid = [];
foreach ($allRoomSeats as $rs) {
    $rid = (int)$rs['room_id'];
    $row = (int)$rs['row_num'];
    $col = (int)$rs['col_num'];
    $rRoll = strtoupper(trim($rs['roll_no']));

    if ($rRoll === $rollA) {
        // A will be at B's position
        $grid["{$b['room_id']}:{$b['row_num']}:{$b['col_num']}"] = [
            'roll_no'   => $rollA,
            'exam_code' => $a['exam_code'],
            'room_no'   => $b['room_no'],
        ];
    } elseif ($rRoll === $rollB) {
        // B will be at A's position
        $grid["{$a['room_id']}:{$a['row_num']}:{$a['col_num']}"] = [
            'roll_no'   => $rollB,
            'exam_code' => $b['exam_code'],
            'room_no'   => $a['room_no'],
        ];
    } else {
        $grid["{$rid}:{$row}:{$col}"] = [
            'roll_no'   => $rRoll,
            'exam_code' => $rs['exam_code'],
            'room_no'   => $rs['room_no'],
        ];
    }
}

// Check adjacent horizontal and vertical neighbors for clashes
$orthoOffsets = [[0, 1], [0, -1], [1, 0], [-1, 0]];
$clashes = [];

// Check A at B's position
$targetKeyA = "{$b['room_id']}:{$b['row_num']}:{$b['col_num']}";
foreach ($orthoOffsets as [$dr, $dc]) {
    $nr = (int)$b['row_num'] + $dr;
    $nc = (int)$b['col_num'] + $dc;
    $nKey = "{$b['room_id']}:{$nr}:{$nc}";
    if (isset($grid[$nKey]) && $grid[$nKey]['roll_no'] !== $rollA) {
        $n = $grid[$nKey];
        if ($n['exam_code'] === $a['exam_code']) {
            $clashes[] = "Placing {$a['roll_no']} ({$a['exam_code']}) at Hall {$b['room_no']} (Row {$b['row_num']}, Col {$b['col_num']}) clashes with adjacent student {$n['roll_no']} ({$n['exam_code']}) at Row {$nr}, Col {$nc}.";
        }
    }
}

// Check B at A's position
$targetKeyB = "{$a['room_id']}:{$a['row_num']}:{$a['col_num']}";
foreach ($orthoOffsets as [$dr, $dc]) {
    $nr = (int)$a['row_num'] + $dr;
    $nc = (int)$a['col_num'] + $dc;
    $nKey = "{$a['room_id']}:{$nr}:{$nc}";
    if (isset($grid[$nKey]) && $grid[$nKey]['roll_no'] !== $rollB) {
        $n = $grid[$nKey];
        if ($n['exam_code'] === $b['exam_code']) {
            $clashes[] = "Placing {$b['roll_no']} ({$b['exam_code']}) at Hall {$a['room_no']} (Row {$a['row_num']}, Col {$a['col_num']}) clashes with adjacent student {$n['roll_no']} ({$n['exam_code']}) at Row {$nr}, Col {$nc}.";
        }
    }
}

$clashes = array_values(array_unique($clashes));

if ($checkOnly) {
    json_response([
        'success'   => true,
        'student_a' => $a,
        'student_b' => $b,
        'has_clash' => count($clashes) > 0,
        'clashes'   => $clashes,
    ]);
}

if (!empty($clashes) && !$force) {
    json_response([
        'success'   => false,
        'warning'   => true,
        'has_clash' => true,
        'clashes'   => $clashes,
        'student_a' => $a,
        'student_b' => $b,
        'message'   => "Warning: This swap creates " . count($clashes) . " adjacent same-paper conflict(s). Set force=true to proceed anyway."
    ], 409);
}

// Execute swap atomically by parking student A in temporary coordinates first to avoid UNIQUE constraint violation
$pdo->beginTransaction();
try {
    // 1. Temporarily park student A in negative coordinates
    $tempPark = $pdo->prepare("UPDATE seating SET row_num = -row_num - 999999 WHERE id = ?");
    $tempPark->execute([$a['id']]);

    // 2. Move student B into student A's original seat
    $updB = $pdo->prepare("UPDATE seating SET room_id=?, row_num=?, col_num=?, bench_no=?, seat_index=? WHERE id=?");
    $updB->execute([$a['room_id'], $a['row_num'], $a['col_num'], $a['bench_no'], $a['seat_index'], $b['id']]);

    // 3. Move student A from temporary coordinates into student B's original seat
    $updA = $pdo->prepare("UPDATE seating SET room_id=?, row_num=?, col_num=?, bench_no=?, seat_index=? WHERE id=?");
    $updA->execute([$b['room_id'], $b['row_num'], $b['col_num'], $b['bench_no'], $b['seat_index'], $a['id']]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['error' => 'Database seat swap failed: ' . $e->getMessage()], 500);
}

json_response([
    'success'   => true,
    'message'   => "Seats successfully swapped: {$rollA} ⇄ {$rollB}",
    'has_clash' => count($clashes) > 0,
    'clashes'   => $clashes,
]);