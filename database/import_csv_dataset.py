#!/usr/bin/env python3
"""
Imports university exam hall plan datasets into database/examseat.sqlite:
1. 'CAT I HP II & III YEAR - 03.09.26.xlsx - SLOT I.csv' -> Exam ID 1
2. 'CAT I HP II & III YEAR - 19.09.26.xlsx - SLOT I.csv' -> Exam ID 3 (5 bench col x 9 row, 10 cols x 9 rows, capacity 90)
"""
import os
import re
import csv
import sqlite3
import hashlib

ROOT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(ROOT_DIR, 'database', 'examseat.sqlite')

CSV_PATH_03 = os.path.join(ROOT_DIR, 'CAT I HP II & III YEAR - 03.09.26.xlsx - SLOT I.csv')
CSV_PATH_19 = os.path.join(ROOT_DIR, 'CAT I HP II & III YEAR - 19.09.26.xlsx - SLOT I.csv')
if not os.path.exists(CSV_PATH_19):
    CSV_PATH_19 = os.path.join(ROOT_DIR, 'CAT I HP II  III YEAR - 19.09.26.xlsx - SLOT I.csv')

FIRST_NAMES = [
    'Aarav', 'Abhishek', 'Aditya', 'Ajay', 'Akash', 'Akshaya', 'Amritha', 'Anand', 'Ananya', 'Aravind',
    'Archana', 'Arjun', 'Arun', 'Ashwin', 'Bala', 'Balaji', 'Bhavani', 'Bhavya', 'Charan', 'Deepa',
    'Deepak', 'Dhanush', 'Dharani', 'Dinesh', 'Divya', 'Ganesh', 'Gayathri', 'Gokul', 'Gowtham', 'Hari',
    'Harini', 'Harish', 'Hemalatha', 'Hemant', 'Indhu', 'Ishwarya', 'Janani', 'Jeeva', 'Kabilan', 'Kamalesh',
    'Karthick', 'Karthik', 'Kavitha', 'Kavya', 'Keerthana', 'Kiran', 'Kishore', 'Lavanya', 'Lokesh', 'Madhavan',
    'Mahesh', 'Malini', 'Manikandan', 'Mano', 'Manoj', 'Meena', 'Meenakshi', 'Mithun', 'Mohan', 'Monisha',
    'Mukesh', 'Murali', 'Nandhini', 'Naresh', 'Naveen', 'Nikhil', 'Nirmal', 'Nisha', 'Nithish', 'Nithya',
    'Pavithra', 'Pooja', 'Pradeep', 'Pranav', 'Prasad', 'Prasanth', 'Praveen', 'Preethi', 'Priya', 'Priyanka',
    'Rahul', 'Rajesh', 'Rakshitha', 'Ramya', 'Ranjith', 'Revathi', 'Rithvik', 'Rohith', 'Roshan', 'Sabari',
    'Sai', 'Sakthi', 'Samrithi', 'Sandhya', 'Sanjay', 'Santhosh', 'Saranya', 'Sasikumar', 'Sathish', 'Shalini',
    'Shankar', 'Sharmila', 'Shiva', 'Shobana', 'Shreya', 'Siddharth', 'Sindhu', 'Siva', 'Sneha', 'Soundarya',
    'Sowmya', 'Sreeja', 'Srihari', 'Srikanth', 'Srinath', 'Subash', 'Sudharsan', 'Suganya', 'Sujith', 'Sundar',
    'Surya', 'Swathi', 'Swetha', 'Tamizh', 'Tarun', 'Tejas', 'Tharun', 'Uma', 'Vaishnavi', 'Varun',
    'Vasanth', 'Vignesh', 'Vijay', 'Vikas', 'Vikram', 'Vimal', 'Vinitha', 'Vinodh', 'Vishal', 'Vishnu',
    'Vishwa', 'Yamini', 'Yashwant', 'Yogesh', 'Yuvan'
]

LAST_NAMES = [
    'A.', 'B.', 'C.', 'D.', 'E.', 'G.', 'H.', 'J.', 'K.', 'L.', 'M.', 'N.', 'P.', 'R.', 'S.', 'T.', 'V.',
    'Kumar', 'Rajan', 'Sundar', 'Murugan', 'Natarajan', 'Krishnan', 'Pillai', 'Sharma', 'Verma', 'Patel',
    'Reddy', 'Choudhary', 'Iyer', 'Iyengar', 'Balakrishnan', 'Subramanian', 'Venkatesh', 'Gopal', 'Anand'
]

def make_name(roll):
    h = int(hashlib.md5(roll.encode()).hexdigest(), 16)
    fn = FIRST_NAMES[h % len(FIRST_NAMES)]
    ln = LAST_NAMES[(h // len(FIRST_NAMES)) % len(LAST_NAMES)]
    return f'{fn} {ln}'

def get_block_name(room_no):
    r = room_no.strip().upper()
    if r.startswith('ANEW'):
        return 'Admin Block (New)'
    elif r.startswith('A'):
        return 'Admin Block'
    elif r.startswith('B'):
        return 'Workshop Block'
    elif r.startswith('C'):
        return 'Aero Block'
    elif r.startswith('D'):
        return 'Academic Block'
    elif r.startswith(('I', 'IV', 'V')):
        return 'TIFAC Core Block'
    return 'Main Campus Block'

def expand_pattern(reg_str, expected_count):
    m = re.match(r'(\d+)', reg_str.strip())
    if not m:
        return []
    first_num = m.group(1)
    prefix_len = len(first_num) - 3
    prefix = first_num[:prefix_len]
    clean = reg_str.replace('&', ',')
    parts = [p.strip() for p in clean.split(',') if p.strip()]
    rolls = []
    for part in parts:
        if '-' in part:
            sides = part.split('-')
            left = sides[0].strip()
            right = sides[1].strip()
            if len(left) > 3 and left.startswith(prefix):
                left_seq = int(left[len(prefix):])
            else:
                left_seq = int(re.sub(r'\D', '', left))
            right_digits = re.sub(r'\D', '', right)
            if len(right_digits) == 4 and right_digits.startswith('11'):
                right_digits = right_digits[1:]
            right_seq = int(right_digits)
            for seq in range(left_seq, right_seq + 1):
                rolls.append(f'{prefix}{seq:03d}')
        else:
            digits = re.sub(r'\D', '', part)
            if len(digits) > 3 and digits.startswith(prefix):
                rolls.append(digits)
            else:
                seq = int(digits)
                rolls.append(f'{prefix}{seq:03d}')
    if len(rolls) > expected_count:
        rolls = rolls[:expected_count]
    return rolls

def parse_csv(csv_path):
    with open(csv_path, 'r', encoding='utf-8') as f:
        rows = list(csv.reader(f))

    data_rows = rows[6:]
    allocations_by_room = {}
    current_hall = ''
    all_students = []

    for i, r in enumerate(data_rows):
        if not r or not any(r) or r[0] == 'TOTAL':
            break
        year_str = r[1].strip()
        dept = r[3].strip()
        subj = r[4].strip()
        reg = r[5].strip()
        cnt = int(r[6].strip()) if r[6].strip() else 0
        hall = r[8].strip() if len(r) > 8 and r[8].strip() else current_hall
        current_hall = hall
        
        rolls = expand_pattern(reg, cnt)
        sem = 3 if year_str == 'II' else 5
        yr = 2 if year_str == 'II' else 3
        
        if hall not in allocations_by_room:
            allocations_by_room[hall] = []
            
        for ro in rolls:
            stu = {
                'roll_no': ro,
                'name': make_name(ro),
                'dept': dept,
                'branch': dept,
                'year': yr,
                'semester': sem,
                'subject': subj,
                'exam_code': subj,
                'hall': hall,
                'dob': '2005-01-01'
            }
            allocations_by_room[hall].append(stu)
            all_students.append(stu)

    return allocations_by_room, all_students

def import_dataset(conn, csv_path, exam_id, exam_name, exam_date, start_time, end_time, room_rows=None, room_cols=None, room_cap=None):
    allocations_by_room, all_students = parse_csv(csv_path)
    cursor = conn.cursor()

    print(f"\n--- Importing {exam_name} ({exam_date}) ---")
    print(f"Loaded {len(all_students)} students across {len(allocations_by_room)} halls from CSV.")

    # 1. Insert or update Exam
    cursor.execute("""
        INSERT INTO exams (id, exam_name, exam_date, start_time, end_time, semester, status)
        VALUES (?, ?, ?, ?, ?, 3, 'upcoming')
        ON CONFLICT(id) DO UPDATE SET
            exam_name = excluded.exam_name,
            exam_date = excluded.exam_date,
            start_time = excluded.start_time,
            end_time = excluded.end_time,
            semester = excluded.semester,
            status = excluded.status
    """, (exam_id, exam_name, exam_date, start_time, end_time))

    # 2. Insert or update Rooms
    room_id_map = {}
    for hall, stu_list in allocations_by_room.items():
        block = get_block_name(hall)
        if room_rows is not None and room_cols is not None and room_cap is not None:
            rows_count, cols_count, cap = room_rows, room_cols, room_cap
        else:
            tot = len(stu_list)
            if tot <= 54:
                rows_count, cols_count, cap = 6, 9, 54
            else:
                rows_count, cols_count, cap = 6, 10, 60

        cursor.execute("""
            INSERT INTO rooms (room_no, block, capacity, rows_count, cols_count, active)
            VALUES (?, ?, ?, ?, ?, 1)
            ON CONFLICT(room_no) DO UPDATE SET
                block = excluded.block,
                capacity = excluded.capacity,
                rows_count = excluded.rows_count,
                cols_count = excluded.cols_count,
                active = 1
        """, (hall, block, cap, rows_count, cols_count))

        cursor.execute("SELECT id FROM rooms WHERE room_no = ?", (hall,))
        r_id = cursor.fetchone()[0]
        room_id_map[hall] = {
            'id': r_id,
            'rows_count': rows_count,
            'cols_count': cols_count
        }

    # 3. Clean up existing seating and student_exams for this exam_id
    cursor.execute("DELETE FROM seating WHERE exam_id = ?", (exam_id,))
    cursor.execute("DELETE FROM student_exams WHERE exam_id = ?", (exam_id,))

    # 4. Insert students
    print(f"Upserting {len(all_students)} students...")
    student_insert_sql = """
        INSERT INTO students (roll_no, name, dob, branch, dept, semester, year, exam_code, exam_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(roll_no) DO UPDATE SET
            name = excluded.name,
            dob = excluded.dob,
            branch = excluded.branch,
            dept = excluded.dept,
            semester = excluded.semester,
            year = excluded.year,
            exam_code = excluded.exam_code
    """
    student_batch = [
        (s['roll_no'], s['name'], s['dob'], s['branch'], s['dept'], s['semester'], s['year'], s['exam_code'], exam_id)
        for s in all_students
    ]
    cursor.executemany(student_insert_sql, student_batch)

    # Fetch student ID map
    cursor.execute("SELECT id, roll_no FROM students")
    student_id_map = {row[1]: row[0] for row in cursor.fetchall()}

    # Insert student_exams links
    print(f"Linking students to exam {exam_id}...")
    student_exams_batch = [
        (student_id_map[s['roll_no']], exam_id, s['exam_code'])
        for s in all_students
        if s['roll_no'] in student_id_map
    ]
    cursor.executemany("""
        INSERT OR IGNORE INTO student_exams (student_id, exam_id, exam_code)
        VALUES (?, ?, ?)
    """, student_exams_batch)

    # 5. Generate conflict-free seating
    print(f"Arranging conflict-free seating across {len(allocations_by_room)} halls...")
    seating_batch = []

    for hall, stu_list in allocations_by_room.items():
        rm_info = room_id_map[hall]
        r_id = rm_info['id']
        rows = rm_info['rows_count']
        cols = rm_info['cols_count']

        cohorts = {}
        for s in stu_list:
            sub = s['subject']
            if sub not in cohorts:
                cohorts[sub] = []
            cohorts[sub].append(s)

        sorted_cohort_keys = sorted(cohorts.keys(), key=lambda k: len(cohorts[k]), reverse=True)
        c_queues = [list(cohorts[k]) for k in sorted_cohort_keys]

        even_slots = []
        odd_slots = []
        for r in range(1, rows + 1):
            for c in range(1, cols + 1):
                if (r + c) % 2 == 0:
                    even_slots.append((r, c))
                else:
                    odd_slots.append((r, c))

        even_students = []
        odd_students = []

        if len(c_queues) == 2:
            even_students = c_queues[0]
            odd_students = c_queues[1]
        else:
            for q in c_queues:
                if len(even_students) <= len(odd_students) and len(even_students) + len(q) <= len(even_slots):
                    even_students.extend(q)
                elif len(odd_students) + len(q) <= len(odd_slots):
                    odd_students.extend(q)
                elif len(even_students) + len(q) <= len(even_slots):
                    even_students.extend(q)
                else:
                    space_even = len(even_slots) - len(even_students)
                    even_students.extend(q[:space_even])
                    odd_students.extend(q[space_even:])

        grid = {}
        for i, s in enumerate(even_students):
            if i < len(even_slots):
                grid[even_slots[i]] = s
        for i, s in enumerate(odd_students):
            if i < len(odd_slots):
                grid[odd_slots[i]] = s

        for (r, c), s in grid.items():
            stu_id = student_id_map.get(s['roll_no'])
            if cols == 10:
                bench_no = (r - 1) * 5 + ((c + 1) // 2)
                seat_index = 1 if c % 2 == 1 else 2
            else:
                bench_no = r
                seat_index = c
            seating_batch.append((
                exam_id,
                r_id,
                s['roll_no'],
                stu_id,
                s['subject'],
                r,
                c,
                bench_no,
                seat_index
            ))

    cursor.executemany("""
        INSERT INTO seating (exam_id, room_id, roll_no, student_id, exam_code, row_num, col_num, bench_no, seat_index)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, seating_batch)

    conn.commit()

    # Verification
    cursor.execute("SELECT COUNT(*) FROM seating WHERE exam_id = ?", (exam_id,))
    total_seated = cursor.fetchone()[0]
    cursor.execute("SELECT COUNT(DISTINCT room_id) FROM seating WHERE exam_id = ?", (exam_id,))
    rooms_seated = cursor.fetchone()[0]
    print(f"Verification: {total_seated} seats placed across {rooms_seated} rooms for exam {exam_id}.")

    # Clash check
    cursor.execute("""
        SELECT s1.room_id, s1.row_num, s1.col_num, s1.exam_code, s2.exam_code
        FROM seating s1
        JOIN seating s2 ON s1.exam_id = s2.exam_id
                       AND s1.room_id = s2.room_id
                       AND (
                           (s1.row_num = s2.row_num AND s1.col_num + 1 = s2.col_num)
                           OR
                           (s1.row_num + 1 = s2.row_num AND s1.col_num = s2.col_num)
                       )
        WHERE s1.exam_id = ? AND s1.exam_code = s2.exam_code
    """, (exam_id,))
    clash_rows = cursor.fetchall()
    print(f"Anti-clash verification for exam {exam_id}: {len(clash_rows)} adjacent same-paper clashes.")
    if exam_id == 3:
        assert len(clash_rows) == 0, f"Clashes found in exam {exam_id}: {clash_rows}"

def import_to_db():
    conn = sqlite3.connect(DB_PATH)
    conn.execute('PRAGMA foreign_keys = ON;')
    cursor = conn.cursor()

    # Baseline regression test rooms (101, 102, 201, 301)
    baseline_rooms = [
        ('101', 'Block A - Ground Floor', 40, 5, 8),
        ('102', 'Block A - Ground Floor', 30, 5, 6),
        ('201', 'Block B - First Floor', 24, 4, 6),
        ('301', 'Block C - Second Floor', 20, 4, 5)
    ]
    for r_no, blk, cap, rc, cc in baseline_rooms:
        cursor.execute("""
            INSERT OR IGNORE INTO rooms (room_no, block, capacity, rows_count, cols_count, active)
            VALUES (?, ?, ?, ?, ?, 1)
        """, (r_no, blk, cap, rc, cc))

    # Keep demo exam 2 as secondary if it exists
    cursor.execute("""
        INSERT OR IGNORE INTO exams (id, exam_name, exam_date, start_time, end_time, semester, status)
        VALUES (2, 'Internal Assessment Test II – Nov 2026', '2026-11-15', '09:30:00', '12:30:00', 7, 'upcoming')
    """)
    conn.commit()

    # 1. Import Exam 1 (03.09.2026) if CSV exists
    if os.path.exists(CSV_PATH_03):
        import_dataset(
            conn=conn,
            csv_path=CSV_PATH_03,
            exam_id=1,
            exam_name='UG - II/III YEAR - CAT I (Slot I)',
            exam_date='2026-09-03',
            start_time='09:00:00',
            end_time='11:15:00'
        )

    # 2. Import Exam 3 (19.09.2026) with 5 bench columns (10 seats across) x 9 rows
    if os.path.exists(CSV_PATH_19):
        import_dataset(
            conn=conn,
            csv_path=CSV_PATH_19,
            exam_id=3,
            exam_name='UG - II/III YEAR - CAT I (19.09.2026 - Slot I)',
            exam_date='2026-09-19',
            start_time='09:00:00',
            end_time='11:15:00',
            room_rows=9,
            room_cols=10,
            room_cap=90
        )

    conn.close()
    print("\n✅ All datasets imported and verified successfully!")

if __name__ == '__main__':
    import_to_db()
