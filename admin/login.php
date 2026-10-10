<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    if (!check_login_rate_limit('admin_login')) {
        $error = 'Too many tries. Wait a minute and try again.';
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

                if (!empty($_POST['remember'])) {
                    // Extend session cookie lifetime to 30 days if remember is checked
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        session_id(),
                        time() + (30 * 86400),
                        $params['path'],
                        $params['domain'],
                        $params['secure'],
                        $params['httponly']
                    );
                }

                if (!empty($admin['must_change_password']) || password_verify('Admin@123', $admin['password_hash'])) {
                    $_SESSION['must_change_password'] = true;
                    header('Location: change_password.php');
                    exit;
                }

                header('Location: index.php');
                exit;
            }

            record_failed_login('admin_login');
            $error = 'We couldn’t sign you in. Check your username and password and try again.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · DeskMap</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="alternate icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="assets/lib/bootstrap.min.css">
<link rel="stylesheet" href="assets/lib/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/fonts.css">
<link href="assets/admin.css" rel="stylesheet">
<style>
  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--admin-bg, #EAE2D6);
    margin: 0;
    padding: 24px 16px;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    color: var(--text-main, #2B2E27);
  }

  .login-wrapper {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
  }

  .login-card {
    width: 100%;
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #D8CFBF;
    box-shadow: none;
    padding: 36px 32px 32px;
  }

  .login-logo-wrap {
    text-align: center;
    margin-bottom: 20px;
  }

  .login-logo-img {
    width: 96px;
    height: auto;
    display: inline-block;
  }

  .login-title {
    font-family: 'Plus Jakarta Sans', system-ui, sans-serif; font-weight: 700;
    font-size: 1.65rem;
    font-weight: 400;
    letter-spacing: -0.01em;
    color: #2B2E27;
    margin: 0 0 6px;
    text-align: center;
  }

  .login-subtitle {
    font-size: 0.88rem;
    color: #6B6F62;
    font-weight: 500;
    margin-bottom: 24px;
    text-align: center;
  }

  /* Muted brick red error box inside card */
  .login-error-alert {
    background: #F9ECE8;
    border: 1px solid #F0CFC7;
    color: #7D3020;
    border-radius: 12px;
    padding: 12px 14px;
    font-size: 0.88rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    animation: fadeIn 0.2s ease-out;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* Form groups with labels cleanly above inputs */
  .form-group-custom {
    margin-bottom: 18px;
  }

  .form-label-custom {
    display: block;
    font-size: 0.84rem;
    font-weight: 700;
    color: #2B2E27;
    margin-bottom: 6px;
    letter-spacing: 0.01em;
  }

  /* Full-width inputs with pure white background */
  .form-control-custom {
    width: 100%;
    height: 48px;
    border: 1.5px solid #EAE2D6;
    border-radius: 12px;
    padding: 10px 14px;
    font-size: 0.95rem;
    font-weight: 500;
    color: #2B2E27;
    background-color: #ffffff;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }

  .form-control-custom:focus {
    border-color: #8B9A6E;
    box-shadow: 0 0 0 3px rgba(139, 154, 110, 0.28);
    outline: none;
  }

  /* Slightly darkened placeholder for high contrast */
  .form-control-custom::placeholder {
    color: #8A8E81;
    font-weight: 400;
    opacity: 1;
  }

  /* Password input with show/hide toggle */
  .password-input-wrap {
    position: relative;
    width: 100%;
  }

  .password-input-wrap .form-control-custom {
    padding-right: 46px;
  }

  .password-toggle-btn {
    position: absolute;
    right: 0;
    top: 0;
    height: 100%;
    width: 44px;
    background: transparent;
    border: none;
    display: grid;
    place-items: center;
    color: #8A8E81;
    cursor: pointer;
    font-size: 1.15rem;
    padding: 0;
    border-radius: 0 12px 12px 0;
    transition: color 0.15s ease;
  }

  .password-toggle-btn:hover {
    color: #8B9A6E;
  }

  .password-toggle-btn:focus-visible {
    outline: 2px solid #8B9A6E;
    outline-offset: -2px;
  }

  /* Caps Lock Warning (Mustard) */
  .caps-warning {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #8C6514;
    background: #FAF3E1;
    border: 1px solid #F3E4BA;
    border-radius: 8px;
    padding: 5px 10px;
    margin-top: 6px;
  }

  /* Remember this device checkbox */
  .form-check-custom {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 22px;
    margin-top: 4px;
  }

  .form-check-custom input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #6D7C55;
    cursor: pointer;
    border-radius: 4px;
  }

  .form-check-custom label {
    font-size: 0.84rem;
    color: #4D5247;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
  }

  /* Full-width submit button in #FFBDA3 */
  .btn-submit-login {
    width: 100%;
    height: 48px;
    background: #FFBDA3;
    color: #261B14;
    border: 1px solid #F3A88D;
    border-radius: 12px;
    font-size: 0.98rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 14px rgba(234, 120, 80, 0.25);
    transition: all 0.18s ease;
  }

  .btn-submit-login:hover {
    background: #F7A384;
    border-color: #EE9572;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(234, 120, 80, 0.35);
  }

  .btn-submit-login:active {
    background: #EE8F6A;
    transform: translateY(0);
  }

  .btn-submit-login:focus-visible {
    outline: 2px solid #FFBDA3;
    outline-offset: 2px;
  }

  .btn-submit-login:disabled {
    opacity: 0.75;
    cursor: not-allowed;
    transform: none;
  }

  /* Demo viva hint */
  .demo-login-hint {
    text-align: center;
    font-size: 0.8rem;
    color: #6B6F62;
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px dashed #EAE2D6;
  }

  .demo-login-hint code {
    background: #FAF6F0;
    color: #2B2E27;
    padding: 2px 6px;
    border-radius: 6px;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', system-ui, sans-serif; font-variant-numeric: tabular-nums;
    font-size: 0.78rem;
  }

  /* Open Student Seat Finder Link below the card */
  .login-footer {
    text-align: center;
    margin-top: 20px;
  }

  .student-portal-link {
    color: #D8D3CA;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: color 0.15s ease, transform 0.15s ease;
  }

  .student-portal-link:hover {
    color: #C2D1A8;
    text-decoration: none;
    transform: translateX(-3px);
  }

  .student-portal-link:focus-visible {
    outline: 2px solid #8B9A6E;
    outline-offset: 4px;
    border-radius: 4px;
  }
</style>
</head>
<body>
<div class="login-wrapper">
  <div class="login-card">
    <!-- Centered Brand Logo & Headings -->
    <div class="login-logo-wrap">
      <img src="assets/deskmap-full.svg" alt="DeskMap" class="login-logo-img">
    </div>
    <h1 class="login-title">Sign in to DeskMap</h1>
    <p class="login-subtitle">University examination seating portal</p>

    <!-- Error Alert Box -->
    <?php if ($error): ?>
      <div class="login-error-alert" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="post" action="login.php" id="login-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-group-custom">
        <label for="username" class="form-label-custom">Username</label>
        <input
          id="username"
          name="username"
          type="text"
          class="form-control-custom"
          required
          autofocus
          placeholder="Enter admin username"
          autocomplete="username"
        >
      </div>

      <div class="form-group-custom">
        <label for="password" class="form-label-custom">Password</label>
        <div class="password-input-wrap">
          <input
            id="password"
            name="password"
            type="password"
            class="form-control-custom"
            required
            placeholder="Enter password"
            autocomplete="current-password"
          >
          <button
            type="button"
            class="password-toggle-btn"
            id="toggle-password-btn"
            aria-label="Toggle password visibility"
            tabindex="-1"
          >
            <i class="bi bi-eye" id="toggle-password-icon"></i>
          </button>
        </div>
        <!-- Caps Lock Indicator -->
        <div id="caps-warning" class="caps-warning d-none" role="status" aria-live="polite">
          <i class="bi bi-capslock-fill" aria-hidden="true"></i> Caps Lock is ON
        </div>
      </div>

      <!-- Remember this device -->
      <div class="form-check-custom">
        <input type="checkbox" id="remember" name="remember">
        <label for="remember">Remember this device</label>
      </div>

      <!-- Full-Width Submit Button -->
      <button type="submit" class="btn-submit-login" id="submit-btn">
        <span id="btn-text">Sign In to Dashboard →</span>
      </button>

      <!-- Demo viva credentials hint -->
      <div class="demo-login-hint">
        <i class="bi bi-info-circle me-1" aria-hidden="true"></i> Demo login: <code>admin</code> / <code>Admin@123</code>
      </div>
    </form>
  </div>

  <!-- Open Student Seat Finder Link Below Card -->
  <div class="login-footer">
    <a href="../" class="student-portal-link" aria-label="Return to Student Seat Finder">
      <i class="bi bi-arrow-left" aria-hidden="true"></i> Open Student Seat Finder
    </a>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const pwdInput = document.getElementById('password');
  const toggleBtn = document.getElementById('toggle-password-btn');
  const toggleIcon = document.getElementById('toggle-password-icon');
  const capsWarning = document.getElementById('caps-warning');
  const loginForm = document.getElementById('login-form');
  const submitBtn = document.getElementById('submit-btn');

  // 1. Show/hide password toggle
  if (toggleBtn && pwdInput) {
    toggleBtn.addEventListener('click', () => {
      const isPassword = pwdInput.type === 'password';
      pwdInput.type = isPassword ? 'text' : 'password';
      toggleIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
      toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
      pwdInput.focus();
    });
  }

  // 2. Caps Lock warning
  if (pwdInput && capsWarning) {
    ['keydown', 'keyup'].forEach((evt) => {
      pwdInput.addEventListener(evt, (e) => {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
          capsWarning.classList.remove('d-none');
        } else {
          capsWarning.classList.add('d-none');
        }
      });
    });
    pwdInput.addEventListener('blur', () => {
      capsWarning.classList.add('d-none');
    });
  }

  // 3. Spinner and disable button on submit
  if (loginForm && submitBtn) {
    loginForm.addEventListener('submit', () => {
      submitBtn.disabled = true;
      submitBtn.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Signing in…
      `;
    });
  }
});
</script>
</body>
</html>