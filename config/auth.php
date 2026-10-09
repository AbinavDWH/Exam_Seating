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

function require_admin(): void {
    if (!is_admin()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin_api(): void {
    if (!is_admin()) {
        json_response(['error' => 'Unauthorized — please log in'], 401);
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

function check_login_rate_limit(string $key = 'admin_login'): bool {
    $now = time();
    $attempts = $_SESSION['login_rate_' . $key] ?? [];
    $attempts = array_filter($attempts, fn($t) => ($now - $t) < 900);
    $_SESSION['login_rate_' . $key] = $attempts;
    return count($attempts) < 5;
}

function record_failed_login(string $key = 'admin_login'): void {
    $now = time();
    $attempts = $_SESSION['login_rate_' . $key] ?? [];
    $attempts[] = $now;
    $_SESSION['login_rate_' . $key] = $attempts;
}

function reset_login_rate_limit(string $key = 'admin_login'): void {
    unset($_SESSION['login_rate_' . $key]);
}