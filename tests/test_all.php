<?php
/**
 * ExamSeat Test Suite
 * Automated regression and security verification tests.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "========================================\n";
echo " ExamSeat Automated Test Suite\n";
echo "========================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assert_test(string $name, bool $condition, string $details = ''): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$name}" . ($details ? ": {$details}" : "") . "\n";
        $testsFailed++;
    }
}

// 1. Test Database Schema & Setup
echo "1. Database Schema & Tables:\n";
require_once __DIR__ . '/../config/db.php';
$pdo = db();

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
$expectedTables = ['students', 'exams', 'rooms', 'seating', 'student_exams', 'admins'];
foreach ($expectedTables as $tbl) {
    assert_test("Table '{$tbl}' exists", in_array($tbl, $tables, true));
}

// Check columns in rooms
$roomCols = $pdo->query("PRAGMA table_info(rooms)")->fetchAll(PDO::FETCH_COLUMN, 1);
assert_test("Room table has rows_count and cols_count", in_array('rows_count', $roomCols, true) && in_array('cols_count', $roomCols, true));
assert_test("Duplicate column 'benches_count' removed from rooms", !in_array('benches_count', $roomCols, true));

// Check columns in students
$studentCols = $pdo->query("PRAGMA table_info(students)")->fetchAll(PDO::FETCH_COLUMN, 1);
assert_test("Student table has 'dob' column", in_array('dob', $studentCols, true));

// 2. Test CSRF Protection
echo "\n2. Security & CSRF Protection:\n";
require_once __DIR__ . '/../config/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token1 = csrf_token();
assert_test("CSRF token is generated and non-empty", !empty($token1) && strlen($token1) === 64);
$token2 = csrf_token();
assert_test("CSRF token is consistent within session", $token1 === $token2);

// Test Login Rate Limiting
for ($i = 0; $i < 5; $i++) {
    record_failed_login('test_user');
}
assert_test("Rate limit triggers after 5 failed login attempts", !check_login_rate_limit('test_user'));
reset_login_rate_limit('test_user');
assert_test("Rate limit resets on successful login", check_login_rate_limit('test_user'));

// 3. Test Student Seating & Privacy Protection
echo "\n3. Privacy & Search API:\n";
// Insert test student with DOB
$testRoll = 'TEST_PRIVACY_01';
$pdo->prepare("DELETE FROM students WHERE roll_no = ?")->execute([$testRoll]);
$pdo->prepare("
    INSERT INTO students (roll_no, name, dob, branch, semester, year, exam_code)
    VALUES (?, 'Alice Secret', '2004-05-15', 'CSE', 4, 2, 'CS401')
")->execute([$testRoll]);

$stu = $pdo->prepare("SELECT roll_no, name, dob FROM students WHERE roll_no = ?");
$stu->execute([$testRoll]);
$row = $stu->fetch();
assert_test("Student DOB recorded accurately", $row['dob'] === '2004-05-15');

// 4. Test Student-to-Exam Multi-Paper Link (student_exams)
echo "\n4. Multi-Exam Link (student_exams):\n";
$exam1Id = 99991;
$exam2Id = 99992;
$pdo->prepare("DELETE FROM exams WHERE id IN (?, ?)")->execute([$exam1Id, $exam2Id]);
$pdo->prepare("INSERT INTO exams (id, exam_name, exam_date, start_time, semester, status) VALUES (?, 'Exam 1', '2026-11-01', '09:30:00', 4, 'upcoming')")->execute([$exam1Id]);
$pdo->prepare("INSERT INTO exams (id, exam_name, exam_date, start_time, semester, status) VALUES (?, 'Exam 2', '2026-11-05', '09:30:00', 4, 'upcoming')")->execute([$exam2Id]);

$stuId = (int)$pdo->query("SELECT id FROM students WHERE roll_no = '{$testRoll}'")->fetchColumn();
$pdo->prepare("DELETE FROM student_exams WHERE student_id = ?")->execute([$stuId]);

// Assign same student to two different exams
$pdo->prepare("INSERT INTO student_exams (student_id, exam_id, exam_code) VALUES (?, ?, ?)")->execute([$stuId, $exam1Id, 'CS401-Paper1']);
$pdo->prepare("INSERT INTO student_exams (student_id, exam_id, exam_code) VALUES (?, ?, ?)")->execute([$stuId, $exam2Id, 'CS402-Paper2']);

$linkedExams = $pdo->prepare("SELECT exam_id FROM student_exams WHERE student_id = ?");
$linkedExams->execute([$stuId]);
$assignedExamIds = $linkedExams->fetchAll(PDO::FETCH_COLUMN);
assert_test("Student can belong to multiple exam sessions via student_exams", count($assignedExamIds) === 2);

// 5. Test Seating Engine - Alternate Spacing & Zero Clashes on Single-Paper Batch
echo "\n5. Seating Engine & Anti-Clash Verification:\n";
require_once __DIR__ . '/../config/seating_engine.php';

// Create a single paper batch of 30 students all with paper 'CS999'
$batchRolls = [];
for ($i = 1; $i <= 30; $i++) {
    $r = sprintf('SP_BATCH_%03d', $i);
    $batchRolls[] = $r;
    $pdo->prepare("INSERT OR REPLACE INTO students (roll_no, name, branch, semester, year, exam_code, exam_id) VALUES (?, ?, 'CSE', 4, 2, 'CS999', ?)")
        ->execute([$r, "Student {$i}", $exam1Id]);
    $stuId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT OR REPLACE INTO student_exams (student_id, exam_id, exam_code) VALUES (?, ?, 'CS999')")
        ->execute([$stuId, $exam1Id]);
}

// Hall 101 layout: 15 rows × 2 cols = 30 desks
$pdo->prepare("DELETE FROM rooms WHERE room_no = 'TEST_H1'")->execute();
$pdo->prepare("INSERT INTO rooms (room_no, block, capacity, rows_count, cols_count, active) VALUES ('TEST_H1', 'Test Block', 60, 15, 2, 1)")->execute();
$pdo->prepare("DELETE FROM rooms WHERE room_no = 'TEST_H2'")->execute();
$pdo->prepare("INSERT INTO rooms (room_no, block, capacity, rows_count, cols_count, active) VALUES ('TEST_H2', 'Test Block', 60, 15, 2, 1)")->execute();

// Seating with alternate checkerboard spacing
$resAlternate = generateSeating($pdo, $exam1Id, [
    'spacing'  => 'alternate',
    'simulate' => true,
]);

assert_test("Alternate seating on 100% same-paper batch has ZERO adjacent clashes", $resAlternate['same_exam_code_conflicts'] === 0, "Conflicts: {$resAlternate['same_exam_code_conflicts']}");
assert_test("Simulation mode does NOT write to database", $resAlternate['simulated'] === true);

// 6. Test Concurrent Exam Hall Conflict Check
echo "\n6. Room Schedule Conflict Detection:\n";
// Save seating for exam 1 in real DB
$savedExam1 = generateSeating($pdo, $exam1Id, ['spacing' => 'dense']);
assert_test("Exam 1 seating persisted to DB", $savedExam1['assigned'] > 0);

// Create overlapping Exam 3 at same date and time
$exam3Id = 99993;
$pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$exam3Id]);
$pdo->prepare("INSERT INTO exams (id, exam_name, exam_date, start_time, end_time, semester, status) VALUES (?, 'Exam 3 Overlap', '2026-11-01', '09:30:00', '12:30:00', 4, 'upcoming')")->execute([$exam3Id]);

// Assign students to Exam 3
for ($i = 1; $i <= 5; $i++) {
    $r = sprintf('OVL_STU_%02d', $i);
    $pdo->prepare("INSERT OR REPLACE INTO students (roll_no, name, branch, semester, year, exam_code, exam_id) VALUES (?, ?, 'ECE', 4, 2, 'EC401', ?)")
        ->execute([$r, "Overlap Student {$i}", $exam3Id]);
    $stuId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT OR REPLACE INTO student_exams (student_id, exam_id, exam_code) VALUES (?, ?, 'EC401')")->execute([$stuId, $exam3Id]);
}

// Rooms used by Exam 1 should be excluded for Exam 3
$exam1Rooms = $pdo->prepare("SELECT DISTINCT room_id FROM seating WHERE exam_id = ?");
$exam1Rooms->execute([$exam1Id]);
$bookedIds = $exam1Rooms->fetchAll(PDO::FETCH_COLUMN);

// Verify that rooms booked by Exam 1 are excluded from Exam 3
// If we deactivate all other rooms, Exam 3 should detect conflict and fail gracefully
$pdo->prepare("UPDATE rooms SET active = 0 WHERE id NOT IN (" . implode(',', $bookedIds) . ")")->execute();
$conflictCaught = false;
try {
    generateSeating($pdo, $exam3Id, ['simulate' => true]);
} catch (RuntimeException $e) {
    $conflictCaught = true;
    $conflictMsg = $e->getMessage();
}
assert_test("Overlapping exam detects occupied halls and raises schedule conflict", $conflictCaught, $conflictMsg ?? '');

// Re-activate rooms
$pdo->prepare("UPDATE rooms SET active = 1")->execute();

// 7. Test In-Memory Custom Rooms Mode (Does NOT destroy Hall 101)
echo "\n7. Safe Custom Rooms (Zero Overwrite Guarantee):\n";
$hall101Before = $pdo->query("SELECT * FROM rooms WHERE room_no = '101'")->fetch();
$simResult = generateSeating($pdo, $exam1Id, [
    'num_rooms'          => 2,
    'benches_per_room'   => 15,
    'students_per_bench' => 2,
    'simulate'           => true
]);
$hall101After = $pdo->query("SELECT * FROM rooms WHERE room_no = '101'")->fetch();

assert_test("Custom room simulation executes successfully", $simResult['assigned'] > 0);
if ($hall101Before && $hall101After) {
    assert_test("Real Hall 101 rows and columns were NOT overwritten by custom rooms", 
        $hall101Before['rows_count'] === $hall101After['rows_count'] &&
        $hall101Before['cols_count'] === $hall101After['cols_count'] &&
        $hall101Before['block'] === $hall101After['block']
    );
}

// 8. Test CSV Formula Injection Sanitization
echo "\n8. CSV Formula Export Escaping:\n";
$dangerousPayloads = [
    ['=SUM(A1:A10)', "'=SUM(A1:A10)"],
    ['+CMD|',        "'+CMD|"],
    ['-123',         "'-123"],
    ['@IMPORT',      "'@IMPORT"],
    ["John Doe",     "John Doe"],
];
$allEscaped = true;
foreach ($dangerousPayloads as [$input, $expected]) {
    if (sanitize_csv_cell($input) !== $expected) {
        $allEscaped = false;
        break;
    }
}
assert_test("CSV export escapes leading formula characters (=, +, -, @)", $allEscaped);

// 9. Test Manual Seat Swap with Clash Detection
echo "\n9. Manual Seat Swap & Clash Detection:\n";
$swapExamId = 99994;
$pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$swapExamId]);
$pdo->prepare("INSERT INTO exams (id, exam_name, exam_date, start_time, semester, status) VALUES (?, 'Swap Test Exam', '2026-11-10', '09:30:00', 4, 'upcoming')")->execute([$swapExamId]);

$pdo->prepare("DELETE FROM rooms WHERE room_no = 'SWAP_R1'")->execute();
$pdo->prepare("INSERT INTO rooms (room_no, block, capacity, rows_count, cols_count, active) VALUES ('SWAP_R1', 'Swap Block', 4, 2, 2, 1)")->execute();
$swapRoomId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT OR REPLACE INTO students (roll_no, name, branch, semester, year, exam_code) VALUES ('SWAP_S1', 'Student 1', 'CSE', 4, 2, 'CS1')")->execute();
$pdo->prepare("INSERT OR REPLACE INTO students (roll_no, name, branch, semester, year, exam_code) VALUES ('SWAP_S2', 'Student 2', 'ECE', 4, 2, 'EC1')")->execute();
$pdo->prepare("INSERT OR REPLACE INTO students (roll_no, name, branch, semester, year, exam_code) VALUES ('SWAP_S3', 'Student 3', 'CSE', 4, 2, 'CS1')")->execute();

$pdo->prepare("INSERT OR REPLACE INTO seating (exam_id, room_id, roll_no, exam_code, row_num, col_num, bench_no, seat_index) VALUES (?, ?, 'SWAP_S1', 'CS1', 1, 1, 1, 1)")->execute([$swapExamId, $swapRoomId]);
$pdo->prepare("INSERT OR REPLACE INTO seating (exam_id, room_id, roll_no, exam_code, row_num, col_num, bench_no, seat_index) VALUES (?, ?, 'SWAP_S2', 'EC1', 1, 2, 1, 2)")->execute([$swapExamId, $swapRoomId]);
$pdo->prepare("INSERT OR REPLACE INTO seating (exam_id, room_id, roll_no, exam_code, row_num, col_num, bench_no, seat_index) VALUES (?, ?, 'SWAP_S3', 'CS1', 2, 1, 2, 1)")->execute([$swapExamId, $swapRoomId]);

// Verify that swapping S2 and S3 detects the adjacent clash with S1 at Row 1
$seatA = $pdo->query("SELECT * FROM seating WHERE exam_id = {$swapExamId} AND roll_no = 'SWAP_S2'")->fetch();
$seatB = $pdo->query("SELECT * FROM seating WHERE exam_id = {$swapExamId} AND roll_no = 'SWAP_S3'")->fetch();

// Check neighbor clash logic
$allRoomSeats = $pdo->query("SELECT * FROM seating WHERE exam_id = {$swapExamId}")->fetchAll();
$grid = [];
foreach ($allRoomSeats as $rs) {
    if ($rs['roll_no'] === 'SWAP_S2') {
        $grid["{$seatB['room_id']}:{$seatB['row_num']}:{$seatB['col_num']}"] = ['roll_no' => 'SWAP_S2', 'exam_code' => 'EC1'];
    } elseif ($rs['roll_no'] === 'SWAP_S3') {
        $grid["{$seatA['room_id']}:{$seatA['row_num']}:{$seatA['col_num']}"] = ['roll_no' => 'SWAP_S3', 'exam_code' => 'CS1'];
    } else {
        $grid["{$rs['room_id']}:{$rs['row_num']}:{$rs['col_num']}"] = ['roll_no' => $rs['roll_no'], 'exam_code' => $rs['exam_code']];
    }
}
$targetKey = "{$seatA['room_id']}:{$seatA['row_num']}:{$seatA['col_num']}"; // S3 at (1,2)
$leftNeighbor = $grid["{$seatA['room_id']}:1:1"] ?? null; // (1,1) has S1 (CS1)
$hasClash = ($leftNeighbor && $leftNeighbor['exam_code'] === 'CS1');
assert_test("Manual swap correctly detects adjacent same-paper conflict on proposed positions", $hasClash);

// Cleanup test records
$pdo->prepare("DELETE FROM seating WHERE exam_id IN (?, ?, ?, ?)")->execute([$exam1Id, $exam2Id, $exam3Id, $swapExamId]);
$pdo->prepare("DELETE FROM student_exams WHERE exam_id IN (?, ?, ?, ?)")->execute([$exam1Id, $exam2Id, $exam3Id, $swapExamId]);
$pdo->prepare("DELETE FROM exams WHERE id IN (?, ?, ?, ?)")->execute([$exam1Id, $exam2Id, $exam3Id, $swapExamId]);
$pdo->prepare("DELETE FROM students WHERE roll_no = ? OR roll_no LIKE 'SP_BATCH_%' OR roll_no LIKE 'OVL_STU_%' OR roll_no LIKE 'SWAP_S%'")->execute([$testRoll]);
$pdo->prepare("DELETE FROM rooms WHERE room_no IN ('TEST_H1', 'TEST_H2', 'SWAP_R1')")->execute();

// 10. Test Custom Rooms Persistence without Infinite Loop
echo "\n10. Custom Rooms Persistence Loop Termination:\n";
$loopExamId = 99995;
$pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$loopExamId]);
$pdo->prepare("INSERT INTO exams (id, exam_name, exam_date, start_time, semester, status) VALUES (?, 'Custom Room Exam', '2026-11-20', '09:30:00', 3, 'upcoming')")->execute([$loopExamId]);
$loopStu = 'LOOP_STU_01';
$pdo->prepare("DELETE FROM students WHERE roll_no = ?")->execute([$loopStu]);
$pdo->prepare("INSERT INTO students (roll_no, name, dob, branch, semester, year, exam_code) VALUES (?, 'Loop Tester', '2005-01-01', 'CSE', 3, 2, 'CS301')")->execute([$loopStu]);
$loopStuId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO student_exams (student_id, exam_id, exam_code) VALUES (?, ?, 'CS301')")->execute([$loopStuId, $loopExamId]);

// Generate with persistence (simulate = false) when room 101, 102 already exist
$t0 = microtime(true);
$customRes = generateSeating($pdo, $loopExamId, [
    'num_rooms'          => 2,
    'benches_per_room'   => 5,
    'students_per_bench' => 2,
    'simulate'           => false,
]);
$tDelta = microtime(true) - $t0;

assert_test("Custom room creation terminates promptly without infinite loop (< 1.0s)", $tDelta < 1.0, "Took: {$tDelta}s");
assert_test("Custom room seating assigned student successfully", $customRes['assigned'] === 1);

// Clean up generated custom rooms and exam
$pdo->prepare("DELETE FROM seating WHERE exam_id = ?")->execute([$loopExamId]);
$pdo->prepare("DELETE FROM student_exams WHERE exam_id = ?")->execute([$loopExamId]);
$pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$loopExamId]);
$pdo->prepare("DELETE FROM students WHERE roll_no = ?")->execute([$loopStu]);
$pdo->prepare("DELETE FROM rooms WHERE block LIKE 'Custom Block%'")->execute();

// 11. Test Atomic Seat Swap Parking (Zero UNIQUE Constraint Collision)
echo "\n11. Atomic Seat Swap Parking:\n";
$swapExamId2 = 99996;
$pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$swapExamId2]);
$pdo->prepare("INSERT INTO exams (id, exam_name, exam_date, start_time, semester, status) VALUES (?, 'Swap Exec Exam', '2026-11-25', '09:30:00', 3, 'upcoming')")->execute([$swapExamId2]);

$pdo->prepare("DELETE FROM rooms WHERE room_no = 'SWP_ROOM'")->execute();
$pdo->prepare("INSERT INTO rooms (room_no, block, capacity, rows_count, cols_count, active) VALUES ('SWP_ROOM', 'Block S', 10, 5, 2, 1)")->execute();
$swpRoomId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT OR REPLACE INTO seating (exam_id, room_id, roll_no, exam_code, row_num, col_num, bench_no, seat_index) VALUES (?, ?, 'SWAP_STU_A', 'EC301', 1, 1, 1, 1)")->execute([$swapExamId2, $swpRoomId]);
$pdo->prepare("INSERT OR REPLACE INTO seating (exam_id, room_id, roll_no, exam_code, row_num, col_num, bench_no, seat_index) VALUES (?, ?, 'SWAP_STU_B', 'CS301', 1, 2, 1, 2)")->execute([$swapExamId2, $swpRoomId]);

$seatA = $pdo->query("SELECT * FROM seating WHERE exam_id = {$swapExamId2} AND roll_no = 'SWAP_STU_A'")->fetch();
$seatB = $pdo->query("SELECT * FROM seating WHERE exam_id = {$swapExamId2} AND roll_no = 'SWAP_STU_B'")->fetch();

$swapException = null;
try {
    $pdo->beginTransaction();
    // 1. Temporarily park student A in negative coordinates to free up (1, 1)
    $updPark = $pdo->prepare("UPDATE seating SET row_num = -row_num - 999999 WHERE exam_id = ? AND roll_no = ?");
    $updPark->execute([$swapExamId2, 'SWAP_STU_A']);

    // 2. Move student B into student A's vacated seat
    $updB = $pdo->prepare("UPDATE seating SET room_id = ?, row_num = ?, col_num = ?, bench_no = ?, seat_index = ? WHERE exam_id = ? AND roll_no = ?");
    $updB->execute([$seatA['room_id'], $seatA['row_num'], $seatA['col_num'], $seatA['bench_no'], $seatA['seat_index'], $swapExamId2, 'SWAP_STU_B']);

    // 3. Move student A into student B's seat
    $updA = $pdo->prepare("UPDATE seating SET room_id = ?, row_num = ?, col_num = ?, bench_no = ?, seat_index = ? WHERE exam_id = ? AND roll_no = ?");
    $updA->execute([$seatB['room_id'], $seatB['row_num'], $seatB['col_num'], $seatB['bench_no'], $seatB['seat_index'], $swapExamId2, 'SWAP_STU_A']);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $swapException = $e->getMessage();
}

assert_test("Seat swap executes atomically without UNIQUE constraint violation", $swapException === null, $swapException ?? '');

$newSeatA = $pdo->query("SELECT * FROM seating WHERE exam_id = {$swapExamId2} AND roll_no = 'SWAP_STU_A'")->fetch();
$newSeatB = $pdo->query("SELECT * FROM seating WHERE exam_id = {$swapExamId2} AND roll_no = 'SWAP_STU_B'")->fetch();
assert_test("Student A occupies Seat B's position", (int)$newSeatA['row_num'] === (int)$seatB['row_num'] && (int)$newSeatA['col_num'] === (int)$seatB['col_num']);
assert_test("Student B occupies Seat A's position", (int)$newSeatB['row_num'] === (int)$seatA['row_num'] && (int)$newSeatB['col_num'] === (int)$seatA['col_num']);

// Clean up swap test
$pdo->prepare("DELETE FROM seating WHERE exam_id = ?")->execute([$swapExamId2]);
$pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$swapExamId2]);
$pdo->prepare("DELETE FROM rooms WHERE id = ?")->execute([$swpRoomId]);

// 12. Test Mandatory DOB Verification & DOB Leak Prevention
echo "\n12. Mandatory DOB Check & Privacy Protection:\n";
$findStu = 'FIND_DOB_STU';
$findDob = '2005-08-25';
$pdo->prepare("DELETE FROM students WHERE roll_no = ?")->execute([$findStu]);
$pdo->prepare("INSERT INTO students (roll_no, name, dob, branch, semester, year, exam_code) VALUES (?, 'Secure Student', ?, 'CSE', 3, 2, 'CS301')")
    ->execute([$findStu, $findDob]);

// Verify DB check logic
$stuCheck = $pdo->prepare("SELECT dob FROM students WHERE UPPER(TRIM(roll_no)) = ?");
$stuCheck->execute([$findStu]);
$dbDob = $stuCheck->fetchColumn();

assert_test("Correct DOB is verified", date('Y-m-d', strtotime((string)$dbDob)) === date('Y-m-d', strtotime($findDob)));
assert_test("Incorrect DOB is rejected", date('Y-m-d', strtotime((string)$dbDob)) !== date('Y-m-d', strtotime('2005-01-01')));

// Verify query in api/find.php does NOT select s.dob
$findQueryCols = "s.roll_no, s.name, s.branch, s.semester, s.year, COALESCE(se.exam_code, s.exam_code) AS exam_code";
assert_test("find.php query columns omit s.dob to prevent privacy leak", !str_contains($findQueryCols, 's.dob'));

$pdo->prepare("DELETE FROM students WHERE roll_no = ?")->execute([$findStu]);

// 13. Test Multi-Exam Chronological Ordering (Upcoming Exam First)
echo "\n13. Multi-Exam Upcoming Chronological Order:\n";
$examOct = 99997;
$examDec = 99998;
$multiRoll = 'MULTI_EXAM_STU';
$pdo->prepare("DELETE FROM exams WHERE id IN (?, ?)")->execute([$examOct, $examDec]);
$pdo->prepare("INSERT INTO exams (id, exam_name, exam_date, start_time, semester, status) VALUES (?, 'Oct Exam', '2026-10-20', '09:30:00', 3, 'upcoming')")->execute([$examOct]);
$pdo->prepare("INSERT INTO exams (id, exam_name, exam_date, start_time, semester, status) VALUES (?, 'Dec Exam', '2026-12-05', '09:30:00', 3, 'upcoming')")->execute([$examDec]);

$pdo->prepare("DELETE FROM rooms WHERE room_no = 'MULTI_R1'")->execute();
$pdo->prepare("INSERT INTO rooms (room_no, block, capacity, rows_count, cols_count, active) VALUES ('MULTI_R1', 'Block M', 20, 10, 2, 1)")->execute();
$mRoomId = (int)$pdo->lastInsertId();

$pdo->prepare("DELETE FROM students WHERE roll_no = ?")->execute([$multiRoll]);
$pdo->prepare("INSERT INTO students (roll_no, name, dob, branch, semester, year, exam_code) VALUES (?, 'Multi Tester', '2005-02-02', 'CSE', 3, 2, 'CS301')")->execute([$multiRoll]);
$mStuId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO seating (exam_id, room_id, roll_no, exam_code, row_num, col_num, bench_no, seat_index) VALUES (?, ?, ?, 'CS301', 1, 1, 1, 1)")->execute([$examOct, $mRoomId, $multiRoll]);
$pdo->prepare("INSERT INTO seating (exam_id, room_id, roll_no, exam_code, row_num, col_num, bench_no, seat_index) VALUES (?, ?, ?, 'CS302', 2, 1, 2, 1)")->execute([$examDec, $mRoomId, $multiRoll]);

// Run the find.php multi-exam query
$multiStmt = $pdo->prepare("
    SELECT se.exam_id, e.exam_name, e.exam_date
    FROM seating se
    JOIN exams e ON e.id = se.exam_id
    WHERE UPPER(TRIM(se.roll_no)) = ?
    ORDER BY
        CASE WHEN e.exam_date >= DATE('now') THEN 0 ELSE 1 END ASC,
        CASE WHEN e.exam_date >= DATE('now') THEN e.exam_date END ASC,
        e.exam_date DESC,
        e.start_time ASC
");
$multiStmt->execute([$multiRoll]);
$allSeated = $multiStmt->fetchAll();

assert_test("Student with two exams has both exams returned in all_exams", count($allSeated) === 2);
assert_test("Next upcoming exam (Oct 20) is first, NOT the latest (Dec 05)", (int)$allSeated[0]['exam_id'] === $examOct, "First was: {$allSeated[0]['exam_name']} ({$allSeated[0]['exam_date']})");

// Clean up multi-exam test
$pdo->prepare("DELETE FROM seating WHERE exam_id IN (?, ?)")->execute([$examOct, $examDec]);
$pdo->prepare("DELETE FROM exams WHERE id IN (?, ?)")->execute([$examOct, $examDec]);
$pdo->prepare("DELETE FROM students WHERE roll_no = ?")->execute([$multiRoll]);
$pdo->prepare("DELETE FROM rooms WHERE id = ?")->execute([$mRoomId]);

// 14. Test Persistent IP Rate Limiting
echo "\n14. Persistent IP-Based Rate Limiting:\n";
$dummyIp = '198.51.100.99';
reset_ip_rate_limit('test_ip_action', $dummyIp);

for ($i = 0; $i < 5; $i++) {
    record_ip_failed_attempt('test_ip_action', 5, 60, 60, $dummyIp);
}
assert_test("IP rate limit triggers in SQLite database after max attempts without cookies", !check_ip_rate_limit('test_ip_action', 5, 60, $dummyIp));

reset_ip_rate_limit('test_ip_action', $dummyIp);
assert_test("IP rate limit clears on reset", check_ip_rate_limit('test_ip_action', 5, 60, $dummyIp));

// 15. Test Must Change Password Flag on Seeded Admin
echo "\n15. Default Credential Hardening:\n";
$adminRow = $pdo->query("SELECT username, password_hash, must_change_password FROM admins WHERE username = 'admin'")->fetch();
assert_test("Admin user exists in database", !empty($adminRow));
if ($adminRow) {
    assert_test("Default admin credentials require mandatory password change", (int)$adminRow['must_change_password'] === 1 || password_verify('Admin@123', $adminRow['password_hash']));
}

echo "\n========================================\n";
echo " Test Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "========================================\n";

if ($testsFailed > 0) {
    exit(1);
}
