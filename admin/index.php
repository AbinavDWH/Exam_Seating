<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

// Handle Exam Actions (Edit & Delete) directly from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->prepare("DELETE FROM student_exams WHERE exam_id = ?")->execute([$id]);
            db()->prepare("DELETE FROM seating WHERE exam_id = ?")->execute([$id]);
            db()->prepare("UPDATE students SET exam_id = NULL WHERE exam_id = ?")->execute([$id]);
            db()->prepare("DELETE FROM exams WHERE id = ?")->execute([$id]);
            header('Location: index.php?toast=deleted');
            exit;
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['exam_name'] ?? ''));
        $date = (string)($_POST['exam_date'] ?? '');
        $time = $_POST['start_time'] ?: '09:30:00';
        $sem = max(1, min(8, (int)($_POST['semester'] ?? 1)));
        $status = in_array($_POST['status'] ?? '', ['upcoming', 'ongoing', 'completed'], true) ? $_POST['status'] : 'upcoming';
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
  (SELECT COUNT(*) FROM exams) AS exams_count,
  (SELECT COUNT(*) FROM seating) AS seats")->fetch();

$exams = db()->query("SELECT e.*,
  (SELECT COUNT(DISTINCT se_stu.student_id) FROM student_exams se_stu WHERE se_stu.exam_id=e.id) AS students_count,
  (SELECT COUNT(*) FROM seating s WHERE s.exam_id=e.id) AS assigned
  FROM exams e ORDER BY e.exam_date DESC")->fetchAll();

$studentsCount = (int)$stats['students'];
$roomsCount = (int)$stats['rooms'];
$examsCount = (int)$stats['exams_count'];
$seatsCount = (int)$stats['seats'];
$today = date('Y-m-d');
?>

<!-- Page Header with DM Serif Display title -->
<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= svg_icon('dashboard', 'text-primary', 26) ?>
      Dashboard
    </h1>
    <div class="page-subtitle">
      <span><?= date('l, j F Y') ?> · Overview of university examination readiness</span>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="exams.php" class="btn btn-grad">
      <?= svg_icon('plus', 'me-1', 16) ?>
      Create exam
    </a>
  </div>
</div>

<!-- Real Sequence Workflow with Ticks (Numbered 1 to 4) -->
<div class="workflow-sequence-card">
  <div class="workflow-sequence-header">
    <h2 class="workflow-sequence-title">Examination Setup Workflow</h2>
    <span class="small text-muted">Complete steps in sequence to publish conflict-free seating</span>
  </div>

  <div class="workflow-steps-grid">
    <!-- Step 1: Students uploaded -->
    <a href="students.php" class="workflow-step-card <?= $studentsCount > 0 ? 'completed' : '' ?>">
      <div class="step-title-text">
        <span>1. Students uploaded</span>
        <?php if ($studentsCount > 0): ?>
          <span class="text-success fw-bold" style="color: var(--brand-sage); font-size: 1.1rem;">✔</span>
        <?php else: ?>
          <span class="text-muted small">Pending</span>
        <?php endif; ?>
      </div>
      <div class="step-status-desc">
        <?php if ($studentsCount > 0): ?>
          <?= number_format($studentsCount) ?> students registered across cohorts
        <?php else: ?>
          Upload student roster CSV file
        <?php endif; ?>
      </div>
    </a>

    <!-- Step 2: Halls ready -->
    <a href="rooms.php" class="workflow-step-card <?= $roomsCount > 0 ? 'completed' : '' ?>">
      <div class="step-title-text">
        <span>2. Halls ready</span>
        <?php if ($roomsCount > 0): ?>
          <span class="text-success fw-bold" style="color: var(--brand-sage); font-size: 1.1rem;">✔</span>
        <?php else: ?>
          <span class="text-muted small">Pending</span>
        <?php endif; ?>
      </div>
      <div class="step-status-desc">
        <?php if ($roomsCount > 0): ?>
          <?= number_format($roomsCount) ?> active exam halls (<?= number_format((int)$stats['capacity']) ?> desks)
        <?php else: ?>
          Configure examination hall layouts
        <?php endif; ?>
      </div>
    </a>

    <!-- Step 3: Exam created -->
    <a href="exams.php" class="workflow-step-card <?= $examsCount > 0 ? 'completed' : '' ?>">
      <div class="step-title-text">
        <span>3. Exam created</span>
        <?php if ($examsCount > 0): ?>
          <span class="text-success fw-bold" style="color: var(--brand-sage); font-size: 1.1rem;">✔</span>
        <?php else: ?>
          <span class="text-muted small">Pending</span>
        <?php endif; ?>
      </div>
      <div class="step-status-desc">
        <?php if ($examsCount > 0): ?>
          <?= number_format($examsCount) ?> examination sessions scheduled
        <?php else: ?>
          Schedule your first examination session
        <?php endif; ?>
      </div>
    </a>

    <!-- Step 4: Seating generated -->
    <a href="generate.php" class="workflow-step-card <?= $seatsCount > 0 ? 'completed' : '' ?>">
      <div class="step-title-text">
        <span>4. Seating generated</span>
        <?php if ($seatsCount > 0): ?>
          <span class="text-success fw-bold" style="color: var(--brand-sage); font-size: 1.1rem;">✔</span>
        <?php else: ?>
          <span class="text-muted small">Pending</span>
        <?php endif; ?>
      </div>
      <div class="step-status-desc">
        <?php if ($seatsCount > 0): ?>
          <?= number_format($seatsCount) ?> seats allocated with 0 clashes
        <?php else: ?>
          Run automatic conflict-free allocation
        <?php endif; ?>
      </div>
    </a>
  </div>
</div>

<!-- Scheduled Examinations Table Card -->
<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">
      <div class="text-primary d-inline-flex"><?= svg_icon('calendar', '', 20) ?></div>
      <div>
        <h6 class="mb-0 fw-bold">Scheduled Examination Sessions</h6>
        <div class="table-card-subtitle">Manage sessions, review seat allocations, and generate plans</div>
      </div>
    </div>
    <div>
      <a href="exams.php" class="btn btn-primary-soft btn-sm">
        <?= svg_icon('plus', 'me-1', 14) ?>Create exam
      </a>
    </div>
  </div>

  <!-- Search & Row-Count Selector -->
  <div class="table-filter-bar">
    <div class="table-search-box flex-grow-1" style="max-width: 320px;">
      <div class="table-search-icon"><?= svg_icon('search', '', 16) ?></div>
      <input type="text" id="dashSearchInput" class="form-control form-control-sm table-search-input" placeholder="Search exams by name…" autocomplete="off">
    </div>

    <select id="dashSemFilter" class="form-select form-select-sm table-filter-select" style="max-width: 150px;">
      <option value="">All semesters</option>
      <?php for ($s = 1; $s <= 8; $s++): ?>
        <option value="Sem <?= $s ?>">Sem <?= $s ?></option>
      <?php endfor; ?>
    </select>

    <div class="ms-auto d-flex align-items-center gap-2">
      <label for="dashPerPageSelector" class="small text-muted mb-0">Rows:</label>
      <select id="dashPerPageSelector" class="form-select form-select-sm" style="width: 80px;">
        <option value="10">10</option>
        <option value="25" selected>25</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0" id="dashExamsTable">
      <thead>
        <tr>
          <th class="col-exam">Exam name</th>
          <th class="col-date">Date and time</th>
          <th class="col-sem">Semester</th>
          <th class="col-status">Status</th>
          <th class="col-allocated">Seated progress</th>
          <th class="col-actions text-end">Actions</th>
        </tr>
      </thead>
      <tbody id="dashExamsTableBody">
        <?php foreach ($exams as $e):
            $assigned = (int)$e['assigned'];
            $totalStudents = (int)$e['students_count'];
            $pct = $totalStudents > 0 ? (int)round(($assigned / $totalStudents) * 100) : 0;
            $examDateStr = $e['exam_date'];

            if ($examDateStr < $today) {
                $statusClass = 'status-completed';
                $statusLabel = 'Completed';
                $autoStatus = 'completed';
            } elseif ($examDateStr === $today) {
                $statusClass = 'status-ongoing';
                $statusLabel = 'Ongoing';
                $autoStatus = 'ongoing';
            } else {
                $statusClass = 'status-upcoming';
                $statusLabel = 'Upcoming';
                $autoStatus = 'upcoming';
            }
        ?>
          <tr data-exam-id="<?= $e['id'] ?>"
              data-name="<?= htmlspecialchars(strtolower($e['exam_name'])) ?>"
              data-sem="Sem <?= (int)$e['semester'] ?>"
              data-status="<?= $autoStatus ?>"
              data-raw-name="<?= htmlspecialchars($e['exam_name'], ENT_QUOTES) ?>"
              data-assigned="<?= $assigned ?>">
            <td class="col-exam">
              <div class="fw-semibold text-main"><?= htmlspecialchars($e['exam_name']) ?></div>
            </td>
            <td class="col-date">
              <div class="fw-medium text-main"><?= date('d M Y', strtotime($e['exam_date'])) ?></div>
              <div class="text-muted small"><?= date('h:i A', strtotime($e['start_time'])) ?></div>
            </td>
            <td class="col-sem">
              <span class="badge bg-light text-dark border">Sem <?= (int)$e['semester'] ?></span>
            </td>
            <td class="col-status">
              <span class="status-pill <?= $statusClass ?>">
                <span class="status-dot"></span>
                <span><?= $statusLabel ?></span>
              </span>
            </td>
            <td class="col-allocated">
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height: 6px; background: #EAE2D6; border-radius: 999px;">
                  <div class="progress-bar" style="width: <?= $pct ?>%; background: <?= $pct >= 100 ? '#43522C' : ($pct > 0 ? '#8B9A6E' : '#D8CFBF') ?>;"></div>
                </div>
                <span class="small text-muted text-nowrap"><?= $assigned ?> / <?= $totalStudents ?></span>
              </div>
            </td>
            <td class="col-actions text-end">
              <a href="generate.php?exam_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Generate seating plan">
                <?= svg_icon('magic', '', 14) ?>
              </a>
              <button type="button" class="btn-action btn-action-delete" title="Delete exam" onclick="confirmDeleteDashExam(this)">
                <?= svg_icon('trash', '', 14) ?>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$exams): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-5">
              No examination sessions scheduled yet.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination Summary Footer -->
  <div class="table-pagination-row">
    <span class="small text-muted" id="dashShowingCount">
      Showing 1–<?= count($exams) ?> of <?= count($exams) ?>
    </span>
    <div class="d-flex gap-1" id="dashPaginationControls">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="dashPrevPageBtn" disabled>Prev</button>
      <span class="btn btn-sm btn-light disabled px-3" id="dashPageIndicator">1 / 1</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="dashNextPageBtn" disabled>Next</button>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal (Says what will be lost) -->
<div class="side-panel-overlay" id="dashDeleteModalOverlay" onclick="closeDashDeleteModal()"></div>
<div class="side-panel" id="dashDeleteConfirmModal" style="width: min(440px, 100vw); height: auto; top: 20%; bottom: auto; border-radius: 16px; margin: 0 auto; left: 0; right: 0;" role="dialog">
  <div class="side-panel-header border-0 pb-0">
    <h3 class="side-panel-title text-danger" style="font-size: 1.25rem;">Delete exam</h3>
    <button type="button" class="side-panel-close" onclick="closeDashDeleteModal()">
      <?= svg_icon('x', '', 18) ?>
    </button>
  </div>
  <div class="side-panel-body py-3">
    <p id="dashDeleteConsequenceText" class="mb-4 text-main fw-medium">
      Deleting this exam will un-seat all students assigned to it.
    </p>
    <form method="post" action="index.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="dashDeleteTargetId" value="">
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-danger flex-grow-1" style="background:#9C4632; border-color:#9C4632;">Delete exam</button>
        <button type="button" class="btn btn-outline-secondary" onclick="closeDashDeleteModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function confirmDeleteDashExam(btn) {
  const tr = btn.closest('tr');
  const examId = tr.dataset.examId;
  const examName = tr.dataset.rawName;
  const assigned = parseInt(tr.dataset.assigned || '0', 10);

  document.getElementById('dashDeleteTargetId').value = examId;
  const p = document.getElementById('dashDeleteConsequenceText');
  if (assigned > 0) {
    p.textContent = `Deleting ${examName} un-seats ${assigned} student${assigned === 1 ? '' : 's'}.`;
  } else {
    p.textContent = `Deleting ${examName} removes this session.`;
  }

  document.getElementById('dashDeleteConfirmModal').classList.add('open');
  document.getElementById('dashDeleteModalOverlay').classList.add('active');
}

function closeDashDeleteModal() {
  document.getElementById('dashDeleteConfirmModal').classList.remove('open');
  document.getElementById('dashDeleteModalOverlay').classList.remove('active');
}

document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('dashSearchInput');
  const semFilter = document.getElementById('dashSemFilter');
  const perPageSelect = document.getElementById('dashPerPageSelector');
  const tbody = document.getElementById('dashExamsTableBody');
  const rows = Array.from(tbody.querySelectorAll('tr[data-exam-id]'));
  const countLabel = document.getElementById('dashShowingCount');
  const prevBtn = document.getElementById('dashPrevPageBtn');
  const nextBtn = document.getElementById('dashNextPageBtn');
  const pageIndicator = document.getElementById('dashPageIndicator');

  let currentPage = 1;

  function filterAndPaginate() {
    const q = searchInput.value.toLowerCase().trim();
    const sem = semFilter.value;
    const perPage = parseInt(perPageSelect.value, 10);

    const filtered = rows.filter(r => {
      const name = r.dataset.name;
      const rSem = r.dataset.sem;
      const matchQ = !q || name.includes(q);
      const matchSem = !sem || rSem === sem;
      return matchQ && matchSem;
    });

    const total = filtered.length;
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * perPage;
    const end = start + perPage;

    rows.forEach(r => r.style.display = 'none');
    filtered.slice(start, end).forEach(r => r.style.display = '');

    if (total === 0) {
      countLabel.textContent = 'Showing 0 of 0';
    } else {
      countLabel.textContent = `Showing ${start + 1}–${Math.min(total, end)} of ${total}`;
    }

    pageIndicator.textContent = `${currentPage} / ${totalPages}`;
    prevBtn.disabled = currentPage <= 1;
    nextBtn.disabled = currentPage >= totalPages;
  }

  searchInput.addEventListener('input', () => { currentPage = 1; filterAndPaginate(); });
  semFilter.addEventListener('change', () => { currentPage = 1; filterAndPaginate(); });
  perPageSelect.addEventListener('change', () => { currentPage = 1; filterAndPaginate(); });

  prevBtn.addEventListener('click', () => {
    if (currentPage > 1) {
      currentPage--;
      filterAndPaginate();
    }
  });

  nextBtn.addEventListener('click', () => {
    currentPage++;
    filterAndPaginate();
  });

  filterAndPaginate();
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>