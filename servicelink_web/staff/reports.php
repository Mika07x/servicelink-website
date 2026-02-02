<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'staff') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'];

// Get filters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$assignment_filter = $_GET['assignment'] ?? 'assigned'; // assigned, all
$search = $_GET['search'] ?? '';

// Build query conditions
$where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?", "t.department_id = ?"];
$params = [$date_from, $date_to, $department_id];

if ($assignment_filter == 'assigned') {
    $where_conditions[] = "t.assigned_to = ?";
    $params[] = $user_id;
}

if ($status_filter) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $where_conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
}

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get report data
try {
    // Personal statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_tickets,
            SUM(CASE WHEN status IN ('new', 'pending') THEN 1 ELSE 0 END) as open_tickets,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
            SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_tickets,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
            SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold_tickets,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
            SUM(CASE WHEN status = 'reopen' THEN 1 ELSE 0 END) as reopen_tickets,
            SUM(CASE WHEN priority = 'emergency' THEN 1 ELSE 0 END) as emergency_tickets,
            SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority_tickets,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_resolution_time
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        $where_clause
    ");
    $stmt->execute($params);
    $overview = $stmt->fetch();

    // My performance vs department average (for assigned tickets only)
    $stmt = $pdo->prepare("
        SELECT 
            'My Performance' as category,
            COUNT(*) as ticket_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_resolution_time
        FROM tickets 
        WHERE assigned_to = ? AND department_id = ? AND DATE(created_at) BETWEEN ? AND ?
        UNION ALL
        SELECT 
            'Department Average' as category,
            COUNT(*) as ticket_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
            AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) as avg_resolution_time
        FROM tickets 
        WHERE department_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $department_id, $date_from, $date_to, $department_id, $date_from, $date_to]);
    $performance_comparison = $stmt->fetchAll();

    // Recent tickets
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CONCAT(u.first_name, ' ', u.last_name) as requester_name
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        $where_clause
        ORDER BY t.created_at DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $recent_tickets = $stmt->fetchAll();

    // Priority breakdown
    $stmt = $pdo->prepare("
        SELECT priority, COUNT(*) as count
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        $where_clause
        GROUP BY priority
        ORDER BY FIELD(priority, 'emergency', 'high', 'medium', 'low')
    ");
    $stmt->execute($params);
    $priority_breakdown = $stmt->fetchAll();

} catch (PDOException $e) {
    $overview = ['total_tickets' => 0, 'open_tickets' => 0, 'in_progress_tickets' => 0, 
                'resolved_tickets' => 0, 'closed_tickets' => 0, 'emergency_tickets' => 0,
                'high_priority_tickets' => 0, 'avg_resolution_time' => 0];
    $performance_comparison = [];
    $recent_tickets = [];
    $priority_breakdown = [];
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
    <title>My Reports - ServiceLink</title>
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
                    My Performance Reports
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
                            <label for="assignment" class="form-label">View</label>
                            <select class="form-select" id="assignment" name="assignment">
                                <option value="assigned" <?php echo ($assignment_filter == 'assigned') ? 'selected' : ''; ?>>My Assigned Tickets</option>
                                <option value="all" <?php echo ($assignment_filter == 'all') ? 'selected' : ''; ?>>All Department Tickets</option>
                            </select>
                        </div>
                        
                        <div class="col-md-1">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="new" <?php echo ($status_filter == 'new') ? 'selected' : ''; ?>>New</option>
                                <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="assigned" <?php echo ($status_filter == 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                                <option value="in_progress" <?php echo ($status_filter == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="on_hold" <?php echo ($status_filter == 'on_hold') ? 'selected' : ''; ?>>On Hold</option>
                                <option value="resolved" <?php echo ($status_filter == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo ($status_filter == 'closed') ? 'selected' : ''; ?>>Closed</option>
                                <option value="reopen" <?php echo ($status_filter == 'reopen') ? 'selected' : ''; ?>>Reopen</option>
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

            <!-- Performance Overview -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line text-success me-2"></i>
                                <?php echo ($assignment_filter == 'assigned') ? 'My Performance' : 'Department Overview'; ?>
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

            <div class="row mb-4">
                <!-- Performance Comparison -->
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-trophy text-success me-2"></i>
                                Performance Comparison
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($performance_comparison)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-chart-bar fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No performance data available</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Metric</th>
                                                <th>Total</th>
                                                <th>Resolved</th>
                                                <th>Rate</th>
                                                <th>Avg Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($performance_comparison as $perf): ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($perf['category'] == 'My Performance'): ?>
                                                            <i class="fas fa-user text-primary me-1"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-users text-secondary me-1"></i>
                                                        <?php endif; ?>
                                                        <?php echo $perf['category']; ?>
                                                    </td>
                                                    <td><?php echo $perf['ticket_count']; ?></td>
                                                    <td><?php echo $perf['resolved_count']; ?></td>
                                                    <td>
                                                        <?php 
                                                        $rate = $perf['ticket_count'] > 0 ? 
                                                               round(($perf['resolved_count'] / $perf['ticket_count']) * 100, 1) : 0;
                                                        $color = $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
                                                        ?>
                                                        <span class="badge bg-<?php echo $color; ?>"><?php echo $rate; ?>%</span>
                                                    </td>
                                                    <td><?php echo round($perf['avg_resolution_time'], 1); ?>h</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Priority Breakdown -->
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle text-success me-2"></i>
                                Priority Breakdown
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($priority_breakdown)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-chart-pie fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No priority data available</p>
                                </div>
                            <?php else: ?>
                                <?php 
                                $total_priority = array_sum(array_column($priority_breakdown, 'count'));
                                foreach ($priority_breakdown as $priority): 
                                    $percentage = $total_priority > 0 ? round(($priority['count'] / $total_priority) * 100, 1) : 0;
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small">
                                            <i class="fas fa-circle text-<?php echo getPriorityColor($priority['priority']); ?> me-1"></i>
                                            <?php echo ucfirst($priority['priority']); ?>
                                        </span>
                                        <div>
                                            <span class="badge bg-<?php echo getPriorityColor($priority['priority']); ?>">
                                                <?php echo $priority['count']; ?> (<?php echo $percentage; ?>%)
                                            </span>
                                        </div>
                                    </div>
                                    <div class="progress mb-3" style="height: 4px;">
                                        <div class="progress-bar bg-<?php echo getPriorityColor($priority['priority']); ?>" 
                                             style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                                Recent Tickets
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
                                            <th>Status</th>
                                            <th>Priority</th>
                                            <th>Created</th>
                                            <th>Actions</th>
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
                                                        <a href="view.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($ticket['requester_name']); ?></td>
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
                                                    <td>
                                                        <a href="view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
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