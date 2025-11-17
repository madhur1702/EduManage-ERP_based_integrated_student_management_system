<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'madhur1702');
define('DB_NAME', 'student_management_system');

// App Settings
define('APP_NAME', 'EduManage');
define('APP_URL', 'http://localhost/EDU');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>