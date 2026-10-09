<?php
/**
 * Database connection & response helpers
 * Uses SQLite PDO (self-contained, zero-dependency, transactional).
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

        $pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');
    }
    return $pdo;
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

function sanitize_csv_cell(mixed $val): mixed {
    if (is_string($val) && strlen($val) > 0 && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $val;
    }
    return $val;
}