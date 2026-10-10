import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
  Search,
  Calendar,
  Clock,
  ArrowRight,
  Sparkles,
  ChevronDown,
  ChevronUp,
  X,
  AlertCircle,
  CheckCircle2,
  BookOpen,
} from 'lucide-react';
import { api } from '../api/client.js';
import SeatCard from '../components/SeatCard.jsx';
import RoomGrid from '../components/RoomGrid.jsx';
import { useToast } from '../components/Toast.jsx';

const RECENT_KEY = 'examseat_recent';
const LAST_RESULT_KEY = 'examseat_last_result';

function ResultSkeleton() {
  return (
    <div className="seat-card card-enter" aria-hidden="true" style={{ borderTop: '4px solid #cbd5e1' }}>
      <div style={{ padding: '26px 30px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between' }}>
        <div>
          <div className="skel" style={{ width: 130, height: 26, borderRadius: 999, marginBottom: 14 }} />
          <div className="skel" style={{ width: 220, height: 40, marginBottom: 8 }} />
          <div className="skel" style={{ width: 140, height: 20 }} />
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end' }}>
          <div className="skel" style={{ width: 160, height: 26, marginBottom: 8 }} />
          <div className="skel" style={{ width: 120, height: 18 }} />
        </div>
      </div>
      <div style={{ padding: '26px 30px' }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12, marginBottom: 24 }}>
          {[...Array(4)].map((_, i) => (
            <div key={i} className="skel" style={{ height: 68 }} />
          ))}
        </div>
        <div className="skel" style={{ height: 140, marginBottom: 24 }} />
        <div style={{ display: 'flex', gap: 10 }}>
          <div className="skel" style={{ flex: 1, height: 46 }} />
          <div className="skel" style={{ flex: 1, height: 46 }} />
          <div className="skel" style={{ flex: 1, height: 46 }} />
        </div>
      </div>
    </div>
  );
}

export default function FindSeat() {
  const [params] = useSearchParams();
  const toast = useToast();
  const [roll, setRoll] = useState(params.get('roll') || '');
  const [dob, setDob] = useState(params.get('dob') || '2005-01-01');
  const [status, setStatus] = useState('idle'); // idle | loading | found | error
  const [result, setResult] = useState(null);
  const [room, setRoom] = useState(null);
  const [error, setError] = useState('');
  const [showAllDemos, setShowAllDemos] = useState(false);
  const [recent, setRecent] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem(RECENT_KEY)) || [];
    } catch {
      return [];
    }
  });

  const sampleRolls = [
    { roll: '2116241801001', label: 'AI&DS' },
    { roll: '2116251001001', label: 'IT' },
    { roll: '2116250701001', label: 'CSE' },
    { roll: '2116241501001', label: 'AI&ML' },
    { roll: '2116251401001', label: 'CSBS' },
    { roll: '2116241101001', label: 'MECH' },
    { roll: '2116240901001', label: 'EEE' },
    { roll: '2116240101001', label: 'AERO' },
  ];

  // Show only 3–4 demo chips by default (Item 9)
  const visibleDemos = showAllDemos ? sampleRolls : sampleRolls.slice(0, 4);

  const search = useCallback(
    async (value, dobVal, examIdVal = null) => {
      const rollNo = String(value ?? roll).trim();
      const currentDob = String(dobVal ?? dob).trim() || '2005-01-01';
      if (!rollNo) {
        toast('Please enter your university roll number');
        return;
      }
      setStatus('loading');
      setResult(null);
      setRoom(null);
      setError('');

      try {
        const { data } = await api.findSeat(rollNo, currentDob, examIdVal);
        setResult(data);
        setStatus('found');

        // Persist recent search roll numbers
        setRecent((prev) => {
          const next = [data.roll_no, ...prev.filter((r) => r !== data.roll_no)].slice(0, 5);
          localStorage.setItem(RECENT_KEY, JSON.stringify(next));
          return next;
        });

        // Save last result for "Your next exam" card on Home (Item 33)
        try {
          localStorage.setItem(
            LAST_RESULT_KEY,
            JSON.stringify({
              roll_no: data.roll_no,
              name: data.name,
              branch: data.branch,
              semester: data.semester,
              room_no: data.room_no,
              block: data.block,
              bench_no: data.bench_no,
              row_num: data.row_num,
              col_num: data.col_num,
              seat_index: data.seat_index,
              exam_id: data.exam_id,
              exam_name: data.exam_name,
              exam_date: data.exam_date,
              start_time: data.start_time,
              reporting_time: data.reporting_time,
            })
          );
        } catch {
          // ignore localStorage error
        }

        try {
          const roomRes = await api.getRoom(data.room_id, data.exam_id, data.roll_no, currentDob);
          setRoom(roomRes.data);
        } catch {
          setRoom(null);
        }
      } catch (e) {
        setStatus('error');
        setError(e.message || 'Seat allocation not found');
      }
    },
    [roll, dob, toast]
  );

  useEffect(() => {
    const r = params.get('roll');
    const d = params.get('dob') || '2005-01-01';
    if (r) {
      setRoll(r);
      if (d) setDob(d);
      search(r, d);
    }
  }, [params, search]);

  const clearRecent = () => {
    localStorage.removeItem(RECENT_KEY);
    setRecent([]);
  };

  const handleFormSubmit = (e) => {
    e.preventDefault();
    search();
  };

  return (
    <div className="container" style={{ padding: '36px 0 70px' }}>
      <div style={{ maxWidth: 740, margin: '0 auto' }} className="no-print">
        {/* Item 6: Friendly heading above the search card */}
        <div className="search-header">
          <h1>Find your exam seat</h1>
          <p>
            Enter your university roll number and date of birth to check your hall and seat allocation.
          </p>
        </div>

        {/* Item 7 & 8: Clear labels, 1 row on laptop, stacked on mobile */}
        <form className="search-card-container" onSubmit={handleFormSubmit}>
          <div className="search-row-form">
            <div className="search-field flex-roll">
              <label htmlFor="roll-field">Roll Number</label>
              <div className="input-with-icon">
                <span className="input-icon">
                  <Search size={18} />
                </span>
                <input
                  id="roll-field"
                  className="search-input"
                  value={roll}
                  onChange={(e) => setRoll(e.target.value)}
                  placeholder="e.g. 2116241801001"
                  aria-label="Roll Number"
                  autoFocus
                  required
                />
              </div>
            </div>

            <div className="search-field flex-dob">
              <label htmlFor="dob-field">Date of Birth</label>
              <div className="input-with-icon">
                <span className="input-icon">
                  <Calendar size={18} />
                </span>
                <input
                  id="dob-field"
                  type="date"
                  className="search-input"
                  value={dob}
                  onChange={(e) => setDob(e.target.value)}
                  aria-label="Date of Birth"
                  required
                />
              </div>
            </div>

            <button
              type="submit"
              className="btn-solid"
              disabled={status === 'loading'}
              aria-label="Find My Seat"
            >
              {status === 'loading' ? (
                'Searching…'
              ) : (
                <>
                  Find My Seat <ArrowRight size={16} />
                </>
              )}
            </button>
          </div>
        </form>

        {/* Quick Demos & Recent History */}
        <div className="chips-wrap">
          {/* Item 9: Show only 3–4 quick demo chips with a "More" link */}
          <div className="chips">
            <span className="chip-label">Quick Demos:</span>
            {visibleDemos.map((s) => (
              <button
                key={s.roll}
                type="button"
                className="chip"
                onClick={() => {
                  setRoll(s.roll);
                  setDob('2005-01-01');
                  search(s.roll, '2005-01-01');
                }}
                title={`Try ${s.roll} (${s.label})`}
                aria-label={`Demo roll number ${s.roll} for ${s.label}`}
              >
                <b>{s.roll}</b>
                <span className="chip-dept-tag">{s.label}</span>
              </button>
            ))}

            <button
              type="button"
              className="chip-more-toggle"
              onClick={() => setShowAllDemos((prev) => !prev)}
              aria-expanded={showAllDemos}
              aria-label={showAllDemos ? 'Show fewer demo roll numbers' : 'Show more demo roll numbers'}
            >
              {showAllDemos ? (
                <>
                  Less <ChevronUp size={14} />
                </>
              ) : (
                <>
                  More (+4) <ChevronDown size={14} />
                </>
              )}
            </button>
          </div>

          {/* Item 10: Recent row with small clock icon */}
          {recent.length > 0 && (
            <div className="chips">
              <span className="chip-label">
                <Clock size={13} /> Recent:
              </span>
              {recent.map((r) => (
                <button
                  key={r}
                  type="button"
                  className="chip"
                  onClick={() => {
                    setRoll(r);
                    search(r);
                  }}
                  aria-label={`Search again for roll number ${r}`}
                >
                  {r}
                </button>
              ))}
              <button
                type="button"
                className="chip-clear"
                onClick={clearRecent}
                title="Clear history"
                aria-label="Clear recent searches"
              >
                <X size={12} style={{ marginRight: 3, verticalAlign: 'middle' }} /> Clear
              </button>
            </div>
          )}
        </div>
      </div>

      <div style={{ marginTop: 32 }}>
        {/* Item 24: Loading skeleton with soft grey shimmer */}
        {status === 'loading' && <ResultSkeleton />}

        {/* Idle State */}
        {status === 'idle' && (
          <div className="state-box no-print card-enter" style={{ marginTop: 24 }}>
            <div className="state-box-icon">
              <Search size={24} />
            </div>
            <h3>Check Your Examination Seating Plan</h3>
            <p>
              Type your roll number and date of birth above to locate your assigned hall,
              desk, row, column, and interactive classroom floor plan.
            </p>
          </div>
        )}

        {/* Item 25: Friendly error state with actionable fix tip */}
        {status === 'error' && (
          <div className="state-box no-print card-enter" style={{ marginTop: 24 }}>
            <div className="state-box-icon error">
              <AlertCircle size={26} />
            </div>
            <h3>Seat Allocation Not Found</h3>
            <p>
              {error || `No student seat record found matching this roll number and date of birth.`}
            </p>

            <div className="fix-tip-box">
              <b>Fix Tip:</b> Please check the 13 digits of your university roll number,
              verify your Date of Birth (default demo: <code>01/01/2005</code>), or try one of the instant demos below:
            </div>

            <div className="chips" style={{ justifyContent: 'center', marginBottom: 18 }}>
              {sampleRolls.slice(0, 3).map((s) => (
                <button
                  key={s.roll}
                  type="button"
                  className="chip"
                  onClick={() => {
                    setRoll(s.roll);
                    setDob('2005-01-01');
                    search(s.roll, '2005-01-01');
                  }}
                >
                  <b>{s.roll}</b> <span className="chip-dept-tag">({s.label})</span>
                </button>
              ))}
            </div>

            <button
              type="button"
              className="btn-solid"
              onClick={() => search()}
              aria-label="Search Again"
            >
              Search Again
            </button>
          </div>
        )}

        {/* Found State with Item 26 & 31: 0.3s enter animation & checkmark reward */}
        {status === 'found' && result && (
          <div className="card-enter">
            {/* Item 31: One-time check-mark delight banner */}
            <div style={{ textAlign: 'center' }} className="no-print">
              <div className="celebrate-toast">
                <CheckCircle2 size={16} />
                <span>Seat found successfully for {result.name}</span>
              </div>
            </div>

            {/* Switch exam session if multiple papers exist */}
            {result.all_exams && result.all_exams.length > 1 && (
              <div
                className="no-print"
                style={{
                  maxWidth: 620,
                  margin: '0 auto 20px',
                  background: '#ffffff',
                  padding: '14px 18px',
                  borderRadius: 'var(--radius-lg)',
                  border: '1px solid var(--border)',
                  boxShadow: 'var(--shadow-sm)',
                }}
              >
                <div
                  style={{
                    fontSize: '0.84rem',
                    fontWeight: 700,
                    color: 'var(--text-muted)',
                    marginBottom: 10,
                    display: 'flex',
                    alignItems: 'center',
                    gap: 6,
                  }}
                >
                  <BookOpen size={15} />
                  <span>You have {result.all_exams.length} scheduled exam sessions. Switch paper:</span>
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                  {result.all_exams.map((ex) => {
                    const isSelected = +ex.exam_id === +result.exam_id;
                    return (
                      <button
                        key={ex.exam_id}
                        type="button"
                        className={isSelected ? 'btn-solid' : 'btn-outline'}
                        style={{
                          fontSize: '0.82rem',
                          height: 38,
                          padding: '0 14px',
                          borderRadius: 999,
                        }}
                        onClick={() => search(roll, dob, ex.exam_id)}
                      >
                        {ex.exam_code ? `[${ex.exam_code}] ` : ''}
                        {ex.exam_name} · {ex.exam_date}
                      </button>
                    );
                  })}
                </div>
              </div>
            )}

            <SeatCard data={result} dob={dob} />

            {room ? (
              <RoomGrid
                room={room.room}
                seats={room.seats}
                highlightRoll={result.roll_no}
              />
            ) : (
              <div
                className="room-map-card"
                style={{ textAlign: 'center', color: 'var(--text-subtle)', padding: 36 }}
              >
                Loading classroom floor plan…
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}