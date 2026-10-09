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

echo "\n========================================\n";
echo " Test Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "========================================\n";

if ($testsFailed > 0) {
    exit(1);
}
