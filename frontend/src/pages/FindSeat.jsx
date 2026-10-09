import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../api/client.js';
import SeatCard from '../components/SeatCard.jsx';
import RoomGrid from '../components/RoomGrid.jsx';
import { useToast } from '../components/Toast.jsx';

const RECENT_KEY = 'examseat_recent';
const SAMPLE_ROLLS = ['23CS101', '23EC101', '23ME101', '22IT101', '22AD101', '21EC301'];

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
    async (value) => {
      const rollNo = String(value ?? roll).trim();
      if (!rollNo) {
        toast('🙂 Please enter your university roll number');
        return;
      }
      setStatus('loading');
      setResult(null);
      setRoom(null);
      try {
        const { data } = await api.findSeat(rollNo);
        setResult(data);
        setStatus('found');
        setRecent((prev) => {
          const next = [data.roll_no, ...prev.filter((r) => r !== data.roll_no)].slice(0, 5);
          localStorage.setItem(RECENT_KEY, JSON.stringify(next));
          return next;
        });
        try {
          const roomRes = await api.getRoom(data.room_id, data.exam_id);
          setRoom(roomRes.data);
        } catch {
          setRoom(null);
        }
      } catch (e) {
        setStatus('error');
        setError(e.message);
      }
    },
    [roll, toast]
  );

  useEffect(() => {
    const r = params.get('roll');
    if (r) {
      setRoll(r);
      search(r);
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
        <div className="search-card" style={{ marginTop: 0 }}>
          <span style={{ fontSize: '1.25rem', color: 'var(--text-muted)' }}>🔍</span>
          <input
            className="search-input"
            value={roll}
            onChange={(e) => setRoll(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && search()}
            placeholder="Enter Roll Number… e.g. 23CS101, 22IT101"
            aria-label="Roll number"
            autoFocus
          />
          <button
            className="btn-grad"
            onClick={() => search()}
            disabled={status === 'loading'}
          >
            {status === 'loading' ? 'Searching…' : 'Find My Seat →'}
          </button>
        </div>

        {recent.length > 0 ? (
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
        ) : (
          <div className="chips">
            <span className="chip-label">Quick Try:</span>
            {SAMPLE_ROLLS.map((r) => (
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
              Type your roll number above or click one of the quick suggestions. We'll show you
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