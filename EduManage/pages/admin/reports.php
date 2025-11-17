<?php
$page_title = 'Reports';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Admin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

// Get report type from request
$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'overview';

// Initialize variables
$report_data = array();
$chart_data = array();

// Report 1: Student Statistics
$student_stats = array(
    'total' => countStats('students'),
    'by_dept' => $conn->query("
        SELECT d.dept_name, COUNT(s.student_id) as count 
        FROM departments d 
        LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role_id = 4
        LEFT JOIN students s ON s.user_id = u.user_id 
        GROUP BY d.dept_id, d.dept_name
    ")->fetch_all(MYSQLI_ASSOC),
    'by_admission' => $conn->query("
        SELECT admission_year, COUNT(student_id) as count 
        FROM students 
        WHERE admission_year IS NOT NULL
        GROUP BY admission_year 
        ORDER BY admission_year DESC
    ")->fetch_all(MYSQLI_ASSOC)
);

// Report 2: Faculty Statistics
$faculty_stats = array(
    'total' => countStats('faculty'),
    'by_dept' => $conn->query("
        SELECT d.dept_name, COUNT(f.faculty_id) as count 
        FROM departments d 
        LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role_id = 3
        LEFT JOIN faculty f ON f.user_id = u.user_id 
        GROUP BY d.dept_id, d.dept_name
    ")->fetch_all(MYSQLI_ASSOC),
    'by_designation' => $conn->query("
        SELECT designation, COUNT(faculty_id) as count 
        FROM faculty 
        WHERE designation IS NOT NULL
        GROUP BY designation
    ")->fetch_all(MYSQLI_ASSOC)
);

// Report 3: Course & Enrollment Statistics
$course_stats = array(
    'total_courses' => countStats('courses'),
    'total_enrollments' => $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'],
    'by_semester' => $conn->query("
        SELECT c.semester, COUNT(DISTINCT c.course_id) as courses, COUNT(DISTINCT e.enrollment_id) as enrollments
        FROM courses c 
        LEFT JOIN enrollments e ON c.course_id = e.course_id 
        GROUP BY c.semester 
        ORDER BY c.semester
    ")->fetch_all(MYSQLI_ASSOC)
);

// Report 4: Attendance Statistics
$attendance_stats = array(
    'total_records' => $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'],
    'present' => $conn->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'Present'")->fetch_assoc()['count'],
    'absent' => $conn->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'Absent'")->fetch_assoc()['count'],
    'by_date' => $conn->query("
        SELECT DATE(attendance_date) as date,
               SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
               SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent
        FROM attendance 
        WHERE attendance_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(attendance_date)
        ORDER BY date DESC
    ")->fetch_all(MYSQLI_ASSOC)
);

// Report 5: Marks Statistics
$marks_stats = array(
    'total_records' => $conn->query("SELECT COUNT(*) as count FROM marks")->fetch_assoc()['count'],
    'avg_marks' => $conn->query("SELECT AVG(marks_obtained) as avg FROM marks")->fetch_assoc()['avg'],
    'by_exam_type' => $conn->query("
        SELECT exam_type, COUNT(*) as total, AVG(marks_obtained) as avg_marks, MAX(marks_obtained) as max, MIN(marks_obtained) as min
        FROM marks 
        GROUP BY exam_type
    ")->fetch_all(MYSQLI_ASSOC),
    'by_course' => $conn->query("
        SELECT c.course_name, COUNT(m.mark_id) as total, AVG(m.marks_obtained) as avg_marks
        FROM marks m
        JOIN enrollments e ON m.enrollment_id = e.enrollment_id
        JOIN courses c ON e.course_id = c.course_id
        GROUP BY c.course_id, c.course_name
        ORDER BY avg_marks DESC
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC)
);

// Report 6: Library Statistics
$library_stats = array(
    'total_books' => countStats('books'),
    'available_books' => countStats('available_books'),
    'issued_books' => countStats('issued_books'),
    'by_department' => $conn->query("
        SELECT d.dept_name, COUNT(lb.book_id) as total, SUM(lb.available_copies) as available
        FROM library_books lb
        LEFT JOIN departments d ON lb.department = d.dept_id
        GROUP BY d.dept_id, d.dept_name
    ")->fetch_all(MYSQLI_ASSOC),
    'issued_details' => $conn->query("
        SELECT DATE(issue_date) as date, COUNT(*) as count
        FROM library_issues 
        WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(issue_date)
        ORDER BY date DESC
    ")->fetch_all(MYSQLI_ASSOC)
);

// Report 7: Fee Statistics
$fee_stats = $conn->query("
    SELECT 
        SUM(total_fee) as total_fees,
        SUM(amount_paid) as amount_collected,
        SUM(due_amount) as amount_due,
        COUNT(DISTINCT student_id) as total_students,
        COUNT(CASE WHEN due_amount > 0 THEN 1 END) as pending_students
    FROM fees
")->fetch_assoc();

$fee_by_dept = $conn->query("
    SELECT d.dept_name, COUNT(f.fee_id) as students, SUM(f.total_fee) as total, SUM(f.amount_paid) as collected, SUM(f.due_amount) as pending
    FROM fees f
    JOIN students s ON f.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN departments d ON u.dept_id = d.dept_id
    GROUP BY d.dept_id, d.dept_name
")->fetch_all(MYSQLI_ASSOC);

// Report 8: User Activity
$activity_stats = $conn->query("
    SELECT u.full_name, r.role_name, COUNT(al.log_id) as actions, MAX(al.timestamp) as last_activity
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN activity_log al ON u.user_id = al.user_id
    GROUP BY u.user_id, u.full_name, r.role_name
    ORDER BY last_activity DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-chart-bar"></i> System Reports</h1>

            <!-- Report Navigation -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Select Report Type</h5>
                </div>
                <div class="card-body">
                    <div class="btn-group" role="group">
                        <a href="?type=overview" class="btn <?php echo $report_type == 'overview' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-chart-pie"></i> Overview
                        </a>
                        <a href="?type=students" class="btn <?php echo $report_type == 'students' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-user-graduate"></i> Students
                        </a>
                        <a href="?type=faculty" class="btn <?php echo $report_type == 'faculty' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-chalkboard-user"></i> Faculty
                        </a>
                        <a href="?type=courses" class="btn <?php echo $report_type == 'courses' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-book"></i> Courses
                        </a>
                        <a href="?type=attendance" class="btn <?php echo $report_type == 'attendance' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-clipboard-list"></i> Attendance
                        </a>
                        <a href="?type=marks" class="btn <?php echo $report_type == 'marks' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-chart-line"></i> Marks
                        </a>
                        <a href="?type=library" class="btn <?php echo $report_type == 'library' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-library"></i> Library
                        </a>
                        <a href="?type=fees" class="btn <?php echo $report_type == 'fees' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-dollar-sign"></i> Fees
                        </a>
                        <a href="?type=activity" class="btn <?php echo $report_type == 'activity' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-history"></i> Activity
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($report_type == 'overview' || $report_type == ''): ?>
                <!-- OVERVIEW REPORT -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6>Total Students</h6>
                                <h2><?php echo $student_stats['total']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6>Total Faculty</h6>
                                <h2><?php echo $faculty_stats['total']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h6>Total Courses</h6>
                                <h2><?php echo $course_stats['total_courses']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h6>Total Enrollments</h6>
                                <h2><?php echo $course_stats['total_enrollments']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Students by Department</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="overviewStudentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Enrollments by Semester</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="overviewEnrollmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'students'): ?>
                <!-- STUDENT REPORT -->
                <h3 class="mb-3"><i class="fas fa-user-graduate"></i> Student Report</h3>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Students by Department</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Department</th>
                                            <th>Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($student_stats['by_dept'] as $dept): ?>
                                            <tr>
                                                <td><?php echo $dept['dept_name']; ?></td>
                                                <td><span class="badge bg-primary"><?php echo $dept['count']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">Students by Admission Year</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Admission Year</th>
                                            <th>Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($student_stats['by_admission'] as $admission): ?>
                                            <tr>
                                                <td><?php echo $admission['admission_year']; ?></td>
                                                <td><span class="badge bg-success"><?php echo $admission['count']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'faculty'): ?>
                <!-- FACULTY REPORT -->
                <h3 class="mb-3"><i class="fas fa-chalkboard-user"></i> Faculty Report</h3>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Faculty by Department</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Department</th>
                                            <th>Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($faculty_stats['by_dept'] as $dept): ?>
                                            <tr>
                                                <td><?php echo $dept['dept_name']; ?></td>
                                                <td><span class="badge bg-primary"><?php echo $dept['count']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">Faculty by Designation</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Designation</th>
                                            <th>Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($faculty_stats['by_designation'] as $desig): ?>
                                            <tr>
                                                <td><?php echo $desig['designation']; ?></td>
                                                <td><span class="badge bg-success"><?php echo $desig['count']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'courses'): ?>
                <!-- COURSES REPORT -->
                <h3 class="mb-3"><i class="fas fa-book"></i> Courses & Enrollment Report</h3>

                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Enrollment by Semester</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Semester</th>
                                    <th>Courses</th>
                                    <th>Enrollments</th>
                                    <th>Avg per Course</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($course_stats['by_semester'] as $sem): ?>
                                    <tr>
                                        <td><span class="badge bg-primary">Semester <?php echo $sem['semester']; ?></span></td>
                                        <td><?php echo $sem['courses']; ?></td>
                                        <td><?php echo $sem['enrollments']; ?></td>
                                        <td><?php echo $sem['courses'] > 0 ? round($sem['enrollments'] / $sem['courses'], 2) : 0; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type == 'attendance'): ?>
                <!-- ATTENDANCE REPORT -->
                <h3 class="mb-3"><i class="fas fa-clipboard-list"></i> Attendance Report</h3>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h6>Total Records</h6>
                                <h2><?php echo $attendance_stats['total_records']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6>Present</h6>
                                <h2><?php echo $attendance_stats['present']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h6>Absent</h6>
                                <h2><?php echo $attendance_stats['absent']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Attendance Trend (Last 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 400px;">
                            <canvas id="attendanceTrendChart"></canvas>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'marks'): ?>
                <!-- MARKS REPORT -->
                <h3 class="mb-3"><i class="fas fa-chart-line"></i> Marks Report</h3>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Marks by Exam Type</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Exam Type</th>
                                            <th>Count</th>
                                            <th>Avg</th>
                                            <th>Min</th>
                                            <th>Max</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($marks_stats['by_exam_type'] as $exam): ?>
                                            <tr>
                                                <td><?php echo $exam['exam_type']; ?></td>
                                                <td><?php echo $exam['total']; ?></td>
                                                <td><?php echo round($exam['avg_marks'], 2); ?></td>
                                                <td><?php echo round($exam['min'], 2); ?></td>
                                                <td><?php echo round($exam['max'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">Top 10 Courses by Average Marks</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Course Name</th>
                                            <th>Total Records</th>
                                            <th>Avg Marks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($marks_stats['by_course'] as $course): ?>
                                            <tr>
                                                <td><?php echo substr($course['course_name'], 0, 25); ?></td>
                                                <td><?php echo $course['total']; ?></td>
                                                <td><?php echo round($course['avg_marks'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type == 'library'): ?>
                <!-- LIBRARY REPORT -->
                <h3 class="mb-3"><i class="fas fa-library"></i> Library Report</h3>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6>Total Books</h6>
                                <h2><?php echo $library_stats['total_books']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6>Available</h6>
                                <h2><?php echo $library_stats['available_books']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h6>Issued</h6>
                                <h2><?php echo $library_stats['issued_books']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Books by Department</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Department</th>
                                    <th>Total</th>
                                    <th>Available</th>
                                    <th>Issued</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($library_stats['by_department'] as $dept): ?>
                                    <tr>
                                        <td><?php echo $dept['dept_name'] ?? 'General'; ?></td>
                                        <td><?php echo $dept['total']; ?></td>
                                        <td><?php echo $dept['available']; ?></td>
                                        <td><?php echo $dept['total'] - $dept['available']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type == 'fees'): ?>
                <!-- FEE REPORT -->
                <h3 class="mb-3"><i class="fas fa-dollar-sign"></i> Fee Report</h3>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6>Total Fees</h6>
                                <h3>₹<?php echo number_format($fee_stats['total_fees'] ?? 0, 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6>Collected</h6>
                                <h3>₹<?php echo number_format($fee_stats['amount_collected'] ?? 0, 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h6>Pending</h6>
                                <h3>₹<?php echo number_format($fee_stats['amount_due'] ?? 0, 0); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h6>Collection Rate</h6>
                                <h3>
                                    <?php 
                                    $collection_rate = $fee_stats['total_fees'] > 0 ? ($fee_stats['amount_collected'] / $fee_stats['total_fees'] * 100) : 0;
                                    echo number_format($collection_rate, 1) . '%';
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Fee Collection by Department</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Department</th>
                                    <th>Students</th>
                                    <th>Total Fee</th>
                                    <th>Collected</th>
                                    <th>Pending</th>
                                    <th>Collection %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fee_by_dept as $dept): ?>
                                    <tr>
                                        <td><?php echo $dept['dept_name']; ?></td>
                                        <td><?php echo $dept['students']; ?></td>
                                        <td>₹<?php echo number_format($dept['total'], 0); ?></td>
                                        <td>₹<?php echo number_format($dept['collected'], 0); ?></td>
                                        <td>₹<?php echo number_format($dept['pending'], 0); ?></td>
                                        <td>
                                            <?php 
                                            $pct = $dept['total'] > 0 ? ($dept['collected'] / $dept['total'] * 100) : 0;
                                            echo number_format($pct, 1) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type == 'activity'): ?>
                <!-- ACTIVITY REPORT -->
                <h3 class="mb-3"><i class="fas fa-history"></i> User Activity Report</h3>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">User Activity Summary (Last 20 Active Users)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User Name</th>
                                    <th>Role</th>
                                    <th>Total Actions</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activity_stats as $activity): ?>
                                    <tr>
                                        <td><?php echo $activity['full_name']; ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $activity['role_name']; ?></span></td>
                                        <td><?php echo $activity['actions'] ?? 0; ?></td>
                                        <td><?php echo $activity['last_activity'] ? date('d-M-Y H:i', strtotime($activity['last_activity'])) : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Print Button -->
            <div class="mt-4">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button onclick="exportToCSV()" class="btn btn-success">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    function exportToCSV() {
        alert('Export functionality will be implemented soon!');
    }

    <?php if ($report_type == 'overview'): ?>
    // Overview Charts
    setTimeout(function() {
        const ctx1 = document.getElementById('overviewStudentChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($student_stats['by_dept'], 'dept_name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($student_stats['by_dept'], 'count')); ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        const ctx2 = document.getElementById('overviewEnrollmentChart');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($s) { return 'Sem ' . $s['semester']; }, $course_stats['by_semester'])); ?>,
                    datasets: [{
                        label: 'Enrollments',
                        data: <?php echo json_encode(array_column($course_stats['by_semester'], 'enrollments')); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }, 100);
    <?php elseif ($report_type == 'attendance'): ?>
    // Attendance Trend Chart
    setTimeout(function() {
        const attendanceData = <?php echo json_encode(array_reverse($attendance_stats['by_date'])); ?>;
        const ctx = document.getElementById('attendanceTrendChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: attendanceData.map(d => d.date),
                    datasets: [
                        {
                            label: 'Present',
                            data: attendanceData.map(d => d.present),
                            borderColor: 'rgba(75, 192, 75, 1)',
                            backgroundColor: 'rgba(75, 192, 75, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Absent',
                            data: attendanceData.map(d => d.absent),
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }, 100);
    <?php endif; ?>
</script>

<?php require_once('../../includes/footer.php'); ?>