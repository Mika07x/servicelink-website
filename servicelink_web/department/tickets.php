<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is department admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'department_admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$assigned_filter = $_GET['assigned'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build WHERE clause based on user role and filters
$where_conditions = [];
$params = [];

// Department admin can only see tickets from their department
$where_conditions[] = "t.department_id = ?";
$params[] = $department_id;

// Apply filters
if ($status_filter) {
    $statuses = explode(',', $status_filter);
    $placeholders = str_repeat('?,', count($statuses) - 1) . '?';
    $where_conditions[] = "t.status IN ($placeholders)";
    $params = array_merge($params, $statuses);
}

if ($priority_filter) {
    $priorities = explode(',', $priority_filter);
    $placeholders = str_repeat('?,', count($priorities) - 1) . '?';
    $where_conditions[] = "t.priority IN ($placeholders)";
    $params = array_merge($params, $priorities);
}

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR t.ticket_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM tickets t
    LEFT JOIN users u ON t.requester_id = u.id
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN service_categories sc ON t.category_id = sc.id
    LEFT JOIN users staff ON t.assigned_to = staff.id
    $where_clause
";

$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_tickets = $stmt->fetch()['total'];
$total_pages = ceil($total_tickets / $per_page);

// Get tickets
$sql = "
    SELECT t.*, 
           CONCAT(u.first_name, ' ', u.last_name) as requester_name,
           u.email as requester_email,
           d.name as department_name,
           sc.name as category_name,
           CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name
    FROM tickets t
    LEFT JOIN users u ON t.requester_id = u.id
    LEFT JOIN departments d ON t.department_id = d.id
    LEFT JOIN service_categories sc ON t.category_id = sc.id
    LEFT JOIN users staff ON t.assigned_to = staff.id
    $where_clause
    ORDER BY t.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Tickets - ServiceLink</title>
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
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-ticket-alt text-success me-2"></i>
                    Department Tickets
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Error Message Display -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo htmlspecialchars($_SESSION['error_message']); 
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search tickets...">
                        </div>
                        
                        <div class="col-md-2">
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
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-success me-2">
                                <i class="fas fa-search me-1"></i>
                                Filter
                            </button>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tickets List -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            Department Tickets (<?php echo $total_tickets; ?> total)
                        </h5>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($tickets)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No tickets found</h5>
                            <p class="text-muted">
                                No tickets match your current filters.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Ticket #</th>
                                        <th>Title</th>
                                        <th>Requester</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket): ?>
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
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($ticket['department_name']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars($ticket['requester_name']); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($ticket['requester_email']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($ticket['category_name'] ?? 'Uncategorized'); ?>
                                                </small>
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
                                                    <br>
                                                    <?php echo date('g:i A', strtotime($ticket['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view.php?id=<?php echo $ticket['id']; ?>" 
                                                       class="btn btn-outline-success" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($ticket['status'] != 'closed'): ?>
                                                        <a href="edit.php?id=<?php echo $ticket['id']; ?>" 
                                                           class="btn btn-outline-success" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Tickets pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
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
        
        // Ensure main page scrolling works properly after page load
        document.addEventListener('DOMContentLoaded', function() {
            // Reset any potential scroll locks
            document.body.style.overflow = 'auto';
            document.documentElement.style.overflow = 'auto';
            
            // Scroll to top after filtering to ensure visibility
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('status') || urlParams.has('priority') || urlParams.has('search')) {
                setTimeout(function() {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 100);
            }
        });
    </script>
</body>
</html>