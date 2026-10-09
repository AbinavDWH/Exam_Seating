<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_admin_api();

$rows = [];
if (!empty($_FILES['file']['tmp_name'])) {                 // CSV upload
    $fh = fopen($_FILES['file']['tmp_name'], 'r');
    $header = fgetcsv($fh);                                 // skip header
    while (($line = fgetcsv($fh)) !== false) {
        if (count($line) >= 4) {
            $rows[] = [
                'roll_no'   => trim($line[0]),
                'name'      => trim($line[1]),
                'branch'    => strtoupper(trim($line[2])),
                'semester'  => (int)$line[3],
                'exam_id'   => isset($line[4]) && $line[4] !== '' ? (int)$line[4] : null,
                'exam_code' => isset($line[5]) && $line[5] !== '' ? trim($line[5]) : null,
            ];
        }
    }
    fclose($fh);
} else {                                                    // JSON body
    $in = json_decode(file_get_contents('php://input'), true);
    $rows = $in['students'] ?? [];
}
if (!$rows) {
    json_response(['error' => 'No student rows found'], 400);
}

$pdo = db();
$stmt = $pdo->prepare("
    INSERT INTO students (roll_no, name, branch, dept, semester, year, exam_code, exam_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ON CONFLICT(roll_no) DO UPDATE SET
        name = excluded.name,
        branch = excluded.branch,
        dept = excluded.dept,
        semester = excluded.semester,
        year = excluded.year,
        exam_code = excluded.exam_code,
        exam_id = excluded.exam_id
");

$inserted = 0;
$skipped = 0;
$pdo->beginTransaction();
try {
    foreach ($rows as $r) {
        if (empty($r['roll_no']) || empty($r['name']) || empty($r['branch'])) {
            $skipped++;
            continue;
        }
        $branch = strtoupper(trim((string)$r['branch']));
        $sem = (int)($r['semester'] ?? 1);
        $yr = (int)ceil($sem / 2);
        $examCode = trim((string)($r['exam_code'] ?? ''));
        if ($examCode === '') {
            $examCode = $branch . '-S' . $sem;
        }

        $stmt->execute([
            trim((string)$r['roll_no']),
            trim((string)$r['name']),
            $branch,
            $branch,
            $sem,
            $yr,
            $examCode,
            !empty($r['exam_id']) ? (int)$r['exam_id'] : null,
        ]);
        $inserted++;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(['error' => 'Upload failed: ' . $e->getMessage()], 500);
}

json_response(['success' => true, 'data' => ['processed' => $inserted, 'skipped' => $skipped]]);