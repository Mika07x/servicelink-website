<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$ticket_id = $_GET['ticket_id'] ?? null;

// Handle chat type selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['chat_type'])) {
    $chat_type = $_POST['chat_type'];
    
    if ($chat_type === 'ticket' && isset($_POST['selected_ticket'])) {
        $selected_ticket = $_POST['selected_ticket'];
        header("Location: chat.php?ticket_id=$selected_ticket");
        exit;
    } elseif ($chat_type === 'user' && isset($_POST['selected_user'])) {
        $selected_user = $_POST['selected_user'];
        // Create a new general support ticket for this user
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tickets (ticket_number, title, description, requester_id, category_id, priority, status, created_at) 
                VALUES (?, ?, ?, ?, 1, 'medium', 'open', NOW())
            ");
            $ticket_number = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $title = 'Admin Chat Support Session';
            $description = 'Direct chat support session initiated by admin.';
            
            $stmt->execute([$ticket_number, $title, $description, $selected_user]);
            $new_ticket_id = $pdo->lastInsertId();
            
            header("Location: chat.php?ticket_id=$new_ticket_id");
            exit;
        } catch (PDOException $e) {
            $error = "Error creating chat session: " . $e->getMessage();
        }
    }
}

// Get all tickets for admin
$tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) as requester_name,
               sc.name as category_name
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        WHERE t.status IN ('open', 'in_progress')
        ORDER BY t.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    $tickets = [];
}

// Get all users for direct chat
$users = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as name, email, role, user_number
        FROM users 
        WHERE status = 'active' AND role = 'user'
        ORDER BY first_name, last_name
        LIMIT 50
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

// Get recent chat sessions
$recent_chats = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.id, t.ticket_number, t.title, 
               CONCAT(u.first_name, ' ', u.last_name) as requester_name,
               t.status, t.updated_at,
               (SELECT COUNT(*) FROM ticket_comments tc WHERE tc.ticket_id = t.id AND tc.is_internal = 0) as comment_count
        FROM tickets t
        LEFT JOIN users u ON t.requester_id = u.id
        WHERE EXISTS (
            SELECT 1 FROM ticket_comments tc 
            WHERE tc.ticket_id = t.id AND tc.user_id = ?
        )
        ORDER BY t.updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_chats = $stmt->fetchAll();
} catch (PDOException $e) {
    $recent_chats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Support - ServiceLink Admin</title>
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
                    <i class="fas fa-comments text-success me-2"></i>
                    Chat Support Center
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="tickets.php" class="btn btn-outline-success">
                            <i class="fas fa-list me-1"></i>
                            All Tickets
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Chat Options -->
                <div class="col-lg-8 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h5 class="card-title mb-0 text-dark">
                                <i class="fas fa-comment-dots me-2"></i>
                                Start a Chat Session
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="radio" name="chat_type" id="ticket_chat" value="ticket" checked>
                                                    <label class="form-check-label" for="ticket_chat">
                                                        <i class="fas fa-ticket-alt text-success me-1"></i>
                                                        <strong>Existing Ticket</strong>
                                                    </label>
                                                </div>
                                                <p class="text-muted mb-3">Continue conversation on an existing support ticket</p>
                                                
                                                <div id="ticket_selection">
                                                    <select class="form-select" name="selected_ticket">
                                                        <option value="">Select a ticket...</option>
                                                        <?php foreach ($tickets as $ticket): ?>
                                                            <option value="<?php echo $ticket['id']; ?>">
                                                                #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - 
                                                                <?php echo htmlspecialchars($ticket['title']); ?>
                                                                (<?php echo htmlspecialchars($ticket['requester_name']); ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="radio" name="chat_type" id="user_chat" value="user">
                                                    <label class="form-check-label" for="user_chat">
                                                        <i class="fas fa-user text-success me-1"></i>
                                                        <strong>Direct User Chat</strong>
                                                    </label>
                                                </div>
                                                <p class="text-muted mb-3">Start a new chat session with any user</p>
                                                
                                                <div id="user_selection" style="display: none;">
                                                    <select class="form-select" name="selected_user">
                                                        <option value="">Select a user...</option>
                                                        <?php foreach ($users as $user): ?>
                                                            <option value="<?php echo $user['id']; ?>">
                                                                <?php echo htmlspecialchars($user['name']); ?>
                                                                <?php if ($user['user_number']): ?>
                                                                    (<?php echo htmlspecialchars($user['user_number']); ?>)
                                                                <?php endif; ?>
                                                                - <?php echo htmlspecialchars($user['email']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-comments me-2"></i>
                                        Start Chat Session
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Recent Chats & Quick Stats -->
                <div class="col-lg-4">
                    <!-- Recent Chat Sessions -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-history text-success me-2"></i>
                                Recent Chat Sessions
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_chats)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-comments fa-2x text-muted mb-2"></i>
                                    <p class="text-muted small">No recent chat sessions</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_chats as $chat): ?>
                                        <div class="list-group-item px-0 py-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 small">
                                                        #<?php echo htmlspecialchars($chat['ticket_number']); ?>
                                                    </h6>
                                                    <p class="mb-1 small text-muted">
                                                        <?php echo htmlspecialchars($chat['requester_name']); ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        <?php echo $chat['comment_count']; ?> messages • 
                                                        <?php echo timeAgo($chat['updated_at']); ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <a href="chat.php?ticket_id=<?php echo $chat['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-comments"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-chart-bar text-success me-2"></i>
                                Support Overview
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border-end">
                                        <h4 class="text-success mb-1"><?php echo count($tickets); ?></h4>
                                        <small class="text-muted">Active Tickets</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <h4 class="text-success mb-1"><?php echo count($users); ?></h4>
                                    <small class="text-muted">Active Users</small>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 mt-3">
                                <a href="tickets.php" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-list me-1"></i>
                                    View All Tickets
                                </a>
                                <a href="users.php" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-users me-1"></i>
                                    Manage Users
                                </a>
                                <a href="reports.php" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-chart-line me-1"></i>
                                    View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Tickets Table -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-ticket-alt text-success me-2"></i>
                                Active Support Tickets
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($tickets)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Active Tickets</h5>
                                    <p class="text-muted">All tickets are currently resolved or closed.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Ticket #</th>
                                                <th>Title</th>
                                                <th>Requester</th>
                                                <th>Category</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tickets as $ticket): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($ticket['title']); ?></div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($ticket['requester_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($ticket['category_name'] ?? 'General'); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_colors = [
                                                            'open' => 'warning',
                                                            'in_progress' => 'info',
                                                            'resolved' => 'success',
                                                            'closed' => 'secondary'
                                                        ];
                                                        $color = $status_colors[$ticket['status']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="view.php?id=<?php echo $ticket['id']; ?>" 
                                                               class="btn btn-outline-success" title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="chat.php?ticket_id=<?php echo $ticket['id']; ?>" 
                                                               class="btn btn-outline-success" title="Chat">
                                                                <i class="fas fa-comments"></i>
                                                            </a>
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
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle chat type selection
        document.addEventListener('DOMContentLoaded', function() {
            const ticketRadio = document.getElementById('ticket_chat');
            const userRadio = document.getElementById('user_chat');
            const ticketSelection = document.getElementById('ticket_selection');
            const userSelection = document.getElementById('user_selection');

            function toggleSelections() {
                if (ticketRadio.checked) {
                    ticketSelection.style.display = 'block';
                    userSelection.style.display = 'none';
                } else {
                    ticketSelection.style.display = 'none';
                    userSelection.style.display = 'block';
                }
            }

            ticketRadio.addEventListener('change', toggleSelections);
            userRadio.addEventListener('change', toggleSelections);

            // Initial state
            toggleSelections();

            // Smooth page transition effect
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
    </script>
</body>
</html>