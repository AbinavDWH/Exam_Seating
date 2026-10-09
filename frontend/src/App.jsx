import { Routes, Route, NavLink } from 'react-router-dom';
import { ToastProvider } from './components/Toast.jsx';
import Home from './pages/Home.jsx';
import FindSeat from './pages/FindSeat.jsx';
import RoomView from './pages/RoomView.jsx';

const ADMIN_URL = import.meta.env.VITE_ADMIN_URL || 'http://localhost:8000/admin/login.php';

function Navbar() {
  return (
    <nav className="nav no-print">
      <div className="container nav-inner">
        <NavLink to="/" className="logo">
          <span className="logo-mark">🎓</span> ExamSeat
        </NavLink>
        <div className="nav-links">
          <NavLink to="/" end className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>Home</NavLink>
          <NavLink to="/find" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>Find Seat</NavLink>
          <a href={ADMIN_URL} className="nav-link nav-admin hide-sm">Admin</a>
        </div>
      </div>
    </nav>
  );
}

function NotFound() {
  return (
    <div className="state-box section">
      <span className="emoji">🧭</span>
      <h3>Page not found</h3>
      <p>Looks like you've wandered off the seating plan. Let's get you back.</p>
      <a href="/" className="btn-grad" style={{ display: 'inline-block' }}>← Back to Home</a>
    </div>
  );
}

export default function App() {
  return (
    <ToastProvider>
      <Navbar />
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/find" element={<FindSeat />} />
        <Route path="/room/:roomId" element={<RoomView />} />
        <Route path="*" element={<NotFound />} />
      </Routes>
      <footer className="footer no-print">
        🎓 ExamSeat — fair seating, zero stress. Built for students &amp; exam cells.
      </footer>
    </ToastProvider>
  );
}