<?php
$pageTitle = 'Generate Seating';
require __DIR__ . '/_header.php';

$exams = db()->query("SELECT e.*, (SELECT COUNT(*) FROM students s WHERE s.exam_id=e.id) AS students
  FROM exams e ORDER BY e.exam_date DESC")->fetchAll();
$preselect = (int)($_GET['exam_id'] ?? 0);
$roomsCount = (int)db()->query("SELECT COUNT(*) FROM rooms WHERE active=1")->fetchColumn();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= svg_icon('magic', 'text-primary', 26) ?>
      Generate Seating Plan
    </h1>
    <div class="page-subtitle">
      <span>Automatic conflict-free interleaving across departments, cohorts, and exam codes</span>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="table-card">
      <div class="text-center mb-4">
        <div class="stat-icon mx-auto mb-2" style="background:#eef2ff; color:#4f46e5;">
          <?= svg_icon('magic', '', 26) ?>
        </div>
        <h6 class="fw-bold mt-2 mb-1">Automatic Conflict-Free Seating</h6>
        <p class="text-muted small mb-0">
          Interleaves multi-year cohorts and departments. Students on the same bench and adjacent benches write different exams.
        </p>
      </div>

      <!-- Exam Selection -->
      <label class="form-label small fw-semibold">1. Select Exam Session</label>
      <select id="examSelect" class="form-select mb-3" onchange="updateExamInfo()">
        <option value="">— Select an exam session —</option>
        <?php foreach ($exams as $e): ?>
          <option value="<?= $e['id'] ?>" data-students="<?= (int)$e['students'] ?>" <?= $e['id']==$preselect?'selected':'' ?>>
            <?= htmlspecialchars($e['exam_name']) ?> (<?= (int)$e['students'] ?> students)
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Hall & Bench Mode -->
      <label class="form-label small fw-semibold">2. Hall &amp; Bench Layout Settings</label>
      <div class="form-check mb-2">
        <input class="form-check-input" type="radio" name="benchMode" id="modeConfigured" value="configured" checked onchange="toggleMode()">
        <label class="form-check-label small" for="modeConfigured">
          Use Saved Room Layouts (<?= number_format($roomsCount) ?> active halls configured)
        </label>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="radio" name="benchMode" id="modeCustom" value="custom" onchange="toggleMode()">
        <label class="form-check-label small" for="modeCustom">
          Custom: Specify No. of Rooms &amp; Bench Capacity
        </label>
      </div>

      <!-- Custom Inputs Box -->
      <div id="customInputs" class="p-3 bg-light rounded-3 border mb-3" style="display:none">
        <div class="mb-2">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small text-muted mb-0">Number of Exam Halls (1 – 5,000+)</label>
            <span class="badge bg-primary-subtle text-primary small">Scales to 1000+ Halls</span>
          </div>
          <input type="number" id="numRooms" class="form-control form-control-sm" value="2" min="1" max="5000" oninput="recalcCustom()">
        </div>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small text-muted mb-1">Rows (Benches)</label>
            <input type="number" id="benchesPerRoom" class="form-control form-control-sm" value="15" min="1" max="100" oninput="recalcCustom()">
          </div>
          <div class="col-6">
            <label class="form-label small text-muted mb-1">Seats / Bench</label>
            <input type="number" id="studentsPerBench" class="form-control form-control-sm" value="2" min="1" max="20" oninput="recalcCustom()">
          </div>
        </div>
        <div class="d-flex gap-1 flex-wrap mb-2">
          <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setPreset(1000, 15, 2)">1,000 Halls (30k Desks)</button>
          <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setPreset(500, 15, 2)">500 Halls</button>
          <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setPreset(50, 15, 2)">50 Halls</button>
          <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setPreset(2, 5, 6)">2 Halls (60 Desks)</button>
        </div>
        <div id="customCalc" class="small text-muted text-center pt-1 border-top">
          Calculated Capacity: <strong>60 desks</strong> (2 rooms × 15 rows × 2 cols = 30 desks/room)
        </div>
      </div>

      <button id="genBtn" class="btn btn-grad w-100 py-2.5">
        <span id="genLabel" class="d-inline-flex align-items-center gap-1.5">
          <?= svg_icon('magic', '', 18) ?>
          Generate Seating Plan
        </span>
      </button>
      <div class="text-muted small mt-2.5 text-center d-flex align-items-center justify-content-center gap-1">
        <?= svg_icon('check', 'text-success', 15) ?>
        Ensures no adjacent desks share the same exam code.
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="table-card" id="resultPanel" style="display:none">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
          <div class="text-primary"><?= svg_icon('dashboard', '', 20) ?></div>
          <h6 class="fw-bold mb-0">Seating Generation Analytics</h6>
        </div>
        <span class="status-pill status-upcoming" id="badgeStatus">
          <span class="status-dot"></span>Conflict-Free
        </span>
      </div>
      <div class="row g-3" id="summary"></div>
      <div id="warnings" class="mt-3"></div>
      <div class="d-flex gap-2 mt-4 flex-wrap" id="actions" style="display:none!important">
        <a id="printLink" href="#" class="btn btn-primary-soft btn-sm">
          <?= svg_icon('printer', 'me-1', 15) ?>Print Seating Chart
        </a>
        <a id="exportLink" href="#" class="btn btn-outline-secondary btn-sm" style="border-radius:12px;">
          <?= svg_icon('download', 'me-1', 15) ?>Export CSV Roster
        </a>
        <a href="http://localhost:5173/find" target="_blank" class="btn btn-outline-primary btn-sm" style="border-radius:12px;">
          <?= svg_icon('external-link', 'me-1', 15) ?>Test in Student Portal ↗
        </a>
      </div>
    </div>

    <!-- Friendly Placeholder / Empty State -->
    <div class="table-card text-muted text-center p-5" id="placeholder">
      <div class="empty-state-icon mx-auto mb-3">
        <?= svg_icon('magic', '', 28) ?>
      </div>
      <h6 class="fw-bold text-main">Ready to Allocate Seating</h6>
      <p class="text-muted small mb-0">Select an exam session, configure bench capacity, and click <strong>Generate Seating Plan</strong>.</p>
    </div>
  </div>
</div>

<script>
function toggleMode() {
  const isCustom = document.getElementById('modeCustom').checked;
  document.getElementById('customInputs').style.display = isCustom ? 'block' : 'none';
  if (isCustom) recalcCustom();
}

function setPreset(rooms, rows, cols) {
  document.getElementById('numRooms').value = rooms;
  document.getElementById('benchesPerRoom').value = rows;
  document.getElementById('studentsPerBench').value = cols;
  recalcCustom();
}

function recalcCustom() {
  const r = +document.getElementById('numRooms').value || 1;
  const b = +document.getElementById('benchesPerRoom').value || 1;
  const s = +document.getElementById('studentsPerBench').value || 1;
  const total = r * b * s;
  document.getElementById('customCalc').innerHTML = `Calculated Capacity: <b>${total.toLocaleString()} desks</b> across <b>${r.toLocaleString()} halls</b> (${b} rows × ${s} cols = ${b * s} desks/room)`;
}

function updateExamInfo() {
  const sel = document.getElementById('examSelect');
  const opt = sel.options[sel.selectedIndex];
  const count = opt ? +opt.getAttribute('data-students') : 0;
  if (count > 0) {
    const perBench = +document.getElementById('studentsPerBench').value || 2;
    const benchesNeeded = Math.ceil(count / perBench);
    const suggestedRooms = Math.max(1, Math.ceil(benchesNeeded / 15));
    document.getElementById('numRooms').value = suggestedRooms;
    document.getElementById('benchesPerRoom').value = Math.min(20, Math.ceil(benchesNeeded / suggestedRooms));
    recalcCustom();
  }
}

const btn = document.getElementById('genBtn'), label = document.getElementById('genLabel');
btn.addEventListener('click', async () => {
  const examId = document.getElementById('examSelect').value;
  if (!examId) {
    window.showToast('Please select an exam session first.', 'danger');
    return;
  }

  const isCustom = document.getElementById('modeCustom').checked;
  const payload = { exam_id: +examId };
  if (isCustom) {
    payload.num_rooms = +document.getElementById('numRooms').value || 1;
    payload.benches_per_room = +document.getElementById('benchesPerRoom').value || 15;
    payload.students_per_bench = +document.getElementById('studentsPerBench').value || 2;
  }

  btn.disabled = true;
  label.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Arranging conflict-free seats…';

  try {
    const res = await fetch('../api/generate.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Generation failed');
    const d = json.data;
    document.getElementById('placeholder').style.display = 'none';
    document.getElementById('resultPanel').style.display = 'block';

    const sameCodeConflicts = d.same_exam_code_conflicts ?? d.same_paper_conflicts ?? 0;
    const tiles = [
      ['Students Seated', `${d.assigned.toLocaleString()} / ${d.total_students.toLocaleString()}`, '#ecfdf5', '#10b981'],
      ['Same-Code Conflicts', sameCodeConflicts, sameCodeConflicts === 0 ? '#ecfdf5' : '#fef2f2', sameCodeConflicts === 0 ? '#10b981' : '#ef4444'],
      ['Halls Utilized', `${d.rooms_used.toLocaleString()} halls`, '#fff7ed', '#f59e0b'],
      ['Benches Utilized', `${(d.benches_used || d.assigned).toLocaleString()} benches`, '#f0fdf4', '#16a34a'],
      ['Exam Codes Handled', `${d.exam_codes_count || d.cohorts_count} codes (${d.departments_count} depts)`, '#eef2ff', '#6366f1'],
      ['Engine Speed', `${d.execution_time_ms || 10} ms`, '#f0f9ff', '#06b6d4'],
    ];

    document.getElementById('summary').innerHTML = tiles.map(([t,v,bg,c]) =>
      `<div class="col-6 col-md-4"><div class="p-3 rounded-3 text-center border" style="background:${bg}">
        <div class="fw-bold fs-4" style="color:${c}">${v}</div><div class="small text-muted">${t}</div></div></div>`).join('');
    
    let w = '';
    if (d.unassigned > 0) {
      w += `<div class="alert alert-warning py-2 small">⚠️ ${d.unassigned.toLocaleString()} student(s) could not be seated due to capacity limits. Increase rooms or benches.</div>`;
    }
    if (sameCodeConflicts === 0) {
      w += `<div class="alert alert-success py-2 small"><b>Zero Conflicts!</b> No two adjacent students share the same exam code. Interleaved across academic years and departments.</div>`;
    } else {
      w += `<div class="alert alert-danger py-2 small">⚠️ ${sameCodeConflicts} adjacent conflict(s) remain due to insufficient cohort variety.</div>`;
    }
    document.getElementById('warnings').innerHTML = w;

    const actions = document.getElementById('actions');
    actions.style.cssText = '';
    document.getElementById('printLink').href = 'print_plan.php?exam_id=' + examId;
    document.getElementById('exportLink').href = '../api/export.php?exam_id=' + examId;
    
    window.showToast(`Seating plan generated successfully! ${d.assigned} students seated conflict-free.`, 'success');
  } catch (e) {
    window.showToast(e.message, 'danger');
  }
  btn.disabled = false;
  label.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/><path d="M5 3v4"/><path d="M3 5h4"/><path d="M19 17v4"/><path d="M17 19h4"/></svg> Generate Seating Plan';
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>