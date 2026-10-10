import { useEffect, useMemo, useRef, useState } from 'react';
import { ArrowLeftRight, Users, Info } from 'lucide-react';
import { deptColor, deptTextColor, shortDept } from '../api/client.js';

export default function RoomGrid({ room, seats, highlightRoll }) {
  const [activeSeat, setActiveSeat] = useState(null);
  const yourSeatRef = useRef(null);

  const benchesCount = +room.benches_count || +room.rows_count || 1;
  const perBench = +room.students_per_bench || +room.cols_count || 2;

  // Group columns into 2-seat bench units (Items 11, 14, 15)
  const benchPairs = useMemo(() => {
    const pairs = [];
    for (let c = 1; c <= perBench; c += 2) {
      pairs.push({
        benchCol: Math.ceil(c / 2),
        leftCol: c,
        rightCol: c + 1 <= perBench ? c + 1 : null,
      });
    }
    return pairs;
  }, [perBench]);

  // Split benches into Left Block and Right Block around walking aisle (Item 12)
  const leftBenchCount = Math.max(1, Math.ceil(benchPairs.length / 2));
  const leftBenches = useMemo(
    () => benchPairs.slice(0, leftBenchCount),
    [benchPairs, leftBenchCount]
  );
  const rightBenches = useMemo(
    () => benchPairs.slice(leftBenchCount),
    [benchPairs, leftBenchCount]
  );

  // Group rows into sections of 4 rows (e.g. Rows 1–4, 5–8) (Item 13)
  const SECTION_SIZE = 4;
  const sections = useMemo(() => {
    const secs = [];
    for (let r = 1; r <= benchesCount; r += SECTION_SIZE) {
      const startRow = r;
      const endRow = Math.min(r + SECTION_SIZE - 1, benchesCount);
      const rows = [];
      for (let rowIdx = startRow; rowIdx <= endRow; rowIdx++) {
        rows.push(rowIdx);
      }
      secs.push({
        title: `ROWS ${startRow} – ${endRow}`,
        startRow,
        endRow,
        rows,
      });
    }
    return secs;
  }, [benchesCount]);

  // Fast O(1) lookup of seats by row:col
  const seatMap = useMemo(() => {
    const map = new Map();
    for (const s of seats) {
      map.set(`${+s.row_num}:${+s.col_num}`, s);
    }
    return map;
  }, [seats]);

  const branches = useMemo(
    () => [...new Set(seats.map((s) => s.branch).filter(Boolean))].sort(),
    [seats]
  );

  // Auto-scroll to "YOUR SEAT" when page opens (Item 16)
  useEffect(() => {
    if (yourSeatRef.current) {
      const timer = setTimeout(() => {
        yourSeatRef.current?.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
          inline: 'center',
        });
      }, 350);
      return () => clearTimeout(timer);
    }
  }, [highlightRoll]);

  // Set default active seat to user's seat if found
  useEffect(() => {
    if (highlightRoll) {
      const me = seats.find(
        (s) => s.roll_no.toUpperCase() === highlightRoll.toUpperCase()
      );
      if (me) setActiveSeat(me);
    }
  }, [highlightRoll, seats]);

  // Render individual seat square inside bench
  const renderSeat = (r, c, side) => {
    const s = seatMap.get(`${r}:${c}`);

    // Empty desk: white square with light grey dashed border and grey number (Item 4, 6)
    if (!s) {
      return (
        <div
          key={c}
          className="cinema-seat empty"
          title={`Row ${r}, Col ${c} (${side} · Empty)`}
          aria-label={`Row ${r}, Col ${c} (Empty desk)`}
        >
          <span className="seat-num-text">{c}</span>
        </div>
      );
    }

    const isMe =
      highlightRoll &&
      s.roll_no.toUpperCase() === highlightRoll.toUpperCase();

    // Show last 4 digits (Item 6)
    const rollDigits = s.roll_no.length >= 4 ? s.roll_no.slice(-4) : s.roll_no;
    const dColor = deptColor(s.branch);

    // Occupied seat: white square with colored border and colored number; solid orange ONLY for Your Seat (Items 1, 2, 3)
    return (
      <button
        key={c}
        ref={isMe ? yourSeatRef : null}
        type="button"
        className={`cinema-seat occupied ${isMe ? 'you' : ''}`}
        style={{
          '--dept': dColor,
          '--dept-text': deptTextColor(s.branch),
        }}
        title={`${s.roll_no} (${s.branch}) · Row ${r}, Col ${c} (${side})`}
        aria-label={`${isMe ? 'Your Seat: ' : ''}${s.roll_no}, Department: ${s.branch}, Row ${r}, Column ${c}`}
        onClick={() => setActiveSeat(s)}
        onMouseEnter={() => setActiveSeat(s)}
      >
        <span className="seat-num-text">{rollDigits}</span>
      </button>
    );
  };

  // Render bench unit containing 2 seats (Items 11, 14, 15)
  const renderBench = (r, b) => {
    const leftSeat = renderSeat(r, b.leftCol, 'Seat 1 Left');
    const rightSeat = b.rightCol ? (
      renderSeat(r, b.rightCol, 'Seat 2 Right')
    ) : (
      <div key={`empty-ph-${b.benchCol}`} className="cinema-seat placeholder" aria-hidden="true" />
    );

    return (
      <div key={b.benchCol} className="bench-unit" title={`Row ${r}, Bench ${b.benchCol}`}>
        <div className="bench-unit-seats">
          {leftSeat}
          {rightSeat}
        </div>
        <span className="bench-unit-label">B{b.benchCol}</span>
      </div>
    );
  };

  return (
    <div className="room-map-card cinema-card card-enter">
      {/* Centered hall title + filled chip above the map */}
      <div className="hall-cinema-header">
        <h3 className="hall-cinema-title">
          Hall {room.room_no} · {room.block}
        </h3>
        <div className="hall-cinema-sub">
          <span className="pill-neutral">
            <Users size={13} /> {seats.length} / {benchesCount * perBench} seats filled
          </span>
        </div>
      </div>

      {/* Mobile scroll hint (Item 17) */}
      <div className="mobile-scroll-hint">
        <ArrowLeftRight size={14} /> Swipe horizontally to view all benches in hall
      </div>

      {/* Centered Scrollable Theater Grid (Items 11, 12, 17) */}
      <div className="grid-scroll-wrap">
        <div className="cinema-grid-center" role="grid" aria-label={`Seating layout for hall ${room.room_no}`}>
          {/* Column Numbers above each block aligned to seats (Item 14) */}
          <div className="cinema-col-header-row" aria-hidden="true">
            <span className="row-num-spacer" />
            <div className="seat-block">
              {leftBenches.map((b) => (
                <div key={b.benchCol} className="bench-header-unit">
                  <span className="col-num-label">{b.leftCol}</span>
                  {b.rightCol ? (
                    <span className="col-num-label">{b.rightCol}</span>
                  ) : (
                    <span className="col-num-label placeholder" />
                  )}
                </div>
              ))}
            </div>
            {rightBenches.length > 0 && <div className="walking-aisle" />}
            {rightBenches.length > 0 && (
              <div className="seat-block">
                {rightBenches.map((b) => (
                  <div key={b.benchCol} className="bench-header-unit">
                    <span className="col-num-label">{b.leftCol}</span>
                    {b.rightCol ? (
                      <span className="col-num-label">{b.rightCol}</span>
                    ) : (
                      <span className="col-num-label placeholder" />
                    )}
                  </div>
                ))}
              </div>
            )}
            <span className="row-num-spacer" />
          </div>

          {/* Sections of Rows with Divider Lines (Items 12, 13) */}
          {sections.map((section, secIdx) => (
            <div key={secIdx} className="cinema-section-group">
              {/* Centered section label with thin divider line (Item 13) */}
              <div className="section-divider-title" aria-label={`Section ${section.title}`}>
                <span>{section.title}</span>
              </div>

              {/* Rows inside section */}
              <div className="cinema-section-rows">
                {section.rows.map((r) => (
                  <div key={r} className="cinema-row-container">
                    {/* Row number at left (Item 13) */}
                    <span className="row-num-label">{r}</span>

                    {/* Left Block of Bench Units (Item 11) */}
                    <div className="seat-block">
                      {leftBenches.map((b) => renderBench(r, b))}
                    </div>

                    {/* Walking Aisle Gap (48–56px) (Item 12) */}
                    {rightBenches.length > 0 && (
                      <div className="walking-aisle" aria-hidden="true" title="Walking aisle" />
                    )}

                    {/* Right Block of Bench Units (Item 11) */}
                    {rightBenches.length > 0 && (
                      <div className="seat-block">
                        {rightBenches.map((b) => renderBench(r, b))}
                      </div>
                    )}

                    {/* Row number at right for symmetry (Item 13) */}
                    <span className="row-num-label">{r}</span>
                  </div>
                ))}
              </div>
            </div>
          ))}

          {/* Flat Matte Bar at bottom with caption (Items 7, 8, 10) */}
          <div className="cinema-screen-wrap" aria-label="Front of examination hall">
            <div className="cinema-screen-bar" />
            <div className="cinema-screen-caption">FRONT · INVIGILATOR DESK</div>
          </div>

          {/* Centered Legend below screen with outlined seat chips (Item 5) */}
          <div className="cinema-legend-bottom" aria-label="Room seating legend">
            <div className="legend-items-row">
              {branches.map((b) => (
                <span key={b} className="legend-item">
                  <span
                    className="legend-seat-outline"
                    style={{
                      '--dept': deptColor(b),
                      borderColor: deptColor(b),
                      color: deptTextColor(b),
                    }}
                  >
                    {shortDept(b)}
                  </span>
                  <span>{b}</span>
                </span>
              ))}
              <span className="legend-item">
                <span className="legend-seat-outline empty" />
                <span>Empty Desk</span>
              </span>
              <span className="legend-item">
                <span className="legend-seat-outline your-seat" />
                <b>Your Seat</b>
              </span>
            </div>
          </div>
        </div>
      </div>

      {/* Interactive Tooltip / Peek Card on Hover & Tap (Item 5) */}
      {activeSeat && (
        <div className="seat-peek card-enter" role="status" aria-live="polite">
          <Info size={16} color="var(--text-subtle)" />
          {activeSeat.exam_code && (
            <span
              className="pill-neutral"
              style={{ background: '#ffffff', fontWeight: 700 }}
            >
              Paper: {activeSeat.exam_code}
            </span>
          )}
          <span
            className="dept-pill-tag"
            style={{
              '--dept': deptColor(activeSeat.branch),
              borderColor: deptColor(activeSeat.branch),
              color: deptTextColor(activeSeat.branch),
            }}
          >
            {activeSeat.branch}
          </span>
          <b style={{ fontFamily: 'JetBrains Mono, monospace' }}>
            {activeSeat.roll_no}
          </b>
          {activeSeat.name && (
            <span style={{ color: 'var(--text)' }}>({activeSeat.name})</span>
          )}
          <span style={{ color: 'var(--text-subtle)', marginLeft: 'auto' }}>
            Desk <b>Row {activeSeat.row_num}</b>, <b>Col {activeSeat.col_num}</b>
            {activeSeat.seat_index ? ` · Seat ${activeSeat.seat_index}` : ''}
          </span>
          {highlightRoll &&
            activeSeat.roll_no.toUpperCase() === highlightRoll.toUpperCase() && (
              <span className="your-seat-badge-pill">
                ★ THIS IS YOUR SEAT
              </span>
            )}
        </div>
      )}
    </div>
  );
}