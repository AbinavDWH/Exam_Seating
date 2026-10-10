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
      Swap Seats
    </h1>
    <div class="page-subtitle">
      <span>Click any two seats on the room map to swap student assignments</span>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Left Side: Selection & Controls (col-lg-4) -->
  <div class="col-lg-4">
    <div class="table-card">
      <div class="card-title-header mb-3">
        <div class="card-title-icon">
          <?= svg_icon('swap', '', 20) ?>
        </div>
        <div class="card-title-text">
          <h6>Swap Student Seats</h6>
          <p>Click two seats on the map to exchange desks.</p>
        </div>
      </div>

      <!-- Exam Selector -->
      <div class="mb-3">
        <label class="form-label" for="swapExam">Target examination</label>
        <select id="swapExam" class="form-select" onchange="handleExamChange()">
          <option value="">— Select an exam session —</option>
          <?php foreach ($exams as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $e['id'] == $preselect ? 'selected' : '' ?>>
              <?= htmlspecialchars($e['exam_name']) ?> (<?= number_format((int)$e['seated_count']) ?> seated)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Selected Seats Container -->
      <div class="mb-3">
        <label class="form-label">Selected seats</label>
        
        <!-- Seat A Card -->
        <div id="seatCardA" class="p-2.5 rounded-3 border mb-2" style="background:#FAF8F5; border-color:var(--border,#D8CFBF);">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="badge" style="background:#FFBDA3; color:#2B2E27; font-weight:700;">Seat 1</span>
            <span id="seatARoll" class="font-monospace small fw-bold text-muted">—</span>
          </div>
          <div id="seatAName" class="fw-semibold text-main small">Click a seat on the map</div>
          <div id="seatAMeta" class="text-muted small" style="font-size:0.75rem;">—</div>
        </div>

        <!-- Seat B Card -->
        <div id="seatCardB" class="p-2.5 rounded-3 border" style="background:#FAF8F5; border-color:var(--border,#D8CFBF);">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="badge" style="background:#8B9A6E; color:#FFFFFF; font-weight:700;">Seat 2</span>
            <span id="seatBRoll" class="font-monospace small fw-bold text-muted">—</span>
          </div>
          <div id="seatBName" class="fw-semibold text-main small">Click second seat to swap</div>
          <div id="seatBMeta" class="text-muted small" style="font-size:0.75rem;">—</div>
        </div>
      </div>

      <!-- Clash Status / Warnings -->
      <div id="clashStatusBox" class="mb-3" style="display:none;"></div>

      <!-- Force Swap Checkbox -->
      <div id="forceWrapper" class="form-check mb-3" style="display:none;">
        <input class="form-check-input" type="checkbox" id="forceSwapCheck">
        <label class="form-check-label small" for="forceSwapCheck" style="color:var(--brand-red,#9C4632); font-weight:600;">
          This swap creates conflicts. Proceed anyway.
        </label>
      </div>

      <!-- Action Buttons -->
      <div class="d-flex gap-2">
        <button id="executeSwapBtn" class="btn btn-grad flex-grow-1 py-2.5" disabled onclick="executeSwap()">
          Swap seats
        </button>
        <button type="button" class="btn btn-outline-secondary px-3 py-2.5" onclick="clearSeatSelection()">
          Clear
        </button>
      </div>

      <!-- Optional quick search -->
      <div class="mt-3 pt-3 border-top">
        <label class="form-label small text-muted" for="rollSearchInput">Search seat by roll number</label>
        <div class="d-flex gap-1.5">
          <input type="text" id="rollSearchInput" class="form-control form-control-sm font-monospace" placeholder="e.g. 2116241801001" style="text-transform:uppercase;">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="findAndSelectByRoll()">Select</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Side: Visual Room Map (col-lg-8) -->
  <div class="col-lg-8">
    <div class="table-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h6 class="fw-bold mb-0">Examination Room Map</h6>
          <span class="text-muted small">Click any seat to pick Seat 1 (Peach), then another to pick Seat 2 (Sage)</span>
        </div>
        <div id="roomMetaLabel" class="text-muted small"></div>
      </div>

      <!-- Room Navigation Tabs (if multiple halls) -->
      <div id="roomTabs" class="swap-room-tabs" style="display:none;"></div>

      <!-- Visual Hall Container -->
      <div id="swapMapPlaceholder" class="text-muted text-center p-5">
        <div class="empty-state-icon mx-auto mb-3">
          <?= svg_icon('swap', '', 28) ?>
        </div>
        <h6 class="fw-bold text-main">Select an exam session</h6>
        <p class="text-muted small mb-0">Choose an exam above to load the interactive seating map.</p>
      </div>

      <div id="swapMapContent" style="display:none;">
        <div class="hall-board-indicator mb-3">Front · Board</div>
        <div id="swapGrid" class="d-flex flex-column gap-2 mb-3"></div>

        <!-- Map Legend -->
        <div class="d-flex gap-3 flex-wrap pt-3 border-top text-muted small align-items-center">
          <span class="fw-semibold text-main">Legend:</span>
          <span class="d-inline-flex align-items-center gap-1.5">
            <span style="width:14px; height:14px; background:#FFBDA3; border:1px solid #E27A55; border-radius:3px; display:inline-block;"></span>
            Seat 1
          </span>
          <span class="d-inline-flex align-items-center gap-1.5">
            <span style="width:14px; height:14px; background:#8B9A6E; border:1px solid #58693F; border-radius:3px; display:inline-block;"></span>
            Seat 2
          </span>
          <span class="d-inline-flex align-items-center gap-1.5">
            <span style="width:14px; height:14px; background:rgba(156,70,50,0.2); border:1px solid #9C4632; border-radius:3px; display:inline-block;"></span>
            Clash
          </span>
          <span class="d-inline-flex align-items-center gap-1.5">
            <span style="width:14px; height:14px; background:#FAF8F5; border:1px solid #D8CFBF; border-radius:3px; display:inline-block;"></span>
            Seated
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let currentRooms = [];
let currentSeats = [];
let activeRoomId = null;
let selectedSeatA = null;
let selectedSeatB = null;
let currentClashes = [];
let conflictingSeatKeys = new Set();

document.addEventListener('DOMContentLoaded', () => {
  const examSel = document.getElementById('swapExam');
  if (examSel.value) {
    handleExamChange();
  }
});

async function handleExamChange() {
  const examId = document.getElementById('swapExam').value;
  clearSeatSelection();

  if (!examId) {
    document.getElementById('swapMapPlaceholder').style.display = 'block';
    document.getElementById('swapMapContent').style.display = 'none';
    document.getElementById('roomTabs').style.display = 'none';
    return;
  }

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch('../api/swap.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ action: 'get_seating', exam_id: +examId })
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Failed to load seating');

    currentRooms = json.rooms || [];
    currentSeats = json.seats || [];

    if (currentRooms.length === 0 || currentSeats.length === 0) {
      document.getElementById('swapMapPlaceholder').style.display = 'block';
      document.getElementById('swapMapPlaceholder').innerHTML = `
        <div class="empty-state-icon mx-auto mb-3"><?= svg_icon('alert-triangle', '', 28) ?></div>
        <h6 class="fw-bold text-main">No seating generated yet</h6>
        <p class="text-muted small mb-0">Generate a seating plan for this exam session first.</p>
      `;
      document.getElementById('swapMapContent').style.display = 'none';
      document.getElementById('roomTabs').style.display = 'none';
      return;
    }

    renderRoomTabs();
    activeRoomId = currentRooms[0].id;
    renderRoomMap();

    document.getElementById('swapMapPlaceholder').style.display = 'none';
    document.getElementById('swapMapContent').style.display = 'block';
  } catch (e) {
    window.showToast(e.message, 'danger');
  }
}

function renderRoomTabs() {
  const tabsContainer = document.getElementById('roomTabs');
  if (currentRooms.length <= 1) {
    tabsContainer.style.display = 'none';
    return;
  }

  tabsContainer.style.display = 'flex';
  tabsContainer.innerHTML = currentRooms.map((r, i) => `
    <button type="button" class="swap-room-tab ${i === 0 ? 'active' : ''}" onclick="selectRoom(${r.id})">
      Hall ${r.room_no} (${r.block})
    </button>
  `).join('');
}

function selectRoom(roomId) {
  activeRoomId = roomId;
  document.querySelectorAll('.swap-room-tab').forEach(tab => {
    tab.classList.toggle('active', tab.textContent.includes(`Hall ${currentRooms.find(r => r.id === roomId)?.room_no}`));
  });
  renderRoomMap();
}

function renderRoomMap() {
  const room = currentRooms.find(r => r.id === activeRoomId) || currentRooms[0];
  if (!room) return;

  document.getElementById('roomMetaLabel').textContent = `Hall ${room.room_no} · ${room.block} (${room.rows_count} rows × ${room.cols_count} cols)`;

  // Filter seats in this room
  const roomSeats = currentSeats.filter(s => s.room_id == room.id);
  const seatGrid = {};
  roomSeats.forEach(s => {
    seatGrid[`${s.row_num}:${s.col_num}`] = s;
  });

  const gridContainer = document.getElementById('swapGrid');
  let html = '';

  for (let r = 1; r <= room.rows_count; r++) {
    html += `<div class="swap-bench-row">
      <span class="swap-bench-label">R${r}</span>`;

    for (let c = 1; c <= room.cols_count; c++) {
      const s = seatGrid[`${r}:${c}`];
      if (s) {
        const isA = selectedSeatA && selectedSeatA.id === s.id;
        const isB = selectedSeatB && selectedSeatB.id === s.id;
        const isClash = conflictingSeatKeys.has(`${room.id}:${r}:${c}`) || (isA && conflictingSeatKeys.has(`clash_a`)) || (isB && conflictingSeatKeys.has(`clash_b`));

        let cls = 'swap-seat-btn';
        if (isA) cls += ' seat-selected-a';
        else if (isB) cls += ' seat-selected-b';
        if (isClash) cls += ' seat-clash';

        const rollShort = s.roll_no ? s.roll_no.slice(-4) : '—';
        html += `<button type="button" class="${cls}" data-seat-id="${s.id}" onclick="handleSeatClick(${s.id})" title="${s.name || ''} (${s.roll_no}) · Paper ${s.exam_code} · Row ${r}, Col ${c}">
          <div class="swap-seat-roll">${s.roll_no}</div>
          <div class="swap-seat-code">${s.exam_code}</div>
        </button>`;
      } else {
        html += `<div class="swap-seat-btn" style="opacity:0.4; cursor:default; border-style:dashed;">
          <span class="text-muted small">—</span>
        </div>`;
      }
    }

    html += `</div>`;
  }

  gridContainer.innerHTML = html;
}

function handleSeatClick(seatId) {
  const seat = currentSeats.find(s => s.id === seatId);
  if (!seat) return;

  if (!selectedSeatA) {
    selectedSeatA = seat;
  } else if (selectedSeatA.id === seat.id) {
    selectedSeatA = null;
  } else if (!selectedSeatB) {
    selectedSeatB = seat;
    triggerClashCheck();
  } else if (selectedSeatB.id === seat.id) {
    selectedSeatB = null;
    clearClashWarnings();
  } else {
    // Replace B with new selection
    selectedSeatB = seat;
    triggerClashCheck();
  }

  updateSelectionCards();
  renderRoomMap();
}

function updateSelectionCards() {
  const cardA = document.getElementById('seatCardA');
  const cardB = document.getElementById('seatCardB');
  const btn = document.getElementById('executeSwapBtn');

  if (selectedSeatA) {
    const room = currentRooms.find(r => r.id == selectedSeatA.room_id);
    document.getElementById('seatARoll').textContent = selectedSeatA.roll_no;
    document.getElementById('seatAName').textContent = selectedSeatA.name || 'Candidate 1';
    document.getElementById('seatAMeta').textContent = `Hall ${room ? room.room_no : selectedSeatA.room_id} · Row ${selectedSeatA.row_num}, Col ${selectedSeatA.col_num} · ${selectedSeatA.exam_code}`;
    cardA.style.borderColor = '#E27A55';
    cardA.style.background = 'rgba(255, 189, 163, 0.15)';
  } else {
    document.getElementById('seatARoll').textContent = '—';
    document.getElementById('seatAName').textContent = 'Click a seat on the map';
    document.getElementById('seatAMeta').textContent = '—';
    cardA.style.borderColor = 'var(--border, #D8CFBF)';
    cardA.style.background = '#FAF8F5';
  }

  if (selectedSeatB) {
    const room = currentRooms.find(r => r.id == selectedSeatB.room_id);
    document.getElementById('seatBRoll').textContent = selectedSeatB.roll_no;
    document.getElementById('seatBName').textContent = selectedSeatB.name || 'Candidate 2';
    document.getElementById('seatBMeta').textContent = `Hall ${room ? room.room_no : selectedSeatB.room_id} · Row ${selectedSeatB.row_num}, Col ${selectedSeatB.col_num} · ${selectedSeatB.exam_code}`;
    cardB.style.borderColor = '#58693F';
    cardB.style.background = 'rgba(139, 154, 110, 0.15)';
  } else {
    document.getElementById('seatBRoll').textContent = '—';
    document.getElementById('seatBName').textContent = 'Click second seat to swap';
    document.getElementById('seatBMeta').textContent = '—';
    cardB.style.borderColor = 'var(--border, #D8CFBF)';
    cardB.style.background = '#FAF8F5';
  }

  btn.disabled = !(selectedSeatA && selectedSeatB);
}

async function triggerClashCheck() {
  if (!selectedSeatA || !selectedSeatB) return;

  const examId = document.getElementById('swapExam').value;
  const statusBox = document.getElementById('clashStatusBox');
  const forceWrapper = document.getElementById('forceWrapper');
  conflictingSeatKeys.clear();

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch('../api/swap.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({
        exam_id: +examId,
        roll_a: selectedSeatA.roll_no,
        roll_b: selectedSeatB.roll_no,
        check_only: true
      })
    });
    const json = await res.json();
    currentClashes = json.clashes || [];

    if (json.has_clash && currentClashes.length > 0) {
      // Mark clashing seat keys
      if (json.conflicting_seats) {
        json.conflicting_seats.forEach(cs => {
          conflictingSeatKeys.add(`${cs.room_id}:${cs.row}:${cs.col}`);
        });
      }
      statusBox.innerHTML = `
        <div class="p-2.5 rounded-3 border small" style="background:rgba(156,70,50,0.12); border-color:#9C4632; color:#9C4632;">
          <b>${currentClashes.length} clash(es) detected!</b>
          <div class="mt-1" style="font-size:0.75rem;">${currentClashes[0]}</div>
        </div>
      `;
      statusBox.style.display = 'block';
      forceWrapper.style.display = 'block';
    } else {
      statusBox.innerHTML = `
        <div class="p-2.5 rounded-3 border small" style="background:rgba(139,154,110,0.18); border-color:#8B9A6E; color:#3F4B2D;">
          <b>0 clashes.</b> Safe to swap.
        </div>
      `;
      statusBox.style.display = 'block';
      forceWrapper.style.display = 'none';
    }
  } catch (e) {
    statusBox.style.display = 'none';
    forceWrapper.style.display = 'none';
  }

  renderRoomMap();
}

function clearClashWarnings() {
  document.getElementById('clashStatusBox').style.display = 'none';
  document.getElementById('forceWrapper').style.display = 'none';
  conflictingSeatKeys.clear();
  currentClashes = [];
}

function clearSeatSelection() {
  selectedSeatA = null;
  selectedSeatB = null;
  clearClashWarnings();
  updateSelectionCards();
  renderRoomMap();
}

async function executeSwap() {
  if (!selectedSeatA || !selectedSeatB) return;

  const examId = document.getElementById('swapExam').value;
  const isForce = document.getElementById('forceSwapCheck')?.checked || false;

  if (currentClashes.length > 0 && !isForce) {
    window.showToast('Please check the conflict confirmation box before swapping.', 'danger');
    return;
  }

  const btn = document.getElementById('executeSwapBtn');
  btn.disabled = true;
  btn.textContent = 'Swapping…';

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch('../api/swap.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({
        exam_id: +examId,
        roll_a: selectedSeatA.roll_no,
        roll_b: selectedSeatB.roll_no,
        force: isForce
      })
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || json.message || 'Swap failed');

    // Exchange seat coordinates in memory
    const tempRoom = selectedSeatA.room_id;
    const tempRow = selectedSeatA.row_num;
    const tempCol = selectedSeatA.col_num;
    const tempBench = selectedSeatA.bench_no;
    const tempIdx = selectedSeatA.seat_index;

    selectedSeatA.room_id = selectedSeatB.room_id;
    selectedSeatA.row_num = selectedSeatB.row_num;
    selectedSeatA.col_num = selectedSeatB.col_num;
    selectedSeatA.bench_no = selectedSeatB.bench_no;
    selectedSeatA.seat_index = selectedSeatB.seat_index;

    selectedSeatB.room_id = tempRoom;
    selectedSeatB.row_num = tempRow;
    selectedSeatB.col_num = tempCol;
    selectedSeatB.bench_no = tempBench;
    selectedSeatB.seat_index = tempIdx;

    window.showToast('Seats swapped', 'success');
    clearSeatSelection();
  } catch (e) {
    window.showToast(e.message, 'danger');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Swap seats';
  }
}

function findAndSelectByRoll() {
  const roll = document.getElementById('rollSearchInput').value.trim().toUpperCase();
  if (!roll) return;

  const seat = currentSeats.find(s => s.roll_no && s.roll_no.toUpperCase() === roll);
  if (!seat) {
    window.showToast(`Roll number "${roll}" not found in this exam session.`, 'danger');
    return;
  }

  if (activeRoomId !== seat.room_id) {
    selectRoom(seat.room_id);
  }

  handleSeatClick(seat.id);
  window.showToast(`Selected seat for ${roll}`, 'info');
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>
