<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

$pdo = db();

// Add / delete / seed batch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([(int)$_POST['id']]);
        header('Location: students.php?toast=' . urlencode('Student record deleted successfully'));
        exit;
    } elseif ($action === 'seed_cohorts') {
        // Quick bulk generation of multi-year / multi-code students for testing 1000 rooms
        $examId = (int)($_POST['seed_exam_id'] ?: 1);
        $count = max(10, min(30000, (int)($_POST['seed_count'] ?: 1000)));

        $cohortDefs = [
            ['code' => 'CS3301', 'branch' => 'CSE',   'sem' => 3, 'yr' => 2, 'prefix' => '23CS'],
            ['code' => 'EC3301', 'branch' => 'ECE',   'sem' => 3, 'yr' => 2, 'prefix' => '23EC'],
            ['code' => 'ME3301', 'branch' => 'MECH',  'sem' => 3, 'yr' => 2, 'prefix' => '23ME'],
            ['code' => 'CS3501', 'branch' => 'CSE',   'sem' => 5, 'yr' => 3, 'prefix' => '22CS'],
            ['code' => 'IT3501', 'branch' => 'IT',    'sem' => 5, 'yr' => 3, 'prefix' => '22IT'],
            ['code' => 'AD3501', 'branch' => 'AIDS',  'sem' => 5, 'yr' => 3, 'prefix' => '22AD'],
            ['code' => 'EC3701', 'branch' => 'ECE',   'sem' => 7, 'yr' => 4, 'prefix' => '21EC'],
            ['code' => 'IT3701', 'branch' => 'IT',    'sem' => 7, 'yr' => 4, 'prefix' => '21IT'],
        ];

        $names = ['Aarav', 'Diya', 'Rohan', 'Ananya', 'Vivaan', 'Ishita', 'Kabir', 'Meera', 'Arjun', 'Priya', 'Karan', 'Nisha', 'Vikram', 'Tanya', 'Siddharth', 'Trisha', 'Bhavya', 'Kunal', 'Abhishek', 'Esha'];

        $pdo->beginTransaction();
        try {
            $chunkSize = 500;
            for ($c = 0; $c < $count; $c += $chunkSize) {
                $rows = [];
                $params = [];
                $limit = min($count, $c + $chunkSize);
                for ($i = $c + 1; $i <= $limit; $i++) {
                    $cDef = $cohortDefs[$i % count($cohortDefs)];
                    $roll = $cDef['prefix'] . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
                    $name = $names[$i % count($names)] . ' ' . chr(65 + ($i % 26));
                    $rows[] = "(?, ?, ?, ?, ?, ?, ?, ?)";
                    $params[] = $roll;
                    $params[] = $name;
                    $params[] = $cDef['branch'];
                    $params[] = $cDef['branch'];
                    $params[] = $cDef['sem'];
                    $params[] = $cDef['yr'];
                    $params[] = $cDef['code'];
                    $params[] = $examId;
                }
                $sql = "INSERT INTO students (roll_no, name, branch, dept, semester, year, exam_code, exam_id) VALUES " . implode(", ", $rows) .
                       " ON CONFLICT(roll_no) DO UPDATE SET exam_code = excluded.exam_code, exam_id = excluded.exam_id";
                $pdo->prepare($sql)->execute($params);
            }
            $pdo->commit();
            header('Location: students.php?toast=' . urlencode('Successfully seeded ' . number_format($count) . ' test cohort students'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    } else {
        $branch = strtoupper(trim($_POST['branch']));
        $sem = (int)$_POST['semester'];
        $yr = (int)ceil($sem / 2);
        $examCode = trim($_POST['exam_code'] ?? '');
        if ($examCode === '') {
            $examCode = $branch . '-S' . $sem;
        }

        $pdo->prepare("INSERT INTO students (roll_no, name, branch, dept, semester, year, exam_code, exam_id) VALUES (?,?,?,?,?,?,?,?)
                       ON CONFLICT(roll_no) DO UPDATE SET
                           name = excluded.name,
                           branch = excluded.branch,
                           dept = excluded.dept,
                           semester = excluded.semester,
                           year = excluded.year,
                           exam_code = excluded.exam_code,
                           exam_id = excluded.exam_id")
            ->execute([
                trim($_POST['roll_no']),
                trim($_POST['name']),
                $branch,
                $branch,
                $sem,
                $yr,
                $examCode,
                (int)$_POST['exam_id'] ?: null
            ]);
        header('Location: students.php?toast=' . urlencode('Student record saved successfully'));
        exit;
    }
}

$pageTitle = 'Students';
require __DIR__ . '/_header.php';

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

$stmt = $pdo->prepare("$sql ORDER BY s.semester, s.branch, s.roll_no LIMIT 250");
$stmt->execute($params);
$students = $stmt->fetchAll();

$branches = $pdo->query("SELECT DISTINCT branch FROM students ORDER BY branch")->fetchAll(PDO::FETCH_COLUMN);
$semesters = $pdo->query("SELECT DISTINCT semester FROM students ORDER BY semester")->fetchAll(PDO::FETCH_COLUMN);
$examCodes = $pdo->query("SELECT DISTINCT exam_code FROM students WHERE exam_code IS NOT NULL AND exam_code != '' ORDER BY exam_code")->fetchAll(PDO::FETCH_COLUMN);
$exams = $pdo->query("SELECT id, exam_name FROM exams ORDER BY exam_date DESC")->fetchAll();
$colors = ['CSE'=>'#6366f1','ECE'=>'#10b981','MECH'=>'#f59e0b','CIVIL'=>'#ef4444','EE'=>'#06b6d4','IT'=>'#ec4899','AIDS'=>'#8b5cf6'];
?>

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
  <div>
    <span class="badge bg-primary-subtle text-primary border py-2 px-3 fs-6 rounded-pill d-inline-flex align-items-center gap-1.5">
      <?= svg_icon('users', '', 16) ?>
      <strong><?= number_format($totalStudents) ?></strong> Registered Students
    </span>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <!-- Add Student Card -->
    <div class="table-card mb-4">
      <div class="d-flex align-items-center gap-2 mb-3">
        <div class="text-primary"><?= svg_icon('plus', '', 20) ?></div>
        <h6 class="fw-bold mb-0">Add Student</h6>
      </div>
      <form method="post">
        <div class="mb-2">
          <label class="form-label small fw-semibold">Roll Number</label>
          <input name="roll_no" class="form-control" placeholder="e.g. 23CS101" required>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Full Name</label>
          <input name="name" class="form-control" placeholder="Student Full Name" required>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small fw-semibold">Branch</label>
            <input name="branch" class="form-control" placeholder="e.g. CSE" required>
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Semester</label>
            <input name="semester" type="number" min="1" max="8" class="form-control" placeholder="1-8" required>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Exam Paper Code</label>
          <input name="exam_code" class="form-control" placeholder="e.g. CS3301 (or auto-assigned)">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Assigned Exam (Optional)</label>
          <select name="exam_id" class="form-select">
            <option value="">— Assign Exam —</option>
            <?php foreach ($exams as $e): ?>
              <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['exam_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-grad w-100">Save Student</button>
      </form>
    </div>

    <!-- Quick Cohort Generator for 1000 Rooms Demo -->
    <div class="table-card mb-4">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <div class="d-flex align-items-center gap-2">
          <div class="text-primary"><?= svg_icon('magic', '', 18) ?></div>
          <h6 class="fw-bold mb-0">1,000 Halls Demo Seeder</h6>
        </div>
        <span class="badge bg-success-subtle text-success small">Quick Test</span>
      </div>
      <p class="text-muted small">Populate hundreds/thousands of students across multi-year cohorts &amp; distinct exam codes.</p>
      <form method="post">
        <input type="hidden" name="action" value="seed_cohorts">
        <div class="mb-2">
          <label class="form-label small text-muted mb-1">Target Exam Session</label>
          <select name="seed_exam_id" class="form-select form-select-sm">
            <?php foreach ($exams as $e): ?>
              <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['exam_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small text-muted mb-1">Number of Students</label>
          <select name="seed_count" class="form-select form-select-sm">
            <option value="1000">1,000 Students (~33 Halls)</option>
            <option value="3000">3,000 Students (~100 Halls)</option>
            <option value="10000">10,000 Students (~333 Halls)</option>
            <option value="30000">30,000 Students (1,000 Halls Full Capacity)</option>
          </select>
        </div>
        <button class="btn btn-outline-primary btn-sm w-100 rounded-pill">
          <?= svg_icon('magic', 'me-1', 14) ?>Generate Demo Cohort Students
        </button>
      </form>
    </div>

    <!-- CSV Bulk Upload -->
    <div class="table-card">
      <div class="d-flex align-items-center gap-2 mb-2">
        <div class="text-primary"><?= svg_icon('download', '', 18) ?></div>
        <h6 class="fw-bold mb-0">Bulk Upload (CSV)</h6>
      </div>
      <p class="text-muted small">Format: <code>roll_no,name,branch,semester,exam_id,exam_code</code></p>
      <input type="file" id="csvFile" accept=".csv" class="form-control mb-2">
      <button class="btn btn-grad w-100" onclick="uploadCsv()">Upload CSV</button>
      <div id="csvResult" class="small mt-2"></div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="table-card">
      <form class="row g-2 mb-3" method="get">
        <div class="col-md-4">
          <div class="position-relative">
            <input name="q" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm" placeholder="Search roll no or name…">
          </div>
        </div>
        <div class="col-md-3">
          <select name="exam_code" class="form-select form-select-sm">
            <option value="">All Exam Codes</option>
            <?php foreach ($examCodes as $c): ?>
              <option <?= $c===$code?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="branch" class="form-select form-select-sm">
            <option value="">All Departments</option>
            <?php foreach ($branches as $b): ?>
              <option <?= $b===$branch?'selected':'' ?>><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-grad btn-sm w-100">Filter</button>
        </div>
      </form>

      <div class="table-responsive" style="max-height:65vh;overflow:auto">
        <table class="table align-middle mb-0">
          <thead style="position:sticky;top:0;z-index:2;background:#f8fafc;">
            <tr>
              <th>Roll No</th>
              <th>Name</th>
              <th>Exam Code</th>
              <th>Dept</th>
              <th>Sem / Year</th>
              <th>Exam Session</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($students as $s): ?>
            <tr>
              <td class="fw-semibold text-main"><?= htmlspecialchars($s['roll_no']) ?></td>
              <td><?= htmlspecialchars($s['name']) ?></td>
              <td>
                <span class="badge bg-primary-subtle text-primary border fw-bold px-2 py-1" style="border-radius:6px;">
                  <?= htmlspecialchars($s['exam_code'] ?: ($s['branch'].'-S'.$s['semester'])) ?>
                </span>
              </td>
              <td>
                <span class="badge-branch" style="background:<?= $colors[$s['branch']] ?? '#64748b' ?>">
                  <?= $s['branch'] ?>
                </span>
              </td>
              <td>Sem <?= (int)$s['semester'] ?> <span class="text-muted small">(Yr <?= ceil($s['semester'] / 2) ?>)</span></td>
              <td class="small text-muted"><?= htmlspecialchars($s['exam_name'] ?? '— Unassigned —') ?></td>
              <td class="text-end">
                <button type="button" class="btn-action btn-action-delete"
                        title="Delete Student"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteStudentModal"
                        data-id="<?= $s['id'] ?>"
                        data-name="<?= htmlspecialchars($s['roll_no'] . ' - ' . $s['name'], ENT_QUOTES) ?>">
                  <?= svg_icon('trash', '', 15) ?>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$students): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-5">
                <div class="empty-state-icon mx-auto"><?= svg_icon('users', '', 26) ?></div>
                <div class="fw-bold mt-2">No students found</div>
                <div class="small text-muted">Try adjusting your search criteria or add new students.</div>
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="text-muted small mt-3">
        Showing <?= number_format(count($students)) ?> of <?= number_format($totalStudents) ?> student(s)
      </div>
    </div>
  </div>
</div>

<!-- Modal: Delete Confirmation Popup -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-labelledby="deleteStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteStudentModalLabel">
          <?= svg_icon('alert-triangle', '', 20) ?>
          Delete Student
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="students.php">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteStudentId" value="">
        <div class="modal-body py-3">
          <p class="text-body mb-2">Are you sure you want to delete <strong id="deleteStudentName">this student</strong>?</p>
          <div class="p-3 bg-danger-subtle rounded-3 text-danger small">
            <?= svg_icon('alert-triangle', 'me-1', 15) ?>
            This action will delete the student and their associated seating records.
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
  const deleteModal = document.getElementById('deleteStudentModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      document.getElementById('deleteStudentId').value = btn.getAttribute('data-id');
      document.getElementById('deleteStudentName').textContent = `"${btn.getAttribute('data-name')}"`;
    });
  }
});

async function uploadCsv() {
  const f = document.getElementById('csvFile').files[0];
  const out = document.getElementById('csvResult');
  if (!f) { out.innerHTML = '<span class="text-danger">Choose a CSV file first.</span>'; return; }
  const fd = new FormData(); fd.append('file', f);
  out.innerHTML = '<span class="text-muted">Uploading…</span>';
  try {
    const res = await fetch('../api/students_upload.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error);
    out.innerHTML = `<span class="text-success">Processed ${json.data.processed}, skipped ${json.data.skipped}.</span>`;
    setTimeout(() => location.reload(), 900);
  } catch (e) { out.innerHTML = `<span class="text-danger">${e.message}</span>`; }
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>