import { Routes, Route, NavLink, Link } from 'react-router-dom';
import { Compass, ArrowLeft } from 'lucide-react';
import BrandLogo from './components/BrandLogo.jsx';
import { ToastProvider } from './components/Toast.jsx';
import StudentPortal from './pages/StudentPortal.jsx';
import RoomView from './pages/RoomView.jsx';

const ADMIN_URL = import.meta.env.VITE_ADMIN_URL || 'http://localhost:8000/admin/login.php';

function Navbar() {
  return (
    <header className="nav no-print">
      <div className="container nav-inner">
        <NavLink to="/" className="logo" aria-label="DeskMap Home">
          <BrandLogo variant="mark" size={32} />
          <span className="brand-name-text">DeskMap</span>
        </NavLink>
        <nav className="nav-links">
          <NavLink
            to="/"
            end
            className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}
          >
            Find seat
          </NavLink>
          <a
            href={ADMIN_URL}
            className="nav-link nav-admin hide-sm"
            target="_blank"
            rel="noreferrer"
          >
            Admin portal
          </a>
        </nav>
      </div>
    </header>
  );
}

function NotFound() {
  return (
    <div className="state-box section">
      <div className="state-box-icon">
        <Compass size={28} />
      </div>
      <h3>Page not found</h3>
      <p>Looks like you have wandered off the seating map. Return to find your seat.</p>
      <Link to="/" className="btn-solid" style={{ display: 'inline-flex' }}>
        <ArrowLeft size={16} /> Return to find seat
      </Link>
    </div>
  );
}

export default function App() {
  return (
    <ToastProvider>
      <Navbar />
      <Routes>
        <Route path="/" element={<StudentPortal />} />
        <Route path="/find" element={<StudentPortal />} />
        <Route path="/room/:roomId" element={<RoomView />} />
        <Route path="*" element={<NotFound />} />
      </Routes>
      <footer className="footer no-print">
        <div className="container footer-content">
          <div className="footer-brand">
            <div className="footer-logo">
              <BrandLogo variant="mark" size={24} />
              <strong>DeskMap University Seating System</strong>
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
          </div>
        </div>
      </footer>
    </ToastProvider>
  );
}