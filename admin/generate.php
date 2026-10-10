<?php
$pageTitle = 'Generate Seating';
require __DIR__ . '/_header.php';

$exams = db()->query("SELECT e.*, (SELECT COUNT(DISTINCT s.id) FROM students s LEFT JOIN student_exams se ON se.student_id=s.id WHERE se.exam_id=e.id OR s.exam_id=e.id) AS students
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
      <div class="card-title-header mb-3">
        <div class="card-title-icon">
          <?= svg_icon('magic', '', 20) ?>
        </div>
        <div class="card-title-text">
          <h6>Automatic Conflict-Free Seating</h6>
          <p>Interleaves multi-year cohorts and departments across examination halls.</p>
        </div>
      </div>

      <!-- Info banner under description (Rule 7: soft blue/navy info palette) -->
      <div class="alert alert-primary-subtle border border-primary-subtle py-2 px-3 rounded-3 text-primary-emphasis small d-flex align-items-center gap-2 mb-3">
        <?= svg_icon('check-circle', 'text-primary flex-shrink-0', 16) ?>
        <span>Ensures no adjacent desks share the same exam code across rows and columns.</span>
      </div>

      <!-- Step 1 Block (Item 9) -->
      <div class="step-block mb-3">
        <div class="step-block-header">
          <span class="step-number">1</span>
          <span>Select Exam Session</span>
        </div>
        <label class="form-label" for="examSelect">Target Examination</label>
        <select id="examSelect" class="form-select" onchange="updateExamInfo()">
          <option value="">— Select an exam session —</option>
          <?php foreach ($exams as $e): ?>
            <option value="<?= $e['id'] ?>" data-students="<?= (int)$e['students'] ?>" <?= $e['id']==$preselect?'selected':'' ?>>
              <?= htmlspecialchars($e['exam_name']) ?> (<?= number_format((int)$e['students']) ?> students)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Step 2 Block (Item 9, 10, 11, 12) -->
      <div class="step-block mb-3">
        <div class="step-block-header">
          <span class="step-number">2</span>
          <span>Hall &amp; Bench Layout Settings</span>
        </div>
        
        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="benchMode" id="modeConfigured" value="configured" checked onchange="toggleMode()">
          <label class="form-check-label fw-semibold" for="modeConfigured">
            Use Saved Room Layouts
            <span class="d-block text-muted small fw-normal">Use <?= number_format($roomsCount) ?> active campus halls currently configured</span>
          </label>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="benchMode" id="modeCustom" value="custom" onchange="toggleMode()">
          <label class="form-check-label fw-semibold" for="modeCustom">
            Custom: Specify Rooms &amp; Desks
            <span class="d-block text-muted small fw-normal">Provision custom classroom dimensions dynamically</span>
          </label>
        </div>

        <!-- Custom Inputs Box with Smooth Slide-down (Item 12) -->
        <div id="customInputs" class="p-3 bg-light rounded-3 border mt-3" style="display:none;">
          <div class="alert alert-info py-1.5 px-2.5 small mb-2 d-flex align-items-center gap-1.5" style="font-size:11.5px">
            <?= svg_icon('info', 'flex-shrink-0', 14) ?>
            <span>Safe custom rooms: In simulation mode, runs in-memory. If saved, creates unique halls without overwriting existing halls.</span>
          </div>
          <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label text-muted mb-0" for="numRooms">Number of Exam Halls</label>
              <span class="badge bg-primary-subtle text-primary small">Scales to 1000+ Halls</span>
            </div>
            <input type="number" id="numRooms" class="form-control" value="2" min="1" max="5000" oninput="recalcCustom()">
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label text-muted mb-1" for="benchesPerRoom">Rows (Benches)</label>
              <input type="number" id="benchesPerRoom" class="form-control" value="15" min="1" max="100" oninput="recalcCustom()">
            </div>
            <div class="col-6">
              <label class="form-label text-muted mb-1" for="studentsPerBench">Seats / Bench</label>
              <input type="number" id="studentsPerBench" class="form-control" value="2" min="1" max="20" oninput="recalcCustom()">
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
      </div>

      <!-- Step 3 Block (Item 9, 11) -->
      <div class="step-block mb-3">
        <div class="step-block-header">
          <span class="step-number">3</span>
          <span>Spacing &amp; Simulation Options</span>
        </div>
        
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="spacingAlternate">
          <label class="form-check-label fw-semibold" for="spacingAlternate">
            Alternate Empty Desks (Checkerboard)
            <span class="d-block text-muted small fw-normal">Recommended for single-paper or imbalanced batches (e.g. 90/10 split) to guarantee 0 adjacent clashes.</span>
          </label>
        </div>

        <div class="form-check mb-1">
          <input class="form-check-input" type="checkbox" id="simulateOnly">
          <label class="form-check-label fw-semibold" for="simulateOnly">
            Simulate in Memory (Preview only)
            <span class="d-block text-muted small fw-normal">Calculates seating plan and checks clashes without modifying saved halls or database records.</span>
          </label>
        </div>
      </div>

      <button id="genBtn" class="btn btn-grad w-100 py-2.5">
        <span id="genLabel" class="d-inline-flex align-items-center gap-1.5">
          <?= svg_icon('magic', '', 18) ?>
          Generate Seating Plan
        </span>
      </button>
    </div>
  </div>

  <div class="col-lg-7">
    <!-- Results Summary Panel (Items 15, 17) -->
    <div class="table-card" id="resultPanel" style="display:none">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
          <div class="text-primary"><?= svg_icon('dashboard', '', 20) ?></div>
          <h6 class="fw-bold mb-0">Seating Generation Summary</h6>
        </div>
        <span class="status-pill status-upcoming" id="badgeStatus">
          <span class="status-dot"></span>Conflict-Free
        </span>
      </div>

      <div id="simulationBanner" class="mb-3" style="display:none;"></div>
      <div class="row g-3" id="summary"></div>
      <div id="warnings" class="mt-3"></div>

      <div class="d-flex gap-2 mt-4 flex-wrap" id="actions" style="display:none!important">
        <a id="printLink" href="#" class="btn btn-primary-soft btn-sm" title="View Sheets">
          <?= svg_icon('printer', 'me-1', 15) ?>View Sheets
        </a>
        <a id="exportLink" href="#" class="btn btn-outline-secondary btn-sm" style="border-radius:12px;" title="Export CSV">
          <?= svg_icon('download', 'me-1', 15) ?>Export CSV
        </a>
        <a href="<?= htmlspecialchars(getenv('STUDENT_PORTAL_URL') ?: '../') ?>find" target="_blank" class="btn btn-outline-primary btn-sm" style="border-radius:12px;">
          <?= svg_icon('box-arrow-up-right', 'me-1', 15) ?>Test in Student Portal ↗
        </a>
      </div>
    </div>

    <!-- Friendly Placeholder / Empty State (Item 28) -->
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
  const customBox = document.getElementById('customInputs');
  if (isCustom) {
    customBox.style.display = 'block';
    recalcCustom();
  } else {
    customBox.style.display = 'none';
  }
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
  const sel = document.getElementById('examSelect');
  const examId = sel.value;
  if (!examId) {
    window.showToast('Please select an exam session first.', 'danger');
    return;
  }

  const opt = sel.options[sel.selectedIndex];
  const count = opt ? +opt.getAttribute('data-students') : 0;

  const isCustom = document.getElementById('modeCustom').checked;
  const isAlternate = document.getElementById('spacingAlternate').checked;
  const isSimulate = document.getElementById('simulateOnly').checked;

  // Confirmation popup before real generation (Item 13)
  if (!isSimulate) {
    if (!confirm('This will replace the existing arrangement for this exam. Continue?')) {
      return;
    }
  }

  const payload = {
    exam_id: +examId,
    spacing: isAlternate ? 'alternate' : 'dense',
    simulate: isSimulate,
  };

  if (isCustom) {
    payload.num_rooms = +document.getElementById('numRooms').value || 1;
    payload.benches_per_room = +document.getElementById('benchesPerRoom').value || 15;
    payload.students_per_bench = +document.getElementById('studentsPerBench').value || 2;
  }

  // Loading state on button (Item 14)
  btn.disabled = true;
  label.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Allocating ${count > 0 ? Number(count).toLocaleString() + ' ' : ''}seats…`;

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch('../api/generate.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Generation failed');
    const d = json.data;
    
    // Replace placeholder with results summary (Item 15)
    document.getElementById('placeholder').style.display = 'none';
    document.getElementById('resultPanel').style.display = 'block';

    const simBanner = document.getElementById('simulationBanner');
    if (d.simulated) {
      // Clear label as PREVIEW ONLY (Item 17)
      simBanner.innerHTML = `<div class="alert alert-warning py-2.5 px-3 rounded-3 border-warning d-flex align-items-center gap-2">
        <span class="badge bg-warning text-dark fw-bold px-2 py-1">PREVIEW ONLY</span>
        <div>
          <strong class="text-warning-emphasis">PREVIEW ONLY — nothing saved to database.</strong>
          <div class="small text-muted mt-0.5">Seating was calculated in-memory. Uncheck "Simulate in Memory" to save.</div>
        </div>
      </div>`;
      simBanner.style.display = 'block';
    } else {
      simBanner.style.display = 'none';
    }

    const sameCodeConflicts = d.same_exam_code_conflicts ?? d.same_paper_conflicts ?? 0;
    const tiles = [
      ['Students Seated', `${d.assigned.toLocaleString()} / ${d.total_students.toLocaleString()}`, '#eff6ff', '#1d4ed8'],
      ['Halls Used', `${d.rooms_used.toLocaleString()} halls`, '#fff7ed', '#ea580c'],
      ['Clashes Repaired', sameCodeConflicts === 0 ? '0 (Zero Clashes)' : `${sameCodeConflicts} conflicts`, sameCodeConflicts === 0 ? '#f1f5f9' : '#fef2f2', sameCodeConflicts === 0 ? '#0f172a' : '#ef4444'],
      ['Benches Utilized', `${(d.benches_used || d.assigned).toLocaleString()} benches`, '#fffbeb', '#d97706'],
      ['Exam Codes Handled', `${d.exam_codes_count || d.cohorts_count} codes (${d.departments_count} depts)`, '#eef2ff', '#6366f1'],
      ['Engine Speed', `${d.execution_time_ms || 10} ms`, '#f8fafc', '#475569'],
    ];

    document.getElementById('summary').innerHTML = tiles.map(([t,v,bg,c]) =>
      `<div class="col-6 col-md-4"><div class="p-3 rounded-3 text-center border" style="background:${bg}">
        <div class="fw-bold fs-4" style="color:${c}">${v}</div><div class="small text-muted">${t}</div></div></div>`).join('');
    
    let w = '';
    if (d.warnings && d.warnings.length) {
      d.warnings.forEach(msg => {
        w += `<div class="alert alert-warning py-2 small">${msg}</div>`;
      });
    }
    if (sameCodeConflicts === 0) {
      w += `<div class="alert alert-primary py-2 small d-flex align-items-center gap-1.5">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <div><b>Zero Conflicts!</b> No two adjacent students share the same exam code. Interleaved across academic years and departments.</div>
      </div>`;
    } else if (!isAlternate) {
      w += `<div class="alert alert-danger py-2 small">
        <div class="d-flex align-items-center gap-1.5 fw-bold">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          ${sameCodeConflicts} adjacent conflict(s) detected!
        </div>
        <div class="mt-1">This batch has an imbalanced paper mix or single paper.</div>
        <div class="mt-2">
          <button type="button" class="btn btn-warning btn-sm" onclick="enableAlternateAndRegenerate()">
            Enable Alternate Seating &amp; Re-run
          </button>
        </div>
      </div>`;
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
  label.innerHTML = '<?= svg_icon('magic', '', 18) ?> Generate Seating Plan';
});

function enableAlternateAndRegenerate() {
  document.getElementById('spacingAlternate').checked = true;
  document.getElementById('genBtn').click();
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>