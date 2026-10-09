import { useMemo, useState } from 'react';
import { deptColor } from '../api/client.js';

export default function RoomGrid({ room, seats, highlightRoll }) {
  const [selected, setSelected] = useState(null);

  const benchesCount = +room.benches_count || +room.rows_count || 1;
  const perBench = +room.students_per_bench || +room.cols_count || 2;

  const grid = useMemo(() => {
    const g = [];
    for (let r = 1; r <= benchesCount; r++) {
      const row = [];
      for (let c = 1; c <= perBench; c++) {
        row.push(seats.find((s) => +s.row_num === r && +s.col_num === c) || null);
      }
      g.push(row);
    }
    return g;
  }, [benchesCount, perBench, seats]);

  const branches = useMemo(
    () => [...new Set(seats.map((s) => s.branch).filter(Boolean))].sort(),
    [seats]
  );

  return (
    <div className="room-map-card rise rise-2">
      <div className="room-map-head">
        <div>
          <h3 style={{ margin: 0 }}>🗺️ Room {room.room_no} · {room.block}</h3>
          <div style={{ color: 'var(--muted)', fontSize: '.85rem', marginTop: 4 }}>
            {benchesCount} Rows × {perBench} Columns ({benchesCount * perBench} Desks)
          </div>
        </div>
        <span className="badge-dept" style={{ background: '#4f46e5', alignSelf: 'flex-start' }}>
          {seats.length} / {benchesCount * perBench} Seats Filled
        </span>
      </div>

      <div className="front-banner">▲ Front · Invigilator Desk ▲</div>

      <div className="seat-grid" role="grid" aria-label={`Seating map of room ${room.room_no}`}>
        {grid.map((row, ri) => (
          <div key={ri} className="seat-row" style={{ gridTemplateColumns: `repeat(${perBench}, 1fr)` }}>
            {row.map((s, ci) => {
              if (!s) {
                return <div key={ci} className="seat empty" aria-hidden="true" title={`Bench ${ri + 1}, Seat ${ci + 1} (Empty)`} />;
              }
              const isMe = highlightRoll && s.roll_no.toUpperCase() === highlightRoll.toUpperCase();
              return (
                <button
                  key={ci}
                  className={`seat ${isMe ? 'you' : ''}`}
                  style={{ '--dept': deptColor(s.branch) }}
                  title={`${s.roll_no}${isMe && s.name ? ' · ' + s.name : ''} · Code: ${s.exam_code || s.branch} (${s.branch}) · Bench ${ri + 1}, Seat ${ci + 1}`}
                  aria-label={`Bench ${ri + 1} Seat ${ci + 1}: ${s.roll_no}, Code: ${s.exam_code || s.branch}, ${s.branch}`}
                  onClick={() => setSelected(s)}
                >
                  {s.roll_no.slice(-4)}
                </button>
              );
            })}
          </div>
        ))}
      </div>

      {selected && (() => {
        const isSelectedMe = highlightRoll && selected.roll_no.toUpperCase() === highlightRoll.toUpperCase();
        return (
          <div className="seat-peek rise" style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
            {selected.exam_code && (
              <span className="badge-dept" style={{ background: '#0284c7' }}>
                Paper: {selected.exam_code}
              </span>
            )}
            <span className="badge-dept" style={{ background: deptColor(selected.branch) }}>{selected.branch}</span>
            <b>{selected.roll_no}</b>
            <span style={{ color: 'var(--muted)' }}>
              {isSelectedMe && selected.name ? `${selected.name} · ` : ''}<b>Bench {selected.bench_no || selected.row_num}</b> (Seat {selected.seat_index || selected.col_num})
            </span>
          </div>
        );
      })()}

      <div className="legend">
        {branches.map((b) => (
          <span key={b} className="legend-item">
            <span className="legend-dot" style={{ background: deptColor(b) }} /> {b}
          </span>
        ))}
        <span className="legend-item">
          <span className="legend-dot" style={{ background: '#eef1f6', border: '1px solid #cbd5e1' }} /> Empty Seat
        </span>
      </div>
    </div>
  );
}