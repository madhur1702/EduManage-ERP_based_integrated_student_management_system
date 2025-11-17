<?php
$page_title = 'My Profile';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Student')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$student = getStudentInfo($_SESSION['user_id']);
$user = getUserInfo($_SESSION['user_id']);
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-user-circle"></i> My Profile</h1>

            <div class="row">
                <div class="col-md-4">
                    <div class="card text-center mb-4">
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="fas fa-user-circle fa-5x text-primary"></i>
                            </div>
                            <h4><?php echo $student['full_name']; ?></h4>
                            <p class="text-muted">Student</p>
                            <p class="badge bg-info"><?php echo $student['dept_name']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Full Name</strong></label>
                                    <p><?php echo $student['full_name']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Roll Number</strong></label>
                                    <p><?php echo $student['roll_no']; ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Email</strong></label>
                                    <p><?php echo $student['email']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Phone</strong></label>
                                    <p><?php echo $student['phone']; ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Date of Birth</strong></label>
                                    <p><?php echo $student['dob'] ? date('d-M-Y', strtotime($student['dob'])) : 'N/A'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Department</strong></label>
                                    <p><?php echo $student['dept_name']; ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Admission Year</strong></label>
                                    <p><?php echo $student['admission_year']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Address</strong></label>
                                    <p><?php echo $student['address']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-family"></i> Guardian Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Guardian Name</strong></label>
                                    <p><?php echo $student['guardian_name']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Guardian Contact</strong></label>
                                    <p><?php echo $student['guardian_contact']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lock"></i> Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Username</strong></label>
                            <p><?php echo $user['username']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Member Since</strong></label>
                            <p><?php echo date('d-M-Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card mt-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Recent Activities</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $activities_query = "SELECT * FROM activity_log WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10";
                            $stmt = $conn->prepare($activities_query);
                            $stmt->bind_param("i", $_SESSION['user_id']);
                            $stmt->execute();
                            $activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            $stmt->close();

                            if (count($activities) > 0):
                                foreach ($activities as $activity):
                            ?>
                                <tr>
                                    <td><?php echo $activity['action']; ?></td>
                                    <td><?php echo date('d-M-Y H:i', strtotime($activity['timestamp'])); ?></td>
                                </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No activities yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once('../../includes/footer.php'); ?>