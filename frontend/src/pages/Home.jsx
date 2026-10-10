import { useEffect, useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  Search,
  Calendar,
  Clock,
  ArrowRight,
  BookOpen,
  MapPin,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Inbox,
  User,
  Sparkles,
  X,
  Users,
  Building2,
  LayoutGrid,
  Layers,
  CornerDownLeft,
} from 'lucide-react';
import { api } from '../api/client.js';

const LAST_RESULT_KEY = 'examseat_last_result';
const RECENT_KEY = 'examseat_recent';

const fmtDate = (d) =>
  new Date(d + 'T00:00:00').toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });

function getCountdown(examDate, startTime) {
  if (!examDate) return 'Starts in 3 days 4 hrs';
  const target = new Date(`${examDate}T${startTime || '09:30:00'}`);
  const now = new Date();
  const diffMs = target.getTime() - now.getTime();

  if (diffMs > 0) {
    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const mins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    if (days > 0) return `Starts in ${days} days ${hours} hrs`;
    if (hours > 0) return `Starts in ${hours} hrs ${mins} mins`;
    return `Starts in ${mins} mins`;
  }
  return 'Starts in 3 days 4 hrs';
}

export default function Home() {
  const [q, setQ] = useState('');
  const [data, setData] = useState(null);
  const [showAllDemos, setShowAllDemos] = useState(false);
  const [lastExam, setLastExam] = useState(null);
  const [countdown, setCountdown] = useState('Starts in 3 days 4 hrs');
  const [recent, setRecent] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem(RECENT_KEY)) || [];
    } catch {
      return [];
    }
  });
  const navigate = useNavigate();

  useEffect(() => {
    api
      .getExams()
      .then((r) => setData(r.data))
      .catch(() => {});

    try {
      const stored = localStorage.getItem(LAST_RESULT_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        setLastExam(parsed);
        setCountdown(getCountdown(parsed.exam_date, parsed.start_time));
      }
    } catch {
      // ignore parse error
    }
  }, []);

  useEffect(() => {
    if (!lastExam) return;
    const timer = setInterval(() => {
      setCountdown(getCountdown(lastExam.exam_date, lastExam.start_time));
    }, 30000);
    return () => clearInterval(timer);
  }, [lastExam]);

  const clearLastExam = (e) => {
    e.stopPropagation();
    try {
      localStorage.removeItem(LAST_RESULT_KEY);
      setLastExam(null);
    } catch {}
  };

  const submit = (e) => {
    e.preventDefault();
    const roll = q.trim();
    navigate(roll ? `/find?roll=${encodeURIComponent(roll)}` : '/find');
  };

  const sampleRolls = [
    { roll: '2116241801001', label: 'AI&DS (Yr 3)' },
    { roll: '2116251001001', label: 'IT (Yr 2)' },
    { roll: '2116250701001', label: 'CSE (Yr 2)' },
    { roll: '2116241501001', label: 'AI&ML (Yr 3)' },
    { roll: '2116251401001', label: 'CSBS (Yr 2)' },
    { roll: '2116241101001', label: 'MECH (Yr 3)' },
    { roll: '2116240901001', label: 'EEE (Yr 3)' },
  ];

  const visibleDemos = showAllDemos ? sampleRolls : sampleRolls.slice(0, 4);

  return (
    <>
      <header className="hero">
        <div className="container">
          <div className="hero-pill">
            <span className="hero-pulse" />
            <span>Rajalakshmi Engineering College (Autonomous) · CAT I (Slot I)</span>
          </div>
          <h1>Find Your Exam Desk in Seconds</h1>
          <p>
            Continuous Assessment Test I (Odd Sem AY 2026–2027). Enter your university register number to get your hall, row, column, and exact desk coordinates.
          </p>

          {/* 4 Stat Cards with Small Icons & Formatted Commas in Numbers (Points 2 & 3) */}
          <div className="stats-row">
            {data ? (
              <>
                <div className="stat-pill">
                  <div className="stat-pill-icon" style={{ background: '#EEF2E6', color: '#43522C' }}>
                    <Users size={16} />
                  </div>
                  <b>{Number(data.stats.students).toLocaleString('en-IN')}</b>
                  <span>Enrolled Students</span>
                </div>
                <div className="stat-pill">
                  <div className="stat-pill-icon" style={{ background: '#FAF3E1', color: '#6B4E0E' }}>
                    <Building2 size={16} />
                  </div>
                  <b>{Number(data.stats.rooms).toLocaleString('en-IN')}</b>
                  <span>Exam Halls</span>
                </div>
                <div className="stat-pill">
                  <div className="stat-pill-icon" style={{ background: '#EDF3F8', color: '#27384B' }}>
                    <LayoutGrid size={16} />
                  </div>
                  <b>{Number(data.stats.capacity).toLocaleString('en-IN')}</b>
                  <span>Total Desks</span>
                </div>
                <div className="stat-pill">
                  <div className="stat-pill-icon" style={{ background: '#FBF2E3', color: '#70381D' }}>
                    <Layers size={16} />
                  </div>
                  <b>{Number(data.stats.branches).toLocaleString('en-IN')}</b>
                  <span>Departments</span>
                </div>
              </>
            ) : (
              [...Array(4)].map((_, i) => (
                <div key={i} className="skel" style={{ width: 140, height: 72 }} />
              ))
            )}
          </div>
        </div>
      </header>

      <div className="container search-shell">
        {/* Item 4 & 5: "Your Next Exam" banner with Countdown Timer and Proper Close Button */}
        {lastExam && (
          <div className="next-exam-banner card-enter" role="region" aria-label="Your next exam card">
            <div className="next-exam-left">
              <div
                style={{
                  width: 44,
                  height: 44,
                  borderRadius: 'var(--radius-md)',
                  background: '#EEF2E6',
                  color: '#43522C',
                  display: 'grid',
                  placeItems: 'center',
                  flexShrink: 0,
                }}
              >
                <Clock size={22} />
              </div>
              <div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 2, flexWrap: 'wrap' }}>
                  <span className="next-exam-badge">
                    <Sparkles size={12} /> Your Next Exam
                  </span>
                  <span className="countdown-chip" style={{ marginLeft: 0 }}>
                    <Clock size={12} /> {countdown}
                  </span>
                  <span style={{ fontSize: '0.82rem', color: 'var(--text-subtle)' }}>
                    {lastExam.name} ({lastExam.roll_no})
                  </span>
                </div>
                <div style={{ fontSize: '1.05rem', fontWeight: 800, color: 'var(--text)' }}>
                  Hall {lastExam.room_no} · Desk #{lastExam.bench_no || lastExam.row_num} (Row {lastExam.row_num}, Col {lastExam.col_num})
                </div>
                <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>
                  {lastExam.exam_name} · {fmtDate(lastExam.exam_date)}
                </div>
              </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              <Link
                to={`/find?roll=${encodeURIComponent(lastExam.roll_no)}&dob=2005-01-01`}
                className="btn-solid"
                style={{ height: 40, padding: '0 16px', fontSize: '0.86rem' }}
                aria-label="View Full Admit Card"
              >
                View Admit Card <ArrowRight size={14} />
              </Link>
              {/* Item 5: Proper close icon button with hover state */}
              <button
                type="button"
                className="banner-close-btn"
                onClick={clearLastExam}
                title="Dismiss this notification"
                aria-label="Dismiss next exam notification"
              >
                <X size={16} />
              </button>
            </div>
          </div>
        )}

        <form className="search-card-container" onSubmit={submit}>
          <div className="search-row-form">
            <div className="search-field flex-roll">
              <label htmlFor="home-roll-input">Roll Number</label>
              <div className="input-with-icon">
                <span className="input-icon">
                  <Search size={18} />
                </span>
                <input
                  id="home-roll-input"
                  className="search-input"
                  placeholder="e.g. 2116241801001"
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                  aria-label="Register or Roll number"
                />
              </div>
              {/* Item 6: Tiny hint under roll input */}
              <div className="input-hint-line">
                <CornerDownLeft size={12} />
                <span>Press Enter to search</span>
              </div>
            </div>

            <button type="submit" className="btn-solid" aria-label="Find My Seat">
              Find My Seat <ArrowRight size={16} />
            </button>
          </div>
        </form>

        {/* Quick Demo Chips & Item 6: Recent Searches on Home */}
        <div className="chips-wrap" style={{ alignItems: 'center' }}>
          <div className="chips" style={{ justifyContent: 'center' }}>
            <span className="chip-label">Quick Demos:</span>
            {visibleDemos.map((s) => (
              <button
                key={s.roll}
                type="button"
                className="chip"
                onClick={() =>
                  navigate(`/find?roll=${encodeURIComponent(s.roll)}&dob=2005-01-01`)
                }
                title={`Test ${s.roll} (${s.label})`}
                aria-label={`Demo roll ${s.roll} for ${s.label}`}
              >
                <b>{s.roll}</b>
                <span className="chip-dept-tag">({s.label})</span>
              </button>
            ))}

            <button
              type="button"
              className="chip-more-toggle"
              onClick={() => setShowAllDemos((prev) => !prev)}
              aria-expanded={showAllDemos}
              aria-label={showAllDemos ? 'Show fewer demos' : 'Show more demos'}
            >
              {showAllDemos ? (
                <>
                  Less <ChevronUp size={14} />
                </>
              ) : (
                <>
                  More (+3) <ChevronDown size={14} />
                </>
              )}
            </button>
          </div>

          {/* Item 6: Show recent searches on Home page */}
          {recent.length > 0 && (
            <div className="chips" style={{ justifyContent: 'center', marginTop: 4 }}>
              <span className="chip-label">
                <Clock size={13} /> Recent:
              </span>
              {recent.map((r) => (
                <button
                  key={r}
                  type="button"
                  className="chip"
                  onClick={() =>
                    navigate(`/find?roll=${encodeURIComponent(r)}&dob=2005-01-01`)
                  }
                  aria-label={`Search recent roll number ${r}`}
                >
                  {r}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* How it Works section */}
      <section className="section container">
        <h2 className="section-title">How It Works</h2>
        <p className="section-sub">A conflict-free, fair seating system built for university exams.</p>
        <div className="steps">
          <div className="step">
            <div className="step-icon">
              <User size={22} />
            </div>
            <h3>1 · Enter Roll Number</h3>
            <p>Type your roll number in the search field above. Quick autocomplete remembers your searches.</p>
          </div>
          <div className="step">
            <div className="step-icon">
              <CheckCircle2 size={22} />
            </div>
            <h3>2 · Get Exact Coordinates</h3>
            <p>Instantly retrieve your Hall number, Block, Row, Column, and reporting time.</p>
          </div>
          <div className="step">
            <div className="step-icon">
              <MapPin size={22} />
            </div>
            <h3>3 · Interactive Classroom Map</h3>
            <p>View the live classroom layout with your assigned desk glowing for quick identification on exam day.</p>
          </div>
        </div>
      </section>

      {/* Live exam sessions list */}
      <section className="section container" style={{ paddingTop: 0 }}>
        <h2 className="section-title">
          <Calendar size={22} style={{ verticalAlign: 'middle', marginRight: 8 }} />
          Live Exam Sessions
        </h2>
        <p className="section-sub">Seating plans published by the exam cell.</p>
        {data?.exams?.length ? (
          data.exams.map((e) => (
            <div key={e.id} className="exam-item card-enter">
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
            <div className="state-box-icon">
              <Inbox size={26} />
            </div>
            <h3>No Active Examination Sessions</h3>
            <p>Seating plans will appear here as soon as they are published by the examination office.</p>
          </div>
        )}
      </section>
    </>
  );
}