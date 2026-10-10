<?php
/**
 * Authentication, CSRF & Security Guards
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_admin(): bool {
    return !empty($_SESSION['admin_id']);
}

function validate_password_strength(string $password): ?string {
    if (strlen($password) < 8) {
        return 'New password must be at least 8 characters long.';
    }
    if ($password === 'Admin@123') {
        return 'You cannot keep the default password. Please choose a strong, unique password.';
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
    }
    return null;
}

function admin_must_change_password(?int $adminId = null): bool {
    if (!empty($_SESSION['must_change_password'])) {
        return true;
    }
    $adminId = $adminId ?: (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) {
        return false;
    }
    try {
        require_once __DIR__ . '/db.php';
        $stmt = db()->prepare("SELECT must_change_password, password_hash FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $row = $stmt->fetch();
        if ($row && (!empty($row['must_change_password']) || password_verify('Admin@123', (string)$row['password_hash']))) {
            $_SESSION['must_change_password'] = true;
            return true;
        }
    } catch (Throwable) {
    }
    return false;
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: login.php');
        exit;
    }
    if (admin_must_change_password()) {
        $curr = basename($_SERVER['PHP_SELF'] ?? '');
        if ($curr !== 'change_password.php' && $curr !== 'logout.php') {
            header('Location: change_password.php');
            exit;
        }
    }
}

function require_admin_api(): void {
    if (!is_admin()) {
        json_response(['error' => 'Unauthorized — please log in'], 401);
    }
    if (admin_must_change_password()) {
        json_response(['error' => 'Password change required before accessing administration features'], 403);
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($accept, 'application/json') || str_contains($uri, '/api/')) {
            json_response(['error' => 'Invalid or missing CSRF token'], 403);
        }
        exit('CSRF validation failed: Invalid or missing security token.');
    }
}

function get_client_ip(): string {
    $raw = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $parts = explode(',', $raw);
    return trim($parts[0]);
}

function check_ip_rate_limit(string $action, int $maxAttempts = 5, int $windowSeconds = 900, ?string $ip = null): bool {
    require_once __DIR__ . '/db.php';
    $pdo = db();
    $ip = $ip ?: get_client_ip();
    $now = time();

    try {
        $stmt = $pdo->prepare("SELECT attempts, last_attempt, locked_until FROM rate_limits WHERE ip = ? AND action = ?");
        $stmt->execute([$ip, $action]);
        $row = $stmt->fetch();

        if (!$row) {
            return true;
        }

        if ((int)$row['locked_until'] > $now) {
            return false;
        }

        if (($now - (int)$row['last_attempt']) > $windowSeconds) {
            return true;
        }

        return (int)$row['attempts'] < $maxAttempts;
    } catch (Throwable) {
        return true;
    }
}

function record_ip_failed_attempt(string $action, int $maxAttempts = 5, int $windowSeconds = 900, int $lockoutSeconds = 900, ?string $ip = null): void {
    require_once __DIR__ . '/db.php';
    $pdo = db();
    $ip = $ip ?: get_client_ip();
    $now = time();

    try {
        $stmt = $pdo->prepare("SELECT attempts, last_attempt FROM rate_limits WHERE ip = ? AND action = ?");
        $stmt->execute([$ip, $action]);
        $row = $stmt->fetch();

        if (!$row) {
            $ins = $pdo->prepare("INSERT INTO rate_limits (ip, action, attempts, last_attempt, locked_until) VALUES (?, ?, 1, ?, 0)");
            $ins->execute([$ip, $action, $now]);
            return;
        }

        $attempts = (int)$row['attempts'];
        if (($now - (int)$row['last_attempt']) > $windowSeconds) {
            $attempts = 0;
        }
        $attempts++;

        $lockedUntil = 0;
        if ($attempts >= $maxAttempts) {
            $lockedUntil = $now + $lockoutSeconds;
        }

        $upd = $pdo->prepare("UPDATE rate_limits SET attempts = ?, last_attempt = ?, locked_until = ? WHERE ip = ? AND action = ?");
        $upd->execute([$attempts, $now, $lockedUntil, $ip, $action]);
    } catch (Throwable) {
        // Fallback gracefully
    }
}

function reset_ip_rate_limit(string $action, ?string $ip = null): void {
    require_once __DIR__ . '/db.php';
    $pdo = db();
    $ip = $ip ?: get_client_ip();
    try {
        $del = $pdo->prepare("DELETE FROM rate_limits WHERE ip = ? AND action = ?");
        $del->execute([$ip, $action]);
    } catch (Throwable) {
    }
}

function check_login_rate_limit(string $key = 'admin_login', ?string $ip = null): bool {
    return check_ip_rate_limit($key, 5, 900, $ip);
}

function record_failed_login(string $key = 'admin_login', ?string $ip = null): void {
    record_ip_failed_attempt($key, 5, 900, 900, $ip);
}

function reset_login_rate_limit(string $key = 'admin_login', ?string $ip = null): void {
    reset_ip_rate_limit($key, $ip);
}