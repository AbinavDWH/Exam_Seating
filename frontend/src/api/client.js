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
  findSeat: (roll, examId = null) =>
    request(`find.php?roll=${encodeURIComponent(roll)}${examId ? `&exam_id=${examId}` : ''}`),
  getRoom: (roomId, examId, searchRoll) =>
    request(`room.php?room_id=${roomId}${examId ? `&exam_id=${examId}` : ''}${searchRoll ? `&search_roll=${encodeURIComponent(searchRoll)}` : ''}`),
  getExams: () => request('exams.php'),
};

export const DEPT_PALETTE = ['#8B9A6E', '#C4704B', '#C99A2E', '#5E7A99', '#8A6E8B'];

export const DEPT_COLORS = {
  // Sage (#8B9A6E)
  'AI&DS': '#8B9A6E',
  'AIDS': '#8B9A6E',
  'AI&ML': '#8B9A6E',
  'AIML': '#8B9A6E',
  'EEE': '#8B9A6E',
  'EE': '#8B9A6E',

  // Terracotta (#C4704B)
  'CSE': '#C4704B',
  'MECH': '#C4704B',
  'AERO': '#C4704B',
  'AUTO': '#C4704B',

  // Mustard (#C99A2E)
  'ECE': '#C99A2E',
  'CIVIL': '#C99A2E',
  'FT': '#C99A2E',
  'CHEM': '#C99A2E',

  // Muted Slate Blue (#5E7A99)
  'IT': '#5E7A99',
  'MCT': '#5E7A99',
  'R&A': '#5E7A99',
  'RA': '#5E7A99',
  'CYBER': '#5E7A99',
  'MBA': '#5E7A99',

  // Plum (#8A6E8B)
  'CSBS': '#8A6E8B',
  'BT': '#8A6E8B',
  'BME': '#8A6E8B',
  'CSD': '#8A6E8B',
};

export const DEPT_TEXT_COLORS = {
  '#8B9A6E': '#3E4A28', // Dark Sage Ink
  '#C4704B': '#70381D', // Dark Terracotta Ink
  '#C99A2E': '#6B4E0E', // Dark Mustard Ink
  '#5E7A99': '#27384B', // Dark Slate Blue Ink
  '#8A6E8B': '#4D384E', // Dark Plum Ink
};

export const deptColor = (b) => {
  const norm = (b || '').toUpperCase().trim();
  if (DEPT_COLORS[norm]) return DEPT_COLORS[norm];
  if (!norm) return DEPT_PALETTE[0];
  let hash = 0;
  for (let i = 0; i < norm.length; i++) hash = (hash * 31 + norm.charCodeAt(i)) >>> 0;
  return DEPT_PALETTE[hash % DEPT_PALETTE.length];
};

export const deptTextColor = (b) => {
  const c = deptColor(b);
  return DEPT_TEXT_COLORS[c] || '#2B2E27';
};

export const shortDept = (b) => {
  const norm = (b || '').toUpperCase().trim();
  if (norm.includes('AI') && norm.includes('DS')) return 'AI';
  if (norm.includes('AI') && norm.includes('ML')) return 'ML';
  if (norm.includes('CSBS')) return 'CB';
  if (norm.includes('CSE')) return 'CS';
  if (norm.includes('IT')) return 'IT';
  if (norm.includes('ECE')) return 'EC';
  if (norm.includes('EEE')) return 'EE';
  if (norm.includes('MECH')) return 'ME';
  if (norm.includes('CIVIL')) return 'CE';
  if (norm.includes('AERO')) return 'AE';
  if (norm.includes('BME')) return 'BM';
  if (norm.includes('AUTO')) return 'AU';
  return norm.slice(0, 2);
};