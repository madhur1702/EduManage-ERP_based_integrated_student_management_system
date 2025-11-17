<?php
$page_title = 'Manage Courses';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Admin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$success = '';
$error = '';
$auto_enrolled = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = sanitize($_POST['action']);

    if ($action == 'add') {
        $course_code = sanitize($_POST['course_code']);
        $course_name = sanitize($_POST['course_name']);
        $dept_id = (int)$_POST['dept_id'];
        $semester = (int)$_POST['semester'];
        $faculty_id = isset($_POST['faculty_id']) && !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : NULL;

        // Validate
        if (empty($course_code) || empty($course_name)) {
            $error = "Course code and name are required!";
        } elseif ($semester < 1 || $semester > 8) {
            $error = "Semester must be between 1 and 8!";
        } else {
            // Check if course code exists
            $check_result = $conn->query("SELECT course_id FROM courses WHERE course_code = '$course_code' LIMIT 1");
            
            if ($check_result && $check_result->num_rows > 0) {
                $error = "❌ Course code '$course_code' already exists!";
            } else {
                // Insert course
                if ($faculty_id) {
                    $insert_sql = "INSERT INTO courses (course_code, course_name, dept_id, semester, faculty_id) 
                                   VALUES ('$course_code', '$course_name', $dept_id, $semester, $faculty_id)";
                } else {
                    $insert_sql = "INSERT INTO courses (course_code, course_name, dept_id, semester) 
                                   VALUES ('$course_code', '$course_name', $dept_id, $semester)";
                }

                if ($conn->query($insert_sql)) {
                    $course_id = $conn->insert_id;
                    $academic_year = date('Y') . '-' . (date('Y') + 1);
                    $current_year = date('Y');

                    // DETERMINE WHICH ADMISSION YEAR SHOULD BE ENROLLED
                    // Semester 1-2 → Admission Year 2025 (Year 1)
                    // Semester 3-4 → Admission Year 2024 (Year 2)
                    // Semester 5-6 → Admission Year 2023 (Year 3)
                    // Semester 7-8 → Admission Year 2022 (Year 4)

                    $admission_year_to_enroll = NULL;
                    $year_level = '';

                    if ($semester >= 1 && $semester <= 2) {
                        $admission_year_to_enroll = 2025;
                        $year_level = 'Year 1 (2025)';
                    } elseif ($semester >= 3 && $semester <= 4) {
                        $admission_year_to_enroll = 2024;
                        $year_level = 'Year 2 (2024)';
                    } elseif ($semester >= 5 && $semester <= 6) {
                        $admission_year_to_enroll = 2023;
                        $year_level = 'Year 3 (2023)';
                    } elseif ($semester >= 7 && $semester <= 8) {
                        $admission_year_to_enroll = 2022;
                        $year_level = 'Year 4 (2022)';
                    }

                    // ENROLL ONLY STUDENTS FROM SPECIFIC ADMISSION YEAR AND DEPARTMENT
                    if ($admission_year_to_enroll) {
                        $enroll_sql = "
                            SELECT DISTINCT s.student_id
                            FROM students s
                            JOIN users u ON s.user_id = u.user_id
                            WHERE u.dept_id = $dept_id
                            AND s.admission_year = $admission_year_to_enroll
                            AND s.student_id NOT IN (
                                SELECT student_id FROM enrollments 
                                WHERE course_id = $course_id
                            )
                        ";

                        $result = $conn->query($enroll_sql);
                        
                        if ($result) {
                            $students_to_enroll = array();
                            while ($row = $result->fetch_assoc()) {
                                $students_to_enroll[] = $row['student_id'];
                            }

                            // Insert enrollments
                            foreach ($students_to_enroll as $student_id) {
                                $enroll_insert = "
                                    INSERT INTO enrollments (student_id, course_id, semester, academic_year) 
                                    VALUES ($student_id, $course_id, $semester, '$academic_year')
                                ";
                                if ($conn->query($enroll_insert)) {
                                    $auto_enrolled++;
                                }
                            }
                        }

                        if ($auto_enrolled > 0) {
                            $success = "✅ Course '$course_code' created successfully!<br>";
                            $success .= "🎓 Automatically enrolled <strong>$auto_enrolled students</strong> from <strong>$year_level</strong> in Semester $semester";
                        } else {
                            $success = "✅ Course '$course_code' created successfully!<br>";
                            $success .= "<small class='text-muted'><i class='fas fa-info-circle'></i> No students from <strong>$year_level</strong> to enroll</small>";
                        }

                        logActivity($_SESSION['user_id'], "Created course: $course_code - $course_name | $year_level | Auto-enrolled: $auto_enrolled");
                    } else {
                        $success = "✅ Course '$course_code' created successfully!";
                        logActivity($_SESSION['user_id'], "Created course: $course_code - $course_name");
                    }

                    $auto_enrolled = 0;
                } else {
                    $error = "❌ Failed to create course: " . $conn->error;
                }
            }
        }

    } elseif ($action == 'edit') {
        $course_id = (int)$_POST['course_id'];
        $course_name = sanitize($_POST['course_name']);
        $faculty_id = isset($_POST['faculty_id']) && !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : NULL;

        if ($faculty_id) {
            $update_sql = "UPDATE courses SET course_name = '$course_name', faculty_id = $faculty_id WHERE course_id = $course_id";
        } else {
            $update_sql = "UPDATE courses SET course_name = '$course_name', faculty_id = NULL WHERE course_id = $course_id";
        }

        if ($conn->query($update_sql)) {
            $success = "✅ Course updated successfully!";
            logActivity($_SESSION['user_id'], "Updated course: $course_name");
        } else {
            $error = "❌ Failed to update course: " . $conn->error;
        }

    } elseif ($action == 'delete') {
        $course_id = (int)$_POST['course_id'];

        // Check enrollments
        $check_result = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE course_id = $course_id");
        $row = $check_result->fetch_assoc();
        $enrollment_count = $row['count'];

        if ($enrollment_count > 0) {
            $error = "❌ Cannot delete! Course has $enrollment_count enrolled students. Please unenroll students first or delete enrollments.";
        } else {
            if ($conn->query("DELETE FROM courses WHERE course_id = $course_id")) {
                $success = "✅ Course deleted successfully!";
                logActivity($_SESSION['user_id'], "Deleted course ID: $course_id");
            } else {
                $error = "❌ Failed to delete course: " . $conn->error;
            }
        }
    }
}

// Get all courses with faculty and department info
$courses_sql = "
    SELECT c.*, 
           d.dept_name,
           COALESCE(u.full_name, 'Not Assigned') as faculty_name,
           COUNT(DISTINCT e.enrollment_id) as enrolled_count
    FROM courses c
    JOIN departments d ON c.dept_id = d.dept_id
    LEFT JOIN faculty f ON c.faculty_id = f.faculty_id
    LEFT JOIN users u ON f.user_id = u.user_id
    LEFT JOIN enrollments e ON c.course_id = e.course_id
    GROUP BY c.course_id
    ORDER BY c.course_code
";
$courses = $conn->query($courses_sql)->fetch_all(MYSQLI_ASSOC);

// Get departments
$depts = $conn->query("SELECT * FROM departments ORDER BY dept_name")->fetch_all(MYSQLI_ASSOC);

// Get faculty from faculty table with user info
$faculty_sql = "
    SELECT f.faculty_id, u.full_name, d.dept_name
    FROM faculty f
    JOIN users u ON f.user_id = u.user_id
    JOIN departments d ON u.dept_id = d.dept_id
    ORDER BY u.full_name
";
$faculty_list = $conn->query($faculty_sql)->fetch_all(MYSQLI_ASSOC);

// Get statistics for each department with year breakdown
$stats_sql = "
    SELECT d.dept_id, d.dept_name,
           COUNT(DISTINCT s.student_id) as total_students,
           COUNT(DISTINCT c.course_id) as total_courses,
           COUNT(DISTINCT e.enrollment_id) as total_enrollments
    FROM departments d
    LEFT JOIN students s ON s.user_id IN (SELECT user_id FROM users WHERE dept_id = d.dept_id)
    LEFT JOIN courses c ON c.dept_id = d.dept_id
    LEFT JOIN enrollments e ON c.course_id = e.course_id
    GROUP BY d.dept_id, d.dept_name
";
$dept_stats = $conn->query($stats_sql)->fetch_all(MYSQLI_ASSOC);

// Get year-level breakdown
$year_stats_sql = "
    SELECT 
        d.dept_name,
        s.admission_year,
        CASE 
            WHEN s.admission_year = 2025 THEN 'Year 1'
            WHEN s.admission_year = 2024 THEN 'Year 2'
            WHEN s.admission_year = 2023 THEN 'Year 3'
            WHEN s.admission_year = 2022 THEN 'Year 4'
        END as year_label,
        COUNT(s.student_id) as student_count
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN departments d ON u.dept_id = d.dept_id
    GROUP BY d.dept_id, d.dept_name, s.admission_year
    ORDER BY d.dept_name, s.admission_year DESC
";
$year_stats = $conn->query($year_stats_sql)->fetch_all(MYSQLI_ASSOC);
?>

<style>
    .auto-enroll-badge {
        display: inline-block;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 6px 14px;
        border-radius: 15px;
        font-size: 11px;
        font-weight: 600;
    }

    .auto-enroll-info {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
        font-weight: 500;
    }

    .course-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .enrolled-badge {
        background: #28a745;
        color: white;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }

    .stat-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        border-left: 4px solid #667eea;
    }

    .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #667eea;
    }

    .stat-label {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }

    .year-label-badge {
        display: inline-block;
        padding: 3px 8px;
        background: #e7f3ff;
        color: #0066cc;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        margin-right: 5px;
    }
</style>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-book"></i> Manage Courses</h1>

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

            <!-- Department Statistics with Year Breakdown -->
            <div class="row mb-4">
                <?php foreach ($dept_stats as $stat): ?>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stat['total_students']; ?></div>
                            <div class="stat-label">
                                <i class="fas fa-user-graduate"></i> 
                                <?php echo htmlspecialchars($stat['dept_name']); ?> Students
                            </div>
                            <small class="text-muted">
                                <?php echo $stat['total_courses']; ?> courses | 
                                <?php echo $stat['total_enrollments']; ?> enrollments
                            </small>
                            
                            <!-- Year breakdown -->
                            <div style="margin-top: 8px; font-size: 12px;">
                                <?php 
                                    $year_stats_filtered = array_filter($year_stats, function($y) use ($stat) {
                                        return $y['dept_name'] == $stat['dept_name'];
                                    });
                                    foreach ($year_stats_filtered as $ys):
                                ?>
                                    <span class="year-label-badge">
                                        <?php echo $ys['year_label']; ?>: <?php echo $ys['student_count']; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Add Course Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="course-header">
                        <h5 class="mb-0">
                            <i class="fas fa-plus-circle"></i> Add New Course
                        </h5>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="course_code" class="form-label">Course Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="course_code" name="course_code" 
                                       placeholder="e.g., COMP301" required>
                                <small class="text-muted">Unique course identifier</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="course_name" class="form-label">Course Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="course_name" name="course_name" 
                                       placeholder="e.g., Data Structures" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="dept_id" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-control" id="dept_id" name="dept_id" onchange="updateDeptInfo()" required>
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($depts as $dept): ?>
                                        <option value="<?php echo $dept['dept_id']; ?>">
                                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="semester" class="form-label">Semester <span class="text-danger">*</span></label>
                                <select class="form-control" id="semester" name="semester" onchange="updateSemesterInfo()" required>
                                    <option value="">Select Semester</option>
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="faculty_id" class="form-label">Faculty (Optional)</label>
                                <select class="form-control" id="faculty_id" name="faculty_id">
                                    <option value="">-- Not Assigned --</option>
                                    <?php foreach ($faculty_list as $faculty): ?>
                                        <option value="<?php echo $faculty['faculty_id']; ?>">
                                            <?php echo htmlspecialchars($faculty['full_name']); ?> 
                                            (<?php echo htmlspecialchars($faculty['dept_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg mt-3 w-100">
                            <i class="fas fa-plus"></i> Create Course & Auto-Enroll Students
                        </button>
                    </form>
                </div>
            </div>

            <!-- Courses Table -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <div class="course-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> All Courses (<?php echo count($courses); ?>)
                        </h5>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="coursesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Sem</th>
                                <th>Faculty</th>
                                <th>Enrolled</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($courses) > 0): ?>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($course['course_name'], 0, 30)); ?></td>
                                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($course['dept_name']); ?></span></td>
                                        <td><span class="badge bg-secondary"><?php echo $course['semester']; ?></span></td>
                                        <td><small><?php echo $course['faculty_name']; ?></small></td>
                                        <td>
                                            <span class="enrolled-badge">
                                                <?php echo $course['enrolled_count'] ?? 0; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $course['course_id']; ?>" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $course['course_id']; ?>" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $course['course_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-warning">
                                                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Course</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="edit">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Code:</strong></label>
                                                            <p><?php echo htmlspecialchars($course['course_code']); ?></p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Course Name</label>
                                                            <input type="text" class="form-control" name="course_name" 
                                                                   value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Faculty</label>
                                                            <select class="form-control" name="faculty_id">
                                                                <option value="">-- Not Assigned --</option>
                                                                <?php foreach ($faculty_list as $faculty): ?>
                                                                    <option value="<?php echo $faculty['faculty_id']; ?>" 
                                                                            <?php echo isset($course['faculty_id']) && $course['faculty_id'] == $faculty['faculty_id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($faculty['full_name']); ?> 
                                                                        (<?php echo htmlspecialchars($faculty['dept_name']); ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-warning">
                                                            <i class="fas fa-save"></i> Update
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $course['course_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title"><i class="fas fa-trash"></i> Delete Course</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Delete <strong><?php echo htmlspecialchars($course['course_code']); ?></strong>?</p>
                                                    <p><?php echo htmlspecialchars($course['course_name']); ?></p>
                                                    <p><strong>Enrolled:</strong> <span class="badge bg-danger"><?php echo $course['enrolled_count'] ?? 0; ?></span> students</p>
                                                    <p class="text-danger"><i class="fas fa-warning"></i> Cannot be undone!</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-3x mb-2 d-block"></i>
                                        No courses created yet
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
// Get year label based on semester
function getYearLabelFromSemester(semester) {
    if (semester >= 1 && semester <= 2) return 'Year 1 (2025)';
    if (semester >= 3 && semester <= 4) return 'Year 2 (2024)';
    if (semester >= 5 && semester <= 6) return 'Year 3 (2023)';
    if (semester >= 7 && semester <= 8) return 'Year 4 (2022)';
    return '?';
}

// Update semester info
function updateSemesterInfo() {
    const semester = document.getElementById('semester').value;
    const yearLabel = getYearLabelFromSemester(semester);
    
    document.getElementById('semDisplay').textContent = semester || '?';
    document.getElementById('yearDisplay').textContent = yearLabel;
}

// Update department info
function updateDeptInfo() {
    const deptSelect = document.getElementById('dept_id');
    const deptName = deptSelect.options[deptSelect.selectedIndex].text;
    document.getElementById('deptDisplay').textContent = deptName;
}

// Update both on load
document.addEventListener('DOMContentLoaded', function() {
    updateDeptInfo();
    updateSemesterInfo();
});

// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Initialize DataTable
$(document).ready(function() {
    $('#coursesTable').DataTable({
        "pageLength": 10,
        "order": [[0, "asc"]],
        "language": {
            "search": "Search courses:",
            "lengthMenu": "Show _MENU_ entries"
        }
    });
});
</script>

<?php require_once('../../includes/footer.php'); ?>