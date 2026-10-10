<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $examId = (int)($_POST['id'] ?? 0);
        if ($examId > 0) {
            db()->prepare("DELETE FROM student_exams WHERE exam_id = ?")->execute([$examId]);
            db()->prepare("DELETE FROM seating WHERE exam_id = ?")->execute([$examId]);
            db()->prepare("UPDATE students SET exam_id = NULL WHERE exam_id = ?")->execute([$examId]);
            db()->prepare("DELETE FROM exams WHERE id = ?")->execute([$examId]);
        }
        header('Location: exams.php?toast=deleted');
        exit;
    } elseif ($action === 'edit') {
        $examId = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['exam_name'] ?? ''));
        $date = (string)($_POST['exam_date'] ?? '');
        $time = $_POST['start_time'] ?: '09:30:00';
        $sem = max(1, min(8, (int)($_POST['semester'] ?? 1)));
        $status = in_array($_POST['status'] ?? '', ['upcoming', 'ongoing', 'completed'], true) ? $_POST['status'] : 'upcoming';

        if ($examId > 0 && $name !== '' && $date !== '') {
            db()->prepare("UPDATE exams SET exam_name = ?, exam_date = ?, start_time = ?, semester = ?, status = ? WHERE id = ?")
                ->execute([$name, $date, $time, $sem, $status, $examId]);
            header('Location: exams.php?toast=updated');
            exit;
        } else {
            header('Location: exams.php?toast=' . urlencode('Exam name and date are required'));
            exit;
        }
    } elseif ($action === 'assign') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        $sem = (int)($_POST['semester'] ?? 0);
        $branch = trim((string)($_POST['branch'] ?? ''));
        $confirmAll = !empty($_POST['confirm_all_students']);

        if ($examId <= 0) {
            header('Location: exams.php?toast=' . urlencode('Invalid exam session selected'));
            exit;
        }

        if ($sem !== 0 && ($sem < 1 || $sem > 8)) {
            header('Location: exams.php?toast=' . urlencode('Semester must be between 1 and 8, or All Semesters'));
            exit;
        }

        if ($sem === 0 && $branch === '' && !$confirmAll) {
            header('Location: exams.php?toast=' . urlencode('Assigning all students across all departments requires confirmation. Check the box before proceeding.'));
            exit;
        }

        $sql = "INSERT INTO student_exams (student_id, exam_id, exam_code)
                SELECT id, ?, COALESCE(NULLIF(exam_code, ''), branch || '-S' || semester)
                FROM students WHERE 1";
        $params = [$examId];
        if ($sem > 0) {
            $sql .= " AND semester = ?";
            $params[] = $sem;
        }
        if ($branch !== '') {
            $sql .= " AND branch = ?";
            $params[] = $branch;
        }
        $sql .= " ON CONFLICT(student_id, exam_id) DO NOTHING";
        db()->prepare($sql)->execute($params);

        header('Location: exams.php?toast=' . urlencode('Students assigned to exam session'));
        exit;
    } elseif ($action === 'unassign') {
        $examId = (int)($_POST['exam_id'] ?? 0);
        if ($examId > 0) {
            db()->prepare("DELETE FROM student_exams WHERE exam_id = ?")->execute([$examId]);
            db()->prepare("UPDATE students SET exam_id = NULL WHERE exam_id = ?")->execute([$examId]);
            db()->prepare("DELETE FROM seating WHERE exam_id = ?")->execute([$examId]);
        }
        header('Location: exams.php?toast=' . urlencode('Assignments cleared'));
        exit;
    } else {
        $name = trim((string)($_POST['exam_name'] ?? ''));
        $date = (string)($_POST['exam_date'] ?? '');
        $time = $_POST['start_time'] ?: '09:30:00';
        $sem = (int)($_POST['semester'] ?? 0);
        if ($sem < 1 || $sem > 8) {
            header('Location: exams.php?toast=' . urlencode('Semester must be between 1 and 8'));
            exit;
        }
        $status = 'upcoming'; // Hidden & always upcoming on create

        if ($name !== '' && $date !== '') {
            db()->prepare("INSERT INTO exams (exam_name, exam_date, start_time, semester, status) VALUES (?,?,?,?,?)")
                ->execute([$name, $date, $time, $sem, $status]);
            header('Location: exams.php?toast=created');
            exit;
        } else {
            header('Location: exams.php?toast=' . urlencode('Exam name and date are required'));
            exit;
        }
    }
}

$pageTitle = 'Exam Sessions';
require __DIR__ . '/_header.php';

$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$semFilter = trim($_GET['sem'] ?? '');

$whereSql = "WHERE 1";
$params = [];
if ($q !== '') {
    $whereSql .= " AND e.exam_name LIKE ?";
    $params[] = "%$q%";
}
if ($statusFilter === 'upcoming') {
    $whereSql .= " AND e.exam_date > ?";
    $params[] = $today;
} elseif ($statusFilter === 'ongoing') {
    $whereSql .= " AND e.exam_date = ?";
    $params[] = $today;
} elseif ($statusFilter === 'completed') {
    $whereSql .= " AND e.exam_date < ?";
    $params[] = $today;
}
if ($semFilter !== '') {
    $whereSql .= " AND e.semester = ?";
    $params[] = (int)$semFilter;
}

$stmt = db()->prepare("SELECT e.*,
    (SELECT COUNT(DISTINCT se_stu.student_id) FROM student_exams se_stu WHERE se_stu.exam_id = e.id) AS students,
    (SELECT COUNT(*) FROM seating se WHERE se.exam_id = e.id) AS assigned
  FROM exams e
  $whereSql
  ORDER BY e.exam_date DESC");
$stmt->execute($params);
$exams = $stmt->fetchAll();

$branches = db()->query("SELECT DISTINCT branch FROM students ORDER BY branch")->fetchAll(PDO::FETCH_COLUMN);
$semesters = db()->query("SELECT DISTINCT semester FROM students ORDER BY semester")->fetchAll(PDO::FETCH_COLUMN);
$cohortCounts = db()->query("SELECT semester, branch, COUNT(*) as cnt FROM students GROUP BY semester, branch")->fetchAll();
$totalStudentsCount = (int)db()->query("SELECT COUNT(*) FROM students")->fetchColumn();
?>

<!-- Page Header with Clean Sentence Case Buttons -->
<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= svg_icon('calendar', 'text-primary', 26) ?>
      Exam Sessions
    </h1>
    <div class="page-subtitle">
      <span>Schedule examination sessions, bulk-assign student cohorts, and manage status</span>
    </div>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary" onclick="openBulkAssignModal()">
      <?= svg_icon('users', 'me-1', 16) ?>Bulk assign
    </button>
    <button type="button" class="btn btn-grad" onclick="openExamSidePanel('create')">
      <?= svg_icon('plus', 'me-1', 16) ?>Create exam
    </button>
  </div>
</div>

<!-- Primary View: Full Width Existing Exams Table (Page Order: List First) -->
<div class="table-card mb-4">
  <div class="table-card-header">
    <div class="table-card-title">
      <div class="text-primary d-inline-flex"><?= svg_icon('calendar', '', 20) ?></div>
      <div>
        <h6 class="mb-0 fw-bold">Scheduled Examination Sessions</h6>
        <div class="table-card-subtitle">Manage sessions, review timetable dates, and track seated progress</div>
      </div>
    </div>
  </div>

  <!-- Search & Filter Controls with Row Count Selector -->
  <div class="table-filter-bar">
    <div class="table-search-box flex-grow-1" style="max-width: 320px;">
      <div class="table-search-icon"><?= svg_icon('search', '', 16) ?></div>
      <input type="text" id="examSearchInput" class="form-control form-control-sm table-search-input" placeholder="Search exams by name…" autocomplete="off">
    </div>

    <select id="examSemFilter" class="form-select form-select-sm table-filter-select" style="max-width: 150px;">
      <option value="">All semesters</option>
      <?php for ($s = 1; $s <= 8; $s++): ?>
        <option value="Sem <?= $s ?>">Sem <?= $s ?></option>
      <?php endfor; ?>
    </select>

    <select id="examStatusFilter" class="form-select form-select-sm table-filter-select" style="max-width: 150px;">
      <option value="">All statuses</option>
      <option value="upcoming">Upcoming</option>
      <option value="ongoing">Ongoing</option>
      <option value="completed">Completed</option>
    </select>

    <div class="ms-auto d-flex align-items-center gap-2">
      <label for="perPageSelector" class="small text-muted mb-0">Rows:</label>
      <select id="perPageSelector" class="form-select form-select-sm" style="width: 80px;">
        <option value="10">10</option>
        <option value="25" selected>25</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0" id="examsTable">
      <thead>
        <tr>
          <th class="col-exam sortable-th active-sort" data-col="0">Exam name</th>
          <th class="col-date sortable-th" data-col="1">Date and time</th>
          <th class="col-sem sortable-th" data-col="2">Semester</th>
          <th class="col-status sortable-th" data-col="3">Status</th>
          <th class="col-allocated sortable-th" data-col="4">Seated progress</th>
          <th class="col-actions text-end">Actions</th>
        </tr>
      </thead>
      <tbody id="examsTableBody">
        <?php foreach ($exams as $e):
            $assigned = (int)$e['assigned'];
            $totalStudents = (int)$e['students'];
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
              data-date="<?= $e['exam_date'] ?> <?= $e['start_time'] ?>"
              data-raw-name="<?= htmlspecialchars($e['exam_name'], ENT_QUOTES) ?>"
              data-raw-date="<?= htmlspecialchars($e['exam_date'], ENT_QUOTES) ?>"
              data-raw-time="<?= htmlspecialchars($e['start_time'], ENT_QUOTES) ?>"
              data-raw-sem="<?= (int)$e['semester'] ?>"
              data-raw-status="<?= htmlspecialchars($e['status'], ENT_QUOTES) ?>"
              data-assigned="<?= $assigned ?>"
              data-students="<?= $totalStudents ?>">
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
              <div class="d-inline-flex gap-1">
                <button type="button" class="btn-action" title="Edit exam" onclick="editExamRow(this)">
                  <?= svg_icon('edit', '', 15) ?>
                </button>
                <button type="button" class="btn-action btn-action-delete" title="Delete exam" onclick="confirmDeleteExam(this)">
                  <?= svg_icon('trash', '', 15) ?>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$exams): ?>
          <tr id="emptyTableRow">
            <td colspan="6" class="text-center text-muted py-5">
              No exam sessions found matching your filters.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination Summary Footer -->
  <div class="table-pagination-row">
    <span class="small text-muted" id="tableShowingCount">
      Showing 1–<?= count($exams) ?> of <?= count($exams) ?>
    </span>
    <div class="d-flex gap-1" id="paginationControls">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="prevPageBtn" disabled>Prev</button>
      <span class="btn btn-sm btn-light disabled px-3" id="pageIndicator">1 / 1</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="nextPageBtn" disabled>Next</button>
    </div>
  </div>
</div>

<!-- Side Panel for Create / Edit Exam (Full Width Fields & Side-by-Side Date/Time) -->
<div class="side-panel-overlay" id="examPanelOverlay" onclick="closeExamSidePanel()"></div>
<div class="side-panel" id="examSidePanel" aria-labelledby="sidePanelTitle" role="dialog">
  <div class="side-panel-header">
    <h2 class="side-panel-title" id="sidePanelTitle">Create exam</h2>
    <button type="button" class="side-panel-close" onclick="closeExamSidePanel()" aria-label="Close panel">
      <?= svg_icon('x', '', 18) ?>
    </button>
  </div>
  <div class="side-panel-body">
    <form method="post" action="exams.php" id="sideExamForm">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="id" id="editExamId" value="">

      <!-- Full-Width Exam Name -->
      <div class="mb-3">
        <label class="form-label fw-bold" for="sideExamName">Exam name</label>
        <input name="exam_name" id="sideExamName" class="form-control" placeholder="e.g. Internal Assessment Test II" required autocomplete="off">
      </div>

      <!-- Side-by-Side Date and Time -->
      <div class="row g-2 mb-3">
        <div class="col-7">
          <label class="form-label fw-bold" for="sideExamDate">Exam date</label>
          <input name="exam_date" id="sideExamDate" type="date" class="form-control" required placeholder="YYYY-MM-DD">
        </div>
        <div class="col-5">
          <label class="form-label fw-bold" for="sideStartTime">Start time</label>
          <input name="start_time" id="sideStartTime" type="time" class="form-control" value="09:30" required>
        </div>
      </div>

      <!-- Full-Width Semester -->
      <div class="mb-3">
        <label class="form-label fw-bold" for="sideSemester">Semester</label>
        <input name="semester" id="sideSemester" type="number" min="1" max="8" class="form-control" placeholder="e.g. 5" required>
      </div>

      <!-- Status (Hidden when creating, shown only when editing) -->
      <div class="mb-4" id="statusFieldWrap" style="display: none;">
        <label class="form-label fw-bold" for="sideStatus">Status</label>
        <select name="status" id="sideStatus" class="form-select">
          <option value="upcoming">Upcoming</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
        </select>
      </div>

      <div class="d-flex gap-2 pt-2 border-top">
        <button type="submit" class="btn btn-grad flex-grow-1" id="submitExamBtn">Create exam</button>
        <button type="button" class="btn btn-outline-secondary" onclick="closeExamSidePanel()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Bulk Assign Cohort Modal with Live Preview Sentence -->
<div class="side-panel-overlay" id="bulkAssignOverlay" onclick="closeBulkAssignModal()"></div>
<div class="side-panel" id="bulkAssignModal" style="width: min(500px, 100vw);" role="dialog">
  <div class="side-panel-header">
    <h2 class="side-panel-title">Bulk assign students</h2>
    <button type="button" class="side-panel-close" onclick="closeBulkAssignModal()" aria-label="Close">
      <?= svg_icon('x', '', 18) ?>
    </button>
  </div>
  <div class="side-panel-body">
    <form method="post" action="exams.php" id="bulkAssignForm">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="assign">

      <div class="mb-3">
        <label class="form-label fw-bold" for="assignExamSelect">Target exam session</label>
        <select name="exam_id" id="assignExamSelect" class="form-select" required onchange="updateAssignPreview()">
          <option value="">— Select exam session —</option>
          <?php foreach ($exams as $e): ?>
            <option value="<?= $e['id'] ?>" data-name="<?= htmlspecialchars($e['exam_name'], ENT_QUOTES) ?>">
              <?= htmlspecialchars($e['exam_name']) ?> (Sem <?= (int)$e['semester'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold" for="assignSemSelect">Semester</label>
        <select name="semester" id="assignSemSelect" class="form-select" onchange="updateAssignPreview()">
          <option value="0">All semesters</option>
          <?php for ($s = 1; $s <= 8; $s++): ?>
            <option value="<?= $s ?>">Semester <?= $s ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="mb-4">
        <label class="form-label fw-bold" for="assignBranchSelect">Department</label>
        <select name="branch" id="assignBranchSelect" class="form-select" onchange="updateAssignPreview()">
          <option value="">All departments</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Live Preview Sentence Box (Red styling when All Semesters + All Depts) -->
      <div id="assignLivePreviewBox" class="p-3 mb-4 rounded-3 border bg-light">
        <div id="assignPreviewText" class="fw-semibold">Select an exam session to see student count</div>
        <div id="assignAllWarning" class="mt-2 pt-2 border-top border-danger" style="display: none;">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="confirm_all_students" id="confirmAllStudentsCheck">
            <label class="form-check-label small fw-bold text-danger" for="confirmAllStudentsCheck">
              I confirm assigning all students across every semester and department
            </label>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-grad flex-grow-1" id="confirmAssignBtn">Assign students</button>
        <button type="button" class="btn btn-outline-secondary" onclick="closeBulkAssignModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Confirmation Modal (Says what will be lost) -->
<div class="side-panel-overlay" id="deleteModalOverlay" onclick="closeDeleteModal()"></div>
<div class="side-panel" id="deleteConfirmModal" style="width: min(440px, 100vw); height: auto; top: 20%; bottom: auto; border-radius: 16px; margin: 0 auto; left: 0; right: 0;" role="dialog">
  <div class="side-panel-header border-0 pb-0">
    <h3 class="side-panel-title text-danger" style="font-size: 1.25rem;">Delete exam</h3>
    <button type="button" class="side-panel-close" onclick="closeDeleteModal()">
      <?= svg_icon('x', '', 18) ?>
    </button>
  </div>
  <div class="side-panel-body py-3">
    <p id="deleteConsequenceText" class="mb-4 text-main fw-medium">
      Deleting this exam will un-seat all students assigned to it.
    </p>
    <form method="post" action="exams.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="deleteTargetId" value="">
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-danger flex-grow-1" style="background:#9C4632; border-color:#9C4632;">Delete exam</button>
        <button type="button" class="btn btn-outline-secondary" onclick="closeDeleteModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// Cohort count dataset for live calculation
const cohortData = <?= json_encode($cohortCounts) ?>;
const totalStudentsInDb = <?= (int)$totalStudentsCount ?>;

// Open / Close Side Panel
function openExamSidePanel(mode, data = null) {
  const panel = document.getElementById('examSidePanel');
  const overlay = document.getElementById('examPanelOverlay');
  const title = document.getElementById('sidePanelTitle');
  const submitBtn = document.getElementById('submitExamBtn');
  const action = document.getElementById('formAction');
  const idInput = document.getElementById('editExamId');
  const statusWrap = document.getElementById('statusFieldWrap');
  
  if (mode === 'edit' && data) {
    title.textContent = 'Edit exam';
    submitBtn.textContent = 'Save changes';
    action.value = 'edit';
    idInput.value = data.id;
    document.getElementById('sideExamName').value = data.name;
    document.getElementById('sideExamDate').value = data.date;
    document.getElementById('sideStartTime').value = data.time || '09:30';
    document.getElementById('sideSemester').value = data.sem;
    document.getElementById('sideStatus').value = data.status || 'upcoming';
    statusWrap.style.display = 'block'; // Show status only when editing
  } else {
    title.textContent = 'Create exam';
    submitBtn.textContent = 'Create exam';
    action.value = 'create';
    idInput.value = '';
    // Empty inputs with placeholders
    document.getElementById('sideExamName').value = '';
    document.getElementById('sideExamDate').value = '';
    document.getElementById('sideStartTime').value = '09:30';
    document.getElementById('sideSemester').value = '';
    statusWrap.style.display = 'none'; // Hide status on create
  }
  
  panel.classList.add('open');
  overlay.classList.add('active');
}

function closeExamSidePanel() {
  document.getElementById('examSidePanel').classList.remove('open');
  document.getElementById('examPanelOverlay').classList.remove('active');
}

function openBulkAssignModal() {
  document.getElementById('bulkAssignModal').classList.add('open');
  document.getElementById('bulkAssignOverlay').classList.add('active');
  updateAssignPreview();
}

function closeBulkAssignModal() {
  document.getElementById('bulkAssignModal').classList.remove('open');
  document.getElementById('bulkAssignOverlay').classList.remove('active');
}

function editExamRow(btn) {
  const tr = btn.closest('tr');
  const data = {
    id: tr.dataset.examId,
    name: tr.dataset.rawName,
    date: tr.dataset.rawDate,
    time: tr.dataset.rawTime,
    sem: tr.dataset.rawSem,
    status: tr.dataset.rawStatus,
  };
  openExamSidePanel('edit', data);
}

function confirmDeleteExam(btn) {
  const tr = btn.closest('tr');
  const examId = tr.dataset.examId;
  const examName = tr.dataset.rawName;
  const assigned = parseInt(tr.dataset.assigned || '0', 10);
  
  document.getElementById('deleteTargetId').value = examId;
  const p = document.getElementById('deleteConsequenceText');
  if (assigned > 0) {
    p.textContent = `Deleting ${examName} un-seats ${assigned} student${assigned === 1 ? '' : 's'}.`;
  } else {
    p.textContent = `Deleting ${examName} removes this session.`;
  }
  
  document.getElementById('deleteConfirmModal').classList.add('open');
  document.getElementById('deleteModalOverlay').classList.add('active');
}

function closeDeleteModal() {
  document.getElementById('deleteConfirmModal').classList.remove('open');
  document.getElementById('deleteModalOverlay').classList.remove('active');
}

// Live calculation for Bulk Assign
function updateAssignPreview() {
  const examSelect = document.getElementById('assignExamSelect');
  const sem = parseInt(document.getElementById('assignSemSelect').value, 10);
  const branch = document.getElementById('assignBranchSelect').value;
  const selectedOpt = examSelect.options[examSelect.selectedIndex];
  const examName = selectedOpt && selectedOpt.value ? selectedOpt.dataset.name : 'selected exam';
  
  let count = 0;
  if (sem === 0 && branch === '') {
    count = totalStudentsInDb;
  } else {
    for (const c of cohortData) {
      const matchSem = sem === 0 || parseInt(c.semester, 10) === sem;
      const matchBranch = branch === '' || c.branch === branch;
      if (matchSem && matchBranch) {
        count += parseInt(c.cnt, 10);
      }
    }
  }

  const previewBox = document.getElementById('assignLivePreviewBox');
  const previewText = document.getElementById('assignPreviewText');
  const warningBox = document.getElementById('assignAllWarning');
  const confirmBtn = document.getElementById('confirmAssignBtn');

  const isAll = sem === 0 && branch === '';

  if (isAll) {
    previewBox.className = 'p-3 mb-4 rounded-3 border border-danger bg-danger-subtle text-danger';
    previewText.textContent = `Warning: ${count} students will move to ${examName}`;
    warningBox.style.display = 'block';
  } else {
    previewBox.className = 'p-3 mb-4 rounded-3 border bg-light text-main';
    previewText.textContent = `${count} student${count === 1 ? '' : 's'} will move to ${examName}`;
    warningBox.style.display = 'none';
  }
}

// Client-side search, filtering, and row-count pagination
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('examSearchInput');
  const semFilter = document.getElementById('examSemFilter');
  const statusFilter = document.getElementById('examStatusFilter');
  const perPageSelect = document.getElementById('perPageSelector');
  const tbody = document.getElementById('examsTableBody');
  const rows = Array.from(tbody.querySelectorAll('tr[data-exam-id]'));
  const countLabel = document.getElementById('tableShowingCount');
  const prevBtn = document.getElementById('prevPageBtn');
  const nextBtn = document.getElementById('nextPageBtn');
  const pageIndicator = document.getElementById('pageIndicator');

  let currentPage = 1;

  function filterAndPaginate() {
    const q = searchInput.value.toLowerCase().trim();
    const sem = semFilter.value;
    const status = statusFilter.value;
    const perPage = parseInt(perPageSelect.value, 10);

    const filtered = rows.filter(r => {
      const name = r.dataset.name;
      const rSem = r.dataset.sem;
      const rStatus = r.dataset.status;
      const matchQ = !q || name.includes(q);
      const matchSem = !sem || rSem === sem;
      const matchStatus = !status || rStatus === status;
      return matchQ && matchSem && matchStatus;
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
  statusFilter.addEventListener('change', () => { currentPage = 1; filterAndPaginate(); });
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