<?php
$page_title = 'Mark Attendance';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Faculty')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$faculty = getFacultyInfo($_SESSION['user_id']);
$faculty_id = $faculty['faculty_id'];

$success = '';
$error = '';
$students_for_course = array();
$selected_course_id = 0;
$selected_date = date('Y-m-d');

// Get courses assigned to this faculty
$courses_query = "SELECT * FROM courses WHERE faculty_id = ? ORDER BY course_code";
$stmt = $conn->prepare($courses_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['load_students'])) {
        // Load students for selected course
        $selected_course_id = (int)$_POST['course_id'];
        $selected_date = sanitize($_POST['attendance_date']);
        
        $students_query = "
            SELECT s.student_id, s.roll_no, u.full_name, e.enrollment_id
            FROM enrollments e
            JOIN students s ON e.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            WHERE e.course_id = ?
            ORDER BY s.roll_no
        ";
        $stmt = $conn->prepare($students_query);
        $stmt->bind_param("i", $selected_course_id);
        $stmt->execute();
        $students_for_course = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } elseif (isset($_POST['mark_attendance'])) {
        // Save attendance
        $selected_course_id = (int)$_POST['course_id'];
        $selected_date = sanitize($_POST['attendance_date']);
        $attendances = isset($_POST['attendance']) ? $_POST['attendance'] : array();

        if (empty($attendances)) {
            $error = "Please mark attendance for at least one student";
        } else {
            $success_count = 0;
            $update_count = 0;
            
            foreach ($attendances as $enrollment_id => $status) {
                $enrollment_id = (int)$enrollment_id;
                $status = sanitize($status);

                // Check if attendance already exists
                $check_query = "SELECT attendance_id FROM attendance WHERE enrollment_id = ? AND attendance_date = ?";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("is", $enrollment_id, $selected_date);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existing) {
                    // Update existing attendance
                    $update_query = "UPDATE attendance SET status = ? WHERE enrollment_id = ? AND attendance_date = ?";
                    $stmt = $conn->prepare($update_query);
                    $stmt->bind_param("sis", $status, $enrollment_id, $selected_date);
                    if ($stmt->execute()) {
                        $update_count++;
                    }
                    $stmt->close();
                } else {
                    // Insert new attendance
                    $insert_query = "INSERT INTO attendance (enrollment_id, attendance_date, status) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param("iss", $enrollment_id, $selected_date, $status);
                    if ($stmt->execute()) {
                        $success_count++;
                    }
                    $stmt->close();
                }
            }

            if ($success_count > 0 || $update_count > 0) {
                $total = $success_count + $update_count;
                $success = "Attendance marked successfully for $total students!";
                if ($update_count > 0) {
                    $success .= " ($update_count updated)";
                }
                logActivity($_SESSION['user_id'], "Marked attendance for course ID: $selected_course_id on $selected_date");
            }
        }
        
        // Reload students to show updated attendance
        $students_query = "
            SELECT s.student_id, s.roll_no, u.full_name, e.enrollment_id
            FROM enrollments e
            JOIN students s ON e.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            WHERE e.course_id = ?
            ORDER BY s.roll_no
        ";
        $stmt = $conn->prepare($students_query);
        $stmt->bind_param("i", $selected_course_id);
        $stmt->execute();
        $students_for_course = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>

<style>
    .attendance-radio-group {
        display: flex;
        gap: 20px;
        align-items: center;
    }
    
    .attendance-radio-group label {
        margin: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 8px 15px;
        border-radius: 6px;
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    
    .attendance-radio-group input[type="radio"] {
        cursor: pointer;
        width: 18px;
        height: 18px;
    }
    
    .radio-present:has(input:checked) {
        background-color: #d4edda;
        border-color: #28a745;
        font-weight: 600;
    }
    
    .radio-absent:has(input:checked) {
        background-color: #f8d7da;
        border-color: #dc3545;
        font-weight: 600;
    }
</style>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-clipboard-list"></i> Mark Attendance</h1>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Select Course & Date Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-book"></i> Select Course & Date</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                                <select class="form-control" id="course_id" name="course_id" required>
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['course_id']; ?>" 
                                                <?php echo $selected_course_id == $course['course_id'] ? 'selected' : ''; ?>>
                                            <?php echo $course['course_code']; ?> - <?php echo $course['course_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="attendance_date" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="attendance_date" name="attendance_date" 
                                       value="<?php echo $selected_date; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" name="load_students" class="btn btn-primary w-100">
                                    <i class="fas fa-users"></i> Load Students
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Attendance Marking Card -->
            <?php if (!empty($students_for_course)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle"></i> Mark Attendance 
                            <span class="badge bg-white text-success"><?php echo count($students_for_course); ?> Students</span>
                        </h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-light me-2" onclick="markAll('Present')">
                                <i class="fas fa-check"></i> All Present
                            </button>
                            <button type="button" class="btn btn-sm btn-warning" onclick="markAll('Absent')">
                                <i class="fas fa-times"></i> All Absent
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="mark_attendance" value="1">
                            <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                            <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="15%">Roll No</th>
                                            <th width="35%">Student Name</th>
                                            <th width="50%">Attendance Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students_for_course as $student): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($student['roll_no']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                <td>
                                                    <div class="attendance-radio-group">
                                                        <label class="radio-present">
                                                            <input type="radio" 
                                                                   name="attendance[<?php echo $student['enrollment_id']; ?>]" 
                                                                   value="Present" 
                                                                   class="attendance-radio"
                                                                   required>
                                                            <span><i class="fas fa-check-circle text-success"></i> Present</span>
                                                        </label>
                                                        <label class="radio-absent">
                                                            <input type="radio" 
                                                                   name="attendance[<?php echo $student['enrollment_id']; ?>]" 
                                                                   value="Absent" 
                                                                   class="attendance-radio"
                                                                   required>
                                                            <span><i class="fas fa-times-circle text-danger"></i> Absent</span>
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-save"></i> Save Attendance
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif (isset($_POST['load_students'])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No students are enrolled in this course yet.
                </div>
            <?php endif; ?>

            <!-- Recent Attendance Records -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Recent Attendance Records</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Total</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recent_query = "
                                SELECT a.attendance_date, c.course_code, c.course_name,
                                       SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
                                       SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
                                       COUNT(*) as total
                                FROM attendance a
                                JOIN enrollments e ON a.enrollment_id = e.enrollment_id
                                JOIN courses c ON e.course_id = c.course_id
                                WHERE c.faculty_id = ?
                                GROUP BY a.attendance_date, c.course_id, c.course_code, c.course_name
                                ORDER BY a.attendance_date DESC
                                LIMIT 15
                            ";
                            $stmt = $conn->prepare($recent_query);
                            $stmt->bind_param("i", $faculty_id);
                            $stmt->execute();
                            $recent_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            $stmt->close();

                            if (count($recent_records) > 0): 
                                foreach ($recent_records as $record): 
                                    $percentage = ($record['total'] > 0) ? round(($record['present'] / $record['total']) * 100, 1) : 0;
                                    $percentage_class = $percentage >= 75 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                            ?>
                                <tr>
                                    <td><strong><?php echo date('d M Y', strtotime($record['attendance_date'])); ?></strong></td>
                                    <td><?php echo htmlspecialchars($record['course_code']); ?> - <?php echo htmlspecialchars($record['course_name']); ?></td>
                                    <td><span class="badge bg-success"><?php echo $record['present']; ?></span></td>
                                    <td><span class="badge bg-danger"><?php echo $record['absent']; ?></span></td>
                                    <td><strong><?php echo $record['total']; ?></strong></td>
                                    <td>
                                        <div class="progress" style="height: 25px; min-width: 100px;">
                                            <div class="progress-bar bg-<?php echo $percentage_class; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%" 
                                                 aria-valuenow="<?php echo $percentage; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $percentage; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            else: 
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                                        <p class="mb-0">No attendance records yet. Start marking attendance to see records here.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function markAll(status) {
    const radios = document.querySelectorAll('input[type="radio"][value="' + status + '"]');
    radios.forEach(radio => {
        radio.checked = true;
    });
}

// Prevent accidental form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php require_once('../../includes/footer.php'); ?>