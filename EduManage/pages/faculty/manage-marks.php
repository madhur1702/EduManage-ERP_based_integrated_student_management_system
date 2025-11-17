<?php
$page_title = 'Manage Marks';
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
$selected_course_id = 0;
$enrollments_for_course = array();

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
        
        $enrollments_query = "
            SELECT e.enrollment_id, s.roll_no, u.full_name
            FROM enrollments e
            JOIN students s ON e.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            WHERE e.course_id = ?
            ORDER BY s.roll_no
        ";
        $stmt = $conn->prepare($enrollments_query);
        $stmt->bind_param("i", $selected_course_id);
        $stmt->execute();
        $enrollments_for_course = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } elseif (isset($_POST['add_marks'])) {
        $enrollment_id = (int)$_POST['enrollment_id'];
        $exam_type = sanitize($_POST['exam_type']);
        $marks_obtained = (float)$_POST['marks_obtained'];
        $max_marks = (float)$_POST['max_marks'];
        $exam_date = sanitize($_POST['exam_date']);

        // Validation
        if ($marks_obtained < 0 || $max_marks <= 0 || $marks_obtained > $max_marks) {
            $error = "Invalid marks. Marks must be between 0 and $max_marks";
        } else {
            $insert_query = "INSERT INTO marks (enrollment_id, exam_type, marks_obtained, max_marks, exam_date) 
                            VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("isdds", $enrollment_id, $exam_type, $marks_obtained, $max_marks, $exam_date);

            if ($stmt->execute()) {
                $success = "Marks added successfully!";
                logActivity($_SESSION['user_id'], "Added marks for enrollment $enrollment_id: $marks_obtained/$max_marks");
            } else {
                $error = "Failed to add marks: " . $conn->error;
            }
            $stmt->close();
        }
        
    } elseif (isset($_POST['update_marks'])) {
        $mark_id = (int)$_POST['mark_id'];
        $marks_obtained = (float)$_POST['marks_obtained'];
        $max_marks = (float)$_POST['max_marks'];

        if ($marks_obtained < 0 || $max_marks <= 0 || $marks_obtained > $max_marks) {
            $error = "Invalid marks. Marks must be between 0 and $max_marks";
        } else {
            $update_query = "UPDATE marks SET marks_obtained = ?, max_marks = ? WHERE mark_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ddi", $marks_obtained, $max_marks, $mark_id);

            if ($stmt->execute()) {
                $success = "Marks updated successfully!";
                logActivity($_SESSION['user_id'], "Updated marks for mark ID: $mark_id");
            } else {
                $error = "Failed to update marks: " . $conn->error;
            }
            $stmt->close();
        }
        
    } elseif (isset($_POST['delete_marks'])) {
        $mark_id = (int)$_POST['mark_id'];

        $delete_query = "DELETE FROM marks WHERE mark_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $mark_id);

        if ($stmt->execute()) {
            $success = "Marks deleted successfully!";
            logActivity($_SESSION['user_id'], "Deleted marks for mark ID: $mark_id");
        } else {
            $error = "Failed to delete marks: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get all marks for this faculty's courses
$marks_query = "
    SELECT m.*, e.enrollment_id, s.roll_no, u.full_name, c.course_code, c.course_name
    FROM marks m
    JOIN enrollments e ON m.enrollment_id = e.enrollment_id
    JOIN students s ON e.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE c.faculty_id = ?
    ORDER BY m.exam_date DESC, c.course_name, u.full_name
";
$stmt = $conn->prepare($marks_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$all_marks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<style>
    .grade-badge {
        font-size: 14px;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
    }
</style>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-graduation-cap"></i> Manage Marks</h1>

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

            <!-- Select Course Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-book"></i> Step 1: Select Course</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-10 mb-3">
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
                            <div class="col-md-2 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" name="load_students" class="btn btn-primary w-100">
                                    <i class="fas fa-users"></i> Load Students
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Marks Form -->
            <?php if (!empty($enrollments_for_course)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-plus-circle"></i> Step 2: Add Marks 
                            <span class="badge bg-white text-success"><?php echo count($enrollments_for_course); ?> Students</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="add_marks" value="1">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="enrollment_id" class="form-label">Student <span class="text-danger">*</span></label>
                                    <select class="form-control" id="enrollment_id" name="enrollment_id" required>
                                        <option value="">-- Select Student --</option>
                                        <?php foreach ($enrollments_for_course as $enrollment): ?>
                                            <option value="<?php echo $enrollment['enrollment_id']; ?>">
                                                <?php echo htmlspecialchars($enrollment['roll_no']); ?> - <?php echo htmlspecialchars($enrollment['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="exam_type" class="form-label">Exam Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="exam_type" name="exam_type" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="Unit Test 1">Unit Test 1</option>
                                        <option value="Unit Test 2">Unit Test 2</option>
                                        <option value="Mid Semester">Mid Semester</option>
                                        <option value="End Semester">End Semester</option>
                                        <option value="Assignment">Assignment</option>
                                        <option value="Project">Project</option>
                                        <option value="Practical">Practical</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="exam_date" class="form-label">Exam Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="exam_date" name="exam_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="marks_obtained" class="form-label">Marks Obtained <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="marks_obtained" 
                                           name="marks_obtained" placeholder="e.g., 85" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="max_marks" class="form-label">Maximum Marks <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="1" class="form-control" id="max_marks" 
                                           name="max_marks" placeholder="e.g., 100" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="percentage" class="form-label">Percentage</label>
                                    <input type="text" class="form-control bg-light" id="percentage" readonly placeholder="0%">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> Add Marks
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif (isset($_POST['load_students'])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No students are enrolled in this course yet.
                </div>
            <?php endif; ?>

            <!-- All Marks Table -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-table"></i> All Marks Records 
                        <span class="badge bg-white text-info"><?php echo count($all_marks); ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="marksTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Roll No</th>
                                    <th>Student Name</th>
                                    <th>Course</th>
                                    <th>Exam Type</th>
                                    <th>Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($all_marks) > 0): ?>
                                    <?php foreach ($all_marks as $mark): 
                                        $percentage = ($mark['max_marks'] > 0) ? ($mark['marks_obtained'] / $mark['max_marks']) * 100 : 0;
                                        
                                        // Determine grade and color
                                        if ($percentage >= 90) {
                                            $grade = 'A+';
                                            $grade_class = 'bg-success';
                                        } elseif ($percentage >= 80) {
                                            $grade = 'A';
                                            $grade_class = 'bg-success';
                                        } elseif ($percentage >= 70) {
                                            $grade = 'B+';
                                            $grade_class = 'bg-primary';
                                        } elseif ($percentage >= 60) {
                                            $grade = 'B';
                                            $grade_class = 'bg-info';
                                        } elseif ($percentage >= 50) {
                                            $grade = 'C';
                                            $grade_class = 'bg-warning';
                                        } elseif ($percentage >= 40) {
                                            $grade = 'D';
                                            $grade_class = 'bg-orange';
                                        } else {
                                            $grade = 'F';
                                            $grade_class = 'bg-danger';
                                        }
                                    ?>
                                        <tr>
                                            <td><strong><?php echo date('d-M-Y', strtotime($mark['exam_date'])); ?></strong></td>
                                            <td><?php echo htmlspecialchars($mark['roll_no']); ?></td>
                                            <td><?php echo htmlspecialchars($mark['full_name']); ?></td>
                                            <td>
                                                <small><?php echo htmlspecialchars($mark['course_code']); ?></small><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($mark['course_name']); ?></small>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($mark['exam_type']); ?></span></td>
                                            <td><strong><?php echo number_format($mark['marks_obtained'], 1); ?></strong> / <?php echo number_format($mark['max_marks'], 0); ?></td>
                                            <td><?php echo number_format($percentage, 1); ?>%</td>
                                            <td><span class="grade-badge <?php echo $grade_class; ?> text-white"><?php echo $grade; ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $mark['mark_id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $mark['mark_id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?php echo $mark['mark_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-warning">
                                                        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Marks</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="update_marks" value="1">
                                                            <input type="hidden" name="mark_id" value="<?php echo $mark['mark_id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Student:</strong></label>
                                                                <p><?php echo htmlspecialchars($mark['full_name']); ?> (<?php echo htmlspecialchars($mark['roll_no']); ?>)</p>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Course:</strong></label>
                                                                <p><?php echo htmlspecialchars($mark['course_code']); ?> - <?php echo htmlspecialchars($mark['course_name']); ?></p>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Exam Type:</strong></label>
                                                                <p><?php echo htmlspecialchars($mark['exam_type']); ?></p>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Marks Obtained <span class="text-danger">*</span></label>
                                                                    <input type="number" step="0.01" min="0" class="form-control" 
                                                                           name="marks_obtained" value="<?php echo $mark['marks_obtained']; ?>" required>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Maximum Marks <span class="text-danger">*</span></label>
                                                                    <input type="number" step="0.01" min="1" class="form-control" 
                                                                           name="max_marks" value="<?php echo $mark['max_marks']; ?>" required>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-warning">
                                                                <i class="fas fa-save"></i> Update Marks
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $mark['mark_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete marks for <strong><?php echo htmlspecialchars($mark['full_name']); ?></strong>?</p>
                                                        <p><strong>Exam:</strong> <?php echo htmlspecialchars($mark['exam_type']); ?></p>
                                                        <p><strong>Marks:</strong> <?php echo number_format($mark['marks_obtained'], 1); ?> / <?php echo number_format($mark['max_marks'], 0); ?></p>
                                                        <p class="text-danger"><i class="fas fa-warning"></i> This action cannot be undone!</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="POST">
                                                            <input type="hidden" name="delete_marks" value="1">
                                                            <input type="hidden" name="mark_id" value="<?php echo $mark['mark_id']; ?>">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="fas fa-trash"></i> Delete Permanently
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-5">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            <p class="mb-0">No marks recorded yet. Select a course and add marks above.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Calculate percentage in real-time
document.getElementById('marks_obtained').addEventListener('input', calculatePercentage);
document.getElementById('max_marks').addEventListener('input', calculatePercentage);

function calculatePercentage() {
    const obtained = parseFloat(document.getElementById('marks_obtained').value) || 0;
    const max = parseFloat(document.getElementById('max_marks').value) || 0;
    
    if (max > 0) {
        const percentage = (obtained / max) * 100;
        document.getElementById('percentage').value = percentage.toFixed(2) + '%';
        
        // Validate marks
        if (obtained > max) {
            document.getElementById('marks_obtained').setCustomValidity('Marks obtained cannot be greater than maximum marks');
        } else {
            document.getElementById('marks_obtained').setCustomValidity('');
        }
    } else {
        document.getElementById('percentage').value = '0%';
    }
}

// Initialize DataTable
$(document).ready(function() {
    $('#marksTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]],
        "language": {
            "search": "Search marks:",
            "lengthMenu": "Show _MENU_ entries"
        }
    });
});

// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

<?php require_once('../../includes/footer.php'); ?>