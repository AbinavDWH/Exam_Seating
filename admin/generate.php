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
      Generate Seating
    </h1>
    <div class="page-subtitle">
      <span>Automatic conflict-free interleaving across departments and examination papers</span>
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
          <h6>Automatic Seating</h6>
          <p>Interleaves multi-year cohorts and branches across examination halls.</p>
        </div>
      </div>

      <!-- Step 1 -->
      <div class="step-block mb-3">
        <div class="step-block-header">
          <span class="step-number">1</span>
          <span>Select Exam Session</span>
        </div>
        <label class="form-label" for="examSelect">Target examination</label>
        <select id="examSelect" class="form-select" onchange="updateExamInfo()">
          <option value="">— Select an exam session —</option>
          <?php foreach ($exams as $e): ?>
            <option value="<?= $e['id'] ?>" data-students="<?= (int)$e['students'] ?>" <?= $e['id']==$preselect?'selected':'' ?>>
              <?= htmlspecialchars($e['exam_name']) ?> (<?= number_format((int)$e['students']) ?> students)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Step 2 -->
      <div class="step-block mb-3">
        <div class="step-block-header">
          <span class="step-number">2</span>
          <span>Hall and Bench Layout</span>
        </div>
        
        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="benchMode" id="modeConfigured" value="configured" checked onchange="toggleMode()">
          <label class="form-check-label fw-semibold" for="modeConfigured">
            Use saved room layouts
            <span class="d-block text-muted small fw-normal">Use <?= number_format($roomsCount) ?> active campus halls currently configured</span>
          </label>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="benchMode" id="modeCustom" value="custom" onchange="toggleMode()">
          <label class="form-check-label fw-semibold" for="modeCustom">
            Custom rooms and desks
            <span class="d-block text-muted small fw-normal">Specify room dimensions dynamically</span>
          </label>
        </div>

        <!-- Custom Inputs Box -->
        <div id="customInputs" class="p-3 bg-light rounded-3 border mt-3" style="display:none;">
          <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label text-muted mb-0" for="numRooms">Number of exam halls</label>
            </div>
            <input type="number" id="numRooms" class="form-control" value="2" min="1" max="5000" oninput="recalcCustom()">
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label text-muted mb-1" for="benchesPerRoom">Rows (benches)</label>
              <input type="number" id="benchesPerRoom" class="form-control" value="15" min="1" max="100" oninput="recalcCustom()">
            </div>
            <div class="col-6">
              <label class="form-label text-muted mb-1" for="studentsPerBench">Seats / bench</label>
              <input type="number" id="studentsPerBench" class="form-control" value="2" min="1" max="20" oninput="recalcCustom()">
            </div>
          </div>
          <div class="d-flex gap-1 flex-wrap mb-2">
            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setPreset(1000, 15, 2)">1,000 halls (30k desks)</button>
            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setPreset(500, 15, 2)">500 halls</button>
            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setPreset(50, 15, 2)">50 halls</button>
            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size:11px" onclick="setPreset(2, 5, 6)">2 halls (60 desks)</button>
          </div>
          <div id="customCalc" class="small text-muted text-center pt-1 border-top">
            Calculated capacity: <strong>60 desks</strong> (2 rooms × 15 rows × 2 cols = 30 desks/room)
          </div>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="step-block mb-3">
        <div class="step-block-header">
          <span class="step-number">3</span>
          <span>Spacing and Options</span>
        </div>
        
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="spacingAlternate">
          <label class="form-check-label fw-semibold" for="spacingAlternate">
            Alternate empty desks (checkerboard)
            <span class="d-block text-muted small fw-normal">Leaves every other seat empty; needs twice the seats.</span>
          </label>
        </div>

        <div class="form-check mb-1">
          <input class="form-check-input" type="checkbox" id="simulateOnly">
          <label class="form-check-label fw-semibold" for="simulateOnly">
            Simulate in memory (preview only)
            <span class="d-block text-muted small fw-normal">Calculates seating plan and checks clashes without modifying saved records.</span>
          </label>
        </div>
      </div>

      <button id="genBtn" class="btn btn-grad w-100 py-2.5">
        <span id="genLabel" class="d-inline-flex align-items-center gap-1.5">
          <?= svg_icon('magic', '', 18) ?>
          Generate seating
        </span>
      </button>
    </div>
  </div>

  <div class="col-lg-7">
    <!-- Results Summary Panel -->
    <div class="table-card" id="resultPanel" style="display:none">
      <div id="simulationBanner" class="mb-3" style="display:none;"></div>

      <!-- Single Plain Sentence Result -->
      <div class="p-3 mb-3 rounded-3" style="background:#FAF8F5; border:1px solid var(--border,#D8CFBF);">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div id="resultSentence" style="font-family:'Plus Jakarta Sans', system-ui, sans-serif; font-size:1.75rem; color:var(--ink-text,#2B2E27); font-weight:600; line-height:1.2;">
              —
            </div>
            <div id="resultSub" class="small text-muted mt-1">
              All candidates seated conflict-free across designated rooms.
            </div>
          </div>
          <div id="resultBadge"></div>
        </div>
      </div>

      <div id="warnings" class="mb-3"></div>

      <!-- Mini-map per hall -->
      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="fw-bold mb-0" style="color:var(--ink-text,#2B2E27);">Hall room maps</h6>
          <span class="text-muted small" id="hallsCountLabel"></span>
        </div>
        <div id="miniMapsContainer" class="minimap-hall-container"></div>
      </div>

      <div class="d-flex gap-2 pt-3 border-top flex-wrap" id="actions">
        <a id="printLink" href="#" class="btn btn-primary-soft btn-sm" title="View Sheets">
          <?= svg_icon('printer', 'me-1', 15) ?>View sheets
        </a>
        <a id="exportLink" href="#" class="btn btn-outline-secondary btn-sm" style="border-radius:6px;" title="Export CSV">
          <?= svg_icon('download', 'me-1', 15) ?>Export CSV
        </a>
        <a href="<?= htmlspecialchars(getenv('STUDENT_PORTAL_URL') ?: '../') ?>find" target="_blank" class="btn btn-outline-secondary btn-sm" style="border-radius:6px;">
          <?= svg_icon('box-arrow-up-right', 'me-1', 15) ?>Open student portal
        </a>
      </div>
    </div>

    <!-- Friendly Placeholder / Empty State -->
    <div class="table-card text-muted text-center p-5" id="placeholder">
      <div class="empty-state-icon mx-auto mb-3">
        <?= svg_icon('magic', '', 28) ?>
      </div>
      <h6 class="fw-bold text-main">Ready to allocate seating</h6>
      <p class="text-muted small mb-0">Select an exam session, configure bench capacity, and click <strong>Generate seating</strong>.</p>
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
  document.getElementById('customCalc').innerHTML = `Calculated capacity: <b>${total.toLocaleString()} desks</b> across <b>${r.toLocaleString()} halls</b> (${b} rows × ${s} cols = ${b * s} desks/room)`;
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
    window.showToast('Select an exam session first.', 'danger');
    return;
  }

  const opt = sel.options[sel.selectedIndex];
  const count = opt ? +opt.getAttribute('data-students') : 0;

  const isCustom = document.getElementById('modeCustom').checked;
  const isAlternate = document.getElementById('spacingAlternate').checked;
  const isSimulate = document.getElementById('simulateOnly').checked;

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

  btn.disabled = true;
  label.innerHTML = `Allocating ${count > 0 ? Number(count).toLocaleString() + ' ' : ''}seats…`;

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
    
    document.getElementById('placeholder').style.display = 'none';
    document.getElementById('resultPanel').style.display = 'block';

    const simBanner = document.getElementById('simulationBanner');
    if (d.simulated) {
      simBanner.innerHTML = `<div class="p-2.5 rounded-3 border small d-flex align-items-center gap-2" style="background:#FFF9F0; border-color:#E2C8A8; color:#8A5416;">
        <span class="badge" style="background:#E2C8A8; color:#4E310C; font-weight:700;">Preview only</span>
        <span>Nothing saved to database. Seating was calculated in memory.</span>
      </div>`;
      simBanner.style.display = 'block';
    } else {
      simBanner.style.display = 'none';
    }

    const sameCodeConflicts = d.same_exam_code_conflicts ?? d.same_paper_conflicts ?? 0;
    
    // One plain sentence
    const sentenceEl = document.getElementById('resultSentence');
    sentenceEl.textContent = `${d.assigned.toLocaleString()} seated, ${sameCodeConflicts} clashes`;
    if (sameCodeConflicts > 0) {
      sentenceEl.style.color = 'var(--brand-red, #9C4632)';
    } else {
      sentenceEl.style.color = 'var(--ink-text, #2B2E27)';
    }

    const subEl = document.getElementById('resultSub');
    if (d.unassigned > 0) {
      subEl.textContent = `${d.unassigned.toLocaleString()} students unseated due to room capacity.`;
    } else {
      subEl.textContent = `Seated across ${d.rooms_used} hall${d.rooms_used === 1 ? '' : 's'}. Interleaved across branches.`;
    }

    const badgeEl = document.getElementById('resultBadge');
    if (sameCodeConflicts === 0) {
      badgeEl.innerHTML = `<span class="badge" style="background:rgba(139,154,110,0.2); color:#4F5C3B; border:1px solid #8B9A6E;">OK</span>`;
    } else {
      badgeEl.innerHTML = `<span class="badge" style="background:rgba(156,70,50,0.15); color:#9C4632; border:1px solid #9C4632;">Clash</span>`;
    }

    let w = '';
    if (d.warnings && d.warnings.length) {
      d.warnings.forEach(msg => {
        w += `<div class="p-2 rounded-2 small mb-1 border" style="background:#FFF9F0; border-color:#E2C8A8; color:#6A4414;">${msg}</div>`;
      });
    }
    if (sameCodeConflicts > 0 && !isAlternate) {
      w += `<div class="p-2 rounded-2 small mt-2 border" style="background:rgba(156,70,50,0.1); border-color:#9C4632; color:#9C4632;">
        <b>${sameCodeConflicts} conflict(s) detected.</b> This batch has an imbalanced paper mix or single paper.
        <div class="mt-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="enableAlternateAndRegenerate()">
            Enable alternate seating and re-run
          </button>
        </div>
      </div>`;
    }
    document.getElementById('warnings').innerHTML = w;

    // Render Mini-maps per Hall
    renderMiniMaps(d.halls || []);

    document.getElementById('printLink').href = 'print_plan.php?exam_id=' + examId;
    document.getElementById('exportLink').href = '../api/export.php?exam_id=' + examId;
    
    window.showToast(`Seating generated: ${d.assigned} seated, ${sameCodeConflicts} clashes.`, 'success');
  } catch (e) {
    window.showToast(e.message, 'danger');
  }
  btn.disabled = false;
  label.innerHTML = '<?= svg_icon('magic', '', 18) ?> Generate seating';
});

function renderMiniMaps(halls) {
  const container = document.getElementById('miniMapsContainer');
  const countLabel = document.getElementById('hallsCountLabel');
  if (!container) return;

  if (!halls || halls.length === 0) {
    container.innerHTML = '<div class="text-muted small">No hall layouts to preview.</div>';
    countLabel.textContent = '';
    return;
  }

  countLabel.textContent = `${halls.length} hall${halls.length === 1 ? '' : 's'}`;

  let html = '';
  halls.forEach(hall => {
    // Map of row:col => seat
    const seatMap = {};
    if (hall.seats) {
      hall.seats.forEach(s => {
        seatMap[`${s.row}:${s.col}`] = s;
      });
    }

    html += `<div class="hall-minimap-card">
      <div class="minimap-title">
        <span>Hall ${hall.room_no}</span>
        <span class="text-muted small fw-normal">${hall.seated} / ${hall.rows * hall.cols}</span>
      </div>
      <div class="hall-board-indicator">Front · Board</div>
      <div class="minimap-grid">`;

    for (let r = 1; r <= hall.rows; r++) {
      html += `<div class="minimap-row">`;
      for (let c = 1; c <= hall.cols; c++) {
        const s = seatMap[`${r}:${c}`];
        if (s) {
          html += `<div class="minimap-seat-box occupied" title="Row ${r}, Col ${c}: ${s.roll} (${s.code})"></div>`;
        } else {
          html += `<div class="minimap-seat-box empty" title="Row ${r}, Col ${c}: Empty"></div>`;
        }
      }
      html += `</div>`;
    }

    html += `</div></div>`;
  });

  container.innerHTML = html;
}

function enableAlternateAndRegenerate() {
  document.getElementById('spacingAlternate').checked = true;
  document.getElementById('genBtn').click();
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>