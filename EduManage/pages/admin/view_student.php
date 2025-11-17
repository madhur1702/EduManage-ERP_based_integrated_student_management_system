<?php
$page_title = 'View Students';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Admin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

// Get all departments
$depts = $conn->query("SELECT * FROM departments ORDER BY dept_name")->fetch_all(MYSQLI_ASSOC);

$selected_dept = isset($_POST['dept_filter']) ? (int)$_POST['dept_filter'] : 0;
$selected_year = isset($_POST['year_filter']) ? (int)$_POST['year_filter'] : 0;
$search_query = isset($_POST['search_query']) ? sanitize($_POST['search_query']) : '';
$students_list = array();

// Build WHERE clause
$where_conditions = array();
$where_conditions[] = "u.role_id = 4"; // Only students

if ($selected_dept > 0) {
    $where_conditions[] = "u.dept_id = $selected_dept";
}

if ($selected_year > 0) {
    $where_conditions[] = "s.admission_year = $selected_year";
}

if (!empty($search_query)) {
    $where_conditions[] = "(s.roll_no LIKE '%$search_query%' OR u.full_name LIKE '%$search_query%' OR u.email LIKE '%$search_query%')";
}

$where_clause = implode(' AND ', $where_conditions);

// Get students based on filters
$students_sql = "
    SELECT 
        s.student_id, s.roll_no, s.admission_year, s.dob, s.address, s.guardian_name, s.guardian_contact,
        u.user_id, u.full_name, u.email, u.phone, u.created_at,
        d.dept_id, d.dept_name,
        COUNT(DISTINCT e.enrollment_id) as total_enrollments,
        COUNT(DISTINCT a.attendance_id) as total_attendance
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN departments d ON u.dept_id = d.dept_id
    LEFT JOIN enrollments e ON s.student_id = e.student_id
    LEFT JOIN attendance a ON e.enrollment_id = a.enrollment_id
    WHERE $where_clause
    GROUP BY s.student_id, s.roll_no, s.admission_year, s.dob, s.address, s.guardian_name, s.guardian_contact,
             u.user_id, u.full_name, u.email, u.phone, u.created_at, d.dept_id, d.dept_name
    ORDER BY d.dept_name, s.admission_year DESC, s.roll_no
";

$result = $conn->query($students_sql);
if ($result) {
    $students_list = $result->fetch_all(MYSQLI_ASSOC);
}

// Get unique admission years for filter
$years_sql = "SELECT DISTINCT s.admission_year FROM students s ORDER BY s.admission_year DESC";
$years_result = $conn->query($years_sql);
$admission_years = $years_result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_students_sql = "SELECT COUNT(*) as total FROM students";
$total_students_count = $conn->query($total_students_sql)->fetch_assoc()['total'];

$dept_stats_sql = "
    SELECT 
        d.dept_id, d.dept_name, COUNT(s.student_id) as student_count
    FROM departments d
    LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role_id = 4
    LEFT JOIN students s ON u.user_id = s.user_id
    GROUP BY d.dept_id, d.dept_name
    ORDER BY d.dept_name
";
$dept_stats = $conn->query($dept_stats_sql)->fetch_all(MYSQLI_ASSOC);

// Function to get detailed student data for modal
function getStudentDetailedData($student_id, $conn) {
    $data = array();
    
    // Get enrollments
    $enrollments_sql = "
        SELECT 
            e.enrollment_id, e.semester, e.academic_year,
            c.course_id, c.course_code, c.course_name,
            COALESCE(u.full_name, 'Not Assigned') as faculty_name,
            COUNT(DISTINCT a.attendance_id) as total_classes,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as classes_present
        FROM enrollments e
        JOIN courses c ON e.course_id = c.course_id
        LEFT JOIN faculty f ON c.faculty_id = f.faculty_id
        LEFT JOIN users u ON f.user_id = u.user_id
        LEFT JOIN attendance a ON e.enrollment_id = a.enrollment_id
        WHERE e.student_id = $student_id
        GROUP BY e.enrollment_id, c.course_id, e.semester, e.academic_year,
                 c.course_code, c.course_name, u.full_name
        ORDER BY e.academic_year DESC, e.semester DESC
    ";
    $result = $conn->query($enrollments_sql);
    $data['enrollments'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();
    
    // Get attendance stats
    $attendance_sql = "
        SELECT 
            COUNT(DISTINCT a.attendance_id) as total_attendance,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
            ROUND(SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / COUNT(DISTINCT a.attendance_id) * 100, 2) as attendance_percentage
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.enrollment_id
        WHERE e.student_id = $student_id
    ";
    $result = $conn->query($attendance_sql);
    $data['attendance'] = $result && $result->num_rows > 0 ? $result->fetch_assoc() : array('total_attendance' => 0, 'present_count' => 0, 'absent_count' => 0, 'attendance_percentage' => 0);
    
    // Get marks
    $marks_sql = "
        SELECT 
            m.mark_id, m.exam_type, m.marks_obtained, m.max_marks, m.exam_date,
            c.course_code, c.course_name,
            ROUND((m.marks_obtained / m.max_marks) * 100, 2) as percentage
        FROM marks m
        JOIN enrollments e ON m.enrollment_id = e.enrollment_id
        JOIN courses c ON e.course_id = c.course_id
        WHERE e.student_id = $student_id
        ORDER BY m.exam_date DESC
        LIMIT 10
    ";
    $result = $conn->query($marks_sql);
    $data['marks'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();
    
    // Get fee info
    $fee_sql = "SELECT * FROM fees WHERE student_id = $student_id";
    $result = $conn->query($fee_sql);
    $data['fee'] = $result && $result->num_rows > 0 ? $result->fetch_assoc() : array();
    
    return $data;
}

// Handle print request
$print_student_id = isset($_GET['print']) ? (int)$_GET['print'] : 0;
if ($print_student_id > 0) {
    // Get student info
    $student_sql = "
        SELECT 
            s.student_id, s.roll_no, s.admission_year, s.dob, s.address, 
            s.guardian_name, s.guardian_contact,
            u.user_id, u.full_name, u.email, u.phone, u.created_at,
            d.dept_name, d.dept_id
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        JOIN departments d ON u.dept_id = d.dept_id
        WHERE s.student_id = $print_student_id
    ";
    
    $result = $conn->query($student_sql);
    if ($result && $result->num_rows > 0) {
        $print_student = $result->fetch_assoc();
        $print_data = getStudentDetailedData($print_student_id, $conn);
        
        // Generate PDF-like print view
        ?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Report - <?php echo htmlspecialchars($print_student['roll_no']); ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        .print-container {
            width: 8.5in;
            height: 11in;
            margin: 20px auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .college-name {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
            color: #333;
        }
        .report-date {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background: #667eea;
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            margin-bottom: 12px;
            border-radius: 4px;
        }
        .row {
            display: flex;
            margin-bottom: 8px;
        }
        .label {
            width: 150px;
            font-weight: bold;
            color: #333;
        }
        .value {
            flex: 1;
            color: #666;
        }
        .separator {
            border-top: 1px solid #ddd;
            margin: 15px 0;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        .stat-box {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
            background: #f9f9f9;
        }
        .stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th {
            background: #f0f0f0;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }
        table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            background: #667eea;
            color: white;
            border-radius: 3px;
            font-size: 11px;
            margin-right: 5px;
        }
        @media print {
            body {
                background: white;
            }
            .print-container {
                box-shadow: none;
                margin: 0;
                width: 100%;
                height: auto;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- Header -->
        <div class="header">
            <div class="college-name">🎓 College Management System</div>
            <div class="report-title">Student Detailed Report</div>
            <div class="report-date">Generated on: <?php echo date('d-M-Y H:i:s'); ?></div>
        </div>

        <!-- Personal Information -->
        <div class="section">
            <div class="section-title">📋 Personal Information</div>
            <div class="row">
                <div class="label">Full Name:</div>
                <div class="value"><?php echo htmlspecialchars($print_student['full_name']); ?></div>
            </div>
            <div class="row">
                <div class="label">Roll No:</div>
                <div class="value"><?php echo htmlspecialchars($print_student['roll_no']); ?></div>
            </div>
            <div class="row">
                <div class="label">Email:</div>
                <div class="value"><?php echo htmlspecialchars($print_student['email']); ?></div>
            </div>
            <div class="row">
                <div class="label">Phone:</div>
                <div class="value"><?php echo htmlspecialchars($print_student['phone']); ?></div>
            </div>
            <div class="row">
                <div class="label">Date of Birth:</div>
                <div class="value"><?php echo $print_student['dob'] ? date('d-M-Y', strtotime($print_student['dob'])) : 'N/A'; ?></div>
            </div>
            <div class="row">
                <div class="label">Address:</div>
                <div class="value"><?php echo htmlspecialchars($print_student['address'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="section">
            <div class="section-title">🎓 Academic Information</div>
            <div class="row">
                <div class="label">Department:</div>
                <div class="value"><?php echo htmlspecialchars($print_student['dept_name']); ?></div>
            </div>
            <div class="row">
                <div class="label">Admission Year:</div>
                <div class="value"><?php echo $print_student['admission_year']; ?></div>
            </div>
            <div class="row">
                <div class="label">Account Created:</div>
                <div class="value"><?php echo date('d-M-Y', strtotime($print_student['created_at'])); ?></div>
            </div>
        </div>

        <!-- Guardian Information -->
        <div class="section">
            <div class="section-title">👨‍👩‍👧 Guardian Information</div>
            <div class="row">
                <div class="label">Guardian Name:</div>
                <div class="value"><?php echo htmlspecialchars($print_student['guardian_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="row">
                <div class="label">Guardian Contact:</div>
                <div class="value"><?php echo htmlspecialchars($print_student['guardian_contact'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="section">
            <div class="section-title">📊 Statistics</div>
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-number"><?php echo count($print_data['enrollments']); ?></div>
                    <div class="stat-label">Courses</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $print_data['attendance']['total_attendance'] ?? 0; ?></div>
                    <div class="stat-label">Attendance</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo ($print_data['attendance']['attendance_percentage'] ?? 0) . '%'; ?></div>
                    <div class="stat-label">Attendance %</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo count($print_data['marks']); ?></div>
                    <div class="stat-label">Marks Records</div>
                </div>
            </div>
        </div>

        <!-- Attendance Details -->
        <div class="section">
            <div class="section-title">✅ Attendance Details</div>
            <div class="row">
                <div class="label">Total Classes:</div>
                <div class="value"><?php echo $print_data['attendance']['total_attendance'] ?? 0; ?></div>
            </div>
            <div class="row">
                <div class="label">Present:</div>
                <div class="value"><span class="badge">✓</span><?php echo $print_data['attendance']['present_count'] ?? 0; ?></div>
            </div>
            <div class="row">
                <div class="label">Absent:</div>
                <div class="value"><span class="badge" style="background: #dc3545;">✗</span><?php echo $print_data['attendance']['absent_count'] ?? 0; ?></div>
            </div>
            <div class="row">
                <div class="label">Attendance %:</div>
                <div class="value"><?php echo ($print_data['attendance']['attendance_percentage'] ?? 0) . '%'; ?></div>
            </div>
        </div>

        <!-- Course Enrollments -->
        <?php if (count($print_data['enrollments']) > 0): ?>
        <div class="section">
            <div class="section-title">📚 Course Enrollments</div>
            <table>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Sem</th>
                    <th>Year</th>
                    <th>Faculty</th>
                </tr>
                <?php foreach ($print_data['enrollments'] as $enrollment): ?>
                <tr>
                    <td><?php echo htmlspecialchars($enrollment['course_code']); ?></td>
                    <td><?php echo htmlspecialchars(substr($enrollment['course_name'], 0, 20)); ?></td>
                    <td><?php echo $enrollment['semester']; ?></td>
                    <td><?php echo $enrollment['academic_year']; ?></td>
                    <td><?php echo htmlspecialchars(substr($enrollment['faculty_name'], 0, 15)); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>

        <!-- Latest Marks -->
        <?php if (count($print_data['marks']) > 0): ?>
        <div class="section">
            <div class="section-title">📈 Latest Marks</div>
            <table>
                <tr>
                    <th>Course</th>
                    <th>Exam Type</th>
                    <th>Marks</th>
                    <th>%</th>
                    <th>Date</th>
                </tr>
                <?php foreach (array_slice($print_data['marks'], 0, 5) as $mark): ?>
                <tr>
                    <td><?php echo htmlspecialchars($mark['course_code']); ?></td>
                    <td><?php echo htmlspecialchars($mark['exam_type']); ?></td>
                    <td><?php echo $mark['marks_obtained'] . '/' . $mark['max_marks']; ?></td>
                    <td><?php echo $mark['percentage']; ?>%</td>
                    <td><?php echo date('d-M-Y', strtotime($mark['exam_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>

        <!-- Fee Information -->
        <?php if (!empty($print_data['fee'])): ?>
        <div class="section">
            <div class="section-title">💳 Fee Information</div>
            <div class="row">
                <div class="label">Total Fee:</div>
                <div class="value">₹<?php echo number_format($print_data['fee']['total_fee'], 2); ?></div>
            </div>
            <div class="row">
                <div class="label">Amount Paid:</div>
                <div class="value">₹<?php echo number_format($print_data['fee']['amount_paid'], 2); ?></div>
            </div>
            <div class="row">
                <div class="label">Amount Due:</div>
                <div class="value">₹<?php echo number_format($print_data['fee']['due_amount'], 2); ?></div>
            </div>
            <div class="row">
                <div class="label">Payment Status:</div>
                <div class="value">
                    <?php 
                        $payment_rate = $print_data['fee']['total_fee'] > 0 ? ($print_data['fee']['amount_paid'] / $print_data['fee']['total_fee'] * 100) : 0;
                        echo number_format($payment_rate, 1) . '%';
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            Generated by <?php echo $_SESSION['username'] ?? 'Admin'; ?> | College Management System<br>
            Report generated on: <?php echo date('d-M-Y H:i:s'); ?>
        </div>
    </div>

    <script>
        window.print();
    </script>
</body>
</html>
        <?php
        exit;
    }
}
?>

<style>
    .filter-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .filter-group {
        margin-bottom: 15px;
    }

    .filter-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: white;
    }

    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 10px;
        border: none;
        border-radius: 5px;
        background: white;
        color: #333;
    }

    .dept-badge {
        display: inline-block;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin: 5px 5px 5px 0;
    }

    .dept-computer {
        background: #e3f2fd;
        color: #1976d2;
    }

    .dept-it {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    .dept-entc {
        background: #e8f5e9;
        color: #388e3c;
    }

    .student-row {
        background: white;
        padding: 15px;
        margin-bottom: 10px;
        border-left: 4px solid #667eea;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .student-row:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .student-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .student-basic {
        flex: 1;
        min-width: 200px;
    }

    .student-stats {
        display: flex;
        gap: 20px;
        margin-top: 10px;
        flex-wrap: wrap;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }

    .stat-item i {
        color: #667eea;
        font-weight: bold;
    }

    .dept-stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        border: 2px solid #e0e0e0;
    }

    .stat-card h3 {
        margin: 0 0 5px 0;
        font-size: 24px;
        color: #667eea;
    }

    .stat-card p {
        margin: 0;
        font-size: 14px;
        color: #666;
    }

    .no-results {
        text-align: center;
        padding: 40px;
        background: #f5f5f5;
        border-radius: 8px;
        margin: 20px 0;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
    }

    .filter-results {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding: 10px 15px;
        background: #e3f2fd;
        border-radius: 5px;
    }

    .result-count {
        font-weight: 600;
        color: #1976d2;
    }

    .clear-filters {
        background: #ff6b6b;
        border: none;
        color: white;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 12px;
    }

    .clear-filters:hover {
        background: #ee5a52;
    }

    /* Modal Styles */
    .detail-section {
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
    }

    .detail-section:last-child {
        border-bottom: none;
    }

    .detail-section-title {
        font-size: 14px;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-label {
        font-weight: 600;
        color: #333;
        min-width: 150px;
    }

    .detail-value {
        color: #666;
        text-align: right;
        flex: 1;
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin: 15px 0;
    }

    .stat-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
    }

    .stat-box-number {
        font-size: 24px;
        font-weight: bold;
        display: block;
    }

    .stat-box-label {
        font-size: 12px;
        margin-top: 5px;
        opacity: 0.9;
    }

    .enrollment-item {
        background: #f8f9fa;
        padding: 12px;
        margin-bottom: 10px;
        border-left: 3px solid #667eea;
        border-radius: 4px;
    }

    .course-code {
        font-weight: 700;
        color: #667eea;
        display: block;
    }

    .course-name {
        font-size: 13px;
        color: #666;
        margin: 3px 0;
    }

    .course-meta {
        font-size: 12px;
        color: #999;
        margin-top: 5px;
    }

    .attendance-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        margin-right: 5px;
    }

    .attendance-present {
        background: #d4edda;
        color: #155724;
    }

    .attendance-absent {
        background: #f8d7da;
        color: #721c24;
    }

    .marks-item {
        background: #f8f9fa;
        padding: 12px;
        margin-bottom: 10px;
        border-left: 3px solid #28a745;
        border-radius: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .marks-score {
        font-weight: 700;
        font-size: 16px;
        color: #667eea;
    }

    .fee-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .fee-row:last-child {
        border-bottom: none;
    }

    .fee-label {
        font-weight: 600;
        color: #333;
    }

    .fee-value {
        font-weight: 700;
        color: #667eea;
    }

    .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }

    .modal-body::-webkit-scrollbar {
        width: 8px;
    }

    .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 4px;
    }

    .modal-body::-webkit-scrollbar-thumb:hover {
        background: #764ba2;
    }

    .modal-footer .btn-print {
        background: #28a745;
        color: white;
        border: none;
    }

    .modal-footer .btn-print:hover {
        background: #218838;
    }
</style>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-users"></i> View Students</h1>

            <!-- Department Statistics -->
            <div class="dept-stats-cards">
                <div class="stat-card">
                    <h3><?php echo $total_students_count; ?></h3>
                    <p>Total Students</p>
                </div>
                <?php foreach ($dept_stats as $stat): ?>
                    <div class="stat-card">
                        <h3><?php echo $stat['student_count']; ?></h3>
                        <p><?php echo htmlspecialchars($stat['dept_name']); ?> Students</p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Filter Card -->
            <div class="filter-card">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="filter-group">
                                <label for="dept_filter">Filter by Department</label>
                                <select id="dept_filter" name="dept_filter">
                                    <option value="0">-- All Departments --</option>
                                    <?php foreach ($depts as $dept): ?>
                                        <option value="<?php echo $dept['dept_id']; ?>" 
                                                <?php echo $selected_dept == $dept['dept_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="filter-group">
                                <label for="year_filter">Filter by Admission Year</label>
                                <select id="year_filter" name="year_filter">
                                    <option value="0">-- All Years --</option>
                                    <?php foreach ($admission_years as $year): ?>
                                        <option value="<?php echo $year['admission_year']; ?>" 
                                                <?php echo $selected_year == $year['admission_year'] ? 'selected' : ''; ?>>
                                            Year <?php echo $year['admission_year']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="filter-group">
                                <label for="search_query">Search Student</label>
                                <input type="text" id="search_query" name="search_query" 
                                       placeholder="Roll No, Name, or Email"
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="filter-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-light w-100" style="font-weight: 600;">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Filter Results Summary -->
            <?php if ($selected_dept > 0 || $selected_year > 0 || !empty($search_query)): ?>
                <div class="filter-results">
                    <div class="result-count">
                        Found <strong><?php echo count($students_list); ?></strong> student(s)
                        <?php if ($selected_dept > 0): ?>
                            in <strong><?php echo htmlspecialchars($depts[array_search($selected_dept, array_column($depts, 'dept_id'))]['dept_name']); ?></strong>
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="" style="display: inline;">
                        <button type="submit" class="clear-filters">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Students List -->
            <?php if (count($students_list) > 0): ?>
                <div>
                    <?php foreach ($students_list as $student): 
                        $dept_class = 'dept-' . strtolower($student['dept_name']);
                    ?>
                        <div class="student-row">
                            <div class="student-info">
                                <div class="student-basic">
                                    <div style="margin-bottom: 8px;">
                                        <strong style="font-size: 16px;"><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                        <span class="dept-badge <?php echo $dept_class; ?>">
                                            <?php echo htmlspecialchars($student['dept_name']); ?>
                                        </span>
                                    </div>
                                    <div style="font-size: 13px; color: #666;">
                                        <span><strong>Roll No:</strong> <?php echo htmlspecialchars($student['roll_no']); ?></span> | 
                                        <span><strong>Year:</strong> <?php echo $student['admission_year']; ?></span>
                                    </div>
                                    <div style="font-size: 12px; color: #999; margin-top: 5px;">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?> | 
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($student['phone']); ?>
                                    </div>
                                </div>
                                <div class="student-stats">
                                    <div class="stat-item">
                                        <i class="fas fa-book"></i>
                                        <span><?php echo $student['total_enrollments']; ?> Courses</span>
                                    </div>
                                    <div class="stat-item">
                                        <i class="fas fa-clipboard-list"></i>
                                        <span><?php echo $student['total_attendance']; ?> Classes</span>
                                    </div>
                                </div>
                                &nbsp;&nbsp;&nbsp;&nbsp;
                                <div class="action-buttons" style="margin-top: 10px;">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                            data-bs-target="#detailsModal<?php echo $student['student_id']; ?>">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Comprehensive Details Modal -->
                        <div class="modal fade" id="detailsModal<?php echo $student['student_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <div>
                                            <h5 class="modal-title mb-0">
                                                <i class="fas fa-user-circle"></i> 
                                                <?php echo htmlspecialchars($student['full_name']); ?>
                                            </h5>
                                            <small><?php echo htmlspecialchars($student['roll_no']); ?> • <?php echo htmlspecialchars($student['dept_name']); ?></small>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php 
                                            $detailed_data = getStudentDetailedData($student['student_id'], $conn);
                                            $enrollments = $detailed_data['enrollments'];
                                            $attendance = $detailed_data['attendance'];
                                            $marks = $detailed_data['marks'];
                                            $fee = $detailed_data['fee'];
                                        ?>

                                        <!-- Personal Information Section -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">
                                                <i class="fas fa-user"></i> Personal Information
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Full Name</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($student['full_name']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Roll No</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($student['roll_no']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Email</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($student['email']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Phone</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($student['phone']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Date of Birth</span>
                                                <span class="detail-value"><?php echo $student['dob'] ? date('d-M-Y', strtotime($student['dob'])) : 'N/A'; ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Address</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></span>
                                            </div>
                                        </div>

                                        <!-- Academic Information Section -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">
                                                <i class="fas fa-graduation-cap"></i> Academic Information
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Department</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($student['dept_name']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Admission Year</span>
                                                <span class="detail-value"><?php echo $student['admission_year']; ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Account Created</span>
                                                <span class="detail-value"><?php echo date('d-M-Y H:i', strtotime($student['created_at'])); ?></span>
                                            </div>
                                        </div>

                                        <!-- Guardian Information Section -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">
                                                <i class="fas fa-shield-alt"></i> Guardian Information
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Guardian Name</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($student['guardian_name'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Guardian Contact</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($student['guardian_contact'] ?? 'N/A'); ?></span>
                                            </div>
                                        </div>

                                        <!-- Statistics Section -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">
                                                <i class="fas fa-chart-bar"></i> Statistics
                                            </div>
                                            <div class="stat-grid">
                                                <div class="stat-box">
                                                    <span class="stat-box-number"><?php echo count($enrollments); ?></span>
                                                    <span class="stat-box-label">Courses Enrolled</span>
                                                </div>
                                                <div class="stat-box">
                                                    <span class="stat-box-number"><?php echo $attendance['total_attendance'] ?? 0; ?></span>
                                                    <span class="stat-box-label">Attendance Records</span>
                                                </div>
                                                <div class="stat-box">
                                                    <span class="stat-box-number"><?php echo ($attendance['attendance_percentage'] ?? 0) . '%'; ?></span>
                                                    <span class="stat-box-label">Attendance %</span>
                                                </div>
                                                <div class="stat-box">
                                                    <span class="stat-box-number"><?php echo count($marks); ?></span>
                                                    <span class="stat-box-label">Marks Records</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Attendance Details -->
                                        <div class="detail-section">
                                            <div class="detail-section-title">
                                                <i class="fas fa-clipboard-list"></i> Attendance Details
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Total Classes</span>
                                                <span class="detail-value"><?php echo $attendance['total_attendance'] ?? 0; ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">
                                                    <span class="attendance-badge attendance-present">Present</span>
                                                </span>
                                                <span class="detail-value" style="text-align: left;"><?php echo $attendance['present_count'] ?? 0; ?> Classes</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">
                                                    <span class="attendance-badge attendance-absent">Absent</span>
                                                </span>
                                                <span class="detail-value" style="text-align: left;"><?php echo $attendance['absent_count'] ?? 0; ?> Classes</span>
                                            </div>
                                        </div>

                                        <!-- Course Enrollments -->
                                        <?php if (count($enrollments) > 0): ?>
                                            <div class="detail-section">
                                                <div class="detail-section-title">
                                                    <i class="fas fa-book"></i> Course Enrollments (<?php echo count($enrollments); ?>)
                                                </div>
                                                <?php foreach ($enrollments as $enrollment): ?>
                                                    <div class="enrollment-item">
                                                        <span class="course-code"><?php echo htmlspecialchars($enrollment['course_code']); ?></span>
                                                        <span class="course-name"><?php echo htmlspecialchars($enrollment['course_name']); ?></span>
                                                        <div class="course-meta">
                                                            📚 Semester <?php echo $enrollment['semester']; ?> | 
                                                            🎓 <?php echo $enrollment['academic_year']; ?> | 
                                                            👨‍🏫 <?php echo htmlspecialchars($enrollment['faculty_name']); ?><br>
                                                            ✓ <?php echo $enrollment['classes_present'] ?? 0; ?> / <?php echo $enrollment['total_classes'] ?? 0; ?> Classes
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Marks Records -->
                                        <?php if (count($marks) > 0): ?>
                                            <div class="detail-section">
                                                <div class="detail-section-title">
                                                    <i class="fas fa-chart-line"></i> Latest Marks (Top 10)
                                                </div>
                                                <?php foreach ($marks as $mark): 
                                                    $mark_color = $mark['percentage'] >= 80 ? '#28a745' : ($mark['percentage'] >= 60 ? '#ffc107' : '#dc3545');
                                                ?>
                                                    <div class="marks-item">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($mark['course_code']); ?></strong><br>
                                                            <small><?php echo htmlspecialchars($mark['exam_type']); ?> • <?php echo date('d-M-Y', strtotime($mark['exam_date'])); ?></small>
                                                        </div>
                                                        <div>
                                                            <span class="marks-score"><?php echo $mark['marks_obtained']; ?>/<?php echo $mark['max_marks']; ?></span><br>
                                                            <small style="color: <?php echo $mark_color; ?>; font-weight: 700;"><?php echo $mark['percentage']; ?>%</small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Fee Information -->
                                        <?php if (!empty($fee)): ?>
                                            <div class="detail-section">
                                                <div class="detail-section-title">
                                                    <i class="fas fa-dollar-sign"></i> Fee Information
                                                </div>
                                                <div class="fee-row">
                                                    <span class="fee-label">Total Fee</span>
                                                    <span class="fee-value">₹<?php echo number_format($fee['total_fee'], 2); ?></span>
                                                </div>
                                                <div class="fee-row">
                                                    <span class="fee-label">Amount Paid</span>
                                                    <span class="fee-value" style="color: #28a745;">₹<?php echo number_format($fee['amount_paid'], 2); ?></span>
                                                </div>
                                                <div class="fee-row">
                                                    <span class="fee-label">Amount Due</span>
                                                    <span class="fee-value" style="color: #dc3545;">₹<?php echo number_format($fee['due_amount'], 2); ?></span>
                                                </div>
                                                <div class="fee-row">
                                                    <span class="fee-label">Payment Status</span>
                                                    <span class="fee-value">
                                                        <?php 
                                                            $payment_rate = $fee['total_fee'] > 0 ? ($fee['amount_paid'] / $fee['total_fee'] * 100) : 0;
                                                            echo number_format($payment_rate, 1) . '%';
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="fee-row">
                                                    <span class="fee-label">Last Payment Date</span>
                                                    <span class="fee-value"><?php echo $fee['last_payment_date'] ? date('d-M-Y', strtotime($fee['last_payment_date'])) : 'N/A'; ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <a href="?print=<?php echo $student['student_id']; ?>" class="btn btn-print" target="_blank">
                                            <i class="fas fa-print"></i> Print Report
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search" style="font-size: 40px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                    <h5>No Students Found</h5>
                    <p>Try adjusting your filter criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once('../../includes/footer.php'); ?>