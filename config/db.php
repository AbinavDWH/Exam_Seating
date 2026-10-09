<?php
/**
 * Database connection & response helpers
 * Supports SQLite (default, self-contained, no sudo/daemon required)
 * and optional MySQL via environment variables.
 */
declare(strict_types=1);

define('DB_FILE', dirname(__DIR__) . '/database/examseat.sqlite');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dbFile = DB_FILE;
        $dir = dirname($dbFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $driver = getenv('DB_DRIVER') ?: 'sqlite';
        if ($driver === 'mysql') {
            $host = getenv('DB_HOST') ?: 'localhost';
            $name = getenv('DB_NAME') ?: 'examseat';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } else {
            $pdo = new PDO('sqlite:' . $dbFile, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA busy_timeout = 5000;');
        }
        ensure_schema_migrations($pdo);
    }
    return $pdo;
}

/**
 * Automatically ensures exam_code column exists in students and seating tables
 */
function ensure_schema_migrations(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        // 1. Ensure students.exam_code exists
        $stuCols = $pdo->query("PRAGMA table_info(students)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('exam_code', $stuCols, true)) {
            $pdo->exec("ALTER TABLE students ADD COLUMN exam_code VARCHAR(50);");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_students_exam_code ON students(exam_code);");
        }

        // 2. Ensure seating.exam_code exists
        $seatCols = $pdo->query("PRAGMA table_info(seating)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('exam_code', $seatCols, true)) {
            $pdo->exec("ALTER TABLE seating ADD COLUMN exam_code VARCHAR(50);");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_seating_exam_code ON seating(exam_code);");
        }

        // 3. Backfill any empty exam_code in students
        $pdo->exec("
            UPDATE students
            SET exam_code = CASE
                WHEN branch = 'CSE' AND semester = 3 THEN 'CS3301'
                WHEN branch = 'ECE' AND semester = 3 THEN 'EC3301'
                WHEN branch = 'MECH' AND semester = 3 THEN 'ME3301'
                WHEN branch = 'CIVIL' AND semester = 3 THEN 'CE3301'
                WHEN branch = 'CSE' AND semester = 5 THEN 'CS3501'
                WHEN branch = 'IT' AND semester = 5 THEN 'IT3501'
                WHEN branch = 'AIDS' AND semester = 5 THEN 'AD3501'
                WHEN branch = 'ECE' AND semester = 7 THEN 'EC3701'
                WHEN branch = 'IT' AND semester = 7 THEN 'IT3701'
                ELSE branch || '-S' || semester
            END
            WHERE exam_code IS NULL OR exam_code = '';
        ");
    } catch (Throwable $e) {
        // Silently skip if table does not exist yet (during bootstrap)
    }
}

// CORS preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    http_response_code(204);
    exit;
}

function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('json_out')) {
    function json_out(mixed $data, int $code = 200): never {
        json_response($data, $code);
        exit;
    }
}