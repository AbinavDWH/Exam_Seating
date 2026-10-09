<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);
$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$username = trim($in['username'] ?? '');
$password = $in['password'] ?? '';
if ($username === '' || $password === '') json_response(['error' => 'Username and password required'], 400);

$stmt = db()->prepare("SELECT * FROM admins WHERE username = ?");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    json_response(['error' => 'Invalid credentials'], 401);
}
$_SESSION['admin_id']  = $admin['id'];
$_SESSION['admin_user'] = $admin['username'];
json_response(['success' => true, 'data' => ['username' => $admin['username'], 'role' => $admin['role']]]);