<?php
session_start();
require_once('config/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'User logged out');
}

session_destroy();
header('Location: ' . APP_URL . '/index.php');
exit();
?>