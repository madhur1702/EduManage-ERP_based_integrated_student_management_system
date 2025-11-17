<?php
$page_title = 'Manage Faculty';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('SubAdmin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$faculty_list = getAllFaculty();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? sanitize($_POST['action']) : '';

    if ($action == 'add') {
        $username = sanitize($_POST['username']);
        $password = sanitize($_POST['password']);
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $dept_id = (int)$_POST['dept_id'];
        $designation = sanitize($_POST['designation']);
        $qualification = sanitize($_POST['qualification']);
        $joining_date = sanitize($_POST['joining_date']);

        // Create user first
        $query = "INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone) 
                  VALUES (3, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssss", $dept_id, $username, $password, $full_name, $email, $phone);

        if ($stmt->execute()) {
            $user_id = $conn->insert_id;

            // Create faculty record
            $query = "INSERT INTO faculty (user_id, designation, qualification, joining_date) 
                      VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isss", $user_id, $designation, $qualification, $joining_date);

            if ($stmt->execute()) {
                $success = "Faculty member added successfully!";
                logActivity($_SESSION['user_id'], "Added new faculty: $full_name");
                $faculty_list = getAllFaculty();
            } else {
                $error = "Failed to create faculty record: " . $conn->error;
                $conn->query("DELETE FROM users WHERE user_id = $user_id");
            }
        } else {
            $error = "Failed to create user account. Username might already exist!";
        }
    } elseif ($action == 'update') {
        $faculty_id = (int)$_POST['faculty_id'];
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $designation = sanitize($_POST['designation']);
        $qualification = sanitize($_POST['qualification']);
        $joining_date = sanitize($_POST['joining_date']);

        $query = "UPDATE faculty f 
                  JOIN users u ON f.user_id = u.user_id 
                  SET u.full_name = ?, u.email = ?, u.phone = ?, 
                      f.designation = ?, f.qualification = ?, f.joining_date = ?
                  WHERE f.faculty_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssi", $full_name, $email, $phone, $designation, $qualification, $joining_date, $faculty_id);

        if ($stmt->execute()) {
            $success = "Faculty member updated successfully!";
            logActivity($_SESSION['user_id'], "Updated faculty: $full_name");
            $faculty_list = getAllFaculty();
        } else {
            $error = "Failed to update faculty member";
        }
    } elseif ($action == 'delete') {
        $faculty_id = (int)$_POST['faculty_id'];
        $faculty = $conn->query("SELECT u.full_name FROM faculty f JOIN users u ON f.user_id = u.user_id WHERE f.faculty_id = $faculty_id")->fetch_assoc();

        $query = "DELETE FROM faculty WHERE faculty_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $faculty_id);

        if ($stmt->execute()) {
            $success = "Faculty member deleted successfully!";
            logActivity($_SESSION['user_id'], "Deleted faculty: " . $faculty['full_name']);
            $faculty_list = getAllFaculty();
        } else {
            $error = "Failed to delete faculty member";
        }
    }
}

$depts = $conn->query("SELECT * FROM departments ORDER BY dept_name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-chalkboard-user"></i> Manage Faculty</h1>

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

            <!-- Add Faculty Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Faculty Member</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" placeholder="e.g., comp_fac11" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
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
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" placeholder="e.g., Dr. John Smith" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="e.g., john.smith@college.edu" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="e.g., 9876543210" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="joining_date" class="form-label">Joining Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="joining_date" name="joining_date" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="designation" class="form-label">Designation <span class="text-danger">*</span></label>
                                <select class="form-control" id="designation" name="designation" required>
                                    <option value="">Select Designation</option>
                                    <option value="Professor">Professor</option>
                                    <option value="Associate Professor">Associate Professor</option>
                                    <option value="Assistant Professor">Assistant Professor</option>
                                    <option value="Lecturer">Lecturer</option>
                                    <option value="Senior Lecturer">Senior Lecturer</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" id="qualification" name="qualification" placeholder="e.g., PhD Computer Science, MTech Electronics">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Faculty</button>
                    </form>
                </div>
            </div>

            <!-- Faculty List -->
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> All Faculty Members (<span id="facultyCount"><?php echo count($faculty_list); ?></span>)</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="text" class="form-control form-control-sm" id="liveSearch" style="width: 250px;" placeholder="Search by name or email...">
                        <button type="button" class="btn btn-light btn-sm" id="clearSearch" title="Clear Search" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Department</th>
                                <th>Designation</th>
                                <th>Qualification</th>
                                <th>Joining Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="facultyTableBody">
                            <?php if (count($faculty_list) > 0): ?>
                                <?php foreach ($faculty_list as $faculty): ?>
                                    <tr class="faculty-row" data-name="<?php echo strtolower(htmlspecialchars($faculty['full_name'])); ?>" data-email="<?php echo strtolower(htmlspecialchars($faculty['email'])); ?>">
                                        <td><strong><?php echo htmlspecialchars($faculty['full_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($faculty['email']); ?></td>
                                        <td><?php echo htmlspecialchars($faculty['phone']); ?></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($faculty['dept_name']); ?></span></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($faculty['designation']); ?></span></td>
                                        <td><?php echo htmlspecialchars($faculty['qualification']); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($faculty['joining_date'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $faculty['faculty_id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $faculty['faculty_id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $faculty['faculty_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header bg-warning">
                                                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Faculty Member</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="faculty_id" value="<?php echo $faculty['faculty_id']; ?>">
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Full Name</label>
                                                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($faculty['full_name']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Email</label>
                                                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($faculty['email']); ?>" required>
                                                            </div>
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Phone</label>
                                                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($faculty['phone']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Joining Date</label>
                                                                <input type="date" class="form-control" name="joining_date" value="<?php echo $faculty['joining_date']; ?>" required>
                                                            </div>
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Designation</label>
                                                                <select class="form-control" name="designation" required>
                                                                    <option value="Professor" <?php echo ($faculty['designation'] == 'Professor') ? 'selected' : ''; ?>>Professor</option>
                                                                    <option value="Associate Professor" <?php echo ($faculty['designation'] == 'Associate Professor') ? 'selected' : ''; ?>>Associate Professor</option>
                                                                    <option value="Assistant Professor" <?php echo ($faculty['designation'] == 'Assistant Professor') ? 'selected' : ''; ?>>Assistant Professor</option>
                                                                    <option value="Lecturer" <?php echo ($faculty['designation'] == 'Lecturer') ? 'selected' : ''; ?>>Lecturer</option>
                                                                    <option value="Senior Lecturer" <?php echo ($faculty['designation'] == 'Senior Lecturer') ? 'selected' : ''; ?>>Senior Lecturer</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Qualification</label>
                                                                <input type="text" class="form-control" name="qualification" value="<?php echo htmlspecialchars($faculty['qualification']); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-warning">
                                                            <i class="fas fa-save"></i> Update Faculty
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $faculty['faculty_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($faculty['full_name']); ?></strong>?</p>
                                                    <p class="text-danger"><i class="fas fa-warning"></i> This action cannot be undone!</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="faculty_id" value="<?php echo $faculty['faculty_id']; ?>">
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
                                        No faculty members found
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
    const tableRows = document.querySelectorAll('.faculty-row');
    const facultyCount = document.getElementById('facultyCount');
    const tbody = document.getElementById('facultyTableBody');

    // Live search functionality
    searchInput.addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase().trim();
        let visibleCount = 0;

        tableRows.forEach(row => {
            const name = row.getAttribute('data-name');
            const email = row.getAttribute('data-email');

            if (searchValue === '') {
                row.style.display = '';
                visibleCount++;
            } else if (name.includes(searchValue) || email.includes(searchValue)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update count and show/hide clear button
        facultyCount.textContent = visibleCount;
        clearButton.style.display = searchValue !== '' ? 'block' : 'none';

        // Show "no results" message
        if (visibleCount === 0 && searchValue !== '') {
            if (!document.getElementById('noResultsRow')) {
                const noResultsRow = document.createElement('tr');
                noResultsRow.id = 'noResultsRow';
                noResultsRow.innerHTML = '<td colspan="8" class="text-center text-muted py-4"><i class="fas fa-search fa-2x mb-2 d-block"></i>No faculty members found matching "' + searchValue + '"</td>';
                tbody.appendChild(noResultsRow);
            } else {
                document.getElementById('noResultsRow').innerHTML = '<td colspan="8" class="text-center text-muted py-4"><i class="fas fa-search fa-2x mb-2 d-block"></i>No faculty members found matching "' + searchValue + '"</td>';
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