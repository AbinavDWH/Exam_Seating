import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../api/client.js';
import SeatCard from '../components/SeatCard.jsx';
import RoomGrid from '../components/RoomGrid.jsx';
import { useToast } from '../components/Toast.jsx';

const RECENT_KEY = 'examseat_recent';

function ResultSkeleton() {
  return (
    <div className="seat-card" aria-hidden="true">
      <div className="skel" style={{ height: 130, borderRadius: 0 }} />
      <div style={{ padding: 30 }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12, marginBottom: 24 }}>
          {[...Array(4)].map((_, i) => (
            <div key={i} className="skel" style={{ height: 72 }} />
          ))}
        </div>
        <div className="skel" style={{ height: 110, marginBottom: 24 }} />
        <div className="skel" style={{ height: 48 }} />
      </div>
    </div>
  );
}

export default function FindSeat() {
  const [params] = useSearchParams();
  const toast = useToast();
  const [roll, setRoll] = useState(params.get('roll') || '');
  const [dob, setDob] = useState(params.get('dob') || '');
  const [status, setStatus] = useState('idle'); // idle | loading | found | error
  const [result, setResult] = useState(null);
  const [room, setRoom] = useState(null);
  const [error, setError] = useState('');
  const [recent, setRecent] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem(RECENT_KEY)) || [];
    } catch {
      return [];
    }
  });

  const search = useCallback(
    async (value, dobVal, examIdVal = null) => {
      const rollNo = String(value ?? roll).trim();
      const currentDob = String(dobVal ?? dob).trim();
      if (!rollNo) {
        toast('🙂 Please enter your university roll number');
        return;
      }
      if (!currentDob) {
        toast('🎂 Please enter your date of birth for identity verification');
        return;
      }
      setStatus('loading');
      setResult(null);
      setRoom(null);
      try {
        const { data } = await api.findSeat(rollNo, currentDob, examIdVal);
        setResult(data);
        setStatus('found');
        setRecent((prev) => {
          const next = [data.roll_no, ...prev.filter((r) => r !== data.roll_no)].slice(0, 5);
          localStorage.setItem(RECENT_KEY, JSON.stringify(next));
          return next;
        });
        try {
          const roomRes = await api.getRoom(data.room_id, data.exam_id, data.roll_no);
          setRoom(roomRes.data);
        } catch {
          setRoom(null);
        }
      } catch (e) {
        setStatus('error');
        setError(e.message);
      }
    },
    [roll, dob, toast]
  );

  useEffect(() => {
    const r = params.get('roll');
    const d = params.get('dob') || '';
    if (r) {
      setRoll(r);
      if (d) setDob(d);
      if (r && d) {
        search(r, d);
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const clearRecent = () => {
    localStorage.removeItem(RECENT_KEY);
    setRecent([]);
  };

  return (
    <div className="container" style={{ padding: '40px 0 70px' }}>
      <div style={{ maxWidth: 660, margin: '0 auto' }} className="no-print">
        <div className="search-card" style={{ marginTop: 0, flexDirection: 'column', alignItems: 'stretch', gap: 10 }}>
          <div style={{ display: 'flex', alignItems: 'center', width: '100%', gap: 10 }}>
            <span style={{ fontSize: '1.25rem', color: 'var(--text-muted)' }}>🔍</span>
            <input
              className="search-input"
              value={roll}
              onChange={(e) => setRoll(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && search()}
              placeholder="Enter Roll Number… e.g. 23CS101"
              aria-label="Roll number"
              autoFocus
              required
            />
          </div>
          <div style={{ display: 'flex', alignItems: 'center', width: '100%', gap: 10, borderTop: '1px solid var(--border)', paddingTop: 10 }}>
            <span style={{ fontSize: '1.1rem', color: 'var(--text-muted)' }}>🎂</span>
            <input
              type="date"
              className="search-input"
              style={{ fontSize: '0.9rem', color: 'var(--text)' }}
              value={dob}
              onChange={(e) => setDob(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && search()}
              placeholder="Date of Birth (Required for verification)"
              title="Date of Birth (Required identity verification)"
              aria-label="Date of Birth"
              required
            />
            <button
              className="btn-grad"
              style={{ whiteSpace: 'nowrap' }}
              onClick={() => search()}
              disabled={status === 'loading'}
            >
              {status === 'loading' ? 'Searching…' : 'Find My Seat →'}
            </button>
          </div>
        </div>

        {recent.length > 0 && (
          <div className="chips">
            <span className="chip-label">Recent:</span>
            {recent.map((r) => (
              <button
                key={r}
                className="chip"
                onClick={() => {
                  setRoll(r);
                  search(r);
                }}
              >
                {r}
              </button>
            ))}
            <button className="chip-clear" onClick={clearRecent} title="Clear history">
              ✕ Clear
            </button>
          </div>
        )}
      </div>

      <div style={{ marginTop: 36 }}>
        {status === 'loading' && <ResultSkeleton />}

        {status === 'idle' && (
          <div className="state-box no-print rise">
            <span className="emoji">🪑</span>
            <h3>Locate Your University Examination Desk</h3>
            <p>
              Type your roll number and optional date of birth above. We'll show you
              your exact Hall, Desk, Row, Column, reporting time, and interactive floor plan.
            </p>
          </div>
        )}

        {status === 'error' && (
          <div className="state-box no-print rise">
            <span className="emoji">🔎</span>
            <h3>Seat Allocation Not Found</h3>
            <p>
              {error}. Please check for typos in your roll number, or verify if the exam cell
              has published the seating arrangement for your semester.
            </p>
            <button className="btn-grad" onClick={() => search()}>
              ↻ Search Again
            </button>
          </div>
        )}

        {status === 'found' && result && (
          <>
            {result.all_exams && result.all_exams.length > 1 && (
              <div
                className="no-print rise"
                style={{
                  marginBottom: 20,
                  background: 'var(--surface, #ffffff)',
                  padding: '14px 18px',
                  borderRadius: 14,
                  border: '1px solid var(--border, #e2e8f0)',
                  boxShadow: '0 2px 8px rgba(0,0,0,0.04)',
                }}
              >
                <div style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-muted)', marginBottom: 10 }}>
                  📅 You have {result.all_exams.length} scheduled examination sessions. Switch paper:
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                  {result.all_exams.map((ex) => {
                    const isSelected = +ex.exam_id === +result.exam_id;
                    return (
                      <button
                        key={ex.exam_id}
                        type="button"
                        className={isSelected ? 'btn-grad' : 'btn-ghost'}
                        style={{ fontSize: '0.82rem', padding: '6px 14px', borderRadius: 8 }}
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
            <SeatCard data={result} />
            {room ? (
              <RoomGrid room={room.room} seats={room.seats} highlightRoll={result.roll_no} />
            ) : (
              <div
                className="room-map-card rise rise-2"
                style={{ textAlign: 'center', color: 'var(--text-muted)' }}
              >
                🗺️ Loading classroom floor plan…
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}