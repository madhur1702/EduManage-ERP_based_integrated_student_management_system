<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Prevent unauthorized access by role
function checkRole($allowed_roles) {
    if (!in_array($_SESSION['role_name'], $allowed_roles)) {
        header("Location: ../dashboard.php");
        exit();
    }
}
?>
