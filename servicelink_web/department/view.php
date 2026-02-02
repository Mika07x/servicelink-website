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
$ticket_id = $_GET['id'] ?? null;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

// Get ticket details - department admin can access tickets in their department
$ticket = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, sc.name as category_name, ssc.name as subcategory_name, d.name as department_name,
               l.name as location_name, l.building, l.floor, l.room, c.name as campus_name,
               CONCAT(requester.first_name, ' ', requester.last_name) as requester_name,
               requester.email as requester_email, requester.phone_number as requester_phone,
               requester.user_number, requester.year_level,
               requester.profile_picture as requester_profile,
               CONCAT(staff.first_name, ' ', staff.last_name) as assigned_staff_name,
               staff.email as assigned_staff_email
        FROM tickets t 
        LEFT JOIN service_categories sc ON t.category_id = sc.id
        LEFT JOIN service_categories ssc ON t.subcategory_id = ssc.id
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN locations l ON t.location_id = l.id
        LEFT JOIN campuses c ON l.campus_id = c.id
        LEFT JOIN users requester ON t.requester_id = requester.id
        LEFT JOIN users staff ON t.assigned_to = staff.id
        WHERE t.id = ? AND t.department_id = ?
    ");
    $stmt->execute([$ticket_id, $department_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        header('Location: tickets.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: tickets.php');
    exit;
}

// Get ticket attachments
$attachments = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM ticket_attachments WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->execute([$ticket_id]);
    $attachments = $stmt->fetchAll();
} catch (PDOException $e) {
    $attachments = [];
}

// Get ticket comments
$comments = [];
try {
    $stmt = $pdo->prepare("
        SELECT tc.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.role as user_role,
               u.profile_picture
        FROM ticket_comments tc
        JOIN users u ON tc.user_id = u.id
        WHERE tc.ticket_id = ? AND (tc.is_internal = 0 OR ? IN ('admin', 'department_admin', 'staff'))
        ORDER BY tc.created_at ASC
    ");
    $stmt->execute([$ticket_id, $user_role]);
    $comments = $stmt->fetchAll();
} catch (PDOException $e) {
    $comments = [];
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    $is_internal = isset($_POST['is_internal']) && in_array($user_role, ['admin', 'department_admin', 'staff']) ? 1 : 0;
    
    if (!empty($comment)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$ticket_id, $user_id, $comment, $is_internal]);
            
            // Redirect to prevent form resubmission
            header("Location: view.php?id=$ticket_id");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding comment: " . $e->getMessage();
        }
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $resolution = $_POST['resolution'] ?? '';
    $changed_by = $user_id;
    
    try {
        if ($new_status == 'resolved') {
            $stmt = $pdo->prepare("
                UPDATE tickets 
                SET status = ?, resolution = ?, resolved_at = NOW(), updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $resolution, $ticket_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE tickets 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $ticket_id]);
        }
        
        // Log the status change
        $stmt = $pdo->prepare("
            INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$ticket_id, $ticket['status'], $new_status, $changed_by]);
        
        // Redirect to refresh data
        header("Location: view.php?id=$ticket_id");
        exit;
    } catch (PDOException $e) {
        $error = "Error updating ticket: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/top_nav.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
        
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-ticket-alt text-success me-2"></i>
                    Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="tickets.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            Back to Tickets
                        </a>
                        <a href="edit.php?id=<?php echo $ticket['id']; ?>" class="btn btn-success">
                            <i class="fas fa-edit me-1"></i>
                            Edit Ticket
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Ticket Details -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card shadow">
                        <div class="card-header bg-light border-0 py-3">
                            <h6 class="m-0 font-weight-bold text-dark">
                                <i class="fas fa-info-circle me-2"></i>
                                Ticket Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <h5><?php echo htmlspecialchars($ticket['title']); ?></h5>
                                    <p class="text-muted mb-2"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <?php if ($ticket['category_name']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($ticket['category_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($ticket['subcategory_name']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['subcategory_name']); ?></span>
                                        <?php endif; ?>
                                        <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                            <?php echo ucfirst($ticket['priority']); ?> Priority
                                        </span>
                                        <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-dark">Requester Information</h6>
                                    <div class="d-flex align-items-center mb-2">
                                        <?php if ($ticket['requester_profile']): ?>
                                            <img src="../<?php echo htmlspecialchars($ticket['requester_profile']); ?>" 
                                                 alt="Profile" class="rounded-circle me-2" 
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="fas fa-user text-success"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($ticket['requester_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($ticket['user_number']); ?></small>
                                        </div>
                                    </div>
                                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($ticket['requester_email']); ?></p>
                                    <?php if ($ticket['requester_phone']): ?>
                                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($ticket['requester_phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($ticket['year_level']): ?>
                                        <p class="mb-3"><strong>Year Level:</strong> <?php echo htmlspecialchars($ticket['year_level']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-dark">Assignment & Location</h6>
                                    <?php if ($ticket['assigned_staff_name']): ?>
                                        <p class="mb-1"><strong>Assigned to:</strong> <?php echo htmlspecialchars($ticket['assigned_staff_name']); ?></p>
                                        <p class="mb-1"><strong>Staff Email:</strong> <?php echo htmlspecialchars($ticket['assigned_staff_email']); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted mb-1">Not yet assigned</p>
                                    <?php endif; ?>
                                    
                                    <?php if ($ticket['location_name']): ?>
                                        <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($ticket['location_name']); ?></p>
                                        <?php if ($ticket['building']): ?>
                                            <p class="mb-1"><strong>Building:</strong> <?php echo htmlspecialchars($ticket['building']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($ticket['floor'] || $ticket['room']): ?>
                                            <p class="mb-1"><strong>Room:</strong> 
                                                <?php echo htmlspecialchars(($ticket['floor'] ? $ticket['floor'] . ' - ' : '') . $ticket['room']); ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <p class="mb-1"><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></p>
                                    <?php if ($ticket['resolved_at']): ?>
                                        <p class="mb-1"><strong>Resolved:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['resolved_at'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Attachments -->
                            <?php if (!empty($attachments)): ?>
                                <div class="mt-4">
                                    <h6 class="text-dark">Attachments</h6>
                                    <div class="row">
                                        <?php foreach ($attachments as $attachment): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="card">
                                                    <div class="card-body p-2">
                                                        <?php if ($attachment['attachment_type'] == 'image'): ?>
                                                            <img src="../<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                                 class="img-fluid rounded mb-2" alt="Attachment">
                                                        <?php elseif ($attachment['attachment_type'] == 'video'): ?>
                                                            <video controls class="w-100 rounded mb-2">
                                                                <source src="../<?php echo htmlspecialchars($attachment['file_path']); ?>">
                                                            </video>
                                                        <?php else: ?>
                                                            <div class="text-center py-3">
                                                                <i class="fas fa-file fa-2x text-muted mb-2"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <small class="text-muted d-block">
                                                            <?php echo htmlspecialchars($attachment['original_filename']); ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            <?php echo formatFileSize($attachment['file_size']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($ticket['resolution']): ?>
                                <div class="mt-3">
                                    <h6 class="text-dark">Resolution</h6>
                                    <div class="alert alert-success">
                                        <?php echo nl2br(htmlspecialchars($ticket['resolution'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Comments Section -->
                    <div class="card shadow mt-4">
                        <div class="card-header bg-light border-0 py-3">
                            <h6 class="m-0 font-weight-bold text-dark">
                                <i class="fas fa-comments me-2"></i>
                                Comments & Updates
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($comments)): ?>
                                <p class="text-muted text-center py-3">No comments yet.</p>
                            <?php else: ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="border-bottom pb-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="d-flex align-items-center">
                                                <?php if ($comment['profile_picture']): ?>
                                                    <img src="../<?php echo htmlspecialchars($comment['profile_picture']); ?>" 
                                                         alt="Profile" class="rounded-circle me-2" 
                                                         style="width: 32px; height: 32px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 32px; height: 32px;">
                                                        <i class="fas fa-user text-success" style="font-size: 12px;"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                                    <span class="badge bg-<?php echo getRoleColor($comment['user_role']); ?> ms-2">
                                                        <?php echo ucfirst(str_replace('_', ' ', $comment['user_role'])); ?>
                                                    </span>
                                                    <?php if ($comment['is_internal']): ?>
                                                        <span class="badge bg-warning ms-1">Internal</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mt-2 mb-0"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Add Comment Form -->
                            <form method="POST" class="mt-4">
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Add Comment</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_internal" name="is_internal">
                                    <label class="form-check-label" for="is_internal">
                                        Internal comment (not visible to requester)
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-comment me-1"></i>
                                    Add Comment
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Actions -->
                <div class="col-lg-4">
                    <!-- Status Update -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-light border-0 py-3">
                            <h6 class="m-0 font-weight-bold text-dark">
                                <i class="fas fa-edit me-2"></i>
                                Update Status
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="new" <?php echo $ticket['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                        <option value="pending" <?php echo $ticket['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="assigned" <?php echo $ticket['status'] == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                        <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="on_hold" <?php echo $ticket['status'] == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                        <option value="resolved" <?php echo $ticket['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        <option value="reopen" <?php echo $ticket['status'] == 'reopen' ? 'selected' : ''; ?>>Reopen</option>
                                    </select>
                                </div>
                                <div class="mb-3" id="resolution-field" style="display: none;">
                                    <label for="resolution" class="form-label">Resolution</label>
                                    <textarea class="form-control" id="resolution" name="resolution" rows="3"><?php echo htmlspecialchars($ticket['resolution'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" name="update_status" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i>
                                    Update Status
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card shadow">
                        <div class="card-header bg-light border-0 py-3">
                            <h6 class="m-0 font-weight-bold text-dark">
                                <i class="fas fa-bolt me-2"></i>
                                Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="edit.php?id=<?php echo $ticket['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-edit me-2"></i>
                                    Edit Ticket
                                </a>
                                <a href="tickets.php" class="btn btn-outline-success">
                                    <i class="fas fa-list me-2"></i>
                                    All Tickets
                                </a>
                                <button onclick="window.print()" class="btn btn-outline-secondary">
                                    <i class="fas fa-print me-2"></i>
                                    Print Ticket
                                </button>
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

        // Show/hide resolution field based on status
        document.getElementById('status').addEventListener('change', function() {
            const resolutionField = document.getElementById('resolution-field');
            if (this.value === 'resolved') {
                resolutionField.style.display = 'block';
                document.getElementById('resolution').required = true;
            } else {
                resolutionField.style.display = 'none';
                document.getElementById('resolution').required = false;
            }
        });

        // Initialize resolution field visibility
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('status');
            if (statusSelect && statusSelect.value === 'resolved') {
                document.getElementById('resolution-field').style.display = 'block';
            }
        });
    </script>
</body>
</html>