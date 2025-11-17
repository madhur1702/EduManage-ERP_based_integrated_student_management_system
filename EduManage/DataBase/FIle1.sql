-- =====================================================
-- Database: student_management_system
-- COMPLETE DATABASE WITH COMPREHENSIVE DUMMY DATA
-- =====================================================

DROP DATABASE IF EXISTS student_management_system;
CREATE DATABASE student_management_system;
USE student_management_system;

-- =====================================================
-- TABLE DEFINITIONS
-- =====================================================

-- Table: roles
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL
);

INSERT INTO roles (role_name)
VALUES ('Admin'), ('SubAdmin'), ('Faculty'), ('Student'), ('Librarian'), ('Accountant');

-- Table: departments
CREATE TABLE departments (
    dept_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(50) UNIQUE NOT NULL
);

INSERT INTO departments (dept_name)
VALUES ('Computer'), ('IT'), ('ENTC');

-- Table: users
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT,
    dept_id INT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE SET NULL
);

-- Table: students
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    roll_no VARCHAR(20) UNIQUE NOT NULL,
    admission_year YEAR,
    dob DATE,
    address VARCHAR(255),
    guardian_name VARCHAR(100),
    guardian_contact VARCHAR(15),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Table: faculty
CREATE TABLE faculty (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    designation VARCHAR(100),
    qualification VARCHAR(100),
    joining_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Table: courses
CREATE TABLE courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    dept_id INT,
    faculty_id INT,
    semester INT,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL
);

-- Table: enrollments
CREATE TABLE enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    course_id INT,
    semester INT,
    academic_year VARCHAR(10),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE (student_id, course_id)
);

-- Table: attendance
CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT,
    attendance_date DATE,
    status ENUM('Present', 'Absent') NOT NULL,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE CASCADE
);

-- Table: marks
CREATE TABLE marks (
    mark_id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT,
    exam_type VARCHAR(50),
    marks_obtained DECIMAL(5,2),
    max_marks DECIMAL(5,2),
    exam_date DATE,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE CASCADE
);

-- Table: library_books
CREATE TABLE library_books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150),
    author VARCHAR(100),
    isbn VARCHAR(20) UNIQUE,
    department INT,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    FOREIGN KEY (department) REFERENCES departments(dept_id) ON DELETE SET NULL
);

-- Table: library_issues
CREATE TABLE library_issues (
    issue_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    student_id INT,
    issue_date DATE,
    due_date DATE,
    return_date DATE NULL,
    FOREIGN KEY (book_id) REFERENCES library_books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- Table: fees
CREATE TABLE fees (
    fee_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    total_fee DECIMAL(10,2),
    amount_paid DECIMAL(10,2),
    due_amount DECIMAL(10,2),
    last_payment_date DATE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

-- Table: activity_log
CREATE TABLE activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- =====================================================
-- BASE USERS (Admin, SubAdmin, Librarian, Accountant)
-- =====================================================

INSERT INTO users (role_id, username, password_hash, full_name, email, phone)
VALUES
(1, 'admin', 'admin@123', 'System Admin', 'admin@college.edu', '9999999999'),
(2, 'subadmin', 'subadmin@123', 'Sub Admin', 'subadmin@college.edu', '8888888888'),
(5, 'librarian', 'lib@123', 'Library Manager', 'library@college.edu', '7777777777'),
(6, 'accountant', 'acc@123', 'College Accountant', 'accounts@college.edu', '6666666666');

-- =====================================================
-- FACULTY (10 PER DEPARTMENT = 30 TOTAL)
-- =====================================================

-- COMPUTER DEPARTMENT FACULTY (10)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(3, 1, 'comp_fac_001', 'pass@123', 'Dr. Amit Sharma', 'amit.sharma@college.edu', '9898989801'),
(3, 1, 'comp_fac_002', 'pass@123', 'Prof. Priya Desai', 'priya.desai@college.edu', '9898989802'),
(3, 1, 'comp_fac_003', 'pass@123', 'Dr. Rajesh Kumar', 'rajesh.kumar@college.edu', '9898989803'),
(3, 1, 'comp_fac_004', 'pass@123', 'Dr. Sneha Patel', 'sneha.patel@college.edu', '9898989804'),
(3, 1, 'comp_fac_005', 'pass@123', 'Prof. Vikram Singh', 'vikram.singh@college.edu', '9898989805'),
(3, 1, 'comp_fac_006', 'pass@123', 'Dr. Meera Joshi', 'meera.joshi@college.edu', '9898989806'),
(3, 1, 'comp_fac_007', 'pass@123', 'Dr. Arjun Nair', 'arjun.nair@college.edu', '9898989807'),
(3, 1, 'comp_fac_008', 'pass@123', 'Prof. Kavita Rao', 'kavita.rao@college.edu', '9898989808'),
(3, 1, 'comp_fac_009', 'pass@123', 'Dr. Anil Verma', 'anil.verma@college.edu', '9898989809'),
(3, 1, 'comp_fac_010', 'pass@123', 'Dr. Pooja Kulkarni', 'pooja.kulkarni@college.edu', '9898989810');

-- IT DEPARTMENT FACULTY (10)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(3, 2, 'it_fac_001', 'pass@123', 'Prof. Bharat Mehta', 'bharat.mehta@college.edu', '9797979701'),
(3, 2, 'it_fac_002', 'pass@123', 'Dr. Anjali Gupta', 'anjali.gupta@college.edu', '9797979702'),
(3, 2, 'it_fac_003', 'pass@123', 'Dr. Suresh Iyer', 'suresh.iyer@college.edu', '9797979703'),
(3, 2, 'it_fac_004', 'pass@123', 'Prof. Neha Shah', 'neha.shah@college.edu', '9797979704'),
(3, 2, 'it_fac_005', 'pass@123', 'Dr. Karan Kapoor', 'karan.kapoor@college.edu', '9797979705'),
(3, 2, 'it_fac_006', 'pass@123', 'Dr. Ritu Malhotra', 'ritu.malhotra@college.edu', '9797979706'),
(3, 2, 'it_fac_007', 'pass@123', 'Prof. Sanjay Reddy', 'sanjay.reddy@college.edu', '9797979707'),
(3, 2, 'it_fac_008', 'pass@123', 'Dr. Divya Chopra', 'divya.chopra@college.edu', '9797979708'),
(3, 2, 'it_fac_009', 'pass@123', 'Dr. Manish Agarwal', 'manish.agarwal@college.edu', '9797979709'),
(3, 2, 'it_fac_010', 'pass@123', 'Prof. Shreya Bose', 'shreya.bose@college.edu', '9797979710');

-- ENTC DEPARTMENT FACULTY (10)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(3, 3, 'entc_fac_001', 'pass@123', 'Dr. Chetan Patil', 'chetan.patil@college.edu', '9696969601'),
(3, 3, 'entc_fac_002', 'pass@123', 'Prof. Deepa Nair', 'deepa.nair@college.edu', '9696969602'),
(3, 3, 'entc_fac_003', 'pass@123', 'Dr. Rakesh Joshi', 'rakesh.joshi@college.edu', '9696969603'),
(3, 3, 'entc_fac_004', 'pass@123', 'Dr. Shalini Deshmukh', 'shalini.d@college.edu', '9696969604'),
(3, 3, 'entc_fac_005', 'pass@123', 'Prof. Varun Kulkarni', 'varun.kulkarni@college.edu', '9696969605'),
(3, 3, 'entc_fac_006', 'pass@123', 'Dr. Isha Shetty', 'isha.shetty@college.edu', '9696969606'),
(3, 3, 'entc_fac_007', 'pass@123', 'Dr. Nitin Bhavsar', 'nitin.bhavsar@college.edu', '9696969607'),
(3, 3, 'entc_fac_008', 'pass@123', 'Prof. Pallavi Sawant', 'pallavi.sawant@college.edu', '9696969608'),
(3, 3, 'entc_fac_009', 'pass@123', 'Dr. Gaurav Deshpande', 'gaurav.d@college.edu', '9696969609'),
(3, 3, 'entc_fac_010', 'pass@123', 'Dr. Radhika Bhosale', 'radhika.b@college.edu', '9696969610');

-- INSERT FACULTY RECORDS
INSERT INTO faculty (user_id, designation, qualification, joining_date)
SELECT user_id, 
       CASE WHEN user_id % 3 = 0 THEN 'Professor'
            WHEN user_id % 3 = 1 THEN 'Associate Professor'
            ELSE 'Assistant Professor' END,
       CASE WHEN user_id % 2 = 0 THEN 'PhD' ELSE 'MTech' END,
       DATE_SUB(CURDATE(), INTERVAL (user_id % 15 + 1) YEAR)
FROM users 
WHERE role_id = 3;

-- =====================================================
-- STUDENTS (10 PER DEPARTMENT PER YEAR = 120 TOTAL)
-- =====================================================

-- YEAR 1 - ADMISSION YEAR 2025 (10 PER DEPARTMENT)
-- COMPUTER DEPARTMENT YEAR 1 (2025)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 1, 'comp_2025_001', 'stu@123', 'Aarav Patel', 'aarav.patel@college.edu', '9090900101'),
(4, 1, 'comp_2025_002', 'stu@123', 'Vivaan Kumar', 'vivaan.kumar@college.edu', '9090900102'),
(4, 1, 'comp_2025_003', 'stu@123', 'Arjun Singh', 'arjun.singh@college.edu', '9090900103'),
(4, 1, 'comp_2025_004', 'stu@123', 'Siddharth Roy', 'siddharth.roy@college.edu', '9090900104'),
(4, 1, 'comp_2025_005', 'stu@123', 'Aditya Sharma', 'aditya.sharma@college.edu', '9090900105'),
(4, 1, 'comp_2025_006', 'stu@123', 'Ananya Gupta', 'ananya.gupta@college.edu', '9090900106'),
(4, 1, 'comp_2025_007', 'stu@123', 'Priya Verma', 'priya.verma@college.edu', '9090900107'),
(4, 1, 'comp_2025_008', 'stu@123', 'Shreya Nair', 'shreya.nair@college.edu', '9090900108'),
(4, 1, 'comp_2025_009', 'stu@123', 'Isha Desai', 'isha.desai@college.edu', '9090900109'),
(4, 1, 'comp_2025_010', 'stu@123', 'Neha Iyer', 'neha.iyer@college.edu', '9090900110');

-- IT DEPARTMENT YEAR 1 (2025)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 2, 'it_2025_001', 'stu@123', 'Karan Malik', 'karan.malik@college.edu', '9292900201'),
(4, 2, 'it_2025_002', 'stu@123', 'Rohit Saxena', 'rohit.saxena@college.edu', '9292900202'),
(4, 2, 'it_2025_003', 'stu@123', 'Ankush Verma', 'ankush.verma@college.edu', '9292900203'),
(4, 2, 'it_2025_004', 'stu@123', 'Sameer Chopra', 'sameer.chopra@college.edu', '9292900204'),
(4, 2, 'it_2025_005', 'stu@123', 'Manish Patel', 'manish.patel@college.edu', '9292900205'),
(4, 2, 'it_2025_006', 'stu@123', 'Divya Sharma', 'divya.sharma@college.edu', '9292900206'),
(4, 2, 'it_2025_007', 'stu@123', 'Swati Iyer', 'swati.iyer@college.edu', '9292900207'),
(4, 2, 'it_2025_008', 'stu@123', 'Anjali Roy', 'anjali.roy@college.edu', '9292900208'),
(4, 2, 'it_2025_009', 'stu@123', 'Pooja Kapoor', 'pooja.kapoor@college.edu', '9292900209'),
(4, 2, 'it_2025_010', 'stu@123', 'Megha Nair', 'megha.nair@college.edu', '9292900210');

-- ENTC DEPARTMENT YEAR 1 (2025)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 3, 'entc_2025_001', 'stu@123', 'Akhil Pandey', 'akhil.pandey@college.edu', '9393900301'),
(4, 3, 'entc_2025_002', 'stu@123', 'Bhavesh Kumar', 'bhavesh.kumar@college.edu', '9393900302'),
(4, 3, 'entc_2025_003', 'stu@123', 'Chirag Sharma', 'chirag.sharma@college.edu', '9393900303'),
(4, 3, 'entc_2025_004', 'stu@123', 'Dhruv Patel', 'dhruv.patel@college.edu', '9393900304'),
(4, 3, 'entc_2025_005', 'stu@123', 'Eeshwar Singh', 'eeshwar.singh@college.edu', '9393900305'),
(4, 3, 'entc_2025_006', 'stu@123', 'Anushka Desai', 'anushka.desai@college.edu', '9393900306'),
(4, 3, 'entc_2025_007', 'stu@123', 'Bhavna Iyer', 'bhavna.iyer@college.edu', '9393900307'),
(4, 3, 'entc_2025_008', 'stu@123', 'Chetna Nair', 'chetna.nair@college.edu', '9393900308'),
(4, 3, 'entc_2025_009', 'stu@123', 'Disha Roy', 'disha.roy@college.edu', '9393900309'),
(4, 3, 'entc_2025_010', 'stu@123', 'Eesha Verma', 'eesha.verma@college.edu', '9393900310');

-- YEAR 2 - ADMISSION YEAR 2024 (10 PER DEPARTMENT)
-- COMPUTER DEPARTMENT YEAR 2 (2024)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 1, 'comp_2024_001', 'stu@123', 'Prakash Nair', 'prakash.nair@college.edu', '9090901101'),
(4, 1, 'comp_2024_002', 'stu@123', 'Quentin Singh', 'quentin.singh@college.edu', '9090901102'),
(4, 1, 'comp_2024_003', 'stu@123', 'Rishi Patel', 'rishi.patel@college.edu', '9090901103'),
(4, 1, 'comp_2024_004', 'stu@123', 'Suman Rao', 'suman.rao@college.edu', '9090901104'),
(4, 1, 'comp_2024_005', 'stu@123', 'Tanmay Verma', 'tanmay.verma@college.edu', '9090901105'),
(4, 1, 'comp_2024_006', 'stu@123', 'Urvi Desai', 'urvi.desai@college.edu', '9090901106'),
(4, 1, 'comp_2024_007', 'stu@123', 'Vaidehi Iyer', 'vaidehi.iyer@college.edu', '9090901107'),
(4, 1, 'comp_2024_008', 'stu@123', 'Wanda Roy', 'wanda.roy@college.edu', '9090901108'),
(4, 1, 'comp_2024_009', 'stu@123', 'Xyla Khan', 'xyla.khan@college.edu', '9090901109'),
(4, 1, 'comp_2024_010', 'stu@123', 'Yash Kapoor', 'yash.kapoor@college.edu', '9090901110');

-- IT DEPARTMENT YEAR 2 (2024)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 2, 'it_2024_001', 'stu@123', 'Jatin Verma', 'jatin.verma@college.edu', '9292901201'),
(4, 2, 'it_2024_002', 'stu@123', 'Keshav Patel', 'keshav.patel@college.edu', '9292901202'),
(4, 2, 'it_2024_003', 'stu@123', 'Laxman Rao', 'laxman.rao@college.edu', '9292901203'),
(4, 2, 'it_2024_004', 'stu@123', 'Mohit Desai', 'mohit.desai@college.edu', '9292901204'),
(4, 2, 'it_2024_005', 'stu@123', 'Nitin Singh', 'nitin.singh@college.edu', '9292901205'),
(4, 2, 'it_2024_006', 'stu@123', 'Olivia Iyer', 'olivia.iyer@college.edu', '9292901206'),
(4, 2, 'it_2024_007', 'stu@123', 'Prisha Nair', 'prisha.nair@college.edu', '9292901207'),
(4, 2, 'it_2024_008', 'stu@123', 'Quincy Roy', 'quincy.roy@college.edu', '9292901208'),
(4, 2, 'it_2024_009', 'stu@123', 'Rahul Khan', 'rahul.khan@college.edu', '9292901209'),
(4, 2, 'it_2024_010', 'stu@123', 'Sneha Kapoor', 'sneha.kapoor@college.edu', '9292901210');

-- ENTC DEPARTMENT YEAR 2 (2024)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 3, 'entc_2024_001', 'stu@123', 'Dharma Rao', 'dharma.rao@college.edu', '9393901301'),
(4, 3, 'entc_2024_002', 'stu@123', 'Eshita Desai', 'eshita.desai@college.edu', '9393901302'),
(4, 3, 'entc_2024_003', 'stu@123', 'Farah Iyer', 'farah.iyer@college.edu', '9393901303'),
(4, 3, 'entc_2024_004', 'stu@123', 'Girish Nair', 'girish.nair@college.edu', '9393901304'),
(4, 3, 'entc_2024_005', 'stu@123', 'Harini Roy', 'harini.roy@college.edu', '9393901305'),
(4, 3, 'entc_2024_006', 'stu@123', 'Ira Khan', 'ira.khan@college.edu', '9393901306'),
(4, 3, 'entc_2024_007', 'stu@123', 'Jayant Kapoor', 'jayant.kapoor@college.edu', '9393901307'),
(4, 3, 'entc_2024_008', 'stu@123', 'Kanchan Bhat', 'kanchan.bhat@college.edu', '9393901308'),
(4, 3, 'entc_2024_009', 'stu@123', 'Lakshmi Menon', 'lakshmi.menon@college.edu', '9393901309'),
(4, 3, 'entc_2024_010', 'stu@123', 'Mahesh Sinha', 'mahesh.sinha@college.edu', '9393901310');

-- YEAR 3 - ADMISSION YEAR 2023 (10 PER DEPARTMENT)
-- COMPUTER DEPARTMENT YEAR 3 (2023)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 1, 'comp_2023_001', 'stu@123', 'Xavier Menon', 'xavier.menon@college.edu', '9090902101'),
(4, 1, 'comp_2023_002', 'stu@123', 'Yara Sinha', 'yara.sinha@college.edu', '9090902102'),
(4, 1, 'comp_2023_003', 'stu@123', 'Zain Ahmad', 'zain.ahmad@college.edu', '9090902103'),
(4, 1, 'comp_2023_004', 'stu@123', 'Amar Joshi', 'amar.joshi@college.edu', '9090902104'),
(4, 1, 'comp_2023_005', 'stu@123', 'Bhargavi Gupta', 'bhargavi.gupta@college.edu', '9090902105'),
(4, 1, 'comp_2023_006', 'stu@123', 'Chandan Malik', 'chandan.malik@college.edu', '9090902106'),
(4, 1, 'comp_2023_007', 'stu@123', 'Diya Rao', 'diya.rao@college.edu', '9090902107'),
(4, 1, 'comp_2023_008', 'stu@123', 'Ethan Desai', 'ethan.desai@college.edu', '9090902108'),
(4, 1, 'comp_2023_009', 'stu@123', 'Fiona Iyer', 'fiona.iyer@college.edu', '9090902109'),
(4, 1, 'comp_2023_010', 'stu@123', 'Gyan Nair', 'gyan.nair@college.edu', '9090902110');

-- IT DEPARTMENT YEAR 3 (2023)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 2, 'it_2023_001', 'stu@123', 'Raj Pandey', 'raj.pandey@college.edu', '9292902201'),
(4, 2, 'it_2023_002', 'stu@123', 'Sapna Nair', 'sapna.nair@college.edu', '9292902202'),
(4, 2, 'it_2023_003', 'stu@123', 'Tosh Roy', 'tosh.roy@college.edu', '9292902203'),
(4, 2, 'it_2023_004', 'stu@123', 'Uma Khan', 'uma.khan@college.edu', '9292902204'),
(4, 2, 'it_2023_005', 'stu@123', 'Vedant Kapoor', 'vedant.kapoor@college.edu', '9292902205'),
(4, 2, 'it_2023_006', 'stu@123', 'Wina Bhat', 'wina.bhat@college.edu', '9292902206'),
(4, 2, 'it_2023_007', 'stu@123', 'Xavier Menon', 'xavier.menon2@college.edu', '9292902207'),
(4, 2, 'it_2023_008', 'stu@123', 'Yashmit Sinha', 'yashmit.sinha@college.edu', '9292902208'),
(4, 2, 'it_2023_009', 'stu@123', 'Zubin Joshi', 'zubin.joshi@college.edu', '9292902209'),
(4, 2, 'it_2023_010', 'stu@123', 'Arush Gupta', 'arush.gupta@college.edu', '9292902210');

-- ENTC DEPARTMENT YEAR 3 (2023)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 3, 'entc_2023_001', 'stu@123', 'Lena Sinha', 'lena.sinha@college.edu', '9393902301'),
(4, 3, 'entc_2023_002', 'stu@123', 'Madhav Joshi', 'madhav.joshi@college.edu', '9393902302'),
(4, 3, 'entc_2023_003', 'stu@123', 'Nidhi Gupta', 'nidhi.gupta@college.edu', '9393902303'),
(4, 3, 'entc_2023_004', 'stu@123', 'Orin Malik', 'orin.malik@college.edu', '9393902304'),
(4, 3, 'entc_2023_005', 'stu@123', 'Payal Rao', 'payal.rao@college.edu', '9393902305'),
(4, 3, 'entc_2023_006', 'stu@123', 'Quinn Desai', 'quinn.desai@college.edu', '9393902306'),
(4, 3, 'entc_2023_007', 'stu@123', 'Radhika Iyer', 'radhika.iyer@college.edu', '9393902307'),
(4, 3, 'entc_2023_008', 'stu@123', 'Samir Nair', 'samir.nair@college.edu', '9393902308'),
(4, 3, 'entc_2023_009', 'stu@123', 'Tina Roy', 'tina.roy@college.edu', '9393902309'),
(4, 3, 'entc_2023_010', 'stu@123', 'Udit Khan', 'udit.khan@college.edu', '9393902310');

-- YEAR 4 - ADMISSION YEAR 2022 (10 PER DEPARTMENT)
-- COMPUTER DEPARTMENT YEAR 4 (2022)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 1, 'comp_2022_001', 'stu@123', 'Farhad Roy', 'farhad.roy@college.edu', '9090903101'),
(4, 1, 'comp_2022_002', 'stu@123', 'Gaya Khan', 'gaya.khan@college.edu', '9090903102'),
(4, 1, 'comp_2022_003', 'stu@123', 'Haran Kapoor', 'haran.kapoor@college.edu', '9090903103'),
(4, 1, 'comp_2022_004', 'stu@123', 'Iris Bhat', 'iris.bhat@college.edu', '9090903104'),
(4, 1, 'comp_2022_005', 'stu@123', 'Jagat Menon', 'jagat.menon@college.edu', '9090903105'),
(4, 1, 'comp_2022_006', 'stu@123', 'Kavish Sinha', 'kavish.sinha@college.edu', '9090903106'),
(4, 1, 'comp_2022_007', 'stu@123', 'Layla Joshi', 'layla.joshi@college.edu', '9090903107'),
(4, 1, 'comp_2022_008', 'stu@123', 'Manav Gupta', 'manav.gupta@college.edu', '9090903108'),
(4, 1, 'comp_2022_009', 'stu@123', 'Nara Malik', 'nara.malik@college.edu', '9090903109'),
(4, 1, 'comp_2022_010', 'stu@123', 'Orion Rao', 'orion.rao@college.edu', '9090903110');

-- IT DEPARTMENT YEAR 4 (2022)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 2, 'it_2022_001', 'stu@123', 'Zane Gupta', 'zane.gupta@college.edu', '9292903201'),
(4, 2, 'it_2022_002', 'stu@123', 'Anvi Malik', 'anvi.malik@college.edu', '9292903202'),
(4, 2, 'it_2022_003', 'stu@123', 'Bran Rao', 'bran.rao@college.edu', '9292903203'),
(4, 2, 'it_2022_004', 'stu@123', 'Caren Desai', 'caren.desai@college.edu', '9292903204'),
(4, 2, 'it_2022_005', 'stu@123', 'Darius Iyer', 'darius.iyer@college.edu', '9292903205'),
(4, 2, 'it_2022_006', 'stu@123', 'Elena Nair', 'elena.nair@college.edu', '9292903206'),
(4, 2, 'it_2022_007', 'stu@123', 'Fabian Roy', 'fabian.roy@college.edu', '9292903207'),
(4, 2, 'it_2022_008', 'stu@123', 'Gena Khan', 'gena.khan@college.edu', '9292903208'),
(4, 2, 'it_2022_009', 'stu@123', 'Harini Kapoor', 'harini.kapoor@college.edu', '9292903209'),
(4, 2, 'it_2022_010', 'stu@123', 'Irina Bhat', 'irina.bhat@college.edu', '9292903210');

-- ENTC DEPARTMENT YEAR 4 (2022)
INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone)
VALUES
(4, 3, 'entc_2022_001', 'stu@123', 'Tara Khan', 'tara.khan@college.edu', '9393903301'),
(4, 3, 'entc_2022_002', 'stu@123', 'Uvarani Kapoor', 'uvarani.kapoor@college.edu', '9393903302'),
(4, 3, 'entc_2022_003', 'stu@123', 'Vikram Bhat', 'vikram.bhat@college.edu', '9393903303'),
(4, 3, 'entc_2022_004', 'stu@123', 'Wren Menon', 'wren.menon@college.edu', '9393903304'),
(4, 3, 'entc_2022_005', 'stu@123', 'Xander Sinha', 'xander.sinha@college.edu', '9393903305'),
(4, 3, 'entc_2022_006', 'stu@123', 'Yuna Joshi', 'yuna.joshi@college.edu', '9393903306'),
(4, 3, 'entc_2022_007', 'stu@123', 'Zara Gupta', 'zara.gupta@college.edu', '9393903307'),
(4, 3, 'entc_2022_008', 'stu@123', 'Amir Malik', 'amir.malik@college.edu', '9393903308'),
(4, 3, 'entc_2022_009', 'stu@123', 'Brielle Rao', 'brielle.rao@college.edu', '9393903309'),
(4, 3, 'entc_2022_010', 'stu@123', 'Cyrene Desai', 'cyrene.desai@college.edu', '9393903310');

-- =====================================================
-- INSERT ALL STUDENT RECORDS
-- =====================================================

INSERT INTO students (user_id, roll_no, admission_year, dob, address, guardian_name, guardian_contact)
SELECT 
    u.user_id,
    CONCAT(
        CASE WHEN u.dept_id = 1 THEN 'CMP'
             WHEN u.dept_id = 2 THEN 'IT'
             WHEN u.dept_id = 3 THEN 'ENTC' END,
        '-',
        CASE WHEN u.username LIKE '%_2025_%' THEN '2025'
             WHEN u.username LIKE '%_2024_%' THEN '2024'
             WHEN u.username LIKE '%_2023_%' THEN '2023'
             WHEN u.username LIKE '%_2022_%' THEN '2022' END,
        '-',
        LPAD(ROW_NUMBER() OVER (PARTITION BY u.dept_id ORDER BY u.user_id), 2, '0')
    ) as roll_no,
    CASE WHEN u.username LIKE '%_2025_%' THEN 2025
         WHEN u.username LIKE '%_2024_%' THEN 2024
         WHEN u.username LIKE '%_2023_%' THEN 2023
         WHEN u.username LIKE '%_2022_%' THEN 2022 END as admission_year,
    DATE_SUB('2007-01-01', INTERVAL (RAND() * 365) DAY) as dob,
    CASE WHEN u.dept_id = 1 THEN 'Pune, Maharashtra'
         WHEN u.dept_id = 2 THEN 'Mumbai, Maharashtra'
         WHEN u.dept_id = 3 THEN 'Nashik, Maharashtra' END as address,
    CONCAT('Guardian of ', SUBSTRING_INDEX(u.full_name, ' ', 1)) as guardian_name,
    CONCAT('900', LPAD(u.user_id, 7, '0')) as guardian_contact
FROM users u
WHERE u.role_id = 4 AND u.user_id > 7;

-- =====================================================
-- LIBRARY BOOKS (30 PER DEPARTMENT = 90 TOTAL)
-- =====================================================

-- COMPUTER DEPARTMENT BOOKS (30)
INSERT INTO library_books (title, author, isbn, department, total_copies, available_copies)
VALUES
('Introduction to Algorithms', 'Cormen', '9780262033848', 1, 5, 5),
('Operating System Concepts', 'Silberschatz', '9781119456339', 1, 5, 4),
('Database Management Systems', 'Raghu Ramakrishnan', '9780072465631', 1, 4, 3),
('Computer Networks', 'Tanenbaum', '9780132126953', 1, 4, 4),
('Artificial Intelligence: Modern Approach', 'Russell & Norvig', '9780136042594', 1, 4, 4),
('Machine Learning', 'Tom Mitchell', '9780070428072', 1, 3, 3),
('Design Patterns', 'Gamma et al.', '9780201633610', 1, 3, 3),
('Clean Code', 'Robert Martin', '9780132350884', 1, 3, 3),
('The Pragmatic Programmer', 'Hunt & Thomas', '9780201616224', 1, 3, 3),
('Computer Organization', 'Patterson & Hennessy', '9780128122754', 1, 4, 4),
('Deep Learning', 'Goodfellow', '9780262035613', 1, 3, 3),
('Python Programming', 'Mark Lutz', '9781449355739', 1, 4, 4),
('Java: Complete Reference', 'Herbert Schildt', '9781260440232', 1, 5, 5),
('C++ Primer', 'Stanley Lippman', '9780321714114', 1, 3, 3),
('Data Mining Concepts', 'Han & Kamber', '9780123814791', 1, 2, 2),
('Cryptography & Network Security', 'William Stallings', '9780134444284', 1, 3, 3),
('Software Engineering', 'Ian Sommerville', '9780133943030', 1, 4, 4),
('Compiler Design', 'Aho & Ullman', '9780321486813', 1, 2, 2),
('Theory of Computation', 'Michael Sipser', '9781133187790', 1, 2, 2),
('Computer Graphics', 'Donald Hearn', '9780132484572', 1, 3, 3),
('Cloud Computing', 'Thomas Erl', '9780133387520', 1, 3, 3),
('Big Data', 'Tom White', '9781449311520', 1, 2, 2),
('Internet of Things', 'Arshdeep Bahga', '9788131228319', 1, 3, 3),
('Blockchain Basics', 'Daniel Drescher', '9781484226032', 1, 2, 2),
('Cybersecurity Essentials', 'Charles Brooks', '9781119362395', 1, 3, 3),
('Natural Language Processing', 'Jurafsky & Martin', '9780131873216', 1, 2, 2),
('Computer Vision', 'Richard Szeliski', '9781848829343', 1, 2, 2),
('Distributed Systems', 'Tanenbaum', '9780132392275', 1, 2, 2),
('Software Testing', 'Ron Patton', '9780672327988', 1, 3, 3),
('Agile Software Development', 'Robert Martin', '9780135974445', 1, 2, 2);

-- IT DEPARTMENT BOOKS (30)
INSERT INTO library_books (title, author, isbn, department, total_copies, available_copies)
VALUES
('Web Technologies', 'Uttam K. Roy', '9780198066226', 2, 4, 3),
('JavaScript: The Good Parts', 'Douglas Crockford', '9780596517748', 2, 3, 3),
('Learning Web Design', 'Jennifer Robbins', '9780491960206', 2, 4, 4),
('Node.js Development', 'Ethan Brown', '9781491941466', 2, 3, 3),
('React Up & Running', 'Stoyan Stefanov', '9781491931820', 2, 3, 3),
('Angular Development', 'Brad Green', '9781617293344', 2, 3, 3),
('PHP & MySQL', 'Luke Welling', '9780321833891', 2, 4, 4),
('RESTful Web Services', 'Leonard Richardson', '9780596529260', 2, 3, 3),
('Microservices Patterns', 'Chris Richardson', '9781617294549', 2, 2, 2),
('Docker Deep Dive', 'Nigel Poulton', '9781521822807', 2, 3, 3),
('Kubernetes Up & Running', 'Kelsey Hightower', '9781491935675', 2, 3, 3),
('DevOps Handbook', 'Gene Kim', '9781942788003', 2, 2, 2),
('Site Reliability Engineering', 'Betsy Beyer', '9781491929124', 2, 3, 3),
('AWS Certified Solutions', 'Ben Piper', '9781119490708', 2, 3, 3),
('Azure Cloud Services', 'Michael Collier', '9781509302987', 2, 3, 3),
('MongoDB: The Definitive Guide', 'Kristina Chodorow', '9781449344689', 2, 3, 3),
('Redis in Action', 'Josiah Carlson', '9781617290855', 2, 2, 2),
('Elasticsearch: The Guide', 'Clinton Gormley', '9781449358549', 2, 2, 2),
('Apache Kafka', 'Neha Narkhede', '9781491936160', 2, 2, 2),
('GraphQL', 'Eve Porcello', '9781492030713', 2, 2, 2),
('Progressive Web Apps', 'Dean Hume', '9781617294587', 2, 3, 3),
('Mobile First Design', 'Luke Wroblewski', '9781937557027', 2, 2, 2),
('UX Design', 'Steve Krug', '9780321965516', 2, 3, 3),
('SEO 2025', 'Adam Clarke', '9781785098403', 2, 2, 2),
('Digital Marketing', 'Ryan Deiss', '9781119265702', 2, 2, 2),
('Git Version Control', 'Scott Chacon', '9781484200773', 2, 4, 4),
('Continuous Delivery', 'Jez Humble', '9780321601919', 2, 2, 2),
('Clean Architecture', 'Robert Martin', '9780134494166', 2, 3, 3),
('Domain Driven Design', 'Eric Evans', '9780321125217', 2, 2, 2),
('Practical SQL', 'Anthony DeBarros', '9781617291562', 2, 3, 3);

-- ENTC DEPARTMENT BOOKS (30)
INSERT INTO library_books (title, author, isbn, department, total_copies, available_copies)
VALUES
('Digital Logic Design', 'Morris Mano', '9780131989269', 3, 5, 4),
('Electronic Devices & Circuits', 'Boylestad', '9780133356953', 3, 4, 4),
('Digital Signal Processing', 'Oppenheim', '9780133351650', 3, 4, 4),
('Communication Systems', 'Simon Haykin', '9780470466016', 3, 4, 4),
('Microwave Engineering', 'David Pozar', '9780470631553', 3, 3, 3),
('Antennas & Propagation', 'Constantine Balanis', '9781118642061', 3, 3, 3),
('VLSI Design', 'Neil Weste', '9780321547743', 3, 3, 3),
('Embedded Systems', 'Raj Kamal', '9780070151253', 3, 4, 4),
('Microprocessors & Interfacing', 'Douglas Hall', '9780078028151', 3, 4, 4),
('Control Systems Engineering', 'Norman Nise', '9781118170519', 3, 3, 3),
('Power Electronics', 'Muhammad Rashid', '9780134475490', 3, 3, 3),
('Electric Circuits', 'James Nilsson', '9780133760033', 3, 4, 4),
('Signals and Systems', 'Alan Oppenheim', '9780138147570', 3, 3, 3),
('Analog Electronics', 'Sedra & Smith', '9780199368426', 3, 4, 4),
('Digital Communications', 'John Proakis', '9780072321111', 3, 3, 3),
('Wireless Communications', 'Andrea Goldsmith', '9780521837163', 3, 2, 2),
('Optical Fiber Communications', 'Gerd Keiser', '9780073380667', 3, 2, 2),
('RF Circuit Design', 'Christopher Bowick', '9780750685184', 3, 2, 2),
('Electromagnetic Waves', 'David Cheng', '9780201128192', 3, 3, 3),
('Network Analysis', 'Van Valkenburg', '9788120301566', 3, 3, 3),
('Electronic Instrumentation', 'Kalsi', '9780070151925', 3, 2, 2),
('VHDL Programming', 'Douglas Perry', '9780071258876', 3, 3, 3),
('FPGA Prototyping', 'Pong Chu', '9780470185315', 3, 2, 2),
('Digital Image Processing', 'Rafael Gonzalez', '9780133356724', 3, 2, 2),
('Radar Engineering', 'Merrill Skolnik', '9780071485470', 3, 2, 2),
('Satellite Communications', 'Dennis Roddy', '9780070445284', 3, 2, 2),
('Biomedical Electronics', 'Carr & Brown', '9780130115690', 3, 2, 2),
('MEMS & Microsystems', 'Tai-Ran Hsu', '9780470083017', 3, 2, 2),
('Nanoelectronics', 'Hanson', '9780137024780', 3, 2, 2),
('Power Systems', 'Grainger & Stevenson', '9780070612983', 3, 3, 3);

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

SELECT 'COMPLETE DATABASE SUMMARY' as Report;
SELECT 
    'Total Faculty' as Metric,
    COUNT(*) as Count
FROM users WHERE role_id = 3
UNION ALL
SELECT 'Total Students', COUNT(*) FROM users WHERE role_id = 4
UNION ALL
SELECT 'Total Library Books', COUNT(*) FROM library_books
UNION ALL
SELECT 'Total Courses', COUNT(*) FROM courses;

SELECT 'FACULTY BY DEPARTMENT' as Report;
SELECT 
    d.dept_name,
    COUNT(f.faculty_id) as Faculty_Count
FROM departments d
LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role_id = 3
LEFT JOIN faculty f ON u.user_id = f.user_id
GROUP BY d.dept_id, d.dept_name;

SELECT 'STUDENTS BY ADMISSION YEAR AND DEPARTMENT' as Report;
SELECT 
    s.admission_year,
    d.dept_name,
    COUNT(s.student_id) as Student_Count
FROM students s
JOIN users u ON s.user_id = u.user_id
JOIN departments d ON u.dept_id = d.dept_id
GROUP BY s.admission_year, d.dept_id, d.dept_name
ORDER BY s.admission_year DESC, d.dept_name;

SELECT 'LIBRARY BOOKS BY DEPARTMENT' as Report;
SELECT 
    d.dept_name,
    COUNT(lb.book_id) as Total_Books,
    SUM(lb.total_copies) as Total_Copies,
    SUM(lb.available_copies) as Available_Copies
FROM departments d
LEFT JOIN library_books lb ON d.dept_id = lb.department
GROUP BY d.dept_id, d.dept_name;