<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_admin()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $newPass = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    if (strlen($newPass) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($newPass === 'Admin@123') {
        $error = 'You cannot keep the default password. Please choose a strong, unique password.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Passwords do not match. Please verify and retype.';
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $adminId = (int)$_SESSION['admin_id'];

        $upd = db()->prepare("UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        $upd->execute([$hash, $adminId]);

        unset($_SESSION['must_change_password']);
        header('Location: index.php?toast=' . urlencode('Password updated successfully. Default credentials cleared.'));
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change Password · ExamSeat Administration</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/admin.css" rel="stylesheet">
<style>
  body {
    min-height: 100vh;
    display: grid;
    place-items: center;
    background: radial-gradient(120% 120% at 50% 10%, #1e1b4b 0%, #090d16 100%);
    padding: 20px;
  }
  .login-card {
    width: 100%;
    max-width: 440px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    overflow: hidden;
  }
</style>
</head>
<body>
<div class="login-card p-4 p-md-5">
  <div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle mb-3" style="background:#fef2f2; color:#ef4444; width:64px; height:64px;">
      <i class="bi bi-shield-lock-fill fs-2"></i>
    </div>
    <h3 class="fw-bold mb-1">Set New Password</h3>
    <p class="text-muted small">Default credentials detected. For security, please choose a strong administrator password before continuing.</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2 small d-flex align-items-center gap-2 mb-3">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <div><?= htmlspecialchars($error) ?></div>
    </div>
  <?php endif; ?>

  <form method="post" action="change_password.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="mb-3">
      <label class="form-label small fw-semibold">New Password</label>
      <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters" required autofocus minlength="8">
    </div>
    <div class="mb-4">
      <label class="form-label small fw-semibold">Confirm New Password</label>
      <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required minlength="8">
    </div>
    <button class="btn btn-grad w-100 py-2.5 mb-3" type="submit">
      Save Password &amp; Continue →
    </button>
  </form>
</div>
</body>
</html>
