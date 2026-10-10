import { useEffect, useState } from 'react';
import { useParams, useSearchParams, Link } from 'react-router-dom';
import { AlertCircle, BookOpen, ArrowLeft } from 'lucide-react';
import { api } from '../api/client.js';
import RoomGrid from '../components/RoomGrid.jsx';

export default function RoomView() {
  const { roomId } = useParams();
  const [params] = useSearchParams();
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  const highlightRoll = params.get('highlight') || '';

  useEffect(() => {
    api
      .getRoom(roomId, params.get('exam') || '', highlightRoll)
      .then((r) => setData(r.data))
      .catch((e) => setError(e.message));
  }, [roomId, params, highlightRoll]);

  if (error) {
    return (
      <div className="container" style={{ padding: '60px 0' }}>
        <div className="state-box">
          <div className="state-box-icon error">
            <AlertCircle size={26} />
          </div>
          <h3>Room layout unavailable</h3>
          <p>{error}</p>
          <Link to="/find" className="btn-solid" style={{ display: 'inline-flex' }}>
            <ArrowLeft size={16} /> Return to Seat Finder
          </Link>
        </div>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="container" style={{ padding: '40px 0' }}>
        <div className="skel" style={{ height: 440, maxWidth: 760, margin: '0 auto' }} />
      </div>
    );
  }

  return (
    <div className="container" style={{ padding: '36px 0 60px' }}>
      <div style={{ maxWidth: 760, margin: '0 auto 16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }} className="no-print">
        <Link
          to={highlightRoll ? `/find?roll=${encodeURIComponent(highlightRoll)}` : '/find'}
          className="btn-outline"
          style={{ height: 38, fontSize: '0.85rem' }}
        >
          <ArrowLeft size={15} /> Back to Admit Card
        </Link>
        {data.exam && (
          <div
            style={{
              fontSize: '0.85rem',
              color: 'var(--text-muted)',
              display: 'flex',
              alignItems: 'center',
              gap: 6,
            }}
          >
            <BookOpen size={15} />
            <span>{data.exam.exam_name} · {data.exam.exam_date}</span>
          </div>
        )}
      </div>

      <RoomGrid room={data.room} seats={data.seats} highlightRoll={highlightRoll} />
    </div>
  );
}