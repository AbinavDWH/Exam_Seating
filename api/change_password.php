<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

if (!is_admin()) {
    json_response(['error' => 'Unauthorized — please log in'], 401);
}

verify_csrf();

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$currentPass = (string)($in['current_password'] ?? '');
$newPass = (string)($in['new_password'] ?? '');
$confirmPass = (string)($in['confirm_password'] ?? '');

$adminId = (int)$_SESSION['admin_id'];
$stmt = db()->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($currentPass, (string)$admin['password_hash'])) {
    json_response(['error' => 'Current password is incorrect.'], 400);
}
if ($newPass === $currentPass) {
    json_response(['error' => 'New password cannot be the same as your current password.'], 400);
}
if ($strengthErr = validate_password_strength($newPass)) {
    json_response(['error' => $strengthErr], 400);
}
if ($newPass !== $confirmPass) {
    json_response(['error' => 'Passwords do not match. Please verify and retype.'], 400);
}

$hash = password_hash($newPass, PASSWORD_DEFAULT);
$upd = db()->prepare("UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?");
$upd->execute([$hash, $adminId]);

unset($_SESSION['must_change_password']);

json_response([
    'success' => true,
    'message' => 'Password updated successfully.',
]);
