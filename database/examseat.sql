-- ============================================================
--  ExamSeat – Complete Database Schema & Multi-Year/Dept Sample Data
-- ============================================================

-- ---------- Admins ----------
CREATE TABLE IF NOT EXISTS admins (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    username             VARCHAR(50)  UNIQUE NOT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    role                 VARCHAR(20)  DEFAULT 'admin',
    must_change_password INTEGER      NOT NULL DEFAULT 0,
    created_at           DATETIME     DEFAULT CURRENT_TIMESTAMP
);

-- ---------- Rate Limits (Persistent IP-based protection) ----------
CREATE TABLE IF NOT EXISTS rate_limits (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    ip           VARCHAR(45) NOT NULL,
    action       VARCHAR(50) NOT NULL,
    attempts     INTEGER     NOT NULL DEFAULT 1,
    last_attempt INTEGER     NOT NULL,
    locked_until INTEGER     NOT NULL DEFAULT 0,
    UNIQUE(ip, action)
);
CREATE INDEX IF NOT EXISTS idx_rate_limits_ip_action ON rate_limits(ip, action);

-- ---------- Exams ----------
CREATE TABLE IF NOT EXISTS exams (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    exam_name  VARCHAR(150) NOT NULL,
    exam_date  DATE         NOT NULL,
    start_time TIME         NOT NULL DEFAULT '09:30:00',
    end_time   TIME         NOT NULL DEFAULT '12:30:00',
    semester   INTEGER      DEFAULT 3,
    status     VARCHAR(20)  NOT NULL DEFAULT 'upcoming'
);

-- ---------- Rooms (Desks = rows_count * cols_count) ----------
CREATE TABLE IF NOT EXISTS rooms (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    room_no    VARCHAR(20) UNIQUE NOT NULL,
    block      VARCHAR(50) NOT NULL,
    capacity   INTEGER     NOT NULL,
    rows_count INTEGER     NOT NULL,
    cols_count INTEGER     NOT NULL,
    active     INTEGER     NOT NULL DEFAULT 1
);

-- ---------- Students ----------
CREATE TABLE IF NOT EXISTS students (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    roll_no   VARCHAR(20) UNIQUE NOT NULL,
    name      VARCHAR(100) NOT NULL,
    dob       DATE         DEFAULT NULL,
    branch    VARCHAR(20)  NOT NULL,
    dept      VARCHAR(20),
    semester  INTEGER      NOT NULL DEFAULT 1,
    year      INTEGER      DEFAULT 1,
    exam_code VARCHAR(50),
    exam_id   INTEGER      REFERENCES exams(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_students_roll ON students(roll_no);
CREATE INDEX IF NOT EXISTS idx_students_branch ON students(branch);
CREATE INDEX IF NOT EXISTS idx_students_exam ON students(exam_id);
CREATE INDEX IF NOT EXISTS idx_students_exam_code ON students(exam_code);

-- ---------- Student Exam Registrations (Multi-Paper / Multi-Exam link) ----------
CREATE TABLE IF NOT EXISTS student_exams (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    exam_id    INTEGER NOT NULL REFERENCES exams(id)    ON DELETE CASCADE,
    exam_code  VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (student_id, exam_id)
);
CREATE INDEX IF NOT EXISTS idx_student_exams_student ON student_exams(student_id);
CREATE INDEX IF NOT EXISTS idx_student_exams_exam ON student_exams(exam_id);
CREATE INDEX IF NOT EXISTS idx_student_exams_code ON student_exams(exam_code);

-- ---------- Seating ----------
CREATE TABLE IF NOT EXISTS seating (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    exam_id    INTEGER     NOT NULL REFERENCES exams(id)  ON DELETE CASCADE,
    room_id    INTEGER     NOT NULL REFERENCES rooms(id)  ON DELETE CASCADE,
    roll_no    VARCHAR(20) NOT NULL,
    student_id INTEGER     REFERENCES students(id)        ON DELETE CASCADE,
    exam_code  VARCHAR(50),
    row_num    INTEGER     NOT NULL,
    col_num    INTEGER     NOT NULL,
    bench_no   INTEGER     NOT NULL,
    seat_index INTEGER     NOT NULL,
    UNIQUE (exam_id, roll_no),
    UNIQUE (exam_id, room_id, row_num, col_num)
);
CREATE INDEX IF NOT EXISTS idx_seating_exam ON seating(exam_id);
CREATE INDEX IF NOT EXISTS idx_seating_room ON seating(room_id);
CREATE INDEX IF NOT EXISTS idx_seating_roll ON seating(roll_no);
CREATE INDEX IF NOT EXISTS idx_seating_exam_code ON seating(exam_code);

-- ============================================================
--  SAMPLE DATA: Multi-Year & Multi-Department
-- ============================================================

-- Rooms
INSERT INTO rooms (room_no, block, capacity, rows_count, cols_count, active) VALUES
    ('101', 'Block A - Ground Floor', 40, 5, 8, 1),
    ('102', 'Block A - Ground Floor', 30, 5, 6, 1),
    ('201', 'Block B - First Floor',  24, 4, 6, 1),
    ('301', 'Block C - Second Floor', 20, 4, 5, 1);

-- Exams (One multi-year major exam session and one senior exam session)
INSERT INTO exams (id, exam_name, exam_date, start_time, end_time, semester, status) VALUES
    (1, 'University Semester Examinations – Oct 2026', '2026-10-20', '09:30:00', '12:30:00', 3, 'upcoming'),
    (2, 'Internal Assessment Test II – Nov 2026',       '2026-11-15', '09:30:00', '12:30:00', 7, 'upcoming');

-- Multi-Year & Multi-Department Students:
-- Year 2 (Semester 3): CSE, ECE, MECH, CIVIL
-- Year 3 (Semester 5): CSE, IT, AIDS
-- Year 4 (Semester 7): ECE, IT

INSERT INTO students (roll_no, name, dob, branch, dept, semester, year, exam_code, exam_id) VALUES
    -- 2nd Year (Semester 3) - CSE (Exam Code: CS3301 - Data Structures)
    ('23CS101', 'Aarav Sharma',     '2005-04-12', 'CSE',   'CSE',   3, 2, 'CS3301', 1),
    ('23CS102', 'Diya Patel',       '2005-08-23', 'CSE',   'CSE',   3, 2, 'CS3301', 1),
    ('23CS103', 'Rohan Mehta',      '2005-01-15', 'CSE',   'CSE',   3, 2, 'CS3301', 1),
    ('23CS104', 'Ananya Iyer',      '2005-11-30', 'CSE',   'CSE',   3, 2, 'CS3301', 1),
    ('23CS105', 'Vivaan Reddy',     '2005-03-09', 'CSE',   'CSE',   3, 2, 'CS3301', 1),
    ('23CS106', 'Ishita Nair',      '2005-07-19', 'CSE',   'CSE',   3, 2, 'CS3301', 1),
    ('23CS107', 'Kabir Singh',      '2005-09-05', 'CSE',   'CSE',   3, 2, 'CS3301', 1),
    ('23CS108', 'Meera Joshi',      '2005-12-14', 'CSE',   'CSE',   3, 2, 'CS3301', 1),

    -- 2nd Year (Semester 3) - ECE (Exam Code: EC3301 - Signals & Systems)
    ('23EC101', 'Arjun Das',        '2005-02-18', 'ECE',   'ECE',   3, 2, 'EC3301', 1),
    ('23EC102', 'Priya Menon',      '2005-06-25', 'ECE',   'ECE',   3, 2, 'EC3301', 1),
    ('23EC103', 'Karan Verma',      '2005-10-11', 'ECE',   'ECE',   3, 2, 'EC3301', 1),
    ('23EC104', 'Nisha Gupta',      '2005-05-04', 'ECE',   'ECE',   3, 2, 'EC3301', 1),
    ('23EC105', 'Dev Patel',        '2005-09-29', 'ECE',   'ECE',   3, 2, 'EC3301', 1),
    ('23EC106', 'Riya Chawla',      '2005-12-08', 'ECE',   'ECE',   3, 2, 'EC3301', 1),
    ('23EC107', 'Sneha Rao',        '2005-07-16', 'ECE',   'ECE',   3, 2, 'EC3301', 1),
    ('23EC108', 'Manav Shah',       '2005-03-21', 'ECE',   'ECE',   3, 2, 'EC3301', 1),

    -- 2nd Year (Semester 3) - MECH (Exam Code: ME3301 - Thermodynamics)
    ('23ME101', 'Vikram Pillai',    '2005-01-28', 'MECH',  'MECH',  3, 2, 'ME3301', 1),
    ('23ME102', 'Tanya Bose',       '2005-04-03', 'MECH',  'MECH',  3, 2, 'ME3301', 1),
    ('23ME103', 'Rahul Dixit',      '2005-08-17', 'MECH',  'MECH',  3, 2, 'ME3301', 1),
    ('23ME104', 'Pooja Hegde',      '2005-11-22', 'MECH',  'MECH',  3, 2, 'ME3301', 1),
    ('23ME105', 'Sameer Ali',       '2005-02-14', 'MECH',  'MECH',  3, 2, 'ME3301', 1),
    ('23ME106', 'Kavya Nair',       '2005-06-30', 'MECH',  'MECH',  3, 2, 'ME3301', 1),
    ('23ME107', 'Nikhil Jain',      '2005-09-12', 'MECH',  'MECH',  3, 2, 'ME3301', 1),
    ('23ME108', 'Aisha Sheikh',     '2005-12-01', 'MECH',  'MECH',  3, 2, 'ME3301', 1),

    -- 2nd Year (Semester 3) - CIVIL (Exam Code: CE3301 - Fluid Mechanics)
    ('23CE101', 'Harsh Vardhan',    '2005-03-15', 'CIVIL', 'CIVIL', 3, 2, 'CE3301', 1),
    ('23CE102', 'Lakshmi Priya',    '2005-07-27', 'CIVIL', 'CIVIL', 3, 2, 'CE3301', 1),
    ('23CE103', 'Gaurav Mishra',    '2005-10-09', 'CIVIL', 'CIVIL', 3, 2, 'CE3301', 1),
    ('23CE104', 'Divya Kapoor',     '2005-01-05', 'CIVIL', 'CIVIL', 3, 2, 'CE3301', 1),
    ('23CE105', 'Amit Rathi',       '2005-05-19', 'CIVIL', 'CIVIL', 3, 2, 'CE3301', 1),
    ('23CE106', 'Fatima Baig',      '2005-08-31', 'CIVIL', 'CIVIL', 3, 2, 'CE3301', 1),

    -- 3rd Year (Semester 5) - CSE (Exam Code: CS3501 - Database Systems)
    ('22CS201', 'Siddharth Roy',    '2004-02-11', 'CSE',   'CSE',   5, 3, 'CS3501', 1),
    ('22CS202', 'Trisha Das',       '2004-06-18', 'CSE',   'CSE',   5, 3, 'CS3501', 1),
    ('22CS203', 'Aditya Sen',       '2004-09-24', 'CSE',   'CSE',   5, 3, 'CS3501', 1),
    ('22CS204', 'Sanya Agarwal',    '2004-12-05', 'CSE',   'CSE',   5, 3, 'CS3501', 1),
    ('22CS205', 'Pranav Menon',     '2004-03-30', 'CSE',   'CSE',   5, 3, 'CS3501', 1),
    ('22CS206', 'Lavanya Natarajan','2004-07-14', 'CSE',   'CSE',   5, 3, 'CS3501', 1),

    -- 3rd Year (Semester 5) - IT (Exam Code: IT3501 - Web Technology)
    ('22IT101', 'Bhavya Krishna',   '2004-01-22', 'IT',    'IT',    5, 3, 'IT3501', 1),
    ('22IT102', 'Chetan Bhagat',    '2004-05-16', 'IT',    'IT',    5, 3, 'IT3501', 1),
    ('22IT103', 'Deepa Venkat',     '2004-08-08', 'IT',    'IT',    5, 3, 'IT3501', 1),
    ('22IT104', 'Gautam Gambhir',   '2004-11-19', 'IT',    'IT',    5, 3, 'IT3501', 1),
    ('22IT105', 'Hansika Motwani',  '2004-04-02', 'IT',    'IT',    5, 3, 'IT3501', 1),
    ('22IT106', 'Jitendra Kumar',   '2004-07-29', 'IT',    'IT',    5, 3, 'IT3501', 1),

    -- 3rd Year (Semester 5) - AIDS (Exam Code: AD3501 - Machine Learning)
    ('22AD101', 'Kunal Ghosh',      '2004-03-08', 'AIDS',  'AIDS',  5, 3, 'AD3501', 1),
    ('22AD102', 'Monika Bellucci',  '2004-06-12', 'AIDS',  'AIDS',  5, 3, 'AD3501', 1),
    ('22AD103', 'Naveen Polishetty','2004-09-01', 'AIDS',  'AIDS',  5, 3, 'AD3501', 1),
    ('22AD104', 'Omkar Kapoor',     '2004-12-20', 'AIDS',  'AIDS',  5, 3, 'AD3501', 1),
    ('22AD105', 'Payal Rajput',     '2004-02-27', 'AIDS',  'AIDS',  5, 3, 'AD3501', 1),
    ('22AD106', 'Rishabh Pant',     '2004-05-15', 'AIDS',  'AIDS',  5, 3, 'AD3501', 1),

    -- 4th Year (Semester 7) - ECE (Exam Code: EC3701 - VLSI Design)
    ('21EC301', 'Abhishek Bachchan','2003-02-05', 'ECE',   'ECE',   7, 4, 'EC3701', 2),
    ('21EC302', 'Bindu Madhavi',    '2003-06-14', 'ECE',   'ECE',   7, 4, 'EC3701', 2),
    ('21EC303', 'Chirag Paswan',    '2003-10-31', 'ECE',   'ECE',   7, 4, 'EC3701', 2),
    ('21EC304', 'Dhanush Kumar',    '2003-01-28', 'ECE',   'ECE',   7, 4, 'EC3701', 2),

    -- 4th Year (Semester 7) - IT (Exam Code: IT3701 - Cloud Computing)
    ('21IT201', 'Esha Deol',        '2003-03-19', 'IT',    'IT',    7, 4, 'IT3701', 2),
    ('21IT202', 'Farhan Akhtar',    '2003-07-22', 'IT',    'IT',    7, 4, 'IT3701', 2),
    ('21IT203', 'Genelia DSouza',   '2003-09-17', 'IT',    'IT',    7, 4, 'IT3701', 2),
    ('21IT204', 'Himesh Reshammiya','2003-12-10', 'IT',    'IT',    7, 4, 'IT3701', 2);

-- Populate Student Exam Registrations from initial assignments
INSERT OR IGNORE INTO student_exams (student_id, exam_id, exam_code)
SELECT id, exam_id, exam_code FROM students WHERE exam_id IS NOT NULL;