<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/icons.php';
require_admin();

$currentPage = basename($_SERVER['PHP_SELF']);
$nav = [
  'index.php'    => ['dashboard',     'Dashboard'],
  'students.php' => ['users',         'Students & Cohorts'],
  'rooms.php'    => ['building',      'Halls & Desks'],
  'exams.php'    => ['calendar',      'Exam Sessions'],
  'generate.php' => ['magic',         'Generate Seating'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> · ExamSeat University Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/admin.css" rel="stylesheet">
</head>
<body>
<div class="admin-shell">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-icon"><?= svg_icon('cap', '', 20) ?></div>
      <span class="brand-name">ExamSeat</span>
      <span class="brand-badge">CELL</span>
    </div>
    <nav>
      <?php foreach ($nav as $file => [$icon, $label]): ?>
        <a href="<?= $file ?>" class="<?= $currentPage === $file ? 'active' : '' ?>">
          <?= svg_icon($icon, 'me-2.5 flex-shrink-0', 18) ?><span><?= $label ?></span>
        </a>
      <?php endforeach; ?>

      <div class="sidebar-divider"></div>

      <a href="http://localhost:5173" target="_blank" class="portal-link">
        <?= svg_icon('box-arrow-up-right', 'me-2.5 flex-shrink-0', 16) ?><span>Student Portal</span>
      </a>

      <a href="logout.php" class="logout mt-auto">
        <?= svg_icon('box-arrow-right', 'me-2.5 flex-shrink-0', 16) ?><span>Sign Out</span>
      </a>
    </nav>
  </aside>
  <main class="content">
    <div class="content-inner">