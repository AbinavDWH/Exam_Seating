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
    } elseif ($action === 'assign') {          // bulk-assign students to an exam
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

        // Server-side guard: when assigning ALL semesters and ALL depts, require explicit confirmation
        if ($sem === 0 && $branch === '' && !$confirmAll) {
            header('Location: exams.php?toast=' . urlencode('Assigning all students across all departments requires server confirmation. Check the confirmation box before proceeding.'));
            exit;
        }

        // Insert into student_exams link table for multi-exam support
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

        header('Location: exams.php?toast=' . urlencode('Students successfully assigned to exam session'));
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
        $status = in_array($_POST['status'] ?? '', ['upcoming', 'ongoing', 'completed'], true) ? $_POST['status'] : 'upcoming';

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

$pageTitle = 'Exams';
require __DIR__ . '/_header.php';

$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

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

$stmt = db()->prepare("SELECT e.*,
    (SELECT COUNT(DISTINCT se_stu.student_id) FROM student_exams se_stu WHERE se_stu.exam_id = e.id) AS students,
    (SELECT COUNT(*) FROM seating se WHERE se.exam_id = e.id) AS assigned
  FROM exams e
  $whereSql
  ORDER BY e.exam_date DESC");
$stmt->execute($params);
$exams = $stmt->fetchAll();

$allExamsList = db()->query("SELECT id, exam_name, semester FROM exams ORDER BY exam_date DESC")->fetchAll();
$branches = db()->query("SELECT DISTINCT branch FROM students ORDER BY branch")->fetchAll(PDO::FETCH_COLUMN);
$semesters = db()->query("SELECT DISTINCT semester FROM students ORDER BY semester")->fetchAll(PDO::FETCH_COLUMN);

$totalSessions = (int)db()->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$upcomingCount = (int)db()->query("SELECT COUNT(*) FROM exams WHERE exam_date > DATE('now')")->fetchColumn();
$totalAssignedStudents = (int)db()->query("SELECT COUNT(DISTINCT student_id) FROM student_exams")->fetchColumn();
$totalSeated = (int)db()->query("SELECT COUNT(*) FROM seating")->fetchColumn();
?>

<!-- Page Header with Separated Top-Right Stats -->
<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= svg_icon('calendar', 'text-primary', 26) ?>
      Exam Sessions &amp; Schedules
    </h1>
    <div class="page-subtitle">
      <span>Schedule examination sessions, bulk-assign student cohorts, and manage status</span>
    </div>
  </div>
  <div class="d-flex gap-3 flex-wrap">
    <div class="stat-pill px-3 py-2 text-start" style="min-width: 150px;">
      <span style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Sessions</span>
      <b style="font-size: 1.25rem; color: #0f172a; margin-top: 2px;"><?= number_format($totalSessions) ?></b>
      <span class="text-muted small" style="font-size: 0.76rem;"><?= number_format($upcomingCount) ?> Upcoming</span>
    </div>
    <div class="stat-pill px-3 py-2 text-start" style="min-width: 150px;">
      <span style="font-size: 0.72rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Allocated Seating</span>
      <b style="font-size: 1.25rem; color: #ea580c; margin-top: 2px;"><?= number_format($totalSeated) ?></b>
      <span class="text-muted small" style="font-size: 0.76rem;">Seats Booked</span>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Left Side: Forms (Create Session & Bulk Assign) -->
  <div class="col-lg-5 col-xl-4">
    <!-- Create Session Card with Schedule Summary -->
    <div class="table-card mb-4">
      <div class="card-title-header">
        <div class="card-title-icon">
          <?= svg_icon('plus', '', 20) ?>
        </div>
        <div class="card-title-text">
          <h6>Create Exam Session</h6>
          <p>Schedule a new examination session and configure target semester.</p>
        </div>
      </div>

      <form method="post" action="exams.php" id="createExamForm" onsubmit="return validateExamForm(event)">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        
        <div class="mb-3">
          <label class="form-label" for="exam_name">Exam Name</label>
          <input name="exam_name" id="exam_name" class="form-control" placeholder="e.g. End Semester Nov 2026" required value="End Semester Examinations Nov 2026" oninput="updateScheduleSummary()">
          <div class="form-text small text-danger" id="examNameError" style="display:none;">Please enter an exam title with at least 3 characters.</div>
        </div>

        <div class="row g-2 mb-2">
          <div class="col-7">
            <label class="form-label" for="exam_date">Exam Date</label>
            <input name="exam_date" id="exam_date" type="date" class="form-control" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>" oninput="updateScheduleSummary()">
          </div>
          <div class="col-5">
            <label class="form-label" for="start_time">Start Time</label>
            <input name="start_time" id="start_time" type="time" class="form-control" value="09:30" oninput="updateScheduleSummary()">
          </div>
        </div>

        <!-- Live Friendly DateTime Preview -->
        <div class="mb-3">
          <div class="datetime-preview-chip" id="examDateTimePreview">
            <?= svg_icon('clock', 'text-primary', 14) ?>
            <span id="dtPreviewText">Calculating date preview...</span>
          </div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label" for="exam_sem">Target Sem</label>
            <input name="semester" id="exam_sem" type="number" min="1" max="8" class="form-control" placeholder="e.g. 5" value="5" required oninput="updateScheduleSummary()">
          </div>
          <div class="col-6">
            <label class="form-label" for="exam_status">Status</label>
            <select name="status" id="exam_status" class="form-select" onchange="updateScheduleSummary()">
              <option value="upcoming" selected>Upcoming</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
            </select>
          </div>
        </div>

        <!-- Live Schedule Summary Box -->
        <div class="p-3 mb-3 rounded-3 border bg-light" id="scheduleSummaryBox">
          <div class="d-flex justify-content-between align-items-center mb-1.5">
            <span class="small fw-bold text-muted text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Schedule Summary</span>
            <span class="status-pill status-upcoming py-0.5 px-2" style="font-size: 0.7rem;" id="summaryStatusPill">
              <span class="status-dot"></span>
              <span id="summaryStatusText">Upcoming</span>
            </span>
          </div>
          <div class="fw-bold text-main mb-1" id="summaryExamName" style="font-size: 0.95rem;">End Semester Examinations Nov 2026</div>
          <div class="text-muted small d-flex flex-column gap-1" style="font-size: 0.8rem;">
            <div class="d-flex align-items-center gap-1.5">
              <?= svg_icon('calendar', 'text-muted', 14) ?>
              <span id="summaryDateText">Loading...</span>
              <span class="text-muted">·</span>
              <span id="summaryTimeText">09:30 AM</span>
              <span class="badge bg-secondary-subtle text-secondary ms-auto" id="summaryCountdown">In 7 days</span>
            </div>
            <div class="d-flex align-items-center gap-1.5">
              <?= svg_icon('layers', 'text-muted', 14) ?>
              <span>Target Cohort: <strong class="text-main" id="summarySemText">Semester 5</strong></span>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-grad w-100 py-2.5">
          <?= svg_icon('plus', 'me-1', 16) ?>Create Exam Session
        </button>
      </form>
    </div>

    <!-- Bulk Assign Card with Safety Gating & SVG Icons -->
    <div class="table-card">
      <div class="card-title-header">
        <div class="card-title-icon">
          <?= svg_icon('users', '', 20) ?>
        </div>
        <div class="card-title-text">
          <h6>Bulk-Assign Students</h6>
          <p>Assign cohorts across departments to a scheduled exam session.</p>
        </div>
      </div>

      <form method="post" action="exams.php" class="row g-3" id="bulkAssignForm" onsubmit="return handleBulkAssignSubmit(event);">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="assign">
        
        <div class="col-12">
          <label class="form-label" for="bulkExamSelect">Target Exam Session</label>
          <select name="exam_id" id="bulkExamSelect" class="form-select" required>
            <?php foreach ($allExamsList as $e): ?>
              <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['exam_name']) ?> (Sem <?= (int)$e['semester'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6">
          <label class="form-label" for="bulkSemSelect">Semester</label>
          <select name="semester" id="bulkSemSelect" class="form-select" onchange="updateBulkConfirmState()">
            <option value="0">All Semesters</option>
            <?php foreach ($semesters as $s): ?>
              <option value="<?= (int)$s ?>">Sem <?= (int)$s ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6">
          <label class="form-label" for="bulkBranchSelect">Department</label>
          <select name="branch" id="bulkBranchSelect" class="form-select" onchange="updateBulkConfirmState()">
            <option value="">All Depts</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Safe Confirmation Box for All Semesters + All Depts -->
        <div class="col-12" id="confirmAllWrapper" style="display:none;">
          <div class="p-3 bg-warning-subtle rounded-3 border border-warning text-warning-emphasis d-flex align-items-start gap-2.5">
            <div class="mt-0.5 text-warning-emphasis flex-shrink-0">
              <?= svg_icon('alert-triangle', '', 18) ?>
            </div>
            <div>
              <div class="fw-bold small mb-1">Campus-Wide Cohort Assignment</div>
              <div class="small mb-2" style="font-size: 0.8rem; line-height: 1.4;">
                You selected <strong>All Semesters</strong> and <strong>All Departments</strong>. This will assign the entire registered student body to this exam session.
              </div>
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="confirm_all_students" value="1" id="confirmAllCheck" onchange="updateConfirmAllCheck()">
                <label class="form-check-label small fw-semibold text-dark" for="confirmAllCheck">
                  I confirm assigning ALL students across ALL departments
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 mt-3">
          <button type="submit" class="btn btn-primary-soft w-100 justify-content-center py-2.5" id="bulkAssignBtn">
            <?= svg_icon('users', 'me-1.5', 16) ?>Assign Cohort to Exam
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Right Side: Active Exam Sessions Table -->
  <div class="col-lg-7 col-xl-8">
    <div class="table-card">
      <!-- Card Header with chip moved inside (Item 7) -->
      <div class="card-title-header mb-3">
        <div class="card-title-icon">
          <?= svg_icon('calendar', '', 20) ?>
        </div>
        <div class="card-title-text flex-grow-1">
          <div class="d-flex align-items-center gap-2">
            <h6 class="mb-0">Active Exam Sessions</h6>
            <span class="badge bg-secondary-subtle text-secondary border px-2 py-0.5 rounded-pill" style="font-size: 0.75rem;">
              <?= count($exams) ?> <?= count($exams) === 1 ? 'session' : 'sessions' ?>
            </span>
          </div>
          <p class="mb-0">Monitor cohort enrollment, seating progress, and download session plans.</p>
        </div>
      </div>

      <!-- Table Toolbar -->
      <form method="get" action="exams.php" class="table-toolbar-row">
        <div class="toolbar-search position-relative">
          <span class="table-search-icon"><?= svg_icon('search', '', 15) ?></span>
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control table-search-input" placeholder="Search sessions by name..." oninput="filterExamsLocally(this.value)">
        </div>
        <div class="toolbar-filters">
          <select name="status" class="form-select table-filter-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="upcoming" <?= $statusFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
            <option value="ongoing" <?= $statusFilter === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
          </select>
          <?php if ($q !== '' || $statusFilter !== ''): ?>
            <a href="exams.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters">Clear</a>
          <?php endif; ?>
        </div>
        <span class="toolbar-count-badge ms-auto" id="examsCountBadge">
          <?= count($exams) ?> <?= count($exams) === 1 ? 'Session' : 'Sessions' ?>
        </span>
      </form>

      <!-- Table with table-hover (Item 8) -->
      <div class="table-responsive">
        <table class="table align-middle table-hover mb-0" id="examsTable">
          <thead>
            <tr>
              <th class="col-exam">Exam</th>
              <th class="col-date">Date &amp; Time</th>
              <th class="col-sem">Primary Sem</th>
              <th class="col-allocated">Seated Status</th>
              <th class="col-status">Status</th>
              <th class="col-actions text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($exams as $e):
            $assigned = (int)$e['assigned'];
            $total = (int)$e['students'];
            $pct = $total > 0 ? (int)round(($assigned / $total) * 100) : 0;
            
            // Item 4: Auto-compute status from today's date
            $examDateStr = $e['exam_date'];
            if ($examDateStr < $today) {
                $statusClass = 'status-completed'; // grey
                $statusLabel = 'Completed';
            } elseif ($examDateStr === $today) {
                $statusClass = 'status-ongoing';   // blue
                $statusLabel = 'Ongoing';
            } else {
                $statusClass = 'status-upcoming';  // soft amber
                $statusLabel = 'Upcoming';
            }

            // Progress bar color by value (sage fill; dark sage at 100%)
            if ($pct >= 100) {
                $fillColor = '#43522C'; // dark sage at 100%
            } elseif ($pct > 0) {
                $fillColor = '#8B9A6E'; // brand sage
            } else {
                $fillColor = '#D8D3CA'; // beige/neutral
            }
            $progressTooltip = number_format($assigned) . ' of ' . number_format($total) . ' students seated (' . $pct . '%)';
          ?>
            <tr class="exam-row" data-name="<?= strtolower(htmlspecialchars($e['exam_name'])) ?>">
              <td class="col-exam">
                <div class="fw-semibold text-main"><?= htmlspecialchars($e['exam_name']) ?></div>
                <div class="text-muted small">Sem <?= (int)$e['semester'] ?> Cohort</div>
              </td>
              <td class="col-date">
                <div class="fw-medium text-main"><?= date('d M Y', strtotime($e['exam_date'])) ?></div>
                <div class="text-muted small"><?= date('h:i A', strtotime($e['start_time'])) ?></div>
              </td>
              <td class="col-sem">
                <span class="badge bg-light text-dark border px-2.5 py-1.5" style="border-radius: 8px;">
                  Sem <?= (int)$e['semester'] ?>
                </span>
              </td>
              <td class="col-allocated">
                <!-- Seated progress bar with tooltip (Item 5) -->
                <div class="seated-progress-wrapper" style="min-width:120px;" data-bs-toggle="tooltip" title="<?= htmlspecialchars($progressTooltip) ?>">
                  <div class="seated-progress-info">
                    <span class="seated-progress-count"><?= number_format($assigned) ?> / <?= number_format($total) ?></span>
                    <span class="seated-progress-pct"><?= $pct ?>%</span>
                  </div>
                  <div class="seated-progress-track">
                    <div class="seated-progress-fill" style="width: <?= $pct ?>%; background: <?= $fillColor ?>;"></div>
                  </div>
                </div>
              </td>
              <td class="col-status">
                <span class="status-pill <?= $statusClass ?>">
                  <span class="status-dot"></span>
                  <?= $statusLabel ?>
                </span>
              </td>
              <td class="col-actions text-end text-nowrap">
                <!-- Four action icons per row with tooltips (Item 6) -->
                <div class="action-buttons-group">
                  <a href="swap.php?exam_id=<?= $e['id'] ?>" class="btn-action btn-action-swap" data-bs-toggle="tooltip" title="Swap seats">
                    <?= svg_icon('sort', '', 15) ?>
                  </a>
                  <a href="print_plan.php?exam_id=<?= $e['id'] ?>" class="btn-action btn-action-view" data-bs-toggle="tooltip" title="Print sheets">
                    <?= svg_icon('printer', '', 15) ?>
                  </a>
                  <a href="../api/export.php?exam_id=<?= $e['id'] ?>" class="btn-action btn-action-edit" data-bs-toggle="tooltip" title="Export CSV">
                    <?= svg_icon('download', '', 15) ?>
                  </a>
                  <button type="button" class="btn-action btn-action-delete"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteExamModal"
                          title="Delete"
                          data-id="<?= $e['id'] ?>"
                          data-name="<?= htmlspecialchars($e['exam_name'], ENT_QUOTES) ?>">
                    <?= svg_icon('trash', '', 15) ?>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$exams): ?>
            <tr id="emptyExamsRow">
              <td colspan="6" class="text-center text-muted py-5">
                <div class="empty-state-icon mx-auto"><?= svg_icon('calendar', '', 26) ?></div>
                <div class="fw-bold mt-2">No exam sessions yet — create one above</div>
                <div class="small text-muted">Use the form on the left to schedule your first examination session.</div>
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Delete Confirmation Popup (Items 1, 2, 3, 26) -->
<div class="modal fade" id="deleteExamModal" tabindex="-1" aria-labelledby="deleteExamModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteExamModalLabel">
          <?= svg_icon('alert-triangle', '', 20) ?>
          <span id="deleteExamModalTitleHeading">Delete Exam Session</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="exams.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteExamId" value="">
        <div class="modal-body py-3">
          <p class="text-body mb-2 fs-6">
            Delete <strong id="deleteExamName" class="text-dark">this session</strong>?
          </p>
          <div class="p-3 bg-danger-subtle rounded-3 text-danger small">
            <?= svg_icon('alert-triangle', 'me-1', 15) ?>
            This will permanently remove the exam session and clear all student seating assignments for it.
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger px-3 shadow-sm">Yes, Delete Exam</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function formatTime12(timeStr) {
  if (!timeStr) return '09:30 AM';
  const parts = timeStr.split(':');
  let h = parseInt(parts[0], 10);
  const m = parts[1] || '00';
  const ampm = h >= 12 ? 'PM' : 'AM';
  h = h % 12;
  h = h ? h : 12;
  return `${h.toString().padStart(2, '0')}:${m} ${ampm}`;
}

function updateScheduleSummary() {
  const nameInput = document.getElementById('exam_name');
  const dateInput = document.getElementById('exam_date');
  const timeInput = document.getElementById('start_time');
  const semInput = document.getElementById('exam_sem');
  const statusSelect = document.getElementById('exam_status');

  const nameVal = nameInput ? nameInput.value.trim() : '';
  const dateVal = dateInput ? dateInput.value : '';
  const timeVal = timeInput ? timeInput.value : '';
  const semVal = semInput ? semInput.value : '1';
  const statusVal = statusSelect ? statusSelect.value : 'upcoming';

  let formattedDate = 'Select date';
  let countdownStr = '';

  if (dateVal) {
    const parts = dateVal.split('-');
    if (parts.length === 3) {
      const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
      const options = { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' };
      formattedDate = d.toLocaleDateString('en-GB', options);

      const today = new Date();
      today.setHours(0,0,0,0);
      d.setHours(0,0,0,0);
      const diffTime = d - today;
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      if (diffDays > 1) {
        countdownStr = `In ${diffDays} days`;
      } else if (diffDays === 1) {
        countdownStr = 'Tomorrow';
      } else if (diffDays === 0) {
        countdownStr = 'Today';
      } else {
        countdownStr = `${Math.abs(diffDays)}d ago`;
      }
    }
  }

  const time12 = formatTime12(timeVal);

  const dtPreview = document.getElementById('dtPreviewText');
  if (dtPreview) {
    dtPreview.textContent = `${formattedDate} · ${time12} ${countdownStr ? '(' + countdownStr + ')' : ''}`;
  }

  const summaryName = document.getElementById('summaryExamName');
  if (summaryName) {
    summaryName.textContent = nameVal || 'Untitled Exam Session';
  }

  const summaryDate = document.getElementById('summaryDateText');
  if (summaryDate) {
    summaryDate.textContent = formattedDate;
  }

  const summaryTime = document.getElementById('summaryTimeText');
  if (summaryTime) {
    summaryTime.textContent = time12;
  }

  const summaryCountdown = document.getElementById('summaryCountdown');
  if (summaryCountdown) {
    summaryCountdown.textContent = countdownStr || '—';
  }

  const summarySem = document.getElementById('summarySemText');
  if (summarySem) {
    summarySem.textContent = `Semester ${semVal}`;
  }

  const summaryStatusPill = document.getElementById('summaryStatusPill');
  const summaryStatusText = document.getElementById('summaryStatusText');
  if (summaryStatusPill && summaryStatusText) {
    summaryStatusPill.className = `status-pill status-${statusVal} py-0.5 px-2`;
    summaryStatusText.textContent = statusVal.charAt(0).toUpperCase() + statusVal.slice(1);
  }
}

function validateExamForm(e) {
  const nameInput = document.getElementById('exam_name');
  const err = document.getElementById('examNameError');
  if (!nameInput || nameInput.value.trim().length < 3) {
    if (err) err.style.display = 'block';
    if (nameInput) nameInput.focus();
    e.preventDefault();
    return false;
  }
  if (err) err.style.display = 'none';
  return true;
}

function updateBulkConfirmState() {
  const sem = document.getElementById('bulkSemSelect').value;
  const branch = document.getElementById('bulkBranchSelect').value;
  const wrap = document.getElementById('confirmAllWrapper');
  const chk = document.getElementById('confirmAllCheck');
  const btn = document.getElementById('bulkAssignBtn');

  if (sem === '0' && branch === '') {
    wrap.style.display = 'block';
    if (!chk.checked) {
      btn.disabled = true;
      btn.classList.add('disabled');
      btn.style.opacity = '0.6';
      btn.style.cursor = 'not-allowed';
    } else {
      btn.disabled = false;
      btn.classList.remove('disabled');
      btn.style.opacity = '1';
      btn.style.cursor = 'pointer';
    }
  } else {
    wrap.style.display = 'none';
    chk.checked = false;
    btn.disabled = false;
    btn.classList.remove('disabled');
    btn.style.opacity = '1';
    btn.style.cursor = 'pointer';
  }
}

function updateConfirmAllCheck() {
  const chk = document.getElementById('confirmAllCheck');
  const btn = document.getElementById('bulkAssignBtn');
  if (chk.checked) {
    btn.disabled = false;
    btn.classList.remove('disabled');
    btn.style.opacity = '1';
    btn.style.cursor = 'pointer';
  } else {
    btn.disabled = true;
    btn.classList.add('disabled');
    btn.style.opacity = '0.6';
    btn.style.cursor = 'not-allowed';
  }
}

function handleBulkAssignSubmit(e) {
  const form = e.target;
  const sem = form.semester.value;
  const branch = form.branch.value;
  const examSelect = form.exam_id;
  const examName = examSelect.options[examSelect.selectedIndex]?.text || 'selected exam session';

  if (sem == 0 && !branch) {
    const chk = form.confirm_all_students;
    if (!chk || !chk.checked) {
      alert('To assign ALL students across ALL departments, you must check the confirmation box below.');
      e.preventDefault();
      return false;
    }
  }

  let msg = `Assign ${branch ? branch : 'ALL departments'} (${sem == 0 ? 'ALL semesters' : 'Semester ' + sem}) to ${examName}?`;
  if (sem == 0 && !branch) {
    msg = `CONFIRMATION: You are assigning ALL students across every department and semester to "${examName}".\n\nProceed with bulk cohort enrollment?`;
  }
  if (!confirm(msg)) {
    e.preventDefault();
    return false;
  }
  return true;
}

function filterExamsLocally(query) {
  const q = query.toLowerCase().trim();
  const rows = document.querySelectorAll('.exam-row');
  let visibleCount = 0;

  rows.forEach(row => {
    const name = row.getAttribute('data-name') || '';
    if (!q || name.includes(q)) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const badge = document.getElementById('examsCountBadge');
  if (badge) {
    badge.textContent = `${visibleCount} ${visibleCount === 1 ? 'Session' : 'Sessions'}`;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  updateScheduleSummary();
  updateBulkConfirmState();

  // Initialize Bootstrap Tooltips (Items 5, 6, 27)
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));

  // Delete Modal Setup (Items 1, 2, 3)
  const deleteModal = document.getElementById('deleteExamModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name');
      document.getElementById('deleteExamId').value = id;
      document.getElementById('deleteExamName').textContent = name;
      document.getElementById('deleteExamModalTitleHeading').textContent = `Delete ${name}?`;
    });
  }
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>