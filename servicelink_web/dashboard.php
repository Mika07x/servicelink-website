<?php
require_once 'config/session.php'; // Include session config FIRST
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redirect to role-specific dashboard
switch ($_SESSION['user_role']) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'department_admin':
        header('Location: department/dashboard.php');
        break;
    case 'staff':
        header('Location: staff/dashboard.php');
        break;
    case 'user':
    default:
        header('Location: student/dashboard.php');
        break;
}
exit;
