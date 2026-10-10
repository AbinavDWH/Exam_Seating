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
        if ($id > 0) {
            $pdo->prepare("DELETE FROM student_exams WHERE student_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM seating WHERE student_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
        }
        header('Location: students.php?toast=' . urlencode('Student deleted'));
        exit;
    } else {
        $roll = strtoupper(trim((string)($_POST['roll_no'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $branch = strtoupper(trim((string)($_POST['branch'] ?? '')));
        $sem = (int)($_POST['semester'] ?? 0);
        if ($sem < 1 || $sem > 8) {
            header('Location: students.php?toast=' . urlencode('Semester must be between 1 and 8'));
            exit;
        }
        $yr = (int)ceil($sem / 2);
        $dobRaw = trim((string)($_POST['dob'] ?? ''));
        $dob = ($dobRaw !== '' && strtotime($dobRaw) !== false) ? date('Y-m-d', (int)strtotime($dobRaw)) : null;
        $examCode = trim((string)($_POST['exam_code'] ?? ''));
        if ($examCode === '') {
            $examCode = $branch . '-S' . $sem;
        }
        $examId = !empty($_POST['exam_id']) ? (int)$_POST['exam_id'] : null;

        if ($roll === '' || $name === '' || $branch === '') {
            header('Location: students.php?toast=' . urlencode('Roll number, name, and branch are required'));
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO students (roll_no, name, dob, branch, dept, semester, year, exam_code, exam_id)
                               VALUES (?,?,?,?,?,?,?,?,?)
                               ON CONFLICT(roll_no) DO UPDATE SET
                                   name = excluded.name,
                                   dob = excluded.dob,
                                   branch = excluded.branch,
                                   dept = excluded.dept,
                                   semester = excluded.semester,
                                   year = excluded.year,
                                   exam_code = excluded.exam_code,
                                   exam_id = excluded.exam_id");
        $stmt->execute([$roll, $name, $dob, $branch, $branch, $sem, $yr, $examCode, $examId]);

        // Keep student_exams link table updated
        if ($examId) {
            $stuId = (int)$pdo->query("SELECT id FROM students WHERE roll_no = " . $pdo->quote($roll))->fetchColumn();
            if ($stuId > 0) {
                $pdo->prepare("INSERT INTO student_exams (student_id, exam_id, exam_code) VALUES (?, ?, ?)
                               ON CONFLICT(student_id, exam_id) DO UPDATE SET exam_code = excluded.exam_code")
                    ->execute([$stuId, $examId, $examCode]);
            }
        }

        header('Location: students.php?toast=' . urlencode('Student saved'));
        exit;
    }
}

$pageTitle = 'Students';
require __DIR__ . '/_header.php';

$perPage = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$q = trim($_GET['q'] ?? '');
$branch = $_GET['branch'] ?? '';
$sem = isset($_GET['sem']) && $_GET['sem'] !== '' ? (int)$_GET['sem'] : null;
$code = trim($_GET['exam_code'] ?? '');

$sql = "SELECT s.*, e.exam_name FROM students s LEFT JOIN exams e ON e.id = s.exam_id WHERE 1";
$params = [];
if ($q !== '') {
    $sql .= " AND (s.roll_no LIKE ? OR s.name LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($branch !== '') {
    $sql .= " AND s.branch = ?";
    $params[] = $branch;
}
if ($sem !== null) {
    $sql .= " AND s.semester = ?";
    $params[] = $sem;
}
if ($code !== '') {
    $sql .= " AND s.exam_code = ?";
    $params[] = $code;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s WHERE 1" .
    ($q !== '' ? " AND (s.roll_no LIKE ? OR s.name LIKE ?)" : "") .
    ($branch !== '' ? " AND s.branch = ?" : "") .
    ($sem !== null ? " AND s.semester = ?" : "") .
    ($code !== '' ? " AND s.exam_code = ?" : ""));
$countStmt->execute($params);
$totalStudents = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalStudents / $perPage));

$stmt = $pdo->prepare("$sql ORDER BY s.semester, s.branch, s.roll_no LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$students = $stmt->fetchAll();

$recentStudents = $pdo->query("SELECT s.*, e.exam_name FROM students s LEFT JOIN exams e ON e.id = s.exam_id ORDER BY s.id DESC LIMIT 5")->fetchAll();

$branches = $pdo->query("SELECT DISTINCT branch FROM students ORDER BY branch")->fetchAll(PDO::FETCH_COLUMN);
$semesters = $pdo->query("SELECT DISTINCT semester FROM students ORDER BY semester")->fetchAll(PDO::FETCH_COLUMN);
$examCodes = $pdo->query("SELECT DISTINCT exam_code FROM students WHERE exam_code IS NOT NULL AND exam_code != '' ORDER BY exam_code")->fetchAll(PDO::FETCH_COLUMN);
$exams = $pdo->query("SELECT id, exam_name FROM exams ORDER BY exam_date DESC")->fetchAll();

// Aligned with student portal department color system (Point 31)
$deptBadgeClass = [
    'AI&DS' => 'badge-dept-aids',
    'AIDS'  => 'badge-dept-aids',
    'IT'    => 'badge-dept-it',
    'CSE'   => 'badge-dept-cse',
    'ECE'   => 'badge-dept-ece',
    'MECH'  => 'badge-dept-mech',
    'CIVIL' => 'badge-dept-civil',
    'EEE'   => 'badge-dept-eee',
    'EE'    => 'badge-dept-eee',
    'CSBS'  => 'badge-dept-csbs',
];
?>

<!-- Page Header with Separated Top-Right Stats (Point 11) -->
<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= svg_icon('users', 'text-primary', 26) ?>
      Students &amp; Cohorts
    </h1>
    <div class="page-subtitle">
      <span>Manage registered student enrollments, exam codes, and department allocations</span>
    </div>
  </div>
  <div class="d-flex gap-2">
    <div class="stat-pill px-3 py-2 text-start" style="min-width: 170px;">
      <span style="font-size: 0.72rem; color: #64748b; font-weight: 600;">Enrolled students</span>
      <b style="font-size: 1.25rem; color: #2B2E27; margin-top: 2px;"><?= number_format($totalStudents) ?></b>
      <span class="text-muted small" style="font-size: 0.76rem;">active roster</span>
    </div>
  </div>
</div>

<!-- Top Card: Add Student & Recently Added (5) (Items 1-9, 13-18, 24) -->
<div class="table-card mb-4">
  <div class="row g-4">
    <!-- Left Column: Add Student Form (col-lg-7) -->
    <div class="col-lg-7">
      <div class="card-title-header">
        <div class="card-title-icon">
          <?= svg_icon('plus', '', 20) ?>
        </div>
        <div class="card-title-text">
          <h6>Add Student Record</h6>
          <p>Register single student or update cohort details.</p>
        </div>
      </div>

      <form method="post" action="students.php" id="addStudentForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        
        <!-- 2-Column Paired Form Fields with 20-24px vertical rhythm -->
        <div class="row g-3 gy-lg-4">
          <div class="col-md-6">
            <label class="form-label" for="stu_roll">Roll Number</label>
            <input name="roll_no" id="stu_roll" class="form-control" placeholder="e.g. 2116241801001" required autocomplete="off">
            <div id="rollFormatError" class="roll-format-error d-none">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <span>Please enter a valid university roll number</span>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="stu_name">Full Name</label>
            <input name="name" id="stu_name" class="form-control" placeholder="e.g. Vishal Rajan" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="stu_branch">Department</label>
            <input name="branch" id="stu_branch" class="form-control" placeholder="e.g. AI&DS or IT" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="stu_sem">Semester</label>
            <input name="semester" id="stu_sem" type="number" min="1" max="8" class="form-control" placeholder="1 to 8" value="5" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="stu_dob">Date of Birth (Optional)</label>
            <input name="dob" id="stu_dob" type="date" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="stu_code">Exam Paper Code</label>
            <input name="exam_code" id="stu_code" class="form-control" placeholder="e.g. AD23532 (or auto)">
          </div>
          <div class="col-12">
            <label class="form-label" for="stu_exam">Assigned Exam Session (Optional)</label>
            <select name="exam_id" id="stu_exam" class="form-select">
              <option value="">— Select Scheduled Session —</option>
              <?php foreach ($exams as $e): ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['exam_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <button class="btn btn-grad w-100 py-2.5" id="saveStudentBtn" style="margin-top: 24px;">
          <?= svg_icon('plus', 'me-1', 16) ?><span id="saveBtnText">Save Student Record</span>
        </button>
      </form>
    </div>

    <!-- Right Column: Recently Added (5) (col-lg-5 ps-lg-4 border-start-lg) -->
    <div class="col-lg-5 ps-lg-4 border-start-lg d-flex flex-column justify-content-between">
      <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h6 class="fw-bold mb-0 text-main" style="font-size: 0.95rem;">Recently Added (5)</h6>
            <span class="text-muted small" style="font-size: 0.78rem;">Click row to load and edit details</span>
          </div>
          <a href="#studentRoster" class="small fw-semibold text-primary text-decoration-none">View all &rarr;</a>
        </div>

        <div class="d-flex flex-column gap-3" id="recentStudentsList">
          <?php if (!empty($recentStudents)): ?>
            <?php foreach ($recentStudents as $rs): ?>
              <?php
                $studentDataJson = htmlspecialchars(json_encode([
                    'roll_no' => $rs['roll_no'],
                    'name' => $rs['name'],
                    'branch' => $rs['branch'],
                    'semester' => $rs['semester'],
                    'dob' => $rs['dob'] ?? '2005-01-01',
                    'exam_code' => $rs['exam_code'] ?? '',
                    'exam_id' => $rs['exam_id'] ?? ''
                ]), ENT_QUOTES, 'UTF-8');
              ?>
              <div class="recent-student-card"
                   data-student="<?= $studentDataJson ?>"
                   onclick="loadRecentStudent(this)"
                   title="Click to edit this student in the form">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <div class="recent-student-roll"><?= htmlspecialchars($rs['roll_no']) ?></div>
                    <div class="recent-student-name"><?= htmlspecialchars($rs['name']) ?></div>
                  </div>
                  <span class="badge-dept-soft">
                    <?= htmlspecialchars($rs['branch']) ?> &middot; Sem <?= $rs['semester'] ?>
                  </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 pt-1.5 border-top border-light">
                  <span class="text-muted" style="font-size: 0.72rem;">Code: <?= htmlspecialchars($rs['exam_code'] ?: ($rs['branch'].'-S'.$rs['semester'])) ?></span>
                  <span class="recent-edit-hint">Click to edit &rarr;</span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="p-4 text-center text-muted border rounded-3 bg-light small">
              No recent enrollments recorded yet.
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="mt-3 pt-2 text-muted small" style="font-size: 0.76rem;">
        <i class="bi bi-info-circle me-1"></i>Saving an existing roll number updates their record automatically.
      </div>
    </div>
  </div>
</div>

<div class="row g-4" id="studentRoster">
  <!-- Left Side: CSV Bulk Upload (col-lg-4) -->
  <div class="col-lg-4">
    <!-- Drag-and-Drop CSV Bulk Upload (Points 19, 20) -->
    <div class="table-card">
      <div class="card-title-header">
        <div class="card-title-icon">
          <?= svg_icon('download', '', 18) ?>
        </div>
        <div class="card-title-text">
          <h6>Bulk Upload Roster (CSV)</h6>
          <p>Import thousands of students in seconds with duplicate safety.</p>
        </div>
      </div>

      <!-- Drag & Drop Zone (Point 19) -->
      <div class="csv-drop-zone" id="csvDropZone">
        <input type="file" id="csvFile" accept=".csv" class="csv-file-input" onchange="handleCsvFileSelected(this)">
        <div class="csv-drop-content">
          <div class="csv-drop-icon">
            <i class="bi bi-cloud-arrow-up"></i>
          </div>
          <div class="csv-drop-text">
            <strong>Drag CSV here or Browse</strong>
            <span>Supports university CSV rosters with headers</span>
          </div>
          <div id="csvSelectedFileName" class="csv-file-name d-none"></div>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <a href="#" onclick="downloadSampleCsv(event)" class="sample-csv-link">
          <i class="bi bi-file-earmark-arrow-down me-1"></i>Download sample CSV
        </a>
        <span class="text-muted small">Max 50MB &middot; UTF-8</span>
      </div>

      <button class="btn btn-grad w-100 py-2" id="uploadCsvBtn" onclick="uploadCsv()">
        <?= svg_icon('download', 'me-1', 16) ?>Upload &amp; Process CSV
      </button>

      <!-- Result Summary Box (Point 20) -->
      <div id="csvResultBox" class="mt-3 d-none"></div>
    </div>
  </div>

  <!-- Right Side: Students Table, Filters & Pagination (col-lg-8) -->
  <div class="col-lg-8">
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Registered students</h6>
        <span class="text-muted small">
          Showing <?= number_format($totalStudents > 0 ? $offset + 1 : 0) ?>–<?= number_format(min($totalStudents, $offset + $perPage)) ?> of <?= number_format($totalStudents) ?>
        </span>
      </div>

      <!-- Single Toolbar Row -->
      <form class="table-toolbar-row" method="get">
        <div class="toolbar-search">
          <input name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Search roll no or name…">
        </div>
        <div class="toolbar-filters">
          <select name="branch" class="form-select" style="min-width: 130px;">
            <option value="">All departments</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>" <?= $b===$branch?'selected':'' ?>><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="sem" class="form-select" style="min-width: 100px;">
            <option value="">All sem</option>
            <?php foreach ($semesters as $s): ?>
              <option value="<?= (int)$s ?>" <?= $sem===(int)$s?'selected':'' ?>>Sem <?= (int)$s ?></option>
            <?php endforeach; ?>
          </select>
          <select name="per_page" class="form-select" style="min-width: 90px;" onchange="this.form.submit()">
            <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10 / page</option>
            <option value="25" <?= $perPage === 25 ? 'selected' : '' ?>>25 / page</option>
            <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50 / page</option>
            <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100 / page</option>
          </select>
          <button class="btn btn-grad py-1 px-3" style="height: 44px;">Filter</button>
          <?php if ($q !== '' || $branch !== '' || $sem !== null || $perPage !== 25): ?>
            <a href="students.php" class="btn btn-outline-secondary py-1 px-3 d-inline-flex align-items-center" style="height: 44px;">Reset</a>
          <?php endif; ?>
        </div>
      </form>

      <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
        <table class="table align-middle mb-0">
          <thead style="position: sticky; top: 0; z-index: 2; background: var(--table-th-bg, #DFD6C8);">
            <tr>
              <th>Roll No</th>
              <th>Name</th>
              <th>Exam Code</th>
              <th>Department</th>
              <th>Semester</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($students as $s): ?>
            <tr>
              <td class="fw-bold text-main" style="font-family: 'Plus Jakarta Sans', system-ui, sans-serif; font-variant-numeric: tabular-nums; font-size: 0.88rem;">
                <?= htmlspecialchars($s['roll_no']) ?>
              </td>
              <td>
                <span class="fw-semibold"><?= htmlspecialchars($s['name']) ?></span>
              </td>
              <td>
                <span class="badge bg-light text-dark border px-2 py-1">
                  <?= htmlspecialchars($s['exam_code'] ?: ($s['branch'].'-S'.$s['semester'])) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= $deptBadgeClass[$s['branch']] ?? 'bg-secondary-subtle text-secondary' ?> px-2 py-1">
                  <?= htmlspecialchars($s['branch']) ?>
                </span>
              </td>
              <td class="small text-muted">
                Sem <?= (int)$s['semester'] ?> <span class="small">(Yr <?= ceil($s['semester'] / 2) ?>)</span>
              </td>
              <td class="text-end">
                <button type="button" class="btn-action btn-action-delete"
                        title="Delete student"
                        onclick="openDeleteStudentModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['roll_no'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')">
                  <?= svg_icon('trash', '', 15) ?>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$students): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-5">
                No student records found matching the filter criteria.
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination Component -->
      <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
        <span class="small text-muted">
          Showing <?= number_format($totalStudents > 0 ? $offset + 1 : 0) ?>–<?= number_format(min($totalStudents, $offset + $perPage)) ?> of <?= number_format($totalStudents) ?>
        </span>
        <?php if ($totalPages > 1): ?>
          <nav>
            <ul class="pagination pagination-sm mb-0">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&per_page=<?= $perPage ?>&q=<?= urlencode($q) ?>&branch=<?= urlencode($branch) ?>&sem=<?= urlencode($sem ?? '') ?>">Prev</a>
              </li>
              <li class="page-item active">
                <span class="page-link"><?= $page ?> / <?= $totalPages ?></span>
              </li>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&per_page=<?= $perPage ?>&q=<?= urlencode($q) ?>&branch=<?= urlencode($branch) ?>&sem=<?= urlencode($sem ?? '') ?>">Next</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- DeskMap Delete Confirmation Dialog -->
<div id="deleteStudentModal" class="deskmap-modal-backdrop" style="display:none;">
  <div class="deskmap-modal-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold m-0" style="color:var(--brand-red, #9C4632);">Delete student</h5>
      <button type="button" class="btn-close" onclick="closeDeleteStudentModal()" aria-label="Close"></button>
    </div>
    <form method="post" action="students.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="deleteStudentId" value="">
      <p id="deleteStudentMessage" class="text-body mb-3">Deleting this student removes their record and exam seating.</p>
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary px-3" onclick="closeDeleteStudentModal()">Cancel</button>
        <button type="submit" class="btn btn-danger px-3">Delete student</button>
      </div>
    </form>
  </div>
</div>

<script>
function openDeleteStudentModal(id, roll, name) {
  document.getElementById('deleteStudentId').value = id;
  document.getElementById('deleteStudentMessage').innerHTML = `<strong>Deleting ${roll} (${name}) un-seats them from their active examinations and removes their record.</strong>`;
  document.getElementById('deleteStudentModal').style.display = 'flex';
}

function closeDeleteStudentModal() {
  document.getElementById('deleteStudentModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
  // Setup Drag and Drop
  const dropZone = document.getElementById('csvDropZone');
  const fileInput = document.getElementById('csvFile');
  if (dropZone && fileInput) {
    ['dragenter', 'dragover'].forEach((eventName) => {
      dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
      }, false);
    });

    ['dragleave', 'drop'].forEach((eventName) => {
      dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
      }, false);
    });

    dropZone.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      const files = dt.files;
      if (files.length > 0) {
        fileInput.files = files;
        handleCsvFileSelected(fileInput);
      }
    });
  }

  // Roll Number Format Validation (Item 1)
  const rollInput = document.getElementById('stu_roll');
  const rollError = document.getElementById('rollFormatError');
  const addStudentForm = document.getElementById('addStudentForm');

  function validateRollNo() {
    if (!rollInput) return true;
    const val = (rollInput.value || '').trim();
    if (!val) {
      if (rollError) rollError.classList.add('d-none');
      rollInput.classList.remove('is-invalid');
      return false;
    }
    // University roll numbers are typically alphanumeric strings 4-24 chars
    const isValid = /^[A-Za-z0-9]{4,24}$/.test(val);
    if (!isValid) {
      if (rollError) rollError.classList.remove('d-none');
      rollInput.classList.add('is-invalid');
      return false;
    } else {
      if (rollError) rollError.classList.add('d-none');
      rollInput.classList.remove('is-invalid');
      return true;
    }
  }

  if (rollInput) {
    rollInput.addEventListener('blur', validateRollNo);
    rollInput.addEventListener('input', () => {
      if (rollInput.classList.contains('is-invalid')) {
        validateRollNo();
      }
    });
  }

  if (addStudentForm) {
    addStudentForm.addEventListener('submit', (e) => {
      const isValid = validateRollNo();
      if (!isValid) {
        e.preventDefault();
        rollInput.focus();
      }
    });
  }
});

function loadRecentStudent(card) {
  try {
    const raw = card.getAttribute('data-student');
    if (!raw) return;
    const data = JSON.parse(raw);
    
    const roll = document.getElementById('stu_roll');
    const name = document.getElementById('stu_name');
    const branch = document.getElementById('stu_branch');
    const sem = document.getElementById('stu_sem');
    const dob = document.getElementById('stu_dob');
    const code = document.getElementById('stu_code');
    const exam = document.getElementById('stu_exam');
    const btnText = document.getElementById('saveBtnText');

    if (roll) roll.value = data.roll_no || '';
    if (name) name.value = data.name || '';
    if (branch) branch.value = data.branch || '';
    if (sem) sem.value = data.semester || '5';
    if (dob) dob.value = data.dob || '2005-01-01';
    if (code) code.value = data.exam_code || '';
    if (exam && data.exam_id) exam.value = data.exam_id;
    
    if (btnText) {
      btnText.textContent = `Update Student (${data.roll_no})`;
    }

    // Clear any previous error
    const rollError = document.getElementById('rollFormatError');
    if (rollError) rollError.classList.add('d-none');
    if (roll) {
      roll.classList.remove('is-invalid');
      roll.focus();
    }

    if (window.showToast) {
      window.showToast(`Loaded ${data.roll_no} (${data.name}) into form for editing`, 'info');
    }
  } catch (err) {
    console.error('Failed to load recent student:', err);
  }
}

function handleCsvFileSelected(input) {
  const nameBox = document.getElementById('csvSelectedFileName');
  if (input.files && input.files[0]) {
    nameBox.textContent = `Selected: ${input.files[0].name} (${(input.files[0].size / 1024).toFixed(1)} KB)`;
    nameBox.classList.remove('d-none');
  } else {
    nameBox.classList.add('d-none');
  }
}

function downloadSampleCsv(e) {
  if (e) e.preventDefault();
  const csvContent = "data:text/csv;charset=utf-8," +
    "roll_no,name,branch,semester,exam_id,exam_code,dob\n" +
    "2116241801001,Vishal Rajan,AI&DS,5,1,AD23532,2005-01-01\n" +
    "2116251001001,Ishwarya Pillai,IT,3,1,IT23331,2005-01-01\n" +
    "2116250701001,Sai Sharma,CSE,3,1,CS23334,2005-01-01\n" +
    "2116241501001,Gayathri Anand,AI&ML,5,1,AD23632,2005-01-01\n" +
    "2116251401001,Bala Balakrishnan,CSBS,3,1,MC23313,2005-01-01\n";
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "students_roster_sample.csv");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

async function uploadCsv() {
  const fileInput = document.getElementById('csvFile');
  const btn = document.getElementById('uploadCsvBtn');
  const out = document.getElementById('csvResultBox');
  const f = fileInput.files[0];

  if (!f) {
    out.className = 'mt-3 alert alert-danger p-2 small';
    out.textContent = 'Please choose or drag a CSV file first.';
    out.classList.remove('d-none');
    return;
  }

  const fd = new FormData();
  fd.append('file', f);

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Uploading & Processing…';
  out.className = 'mt-3 alert alert-info p-2 small';
  out.textContent = 'Processing student roster records…';
  out.classList.remove('d-none');

  try {
    const res = await fetch('../api/students_upload.php', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': '<?= csrf_token() ?>' },
      body: fd
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Upload failed');

    const processed = json.data.processed;
    const skipped = json.data.skipped;

    // Zero-green compliant alert
    out.className = 'mt-3 alert alert-primary p-2.5 rounded-3 border-primary-subtle';
    out.innerHTML = `
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill text-primary fs-5"></i>
        <div>
          <strong>${processed.toLocaleString()} added</strong> &middot; ${skipped.toLocaleString()} duplicates/invalid skipped
        </div>
      </div>
    `;

    if (window.showToast) {
      window.showToast(`${processed.toLocaleString()} students added successfully (${skipped} skipped)`, 'success');
    }

    setTimeout(() => location.reload(), 1200);
  } catch (e) {
    btn.disabled = false;
    btn.innerHTML = 'Upload & Process CSV';
    out.className = 'mt-3 alert alert-danger p-2 small';
    out.textContent = e.message;
  }
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>