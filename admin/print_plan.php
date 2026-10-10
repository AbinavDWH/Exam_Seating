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
  LEFT JOIN students s ON s.roll_no = se.roll_no
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
  .print-screen-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 16px auto 20px;
    max-width: 440px;
    text-align: center;
  }
  .print-screen-bar {
    width: 100%;
    height: 6px;
    background: #0f172a;
    border-radius: 3px;
  }
  .print-screen-caption {
    font-size: 10px;
    font-weight: 700;
    color: #475569;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: 6px;
  }
  table.seating-grid {
    border-collapse: collapse;
    width: 100%;
    margin: 0 auto 20px;
  }
  table.seating-grid th, table.seating-grid td {
    border: 1px solid #ccc;
    padding: 6px 4px;
    text-align: center;
    vertical-align: middle;
    font-size: 11px;
  }
  table.seating-grid th {
    background: #f8fafc;
    font-weight: 700;
    color: #334155;
  }
  .print-bench-unit {
    border: 1.5px solid #000;
    border-radius: 5px;
    padding: 3px;
    display: flex;
    gap: 4px;
    background: #fff;
    align-items: stretch;
    justify-content: center;
  }
  .print-seat-cell {
    flex: 1;
    min-width: 58px;
    padding: 4px 2px;
    text-align: center;
    border: 1px dashed #999;
    border-radius: 3px;
    background: #fff;
  }
  .print-seat-cell.occupied {
    border: 1px solid #111;
  }
  .print-seat-cell.empty {
    border: 1px dashed #ccc;
    color: #aaa;
  }
  .print-seat-cell b {
    display: block;
    font-size: 11px;
    font-family: monospace;
    font-weight: 700;
    color: #000;
  }
  .print-code {
    display: block;
    font-size: 9px;
    font-weight: 600;
    color: #333;
    margin-top: 1px;
  }
  .empty-text {
    font-size: 10px;
    color: #999;
  }
  .aisle-th, .aisle-td {
    width: 24px;
    background: #fafafa;
    border-top: none !important;
    border-bottom: none !important;
    border-left: 1px dashed #cbd5e1 !important;
    border-right: 1px dashed #cbd5e1 !important;
    font-size: 9px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 1px;
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
      <span class="badge bg-light text-dark border ms-2">Zero Adjacent Same-Code Conflict</span>
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
  <div class="exam-header d-flex align-items-center justify-content-center gap-3">
    <div style="width:36px;height:36px;background:#ea580c;border-radius:8px;display:grid;place-items:center;color:#fff;flex-shrink:0;">
      <?= svg_icon('seat-grid', '', 20) ?>
    </div>
    <div>
      <h1 class="mb-0">OFFICE OF THE CONTROLLER OF EXAMINATIONS</h1>
      <div class="sub">
        <?= htmlspecialchars($exam['exam_name']) ?> &nbsp;|&nbsp;
        Date: <?= date('d-m-Y', strtotime($exam['exam_date'])) ?> &nbsp;|&nbsp;
        Time: <?= date('h:i A', strtotime($exam['start_time'])) ?>
      </div>
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

  <div class="print-screen-wrap">
    <div class="print-screen-bar"></div>
    <div class="print-screen-caption">FRONT · INVIGILATOR DESK</div>
  </div>

  <?php
    $benchCount = (int)ceil($maxCol / 2);
    $leftBenchCount = max(1, (int)ceil($benchCount / 2));
  ?>
  <table class="seating-grid">
    <thead>
      <tr>
        <th style="width: 50px;">Row</th>
        <?php for ($b = 1; $b <= $benchCount; $b++): ?>
          <th>Bench <?= $b ?> <span style="font-size: 9px; font-weight: normal; color: #64748b;">(Cols <?= (2*$b-1) ?><?= (2*$b <= $maxCol ? ', ' . (2*$b) : '') ?>)</span></th>
          <?php if ($b === $leftBenchCount && $benchCount > 1): ?>
            <th class="aisle-th">Aisle</th>
          <?php endif; ?>
        <?php endfor; ?>
      </tr>
    </thead>
    <tbody>
      <?php for ($r = 1; $r <= $maxRow; $r++): ?>
        <tr>
          <th>Row <?= $r ?></th>
          <?php for ($b = 1; $b <= $benchCount; $b++):
            $cLeft = 2 * $b - 1;
            $cRight = 2 * $b;
            $sLeft = $grid[$r][$cLeft] ?? null;
            $sRight = ($cRight <= $maxCol) ? ($grid[$r][$cRight] ?? null) : false;
          ?>
            <td>
              <div class="print-bench-unit">
                <div class="print-seat-cell <?= $sLeft ? 'occupied' : 'empty' ?>">
                  <?php if ($sLeft): ?>
                    <b><?= htmlspecialchars($sLeft['roll_no']) ?></b>
                    <span class="print-code"><?= htmlspecialchars($sLeft['exam_code'] ?: $sLeft['branch']) ?></span>
                  <?php else: ?>
                    <span class="empty-text">—</span>
                  <?php endif; ?>
                </div>
                <?php if ($sRight !== false): ?>
                  <div class="print-seat-cell <?= $sRight ? 'occupied' : 'empty' ?>">
                    <?php if ($sRight): ?>
                      <b><?= htmlspecialchars($sRight['roll_no']) ?></b>
                      <span class="print-code"><?= htmlspecialchars($sRight['exam_code'] ?: $sRight['branch']) ?></span>
                    <?php else: ?>
                      <span class="empty-text">—</span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </td>
            <?php if ($b === $leftBenchCount && $benchCount > 1): ?>
              <td class="aisle-td"></td>
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