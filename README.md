# 🎓 ExamSeat — Conflict-Free University Exam Seating System

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

| Unit | Syllabus Topic | Implementation in ExamSeat |
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

### Sample Student Roll Numbers (for Student Seat Finder)
| Roll Number | Name | Department | Semester / Year |
|---|---|---|---|
| `23CS101` | Aarav Sharma | CSE | Sem 3 (2nd Year) |
| `23EC101` | Arjun Das | ECE | Sem 3 (2nd Year) |
| `23ME101` | Vikram Pillai | MECH | Sem 3 (2nd Year) |
| `23CE101` | Harsh Vardhan | CIVIL | Sem 3 (2nd Year) |
| `22CS201` | Siddharth Roy | CSE | Sem 5 (3rd Year) |
| `22IT101` | Bhavya Krishna | IT | Sem 5 (3rd Year) |
| `22AD101` | Kunal Ghosh | AIDS | Sem 5 (3rd Year) |
| `21EC301` | Abhishek Bachchan | ECE | Sem 7 (4th Year) |

---

## 📂 Project Architecture

```
WP/
├── admin/                      # Unit-III: Admin Portal (PHP + Bootstrap 5)
│   ├── index.php               # Admin Dashboard with KPI stats
│   ├── exams.php               # Exam sessions & multi-year assignment
│   ├── rooms.php               # Exam halls, bench rows/cols
│   ├── students.php            # Multi-dept student roster & CSV upload
│   ├── generate.php            # 1-Click Seating Generator & analytics
│   ├── print_plan.php          # Printable invigilator seating sheets
│   └── login.php               # Admin authentication
├── api/                        # REST API Endpoints (PHP)
│   ├── find.php                # Student seat lookup by roll number
│   ├── exams.php               # Exam session list & system metrics
│   ├── room.php                # Room grid layout & seat occupancy
│   ├── generate.php            # Invokes conflict-free seating engine
│   ├── export.php              # CSV seating plan exporter
│   └── students_upload.php     # CSV roster bulk processor
├── config/
│   ├── db.php                  # Database connection (SQLite PDO + CORS)
│   ├── auth.php                # Session authentication guards
│   └── seating_engine.php      # Fair, conflict-free algorithm
├── database/
│   ├── examseat.sql            # Schema + realistic multi-year seed data
│   ├── init.php                # Database bootstrap script
│   └── seed_admin.php          # Admin user seeder
├── frontend/                   # Unit-IV & V: Student Portal (ReactJS + Vite)
│   └── src/
│       ├── App.jsx             # React Router and navigation
│       ├── pages/
│       │   ├── Home.jsx        # Landing page with active exam schedules
│       │   ├── FindSeat.jsx    # Roll number lookup with seat highlights
│       │   └── RoomView.jsx    # Dedicated room floor-plan map
│       └── components/
│           ├── SeatCard.jsx    # Seat assignment card & ticket printer
│           ├── RoomGrid.jsx    # Interactive classroom desk visualizer
│           └── Toast.jsx       # Custom feedback notification provider
├── php.ini                     # Local PHP configuration (zero sudo required)
└── package.json                # Project automation scripts
```
