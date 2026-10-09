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
        header('Location: rooms.php?toast=' . urlencode('Hall status updated'));
        exit;
    } elseif ($action === 'batch') {
        $count = max(1, min(5000, (int)($_POST['batch_rooms'] ?? 1)));
        $startNo = max(1, (int)($_POST['batch_start_no'] ?: 101));
        $block = trim((string)($_POST['batch_block'] ?: 'Main Academic Block'));
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
$whereSql = "WHERE 1";
$params = [];
if ($q !== '') {
    $whereSql .= " AND (r.room_no LIKE ? OR r.block LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
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

<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= svg_icon('building', 'text-primary', 26) ?>
      Exam Halls &amp; Desk Layout
    </h1>
    <div class="page-subtitle">
      <span>Configure classroom and examination hall capacities, rows, and seating arrangements</span>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <span class="badge bg-primary-subtle text-primary border py-2 px-3 fs-6 rounded-pill d-inline-flex align-items-center gap-1.5">
      <?= svg_icon('building', '', 16) ?>
      <strong><?= number_format($totalRooms) ?></strong> Total Halls (<?= number_format($activeCount) ?> Active)
    </span>
    <span class="badge bg-success-subtle text-success border py-2 px-3 fs-6 rounded-pill d-inline-flex align-items-center gap-1.5">
      <?= svg_icon('grid', '', 16) ?>
      <strong><?= number_format($totalCap) ?></strong> Desks Capacity
    </span>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <!-- Single Room Card -->
    <div class="table-card mb-4">
      <div class="d-flex align-items-center gap-2 mb-2">
        <div class="text-primary"><?= svg_icon('plus', '', 20) ?></div>
        <h6 class="fw-bold mb-0">Add / Update Single Hall</h6>
      </div>
      <p class="text-muted small mb-3">Configure rows and columns of desks for this hall.</p>
      <form method="post" action="rooms.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="mb-2">
          <label class="form-label small fw-semibold">Hall Number</label>
          <input name="room_no" class="form-control" placeholder="e.g. 101" value="101" required>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Block / Building</label>
          <input name="block" class="form-control" placeholder="e.g. Main Block" value="Main Block">
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small fw-semibold">Rows (Benches)</label>
            <input name="rows_count" id="rowsIn" type="number" min="1" max="100" class="form-control" value="15" required oninput="calcCap()">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Seats / Bench</label>
            <input name="cols_count" id="colsIn" type="number" min="1" max="20" class="form-control" value="2" required oninput="calcCap()">
          </div>
        </div>
        <div class="p-2 mb-3 bg-light rounded text-center small text-muted border" id="capPreview">
          Total: <strong>30 desks</strong> (15 rows × 2 seats/bench)
        </div>
        <button class="btn btn-grad w-100">Save Hall Layout</button>
      </form>
    </div>

    <!-- Batch Room Creator for 1000+ Rooms -->
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="d-flex align-items-center gap-2">
          <div class="text-primary"><?= svg_icon('grid', '', 20) ?></div>
          <h6 class="fw-bold mb-0">Batch Create Exam Halls</h6>
        </div>
        <span class="badge bg-info-subtle text-info small">1 to 5,000 Halls</span>
      </div>
      <p class="text-muted small mb-3">Quickly provision hundreds or thousands of exam halls.</p>
      <form method="post" action="rooms.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="batch">
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small fw-semibold">No. of Rooms</label>
            <input name="batch_rooms" id="bCount" type="number" min="1" max="5000" class="form-control" value="1000" required oninput="calcBatch()">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Start Room No</label>
            <input name="batch_start_no" id="bStartNo" type="number" class="form-control" value="101" required>
          </div>
        </div>
        <div class="d-flex gap-1 flex-wrap mb-2">
          <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setBatchPreset(1000, 15, 2)">1,000 Halls</button>
          <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setBatchPreset(500, 15, 2)">500 Halls</button>
          <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setBatchPreset(100, 15, 2)">100 Halls</button>
          <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setBatchPreset(20, 15, 2)">20 Halls</button>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Block Name</label>
          <input name="batch_block" class="form-control" value="Campus Exam Complex">
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small fw-semibold">Rows</label>
            <input name="batch_rows" id="bRows" type="number" min="1" max="100" class="form-control" value="15" required oninput="calcBatch()">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Columns</label>
            <input name="batch_cols" id="bCols" type="number" min="1" max="20" class="form-control" value="2" required oninput="calcBatch()">
          </div>
        </div>
        <div class="p-2 mb-3 bg-light rounded text-center small text-muted border" id="batchPreview">
          Total: <strong>30,000 desks</strong> across 1,000 rooms (30 desks/room)
        </div>
        <button class="btn btn-outline-primary w-100 rounded-pill">
          <?= svg_icon('grid', 'me-1', 15) ?>Batch Create Halls
        </button>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <form class="d-flex gap-2" method="get" style="max-width: 380px;">
          <input name="q" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm" placeholder="Search Hall No or Block…">
          <button class="btn btn-sm btn-outline-primary">Search</button>
          <?php if ($q !== ''): ?>
            <a href="rooms.php" class="btn btn-sm btn-outline-secondary">Reset</a>
          <?php endif; ?>
        </form>
        <span class="text-muted small">
          Showing <?= number_format($offset + 1) ?>–<?= number_format(min($filteredCount, $offset + $perPage)) ?> of <?= number_format($filteredCount) ?> halls
        </span>
      </div>

      <div class="table-responsive" style="max-height:65vh;overflow:auto">
        <table class="table align-middle mb-0">
          <thead style="position:sticky;top:0;z-index:2;background:#f8fafc;">
            <tr>
              <th>Room</th>
              <th>Block</th>
              <th>Grid Layout</th>
              <th>Total Desks</th>
              <th>Occupied</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rooms as $r):
            $rows = (int)$r['rows_count'];
            $cols = (int)$r['cols_count'];
            $cap = (int)$r['capacity'] ?: ($rows * $cols);
            $isActive = (bool)$r['active'];
          ?>
            <tr>
              <td class="fw-bold text-main">Hall <?= htmlspecialchars($r['room_no']) ?></td>
              <td><?= htmlspecialchars($r['block']) ?></td>
              <td><span class="badge bg-light text-dark border px-2 py-1"><?= $rows ?> rows × <?= $cols ?> cols</span></td>
              <td><span class="fw-bold text-primary"><?= number_format($cap) ?> desks</span></td>
              <td><?= (int)$r['occupied'] ?> seated</td>
              <td>
                <span class="status-pill <?= $isActive ? 'status-upcoming' : 'status-completed' ?>">
                  <span class="status-dot"></span>
                  <?= $isActive ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="col-actions text-end text-nowrap">
                <div class="action-buttons-group">
                  <form method="post" class="d-inline" action="rooms.php">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn-action <?= $isActive ? 'btn-action-edit' : 'btn-action-generate' ?>" title="<?= $isActive ? 'Deactivate Hall' : 'Activate Hall' ?>">
                      <?= svg_icon('eye', '', 15) ?>
                    </button>
                  </form>
                  <button type="button" class="btn-action btn-action-delete"
                          title="Delete Hall"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteRoomModal"
                          data-id="<?= $r['id'] ?>"
                          data-name="Hall <?= htmlspecialchars($r['room_no'], ENT_QUOTES) ?>">
                    <?= svg_icon('trash', '', 15) ?>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rooms): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-5">
                <div class="empty-state-icon mx-auto"><?= svg_icon('building', '', 26) ?></div>
                <div class="fw-bold mt-2">No halls found</div>
                <div class="small text-muted">Try adjusting your search query or add new halls.</div>
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav class="mt-3 d-flex justify-content-between align-items-center">
          <div class="small text-muted">Page <?= $page ?> of <?= $totalPages ?></div>
          <ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?>
              <li class="page-item"><a class="page-link" href="?page=1<?= $q ? '&q=' . urlencode($q) : '' ?>">« First</a></li>
              <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?><?= $q ? '&q=' . urlencode($q) : '' ?>">‹ Prev</a></li>
            <?php endif; ?>
            <li class="page-item active"><span class="page-link"><?= $page ?></span></li>
            <?php if ($page < $totalPages): ?>
              <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?><?= $q ? '&q=' . urlencode($q) : '' ?>">Next ›</a></li>
              <li class="page-item"><a class="page-link" href="?page=<?= $totalPages ?><?= $q ? '&q=' . urlencode($q) : '' ?>">Last »</a></li>
            <?php endif; ?>
          </ul>
        </nav>
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
          <div class="p-3 bg-danger-subtle rounded-3 text-danger small">
            <?= svg_icon('alert-triangle', 'me-1', 15) ?>
            This will remove the room configuration and any seats mapped to this hall.
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
  const deleteModal = document.getElementById('deleteRoomModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      document.getElementById('deleteRoomId').value = btn.getAttribute('data-id');
      document.getElementById('deleteRoomName').textContent = `"${btn.getAttribute('data-name')}"`;
    });
  }
});

function calcCap() {
  const r = +document.getElementById('rowsIn').value || 0;
  const c = +document.getElementById('colsIn').value || 0;
  document.getElementById('capPreview').innerHTML = `Total: <b>${(r * c).toLocaleString()} desks</b> (${r} rows × ${c} columns)`;
}
function calcBatch() {
  const n = +document.getElementById('bCount').value || 0;
  const r = +document.getElementById('bRows').value || 0;
  const c = +document.getElementById('bCols').value || 0;
  document.getElementById('batchPreview').innerHTML = `Total: <b>${(n * r * c).toLocaleString()} desks</b> across ${n.toLocaleString()} rooms (${r * c} desks/room)`;
}
function setBatchPreset(n, r, c) {
  document.getElementById('bCount').value = n;
  document.getElementById('bRows').value = r;
  document.getElementById('bCols').value = c;
  calcBatch();
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>