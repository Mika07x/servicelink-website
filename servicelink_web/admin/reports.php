<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get filters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($department_filter) {
    $where_conditions[] = "t.department_id = ?";
    $params[] = $department_filter;
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
    // Overall statistics
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

    // Department performance
    $dept_params = [$date_from, $date_to];
    $dept_where = "WHERE DATE(t.created_at) BETWEEN ? AND ?";
    if ($search) {
        $dept_where .= " AND (t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ?)";
        $dept_params = array_merge($dept_params, [$search_param, $search_param, $search_param]);
    }
    
    $stmt = $pdo->prepare("
        SELECT d.name as department_name, 
               COUNT(t.id) as ticket_count,
               SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
               SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) as open_count,
               AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END) as avg_resolution_time
        FROM departments d
        LEFT JOIN tickets t ON d.id = t.department_id $dept_where
        WHERE d.is_active = 1
        GROUP BY d.id, d.name
        ORDER BY ticket_count DESC
    ");
    $stmt->execute($dept_params);
    $by_department = $stmt->fetchAll();

    // Recent tickets
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CONCAT(u.first_name, ' ', u.last_name) as requester_name,
               d.name as department_name,
               CONCAT(a.first_name, ' ', a.last_name) as assigned_name
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
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
    $by_department = [];
    $recent_tickets = [];
}

// Get departments for filter
$departments = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports - ServiceLink</title>
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
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-chart-bar text-success me-2"></i>
                    System Reports & Analytics
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
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo ($department_filter == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
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

            <!-- Overview Statistics -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line text-success me-2"></i>
                                System Overview
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

            <!-- Department Performance -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-building text-success me-2"></i>
                                Department Performance
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Total Tickets</th>
                                            <th>Open</th>
                                            <th>Resolved</th>
                                            <th>Resolution Rate</th>
                                            <th>Avg Resolution Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($by_department)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No data available for the selected period</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($by_department as $dept): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                                    <td><?php echo $dept['ticket_count']; ?></td>
                                                    <td>
                                                        <span class="badge bg-warning"><?php echo $dept['open_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success"><?php echo $dept['resolved_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $rate = $dept['ticket_count'] > 0 ? 
                                                               round(($dept['resolved_count'] / $dept['ticket_count']) * 100, 1) : 0;
                                                        $color = $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
                                                        ?>
                                                        <span class="badge bg-<?php echo $color; ?>"><?php echo $rate; ?>%</span>
                                                    </td>
                                                    <td><?php echo round($dept['avg_resolution_time'], 1); ?>h</td>
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
                                            <th>Department</th>
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
                                                    <td><?php echo htmlspecialchars($ticket['department_name']); ?></td>
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
        // Smooth page transition effect
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';
            
            setTimeout(function() {
                document.body.style.opacity = '1';
            }, 50);
        });
        
        // Smooth navigation effect for links
        document.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#') && !this.target) {
                    e.preventDefault();
                    document.body.style.opacity = '0.7';
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 150);
                }
            });
        });

        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'true');
            window.open('?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>