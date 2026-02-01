<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is department admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'department_admin') {
    header('Location: ../login.php');
    exit;
}

$department_id = $_SESSION['department_id'];

// Get filters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$assigned_filter = $_GET['assigned'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?", "t.department_id = ?"];
$params = [$date_from, $date_to, $department_id];

if ($status_filter) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $where_conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
}

if ($assigned_filter) {
    $where_conditions[] = "t.assigned_to = ?";
    $params[] = $assigned_filter;
}

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get report data
try {
    // Department statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
            SUM(CASE WHEN priority = 'emergency' THEN 1 ELSE 0 END) as emergency_tickets,
            SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority_tickets,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_resolution_time
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        $where_clause
    ");
    $stmt->execute($params);
    $overview = $stmt->fetch();

    // Staff performance
    $staff_params = [$date_from, $date_to, $department_id];
    $staff_where = "WHERE DATE(t.created_at) BETWEEN ? AND ? AND t.department_id = ?";
    if ($search) {
        $staff_where .= " AND (t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ?)";
        $staff_params = array_merge($staff_params, [$search_param, $search_param, $search_param]);
    }
    
    $stmt = $pdo->prepare("
        SELECT CONCAT(s.first_name, ' ', s.last_name) as staff_name,
               s.id as staff_id,
               COUNT(t.id) as ticket_count,
               SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
               SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) as open_count,
               SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
               AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END) as avg_resolution_time
        FROM users s
        LEFT JOIN tickets t ON s.id = t.assigned_to $staff_where
        WHERE s.department_id = ? AND s.role = 'staff' AND s.is_active = 1
        GROUP BY s.id, s.first_name, s.last_name
        ORDER BY ticket_count DESC
    ");
    $staff_params[] = $department_id;
    $stmt->execute($staff_params);
    $staff_performance = $stmt->fetchAll();

    // Recent tickets
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CONCAT(u.first_name, ' ', u.last_name) as requester_name,
               CONCAT(a.first_name, ' ', a.last_name) as assigned_name
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN users a ON t.assigned_to = a.id
        $where_clause
        ORDER BY t.created_at DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $recent_tickets = $stmt->fetchAll();

} catch (PDOException $e) {
    $overview = ['total_tickets' => 0, 'open_tickets' => 0, 'in_progress_tickets' => 0, 
                'resolved_tickets' => 0, 'closed_tickets' => 0, 'emergency_tickets' => 0,
                'high_priority_tickets' => 0, 'avg_resolution_time' => 0];
    $staff_performance = [];
    $recent_tickets = [];
}

// Get staff for filter
$staff_members = [];
try {
    $stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE department_id = ? AND role = 'staff' AND is_active = 1 ORDER BY first_name, last_name");
    $stmt->execute([$department_id]);
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $staff_members = [];
}

// Get department name
$department_name = '';
try {
    $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $dept = $stmt->fetch();
    $department_name = $dept ? $dept['name'] : 'Department';
} catch (PDOException $e) {
    $department_name = 'Department';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Reports - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <img src="../assets/images/logo.png" alt="ServiceLink" height="40" class="me-2">
                <span class="fw-bold text-success">ServiceLink</span>
            </a>
            
            <!-- Mobile Menu Toggle Button -->
            <button class="mobile-menu-toggle d-md-none" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Desktop User Dropdown -->
            <div class="navbar-nav ms-auto d-none d-md-flex">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center text-dark" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-2 fs-5 text-success"></i>
                        <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item" href="../profile.php">
                                <i class="fas fa-user me-2 text-success"></i>
                                Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="../settings.php">
                                <i class="fas fa-cog me-2 text-success"></i>
                                Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    
    <div class="container-fluid p-0">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-chart-bar text-success me-2"></i>
                    <?php echo htmlspecialchars($department_name); ?> Reports
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-success" onclick="exportReport()">
                        <i class="fas fa-download me-1"></i>
                        Export Report
                    </button>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Ticket number, title, or requester..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="assigned" class="form-label">Assigned To</label>
                            <select class="form-select" id="assigned" name="assigned">
                                <option value="">All Staff</option>
                                <?php foreach ($staff_members as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>" 
                                            <?php echo ($assigned_filter == $staff['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-1">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="open" <?php echo ($status_filter == 'open') ? 'selected' : ''; ?>>Open</option>
                                <option value="in_progress" <?php echo ($status_filter == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo ($status_filter == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo ($status_filter == 'closed') ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="">All Priority</option>
                                <option value="low" <?php echo ($priority_filter == 'low') ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($priority_filter == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo ($priority_filter == 'high') ? 'selected' : ''; ?>>High</option>
                                <option value="emergency" <?php echo ($priority_filter == 'emergency') ? 'selected' : ''; ?>>Emergency</option>
                            </select>
                        </div>
                        
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Department Overview -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line text-success me-2"></i>
                                Department Overview
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <div class="text-center">
                                        <div class="h3 text-primary mb-1">
                                            <?php echo number_format($overview['total_tickets']); ?>
                                        </div>
                                        <div class="small text-muted">Total Tickets</div>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <div class="text-center">
                                        <div class="h3 text-warning mb-1">
                                            <?php echo number_format($overview['open_tickets']); ?>
                                        </div>
                                        <div class="small text-muted">Open</div>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <div class="text-center">
                                        <div class="h3 text-info mb-1">
                                            <?php echo number_format($overview['in_progress_tickets']); ?>
                                        </div>
                                        <div class="small text-muted">In Progress</div>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <div class="text-center">
                                        <div class="h3 text-success mb-1">
                                            <?php echo number_format($overview['resolved_tickets']); ?>
                                        </div>
                                        <div class="small text-muted">Resolved</div>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <div class="text-center">
                                        <div class="h3 text-danger mb-1">
                                            <?php echo number_format($overview['emergency_tickets']); ?>
                                        </div>
                                        <div class="small text-muted">Emergency</div>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                    <div class="text-center">
                                        <div class="h3 text-secondary mb-1">
                                            <?php echo round($overview['avg_resolution_time'], 1); ?>h
                                        </div>
                                        <div class="small text-muted">Avg Resolution</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff Performance -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-users text-success me-2"></i>
                                Staff Performance
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Staff Member</th>
                                            <th>Total Assigned</th>
                                            <th>Open</th>
                                            <th>In Progress</th>
                                            <th>Resolved</th>
                                            <th>Resolution Rate</th>
                                            <th>Avg Resolution Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($staff_performance)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No staff performance data available</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($staff_performance as $staff): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                                                    <td><?php echo $staff['ticket_count']; ?></td>
                                                    <td>
                                                        <span class="badge bg-warning"><?php echo $staff['open_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $staff['in_progress_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success"><?php echo $staff['resolved_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $rate = $staff['ticket_count'] > 0 ? 
                                                               round(($staff['resolved_count'] / $staff['ticket_count']) * 100, 1) : 0;
                                                        $color = $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
                                                        ?>
                                                        <span class="badge bg-<?php echo $color; ?>"><?php echo $rate; ?>%</span>
                                                    </td>
                                                    <td><?php echo round($staff['avg_resolution_time'], 1); ?>h</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Tickets -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clock text-success me-2"></i>
                                Recent Department Tickets
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ticket #</th>
                                            <th>Title</th>
                                            <th>Requester</th>
                                            <th>Assigned To</th>
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_tickets)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No tickets found</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_tickets as $ticket): ?>
                                                <tr>
                                                    <td>
                                                        <a href="../tickets/view.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($ticket['requester_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($ticket['assigned_name'] ?: 'Unassigned'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                                            <?php echo ucfirst($ticket['priority']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo timeAgo($ticket['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.dashboard-sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }

        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'true');
            window.open('?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>