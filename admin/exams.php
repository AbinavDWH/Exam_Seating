<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

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

$exams = db()->query("SELECT e.*,
    (SELECT COUNT(DISTINCT se_stu.student_id) FROM student_exams se_stu WHERE se_stu.exam_id = e.id) AS students,
    (SELECT COUNT(*) FROM seating se WHERE se.exam_id = e.id) AS assigned
  FROM exams e ORDER BY e.exam_date DESC")->fetchAll();

$branches = db()->query("SELECT DISTINCT branch FROM students ORDER BY branch")->fetchAll(PDO::FETCH_COLUMN);
$semesters = db()->query("SELECT DISTINCT semester FROM students ORDER BY semester")->fetchAll(PDO::FETCH_COLUMN);
?>

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
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <!-- Create Session Card -->
    <div class="table-card mb-4">
      <div class="d-flex align-items-center gap-2 mb-3">
        <div class="text-primary"><?= svg_icon('plus', '', 20) ?></div>
        <h6 class="fw-bold mb-0">Create Exam Session</h6>
      </div>
      <form method="post" action="exams.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Exam Name</label>
          <input name="exam_name" class="form-control" placeholder="e.g. End Semester Nov 2026" required>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-7">
            <label class="form-label small fw-semibold">Date</label>
            <input name="exam_date" type="date" class="form-control" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
          </div>
          <div class="col-5">
            <label class="form-label small fw-semibold">Start Time</label>
            <input name="start_time" type="time" class="form-control" value="09:30">
          </div>
        </div>
        <div class="row g-2 mb-4">
          <div class="col-6">
            <label class="form-label small fw-semibold">Target Sem</label>
            <input name="semester" type="number" min="1" max="8" class="form-control" placeholder="e.g. 3" required>
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Status</label>
            <select name="status" class="form-select">
              <option value="upcoming">Upcoming</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
            </select>
          </div>
        </div>
        <button class="btn btn-grad w-100">
          <?= svg_icon('plus', 'me-1', 16) ?>Create Exam Session
        </button>
      </form>
    </div>

    <!-- Bulk Assign Card -->
    <div class="table-card">
      <div class="d-flex align-items-center gap-2 mb-2">
        <div class="text-primary"><?= svg_icon('users', '', 20) ?></div>
        <h6 class="fw-bold mb-0">Bulk-Assign Students</h6>
      </div>
      <p class="text-muted small mb-3">Assign cohorts across multiple departments to an exam session.</p>
      <form method="post" action="exams.php" class="row g-3" id="bulkAssignForm" onsubmit="return handleBulkAssignSubmit(event);">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="assign">
        <div class="col-12">
          <label class="form-label small text-muted mb-1">Target Exam Session</label>
          <select name="exam_id" id="bulkExamSelect" class="form-select" required>
            <?php foreach ($exams as $e): ?>
              <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['exam_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label small text-muted mb-1">Semester</label>
          <select name="semester" id="bulkSemSelect" class="form-select">
            <option value="0">All Semesters</option>
            <?php foreach ($semesters as $s): ?>
              <option value="<?= (int)$s ?>">Sem <?= (int)$s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label small text-muted mb-1">Department</label>
          <select name="branch" id="bulkBranchSelect" class="form-select">
            <option value="">All Depts</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12" id="confirmAllWrapper" style="display:none;">
          <div class="form-check p-2 bg-warning-subtle rounded border border-warning text-warning-emphasis">
            <input class="form-check-input ms-0 me-2" type="checkbox" name="confirm_all_students" value="1" id="confirmAllCheck">
            <label class="form-check-label small fw-semibold" for="confirmAllCheck">
              ⚠️ I confirm assigning ALL students across ALL departments to this exam
            </label>
          </div>
        </div>
        <div class="col-12 mt-3">
          <button class="btn btn-primary-soft w-100 justify-content-center">
            <?= svg_icon('users', 'me-1.5', 16) ?>Assign Cohort to Exam
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Active Exam Sessions</h6>
        <span class="text-muted small"><?= count($exams) ?> session<?= count($exams) === 1 ? '' : 's' ?></span>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
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
            $status = strtolower($e['status']);
            $statusClass = ($status === 'ongoing') ? 'status-ongoing' : (($status === 'completed' || $status === 'done') ? 'status-completed' : 'status-upcoming');
            $statusLabel = ($status === 'completed') ? 'Done' : ucfirst($status);
            $fillColor = ($pct >= 100) ? '#10b981' : (($pct > 0) ? '#4f46e5' : '#cbd5e1');
          ?>
            <tr>
              <td class="col-exam">
                <div class="fw-semibold text-main"><?= htmlspecialchars($e['exam_name']) ?></div>
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
                <div class="seated-progress-wrapper" style="min-width:120px;">
                  <div class="seated-progress-info">
                    <span class="seated-progress-count"><?= $assigned ?> / <?= $total ?></span>
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
                <div class="action-buttons-group">
                  <a href="generate.php?exam_id=<?= $e['id'] ?>" class="btn-action btn-action-generate" title="Generate Seating">
                    <?= svg_icon('magic', '', 15) ?>
                  </a>
                  <a href="print_plan.php?exam_id=<?= $e['id'] ?>" class="btn-action btn-action-view" title="Print Plan">
                    <?= svg_icon('printer', '', 15) ?>
                  </a>
                  <a href="../api/export.php?exam_id=<?= $e['id'] ?>" class="btn-action btn-action-edit" title="Export CSV">
                    <?= svg_icon('download', '', 15) ?>
                  </a>
                  <button type="button" class="btn-action btn-action-delete"
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
          <?php if (!$exams): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-5">
                <div class="empty-state-icon mx-auto"><?= svg_icon('calendar', '', 26) ?></div>
                <div class="fw-bold mt-2">No exam sessions created yet</div>
                <div class="small text-muted">Use the form on the left to create your first session.</div>
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Delete Confirmation Popup -->
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
      <form method="post" action="exams.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteExamId" value="">
        <div class="modal-body py-3">
          <p class="text-body mb-2">Are you sure you want to delete <strong id="deleteExamName">this session</strong>?</p>
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

<script>
function updateBulkConfirmState() {
  const sem = document.getElementById('bulkSemSelect').value;
  const branch = document.getElementById('bulkBranchSelect').value;
  const wrap = document.getElementById('confirmAllWrapper');
  const chk = document.getElementById('confirmAllCheck');
  if (sem === '0' && branch === '') {
    wrap.style.display = 'block';
  } else {
    wrap.style.display = 'none';
    chk.checked = false;
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
      alert('⚠️ To assign ALL students across ALL departments, you must check the confirmation box below the dropdowns.');
      e.preventDefault();
      return false;
    }
  }

  let msg = `Assign ${branch ? branch : 'ALL departments'} (${sem == 0 ? 'ALL semesters' : 'Semester ' + sem}) to ${examName}?`;
  if (sem == 0 && !branch) {
    msg = `⚠️ WARNING: You have selected "All Semesters" and "All Depts".\n\nThis will assign ALL students across every department and semester to "${examName}".\n\nAre you sure you want to proceed?`;
  }
  if (!confirm(msg)) {
    e.preventDefault();
    return false;
  }
  return true;
}

document.addEventListener('DOMContentLoaded', () => {
  const semSel = document.getElementById('bulkSemSelect');
  const branchSel = document.getElementById('bulkBranchSelect');
  if (semSel && branchSel) {
    semSel.addEventListener('change', updateBulkConfirmState);
    branchSel.addEventListener('change', updateBulkConfirmState);
    updateBulkConfirmState();
  }

  const deleteModal = document.getElementById('deleteExamModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      document.getElementById('deleteExamId').value = btn.getAttribute('data-id');
      document.getElementById('deleteExamName').textContent = `"${btn.getAttribute('data-name')}"`;
    });
  }
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>