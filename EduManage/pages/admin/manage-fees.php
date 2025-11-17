<?php
$page_title = 'Manage Fees';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Admin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;
$query = "SELECT f.*, s.student_id, u.full_name, s.roll_no 
          FROM fees f 
          JOIN students s ON f.student_id = s.student_id 
          JOIN users u ON s.user_id = u.user_id 
          ORDER BY u.full_name";
$fees = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = sanitize($_POST['action']);

    if ($action == 'add_payment') {
        $fee_id = (int)$_POST['fee_id'];
        $amount_paid = (float)$_POST['amount_paid'];

        $query = "SELECT * FROM fees WHERE fee_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $fee_id);
        $stmt->execute();
        $fee = $stmt->get_result()->fetch_assoc();

        $new_total = $fee['amount_paid'] + $amount_paid;
        $new_due = $fee['total_fee'] - $new_total;

        $query = "UPDATE fees SET amount_paid = ?, due_amount = ?, last_payment_date = NOW() WHERE fee_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ddi", $new_total, $new_due, $fee_id);

        if ($stmt->execute()) {
            $success = 'Payment recorded successfully';
            logActivity($_SESSION['user_id'], "Added fee payment");
            $query = "SELECT f.*, s.student_id, u.full_name, s.roll_no 
                      FROM fees f 
                      JOIN students s ON f.student_id = s.student_id 
                      JOIN users u ON s.user_id = u.user_id 
                      ORDER BY u.full_name";
            $fees = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
        } else {
            $error = 'Failed to record payment';
        }
    }
}
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-dollar-sign"></i> Manage Fees</h1>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Student Fee Status</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Roll No</th>
                                <th>Student Name</th>
                                <th>Total Fee</th>
                                <th>Amount Paid</th>
                                <th>Due Amount</th>
                                <th>Last Payment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fees as $fee): ?>
                                <tr>
                                    <td><?php echo $fee['roll_no']; ?></td>
                                    <td><?php echo $fee['full_name']; ?></td>
                                    <td>₹<?php echo number_format($fee['total_fee'], 2); ?></td>
                                    <td><span class="badge bg-success">₹<?php echo number_format($fee['amount_paid'], 2); ?></span></td>
                                    <td><span class="badge bg-danger">₹<?php echo number_format($fee['due_amount'], 2); ?></span></td>
                                    <td><?php echo $fee['last_payment_date'] ? date('d-M-Y', strtotime($fee['last_payment_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo $fee['fee_id']; ?>">
                                            <i class="fas fa-plus"></i> Payment
                                        </button>
                                    </td>
                                </tr>

                                <!-- Payment Modal -->
                                <div class="modal fade" id="paymentModal<?php echo $fee['fee_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Add Payment</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="add_payment">
                                                    <input type="hidden" name="fee_id" value="<?php echo $fee['fee_id']; ?>">
                                                    <div class="form-group">
                                                        <label>Student: <?php echo $fee['full_name']; ?></label>
                                                        <p class="text-muted">Due Amount: ₹<?php echo number_format($fee['due_amount'], 2); ?></p>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="amount_paid" class="form-label">Payment Amount</label>
                                                        <input type="number" class="form-control" id="amount_paid" name="amount_paid" step="0.01" max="<?php echo $fee['due_amount']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Record Payment</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once('../../includes/footer.php'); ?>