<?php
$page_title = 'Manage Students';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('SubAdmin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$students = getAllStudents();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? sanitize($_POST['action']) : '';

    if ($action == 'add') {
        $roll_no = sanitize($_POST['roll_no']);
        $username = sanitize($_POST['username']);
        $password = sanitize($_POST['password']);
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $dob = sanitize($_POST['dob']);
        $dept_id = (int)$_POST['dept_id'];
        $admission_year = (int)$_POST['admission_year'];
        $address = sanitize($_POST['address']);
        $guardian_name = sanitize($_POST['guardian_name']);
        $guardian_contact = sanitize($_POST['guardian_contact']);

        // Create user first
        $query = "INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone) 
                  VALUES (4, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssss", $dept_id, $username, $password, $full_name, $email, $phone);

        if ($stmt->execute()) {
            $user_id = $conn->insert_id;

            // Create student record
            $query = "INSERT INTO students (user_id, roll_no, admission_year, dob, address, guardian_name, guardian_contact) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isissss", $user_id, $roll_no, $admission_year, $dob, $address, $guardian_name, $guardian_contact);

            if ($stmt->execute()) {
                $student_id = $conn->insert_id;
                
                // Create fee record
                $total_fee = 100000; // Default fee
                $query = "INSERT INTO fees (student_id, total_fee, amount_paid, due_amount) 
                          VALUES (?, ?, 0, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("idd", $student_id, $total_fee, $total_fee);
                $stmt->execute();

                $success = "Student added successfully!";
                logActivity($_SESSION['user_id'], "Added new student: $full_name (Roll No: $roll_no)");
                $students = getAllStudents();
            } else {
                $error = "Failed to create student record: " . $conn->error;
                // Delete user if student creation fails
                $conn->query("DELETE FROM users WHERE user_id = $user_id");
            }
        } else {
            $error = "Failed to create user account. Username might already exist!";
        }
    } elseif ($action == 'update') {
        $student_id = (int)$_POST['student_id'];
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $dob = sanitize($_POST['dob']);
        $address = sanitize($_POST['address']);
        $guardian_name = sanitize($_POST['guardian_name']);
        $guardian_contact = sanitize($_POST['guardian_contact']);

        $query = "UPDATE students s 
                  JOIN users u ON s.user_id = u.user_id 
                  SET u.full_name = ?, u.email = ?, u.phone = ?, 
                      s.dob = ?, s.address = ?, s.guardian_name = ?, s.guardian_contact = ?
                  WHERE s.student_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssi", $full_name, $email, $phone, $dob, $address, $guardian_name, $guardian_contact, $student_id);

        if ($stmt->execute()) {
            $success = "Student updated successfully!";
            logActivity($_SESSION['user_id'], "Updated student: $full_name");
            $students = getAllStudents();
        } else {
            $error = "Failed to update student";
        }
    } elseif ($action == 'delete') {
        $student_id = (int)$_POST['student_id'];
        $student = $conn->query("SELECT u.full_name FROM students s JOIN users u ON s.user_id = u.user_id WHERE s.student_id = $student_id")->fetch_assoc();

        $query = "DELETE FROM students WHERE student_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);

        if ($stmt->execute()) {
            $success = "Student deleted successfully!";
            logActivity($_SESSION['user_id'], "Deleted student: " . $student['full_name']);
            $students = getAllStudents();
        } else {
            $error = "Failed to delete student";
        }
    }
}

$depts = $conn->query("SELECT * FROM departments ORDER BY dept_name")->fetch_all(MYSQLI_ASSOC);
$current_year = date('Y');
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-user-graduate"></i> Manage Students</h1>

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

            <!-- Add Student Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Student</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="roll_no" class="form-label">Roll Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="roll_no" name="roll_no" placeholder="e.g., CMP2025-023" required>
                                <small class="text-muted">Format: CMP2025-XXX, IT2025-XXX, ENTC2025-XXX</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" placeholder="e.g., student123" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="dept_id" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-control" id="dept_id" name="dept_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($depts as $dept): ?>
                                        <option value="<?php echo $dept['dept_id']; ?>"><?php echo $dept['dept_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="admission_year" class="form-label">Admission Year <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="admission_year" name="admission_year" value="<?php echo $current_year; ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="guardian_name" class="form-label">Guardian Name</label>
                                <input type="text" class="form-control" id="guardian_name" name="guardian_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="guardian_contact" class="form-label">Guardian Contact</label>
                                <input type="tel" class="form-control" id="guardian_contact" name="guardian_contact">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Student</button>
                    </form>
                </div>
            </div>

            <!-- Students List -->
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> All Students (<span id="studentCount"><?php echo count($students); ?></span>)</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="text" class="form-control form-control-sm" id="liveSearch" style="width: 250px;" placeholder="Search by name or roll number...">
                        <button type="button" class="btn btn-light btn-sm" id="clearSearch" title="Clear Search" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Roll No</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Department</th>
                                <th>Admission Year</th>
                                <th>Guardian</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentsTableBody">
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $student): ?>
                                    <tr class="student-row" data-name="<?php echo strtolower(htmlspecialchars($student['full_name'])); ?>" data-rollno="<?php echo strtolower(htmlspecialchars($student['roll_no'])); ?>">
                                        <td><strong><?php echo htmlspecialchars($student['roll_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($student['dept_name']); ?></span></td>
                                        <td><?php echo $student['admission_year']; ?></td>
                                        <td><?php echo htmlspecialchars($student['guardian_name']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $student['student_id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $student['student_id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $student['student_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header bg-warning">
                                                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Student</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Full Name</label>
                                                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Email</label>
                                                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Phone</label>
                                                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($student['phone']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Date of Birth</label>
                                                                <input type="date" class="form-control" name="dob" value="<?php echo $student['dob']; ?>">
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Address</label>
                                                            <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($student['address']); ?>">
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Guardian Name</label>
                                                                <input type="text" class="form-control" name="guardian_name" value="<?php echo htmlspecialchars($student['guardian_name']); ?>">
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Guardian Contact</label>
                                                                <input type="tel" class="form-control" name="guardian_contact" value="<?php echo htmlspecialchars($student['guardian_contact']); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-warning">
                                                            <i class="fas fa-save"></i> Update Student
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $student['student_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>?</p>
                                                    <p class="text-danger"><i class="fas fa-warning"></i> This action cannot be undone!</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
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
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No students found
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
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('liveSearch');
    const clearButton = document.getElementById('clearSearch');
    const tableRows = document.querySelectorAll('.student-row');
    const studentCount = document.getElementById('studentCount');
    const tbody = document.getElementById('studentsTableBody');

    // Live search functionality
    searchInput.addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase().trim();
        let visibleCount = 0;

        tableRows.forEach(row => {
            const name = row.getAttribute('data-name');
            const rollno = row.getAttribute('data-rollno');

            if (searchValue === '') {
                row.style.display = '';
                visibleCount++;
            } else if (name.includes(searchValue) || rollno.includes(searchValue)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update count and show/hide clear button
        studentCount.textContent = visibleCount;
        clearButton.style.display = searchValue !== '' ? 'block' : 'none';

        // Show "no results" message
        if (visibleCount === 0 && searchValue !== '') {
            if (!document.getElementById('noResultsRow')) {
                const noResultsRow = document.createElement('tr');
                noResultsRow.id = 'noResultsRow';
                noResultsRow.innerHTML = '<td colspan="8" class="text-center text-muted py-4"><i class="fas fa-search fa-2x mb-2 d-block"></i>No students found matching "' + searchValue + '"</td>';
                tbody.appendChild(noResultsRow);
            } else {
                document.getElementById('noResultsRow').innerHTML = '<td colspan="8" class="text-center text-muted py-4"><i class="fas fa-search fa-2x mb-2 d-block"></i>No students found matching "' + searchValue + '"</td>';
            }
        } else {
            const noResultsRow = document.getElementById('noResultsRow');
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    });

    // Clear button functionality
    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        searchInput.focus();
        searchInput.dispatchEvent(new Event('keyup'));
    });
});
</script>

<?php require_once('../../includes/footer.php'); ?>