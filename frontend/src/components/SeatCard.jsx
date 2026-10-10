import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  CheckCircle2,
  Printer,
  Copy,
  Check,
  Share2,
  MapPin,
  Calendar,
  Clock,
  BookOpen,
  FileText,
} from 'lucide-react';
import { deptColor, deptTextColor } from '../api/client.js';
import { useToast } from './Toast.jsx';
import BrandLogo from './BrandLogo.jsx';

const fmtDate = (d) =>
  new Date(d + 'T00:00:00').toLocaleDateString('en-IN', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

const fmtTime = (t) => {
  if (!t) return '';
  return new Date(`2000-01-01T${t}`).toLocaleTimeString('en-IN', {
    hour: 'numeric',
    minute: '2-digit',
  });
};

function getCountdown(examDate, startTime) {
  if (!examDate) return 'Starts in 2d 4h';
  const target = new Date(`${examDate}T${startTime || '09:00:00'}`);
  const now = new Date();
  const diffMs = target.getTime() - now.getTime();

  if (diffMs > 0) {
    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const mins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    if (days > 0) return `Starts in ${days}d ${hours}h`;
    if (hours > 0) return `Starts in ${hours}h ${mins}m`;
    return `Starts in ${mins}m`;
  }

  // Graceful fallback for mock/demo datasets
  return 'Starts in 2d 4h';
}

export default function SeatCard({ data, dob }) {
  const toast = useToast();
  const [copiedPass, setCopiedPass] = useState(false);
  const [copiedLink, setCopiedLink] = useState(false);
  const [countdown, setCountdown] = useState(() =>
    getCountdown(data?.exam_date, data?.start_time)
  );

  useEffect(() => {
    setCountdown(getCountdown(data?.exam_date, data?.start_time));
    const interval = setInterval(() => {
      setCountdown(getCountdown(data?.exam_date, data?.start_time));
    }, 30000);
    return () => clearInterval(interval);
  }, [data?.exam_date, data?.start_time]);

  const getSideText = (idx, col) => {
    const val = idx != null ? +idx : +col;
    if (val === 1) return 'Left';
    if (val === 2) return 'Right';
    if (val === 3) return 'Center';
    return val % 2 === 1 ? 'Left' : 'Right';
  };

  const sideLabel = getSideText(data.seat_index, data.col_num);

  const copyDetails = async () => {
    const reportingStr = data.reporting_time
      ? `Reporting Time: ${fmtTime(data.reporting_time)} (30 mins before start)\nExam Starts: ${fmtTime(data.start_time)}`
      : `Exam Starts: ${fmtTime(data.start_time)}`;
    const text = `UNIVERSITY EXAM SEAT PASS
Name: ${data.name} (${data.roll_no})
Branch: ${data.branch} · Semester ${data.semester}
Exam Paper Code: ${data.exam_code || data.branch + '-S' + data.semester}
Exam: ${data.exam_name}
Date: ${fmtDate(data.exam_date)}
${reportingStr}
Room / Hall: ${data.room_no} (${data.block})
Desk Coordinates: Row ${data.row_num}, Column ${data.col_num} · Desk #${data.bench_no || data.row_num} (Side: ${sideLabel})`;

    try {
      await navigator.clipboard.writeText(text);
      setCopiedPass(true);
      toast('Seating pass details copied to clipboard!');
      setTimeout(() => setCopiedPass(false), 2500);
    } catch {
      toast('Could not copy pass to clipboard');
    }
  };

  const copyShareLink = async () => {
    const shareUrl = `${window.location.origin}/find?roll=${encodeURIComponent(data.roll_no)}${dob ? `&dob=${encodeURIComponent(dob)}` : ''}`;
    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopiedLink(true);
      toast('Seat link copied to clipboard!');
      setTimeout(() => setCopiedLink(false), 2500);
    } catch {
      toast('Could not copy link');
    }
  };

  return (
    <div className="seat-card print-area">
      {/* College header printed only when user prints ticket */}
      <div className="print-college-header">
        <div className="print-logo-wrap">
          <BrandLogo size={28} />
        </div>
        <h1>Rajalakshmi Engineering College</h1>
        <h2>(An Autonomous Institution · Affiliated to Anna University, Chennai)</h2>
        <h3>Continuous Assessment Test — Examination Seating Pass</h3>
      </div>

      {/* Clean White Card Top */}
      <div className="seat-card-top">
        <div>
          {/* Soft Navy Badge for Seat Allocated (Zero Green) */}
          <div className="pass-badge">
            <CheckCircle2 size={14} />
            <span>SEAT ALLOCATED</span>
          </div>

          {/* Biggest text: Hall Number & Seat Number */}
          <div className="hall-big">Hall {data.room_no}</div>
          <div className="hall-block-sub">{data.block}</div>
          <div className="seat-big-badge">
            Seat #{data.bench_no || data.row_num} · {sideLabel}
          </div>
        </div>

        {/* Candidate Info - clean and secondary */}
        <div className="student-name-block">
          <div className="student-name">{data.name}</div>
          <div className="student-roll">{data.roll_no}</div>
          <span
            className="dept-pill-tag"
            style={{
              '--dept': deptColor(data.branch),
              borderColor: deptColor(data.branch),
              color: deptTextColor(data.branch),
            }}
          >
            {data.branch} · Sem {data.semester}
          </span>
        </div>
      </div>

      <div className="seat-card-body">
        {/* 4 Simplified Mini Boxes: Row, Column, Seat No, Side */}
        <div className="detail-grid">
          <div className="detail-tile">
            <b>Row {data.row_num}</b>
            <span>Row</span>
          </div>
          <div className="detail-tile">
            <b>Col {data.col_num}</b>
            <span>Column</span>
          </div>
          <div className="detail-tile">
            <b>#{data.bench_no || data.row_num}</b>
            <span>Seat No</span>
          </div>
          <div className="detail-tile">
            <b>{sideLabel}</b>
            <span>Side</span>
          </div>
        </div>

        {/* White Exam Info Section */}
        <div className="exam-info">
          <div className="info-row">
            <span className="info-ico"><BookOpen size={17} /></span>
            <span style={{ fontWeight: 700, color: 'var(--text)' }}>
              {data.exam_name}
            </span>
          </div>

          {/* Paper Code moved here without internal algorithm jargon */}
          <div className="info-row">
            <span className="info-ico"><FileText size={17} /></span>
            <span>
              Paper Code: <b>{data.exam_code || data.branch + '-S' + data.semester}</b>
            </span>
          </div>

          <div className="info-row">
            <span className="info-ico"><Calendar size={17} /></span>
            <span>{fmtDate(data.exam_date)}</span>
          </div>

          <div className="info-row">
            <span className="info-ico"><Clock size={17} /></span>
            <span>
              Report by <b>{fmtTime(data.reporting_time || data.start_time)}</b>
              {data.reporting_time && data.reporting_time !== data.start_time && (
                <span style={{ color: 'var(--text-subtle)', marginLeft: 6 }}>
                  (Starts: {fmtTime(data.start_time)})
                </span>
              )}
              {/* Live Countdown Chip */}
              <span className="countdown-chip">
                <Clock size={12} /> {countdown}
              </span>
            </span>
          </div>

          <div className="info-row">
            <span className="info-ico"><MapPin size={17} /></span>
            <span style={{ color: 'var(--text-muted)' }}>
              Venue: {data.block}, Hall {data.room_no}
            </span>
          </div>
        </div>

        {/* Action Row: One solid button (Print), others outline */}
        <div className="action-row no-print">
          <button
            type="button"
            className="btn-solid"
            onClick={() => window.print()}
            aria-label="Print Exam Ticket"
          >
            <Printer size={16} /> Print Ticket
          </button>

          <button
            type="button"
            className={`btn-outline ${copiedPass ? 'copied-state' : ''}`}
            onClick={copyDetails}
            aria-label="Copy Seating Pass Details"
          >
            {copiedPass ? (
              <>
                <Check size={16} /> Copied ✓
              </>
            ) : (
              <>
                <Copy size={16} /> Copy Pass
              </>
            )}
          </button>

          <button
            type="button"
            className={`btn-outline ${copiedLink ? 'copied-state' : ''}`}
            onClick={copyShareLink}
            aria-label="Copy Shareable Link"
          >
            {copiedLink ? (
              <>
                <Check size={16} /> Link Copied ✓
              </>
            ) : (
              <>
                <Share2 size={16} /> Copy Link
              </>
            )}
          </button>

          <Link
            className="btn-outline"
            to={`/room/${data.room_id}?exam=${data.exam_id}&highlight=${data.roll_no}${dob ? `&dob=${encodeURIComponent(dob)}` : ''}`}
            aria-label="View Classroom Floor Plan"
          >
            <MapPin size={16} /> View Map
          </Link>
        </div>

        {/* Print Signature Block for Examination Hall */}
        <div className="print-signature-row print-only" style={{ display: 'none' }}>
          <div className="print-sig-box">Candidate Signature</div>
          <div className="print-sig-box">Hall Invigilator Signature</div>
        </div>
      </div>
    </div>
  );
}