import { Routes, Route, NavLink, Link } from 'react-router-dom';
import { Compass, ArrowLeft, ArrowUpRight } from 'lucide-react';
import BrandLogo from './components/BrandLogo.jsx';
import { ToastProvider } from './components/Toast.jsx';
import Home from './pages/Home.jsx';
import FindSeat from './pages/FindSeat.jsx';
import RoomView from './pages/RoomView.jsx';

const ADMIN_URL = import.meta.env.VITE_ADMIN_URL || 'http://localhost:8000/admin/login.php';

function Navbar() {
  return (
    <nav className="nav no-print">
      <div className="container nav-inner">
        <NavLink to="/" className="logo" aria-label="ExamSeat Home">
          <span className="logo-mark">
            <BrandLogo size={20} />
          </span>{' '}
          ExamSeat
        </NavLink>
        <div className="nav-links">
          <NavLink
            to="/"
            end
            className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}
          >
            Home
          </NavLink>
          <NavLink
            to="/find"
            className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}
          >
            Find Seat
          </NavLink>
          <a
            href={ADMIN_URL}
            className="nav-link nav-admin hide-sm"
            target="_blank"
            rel="noreferrer"
          >
            <span>Admin Portal</span>
            <ArrowUpRight size={14} style={{ opacity: 0.7 }} />
          </a>
        </div>
      </div>
    </nav>
  );
}

function NotFound() {
  return (
    <div className="state-box section card-enter">
      <div className="state-box-icon">
        <Compass size={28} />
      </div>
      <h3>Page not found</h3>
      <p>Looks like you've wandered off the seating plan. Let's get you back.</p>
      <Link to="/" className="btn-solid" style={{ display: 'inline-flex' }}>
        <ArrowLeft size={16} /> Back to Home
      </Link>
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
        <div className="container footer-content">
          <div className="footer-brand">
            <div className="footer-logo">
              <span className="footer-logo-icon">
                <BrandLogo size={16} />
              </span>
              <strong>ExamSeat University Seating System</strong>
            </div>
            <p className="footer-college">
              Rajalakshmi Engineering College (Autonomous), Rajalakshmi Nagar, Thandalam, Chennai — 602 105
            </p>
          </div>
          <div className="footer-meta">
            <div className="footer-contact">
              <strong>Office of the Controller of Examinations</strong>
              <span>Helpdesk: coe@rajalakshmi.edu.in · +91 (044) 6718 1111</span>
            </div>
            <div className="footer-version">
              ExamSeat v2.4 · R2023 Conflict-Free Engine
            </div>
          </div>
        </div>
      </footer>
    </ToastProvider>
  );
}