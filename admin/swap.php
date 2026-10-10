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
      <?= svg_icon('swap', 'text-primary', 26) ?>
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
      <div class="card-title-header mb-3">
        <div class="card-title-icon">
          <?= svg_icon('swap', '', 20) ?>
        </div>
        <div class="card-title-text">
          <h6>Swap Student Seats</h6>
          <p>Exchanges desk coordinates between two candidates and verifies adjacent paper conflict safety.</p>
        </div>
      </div>

      <!-- Step 1 Block: Target Examination (Item 19) -->
      <div class="step-block mb-3">
        <div class="step-block-header">
          <span class="step-number">1</span>
          <span>Select Examination</span>
        </div>
        <label class="form-label" for="swapExam">Target Exam Session</label>
        <select id="swapExam" class="form-select" onchange="handleExamChange()">
          <option value="">— Select an exam session —</option>
          <?php foreach ($exams as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $e['id'] == $preselect ? 'selected' : '' ?>>
              <?= htmlspecialchars($e['exam_name']) ?> (<?= number_format((int)$e['seated_count']) ?> seated)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Step 2 Block: Students to Swap (Item 19, 20, 21) -->
      <div class="step-block mb-3">
        <div class="step-block-header">
          <span class="step-number">2</span>
          <span>Students to Swap</span>
        </div>

        <!-- Student A -->
        <div class="mb-3">
          <label class="form-label" for="rollA">First Student Roll Number</label>
          <input type="text" id="rollA" class="form-control" placeholder="e.g. 2116241801001" style="text-transform:uppercase" oninput="lookupStudent('A')">
          <!-- Live Candidate A Card (Item 21) -->
          <div id="previewCardA" class="mt-2 p-2.5 rounded-3 border bg-light small" style="display:none;"></div>
        </div>

        <!-- Student B -->
        <div class="mb-1">
          <label class="form-label" for="rollB">Second Student Roll Number</label>
          <input type="text" id="rollB" class="form-control" placeholder="e.g. 2116251001001" style="text-transform:uppercase" oninput="lookupStudent('B')">
          <!-- Live Candidate B Card (Item 21) -->
          <div id="previewCardB" class="mt-2 p-2.5 rounded-3 border bg-light small" style="display:none;"></div>
        </div>
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
    <!-- Placeholder (Item 28) -->
    <div class="table-card text-muted text-center p-5" id="swapPlaceholder">
      <div class="empty-state-icon mx-auto mb-3">
        <?= svg_icon('swap', '', 28) ?>
      </div>
      <h6 class="fw-bold text-main">Ready to Inspect &amp; Swap</h6>
      <p class="text-muted small mb-0">Select an exam session, enter both roll numbers, and click <strong>Preview Swap &amp; Check Clashes</strong>.</p>
    </div>

    <!-- Preview & Clash Panel (Item 22) -->
    <div class="table-card" id="swapPanel" style="display:none">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
          <div class="text-primary"><?= svg_icon('swap', '', 20) ?></div>
          <h6 class="fw-bold mb-0">Seat Swap Preview &amp; Verification</h6>
        </div>
        <span class="status-pill status-upcoming" id="swapBadge">Checking…</span>
      </div>

      <div class="row g-3 mb-3">
        <!-- Student A Card -->
        <div class="col-md-6">
          <div class="p-3 rounded-3 border bg-light h-100" id="cardA">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge bg-primary">Candidate A</span>
              <span class="small text-muted font-monospace fw-bold" id="aRoll"></span>
            </div>
            <h6 class="fw-bold mb-1 text-main" id="aName">—</h6>
            <div class="small text-muted mb-2">
              <span id="aBranch"></span> · Paper: <b id="aCode"></b>
            </div>
            <div class="p-2.5 rounded border bg-white small">
              <div class="text-muted"><b>Current:</b> Hall <span id="aCurRoom" class="text-dark fw-bold"></span> (Row <span id="aCurRow"></span>, Col <span id="aCurCol"></span>)</div>
              <div class="text-success mt-1 fw-semibold"><b>Will Move To:</b> Hall <span id="aNewRoom" class="fw-bold"></span> (Row <span id="aNewRow"></span>, Col <span id="aNewCol"></span>)</div>
            </div>
          </div>
        </div>

        <!-- Student B Card -->
        <div class="col-md-6">
          <div class="p-3 rounded-3 border bg-light h-100" id="cardB">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge bg-secondary text-white">Candidate B</span>
              <span class="small text-muted font-monospace fw-bold" id="bRoll"></span>
            </div>
            <h6 class="fw-bold mb-1 text-main" id="bName">—</h6>
            <div class="small text-muted mb-2">
              <span id="bBranch"></span> · Paper: <b id="bCode"></b>
            </div>
            <div class="p-2.5 rounded border bg-white small">
              <div class="text-muted"><b>Current:</b> Hall <span id="bCurRoom" class="text-dark fw-bold"></span> (Row <span id="bCurRow"></span>, Col <span id="bCurCol"></span>)</div>
              <div class="text-success mt-1 fw-semibold"><b>Will Move To:</b> Hall <span id="bNewRoom" class="fw-bold"></span> (Row <span id="bNewRow"></span>, Col <span id="bNewCol"></span>)</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Verdict & Clash Warnings Box (Item 22) -->
      <div id="swapWarnings" class="mb-3"></div>

      <!-- Action Box -->
      <div class="p-3 rounded-3 border bg-light d-flex flex-column gap-2" id="swapActionBox">
        <div class="form-check" id="forceCheckWrapper" style="display:none">
          <input class="form-check-input" type="checkbox" id="forceSwapCheck" onchange="handleForceCheckChange()">
          <label class="form-check-label small text-danger fw-semibold" for="forceSwapCheck">
            I understand that this swap causes adjacent paper conflicts. Proceed anyway.
          </label>
        </div>
        <div class="d-flex gap-2">
          <button id="confirmSwapBtn" class="btn btn-success px-4 py-2 fw-semibold">
            Confirm &amp; Execute Swap
          </button>
          <button id="cancelSwapBtn" class="btn btn-outline-secondary px-3 py-2" onclick="resetSwap()">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Swaps Audit Log (Item 24) -->
<div class="table-card mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
      <div class="text-primary"><?= svg_icon('clock', '', 18) ?></div>
      <h6 class="fw-bold mb-0">Recent Swaps Audit Log</h6>
    </div>
    <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none p-0" onclick="clearRecentSwaps()">Clear Log</button>
  </div>
  <div id="recentSwapsContainer">
    <div class="text-muted small text-center py-3" id="noRecentSwapsMsg">
      No seat swaps executed in this session yet.
    </div>
    <div class="d-flex flex-column gap-2" id="recentSwapsList" style="display:none;"></div>
  </div>
</div>

<script>
let currentSwapData = null;
const RECENT_SWAPS_KEY = 'examseat_recent_swaps';

const previewBtn = document.getElementById('previewBtn');
const confirmSwapBtn = document.getElementById('confirmSwapBtn');

let lookupTimers = { A: null, B: null };

function handleExamChange() {
  document.getElementById('previewCardA').style.display = 'none';
  document.getElementById('previewCardB').style.display = 'none';
  if (document.getElementById('rollA').value.trim()) lookupStudent('A');
  if (document.getElementById('rollB').value.trim()) lookupStudent('B');
}

// Live student seat lookup (Item 21)
function lookupStudent(target) {
  clearTimeout(lookupTimers[target]);
  const examId = document.getElementById('swapExam').value;
  const roll = document.getElementById(target === 'A' ? 'rollA' : 'rollB').value.trim();
  const card = document.getElementById(target === 'A' ? 'previewCardA' : 'previewCardB');

  if (!examId || roll.length < 3) {
    card.style.display = 'none';
    return;
  }

  lookupTimers[target] = setTimeout(async () => {
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const res = await fetch('../api/swap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ action: 'lookup', exam_id: +examId, roll: roll })
      });
      const json = await res.json();
      if (!res.ok || !json.student) {
        card.innerHTML = `<span class="text-muted"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>No seat allocated for "${roll}" in this session.</span>`;
        card.style.display = 'block';
        return;
      }
      const s = json.student;
      card.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-1">
          <strong class="text-main">${s.name}</strong>
          <span class="badge bg-secondary-subtle text-secondary">${s.branch} · Sem ${s.semester}</span>
        </div>
        <div class="text-muted">
          Current: <b>Hall ${s.room_no} (${s.block})</b> · Row ${s.row_num}, Col ${s.col_num} · Desk #${s.bench_no || s.row_num}
        </div>
      `;
      card.style.display = 'block';
    } catch {
      card.style.display = 'none';
    }
  }, 350);
}

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

  // Clear verdict banner (Item 22)
  if (data.has_clash) {
    badge.className = 'status-pill status-cancelled';
    badge.innerHTML = '<span class="status-dot"></span>Clash Detected';
    forceCheck.style.display = 'block';
    confirmSwapBtn.disabled = true;

    let html = `<div class="alert alert-danger py-2.5 px-3 rounded-3 small">
      <div class="d-flex align-items-center gap-1.5 fw-bold text-danger mb-1">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Conflict Warning
      </div>
      <div>Swapping these seats will introduce ${data.clashes.length} adjacent conflict(s):</div>
      <ul class="mb-0 mt-1 ps-3">`;
    data.clashes.forEach(c => {
      html += `<li>${c}</li>`;
    });
    html += `</ul></div>`;
    warnDiv.innerHTML = html;
  } else {
    badge.className = 'status-pill status-ongoing';
    badge.innerHTML = '<span class="status-dot"></span>Conflict-Free Swap';
    forceCheck.style.display = 'none';
    confirmSwapBtn.disabled = false;
    warnDiv.innerHTML = `<div class="alert alert-primary py-2.5 px-3 rounded-3 small d-flex align-items-center gap-2">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      <div><b>Zero Conflicts Found!</b> Both desks can be safely exchanged without creating adjacent same-exam-code clashes.</div>
    </div>`;
  }
}

function handleForceCheckChange() {
  const force = document.getElementById('forceSwapCheck').checked;
  confirmSwapBtn.disabled = !force;
}

confirmSwapBtn.addEventListener('click', async () => {
  if (!currentSwapData) return;

  const examId = document.getElementById('swapExam').value;
  const examSel = document.getElementById('swapExam');
  const examName = examSel.options[examSel.selectedIndex]?.text || 'Exam';
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

    // Update both student preview cards in place (Item 23)
    lookupStudent('A');
    lookupStudent('B');

    // Add to Recent Swaps Audit Log (Item 24)
    recordRecentSwap({
      exam: examName,
      rollA: rollA,
      rollB: rollB,
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      date: new Date().toLocaleDateString([], { day: 'numeric', month: 'short' }),
    });

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
}

function recordRecentSwap(entry) {
  try {
    const list = JSON.parse(localStorage.getItem(RECENT_SWAPS_KEY)) || [];
    list.unshift(entry);
    localStorage.setItem(RECENT_SWAPS_KEY, JSON.stringify(list.slice(0, 10)));
    renderRecentSwaps();
  } catch {}
}

function clearRecentSwaps() {
  localStorage.removeItem(RECENT_SWAPS_KEY);
  renderRecentSwaps();
}

function renderRecentSwaps() {
  try {
    const list = JSON.parse(localStorage.getItem(RECENT_SWAPS_KEY)) || [];
    const container = document.getElementById('recentSwapsList');
    const emptyMsg = document.getElementById('noRecentSwapsMsg');

    if (!list.length) {
      emptyMsg.style.display = 'block';
      container.style.display = 'none';
      return;
    }

    emptyMsg.style.display = 'none';
    container.style.display = 'flex';
    container.innerHTML = list.map(item => `
      <div class="d-flex justify-content-between align-items-center p-2 px-3 rounded-2 bg-light border small">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary-subtle text-primary fw-bold font-monospace">${item.rollA}</span>
          <span class="text-muted">⇄</span>
          <span class="badge bg-secondary-subtle text-secondary fw-bold font-monospace">${item.rollB}</span>
          <span class="text-muted ms-1">(${item.exam})</span>
        </div>
        <span class="text-muted small">${item.date} · ${item.time}</span>
      </div>
    `).join('');
  } catch {}
}

document.addEventListener('DOMContentLoaded', () => {
  renderRecentSwaps();
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>
