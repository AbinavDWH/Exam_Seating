<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

// Handle Exam Actions (Edit & Delete) directly from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare("DELETE FROM seating WHERE exam_id = ?")->execute([$id]);
            db()->prepare("UPDATE students SET exam_id = NULL WHERE exam_id = ?")->execute([$id]);
            db()->prepare("DELETE FROM exams WHERE id = ?")->execute([$id]);
            header('Location: index.php?toast=deleted');
            exit;
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['exam_name'] ?? '');
        $date = $_POST['exam_date'] ?? '';
        $time = $_POST['start_time'] ?: '09:30:00';
        $sem = (int)($_POST['semester'] ?? 1);
        $status = $_POST['status'] ?? 'upcoming';
        if ($id > 0 && $name !== '' && $date !== '') {
            db()->prepare("UPDATE exams SET exam_name = ?, exam_date = ?, start_time = ?, semester = ?, status = ? WHERE id = ?")
                ->execute([$name, $date, $time, $sem, $status, $id]);
            header('Location: index.php?toast=updated');
            exit;
        }
    }
}

$pageTitle = 'Dashboard';
require __DIR__ . '/_header.php';

$stats = db()->query("SELECT
  (SELECT COUNT(*) FROM students) AS students,
  (SELECT COUNT(*) FROM rooms WHERE active=1) AS rooms,
  (SELECT IFNULL(SUM(rows_count*cols_count),0) FROM rooms WHERE active=1) AS capacity,
  (SELECT COUNT(*) FROM seating) AS seats")->fetch();

$exams = db()->query("SELECT e.*,
  (SELECT COUNT(*) FROM students st WHERE st.exam_id=e.id) AS students_count,
  (SELECT COUNT(*) FROM seating s WHERE s.exam_id=e.id) AS assigned
  FROM exams e ORDER BY e.exam_date DESC")->fetchAll();

$upcomingCount = (int)db()->query("SELECT COUNT(*) FROM exams WHERE status='upcoming'")->fetchColumn();

$cards = [
    [
        'id'    => 'students',
        'icon'  => 'users',
        'bg'    => '#eef2ff',
        'color' => '#4f46e5',
        'label' => 'Registered Students',
        'value' => $stats['students'],
        'link'  => 'students.php',
    ],
    [
        'id'    => 'rooms',
        'icon'  => 'building',
        'bg'    => '#ecfdf5',
        'color' => '#10b981',
        'label' => 'Active Exam Halls',
        'value' => $stats['rooms'],
        'link'  => 'rooms.php',
    ],
    [
        'id'    => 'capacity',
        'icon'  => 'grid',
        'bg'    => '#fff7ed',
        'color' => '#f59e0b',
        'label' => 'Total Seating Capacity',
        'value' => $stats['capacity'],
        'link'  => 'rooms.php',
    ],
    [
        'id'    => 'seats',
        'icon'  => 'check-circle',
        'bg'    => '#fdf2f8',
        'color' => '#ec4899',
        'label' => 'Seats Allocated',
        'value' => $stats['seats'],
        'link'  => 'generate.php',
    ],
];
?>

<!-- Page Header (Requirements 8, 22) -->
<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= svg_icon('dashboard', 'text-primary', 26) ?>
      Welcome back, <?= htmlspecialchars($_SESSION['admin_user']) ?>
    </h1>
    <div class="page-subtitle">
      <?= svg_icon('calendar', 'text-muted', 15) ?>
      <span><?= date('l, j F Y') ?></span>
      <span>•</span>
      <span><?= (int)$upcomingCount ?> upcoming exam session<?= $upcomingCount === 1 ? '' : 's' ?> scheduled</span>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="exams.php" class="btn btn-primary-soft">
      <?= svg_icon('plus', 'me-1', 16) ?>
      Schedule Exam
    </a>
  </div>
</div>

<!-- 4 Stat Cards in One Row (Requirements 1, 2, 3, 9, 11, 13) -->
<div class="stat-grid">
  <?php foreach ($cards as $c): ?>
    <a href="<?= $c['link'] ?>" class="stat-card" title="Open <?= htmlspecialchars($c['label']) ?>">
      <div class="stat-icon" style="background:<?= $c['bg'] ?>; color:<?= $c['color'] ?>;">
        <?= svg_icon($c['icon'], '', 24) ?>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?= number_format((int)$c['value']) ?></div>
        <div class="stat-label"><?= $c['label'] ?></div>
      </div>
      <div class="stat-arrow">
        <?= svg_icon('arrow-up-right', '', 18) ?>
      </div>
    </a>
  <?php endforeach; ?>
</div>

<!-- Scheduled Examinations Table Card (Requirements 4, 5, 6, 7, 8, 9, 10, 12, 14, 15, 16) -->
<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">
      <div class="text-primary d-inline-flex"><?= svg_icon('calendar', '', 22) ?></div>
      <div>
        <h6>Scheduled Examinations</h6>
        <div class="table-card-subtitle">Manage sessions, monitor seat fills, and launch seating plans</div>
      </div>
    </div>
    <div>
      <a href="exams.php" class="btn btn-primary-soft btn-sm">
        <?= svg_icon('plus', 'me-1', 15) ?>Schedule New Exam
      </a>
    </div>
  </div>

  <!-- Search & Filter Controls (Requirement 14) -->
  <div class="table-filter-bar">
    <div class="table-search-box">
      <div class="table-search-icon"><?= svg_icon('search', '', 16) ?></div>
      <input type="text" id="examSearchInput" class="form-control form-control-sm table-search-input" placeholder="Search exams by name..." autocomplete="off">
    </div>
    
    <select id="examSemFilter" class="form-select form-select-sm table-filter-select">
      <option value="">All Semesters</option>
      <?php for ($s = 1; $s <= 8; $s++): ?>
        <option value="Sem <?= $s ?>">Sem <?= $s ?></option>
      <?php endfor; ?>
    </select>

    <select id="examStatusFilter" class="form-select form-select-sm table-filter-select">
      <option value="">All Statuses</option>
      <option value="upcoming">Upcoming</option>
      <option value="ongoing">Ongoing</option>
      <option value="completed">Completed</option>
    </select>

    <button type="button" id="resetFiltersBtn" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" style="border-radius:10px;">
      <?= svg_icon('x', '', 14) ?>Reset
    </button>
  </div>

  <div class="table-responsive">
    <table class="table align-middle" id="examsTable">
      <thead>
        <tr>
          <th class="col-exam sortable-th active-sort" data-col="0" data-type="text">
            Exam Name <span class="sort-icon"><?= svg_icon('sort', '', 13) ?></span>
          </th>
          <th class="col-date sortable-th" data-col="1" data-type="date">
            Date &amp; Time <span class="sort-icon"><?= svg_icon('sort', '', 13) ?></span>
          </th>
          <th class="col-sem sortable-th" data-col="2" data-type="text">
            Target Sem <span class="sort-icon"><?= svg_icon('sort', '', 13) ?></span>
          </th>
          <th class="col-status sortable-th" data-col="3" data-type="text">
            Status <span class="sort-icon"><?= svg_icon('sort', '', 13) ?></span>
          </th>
          <th class="col-allocated sortable-th" data-col="4" data-type="number">
            Seated Progress <span class="sort-icon"><?= svg_icon('sort', '', 13) ?></span>
          </th>
          <th class="col-actions text-end">
            Actions
          </th>
        </tr>
      </thead>
      <tbody id="examsTableBody">
        <?php foreach ($exams as $e):
          $assigned = (int)$e['assigned'];
          $totalStudents = (int)$e['students_count'];
          $pct = $totalStudents > 0 ? (int)round(($assigned / $totalStudents) * 100) : 0;
          $status = strtolower($e['status']);
          $statusClass = ($status === 'ongoing') ? 'status-ongoing' : (($status === 'completed' || $status === 'done') ? 'status-completed' : 'status-upcoming');
          $statusLabel = ($status === 'completed') ? 'Done' : ucfirst($status);
          $fillColor = ($pct >= 100) ? '#10b981' : (($pct > 0) ? '#4f46e5' : '#cbd5e1');
        ?>
          <tr data-exam-id="<?= $e['id'] ?>"
              data-name="<?= htmlspecialchars(strtolower($e['exam_name'])) ?>"
              data-sem="Sem <?= (int)$e['semester'] ?>"
              data-status="<?= $status ?>"
              data-date="<?= $e['exam_date'] ?> <?= $e['start_time'] ?>"
              data-pct="<?= $pct ?>">
            
            <!-- Column 1: Exam (Left aligned) -->
            <td class="col-exam">
              <div class="fw-semibold text-main"><?= htmlspecialchars($e['exam_name']) ?></div>
            </td>

            <!-- Column 2: Date & Time (Left aligned) -->
            <td class="col-date">
              <div class="fw-medium text-main"><?= date('d M Y', strtotime($e['exam_date'])) ?></div>
              <div class="text-muted small"><?= date('h:i A', strtotime($e['start_time'])) ?></div>
            </td>

            <!-- Column 3: Target Sem (Center aligned) -->
            <td class="col-sem">
              <span class="badge bg-light text-dark border px-2.5 py-1.5" style="border-radius: 8px; font-weight: 600;">
                Sem <?= (int)$e['semester'] ?>
              </span>
            </td>

            <!-- Column 4: Status (Center aligned) - Requirement 5 -->
            <td class="col-status">
              <span class="status-pill <?= $statusClass ?>">
                <span class="status-dot"></span>
                <?= $statusLabel ?>
              </span>
            </td>

            <!-- Column 5: Allocated Progress Bar (Left aligned) - Requirement 6 -->
            <td class="col-allocated">
              <div class="seated-progress-wrapper">
                <div class="seated-progress-info">
                  <span class="seated-progress-count"><?= number_format($assigned) ?> / <?= number_format($totalStudents) ?> Seated</span>
                  <span class="seated-progress-pct"><?= $pct ?>%</span>
                </div>
                <div class="seated-progress-track">
                  <div class="seated-progress-fill" style="width: <?= $pct ?>%; background: <?= $fillColor ?>;"></div>
                </div>
              </div>
            </td>

            <!-- Column 6: Actions (Right aligned) - Requirement 7 -->
            <td class="col-actions text-end text-nowrap">
              <div class="action-buttons-group">
                <!-- View Seating Plan -->
                <a href="print_plan.php?exam_id=<?= $e['id'] ?>" class="btn-action btn-action-view" title="View Seating Plan">
                  <?= svg_icon('eye', '', 15) ?>
                </a>

                <!-- Edit Exam Session -->
                <button type="button" class="btn-action btn-action-edit btn-edit-exam"
                        title="Edit Exam"
                        data-bs-toggle="modal"
                        data-bs-target="#editExamModal"
                        data-id="<?= $e['id'] ?>"
                        data-name="<?= htmlspecialchars($e['exam_name'], ENT_QUOTES) ?>"
                        data-date="<?= $e['exam_date'] ?>"
                        data-time="<?= substr($e['start_time'], 0, 5) ?>"
                        data-sem="<?= (int)$e['semester'] ?>"
                        data-status="<?= $e['status'] ?>">
                  <?= svg_icon('edit', '', 15) ?>
                </button>

                <!-- Generate Seating -->
                <a href="generate.php?exam_id=<?= $e['id'] ?>" class="btn-action btn-action-generate" title="Generate Seating">
                  <?= svg_icon('magic', '', 15) ?>
                </a>

                <!-- Delete Exam -->
                <button type="button" class="btn-action btn-action-delete btn-delete-exam"
                        title="Delete Exam"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteExamModal"
                        data-id="<?= $e['id'] ?>"
                        data-name="<?= htmlspecialchars($e['exam_name'], ENT_QUOTES) ?>">
                  <?= svg_icon('trash', '', 15) ?>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Friendly Empty State (Requirement 16) -->
  <div id="tableEmptyState" class="table-empty-state" style="<?= count($exams) === 0 ? '' : 'display:none;' ?>">
    <div class="empty-state-icon">
      <?= svg_icon('calendar', '', 28) ?>
    </div>
    <div class="empty-state-title" id="emptyStateTitle">No exam sessions yet – create one</div>
    <div class="empty-state-text" id="emptyStateText">
      Get started by scheduling your university examination sessions to assign students and allocate halls.
    </div>
    <a href="exams.php" class="btn btn-grad">
      <?= svg_icon('plus', 'me-1', 16) ?>
      Schedule Exam Session
    </a>
  </div>
</div>

<!-- Modal: Delete Confirmation Popup (Requirement 18) -->
<div class="modal fade" id="deleteExamModal" tabindex="-1" aria-labelledby="deleteExamModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteExamModalLabel">
          <?= svg_icon('alert-triangle', '', 20) ?>
          Delete Exam Session
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="index.php">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteExamId" value="">
        <div class="modal-body py-3">
          <p class="text-body mb-2">Are you sure you want to delete <strong id="deleteExamName">this exam session</strong>?</p>
          <div class="p-3 bg-danger-subtle rounded-3 text-danger small">
            <?= svg_icon('alert-triangle', 'me-1', 15) ?>
            This will permanently remove the exam session and clear all student seating assignments for it.
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger px-3">Yes, Delete Exam</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Quick Edit Exam Session -->
<div class="modal fade" id="editExamModal" tabindex="-1" aria-labelledby="editExamModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="editExamModalLabel">
          <?= svg_icon('edit', 'text-primary', 20) ?>
          Edit Exam Session
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="index.php">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editExamId" value="">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Exam Name</label>
            <input type="text" name="exam_name" id="editExamName" class="form-control" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-7">
              <label class="form-label small fw-semibold">Date</label>
              <input type="date" name="exam_date" id="editExamDate" class="form-control" required>
            </div>
            <div class="col-5">
              <label class="form-label small fw-semibold">Start Time</label>
              <input type="time" name="start_time" id="editExamTime" class="form-control" required>
            </div>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Target Semester</label>
              <input type="number" name="semester" id="editExamSem" min="1" max="8" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Status</label>
              <select name="status" id="editExamStatus" class="form-select">
                <option value="upcoming">Upcoming</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-grad">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Elements
  const searchInput = document.getElementById('examSearchInput');
  const semFilter = document.getElementById('examSemFilter');
  const statusFilter = document.getElementById('examStatusFilter');
  const resetBtn = document.getElementById('resetFiltersBtn');
  const tableBody = document.getElementById('examsTableBody');
  const emptyState = document.getElementById('tableEmptyState');
  const emptyTitle = document.getElementById('emptyStateTitle');
  const emptyText = document.getElementById('emptyStateText');
  const table = document.getElementById('examsTable');

  // Filter Function (Requirement 14)
  function applyFilters() {
    const query = searchInput.value.trim().toLowerCase();
    const semVal = semFilter.value;
    const statusVal = statusFilter.value.toLowerCase();
    const rows = tableBody.querySelectorAll('tr');
    let visibleCount = 0;

    rows.forEach(row => {
      const name = row.getAttribute('data-name') || '';
      const sem = row.getAttribute('data-sem') || '';
      const status = row.getAttribute('data-status') || '';

      const matchesQuery = !query || name.includes(query);
      const matchesSem = !semVal || sem === semVal;
      const matchesStatus = !statusVal || status === statusVal;

      if (matchesQuery && matchesSem && matchesStatus) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    if (visibleCount === 0) {
      table.style.display = 'none';
      emptyState.style.display = 'block';
      if (rows.length === 0) {
        emptyTitle.textContent = 'No exam sessions yet – create one';
        emptyText.textContent = 'Get started by scheduling your university examination sessions to assign students and allocate halls.';
      } else {
        emptyTitle.textContent = 'No matching exam sessions found';
        emptyText.textContent = 'None of your exam sessions match the active search criteria or filters. Try adjusting your filters.';
      }
    } else {
      table.style.display = '';
      emptyState.style.display = 'none';
    }
  }

  searchInput.addEventListener('input', applyFilters);
  semFilter.addEventListener('change', applyFilters);
  statusFilter.addEventListener('change', applyFilters);

  resetBtn.addEventListener('click', () => {
    searchInput.value = '';
    semFilter.value = '';
    statusFilter.value = '';
    applyFilters();
  });

  // Table Column Sorting (Requirement 15)
  const headers = table.querySelectorAll('th.sortable-th');
  let currentSort = { col: 0, asc: true };

  headers.forEach(th => {
    th.addEventListener('click', () => {
      const colIndex = parseInt(th.getAttribute('data-col'), 10);
      const type = th.getAttribute('data-type') || 'text';

      if (currentSort.col === colIndex) {
        currentSort.asc = !currentSort.asc;
      } else {
        currentSort.col = colIndex;
        currentSort.asc = true;
      }

      headers.forEach(h => h.classList.remove('active-sort'));
      th.classList.add('active-sort');

      const rows = Array.from(tableBody.querySelectorAll('tr'));
      rows.sort((a, b) => {
        let valA, valB;
        if (colIndex === 0) {
          valA = a.getAttribute('data-name');
          valB = b.getAttribute('data-name');
        } else if (colIndex === 1) {
          valA = a.getAttribute('data-date');
          valB = b.getAttribute('data-date');
        } else if (colIndex === 2) {
          valA = a.getAttribute('data-sem');
          valB = b.getAttribute('data-sem');
        } else if (colIndex === 3) {
          valA = a.getAttribute('data-status');
          valB = b.getAttribute('data-status');
        } else if (colIndex === 4) {
          valA = parseInt(a.getAttribute('data-pct'), 10) || 0;
          valB = parseInt(b.getAttribute('data-pct'), 10) || 0;
        }

        if (type === 'number') {
          return currentSort.asc ? valA - valB : valB - valA;
        } else {
          return currentSort.asc ? String(valA).localeCompare(String(valB)) : String(valB).localeCompare(String(valA));
        }
      });

      rows.forEach(r => tableBody.appendChild(r));
    });
  });

  // Delete Modal Setup (Requirement 18)
  const deleteModal = document.getElementById('deleteExamModal');
  deleteModal.addEventListener('show.bs.modal', (event) => {
    const btn = event.relatedTarget;
    const id = btn.getAttribute('data-id');
    const name = btn.getAttribute('data-name');
    document.getElementById('deleteExamId').value = id;
    document.getElementById('deleteExamName').textContent = `"${name}"`;
  });

  // Edit Modal Setup
  const editModal = document.getElementById('editExamModal');
  editModal.addEventListener('show.bs.modal', (event) => {
    const btn = event.relatedTarget;
    document.getElementById('editExamId').value = btn.getAttribute('data-id');
    document.getElementById('editExamName').value = btn.getAttribute('data-name');
    document.getElementById('editExamDate').value = btn.getAttribute('data-date');
    document.getElementById('editExamTime').value = btn.getAttribute('data-time');
    document.getElementById('editExamSem').value = btn.getAttribute('data-sem');
    document.getElementById('editExamStatus').value = btn.getAttribute('data-status');
  });
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>