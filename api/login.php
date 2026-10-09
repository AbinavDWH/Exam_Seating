<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

if (!check_login_rate_limit('api_login')) {
    json_response(['error' => 'Too many failed login attempts. Please wait 15 minutes before retrying.'], 429);
}

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$username = trim((string)($in['username'] ?? ''));
$password = (string)($in['password'] ?? '');
if ($username === '' || $password === '') {
    json_response(['error' => 'Username and password required'], 400);
}

$stmt = db()->prepare("SELECT * FROM admins WHERE username = ?");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, (string)$admin['password_hash'])) {
    record_failed_login('api_login');
    json_response(['error' => 'Invalid credentials'], 401);
}

reset_login_rate_limit('api_login');
session_regenerate_id(true);
$_SESSION['admin_id']  = $admin['id'];
$_SESSION['admin_user'] = $admin['username'];

json_response([
    'success' => true,
    'data' => [
        'username' => $admin['username'],
        'role' => $admin['role'] ?? 'admin',
        'csrf_token' => csrf_token(),
    ],
]);