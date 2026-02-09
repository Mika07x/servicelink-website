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
$user_role = $_SESSION['user_role'];
$department_id = $_SESSION['department_id'];
$ticket_id = $_GET['id'] ?? null;

if (!$ticket_id) {
    header('Location: tickets.php');
    exit;
}

// Get ticket details - staff can access tickets in their department or assigned to them
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
        WHERE t.id = ? AND (t.department_id = ? OR t.assigned_to = ?)
    ");
    $stmt->execute([$ticket_id, $department_id, $user_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        header('Location: tickets.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: tickets.php');
    exit;
}

// Get ticket attachments (initial submission from requester)
$attachments = [];
try {
    $stmt = $pdo->prepare("
        SELECT ta.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
        FROM ticket_attachments ta
        LEFT JOIN users u ON ta.uploaded_by = u.id
        WHERE ta.ticket_id = ? AND ta.uploaded_by = ?
        ORDER BY ta.created_at ASC
    ");
    $stmt->execute([$ticket_id, $ticket['requester_id']]);
    $attachments = $stmt->fetchAll();
} catch (PDOException $e) {
    $attachments = [];
}

// Get ALL proof of work attachments (uploaded by staff/dept admin) - visible to everyone
$proof_attachments = [];
try {
    $stmt = $pdo->prepare("
        SELECT ta.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name, u.role
        FROM ticket_attachments ta
        LEFT JOIN users u ON ta.uploaded_by = u.id
        WHERE ta.ticket_id = ? AND ta.uploaded_by != ?
        ORDER BY ta.created_at DESC
    ");
    $stmt->execute([$ticket_id, $ticket['requester_id']]);
    $proof_attachments = $stmt->fetchAll();
} catch (PDOException $e) {
    $proof_attachments = [];
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

// Handle status update with proof of work
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $resolution = trim($_POST['resolution'] ?? '');
    $work_notes = trim($_POST['work_notes'] ?? '');
    $changed_by = $user_id;
    
    // Staff can only update to operational statuses
    $allowed_statuses = ['in_progress', 'resolved', 'closed'];
    if (!in_array($new_status, $allowed_statuses)) {
        $error = "Staff can only update to: In Progress, Resolved, or Closed status.";
    } else {
        // Validate resolution is required for resolved status
        if ($new_status == 'resolved' && empty($resolution)) {
            $error = "Resolution details are required when marking ticket as resolved.";
        } else {
            try {
                // Update ticket
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
                    INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$ticket_id, $ticket['status'], $new_status, $changed_by, $work_notes]);
                
                // Handle proof of work file uploads
                if (isset($_FILES['proof_files']) && !empty($_FILES['proof_files']['name'][0])) {
                    $upload_dir = '../uploads/proof_of_work/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $max_files = 5;
                    $max_size = 10 * 1024 * 1024; // 10MB
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/mpeg', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    
                    $file_count = min(count($_FILES['proof_files']['name']), $max_files);
                    
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['proof_files']['error'][$i] == 0) {
                            $file_size = $_FILES['proof_files']['size'][$i];
                            $file_type = $_FILES['proof_files']['type'][$i];
                            $file_name = $_FILES['proof_files']['name'][$i];
                            
                            if ($file_size <= $max_size && in_array($file_type, $allowed_types)) {
                                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                                $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
                                $file_path = $upload_dir . $unique_name;
                                
                                if (move_uploaded_file($_FILES['proof_files']['tmp_name'][$i], $file_path)) {
                                    // Determine attachment type
                                    $attachment_type = 'document';
                                    if (strpos($file_type, 'image') !== false) {
                                        $attachment_type = 'image';
                                    } elseif (strpos($file_type, 'video') !== false) {
                                        $attachment_type = 'video';
                                    }
                                    
                                    // Save to database
                                    $stmt = $pdo->prepare("
                                        INSERT INTO ticket_attachments (ticket_id, file_path, original_filename, file_size, attachment_type, uploaded_by, created_at)
                                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                                    ");
                                    $stmt->execute([$ticket_id, $file_path, $file_name, $file_size, $attachment_type, $user_id]);
                                }
                            }
                        }
                    }
                }
                
                // Redirect to refresh data
                header("Location: view.php?id=$ticket_id");
                exit;
            } catch (PDOException $e) {
                $error = "Error updating ticket: " . $e->getMessage();
            }
        }
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
    <style>
        .comment-bubble {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .comment-bubble.own {
            background: #28a745;
            color: white;
            margin-left: 20%;
        }
        
        .comment-bubble:not(.own) {
            margin-right: 20%;
        }
        
        .comment-meta {
            font-size: 0.85em;
            opacity: 0.8;
            margin-top: 8px;
        }
    </style>
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
                <a href="tickets.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    Back to Tickets
                </a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Main Ticket Details -->
                <div class="col-lg-8 mb-4">
                    <!-- Ticket Header -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div class="flex-grow-1">
                                    <h3 class="mb-2"><?php echo htmlspecialchars($ticket['title']); ?></h3>
                                    <div class="d-flex gap-2 flex-wrap mb-3">
                                        <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                        <span class="badge bg-<?php echo getPriorityColor($ticket['priority']); ?>">
                                            <?php echo ucfirst($ticket['priority']); ?> Priority
                                        </span>
                                        <?php if ($ticket['category_name']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($ticket['category_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($ticket['subcategory_name']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($ticket['subcategory_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Description</h6>
                                    <p class="mb-3"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Details</h6>
                                    <div class="small">
                                        <div class="mb-1"><strong>Ticket #:</strong> <?php echo htmlspecialchars($ticket['ticket_number']); ?></div>
                                        <div class="mb-1"><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></div>
                                        <?php if ($ticket['location_name']): ?>
                                            <div class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($ticket['location_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($ticket['department_name']): ?>
                                            <div class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($ticket['department_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($ticket['assigned_staff_name']): ?>
                                            <div class="mb-1"><strong>Assigned to:</strong> <?php echo htmlspecialchars($ticket['assigned_staff_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($ticket['resolved_at']): ?>
                                            <div class="mb-1"><strong>Resolved:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['resolved_at'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3 pt-3 border-top">
                                <div class="col-md-12">
                                    <h6 class="text-muted mb-3">Requester Information</h6>
                                    <div class="d-flex align-items-center">
                                        <?php if ($ticket['requester_profile']): ?>
                                            <img src="../<?php echo htmlspecialchars($ticket['requester_profile']); ?>" 
                                                 alt="Profile" class="rounded-circle me-3" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-user-graduate fa-lg text-success"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-1"><strong><?php echo htmlspecialchars($ticket['requester_name']); ?></strong></div>
                                                    <div class="small text-muted mb-1"><?php echo htmlspecialchars($ticket['requester_email']); ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <?php if ($ticket['user_number']): ?>
                                                        <div class="small mb-1"><strong>Student #:</strong> <?php echo htmlspecialchars($ticket['user_number']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($ticket['year_level']): ?>
                                                        <div class="small mb-1"><strong>Year Level:</strong> <?php echo htmlspecialchars($ticket['year_level']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($ticket['requester_phone']): ?>
                                                        <div class="small"><strong>Phone:</strong> <?php echo htmlspecialchars($ticket['requester_phone']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($ticket['resolution']): ?>
                                <div class="mt-3 pt-3 border-top">
                                    <h6 class="text-success mb-2">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Resolution
                                    </h6>
                                    <div class="alert alert-success mb-0">
                                        <?php echo nl2br(htmlspecialchars($ticket['resolution'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Initial Attachments -->
                    <?php if (!empty($attachments)): ?>
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-paperclip text-success me-2"></i>
                                    Initial Attachments
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($attachments as $attachment): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card">
                                                <div class="card-body p-2">
                                                    <?php if ($attachment['attachment_type'] == 'image'): ?>
                                                        <img src="../<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                             class="img-fluid rounded mb-2" alt="Attachment" style="cursor: pointer;"
                                                             onclick="showImageModal('../<?php echo htmlspecialchars($attachment['file_path']); ?>', '<?php echo htmlspecialchars($attachment['original_filename']); ?>')">
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
                        </div>
                    <?php endif; ?>
                    
                    <!-- Proof of Work -->
                    <?php if (!empty($proof_attachments)): ?>
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Proof of Work
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($proof_attachments as $attachment): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-success">
                                                <div class="card-body p-2">
                                                    <?php if ($attachment['attachment_type'] == 'image'): ?>
                                                        <img src="../<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                             class="img-fluid rounded mb-2" alt="Proof of Work" style="cursor: pointer;"
                                                             onclick="showImageModal('../<?php echo htmlspecialchars($attachment['file_path']); ?>', '<?php echo htmlspecialchars($attachment['original_filename']); ?>')">
                                                    <?php elseif ($attachment['attachment_type'] == 'video'): ?>
                                                        <video controls class="w-100 rounded mb-2">
                                                            <source src="../<?php echo htmlspecialchars($attachment['file_path']); ?>">
                                                        </video>
                                                    <?php else: ?>
                                                        <div class="text-center py-3">
                                                            <i class="fas fa-file fa-2x text-success mb-2"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <small class="text-muted d-block">
                                                        <?php echo htmlspecialchars($attachment['original_filename']); ?>
                                                    </small>
                                                    <small class="text-muted d-block">
                                                        <?php echo formatFileSize($attachment['file_size']); ?>
                                                    </small>
                                                    <small class="text-success">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($attachment['uploaded_by_name']); ?>
                                                    </small>
                                                    <small class="text-muted d-block">
                                                        <?php echo date('M j, Y g:i A', strtotime($attachment['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Comments Section -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-comments text-success me-2"></i>
                                Comments & Updates
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($comments)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No comments yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="mb-4" style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($comments as $comment): ?>
                                        <?php $is_own = $comment['user_id'] == $user_id; ?>
                                        <div class="comment-bubble <?php echo $is_own ? 'own' : ''; ?>">
                                            <div><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></div>
                                            <div class="comment-meta">
                                                <?php if (!$is_own): ?>
                                                    <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                                    <span class="badge bg-<?php echo getRoleColor($comment['user_role']); ?> ms-1">
                                                        <?php echo ucfirst(str_replace('_', ' ', $comment['user_role'])); ?>
                                                    </span>
                                                    <?php if ($comment['is_internal']): ?>
                                                        <span class="badge bg-warning ms-1">Internal</span>
                                                    <?php endif; ?>
                                                    <br>
                                                <?php endif; ?>
                                                <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Add Comment Form -->
                            <div class="border-top pt-3">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Add a Comment</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="3" 
                                                  placeholder="Type your comment here..." required></textarea>
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
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Ticket History -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-light border-0">
                            <h6 class="card-title mb-0 text-dark">
                                <i class="fas fa-history me-2"></i>
                                Ticket History
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get ticket history
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT tsh.*, CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
                                    FROM ticket_status_history tsh
                                    LEFT JOIN users u ON tsh.changed_by = u.id
                                    WHERE tsh.ticket_id = ?
                                    ORDER BY tsh.created_at DESC
                                    LIMIT 10
                                ");
                                $stmt->execute([$ticket_id]);
                                $history = $stmt->fetchAll();
                                
                                if (empty($history)): ?>
                                    <p class="text-muted text-center small">No status changes yet.</p>
                                <?php else: ?>
                                    <div style="max-height: 300px; overflow-y: auto;">
                                        <?php foreach ($history as $entry): ?>
                                            <div class="border-bottom pb-2 mb-2">
                                                <div class="d-flex gap-2 mb-1">
                                                    <span class="badge bg-<?php echo getStatusColor($entry['old_status']); ?>" style="font-size: 0.7rem;">
                                                        <?php echo ucfirst(str_replace('_', ' ', $entry['old_status'])); ?>
                                                    </span>
                                                    <i class="fas fa-arrow-right text-muted" style="font-size: 0.7rem; margin-top: 4px;"></i>
                                                    <span class="badge bg-<?php echo getStatusColor($entry['new_status']); ?>" style="font-size: 0.7rem;">
                                                        <?php echo ucfirst(str_replace('_', ' ', $entry['new_status'])); ?>
                                                    </span>
                                                </div>
                                                <?php if ($entry['changed_by_name']): ?>
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($entry['changed_by_name']); ?>
                                                    </small>
                                                <?php endif; ?>
                                                <?php if ($entry['notes']): ?>
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-sticky-note me-1"></i>
                                                        <?php echo htmlspecialchars($entry['notes']); ?>
                                                    </small>
                                                <?php endif; ?>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($entry['created_at'])); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif;
                            } catch (PDOException $e) {
                                echo '<p class="text-muted small">Unable to load history.</p>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Requester Information -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-light border-0">
                            <h6 class="card-title mb-0 text-dark">
                                <i class="fas fa-tasks me-2"></i>
                                Update Work Progress
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status (Operational)</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="" disabled>── Administrative (Dept Admin) ──</option>
                                        <option value="new" disabled>New</option>
                                        <option value="pending" disabled>Pending</option>
                                        <option value="assigned" disabled>Assigned</option>
                                        <option value="on_hold" disabled>On Hold</option>
                                        <option value="reopen" disabled>Reopen</option>
                                        <option value="" disabled>── Operational (You) ──</option>
                                        <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="resolved" <?php echo $ticket['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                    <small class="text-muted">You handle the actual service work</small>
                                </div>
                                
                                <div class="mb-3" id="resolution-field" style="display: none;">
                                    <label for="resolution" class="form-label">Resolution Details <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="resolution" name="resolution" rows="3" placeholder="Describe how you resolved this issue..."><?php echo htmlspecialchars($ticket['resolution'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="work_notes" class="form-label">Work Notes</label>
                                    <textarea class="form-control" id="work_notes" name="work_notes" rows="2" placeholder="Describe what you did..."></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-paperclip me-1"></i>
                                        Proof of Work (Optional)
                                    </label>
                                    <input type="file" class="form-control" name="proof_files[]" id="proof_files" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle me-1"></i>
                                        You can upload multiple times - files will be added, not replaced
                                    </small>
                                    <small class="text-muted">Max 5 files per upload, 10MB each</small>
                                </div>
                                
                                <div id="file-preview" class="mb-3"></div>
                                
                                <button type="submit" name="update_status" class="btn btn-success w-100">
                                    <i class="fas fa-save me-1"></i>
                                    Update Progress
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h6 class="card-title mb-0 text-dark">
                                <i class="fas fa-bolt me-2"></i>
                                Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
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
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imageModalImg" src="" class="img-fluid" alt="Image Preview">
                </div>
            </div>
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

        // Image modal functionality
        function showImageModal(imagePath, filename) {
            document.getElementById('imageModalImg').src = imagePath;
            document.getElementById('imageModalTitle').textContent = filename;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
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
                document.getElementById('resolution').required = true;
            }
        });
        
        // File preview
        document.getElementById('proof_files').addEventListener('change', function(e) {
            const preview = document.getElementById('file-preview');
            preview.innerHTML = '';
            
            const files = Array.from(e.target.files).slice(0, 5); // Max 5 files
            
            if (files.length > 0) {
                const previewContainer = document.createElement('div');
                previewContainer.className = 'alert alert-info';
                previewContainer.innerHTML = '<strong>Files to upload:</strong>';
                
                const fileList = document.createElement('ul');
                fileList.className = 'mb-0 mt-2';
                
                files.forEach(file => {
                    const li = document.createElement('li');
                    const size = (file.size / 1024 / 1024).toFixed(2);
                    li.textContent = `${file.name} (${size} MB)`;
                    fileList.appendChild(li);
                });
                
                previewContainer.appendChild(fileList);
                preview.appendChild(previewContainer);
            }
        });
    </script>
</body>
</html>