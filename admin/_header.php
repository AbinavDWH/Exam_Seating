<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/icons.php';
require_admin();

$currentPage = basename($_SERVER['PHP_SELF']);
$studentPortalUrl = getenv('STUDENT_PORTAL_URL') ?: '../';

$nav = [
  'index.php'    => ['dashboard',     'Dashboard'],
  'students.php' => ['users',         'Students & Cohorts'],
  'rooms.php'    => ['building',      'Halls & Desks'],
  'exams.php'    => ['calendar',      'Exam Sessions'],
  'generate.php' => ['magic',         'Generate Seating'],
  'swap.php'     => ['sort',          'Seat Swap'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> · ExamSeat University Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/admin.css" rel="stylesheet">
</head>
<body>
<div class="admin-shell">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-icon"><?= svg_icon('seat-grid', '', 20) ?></div>
      <span class="brand-name">ExamSeat</span>
      <span class="brand-badge">CELL</span>
    </div>
    <nav>
      <?php foreach ($nav as $file => [$icon, $label]): ?>
        <a href="<?= $file ?>" class="<?= $currentPage === $file ? 'active' : '' ?>">
          <span class="sidebar-icon-cell"><?= svg_icon($icon, '', 18) ?></span>
          <span class="sidebar-label"><?= $label ?></span>
        </a>
      <?php endforeach; ?>

      <div class="sidebar-divider"></div>

      <a href="<?= htmlspecialchars($studentPortalUrl) ?>" target="_blank" class="portal-link">
        <span class="sidebar-icon-cell"><?= svg_icon('box-arrow-up-right', '', 16) ?></span>
        <span class="sidebar-label">Student Portal</span>
      </a>

      <!-- Logged-in User Profile (Point 10, 13) -->
      <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['admin_user'] ?? 'A', 0, 1)) ?></div>
        <div class="user-meta">
          <span class="user-name"><?= htmlspecialchars($_SESSION['admin_user'] ?? 'admin') ?></span>
          <span class="user-role">Exam Cell</span>
        </div>
      </div>

      <!-- Sign Out ghost button (Point 8, 14) -->
      <form method="post" action="logout.php" class="w-100">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <button type="submit" class="logout w-100">
          <span class="sidebar-icon-cell"><?= svg_icon('box-arrow-right', '', 16) ?></span>
          <span class="sidebar-label">Sign Out</span>
        </button>
      </form>
    </nav>
  </aside>
  <main class="content">
    <div class="content-inner">