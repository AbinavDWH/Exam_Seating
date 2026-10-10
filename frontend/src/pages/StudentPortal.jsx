import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Calendar, Clock, Download, Image as ImageIcon, Printer, AlertCircle } from 'lucide-react';
import BrandLogo from '../components/BrandLogo.jsx';
import RoomGrid from '../components/RoomGrid.jsx';
import { api } from '../api/client.js';
import { useToast } from '../components/Toast.jsx';

const STORAGE_KEY = 'deskmap_student_result';

const fmtDate = (d) => {
  if (!d) return '';
  return new Date(d + 'T00:00:00').toLocaleDateString('en-IN', {
    weekday: 'long',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};

const fmtTime = (t) => {
  if (!t) return '';
  return new Date(`2000-01-01T${t}`).toLocaleTimeString('en-IN', {
    hour: 'numeric',
    minute: '2-digit',
  });
};

// ICS Calendar download helper
function downloadCalendarEvent(seat) {
  const dateStr = seat.exam_date;
  const timeStr = seat.start_time || '09:30:00';
  const startDt = new Date(`${dateStr}T${timeStr}`);
  const endDt = new Date(startDt.getTime() + 3 * 60 * 60 * 1000);

  const formatICSDate = (d) => d.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';

  const ics = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//DeskMap//Exam Seating//EN',
    'BEGIN:VEVENT',
    `UID:deskmap-${seat.exam_id}-${seat.roll_no}@deskmap`,
    `DTSTAMP:${formatICSDate(new Date())}`,
    `DTSTART:${formatICSDate(startDt)}`,
    `DTEND:${formatICSDate(endDt)}`,
    `SUMMARY:${seat.exam_name} - Hall ${seat.room_no}`,
    `LOCATION:Hall ${seat.room_no}, ${seat.block}`,
    `DESCRIPTION:Seat: ${seat.bench_no || seat.row_num} (${seat.col_num % 2 === 1 ? 'Left' : 'Right'})\\nReporting Time: ${seat.reporting_time || '30 mins before start'}\\nPaper: ${seat.exam_code || ''}`,
    'STATUS:CONFIRMED',
    'END:VEVENT',
    'END:VCALENDAR',
  ].join('\r\n');

  const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `DeskMap-Exam-${seat.roll_no}.ics`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

// Canvas-based seat slip image export
function downloadSeatImage(seat) {
  const canvas = document.createElement('canvas');
  canvas.width = 1200;
  canvas.height = 780;
  const ctx = canvas.getContext('2d');

  // Background Linen #EAE2D6
  ctx.fillStyle = '#EAE2D6';
  ctx.fillRect(0, 0, 1200, 780);

  // White Card with 1px border #D8CFBF
  ctx.fillStyle = '#FFFFFF';
  ctx.strokeStyle = '#D8CFBF';
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.roundRect(40, 40, 1120, 700, 16);
  ctx.fill();
  ctx.stroke();

  // Header band
  ctx.fillStyle = '#2B2E27';
  ctx.font = 'bold 26px "Plus Jakarta Sans", sans-serif';
  ctx.fillText('DeskMap · Examination Seating Pass', 80, 105);

  ctx.fillStyle = '#5A5E52';
  ctx.font = '16px "Plus Jakarta Sans", sans-serif';
  ctx.fillText('Official University Examination Seating Record', 80, 135);

  // Divider
  ctx.strokeStyle = '#EAE2D6';
  ctx.beginPath();
  ctx.moveTo(80, 160);
  ctx.lineTo(1120, 160);
  ctx.stroke();

  // 1. Hall & Block (Biggest type)
  ctx.fillStyle = '#2B2E27';
  ctx.font = 'bold 52px "Plus Jakarta Sans", sans-serif';
  ctx.fillText(`Hall ${seat.room_no}, ${seat.block}`, 80, 235);

  // 2. Seat Number (Sturdy serif font)
  const seatSide = seat.col_num % 2 === 1 ? 'Left' : 'Right';
  const seatLabel = `Seat #${seat.bench_no || seat.row_num} (${seatSide})`;
  ctx.fillStyle = '#2B2E27';
  ctx.font = 'bold 38px "DM Serif Display", serif';
  ctx.fillText(seatLabel, 80, 295);

  // 3. Exam Details
  ctx.fillStyle = '#2B2E27';
  ctx.font = 'bold 28px "Plus Jakarta Sans", sans-serif';
  ctx.fillText(seat.exam_name, 80, 365);

  ctx.fillStyle = '#5A5E52';
  ctx.font = '20px "Plus Jakarta Sans", sans-serif';
  ctx.fillText(`Date: ${seat.exam_date}   |   Start: ${seat.start_time}   |   Report by: ${seat.reporting_time || '30 mins before'}`, 80, 410);
  ctx.fillText(`Candidate: ${seat.name} (${seat.roll_no})   |   Dept: ${seat.branch}   |   Paper: ${seat.exam_code}`, 80, 455);
  ctx.fillText(`Coordinates: Row ${seat.row_num}, Column ${seat.col_num}`, 80, 495);

  // Divider
  ctx.fillStyle = '#8B9A6E';
  ctx.fillRect(80, 540, 1040, 3);

  ctx.fillStyle = '#5A5E52';
  ctx.font = '16px "Plus Jakarta Sans", sans-serif';
  ctx.fillText('Please arrive at the examination hall at least 15 minutes before reporting time with your college ID card.', 80, 580);
  ctx.fillText('Office of the Controller of Examinations · Autonomous University System', 80, 620);

  canvas.toBlob((blob) => {
    if (!blob) return;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `DeskMap-Seat-${seat.roll_no}-Hall-${seat.room_no}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }, 'image/png');
}

export default function StudentPortal() {
  const [params] = useSearchParams();
  const toast = useToast();

  const [roll, setRoll] = useState(params.get('roll') || '');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);
  const [room, setRoom] = useState(null);
  const [activeExamId, setActiveExamId] = useState(null);

  // Quick sample test rolls
  const sampleRolls = [
    { roll: '2116241801001', label: 'AI&DS' },
    { roll: '2116251001001', label: 'IT' },
    { roll: '2116250701001', label: 'CSE' },
    { roll: '2116241101001', label: 'MECH' },
  ];

  // Search logic
  const handleFind = useCallback(
    async (targetRoll = null, targetExamId = null) => {
      const qRoll = String(targetRoll ?? roll).trim().toUpperCase();

      if (!qRoll) {
        toast('Enter your roll number to find your seat');
        return;
      }

      setLoading(true);
      setError('');

      try {
        const res = await api.findSeat(qRoll, targetExamId);
        if (res.seat) {
          setResult(res);
          setActiveExamId(res.seat.exam_id);

          // Cache last result so students retain it on phone reloads
          try {
            localStorage.setItem(
              STORAGE_KEY,
              JSON.stringify({
                roll: qRoll,
                res,
              })
            );
          } catch {}

          // Load room map
          if (res.seat.room_id) {
            try {
              const rRes = await api.getRoom(res.seat.room_id, res.seat.exam_id, qRoll);
              if (rRes.success && rRes.data) {
                setRoom(rRes.data);
              }
            } catch {}
          }
        } else {
          setError(res.error || 'We couldn’t find that roll number. Check the number and try again.');
        }
      } catch (err) {
        setError(err.message || 'We couldn’t find that roll number. Check the number and try again.');
      } finally {
        setLoading(false);
      }
    },
    [roll, toast]
  );

  // Restore last result on load or URL search
  useEffect(() => {
    const qRoll = params.get('roll');
    if (qRoll) {
      handleFind(qRoll);
      return;
    }

    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed?.res?.seat) {
          setRoll(parsed.roll);
          setResult(parsed.res);
          setActiveExamId(parsed.res.seat.exam_id);

          // Fetch fresh room data in background
          api
            .getRoom(parsed.res.seat.room_id, parsed.res.seat.exam_id, parsed.roll)
            .then((r) => {
              if (r?.data) setRoom(r.data);
            })
            .catch(() => {});
        }
      }
    } catch {}
  }, []);

  const handleSampleClick = (sample) => {
    setRoll(sample.roll);
    handleFind(sample.roll);
  };

  const currentSeat = result?.seat;
  const allExams = result?.all_exams || [];
  const sideLabel = currentSeat ? (currentSeat.col_num % 2 === 1 ? 'Left' : 'Right') : '';

  return (
    <main className="student-portal-wrapper">
      <div className="container student-portal-container">
        {/* Header & Logo */}
        <div className="student-hero no-print">
          <div className="student-hero-logo">
            <BrandLogo variant="full" size={72} />
          </div>
          <p className="student-hero-sub">
            Enter your university roll number to find your exam hall and seat.
          </p>
        </div>

        {/* The One Simple Form */}
        <section className="student-search-card no-print" aria-label="Find seat form">
          <form
            onSubmit={(e) => {
              e.preventDefault();
              handleFind();
            }}
            className="student-form"
          >
            <div className="form-fields-grid">
              <div className="form-field">
                <label htmlFor="studentRollInput" className="field-label">
                  Roll number
                </label>
                <input
                  id="studentRollInput"
                  type="text"
                  className="field-input field-input-mono"
                  placeholder="e.g. 2116241801001"
                  value={roll}
                  onChange={(e) => setRoll(e.target.value.toUpperCase())}
                  required
                  autoComplete="off"
                />
              </div>

              <div className="form-submit-cell">
                <button
                  type="submit"
                  className="btn-find-seat"
                  disabled={loading}
                >
                  {loading ? 'Finding seat…' : 'Find my seat'}
                </button>
              </div>
            </div>

            {/* Quick Demo Roll Chips */}
            <div className="quick-chips-row">
              <span className="quick-chips-label">Demo rolls:</span>
              <div className="chips-list">
                {sampleRolls.map((s) => (
                  <button
                    key={s.roll}
                    type="button"
                    className="quick-chip-btn"
                    onClick={() => handleSampleClick(s)}
                  >
                    {s.roll} ({s.label})
                  </button>
                ))}
              </div>
            </div>
          </form>

          {error && (
            <div className="error-banner" role="alert">
              <AlertCircle size={18} className="error-banner-icon" />
              <span>{error}</span>
            </div>
          )}
        </section>

        {/* The Result Screen */}
        {currentSeat && (
          <section className="student-result-card" aria-label="Seating details">
            {/* Multi-Exam Switcher */}
            {allExams.length > 1 && (
              <div className="multi-exam-tabs no-print" role="tablist" aria-label="Exam sessions">
                {allExams.map((ex) => (
                  <button
                    key={ex.exam_id}
                    role="tab"
                    aria-selected={activeExamId === ex.exam_id}
                    className={`exam-tab ${activeExamId === ex.exam_id ? 'active' : ''}`}
                    onClick={() => {
                      setActiveExamId(ex.exam_id);
                      handleFind(roll, ex.exam_id);
                    }}
                  >
                    <span className="exam-tab-name">{ex.exam_name}</span>
                    <span className="exam-tab-date">{fmtDate(ex.exam_date)}</span>
                  </button>
                ))}
              </div>
            )}

            {/* Print Header (Visible in print mode only) */}
            <div className="print-only print-header">
              <img src="/deskmap-full.svg" alt="DeskMap" width="64" />
              <h2>DeskMap Examination Seating Pass</h2>
              <p>Office of the Controller of Examinations</p>
            </div>

            {/* Strict Result Order */}
            <div className="result-core-hierarchy">
              {/* 1. Hall & Block (Biggest Type) */}
              <div className="result-hall-block">
                <span className="result-hall-title">
                  Hall {currentSeat.room_no}
                </span>
                <span className="result-block-subtitle">
                  {currentSeat.block}
                </span>
              </div>

              {/* 2. Seat Number (Sturdy Serif Font) */}
              <div className="result-seat-box">
                <span className="result-seat-label">Allocated Seat</span>
                <div className="result-seat-number font-serif">
                  Seat #{currentSeat.bench_no || currentSeat.row_num}
                  {sideLabel && <span className="result-seat-side">({sideLabel})</span>}
                </div>
                <div className="result-seat-coords font-mono">
                  Row {currentSeat.row_num} · Desk Column {currentSeat.col_num}
                </div>
              </div>

              {/* 3. Exam Name, Date, Start Time & Report-by Time */}
              <div className="result-meta-grid">
                <div className="meta-item">
                  <span className="meta-label">Examination</span>
                  <span className="meta-value">{currentSeat.exam_name}</span>
                </div>
                <div className="meta-item">
                  <span className="meta-label">Exam Date</span>
                  <span className="meta-value d-flex align-items-center gap-1">
                    <Calendar size={15} />
                    {fmtDate(currentSeat.exam_date)}
                  </span>
                </div>
                <div className="meta-item">
                  <span className="meta-label">Start Time</span>
                  <span className="meta-value d-flex align-items-center gap-1">
                    <Clock size={15} />
                    {fmtTime(currentSeat.start_time)}
                  </span>
                </div>
                <div className="meta-item">
                  <span className="meta-label">Report By</span>
                  <span className="meta-value report-by-highlight">
                    {fmtTime(currentSeat.reporting_time || currentSeat.start_time)}
                  </span>
                </div>
                <div className="meta-item">
                  <span className="meta-label">Student</span>
                  <span className="meta-value">
                    {currentSeat.name} <span className="font-mono text-muted">({currentSeat.roll_no})</span>
                  </span>
                </div>
                <div className="meta-item">
                  <span className="meta-label">Subject Code</span>
                  <span className="meta-value font-mono">{currentSeat.exam_code}</span>
                </div>
              </div>

              {/* 4. Room Map with Circling Reveal */}
              {room && (
                <div className="result-room-section">
                  <div className="room-section-header">
                    <h3 className="room-section-title font-serif">
                      Room Map — Hall {currentSeat.room_no}
                    </h3>
                    <span className="room-section-meta font-mono">
                      {room.rows_count} rows × {room.cols_count} desks/row
                    </span>
                  </div>

                  <RoomGrid
                    room={room}
                    highlightRoll={currentSeat.roll_no}
                    targetRow={currentSeat.row_num}
                    targetCol={currentSeat.col_num}
                  />
                </div>
              )}
            </div>

            {/* Quick Actions (Save as image, Add to calendar, Print) */}
            <div className="result-actions-row no-print">
              <button
                type="button"
                className="action-btn"
                onClick={() => downloadSeatImage(currentSeat)}
              >
                <ImageIcon size={18} />
                Save as image
              </button>

              <button
                type="button"
                className="action-btn"
                onClick={() => downloadCalendarEvent(currentSeat)}
              >
                <Download size={18} />
                Add to calendar
              </button>

              <button
                type="button"
                className="action-btn"
                onClick={() => window.print()}
              >
                <Printer size={18} />
                Print pass
              </button>
            </div>
          </section>
        )}
      </div>
    </main>
  );
}
