<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user has permission to view reports
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] == 'user') {
    header('Location: ../login.php');
    exit;
}

// Redirect to role-specific reports
switch ($_SESSION['user_role']) {
    case 'admin':
        header('Location: ../admin/reports.php');
        break;
    case 'department_admin':
        header('Location: ../department/reports.php');
        break;
    case 'staff':
        header('Location: ../staff/reports.php');
        break;
    default:
        header('Location: ../login.php');
        break;
}
exit;
?>

$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'];

// Get date range from filters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query conditions based on user role
$where_conditions = ["DATE(t.created_at) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($user_role == 'department_admin' || $user_role == 'staff') {
    $where_conditions[] = "t.department_id = ?";
    $params[] = $department_id;
} elseif ($user_role == 'admin' && $department_filter) {
    $where_conditions[] = "t.department_id = ?";
    $params[] = $department_filter;
}

if ($status_filter) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get report data
$report_data = [];
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
        $where_clause
    ");
    $stmt->execute($params);
    $report_data['overview'] = $stmt->fetch();
    
    // Tickets by department
    $dept_where = str_replace('t.department_id = ?', 't.department_id = d.id', $where_clause);
    if ($user_role == 'admin' && !$department_filter) {
        $stmt = $pdo->prepare("
            SELECT d.name as department_name, 
                   COUNT(t.id) as ticket_count,
                   SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                   AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END) as avg_resolution_time
            FROM departments d
            LEFT JOIN tickets t ON d.id = t.department_id AND DATE(t.created_at) BETWEEN ? AND ?
            WHERE d.is_active = 1
            GROUP BY d.id, d.name
            ORDER BY ticket_count DESC
        ");
        $stmt->execute([$date_from, $date_to]);
        $report_data['by_department'] = $stmt->fetchAll();
    }
    
    // Tickets by priority
    $stmt = $pdo->prepare("
        SELECT priority, COUNT(*) as count
        FROM tickets t
        $where_clause
        GROUP BY priority
        ORDER BY FIELD(priority, 'emergency', 'high', 'medium', 'low')
    ");
    $stmt->execute($params);
    $report_data['by_priority'] = $stmt->fetchAll();
    
    // Tickets by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM tickets t
        $where_clause
        GROUP BY status
        ORDER BY FIELD(status, 'open', 'in_progress', 'resolved', 'closed', 'cancelled')
    ");
    $stmt->execute($params);
    $report_data['by_status'] = $stmt->fetchAll();
    
    // Daily ticket creation trend
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM tickets t
        $where_clause
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute($params);
    $report_data['daily_trend'] = $stmt->fetchAll();
    
    // Top categories
    $stmt = $pdo->prepare("
        SELECT sc.name as category_name, COUNT(t.id) as count
        FROM service_categories sc
        LEFT JOIN tickets t ON sc.id = t.category_id
        $where_clause
        GROUP BY sc.id, sc.name
        HAVING count > 0
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $report_data['top_categories'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $report_data = [
        'overview' => ['total_tickets' => 0, 'open_tickets' => 0, 'in_progress_tickets' => 0, 
                      'resolved_tickets' => 0, 'closed_tickets' => 0, 'emergency_tickets' => 0,
                      'high_priority_tickets' => 0, 'avg_resolution_time' => 0],
        'by_department' => [],
        'by_priority' => [],
        'by_status' => [],
        'daily_trend' => [],
        'top_categories' => []
    ];
}

// Get departments for filter (admin only)
$departments = [];
if ($user_role == 'admin') {
    try {
        $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
        $departments = $stmt->fetchAll();
    } catch (PDOException $e) {
        $departments = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 dashboard-content">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-bar text-success me-2"></i>
                        Reports & Analytics
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-success" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-1"></i>
                                Export PDF
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-1"></i>
                                Export Excel
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <?php if ($user_role == 'admin'): ?>
                            <div class="col-md-3">
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
                            <?php endif; ?>
                            
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="open" <?php echo ($status_filter == 'open') ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo ($status_filter == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo ($status_filter == 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="closed" <?php echo ($status_filter == 'closed') ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                            
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-filter"></i>
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
                                    Overview Statistics
                                    <small class="text-muted">
                                        (<?php echo date('M j, Y', strtotime($date_from)); ?> - 
                                         <?php echo date('M j, Y', strtotime($date_to)); ?>)
                                    </small>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                        <div class="text-center">
                                            <div class="h3 text-primary mb-1">
                                                <?php echo number_format($report_data['overview']['total_tickets']); ?>
                                            </div>
                                            <div class="small text-muted">Total Tickets</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                        <div class="text-center">
                                            <div class="h3 text-warning mb-1">
                                                <?php echo number_format($report_data['overview']['open_tickets']); ?>
                                            </div>
                                            <div class="small text-muted">Open</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                        <div class="text-center">
                                            <div class="h3 text-info mb-1">
                                                <?php echo number_format($report_data['overview']['in_progress_tickets']); ?>
                                            </div>
                                            <div class="small text-muted">In Progress</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                        <div class="text-center">
                                            <div class="h3 text-success mb-1">
                                                <?php echo number_format($report_data['overview']['resolved_tickets']); ?>
                                            </div>
                                            <div class="small text-muted">Resolved</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                        <div class="text-center">
                                            <div class="h3 text-danger mb-1">
                                                <?php echo number_format($report_data['overview']['emergency_tickets']); ?>
                                            </div>
                                            <div class="small text-muted">Emergency</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                        <div class="text-center">
                                            <div class="h3 text-secondary mb-1">
                                                <?php echo round($report_data['overview']['avg_resolution_time'], 1); ?>h
                                            </div>
                                            <div class="small text-muted">Avg Resolution</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Status Distribution -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">Tickets by Status</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Priority Distribution -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">Tickets by Priority</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="priorityChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Trend -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">Daily Ticket Creation Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="trendChart" width="400" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tables Row -->
                <div class="row">
                    <!-- Department Performance (Admin only) -->
                    <?php if ($user_role == 'admin' && !empty($report_data['by_department'])): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">Performance by Department</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Total</th>
                                                <th>Resolved</th>
                                                <th>Rate</th>
                                                <th>Avg Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['by_department'] as $dept): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                                    <td><?php echo $dept['ticket_count']; ?></td>
                                                    <td><?php echo $dept['resolved_count']; ?></td>
                                                    <td>
                                                        <?php 
                                                        $rate = $dept['ticket_count'] > 0 ? 
                                                               round(($dept['resolved_count'] / $dept['ticket_count']) * 100, 1) : 0;
                                                        echo $rate . '%';
                                                        ?>
                                                    </td>
                                                    <td><?php echo round($dept['avg_resolution_time'], 1); ?>h</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Top Categories -->
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">Top Service Categories</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($report_data['top_categories'])): ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-chart-bar fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">No data available</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($report_data['top_categories'] as $category): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small"><?php echo htmlspecialchars($category['category_name']); ?></span>
                                            <div>
                                                <span class="badge bg-success"><?php echo $category['count']; ?></span>
                                            </div>
                                        </div>
                                        <div class="progress mb-3" style="height: 4px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo ($category['count'] / $report_data['top_categories'][0]['count']) * 100; ?>%"></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.dashboard-sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo "'" . implode("','", array_map(function($item) { return ucfirst(str_replace('_', ' ', $item['status'])); }, $report_data['by_status'])) . "'"; ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($report_data['by_status'], 'count')); ?>],
                    backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#6c757d', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Priority Chart
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_map('ucfirst', array_column($report_data['by_priority'], 'priority'))) . "'"; ?>],
                datasets: [{
                    label: 'Tickets',
                    data: [<?php echo implode(',', array_column($report_data['by_priority'], 'count')); ?>],
                    backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#28a745']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($report_data['daily_trend'], 'date')) . "'"; ?>],
                datasets: [{
                    label: 'Tickets Created',
                    data: [<?php echo implode(',', array_column($report_data['daily_trend'], 'count')); ?>],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Export functions
        function exportToPDF() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'pdf');
            window.open('export.php?' + params.toString(), '_blank');
        }

        function exportToExcel() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.open('export.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>