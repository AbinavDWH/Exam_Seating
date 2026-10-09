import { useState } from 'react';
import { Link } from 'react-router-dom';
import { deptColor } from '../api/client.js';
import { useToast } from './Toast.jsx';

const fmtDate = (d) =>
  new Date(d + 'T00:00:00').toLocaleDateString('en-IN', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

const fmtTime = (t) =>
  new Date(`2000-01-01T${t}`).toLocaleTimeString('en-IN', {
    hour: 'numeric',
    minute: '2-digit',
  });

export default function SeatCard({ data }) {
  const toast = useToast();
  const [copied, setCopied] = useState(false);

  const copyDetails = async () => {
    const reportingStr = data.reporting_time
      ? `Reporting Time: ${fmtTime(data.reporting_time)} (30 mins before start)\nExam Starts: ${fmtTime(data.start_time)}`
      : `Exam Starts: ${fmtTime(data.start_time)}`;
    const text = `🎓 UNIVERSITY EXAM SEAT PASS
Name: ${data.name} (${data.roll_no})
Branch: ${data.branch} · Semester ${data.semester}
Exam Paper Code: ${data.exam_code || (data.branch + '-S' + data.semester)}
Exam: ${data.exam_name}
Date: ${fmtDate(data.exam_date)}
${reportingStr}
Room / Hall: ${data.room_no} (${data.block})
Desk Coordinates: Row ${data.row_num}, Column ${data.col_num} · Desk #${data.bench_no || data.row_num} (Seat ${data.seat_index || data.col_num})`;

    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      toast('📋 Seating pass details copied to clipboard!');
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast('⚠️ Could not copy — please try again');
    }
  };

  const getPositionLabel = (idx) => {
    if (+idx === 1) return 'Seat 1 (Left)';
    if (+idx === 2) return 'Seat 2 (Right)';
    if (+idx === 3) return 'Seat 3 (Center)';
    return `Seat ${idx}`;
  };

  return (
    <div className="seat-card rise print-area">
      <div className="seat-card-top">
        <div>
          <span className="pass-badge">✓ SEAT ALLOCATED</span>
          <h2 className="name">{data.name}</h2>
          <div className="roll">
            <span>{data.roll_no}</span>
            <span
              className="badge-dept"
              style={{ background: deptColor(data.branch), marginLeft: 10 }}
            >
              {data.branch} · Sem {data.semester}
            </span>
            {data.exam_code && (
              <span
                className="badge-dept"
                style={{ background: '#0284c7', marginLeft: 6 }}
              >
                Code: {data.exam_code}
              </span>
            )}
          </div>
        </div>
        <div className="room-big">
          <div className="num">Hall {data.room_no}</div>
          <div className="lbl">{data.block}</div>
        </div>
      </div>

      <div className="seat-card-body">
        <div className="detail-grid">
          <div className="detail-tile">
            <b>Row {data.row_num}</b>
            <span>Desk Row</span>
          </div>
          <div className="detail-tile">
            <b>Col {data.col_num}</b>
            <span>Desk Column</span>
          </div>
          <div className="detail-tile">
            <b>#{data.bench_no || data.row_num}</b>
            <span>Desk / Bench</span>
          </div>
          <div className="detail-tile">
            <b style={{ fontSize: '1.05rem', marginTop: 2 }}>{getPositionLabel(data.seat_index || data.col_num)}</b>
            <span>Bench Position</span>
          </div>
        </div>

        <div className="exam-info">
          <div className="row">
            <span className="ico">📝</span>
            <span style={{ fontWeight: 700 }}>{data.exam_name}</span>
          </div>
          <div className="row">
            <span className="ico">📄</span>
            <span>Exam Paper Code: <b>{data.exam_code || (data.branch + '-S' + data.semester)}</b> <span style={{ color: 'var(--text-muted)' }}>(Conflict-Free Interleaved)</span></span>
          </div>
          <div className="row">
            <span className="ico">📅</span>
            <span>{fmtDate(data.exam_date)}</span>
          </div>
          <div className="row">
            <span className="ico">🕤</span>
            <span>
              Report by <b>{fmtTime(data.reporting_time || data.start_time)}</b>
              {data.reporting_time && data.reporting_time !== data.start_time && (
                <span style={{ color: 'var(--text-muted)', marginLeft: 8 }}>
                  (Exam starts: {fmtTime(data.start_time)})
                </span>
              )}
            </span>
          </div>
          <div className="row">
            <span className="ico">📍</span>
            <span style={{ color: 'var(--text-muted)' }}>Venue: {data.block}, Hall {data.room_no}</span>
          </div>
        </div>

        <div className="action-row no-print">
          <button className={copied ? 'btn-ghost' : 'btn-grad'} onClick={copyDetails}>
            {copied ? '✓ Copied!' : '📋 Copy Pass'}
          </button>
          <button className="btn-ghost" onClick={() => window.print()}>
            🖨️ Print Ticket
          </button>
          <Link
            className="btn-ghost"
            to={`/room/${data.room_id}?exam=${data.exam_id}&highlight=${data.roll_no}`}
          >
            🗺️ View Floor Plan
          </Link>
        </div>
      </div>
    </div>
  );
}