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
    $currentPass = (string)($_POST['current_password'] ?? '');
    $newPass = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    $adminId = (int)$_SESSION['admin_id'];
    $stmt = db()->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($currentPass, (string)$admin['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif ($newPass === $currentPass) {
        $error = 'New password cannot be the same as your current password.';
    } elseif ($strengthErr = validate_password_strength($newPass)) {
        $error = $strengthErr;
    } elseif ($newPass !== $confirmPass) {
        $error = 'Passwords do not match. Please verify and retype.';
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
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
<title>Change password · DeskMap</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="alternate icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="assets/lib/bootstrap.min.css">
<link rel="stylesheet" href="assets/fonts.css">
<link href="assets/admin.css" rel="stylesheet">
<style>
  body {
    min-height: 100vh;
    display: grid;
    place-items: center;
    background: var(--admin-bg, #EAE2D6);
    padding: 20px;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    color: var(--text-main, #2B2E27);
  }
  .change-card {
    width: 100%;
    max-width: 440px;
    border: 1px solid #D8CFBF;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: none;
    padding: 36px 32px;
  }
  .form-control {
    width: 100%;
    height: 44px;
    border: 1px solid #D8CFBF;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.95rem;
    color: #2B2E27;
    background: #ffffff;
    box-sizing: border-box;
  }
  .form-control:focus {
    border-color: #8B9A6E;
    outline: 2px solid #8B9A6E;
    outline-offset: 1px;
  }
  .btn-submit {
    width: 100%;
    height: 48px;
    background: #FFBDA3;
    color: #261B14;
    border: 1px solid #F3A88D;
    border-radius: 8px;
    font-size: 0.98rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s ease;
  }
  .btn-submit:hover {
    background: #F7A384;
  }
  .btn-submit:focus-visible {
    outline: 2px solid #2B2E27;
    outline-offset: 2px;
  }
</style>
</head>
<body>
<div class="change-card">
  <div class="text-center mb-3">
    <img src="assets/deskmap-full.svg" alt="DeskMap" style="width: 80px; height: auto;">
  </div>
  <h2 style="font-family: 'Plus Jakarta Sans', system-ui, sans-serif; font-size: 1.5rem; text-align: center; margin: 0 0 8px; font-weight: 700;">
    <?= !empty($_SESSION['must_change_password']) ? 'Set new password' : 'Change password' ?>
  </h2>
  <p class="text-muted small text-center mb-4" style="color: #6B6F62; font-size: 0.88rem;">
    <?= !empty($_SESSION['must_change_password']) ? 'Default credentials detected. Choose a secure administrator password before continuing.' : 'Enter your current password and choose a secure new password.' ?>
  </p>

  <?php if ($error): ?>
    <div style="background: #F9ECE8; border: 1px solid #F0CFC7; color: #7D3020; border-radius: 8px; padding: 10px 14px; font-size: 0.86rem; margin-bottom: 18px; display: flex; align-items: center; gap: 8px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div><?= htmlspecialchars($error) ?></div>
    </div>
  <?php endif; ?>

  <form method="post" action="change_password.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div style="margin-bottom: 16px;">
      <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Current password</label>
      <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required autofocus>
    </div>
    <div style="margin-bottom: 16px;">
      <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">New password</label>
      <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters" required minlength="8">
      <div style="font-size: 0.76rem; color: #6B6F62; margin-top: 4px;">Must contain at least 8 characters, an uppercase letter, a lowercase letter, and a number.</div>
    </div>
    <div style="margin-bottom: 22px;">
      <label style="display: block; font-size: 0.84rem; font-weight: 700; margin-bottom: 6px;">Confirm new password</label>
      <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required minlength="8">
    </div>
    <button class="btn-submit" type="submit">
      Save changes
    </button>
  </form>
</div>
</body>
</html>
