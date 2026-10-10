<?php
// One-command DB bootstrap: php database/init.php
$dbFile = __DIR__ . '/examseat.sqlite';
$sqlFile = __DIR__ . '/examseat.sql';

if (!file_exists($sqlFile)) {
    exit("❌ Error: examseat.sql not found at $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

try {
    // If resetting DB, remove previous sqlite file
    if (file_exists($dbFile)) {
        unlink($dbFile);
    }
    
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec($sql);

    // Ensure student_exams is populated for any students with exam_id
    $pdo->exec("INSERT OR IGNORE INTO student_exams (student_id, exam_id, exam_code)
                SELECT id, exam_id, exam_code FROM students WHERE exam_id IS NOT NULL;");

    // Automatically load the university hall plan CSV dataset
    $importScript = __DIR__ . '/import_csv_dataset.py';
    if (file_exists($importScript)) {
        passthru('python3 ' . escapeshellarg($importScript));
    }

    echo "✅ Database initialized successfully at database/examseat.sqlite\n";
} catch (PDOException $e) {
    exit("❌ Init failed: " . $e->getMessage() . "\n");
}