<?php
$page_title = 'Manage Users';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Admin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$users = getAllUsers();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = sanitize($_POST['action']);
    global $conn;

    if ($action == 'add') {
        $username = sanitize($_POST['username']);
        $password = sanitize($_POST['password']);
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $role_id = (int)$_POST['role_id'];
        $dept_id = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;

        $query = "INSERT INTO users (role_id, dept_id, username, password_hash, full_name, email, phone) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iisssss", $role_id, $dept_id, $username, $password, $full_name, $email, $phone);

        if ($stmt->execute()) {
            $success = 'User added successfully';
            logActivity($_SESSION['user_id'], "Added new user: $full_name");
            $users = getAllUsers();
        } else {
            $error = 'Failed to add user';
        }
    }
}
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-users"></i> Manage Users</h1>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add New User</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="role_id" class="form-label">Role</label>
                                <select class="form-control" id="role_id" name="role_id" required>
                                    <option value="">Select Role</option>
                                    <option value="1">Admin</option>
                                    <option value="2">SubAdmin</option>
                                    <option value="3">Faculty</option>
                                    <option value="4">Student</option>
                                    <option value="5">Librarian</option>
                                    <option value="6">Accountant</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add User</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">All Users</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><strong><?php echo $u['username']; ?></strong></td>
                                    <td><?php echo $u['full_name']; ?></td>
                                    <td><?php echo $u['email']; ?></td>
                                    <td><?php echo $u['phone']; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $u['role_name']; ?></span></td>
                                    <td><?php echo $u['dept_name'] ?? 'N/A'; ?></td>
                                    <td><?php echo date('d-M-Y', strtotime($u['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once('../../includes/footer.php'); ?>