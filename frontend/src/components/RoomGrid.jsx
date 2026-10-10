import { useEffect, useMemo, useRef } from 'react';
import { LogIn, Square } from 'lucide-react';

/**
 * RoomGrid - Ink on Paper Seating Map
 * - Front of hall clearly marked with Board and Entrance Door
 * - Bench numbers explicitly numbered
 * - ONLY the student's own seat is highlighted in Peach (#FFBDA3)
 * - Everyone else's seat is grey, anonymous, and pattern-coded
 * - Hand-drawn SVG brush circle animates drawing around the student's seat (< 1s, reduced motion respected)
 */
export default function RoomGrid({ room, seats = [], highlightRoll }) {
  const yourSeatRef = useRef(null);

  const benchesCount = +room.benches_count || +room.rows_count || 1;
  const perBench = +room.students_per_bench || +room.cols_count || 2;

  // Group columns into 2-seat bench pairs
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

  const leftBenchCount = Math.max(1, Math.ceil(benchPairs.length / 2));
  const leftBenches = useMemo(() => benchPairs.slice(0, leftBenchCount), [benchPairs, leftBenchCount]);
  const rightBenches = useMemo(() => benchPairs.slice(leftBenchCount), [benchPairs, leftBenchCount]);

  // Fast map lookup
  const seatMap = useMemo(() => {
    const map = new Map();
    for (const s of seats) {
      map.set(`${+s.row_num}:${+s.col_num}`, s);
    }
    return map;
  }, [seats]);

  // Auto-scroll to student's seat
  useEffect(() => {
    if (yourSeatRef.current) {
      const timer = setTimeout(() => {
        yourSeatRef.current?.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
          inline: 'center',
        });
      }, 300);
      return () => clearTimeout(timer);
    }
  }, [highlightRoll]);

  const renderSeat = (r, c, sideLabel, benchNumber) => {
    const s = seatMap.get(`${r}:${c}`);
    const isMe = highlightRoll && s && s.roll_no.toUpperCase() === highlightRoll.toUpperCase();

    // 1. Empty desk: off-white with dashed border
    if (!s) {
      return (
        <div
          key={c}
          className="seat-box seat-empty"
          title={`Row ${r}, Col ${c} (${sideLabel}) · Empty desk`}
          aria-label={`Row ${r}, Col ${c}, Empty desk`}
        >
          <span className="seat-sub-num">{c}</span>
        </div>
      );
    }

    // 2. Student's own allocated seat: Peach with animated brush circle
    if (isMe) {
      return (
        <div
          key={c}
          ref={yourSeatRef}
          className="seat-box seat-mine"
          title={`Your allocated seat: Bench ${benchNumber}, ${sideLabel} (Row ${r}, Col ${c})`}
          aria-label={`Your seat: Bench ${benchNumber}, ${sideLabel}, Row ${r}, Column ${c}`}
        >
          {/* Animated Hand-drawn Brush Circle Reveal */}
          <svg className="seat-reveal-circle" viewBox="0 0 100 100" aria-hidden="true">
            <path
              d="M 50 7 C 76 6, 95 24, 94 50 C 93 76, 74 94, 49 93 C 23 92, 6 72, 7 48 C 8 22, 29 6, 54 7 C 69 8, 84 14, 92 27"
              fill="none"
              stroke="#2B2E27"
              strokeWidth="5"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
          <span className="seat-star" aria-hidden="true">★</span>
          <span className="seat-label-text">YOU</span>
        </div>
      );
    }

    // 3. Other students' seats: Grey, anonymous, pattern-coded (diagonal hash)
    return (
      <div
        key={c}
        className="seat-box seat-other-occupied"
        title={`Row ${r}, Col ${c} (${sideLabel}) · Occupied desk`}
        aria-label={`Row ${r}, Col ${c}, Occupied desk`}
      >
        <span className="seat-pattern-icon" aria-hidden="true">
          <Square size={10} strokeWidth={2.5} />
        </span>
      </div>
    );
  };

  const renderBench = (r, b) => {
    const benchNumber = (r - 1) * benchPairs.length + b.benchCol;
    return (
      <div key={b.benchCol} className="bench-cell">
        <div className="bench-seats-pair">
          {renderSeat(r, b.leftCol, 'Left', benchNumber)}
          {b.rightCol ? (
            renderSeat(r, b.rightCol, 'Right', benchNumber)
          ) : (
            <div className="seat-box seat-placeholder" aria-hidden="true" />
          )}
        </div>
        <div className="bench-indicator">Bench {benchNumber}</div>
      </div>
    );
  };

  return (
    <div className="room-map-wrapper">
      {/* Front of Hall: Blackboard and Entrance Door */}
      <div className="hall-front-stage">
        <div className="hall-door-marker" title="Room Entrance Door">
          <LogIn size={15} />
          <span>Door</span>
        </div>
        <div className="hall-board-marker" title="Classroom Blackboard / Whiteboard">
          <div className="hall-board-chalk" />
          <span>Board &amp; Invigilator Desk</span>
        </div>
        <div className="hall-door-spacer" />
      </div>

      {/* Seating Layout Grid */}
      <div className="map-scroll-area">
        <div className="hall-grid-container" role="grid" aria-label={`Seating layout for hall ${room.room_no}`}>
          {Array.from({ length: benchesCount }, (_, i) => i + 1).map((r) => (
            <div key={r} className="hall-grid-row">
              <span className="row-pill">Row {r}</span>

              {/* Left Wing Benches */}
              <div className="wing-block">
                {leftBenches.map((b) => renderBench(r, b))}
              </div>

              {/* Center Walking Aisle */}
              {rightBenches.length > 0 && (
                <div className="aisle-spacer" aria-hidden="true">
                  <span>Aisle</span>
                </div>
              )}

              {/* Right Wing Benches */}
              {rightBenches.length > 0 && (
                <div className="wing-block">
                  {rightBenches.map((b) => renderBench(r, b))}
                </div>
              )}

              <span className="row-pill">Row {r}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Clean Calm Legend */}
      <div className="room-map-legend">
        <div className="legend-entry">
          <span className="legend-chip legend-chip-mine">
            <span className="legend-mini-circle" />
            ★
          </span>
          <span>Your seat (Peach)</span>
        </div>
        <div className="legend-entry">
          <span className="legend-chip legend-chip-other" />
          <span>Occupied desk (Anonymous)</span>
        </div>
        <div className="legend-entry">
          <span className="legend-chip legend-chip-empty" />
          <span>Empty desk</span>
        </div>
      </div>
    </div>
  );
}