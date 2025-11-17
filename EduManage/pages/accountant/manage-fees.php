<?php
$page_title = 'Manage Student Fees';
require_once('../../config/config.php');
require_once('../../includes/db.php');
require_once('../../includes/functions.php');
require_once('../../includes/header.php');

if (!hasRole('Accountant')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

global $conn;

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? sanitize($_POST['action']) : '';

    if ($action == 'add_payment') {
        $fee_id = (int)$_POST['fee_id'];
        $amount_paid = (float)$_POST['amount_paid'];

        // Validate amount
        if ($amount_paid <= 0) {
            $error = "Payment amount must be greater than zero";
        } else {
            // Get current fee details
            $fee_query = "SELECT * FROM fees WHERE fee_id = ?";
            $stmt = $conn->prepare($fee_query);
            $stmt->bind_param("i", $fee_id);
            $stmt->execute();
            $fee = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$fee) {
                $error = "Fee record not found";
            } elseif ($amount_paid > $fee['due_amount']) {
                $error = "Payment amount cannot exceed due amount of ₹" . number_format($fee['due_amount'], 2);
            } else {
                $new_total = $fee['amount_paid'] + $amount_paid;
                $new_due = $fee['total_fee'] - $new_total;

                $update_query = "UPDATE fees SET amount_paid = ?, due_amount = ?, last_payment_date = NOW() WHERE fee_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ddi", $new_total, $new_due, $fee_id);

                if ($stmt->execute()) {
                    $success = "Payment of ₹" . number_format($amount_paid, 2) . " recorded successfully!";
                    logActivity($_SESSION['user_id'], "Added fee payment: ₹" . number_format($amount_paid, 2));
                    $stmt->close();
                } else {
                    $error = "Failed to record payment: " . $stmt->error;
                    $stmt->close();
                }
            }
        }
    } elseif ($action == 'update_fee') {
        $fee_id = (int)$_POST['fee_id'];
        $total_fee = (float)$_POST['total_fee'];

        if ($total_fee <= 0) {
            $error = "Total fee must be greater than zero";
        } else {
            // Get current fee details
            $fee_query = "SELECT * FROM fees WHERE fee_id = ?";
            $stmt = $conn->prepare($fee_query);
            $stmt->bind_param("i", $fee_id);
            $stmt->execute();
            $fee = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $amount_paid = $fee['amount_paid'];
            $due_amount = $total_fee - $amount_paid;

            $update_query = "UPDATE fees SET total_fee = ?, due_amount = ? WHERE fee_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ddi", $total_fee, $due_amount, $fee_id);

            if ($stmt->execute()) {
                $success = "Fee updated successfully to ₹" . number_format($total_fee, 2) . "!";
                logActivity($_SESSION['user_id'], "Updated fee: ₹" . number_format($total_fee, 2));
                $stmt->close();
            } else {
                $error = "Failed to update fee: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

// Get all fees with student details
$fees_query = "
    SELECT f.*, s.student_id, s.roll_no, u.full_name, d.dept_name 
    FROM fees f 
    JOIN students s ON f.student_id = s.student_id 
    JOIN users u ON s.user_id = u.user_id 
    LEFT JOIN departments d ON u.dept_id = d.dept_id 
    ORDER BY u.full_name ASC
";

$fees_result = $conn->query($fees_query);

if (!$fees_result) {
    $error = "Database Error: " . $conn->error;
    $fees = array();
} else {
    $fees = $fees_result->fetch_all(MYSQLI_ASSOC);
}

// Filter options
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';
if ($filter == 'pending') {
    $fees = array_filter($fees, function($f) { return $f['due_amount'] > 0; });
} elseif ($filter == 'paid') {
    $fees = array_filter($fees, function($f) { return $f['due_amount'] == 0; });
}

// Get filter counts
$all_fees = $conn->query("SELECT COUNT(*) as count FROM fees")->fetch_assoc()['count'];
$paid_count = $conn->query("SELECT COUNT(*) as count FROM fees WHERE due_amount = 0")->fetch_assoc()['count'];
$pending_count = $conn->query("SELECT COUNT(*) as count FROM fees WHERE due_amount > 0")->fetch_assoc()['count'];
?>

<div class="d-flex">
    <?php require_once('../../includes/sidebar.php'); ?>
    
    <main class="flex-grow-1 p-4">
        <div class="container-fluid">
            <h1 class="mb-4"><i class="fas fa-money-bill-wave"></i> Manage Student Fees</h1>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Buttons -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="btn-group flex-wrap" role="group">
                        <a href="?filter=all" class="btn <?php echo ($filter == 'all' || $filter == '') ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-list"></i> All (<?php echo $all_fees; ?>)
                        </a>
                        <a href="?filter=paid" class="btn <?php echo $filter == 'paid' ? 'btn-success' : 'btn-outline-success'; ?>">
                            <i class="fas fa-check-circle"></i> Paid (<?php echo $paid_count; ?>)
                        </a>
                        <a href="?filter=pending" class="btn <?php echo $filter == 'pending' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                            <i class="fas fa-exclamation"></i> Pending (<?php echo $pending_count; ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Fees Table -->
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-table"></i> Student Fees (<span id="feesCount"><?php echo count($fees); ?></span>)</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="text" class="form-control form-control-sm" id="liveSearch" style="width: 250px;" placeholder="Search by name or roll number...">
                        <button type="button" class="btn btn-light btn-sm" id="clearSearch" title="Clear Search" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Roll No</th>
                                <th>Student Name</th>
                                <th>Department</th>
                                <th>Total Fee</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th>Status</th>
                                <th>Last Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="feesTableBody">
                            <?php if (count($fees) > 0): ?>
                                <?php foreach ($fees as $fee): ?>
                                    <tr class="fee-row" data-name="<?php echo strtolower(htmlspecialchars($fee['full_name'])); ?>" data-rollno="<?php echo strtolower(htmlspecialchars($fee['roll_no'])); ?>">
                                        <td><strong><?php echo htmlspecialchars($fee['roll_no']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($fee['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($fee['dept_name'] ?? 'N/A'); ?></td>
                                        <td>₹<?php echo number_format($fee['total_fee'], 2); ?></td>
                                        <td><span class="badge bg-success">₹<?php echo number_format($fee['amount_paid'], 2); ?></span></td>
                                        <td><span class="badge bg-danger">₹<?php echo number_format($fee['due_amount'], 2); ?></span></td>
                                        <td>
                                            <?php 
                                            if ($fee['due_amount'] == 0) {
                                                echo '<span class="badge bg-success"><i class="fas fa-check"></i> Paid</span>';
                                            } else {
                                                $percentage = ($fee['amount_paid'] / $fee['total_fee']) * 100;
                                                echo '<span class="badge bg-warning">' . number_format($percentage, 0) . '% Paid</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $fee['last_payment_date'] ? date('d-M-Y', strtotime($fee['last_payment_date'])) : '<span class="text-muted">Never</span>'; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo $fee['fee_id']; ?>" 
                                                title="Add Payment" <?php echo ($fee['due_amount'] <= 0) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $fee['fee_id']; ?>"
                                                title="Edit Fee">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Payment Modal -->
                                    <div class="modal fade" id="paymentModal<?php echo $fee['fee_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-success text-white">
                                                    <h5 class="modal-title"><i class="fas fa-money-bill-wave"></i> Add Payment</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="add_payment">
                                                        <input type="hidden" name="fee_id" value="<?php echo $fee['fee_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Student:</strong></label>
                                                            <p class="text-primary"><?php echo htmlspecialchars($fee['full_name']); ?> (<?php echo htmlspecialchars($fee['roll_no']); ?>)</p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Due Amount:</strong></label>
                                                            <p class="display-6 text-danger">₹<?php echo number_format($fee['due_amount'], 2); ?></p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="amount_paid<?php echo $fee['fee_id']; ?>" class="form-label">Payment Amount <span class="text-danger">*</span></label>
                                                            <input type="number" step="0.01" class="form-control" id="amount_paid<?php echo $fee['fee_id']; ?>" name="amount_paid" max="<?php echo $fee['due_amount']; ?>" placeholder="Enter amount" required>
                                                            <small class="form-text text-muted">Max: ₹<?php echo number_format($fee['due_amount'], 2); ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Record Payment</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $fee['fee_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-warning text-white">
                                                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Fee</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update_fee">
                                                        <input type="hidden" name="fee_id" value="<?php echo $fee['fee_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Student:</strong></label>
                                                            <p class="text-primary"><?php echo htmlspecialchars($fee['full_name']); ?> (<?php echo htmlspecialchars($fee['roll_no']); ?>)</p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="total_fee<?php echo $fee['fee_id']; ?>" class="form-label">Total Fee <span class="text-danger">*</span></label>
                                                            <input type="number" step="0.01" class="form-control" id="total_fee<?php echo $fee['fee_id']; ?>" name="total_fee" value="<?php echo $fee['total_fee']; ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Already Paid:</strong></label>
                                                            <p>₹<?php echo number_format($fee['amount_paid'], 2); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update Fee</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No fees to display
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
    const tableRows = document.querySelectorAll('.fee-row');
    const feesCount = document.getElementById('feesCount');
    const tbody = document.getElementById('feesTableBody');

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
        feesCount.textContent = visibleCount;
        clearButton.style.display = searchValue !== '' ? 'block' : 'none';

        // Show "no results" message
        if (visibleCount === 0 && searchValue !== '') {
            if (!document.getElementById('noResultsRow')) {
                const noResultsRow = document.createElement('tr');
                noResultsRow.id = 'noResultsRow';
                noResultsRow.innerHTML = '<td colspan="9" class="text-center text-muted py-4"><i class="fas fa-search fa-2x mb-2 d-block"></i>No students found matching "' + searchValue + '"</td>';
                tbody.appendChild(noResultsRow);
            } else {
                document.getElementById('noResultsRow').innerHTML = '<td colspan="9" class="text-center text-muted py-4"><i class="fas fa-search fa-2x mb-2 d-block"></i>No students found matching "' + searchValue + '"</td>';
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