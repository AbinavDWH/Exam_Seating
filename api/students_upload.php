<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_admin_api();

$rows = [];
if (!empty($_FILES['file']['tmp_name'])) {
    $fh = fopen($_FILES['file']['tmp_name'], 'r');
    if ($fh) {
        $firstLine = fgetcsv($fh);
        if ($firstLine !== false) {
            // Check if first row is a header row
            $firstCol = strtolower(trim((string)$firstLine[0]));
            $secondCol = strtolower(trim((string)($firstLine[1] ?? '')));
            $isHeader = str_contains($firstCol, 'roll') || str_contains($secondCol, 'name');

            if (!$isHeader && count($firstLine) >= 3) {
                // First line is actually student data!
                $rows[] = parse_csv_student_line($firstLine);
            }

            while (($line = fgetcsv($fh)) !== false) {
                if (count($line) >= 3) {
                    $rows[] = parse_csv_student_line($line);
                }
            }
        }
        fclose($fh);
    }
} else {
    $in = json_decode(file_get_contents('php://input'), true);
    $rows = $in['students'] ?? [];
}

function parse_csv_student_line(array $line): array {
    $sem = isset($line[3]) && trim((string)$line[3]) !== '' ? (int)$line[3] : 1;
    return [
        'roll_no'   => strtoupper(trim((string)$line[0])),
        'name'      => trim((string)$line[1]),
        'branch'    => strtoupper(trim((string)$line[2])),
        'semester'  => $sem,
        'exam_id'   => isset($line[4]) && trim((string)$line[4]) !== '' ? (int)$line[4] : null,
        'exam_code' => isset($line[5]) && trim((string)$line[5]) !== '' ? trim((string)$line[5]) : null,
        'dob'       => isset($line[6]) && trim((string)$line[6]) !== '' ? trim((string)$line[6]) : null,
    ];
}

if (!$rows) {
    json_response(['error' => 'No student rows found in uploaded file'], 400);
}

$pdo = db();
$stmt = $pdo->prepare("
    INSERT INTO students (roll_no, name, dob, branch, dept, semester, year, exam_code, exam_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON CONFLICT(roll_no) DO UPDATE SET
        name = excluded.name,
        dob = excluded.dob,
        branch = excluded.branch,
        dept = excluded.dept,
        semester = excluded.semester,
        year = excluded.year,
        exam_code = COALESCE(NULLIF(excluded.exam_code, ''), students.exam_code, excluded.branch || '-S' || excluded.semester),
        exam_id = COALESCE(excluded.exam_id, students.exam_id)
");

$linkStmt = $pdo->prepare("
    INSERT INTO student_exams (student_id, exam_id, exam_code)
    VALUES (?, ?, ?)
    ON CONFLICT(student_id, exam_id) DO UPDATE SET
        exam_code = COALESCE(excluded.exam_code, student_exams.exam_code)
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
        $roll = strtoupper(trim((string)$r['roll_no']));
        $name = trim((string)$r['name']);
        $branch = strtoupper(trim((string)$r['branch']));
        $sem = (int)($r['semester'] ?? 1);
        if ($sem < 1 || $sem > 8) {
            $skipped++;
            continue;
        }
        $yr = (int)ceil($sem / 2);

        // Optional Date of Birth
        $dobRaw = !empty($r['dob']) ? trim((string)$r['dob']) : '';
        $dob = ($dobRaw !== '' && strtotime($dobRaw) !== false) ? date('Y-m-d', (int)strtotime($dobRaw)) : null;

        $examCode = !empty($r['exam_code']) ? trim((string)$r['exam_code']) : null;
        $examId = !empty($r['exam_id']) ? (int)$r['exam_id'] : null;

        $stmt->execute([
            $roll,
            $name,
            $dob,
            $branch,
            $branch,
            $sem,
            $yr,
            $examCode,
            $examId,
        ]);

        if ($examId) {
            $stuId = (int)$pdo->query("SELECT id FROM students WHERE roll_no = " . $pdo->quote($roll))->fetchColumn();
            if ($stuId > 0) {
                $codeForLink = $examCode ?: ($branch . '-S' . $sem);
                $linkStmt->execute([$stuId, $examId, $codeForLink]);
            }
        }
        $inserted++;
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['error' => 'Upload failed: ' . $e->getMessage()], 500);
}

json_response(['success' => true, 'data' => ['processed' => $inserted, 'skipped' => $skipped]]);