const API_BASE = import.meta.env.VITE_API_URL || '/api';

async function request(path, options = {}) {
  const res = await fetch(`${API_BASE}/${path}`, {
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Something went wrong. Please try again.');
  return data;
}

export const api = {
  findSeat: (roll, dob, examId = null) =>
    request(`find.php?roll=${encodeURIComponent(roll)}${dob ? `&dob=${encodeURIComponent(dob)}` : ''}${examId ? `&exam_id=${examId}` : ''}`),
  getRoom: (roomId, examId, searchRoll) =>
    request(`room.php?room_id=${roomId}${examId ? `&exam_id=${examId}` : ''}${searchRoll ? `&search_roll=${encodeURIComponent(searchRoll)}` : ''}`),
  getExams: () => request('exams.php'),
};

export const DEPT_COLORS = {
  CSE: '#6366f1', ECE: '#10b981', MECH: '#f59e0b',
  CIVIL: '#ef4444', EE: '#06b6d4', IT: '#ec4899', MBA: '#8b5cf6',
  AIDS: '#8b5cf6', CYBER: '#14b8a6',
};
export const deptColor = (b) => DEPT_COLORS[(b || '').toUpperCase()] || '#64748b';