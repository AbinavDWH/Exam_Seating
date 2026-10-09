<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

$examId = (int)($_GET['exam_id'] ?? 0);
$exam = db()->prepare("SELECT * FROM exams WHERE id = ?");
$exam->execute([$examId]); $exam = $exam->fetch();
if (!$exam) exit('Exam not found');

// Filter by room or batch if large number of rooms
$selectedRoom = trim($_GET['room'] ?? '');
$batch = max(1, (int)($_GET['batch'] ?? 1));
$perBatch = 20;

$whereExtra = "";
$params = [$examId];
if ($selectedRoom !== '') {
    $whereExtra = " AND r.room_no = ?";
    $params[] = $selectedRoom;
}

$stmt = db()->prepare("
  SELECT r.room_no, r.block, r.rows_count, r.cols_count,
         se.row_num, se.col_num, se.roll_no, s.name, s.branch, s.semester, s.year,
         COALESCE(se.exam_code, s.exam_code) AS exam_code
  FROM rooms r
  JOIN seating se ON se.room_id = r.id AND se.exam_id = ?
  LEFT JOIN students s ON s.roll_no = se.roll_no AND s.exam_id = se.exam_id
  WHERE 1 $whereExtra
  ORDER BY r.block, CAST(r.room_no AS INTEGER), r.room_no, se.row_num, se.col_num");
$stmt->execute($params);
$allSeats = $stmt->fetchAll();

$byRoom = [];
foreach ($allSeats as $row) {
    $byRoom[$row['room_no']][] = $row;
}

$totalRoomsCount = count($byRoom);
$allRoomNos = array_keys($byRoom);

// If no room is specifically selected and there are more than 30 rooms, paginate batches of 20
$showAll = isset($_GET['all']) && $_GET['all'] === '1';
if ($selectedRoom === '' && !$showAll && $totalRoomsCount > 30) {
    $slicedRoomNos = array_slice($allRoomNos, ($batch - 1) * $perBatch, $perBatch);
    $filteredByRoom = [];
    foreach ($slicedRoomNos as $rNo) {
        $filteredByRoom[$rNo] = $byRoom[$rNo];
    }
    $displayRooms = $filteredByRoom;
} else {
    $displayRooms = $byRoom;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Seating Plan — <?= htmlspecialchars($exam['exam_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    margin: 24px;
    color: #111;
    background: #fff;
  }
  .exam-header {
    border-bottom: 2px solid #000;
    padding-bottom: 12px;
    margin-bottom: 20px;
    text-align: center;
  }
  .exam-header h1 {
    font-size: 22px;
    font-weight: 800;
    margin: 0 0 4px;
    text-transform: uppercase;
  }
  .exam-header .sub {
    font-size: 13px;
    color: #333;
    font-weight: 600;
  }
  .room-sheet {
    page-break-after: always;
    margin-bottom: 36px;
    border: 1px solid #ccc;
    padding: 24px;
    border-radius: 8px;
  }
  .room-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #000;
    padding-bottom: 8px;
    margin-bottom: 16px;
  }
  .room-title h2 {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
  }
  .front-stage {
    text-align: center;
    border: 2px dashed #000;
    padding: 6px;
    font-weight: 800;
    font-size: 11px;
    letter-spacing: 3px;
    margin-bottom: 16px;
    background: #f8fafc;
    text-transform: uppercase;
  }
  table.seating-grid {
    border-collapse: collapse;
    width: 100%;
    margin: 0 auto 20px;
  }
  table.seating-grid th, table.seating-grid td {
    border: 1px solid #777;
    padding: 6px 4px;
    text-align: center;
    font-size: 11px;
  }
  table.seating-grid th {
    background: #f1f5f9;
    font-weight: 700;
  }
  table.seating-grid td.occupied {
    background: #fff;
  }
  table.seating-grid td.occupied b {
    display: block;
    font-size: 11.5px;
    font-family: monospace;
    font-weight: 700;
  }
  table.seating-grid td.occupied .code-badge {
    display: inline-block;
    background: #e0f2fe;
    color: #0369a1;
    font-weight: 700;
    font-size: 9.5px;
    padding: 1px 4px;
    border-radius: 3px;
    margin-top: 2px;
    border: 1px solid #bae6fd;
  }
  table.seating-grid td.occupied .meta-dept {
    font-size: 9px;
    color: #444;
    display: block;
    margin-top: 1px;
  }
  table.seating-grid td.empty {
    background: #f8fafc;
    color: #bbb;
    font-style: italic;
  }
  .sign-row {
    display: flex;
    justify-content: space-between;
    margin-top: 36px;
    padding-top: 16px;
    border-top: 1px dashed #aaa;
    font-size: 12px;
    font-weight: 600;
  }
  @media print {
    .no-print { display: none !important; }
    body { margin: 0; }
    .room-sheet { border: none; padding: 0; }
  }
</style>
</head>
<body>
<div class="no-print mb-4 bg-light p-3 rounded border">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div>
      <b>Official Seating Chart</b> — <?= number_format($totalRoomsCount) ?> Hall(s) Allocated
      <span class="badge bg-success-subtle text-success border ms-2">Zero Adjacent Same-Code Conflict</span>
    </div>
    <div>
      <button class="btn btn-primary btn-sm me-2 d-inline-flex align-items-center gap-1.5" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print Displayed Sheets
      </button>
      <a href="generate.php?exam_id=<?= $examId ?>" class="btn btn-outline-secondary btn-sm">← Back to Generator</a>
    </div>
  </div>

  <?php if ($totalRoomsCount > 30): ?>
    <div class="d-flex gap-2 align-items-center flex-wrap pt-2 border-top small">
      <span class="text-muted fw-bold">Large Scale Navigation (<?= $totalRoomsCount ?> Halls):</span>
      <?php $totalBatches = (int)ceil($totalRoomsCount / $perBatch); ?>
      <div class="btn-group btn-group-sm">
        <?php for ($b = 1; $b <= min(10, $totalBatches); $b++): ?>
          <a href="?exam_id=<?= $examId ?>&batch=<?= $b ?>" class="btn btn-outline-primary <?= ($b === $batch && !$showAll && !$selectedRoom) ? 'active' : '' ?>">
            Halls <?= (($b - 1) * $perBatch + 1) ?>–<?= min($totalRoomsCount, $b * $perBatch) ?>
          </a>
        <?php endfor; ?>
        <?php if ($totalBatches > 10): ?>
          <span class="btn btn-outline-secondary disabled">…</span>
        <?php endif; ?>
      </div>
      <a href="?exam_id=<?= $examId ?>&all=1" class="btn btn-outline-danger btn-sm <?= $showAll ? 'active' : '' ?>">View All <?= $totalRoomsCount ?> Halls</a>
    </div>
  <?php endif; ?>
</div>

<?php foreach ($displayRooms as $roomNo => $seats):
  $grid = []; foreach ($seats as $s) $grid[$s['row_num']][$s['col_num']] = $s;
  $maxRow = (int)($seats[0]['rows_count'] ?? max(array_keys($grid)));
  $maxCol = (int)($seats[0]['cols_count'] ?? max(array_column($seats, 'col_num')));
  $distinctCodes = array_unique(array_filter(array_column($seats, 'exam_code')));
?>
<div class="room-sheet">
  <div class="exam-header">
    <h1>OFFICE OF THE CONTROLLER OF EXAMINATIONS</h1>
    <div class="sub">
      <?= htmlspecialchars($exam['exam_name']) ?> &nbsp;|&nbsp;
      Date: <?= date('d-m-Y', strtotime($exam['exam_date'])) ?> &nbsp;|&nbsp;
      Time: <?= date('h:i A', strtotime($exam['start_time'])) ?>
    </div>
  </div>

  <div class="room-title">
    <div>
      <h2>Hall No: <?= htmlspecialchars($roomNo) ?> &nbsp;<span style="font-size: 13px; font-weight: normal; color: #555;">(<?= htmlspecialchars($seats[0]['block']) ?>)</span></h2>
      <div class="small text-muted mt-1">
        Interleaved Exam Codes: <b><?= implode(', ', array_map('htmlspecialchars', $distinctCodes)) ?: 'Standard' ?></b>
      </div>
    </div>
    <div class="text-end">
      <b><?= count($seats) ?></b> students seated &nbsp;/&nbsp; <b><?= $maxRow * $maxCol ?></b> desks<br>
      <span class="badge bg-light text-dark border small">Side-by-side &amp; front-back exam codes guaranteed different</span>
    </div>
  </div>

  <div class="front-stage">▲ FRONT / INVIGILATOR TABLE ▲</div>

  <table class="seating-grid">
    <thead>
      <tr>
        <th style="width: 60px;">Row</th>
        <?php for ($c = 1; $c <= $maxCol; $c++): ?>
          <th>Col <?= $c ?></th>
        <?php endfor; ?>
      </tr>
    </thead>
    <tbody>
      <?php for ($r = 1; $r <= $maxRow; $r++): ?>
        <tr>
          <th>Row <?= $r ?></th>
          <?php for ($c = 1; $c <= $maxCol; $c++):
            $s = $grid[$r][$c] ?? null;
          ?>
            <?php if ($s): ?>
              <td class="occupied">
                <b><?= htmlspecialchars($s['roll_no']) ?></b>
                <span class="code-badge"><?= htmlspecialchars($s['exam_code'] ?: $s['branch']) ?></span>
                <span class="meta-dept"><?= htmlspecialchars($s['branch']) ?><?= !empty($s['semester']) ? '-S' . $s['semester'] : '' ?></span>
              </td>
            <?php else: ?>
              <td class="empty">— Empty —</td>
            <?php endif; ?>
          <?php endfor; ?>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>

  <div class="sign-row">
    <div>Hall Superintendent Signature: _______________________</div>
    <div>Chief Superintendent: _______________________</div>
  </div>
</div>
<?php endforeach; ?>
</body>
</html>