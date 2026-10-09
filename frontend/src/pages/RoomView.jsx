import { useEffect, useState } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import { api } from '../api/client.js';
import RoomGrid from '../components/RoomGrid.jsx';

export default function RoomView() {
  const { roomId } = useParams();
  const [params] = useSearchParams();
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.getRoom(roomId, params.get('exam') || '', params.get('highlight') || '')
      .then((r) => setData(r.data))
      .catch((e) => setError(e.message));
  }, [roomId, params]);

  if (error) return (
    <div className="state-box section">
      <span className="emoji">😕</span><h3>Room unavailable</h3><p>{error}</p>
    </div>
  );
  if (!data) return (
    <div className="container" style={{ padding: 40 }}>
      <div className="skel" style={{ height: 420, maxWidth: 640, margin: '0 auto' }} />
    </div>
  );

  return (
    <div className="container" style={{ padding: '40px 0 60px' }}>
      {data.exam && (
        <p style={{ textAlign: 'center', color: 'var(--muted)' }}>
          📝 {data.exam.exam_name} · {data.exam.exam_date}
        </p>
      )}
      <RoomGrid room={data.room} seats={data.seats} highlightRoll={params.get('highlight')} />
    </div>
  );
}