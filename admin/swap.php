<?php
$pageTitle = 'Swap Seats';
require __DIR__ . '/_header.php';

$exams = db()->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM seating se WHERE se.exam_id = e.id) AS seated_count
    FROM exams e
    ORDER BY e.exam_date DESC
")->fetchAll();

$preselect = (int)($_GET['exam_id'] ?? 0);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= svg_icon('move', 'text-primary', 26) ?>
      Manual Seat Swap &amp; Clash Prevention
    </h1>
    <div class="page-subtitle">
      <span>Exchange desk assignments between two students with real-time adjacency conflict checking</span>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="table-card">
      <div class="text-center mb-4">
        <div class="stat-icon mx-auto mb-2" style="background:#fef3c7; color:#d97706;">
          <?= svg_icon('move', '', 26) ?>
        </div>
        <h6 class="fw-bold mt-2 mb-1">Swap Student Seats</h6>
        <p class="text-muted small mb-0">
          Swaps seating coordinates between two candidates and verifies that neither desk creates a conflict with adjacent exam papers.
        </p>
      </div>

      <!-- Exam Selection -->
      <label class="form-label small fw-semibold">1. Select Examination</label>
      <select id="swapExam" class="form-select mb-3">
        <option value="">— Select an exam session —</option>
        <?php foreach ($exams as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $e['id'] == $preselect ? 'selected' : '' ?>>
            <?= htmlspecialchars($e['exam_name']) ?> (<?= (int)$e['seated_count'] ?> seated)
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Roll Numbers -->
      <label class="form-label small fw-semibold">2. Students to Swap</label>
      <div class="mb-3">
        <label class="form-label text-muted small mb-1">First Student Roll Number</label>
        <input type="text" id="rollA" class="form-control" placeholder="e.g. 23CS101" style="text-transform:uppercase">
      </div>
      <div class="mb-3">
        <label class="form-label text-muted mb-1 small">Second Student Roll Number</label>
        <input type="text" id="rollB" class="form-control" placeholder="e.g. 23EC105" style="text-transform:uppercase">
      </div>

      <button id="previewBtn" class="btn btn-grad w-100 py-2.5">
        <span id="previewLabel" class="d-inline-flex align-items-center gap-1.5">
          <?= svg_icon('search', '', 18) ?>
          Preview Swap &amp; Check Clashes
        </span>
      </button>
    </div>
  </div>

  <div class="col-lg-7">
    <!-- Placeholder -->
    <div class="table-card text-muted text-center p-5" id="swapPlaceholder">
      <div class="empty-state-icon mx-auto mb-3">
        <?= svg_icon('move', '', 28) ?>
      </div>
      <h6 class="fw-bold text-main">Ready to Inspect &amp; Swap</h6>
      <p class="text-muted small mb-0">Select an exam, enter both roll numbers, and click <strong>Preview Swap &amp; Check Clashes</strong>.</p>
    </div>

    <!-- Preview & Clash Panel -->
    <div class="table-card" id="swapPanel" style="display:none">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Seat Swap Preview &amp; Verification</h6>
        <span class="status-pill status-upcoming" id="swapBadge">Checking…</span>
      </div>

      <div class="row g-3 mb-4">
        <!-- Student A Card -->
        <div class="col-md-6">
          <div class="p-3 rounded-3 border bg-light h-100" id="cardA">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge bg-primary">Candidate A</span>
              <span class="small text-muted" id="aRoll"></span>
            </div>
            <h6 class="fw-bold mb-1" id="aName">—</h6>
            <div class="small text-muted mb-2">
              <span id="aBranch"></span> · Code: <b id="aCode"></b>
            </div>
            <div class="p-2 rounded border bg-white small">
              <div><b>Current:</b> Hall <span id="aCurRoom"></span> (Row <span id="aCurRow"></span>, Col <span id="aCurCol"></span>)</div>
              <div class="text-success mt-1"><b>Will Move To:</b> Hall <span id="aNewRoom"></span> (Row <span id="aNewRow"></span>, Col <span id="aNewCol"></span>)</div>
            </div>
          </div>
        </div>

        <!-- Student B Card -->
        <div class="col-md-6">
          <div class="p-3 rounded-3 border bg-light h-100" id="cardB">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge bg-info text-dark">Candidate B</span>
              <span class="small text-muted" id="bRoll"></span>
            </div>
            <h6 class="fw-bold mb-1" id="bName">—</h6>
            <div class="small text-muted mb-2">
              <span id="bBranch"></span> · Code: <b id="bCode"></b>
            </div>
            <div class="p-2 rounded border bg-white small">
              <div><b>Current:</b> Hall <span id="bCurRoom"></span> (Row <span id="bCurRow"></span>, Col <span id="bCurCol"></span>)</div>
              <div class="text-success mt-1"><b>Will Move To:</b> Hall <span id="bNewRoom"></span> (Row <span id="bNewRow"></span>, Col <span id="bNewCol"></span>)</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Clash Warnings Box -->
      <div id="swapWarnings" class="mb-4"></div>

      <!-- Action Box -->
      <div class="p-3 rounded-3 border bg-light d-flex flex-column gap-2" id="swapActionBox">
        <div class="form-check" id="forceCheckWrapper" style="display:none">
          <input class="form-check-input" type="checkbox" id="forceSwapCheck">
          <label class="form-check-label small text-danger fw-semibold" for="forceSwapCheck">
            I understand that this swap causes adjacent paper conflicts. Proceed anyway.
          </label>
        </div>
        <div class="d-flex gap-2">
          <button id="confirmSwapBtn" class="btn btn-success btn-sm px-4 py-2">
            Confirm &amp; Execute Swap
          </button>
          <button id="cancelSwapBtn" class="btn btn-outline-secondary btn-sm" onclick="resetSwap()">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let currentSwapData = null;

const previewBtn = document.getElementById('previewBtn');
const confirmSwapBtn = document.getElementById('confirmSwapBtn');

previewBtn.addEventListener('click', async () => {
  const examId = document.getElementById('swapExam').value;
  const rollA = document.getElementById('rollA').value.trim();
  const rollB = document.getElementById('rollB').value.trim();

  if (!examId) {
    window.showToast('Please select an exam first.', 'danger');
    return;
  }
  if (!rollA || !rollB) {
    window.showToast('Please enter both roll numbers.', 'danger');
    return;
  }
  if (rollA.toUpperCase() === rollB.toUpperCase()) {
    window.showToast('Roll numbers must be different.', 'danger');
    return;
  }

  previewBtn.disabled = true;
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch('../api/swap.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({
        exam_id: +examId,
        roll_a: rollA,
        roll_b: rollB,
        check_only: true
      })
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Failed to inspect seats');
    currentSwapData = json;
    renderSwapPreview(json);
  } catch (e) {
    window.showToast(e.message, 'danger');
  } finally {
    previewBtn.disabled = false;
  }
});

function renderSwapPreview(data) {
  document.getElementById('swapPlaceholder').style.display = 'none';
  document.getElementById('swapPanel').style.display = 'block';

  const a = data.student_a;
  const b = data.student_b;

  document.getElementById('aRoll').textContent = a.roll_no;
  document.getElementById('aName').textContent = a.name;
  document.getElementById('aBranch').textContent = `${a.branch} · Sem ${a.semester}`;
  document.getElementById('aCode').textContent = a.exam_code;
  document.getElementById('aCurRoom').textContent = `${a.room_no} (${a.block})`;
  document.getElementById('aCurRow').textContent = a.row_num;
  document.getElementById('aCurCol').textContent = a.col_num;
  document.getElementById('aNewRoom').textContent = `${b.room_no} (${b.block})`;
  document.getElementById('aNewRow').textContent = b.row_num;
  document.getElementById('aNewCol').textContent = b.col_num;

  document.getElementById('bRoll').textContent = b.roll_no;
  document.getElementById('bName').textContent = b.name;
  document.getElementById('bBranch').textContent = `${b.branch} · Sem ${b.semester}`;
  document.getElementById('bCode').textContent = b.exam_code;
  document.getElementById('bCurRoom').textContent = `${b.room_no} (${b.block})`;
  document.getElementById('bCurRow').textContent = b.row_num;
  document.getElementById('bCurCol').textContent = b.col_num;
  document.getElementById('bNewRoom').textContent = `${a.room_no} (${a.block})`;
  document.getElementById('bNewRow').textContent = a.row_num;
  document.getElementById('bNewCol').textContent = a.col_num;

  const badge = document.getElementById('swapBadge');
  const warnDiv = document.getElementById('swapWarnings');
  const forceCheck = document.getElementById('forceCheckWrapper');
  const forceInput = document.getElementById('forceSwapCheck');

  forceInput.checked = false;

  if (data.has_clash) {
    badge.className = 'status-pill status-cancelled';
    badge.innerHTML = '<span class="status-dot"></span>Clash Detected';
    forceCheck.style.display = 'block';

    let html = `<div class="alert alert-danger py-2 small">
      <b>⚠️ Conflict Warning:</b> Swapping these seats will introduce ${data.clashes.length} adjacent conflict(s):
      <ul class="mb-0 mt-1 ps-3">`;
    data.clashes.forEach(c => {
      html += `<li>${c}</li>`;
    });
    html += `</ul></div>`;
    warnDiv.innerHTML = html;
  } else {
    badge.className = 'status-pill status-upcoming';
    badge.innerHTML = '<span class="status-dot"></span>Conflict-Free Swap';
    forceCheck.style.display = 'none';
    warnDiv.innerHTML = `<div class="alert alert-success py-2 small">
      <b>✓ Zero Conflicts!</b> Both desks can be safely exchanged without creating adjacent same-exam-code clashes.
    </div>`;
  }
}

confirmSwapBtn.addEventListener('click', async () => {
  if (!currentSwapData) return;

  const examId = document.getElementById('swapExam').value;
  const rollA = document.getElementById('rollA').value.trim();
  const rollB = document.getElementById('rollB').value.trim();
  const force = document.getElementById('forceSwapCheck').checked;

  if (currentSwapData.has_clash && !force) {
    window.showToast('Please check the confirmation box to force a swap with conflicts.', 'warning');
    return;
  }

  confirmSwapBtn.disabled = true;
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch('../api/swap.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({
        exam_id: +examId,
        roll_a: rollA,
        roll_b: rollB,
        force: force
      })
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || json.message || 'Swap failed');

    window.showToast(json.message || 'Seats swapped successfully!', 'success');
    resetSwap();
  } catch (e) {
    window.showToast(e.message, 'danger');
  } finally {
    confirmSwapBtn.disabled = false;
  }
});

function resetSwap() {
  currentSwapData = null;
  document.getElementById('swapPanel').style.display = 'none';
  document.getElementById('swapPlaceholder').style.display = 'block';
  document.getElementById('rollA').value = '';
  document.getElementById('rollB').value = '';
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>
