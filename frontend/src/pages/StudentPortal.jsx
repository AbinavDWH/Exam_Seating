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

// DOB formatting helpers (typed DD / MM / YYYY field)
function formatDobInput(raw) {
  const digits = raw.replace(/\D/g, '').slice(0, 8);
  if (digits.length <= 2) return digits;
  if (digits.length <= 4) return `${digits.slice(0, 2)} / ${digits.slice(2)}`;
  return `${digits.slice(0, 2)} / ${digits.slice(2, 4)} / ${digits.slice(4)}`;
}

function parseDobToISO(displayVal) {
  const digits = displayVal.replace(/\D/g, '');
  if (digits.length === 8) {
    const d = digits.slice(0, 2);
    const m = digits.slice(2, 4);
    const y = digits.slice(4, 8);
    return `${y}-${m}-${d}`;
  }
  return '';
}

function formatISOToDisplay(isoVal) {
  if (!isoVal || !/^\d{4}-\d{2}-\d{2}$/.test(isoVal)) return isoVal || '';
  const [y, m, d] = isoVal.split('-');
  return `${d} / ${m} / ${y}`;
}

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
  const [dobDisplay, setDobDisplay] = useState(() => {
    const raw = params.get('dob');
    if (raw) return formatISOToDisplay(raw);
    return '01 / 01 / 2005';
  });

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
    { roll: '2116241501001', label: 'AI&ML' },
  ];

  // Search logic
  const handleFind = useCallback(
    async (targetRoll, targetDobISO, targetExamId = null) => {
      const qRoll = String(targetRoll ?? roll).trim().toUpperCase();
      const qDob = String(targetDobISO ?? parseDobToISO(dobDisplay)).trim();

      if (!qRoll) {
        toast('Enter your roll number to find your seat');
        return;
      }
      if (!qDob) {
        toast('Enter your date of birth as DD / MM / YYYY');
        return;
      }

      setLoading(true);
      setError('');

      try {
        const res = await api.findSeat(qRoll, qDob, targetExamId);
        if (res.seat) {
          setResult(res);
          setActiveExamId(res.seat.exam_id);

          // Cache last result so students retain it on phone reloads
          try {
            localStorage.setItem(
              STORAGE_KEY,
              JSON.stringify({
                roll: qRoll,
                dob: qDob,
                res,
              })
            );
          } catch {}

          // Load room map
          if (res.seat.room_id) {
            try {
              const rRes = await api.getRoom(res.seat.room_id, res.seat.exam_id, qRoll, qDob);
              if (rRes.success && rRes.data) {
                setRoom(rRes.data);
              }
            } catch {}
          }
        } else {
          setError(res.error || 'We couldn’t find that roll number and date of birth. Check both and try again.');
        }
      } catch (err) {
        setError(err.message || 'We couldn’t find that roll number and date of birth. Check both and try again.');
      } finally {
        setLoading(false);
      }
    },
    [roll, dobDisplay, toast]
  );

  // Restore last result on load or URL search
  useEffect(() => {
    const qRoll = params.get('roll');
    const qDob = params.get('dob');
    if (qRoll && qDob) {
      handleFind(qRoll, qDob);
      return;
    }

    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed?.res?.seat) {
          setRoll(parsed.roll);
          setDobDisplay(formatISOToDisplay(parsed.dob));
          setResult(parsed.res);
          setActiveExamId(parsed.res.seat.exam_id);

          // Fetch fresh room data in background
          api
            .getRoom(parsed.res.seat.room_id, parsed.res.seat.exam_id, parsed.roll, parsed.dob)
            .then((r) => {
              if (r?.data) setRoom(r.data);
            })
            .catch(() => {});
        }
      }
    } catch {}
  }, []);

  const handleDobChange = (e) => {
    const formatted = formatDobInput(e.target.value);
    setDobDisplay(formatted);
  };

  const handleSampleClick = (sample) => {
    setRoll(sample.roll);
    setDobDisplay('01 / 01 / 2005');
    handleFind(sample.roll, '2005-01-01');
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
            Enter your university roll number and date of birth to find your exam hall and seat.
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

              <div className="form-field">
                <label htmlFor="studentDobInput" className="field-label">
                  Date of birth (DD / MM / YYYY)
                </label>
                <input
                  id="studentDobInput"
                  type="text"
                  inputMode="numeric"
                  className="field-input field-input-mono"
                  placeholder="DD / MM / YYYY"
                  value={dobDisplay}
                  onChange={handleDobChange}
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
                    className="demo-chip-btn"
                    onClick={() => handleSampleClick(s)}
                  >
                    <span>{s.label}</span>
                    <code>{s.roll.slice(-4)}</code>
                  </button>
                ))}
              </div>
            </div>
          </form>

          {/* Friendly Error Message */}
          {error && (
            <div className="student-error-banner" role="alert">
              <AlertCircle size={18} className="flex-shrink-0" />
              <span>{error}</span>
            </div>
          )}
        </section>

        {/* Result Screen */}
        {currentSeat && (
          <article className="seat-result-article print-slip-area">
            {/* Multi-Exam Tabs (Next Exam First) */}
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
                      handleFind(roll, parseDobToISO(dobDisplay), ex.exam_id);
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

            <div className="result-card">
              {/* 1. Hall and block, in the biggest type */}
              <div className="result-section result-hall-section">
                <span className="result-kicker">Examination Hall &amp; Block</span>
                <h1 className="result-hall-title">
                  Hall {currentSeat.room_no}, {currentSeat.block}
                </h1>
              </div>

              {/* 2. Seat number */}
              <div className="result-section result-seat-section">
                <span className="result-kicker">Assigned Desk</span>
                <div className="result-seat-number">
                  Seat {currentSeat.bench_no || currentSeat.row_num}
                </div>
                <div className="result-seat-sub">
                  Row {currentSeat.row_num}, Column {currentSeat.col_num} · {sideLabel}
                </div>
              </div>

              {/* 3. Exam name, date, start time and report by time */}
              <div className="result-section result-exam-section">
                <div className="exam-meta-grid">
                  <div className="meta-tile">
                    <span className="meta-label">Exam name</span>
                    <strong className="meta-value">{currentSeat.exam_name}</strong>
                  </div>
                  <div className="meta-tile">
                    <span className="meta-label">Date</span>
                    <span className="meta-value">{fmtDate(currentSeat.exam_date)}</span>
                  </div>
                  <div className="meta-tile">
                    <span className="meta-label">
                      <Clock size={14} /> Start time
                    </span>
                    <strong className="meta-value">{fmtTime(currentSeat.start_time)}</strong>
                  </div>
                  <div className="meta-tile report-tile">
                    <span className="meta-label">
                      <Clock size={14} /> Report by
                    </span>
                    <strong className="meta-value highlight-sage">
                      {fmtTime(currentSeat.reporting_time || currentSeat.start_time)}
                    </strong>
                  </div>
                </div>

                <div className="candidate-strip">
                  <span>Candidate: <strong>{currentSeat.name}</strong> ({currentSeat.roll_no})</span>
                  <span>Branch: <strong>{currentSeat.branch}</strong> (Sem {currentSeat.semester})</span>
                  <span>Paper code: <strong>{currentSeat.exam_code || 'Standard'}</strong></span>
                </div>
              </div>

              {/* Mobile Quick Action Buttons (at least 48px tall) */}
              <div className="result-actions-row no-print">
                <button
                  type="button"
                  className="action-btn"
                  onClick={() => downloadSeatImage(currentSeat)}
                >
                  <ImageIcon size={18} />
                  <span>Save as image</span>
                </button>

                <button
                  type="button"
                  className="action-btn"
                  onClick={() => downloadCalendarEvent(currentSeat)}
                >
                  <Calendar size={18} />
                  <span>Add to calendar</span>
                </button>

                <button
                  type="button"
                  className="action-btn"
                  onClick={() => window.print()}
                >
                  <Printer size={18} />
                  <span>Print slip</span>
                </button>
              </div>

              {/* 4. The room map with the student's seat circled */}
              {room && (
                <div className="result-section result-map-section">
                  <div className="map-section-header">
                    <h3 className="map-section-title">Room Map &amp; Desk Location</h3>
                    <p className="map-section-sub">
                      Your seat is circled on the room map below.
                    </p>
                  </div>
                  <RoomGrid
                    room={room.room}
                    seats={room.seats}
                    highlightRoll={currentSeat.roll_no}
                  />
                </div>
              )}
            </div>
          </article>
        )}
      </div>
    </main>
  );
}
