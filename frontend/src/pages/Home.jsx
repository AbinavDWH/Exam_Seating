import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api/client.js';

const fmtDate = (d) =>
  new Date(d + 'T00:00:00').toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });

const SAMPLE_CHIPS = ['23CS101', '23EC101', '23ME101', '22IT101', '21EC301'];

export default function Home() {
  const [q, setQ] = useState('');
  const [data, setData] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    api
      .getExams()
      .then((r) => setData(r.data))
      .catch(() => {});
  }, []);

  const submit = (e) => {
    e.preventDefault();
    const roll = q.trim();
    navigate(roll ? `/find?roll=${encodeURIComponent(roll)}` : '/find');
  };

  return (
    <>
      <header className="hero">
        <div className="container">
          <div className="hero-pill">
            <span className="hero-pulse" />
            <span>Official University Examination Seating Portal</span>
          </div>
          <h1>Find Your Exam Desk in Seconds</h1>
          <p>
            No crowded notice boards. No exam hall confusion. Simply enter your university roll
            number to get your hall, row, column, and exact desk coordinates.
          </p>
          <div className="stats-row">
            {data ? (
              <>
                <div className="stat-pill">
                  <b>{data.stats.students}</b>
                  <span>Enrolled</span>
                </div>
                <div className="stat-pill">
                  <b>{data.stats.rooms}</b>
                  <span>Exam Halls</span>
                </div>
                <div className="stat-pill">
                  <b>{data.stats.capacity}</b>
                  <span>Total Desks</span>
                </div>
                <div className="stat-pill">
                  <b>{data.stats.branches}</b>
                  <span>Departments</span>
                </div>
              </>
            ) : (
              [...Array(4)].map((_, i) => (
                <div key={i} className="skel" style={{ width: 120, height: 64 }} />
              ))
            )}
          </div>
        </div>
      </header>

      <div className="container search-shell">
        <form className="search-card" onSubmit={submit}>
          <span style={{ fontSize: '1.25rem', color: 'var(--text-muted)' }}>🔍</span>
          <input
            className="search-input"
            placeholder="Enter your university roll number… e.g. 23CS101"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            aria-label="Roll number"
          />
          <button className="btn-grad" type="submit">
            Find My Seat →
          </button>
        </form>

        <div className="chips" style={{ justifyContent: 'center', marginTop: 14 }}>
          <span className="chip-label">Try Sample:</span>
          {SAMPLE_CHIPS.map((roll) => (
            <button
              key={roll}
              type="button"
              className="chip"
              onClick={() => navigate(`/find?roll=${encodeURIComponent(roll)}`)}
            >
              {roll}
            </button>
          ))}
        </div>
      </div>

      <section className="section container">
        <h2 className="section-title">How It Works</h2>
        <p className="section-sub">A conflict-free, fair seating system built for university exams.</p>
        <div className="steps">
          <div className="step">
            <div className="step-icon">🔢</div>
            <h3>1 · Enter Roll Number</h3>
            <p>Type your roll number in the search field above. Quick autocomplete remembers your searches.</p>
          </div>
          <div className="step">
            <div className="step-icon">🎯</div>
            <h3>2 · Get Exact Coordinates</h3>
            <p>Instantly retrieve your Hall number, Block, Row, Column, and reporting time.</p>
          </div>
          <div className="step">
            <div className="step-icon">🗺️</div>
            <h3>3 · Interactive Classroom Map</h3>
            <p>View the live classroom layout with your assigned desk glowing for quick identification on exam day.</p>
          </div>
        </div>
      </section>

      <section className="section container" style={{ paddingTop: 0 }}>
        <h2 className="section-title">📅 Live Exam Sessions</h2>
        <p className="section-sub">Seating plans published by the exam cell.</p>
        {data?.exams?.length ? (
          data.exams.map((e) => (
            <div key={e.id} className="exam-item rise">
              <div>
                <h4>{e.exam_name}</h4>
                <div className="meta">
                  {fmtDate(e.exam_date)} · Semester {e.semester} · {e.assigned} / {e.students} students seated
                </div>
              </div>
              <span className={`badge ${e.status}`}>{e.status}</span>
            </div>
          ))
        ) : (
          <div className="state-box">
            <span className="emoji">📭</span>
            <h3>No Active Examination Sessions</h3>
            <p>Seating plans will appear here as soon as they are published by the examination office.</p>
          </div>
        )}
      </section>
    </>
  );
}