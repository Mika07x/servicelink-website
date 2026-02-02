<?php
require_once '../config/session.php'; // Include session config FIRST
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'];

// Get admin-specific statistics
$stats = getUserDashboardStats($pdo, $user_id, $user_role, $department_id);

// Get recent tickets for admin (all tickets)
$recent_tickets = getRecentTickets($pdo, $user_id, $user_role, $department_id, 5);

// Get notifications
$notifications = getUnreadNotifications($pdo, $user_id, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/top_nav.php'; ?>
    
    <div class="container-fluid p-0">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="dashboard-content">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cogs text-success me-2"></i>
                        Admin Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="tickets.php" class="btn btn-success">
                                <i class="fas fa-tasks me-1"></i>
                                Manage Tickets
                            </a>
                            <button type="button" class="btn btn-outline-secondary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Welcome Message -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-success border-0 shadow-sm">
                            <h4 class="alert-heading">
                                <i class="fas fa-shield-alt me-2"></i>
                                Welcome back, Administrator <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
                            </h4>
                            <p class="mb-0">
                                You have full system access. Monitor all tickets, manage users, and oversee the entire platform.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Admin Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Tickets
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_tickets']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-ticket-alt fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['open_tickets']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            In Progress
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['in_progress_tickets'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-cog fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            High Priority
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['high_priority_tickets'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-fire fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity and Notifications -->
                <div class="row">
                    <!-- Recent Tickets -->
                    <div class="col-lg-8 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history text-success me-2"></i>
                                    Recent Tickets
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_tickets)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No tickets found.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Ticket #</th>
                                                    <th>Title</th>
                                                    <th>Priority</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_tickets as $ticket): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-secondary">
                                                                <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="fw-bold">
                                                                <?php echo htmlspecialchars(substr($ticket['title'], 0, 50)); ?>
                                                                <?php echo strlen($ticket['title']) > 50 ? '...' : ''; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                                                <?php echo ucfirst($ticket['priority']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <a href="view.php?id=<?php echo $ticket['id']; ?>" 
                                                               class="btn btn-sm btn-outline-success">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="tickets.php" class="btn btn-outline-success">
                                            View All Tickets
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bell text-success me-2"></i>
                                    Notifications
                                    <?php if (count($notifications) > 0): ?>
                                        <span class="badge bg-danger ms-2"><?php echo count($notifications); ?></span>
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($notifications)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-bell-slash fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">No new notifications</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($notifications as $notification): ?>
                                            <div class="list-group-item border-0 px-0">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo timeAgo($notification['created_at']); ?>
                                                    </small>
                                                </div>
                                                <p class="mb-1 small text-muted">
                                                    <?php echo htmlspecialchars(substr($notification['message'], 0, 100)); ?>
                                                    <?php echo strlen($notification['message']) > 100 ? '...' : ''; ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="../notifications.php" class="btn btn-outline-success btn-sm">
                                            View All Notifications
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Quick Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt text-success me-2"></i>
                                    Admin Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="users.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                            <i class="fas fa-users fa-2x mb-2"></i>
                                            <span>Manage Users</span>
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="departments.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                            <i class="fas fa-building fa-2x mb-2"></i>
                                            <span>Departments</span>
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="reports.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                            <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                            <span>Reports</span>
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="settings.php" class="btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                            <i class="fas fa-cog fa-2x mb-2"></i>
                                            <span>Settings</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshDashboard() {
            location.reload();
        }
        
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            // You can implement AJAX refresh for notifications here
        }, 30000);
    </script>
</body>
</html>