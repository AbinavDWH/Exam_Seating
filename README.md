# 🎓 DeskMap — Conflict-Free University Exam Seating System

> Fully aligned with the **R2023 IT / REC Web Technology** syllabus covering **HTML5/CSS3 (Unit I)**, **Client-Side JavaScript (Unit II)**, **PHP & Bootstrap (Unit III)**, and **ReactJS & React Dataflow (Units IV & V)**.

---

## 🌟 Highlights & Capabilities

- **Automatic Conflict-Free Seating Engine**:
  - Eliminates cheating by ensuring **zero students taking the same exam paper sit side-by-side or front-to-back**.
  - Handles **multiple academic years** (Year 1, 2, 3, 4 / Semesters 1–8) and **multiple departments** (CSE, IT, ECE, MECH, CIVIL, AIDS) in the same exam halls.
  - Employs dynamic lookahead cohort rotation and an iterative local-search swap repair pass.
- **Student Online Seat Finder (ReactJS)**:
  - Students search by **Roll Number** (case-insensitive, e.g., `23CS101` or `23cs101`).
  - Displays instant seat details: Hall / Room Number, Block, Row, Column, Bench, Reporting Time.
  - Interactive **Room Grid Map** highlighting the student's exact desk.
  - One-click copy details & print hall ticket.
- **Admin Management Portal (PHP + Bootstrap 5)**:
  - Manage exam halls with custom row/column layouts and active/inactive toggles.
  - Bulk-assign cohorts across multiple semesters and departments.
  - Bulk student upload via CSV file.
  - One-click seating generation with real-time analytics.
  - Printable room seating charts for invigilators with signature blocks.
  - CSV export of complete seating plans.

---

## 📚 Syllabus Alignment (R2023 IT / REC)

| Unit | Syllabus Topic | Implementation in DeskMap |
|---|---|---|
| **Unit I: Web Basics, HTML & CSS** | Semantic tags, structure, CSS rules, Box model, styling, GIT | Semantic HTML5 (`<header>`, `<nav>`, `<main>`, `<section>`, `<footer>`), custom CSS variables, responsive box model, flexbox and grid layouts. |
| **Unit II: Client-Side JavaScript** | DOM manipulation, forms, event listeners, loops, focus methods | Dynamic search input, client-side validation, clipboard copy, live toast notifications, keyboard shortcuts (`Enter` to search), recent search history in `localStorage`. |
| **Unit III: Server-Side PHP & Bootstrap** | PHP principles, arrays, file handling, PDO databases, Bootstrap 5 | Pure PHP REST APIs, PDO SQLite database with foreign keys, CSV file handling for student rosters, full Admin Portal built with Bootstrap 5 grids, cards, modals, and badges. |
| **Unit IV: ReactJS Basics** | React environment, JSX, components, component API, dev tools | Modern React 18 frontend with Vite, modular functional components (`SeatCard`, `RoomGrid`, `Toast`), JSX syntax, clean component hierarchy. |
| **Unit V: React Dataflow** | State, Props, Hooks, React Router, dynamic web applications | `useState`, `useEffect`, `useCallback`, `useMemo`, `react-router-dom` navigation (`Routes`, `Route`, `NavLink`), and interactive room visualizer. |

---

## 🚀 Quick Start Guide

### Prerequisites
- Node.js (v18+)
- PHP (v8.1+) with PDO SQLite

### 1. Installation & Database Setup
```bash
# Initialize database and seed admin credentials
npm run setup
```
*(Or individually: `npm run db:init` and `npm run seed`)*

### 2. Start Application
```bash
npm run start
```
This concurrently starts:
- **PHP Backend & Admin Portal**: `http://localhost:8000`
- **React Student Portal**: `http://localhost:5173`

---

## 🔑 Demo Credentials & Test Data

### Admin Portal
- **URL**: `http://localhost:8000/admin/login.php`
- **Username**: `admin`
- **Password**: `Admin@123`

### Sample Student Register Numbers (CAT I - Slot I Dataset)
| Register No | Name | Department | Semester / Year | Hall No | Subject Code |
|---|---|---|---|---|---|
| `2116241801001` | Vishal Rajan | AI&DS | Sem 5 (3rd Year) | ANEW101 | AD23532 |
| `2116251001001` | Ishwarya Pillai | IT | Sem 3 (2nd Year) | ANEW101 | IT23331 |
| `2116250701001` | Sai Sharma | CSE | Sem 3 (2nd Year) | B321 | CS23334 |
| `2116241501001` | Gayathri Anand | AI&ML | Sem 5 (3rd Year) | B321 | AD23632 |
| `2116251401001` | Bala Balakrishnan | CSBS | Sem 3 (2nd Year) | A204 | MC23313 |
| `2116241101001` | Vasanth H. | MECH | Sem 5 (3rd Year) | B426 | ME23511 |
| `2116240901001` | Shiva G. | EEE | Sem 5 (3rd Year) | C202 | EE23531 |
*(Students can find their allocated seat using their roll number alone)*

---

## 📋 CSV Roster Upload Format

When importing students via CSV in the Admin Portal (**Students** page), the CSV file can include or omit headers. The column order is:

```csv
roll_no,name,branch,semester,exam_id,exam_code,dob
23CS101,Aarav Sharma,CSE,3,1,CS3301,2005-04-12
23EC101,Arjun Das,ECE,3,1,EC3301,2005-07-25
```

| Column | Required | Description |
|---|---|---|
| `roll_no` | **Yes** | Unique university roll number (e.g. `23CS101`). |
| `name` | **Yes** | Full student name. |
| `branch` | **Yes** | Department code (e.g. `CSE`, `ECE`, `MECH`, `IT`, `AIDS`). |
| `semester` | **Yes** | Academic semester (integer from `1` to `8`). |
| `exam_id` | *Optional* | Target examination session ID. If omitted, existing exam assignments are preserved. |
| `exam_code` | *Optional* | Specific course/paper code (e.g. `CS3301`). If omitted, defaults to `{branch}-S{semester}`. |
| `dob` | *Optional* | Date of birth (`YYYY-MM-DD`) for optional student identity verification. |

> [!TIP]
> Both headered and headerless CSV files are accepted automatically. Special spreadsheet formula prefixes (`=`, `+`, `-`, `@`) are sanitized on export to prevent CSV injection.

---

## 🕒 Reporting Times & Anti-Cheating

- **Reporting Time**: Student passes explicitly show a reporting time **30 minutes prior to exam start** (e.g., Report by 09:00 AM for a 09:30 AM exam session).
- **Alternate Empty Desks (Checkerboard Spacing)**: For single-paper batches or heavily imbalanced mixes (e.g., 90/10 split), administrators can enable alternate seating to leave neighboring seats empty, mathematically guaranteeing 0 adjacent same-paper clashes.
- **Concurrent Exam Conflict Checking**: The engine checks room bookings across exams on the same date and overlapping hours to prevent halls from being double-booked.
- **Safe Custom Halls**: Custom hall modes simulate in-memory without altering or overwriting existing halls.
- **Manual Seat Swap with Clash Detection**: Administrators can swap two students' desks (`admin/swap.php`) with instant adjacency clash simulation and warnings.

---

## 🧪 Automated Testing

Run the automated test suite covering database schema, CSRF security, rate limiting, anti-clash generation, schedule conflict checking, and CSV escaping:

```bash
npm test
```

---

## 📂 Project Architecture

```
├── admin/                      # Unit-III: Admin Portal (PHP + Bootstrap 5)
│   ├── index.php               # Admin Dashboard with KPI stats
│   ├── exams.php               # Exam sessions & multi-year assignment
│   ├── rooms.php               # Exam halls, bench rows/cols
│   ├── students.php            # Multi-dept student roster & CSV upload
│   ├── swap.php                # Manual seat swap UI with clash checking
│   ├── generate.php            # 1-Click Seating Generator & analytics
│   ├── print_plan.php          # Printable invigilator seating sheets
│   └── login.php               # Admin authentication & brute-force guard
├── api/                        # REST API Endpoints (PHP)
│   ├── find.php                # Student seat lookup (roll-based)
│   ├── exams.php               # Exam session list & system metrics
│   ├── room.php                # Privacy-preserving room grid layout
│   ├── swap.php                # Atomic seat swap API with clash detection
│   ├── generate.php            # Invokes conflict-free seating engine
│   ├── export.php              # CSV seating plan exporter with sanitization
│   └── students_upload.php     # CSV roster bulk processor
├── config/
│   ├── db.php                  # Database connection (SQLite PDO + CORS)
│   ├── auth.php                # Session authentication, CSRF, & rate limits
│   └── seating_engine.php      # Fair, conflict-free algorithm & alternate spacing
├── database/
│   ├── deskmap.sql            # Schema + realistic multi-year seed data
│   ├── init.php                # Database bootstrap script
│   └── seed_admin.php          # Admin user seeder (preserves existing passwords)
├── frontend/                   # Unit-IV & V: Student Portal (ReactJS + Vite)
│   └── src/
│       ├── App.jsx             # React Router and navigation
│       ├── pages/
│       │   ├── Home.jsx        # Landing page with active exam schedules
│       │   ├── FindSeat.jsx    # Roll number lookup with seat highlights
│       │   └── RoomView.jsx    # Dedicated room floor-plan map
│       └── components/
│           ├── SeatCard.jsx    # Seat assignment card with separate reporting time
│           ├── RoomGrid.jsx    # Interactive classroom desk visualizer
│           └── Toast.jsx       # Custom feedback notification provider
├── tests/
│   └── test_all.php            # Automated regression & security test suite
├── php.ini                     # Local PHP configuration (zero sudo required)
└── package.json                # Project automation scripts
```
