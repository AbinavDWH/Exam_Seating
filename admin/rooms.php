<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: rooms.php?toast=' . urlencode('Invalid hall ID specified'));
            exit;
        }

        $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM seating WHERE room_id = ?");
        $chkStmt->execute([$id]);
        $seatCount = (int)$chkStmt->fetchColumn();

        if ($seatCount > 0 && empty($_POST['confirm_unseat'])) {
            header('Location: rooms.php?toast=' . urlencode("Hall has {$seatCount} seated students. You must confirm un-seating before deleting."));
            exit;
        }

        $pdo->beginTransaction();
        try {
            $delSeats = $pdo->prepare("DELETE FROM seating WHERE room_id = ?");
            $delSeats->execute([$id]);
            $seatCount = $delSeats->rowCount();

            $delRoom = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $delRoom->execute([$id]);
            $pdo->commit();

            $msg = 'Hall deleted successfully' . ($seatCount > 0 ? " (cleared {$seatCount} active seat allocations)" : '');
            header('Location: rooms.php?toast=' . urlencode($msg));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            header('Location: rooms.php?toast=' . urlencode('Could not delete hall: ' . $e->getMessage()));
            exit;
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE rooms SET active = 1 - active WHERE id = ?")->execute([$id]);
        }
        header('Location: rooms.php?toast=' . urlencode('Hall status updated successfully'));
        exit;
    } elseif ($action === 'batch') {
        $count = max(1, min(5000, (int)($_POST['batch_rooms'] ?? 1)));
        $startNo = max(1, (int)($_POST['batch_start_no'] ?: 101));
        $block = trim((string)($_POST['batch_block'] ?: 'Campus Exam Complex'));
        $rows = max(1, min(100, (int)($_POST['batch_rows'] ?? 15)));
        $cols = max(1, min(20, (int)($_POST['batch_cols'] ?? 2)));
        $cap = $rows * $cols;

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("
                INSERT INTO rooms (room_no, block, capacity, rows_count, cols_count, active)
                VALUES (?, ?, ?, ?, ?, 1)
                ON CONFLICT(room_no) DO UPDATE SET
                    block = excluded.block,
                    capacity = excluded.capacity,
                    rows_count = excluded.rows_count,
                    cols_count = excluded.cols_count,
                    active = 1
            ");

            for ($i = 0; $i < $count; $i++) {
                $rNo = (string)($startNo + $i);
                $ins->execute([$rNo, $block, $cap, $rows, $cols]);
            }
            $pdo->commit();
            header('Location: rooms.php?toast=' . urlencode('Successfully provisioned ' . number_format($count) . ' exam halls'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            header('Location: rooms.php?toast=' . urlencode('Batch hall creation failed: ' . $e->getMessage()));
            exit;
        }
    } else {
        $roomNo = trim((string)($_POST['room_no'] ?? ''));
        $block = trim((string)($_POST['block'] ?? ''));
        $rows = max(1, min(100, (int)($_POST['rows_count'] ?? 15)));
        $cols = max(1, min(20, (int)($_POST['cols_count'] ?? 2)));
        $cap = $rows * $cols;

        if ($roomNo === '') {
            header('Location: rooms.php?toast=' . urlencode('Hall number is required'));
            exit;
        }

        $pdo->prepare("INSERT INTO rooms (room_no, block, capacity, rows_count, cols_count, active)
                       VALUES (?,?,?,?,?,1)
                       ON CONFLICT(room_no) DO UPDATE SET
                           block = excluded.block,
                           capacity = excluded.capacity,
                           rows_count = excluded.rows_count,
                           cols_count = excluded.cols_count,
                           active = 1")
            ->execute([$roomNo, $block, $cap, $rows, $cols]);
        header('Location: rooms.php?toast=' . urlencode('Hall layout saved successfully'));
        exit;
    }
}

$pageTitle = 'Rooms';
require __DIR__ . '/_header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$q = trim($_GET['q'] ?? '');
$activeFilter = $_GET['active'] ?? '';
$whereSql = "WHERE 1";
$params = [];
if ($q !== '') {
    $whereSql .= " AND (r.room_no LIKE ? OR r.block LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($activeFilter === '1') {
    $whereSql .= " AND r.active = 1";
} elseif ($activeFilter === '0') {
    $whereSql .= " AND r.active = 0";
}

$totalRooms = (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE active=1")->fetchColumn();
$totalCap = (int)$pdo->query("SELECT IFNULL(SUM(capacity), 0) FROM rooms WHERE active=1")->fetchColumn();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM rooms r $whereSql");
$countStmt->execute($params);
$filteredCount = (int)$countStmt->fetchColumn();
$totalPages = ceil($filteredCount / $perPage);

$query = "
    SELECT r.*, COALESCE(occ.occupied, 0) AS occupied
    FROM rooms r
    LEFT JOIN (SELECT room_id, COUNT(*) AS occupied FROM seating GROUP BY room_id) occ ON occ.room_id = r.id
    $whereSql
    ORDER BY r.block, CAST(r.room_no AS INTEGER), r.room_no
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rooms = $stmt->fetchAll();
?>

<!-- Page Header with Separated Top-Right Stats (Point 11) -->
<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= svg_icon('building', 'text-primary', 26) ?>
      Exam Halls &amp; Desk Layout
    </h1>
    <div class="page-subtitle">
      <span>Configure classroom dimensions, bench capacities, and campus block allocations</span>
    </div>
  </div>
  <div class="d-flex gap-3 flex-wrap">
    <div class="stat-pill px-3 py-2 text-start" style="min-width: 150px;">
      <span style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Exam Halls</span>
      <b style="font-size: 1.25rem; color: #0f172a; margin-top: 2px;"><?= number_format($totalRooms) ?></b>
      <span class="text-muted small" style="font-size: 0.76rem;"><?= number_format($activeCount) ?> Active</span>
    </div>
    <div class="stat-pill px-3 py-2 text-start" style="min-width: 150px;">
      <span style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Desks Capacity</span>
      <b style="font-size: 1.25rem; color: #ea580c; margin-top: 2px;"><?= number_format($totalCap) ?></b>
      <span class="text-muted small" style="font-size: 0.76rem;">Active Seats</span>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Left Side: Hall Forms -->
  <div class="col-lg-5">
    <!-- Single Room Card with Mini Preview Grid (Points 13, 14, 15, 23, 24) -->
    <div class="table-card mb-4">
      <div class="card-title-header">
        <div class="card-title-icon">
          <?= svg_icon('plus', '', 20) ?>
        </div>
        <div class="card-title-text">
          <h6>Add / Update Single Hall</h6>
          <p>Configure bench rows and seats per desk for this hall.</p>
        </div>
      </div>

      <form method="post" action="rooms.php" id="singleRoomForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="room_no">Hall Number</label>
            <input name="room_no" id="room_no" class="form-control" placeholder="e.g. 101" value="101" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="block">Block / Building</label>
            <input name="block" id="block" class="form-control" placeholder="e.g. Main Block" value="Main Academic Block">
          </div>
          <div class="col-6">
            <label class="form-label" for="rowsIn">Rows (Benches)</label>
            <input name="rows_count" id="rowsIn" type="number" min="1" max="100" class="form-control" value="15" required oninput="calcCap()">
          </div>
          <div class="col-6">
            <label class="form-label" for="colsIn">Seats / Bench</label>
            <input name="cols_count" id="colsIn" type="number" min="1" max="20" class="form-control" value="2" required oninput="calcCap()">
          </div>
        </div>

        <!-- Highlighted Desk Chip (Point 23) -->
        <div class="d-flex justify-content-between align-items-center my-3">
          <div class="cap-highlight-chip" id="capPreview">
            <span class="cap-dot"></span>
            <span>Total: <strong id="capTotalNum">30</strong> desks</span>
            <span class="cap-calc-sub text-muted small">(<span id="capRowsNum">15</span> rows × <span id="capColsNum">2</span> seats)</span>
          </div>
          <span class="text-muted small">Real-time Layout</span>
        </div>

        <!-- Live Desk Grid Preview (Point 24) -->
        <div class="mini-grid-container mb-3" id="miniGridContainer">
          <div class="small fw-semibold text-muted mb-2 text-center">
            Classroom Desk Arrangement Preview
          </div>
          <div class="mini-grid-layout" id="miniGridLayout"></div>
        </div>

        <button class="btn btn-grad w-100 py-2.5">
          <?= svg_icon('plus', 'me-1', 16) ?>Save Hall Layout
        </button>
      </form>
    </div>

    <!-- Batch Room Creator (Points 25) -->
    <div class="table-card">
      <div class="card-title-header">
        <div class="card-title-icon">
          <?= svg_icon('grid', '', 20) ?>
        </div>
        <div class="card-title-text">
          <h6>Batch Create Exam Halls</h6>
          <p>Quickly provision multiple exam halls with uniform layouts.</p>
        </div>
      </div>

      <form method="post" action="rooms.php" id="batchRoomForm" onsubmit="return confirmBatchSubmit(event);">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="batch">
        <div class="row g-3 mb-2">
          <div class="col-6">
            <label class="form-label" for="bCount">No. of Rooms</label>
            <input name="batch_rooms" id="bCount" type="number" min="1" max="5000" class="form-control" value="1000" required oninput="calcBatch()">
          </div>
          <div class="col-6">
            <label class="form-label" for="bStartNo">Start Room No</label>
            <input name="batch_start_no" id="bStartNo" type="number" class="form-control" value="101" required oninput="calcBatch()">
          </div>
        </div>

        <!-- Styled Brand Pills for Presets (Point 25) -->
        <div class="d-flex gap-1.5 flex-wrap my-2">
          <button type="button" class="preset-pill" onclick="setBatchPreset(1000, 15, 2)">1,000 Halls</button>
          <button type="button" class="preset-pill" onclick="setBatchPreset(500, 15, 2)">500 Halls</button>
          <button type="button" class="preset-pill" onclick="setBatchPreset(100, 15, 2)">100 Halls</button>
          <button type="button" class="preset-pill" onclick="setBatchPreset(20, 15, 2)">20 Halls</button>
        </div>

        <div class="mb-3">
          <label class="form-label" for="bBlock">Block Name</label>
          <input name="batch_block" id="bBlock" class="form-control" value="Campus Exam Complex" oninput="calcBatch()">
        </div>

        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label" for="bRows">Rows / Room</label>
            <input name="batch_rows" id="bRows" type="number" min="1" max="100" class="form-control" value="15" required oninput="calcBatch()">
          </div>
          <div class="col-6">
            <label class="form-label" for="bCols">Seats / Bench</label>
            <input name="batch_cols" id="bCols" type="number" min="1" max="20" class="form-control" value="2" required oninput="calcBatch()">
          </div>
        </div>

        <!-- Confirmation Summary Box (Point 25) -->
        <div class="p-2.5 mb-3 bg-light rounded-3 border small" id="batchSummaryBox">
          Provision <strong>1,000 halls</strong> (101–1100) in <strong>Campus Exam Complex</strong> with 30 desks/room (Total: 30,000 desks).
        </div>

        <button class="btn btn-outline-primary w-100 py-2">
          <?= svg_icon('grid', 'me-1.5', 16) ?>Create Batch Halls
        </button>
      </form>
    </div>
  </div>

  <!-- Right Side: Rooms Table & Toolbar (Point 28, 29, 30) -->
  <div class="col-lg-7">
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Configured Examination Halls</h6>
        <span class="text-muted small">
          Page <?= $page ?> of <?= max(1, $totalPages) ?> (<?= number_format($filteredCount) ?> halls)
        </span>
      </div>

      <!-- Toolbar Row (Point 28) -->
      <form class="table-toolbar-row" method="get">
        <div class="toolbar-search">
          <input name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Search Hall No or Block…">
        </div>
        <div class="toolbar-filters">
          <select name="active" class="form-select" style="min-width: 140px;">
            <option value="">All Statuses</option>
            <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>Active Only</option>
            <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Inactive Only</option>
          </select>
          <button class="btn btn-grad py-1 px-3" style="height: 44px;">Filter</button>
          <?php if ($q !== '' || $activeFilter !== ''): ?>
            <a href="rooms.php" class="btn btn-outline-secondary py-1 px-3 d-inline-flex align-items-center" style="height: 44px;">Reset</a>
          <?php endif; ?>
        </div>
        <div class="toolbar-count-badge ms-auto">
          <?= number_format($filteredCount) ?> Hall(s)
        </div>
      </form>

      <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
        <table class="table align-middle mb-0">
          <thead style="position: sticky; top: 0; z-index: 2; background: var(--table-th-bg, #DFD6C8);">
            <tr>
              <th>Hall</th>
              <th>Block</th>
              <th>Dimensions</th>
              <th>Capacity</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rooms as $r): ?>
            <tr>
              <td>
                <span class="fw-bold text-main">Hall <?= htmlspecialchars($r['room_no']) ?></span>
              </td>
              <td><?= htmlspecialchars($r['block']) ?></td>
              <td class="small text-muted">
                <?= (int)$r['rows_count'] ?> rows × <?= (int)$r['cols_count'] ?> seats
              </td>
              <td>
                <span class="badge bg-light text-dark border">
                  <?= (int)$r['capacity'] ?> desks
                </span>
                <?php if ($r['occupied'] > 0): ?>
                  <span class="badge bg-warning-subtle text-warning-emphasis ms-1">
                    <?= (int)$r['occupied'] ?> seated
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" action="rooms.php" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button type="submit" class="badge <?= $r['active'] ? 'badge-active-sage' : 'badge-inactive-muted' ?>" style="cursor: pointer;">
                    <?= $r['active'] ? 'Active' : 'Inactive' ?>
                  </button>
                </form>
              </td>
              <td class="text-end">
                <button type="button" class="btn-action btn-action-delete"
                        title="Delete Hall"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteRoomModal"
                        data-id="<?= $r['id'] ?>"
                        data-name="<?= htmlspecialchars($r['room_no'] . ' (' . $r['block'] . ')', ENT_QUOTES) ?>"
                        data-occupied="<?= (int)$r['occupied'] ?>">
                  <?= svg_icon('trash', '', 15) ?>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rooms): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-5">
                No examination halls found matching your criteria.
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination (Point 29) -->
      <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
          <span class="small text-muted">
            Showing <?= number_format($offset + 1) ?>–<?= number_format(min($filteredCount, $offset + $perPage)) ?> of <?= number_format($filteredCount) ?>
          </span>
          <nav>
            <ul class="pagination pagination-sm mb-0">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>&active=<?= urlencode($activeFilter) ?>">Prev</a>
              </li>
              <li class="page-item active">
                <span class="page-link"><?= $page ?> / <?= $totalPages ?></span>
              </li>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>&active=<?= urlencode($activeFilter) ?>">Next</a>
              </li>
            </ul>
          </nav>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal: Delete Confirmation Popup -->
<div class="modal fade" id="deleteRoomModal" tabindex="-1" aria-labelledby="deleteRoomModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteRoomModalLabel">
          <?= svg_icon('alert-triangle', '', 20) ?>
          Delete Exam Hall
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="rooms.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteRoomId" value="">
        <div class="modal-body py-3">
          <p class="text-body mb-2">Are you sure you want to delete <strong id="deleteRoomName">this hall</strong>?</p>
          <div id="unseatWarning" class="p-3 bg-danger-subtle rounded-3 text-danger small mb-2" style="display:none;">
            <div class="fw-bold mb-1">
              <?= svg_icon('alert-triangle', 'me-1', 15) ?>
              <span id="unseatWarningText">Active Seated Students Found!</span>
            </div>
            <div>Deleting this hall will un-seat and displace these students from their examination timetable.</div>
            <div class="form-check mt-2 pt-2 border-top border-danger-subtle">
              <input class="form-check-input" type="checkbox" name="confirm_unseat" value="1" id="confirmUnseatCheck">
              <label class="form-check-label fw-semibold" for="confirmUnseatCheck">
                I understand and confirm un-seating these students
              </label>
            </div>
          </div>
          <div class="p-3 bg-secondary-subtle rounded-3 text-secondary small">
            This will remove the room configuration from the system.
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger px-3">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  calcCap();
  calcBatch();

  const deleteModal = document.getElementById('deleteRoomModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      const occ = parseInt(btn.getAttribute('data-occupied') || '0', 10);
      document.getElementById('deleteRoomId').value = btn.getAttribute('data-id');
      document.getElementById('deleteRoomName').textContent = `"${btn.getAttribute('data-name')}"`;

      const warn = document.getElementById('unseatWarning');
      const warnTxt = document.getElementById('unseatWarningText');
      const chk = document.getElementById('confirmUnseatCheck');
      if (occ > 0) {
        warn.style.display = 'block';
        warnTxt.textContent = `Warning: ${occ} student(s) currently seated in this hall!`;
        chk.required = true;
        chk.checked = false;
      } else {
        warn.style.display = 'none';
        chk.required = false;
        chk.checked = false;
      }
    });
  }
});

function renderMiniDeskGrid(rows, cols) {
  const container = document.getElementById('miniGridLayout');
  if (!container) return;
  const maxR = Math.min(rows, 8);
  const maxC = Math.min(cols, 6);
  let html = '';
  for (let r = 0; r < maxR; r++) {
    html += '<div class="mini-grid-row">';
    for (let c = 0; c < maxC; c++) {
      html += '<div class="mini-desk-cell" title="Row ' + (r+1) + ', Seat ' + (c+1) + '"></div>';
    }
    html += '</div>';
  }
  if (rows > 8 || cols > 6) {
    html += '<div class="text-muted" style="font-size:10px; text-align:center; margin-top:4px;">+ ' + (rows - maxR) + ' more rows</div>';
  }
  container.innerHTML = html;
}

function calcCap() {
  const r = Math.max(1, +document.getElementById('rowsIn').value || 1);
  const c = Math.max(1, +document.getElementById('colsIn').value || 1);
  const total = r * c;
  document.getElementById('capTotalNum').textContent = total.toLocaleString();
  document.getElementById('capRowsNum').textContent = r;
  document.getElementById('capColsNum').textContent = c;
  renderMiniDeskGrid(r, c);
}

function calcBatch() {
  const n = +document.getElementById('bCount').value || 0;
  const s = +document.getElementById('bStartNo').value || 101;
  const r = +document.getElementById('bRows').value || 0;
  const c = +document.getElementById('bCols').value || 0;
  const blk = document.getElementById('bBlock').value || 'Campus Exam Complex';
  const endNo = s + Math.max(0, n - 1);
  const desksPerRoom = r * c;
  const totalDesks = n * desksPerRoom;

  const summary = document.getElementById('batchSummaryBox');
  if (summary) {
    summary.innerHTML = `Provision <strong>${n.toLocaleString()} halls</strong> (${s}–${endNo}) in <strong>${blk}</strong> with ${desksPerRoom} desks/room (Total: <strong>${totalDesks.toLocaleString()}</strong> desks).`;
  }
}

function setBatchPreset(n, r, c) {
  document.getElementById('bCount').value = n;
  document.getElementById('bRows').value = r;
  document.getElementById('bCols').value = c;
  calcBatch();
}

function confirmBatchSubmit(e) {
  const n = +document.getElementById('bCount').value || 0;
  if (n >= 500) {
    return confirm(`Are you sure you want to provision ${n.toLocaleString()} exam halls in bulk?`);
  }
  return true;
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>