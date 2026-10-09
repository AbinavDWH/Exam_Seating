<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!check_login_rate_limit('admin_login')) {
        $error = 'Too many failed login attempts. Please wait 15 minutes before retrying.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $stmt = db()->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            reset_login_rate_limit('admin_login');
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_user'] = $admin['username'];

            if (!empty($admin['must_change_password']) || password_verify('Admin@123', $admin['password_hash'])) {
                $_SESSION['must_change_password'] = true;
                header('Location: change_password.php');
                exit;
            }

            header('Location: index.php');
            exit;
        }

        record_failed_login('admin_login');
        $error = 'Invalid username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In · ExamSeat Administration</title>
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
    padding: 36px 32px;
  }
</style>
</head>
<body>
<div class="login-card">
  <div class="text-center mb-4">
    <div style="width: 54px; height: 54px; margin: 0 auto 16px; border-radius: 16px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); display: grid; place-items: center; color: #fff; box-shadow: 0 8px 16px rgba(79, 70, 229, 0.3);">
      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
    </div>
    <h4 class="fw-bold mb-1" style="letter-spacing: -0.02em;">ExamSeat Administration</h4>
    <p class="text-muted small mb-0">University Examination &amp; Seating Portal</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2 small rounded-3"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" action="login.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="mb-3">
      <label class="form-label small fw-semibold text-secondary">Username</label>
      <input name="username" class="form-control" required autofocus placeholder="Enter admin username" autocomplete="username">
    </div>
    <div class="mb-4">
      <label class="form-label small fw-semibold text-secondary">Password</label>
      <input name="password" type="password" class="form-control" required placeholder="Enter password" autocomplete="current-password">
    </div>
    <button class="btn btn-grad w-100 py-2 mb-3">Sign In to Dashboard →</button>
  </form>

  <div class="text-center pt-2 border-top">
    <a href="../" class="text-decoration-none small text-muted fw-semibold">
      <i class="bi bi-arrow-left me-1"></i>Open Student Seat Finder
    </a>
  </div>
</div>
</body>
</html>