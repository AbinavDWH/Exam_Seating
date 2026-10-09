<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function is_admin(): bool { return !empty($_SESSION['admin_id']); }

function require_admin(): void {           // for admin pages
    if (!is_admin()) { header('Location: login.php'); exit; }
}

function require_admin_api(): void {       // for admin API endpoints
    if (!is_admin()) json_response(['error' => 'Unauthorized — please log in'], 401);
}