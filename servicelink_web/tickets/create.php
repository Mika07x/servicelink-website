<?php
require_once '../config/session.php'; // Include session config FIRST
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a regular user
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'user') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get departments and categories
$departments = [];
$categories = [];

try {
    $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
    $departments = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, name, department_id FROM service_categories WHERE is_active = 1 ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to load form data.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = $_POST['category_id'];
    $urgency = $_POST['urgency'] ?? 'medium';
    
    // Validation
    if (empty($title) || empty($description) || empty($category_id)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Generate unique ticket number
            $ticket_number = generateTicketNumber($pdo);
            
            // Get category department
            $stmt = $pdo->prepare("SELECT department_id FROM service_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $category = $stmt->fetch();
            $department_id = $category['department_id'];
            
            // AI Analysis would go here - for now, we'll use the urgency as priority
            $priority_map = [
                'low' => 'low',
                'medium' => 'medium', 
                'high' => 'high',
                'urgent' => 'emergency'
            ];
            $priority = $priority_map[$urgency] ?? 'medium';
            
            // Create ticket
            $stmt = $pdo->prepare("
                INSERT INTO tickets (ticket_number, title, description, category_id, priority, 
                                   requester_id, department_id, ai_analysis) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $ai_analysis = "Auto-categorized based on user selection. Priority set to: $priority";
            
            if ($stmt->execute([$ticket_number, $title, $description, $category_id, $priority, 
                              $user_id, $department_id, $ai_analysis])) {
                
                $ticket_id = $pdo->lastInsertId();
                
                // Handle file uploads
                if (!empty($_FILES['attachments']['name'][0])) {
                    $upload_dir = '../uploads/tickets/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['attachments']['error'][$key] == 0) {
                            try {
                                $file_info = [
                                    'name' => $_FILES['attachments']['name'][$key],
                                    'tmp_name' => $tmp_name,
                                    'size' => $_FILES['attachments']['size'][$key],
                                    'error' => $_FILES['attachments']['error'][$key]
                                ];
                                
                                $upload_result = uploadFile($file_info, $upload_dir);
                                
                                // Save to database
                                $stmt = $pdo->prepare("
                                    INSERT INTO ticket_attachments 
                                    (ticket_id, filename, original_filename, file_path, file_size, mime_type, uploaded_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $ticket_id,
                                    $upload_result['filename'],
                                    $upload_result['original_filename'],
                                    $upload_result['file_path'],
                                    $upload_result['file_size'],
                                    $upload_result['mime_type'],
                                    $user_id
                                ]);
                            } catch (Exception $e) {
                                // Continue even if file upload fails
                            }
                        }
                    }
                }
                
                // Send notification to department staff
                $stmt = $pdo->prepare("
                    SELECT id FROM users 
                    WHERE department_id = ? AND role IN ('staff', 'department_admin') AND is_active = 1
                ");
                $stmt->execute([$department_id]);
                $staff_members = $stmt->fetchAll();
                
                foreach ($staff_members as $staff) {
                    sendNotification($pdo, $staff['id'], $ticket_id, 
                                   'New Ticket Assigned', 
                                   "A new ticket #{$ticket_number} has been submitted: {$title}",
                                   'ticket_created');
                }
                
                $success = "Ticket #{$ticket_number} has been created successfully!";
                
                // Clear form
                $_POST = [];
            } else {
                $error = 'Failed to create ticket. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Failed to create ticket. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Ticket - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                        <i class="fas fa-plus text-success me-2"></i>
                        Create New Ticket
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            Back to Tickets
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <div class="mt-2">
                            <a href="index.php" class="btn btn-success btn-sm">View My Tickets</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Create Ticket Form -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-edit text-success me-2"></i>
                                    Ticket Details
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                               placeholder="Brief description of your request" required>
                                        <div class="form-text">Provide a clear, concise title for your request</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="category_id" class="form-label">Service Category *</label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">Select a category</option>
                                                <?php 
                                                $current_dept = '';
                                                foreach ($categories as $category): 
                                                    // Group by department
                                                    $dept_name = '';
                                                    foreach ($departments as $dept) {
                                                        if ($dept['id'] == $category['department_id']) {
                                                            $dept_name = $dept['name'];
                                                            break;
                                                        }
                                                    }
                                                    
                                                    if ($dept_name != $current_dept) {
                                                        if ($current_dept != '') echo '</optgroup>';
                                                        echo '<optgroup label="' . htmlspecialchars($dept_name) . '">';
                                                        $current_dept = $dept_name;
                                                    }
                                                ?>
                                                    <option value="<?php echo $category['id']; ?>" 
                                                            <?php echo (($_POST['category_id'] ?? '') == $category['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php if ($current_dept != '') echo '</optgroup>'; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="urgency" class="form-label">Urgency Level *</label>
                                            <select class="form-select" id="urgency" name="urgency" required>
                                                <option value="low" <?php echo (($_POST['urgency'] ?? 'medium') == 'low') ? 'selected' : ''; ?>>
                                                    Low - Can wait a few days
                                                </option>
                                                <option value="medium" <?php echo (($_POST['urgency'] ?? 'medium') == 'medium') ? 'selected' : ''; ?>>
                                                    Medium - Normal priority
                                                </option>
                                                <option value="high" <?php echo (($_POST['urgency'] ?? 'medium') == 'high') ? 'selected' : ''; ?>>
                                                    High - Needs attention soon
                                                </option>
                                                <option value="urgent" <?php echo (($_POST['urgency'] ?? 'medium') == 'urgent') ? 'selected' : ''; ?>>
                                                    Urgent - Critical issue
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description *</label>
                                        <textarea class="form-control" id="description" name="description" rows="6" 
                                                  placeholder="Please provide detailed information about your request..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                        <div class="form-text">
                                            Include as much detail as possible: what happened, when it occurred, 
                                            steps you've already taken, error messages, etc.
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="attachments" class="form-label">Attachments (Optional)</label>
                                        <input type="file" class="form-control" id="attachments" name="attachments[]" 
                                               multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt">
                                        <div class="form-text">
                                            You can upload screenshots, documents, or other relevant files. 
                                            Max 10MB per file. Allowed: JPG, PNG, PDF, DOC, DOCX, TXT
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-paper-plane me-1"></i>
                                            Submit Ticket
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-1"></i>
                                            Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Help Sidebar -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-success text-white">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-question-circle me-2"></i>
                                    Need Help?
                                </h6>
                            </div>
                            <div class="card-body">
                                <h6 class="fw-bold">Tips for Better Support:</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Be specific in your title
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Include error messages
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Attach relevant screenshots
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Mention steps you've tried
                                    </li>
                                </ul>
                                
                                <hr>
                                
                                <h6 class="fw-bold">What We Don't Handle:</h6>
                                <ul class="list-unstyled text-muted small">
                                    <li class="mb-1">• Grade-related issues</li>
                                    <li class="mb-1">• Payment/billing concerns</li>
                                    <li class="mb-1">• Official document requests</li>
                                </ul>
                                <p class="small text-muted">
                                    These are handled by Registrar, Accounting, and Academic Affairs respectively.
                                </p>
                                
                                <hr>
                                
                                <div class="text-center">
                                    <a href="../contact.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-envelope me-1"></i>
                                        Contact Support
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Tickets -->
                        <div class="card border-0 shadow-sm mt-4">
                            <div class="card-header bg-white border-0">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    Your Recent Tickets
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php
                                try {
                                    $stmt = $pdo->prepare("
                                        SELECT ticket_number, title, status, created_at 
                                        FROM tickets 
                                        WHERE requester_id = ? 
                                        ORDER BY created_at DESC 
                                        LIMIT 3
                                    ");
                                    $stmt->execute([$user_id]);
                                    $recent_tickets = $stmt->fetchAll();
                                    
                                    if ($recent_tickets):
                                        foreach ($recent_tickets as $ticket):
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <div class="fw-bold small"><?php echo htmlspecialchars($ticket['ticket_number']); ?></div>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars(substr($ticket['title'], 0, 30)); ?>...
                                            </div>
                                        </div>
                                        <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?> small">
                                            <?php echo ucfirst($ticket['status']); ?>
                                        </span>
                                    </div>
                                <?php 
                                        endforeach;
                                    else:
                                ?>
                                    <p class="text-muted small mb-0">No previous tickets</p>
                                <?php 
                                    endif;
                                } catch (PDOException $e) {
                                    echo '<p class="text-muted small mb-0">Unable to load recent tickets</p>';
                                }
                                ?>
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
        
        // File upload preview
        document.getElementById('attachments').addEventListener('change', function(e) {
            const files = e.target.files;
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            for (let file of files) {
                if (file.size > maxSize) {
                    alert(`File "${file.name}" is too large. Maximum size is 10MB.`);
                    e.target.value = '';
                    break;
                }
            }
        });
        
        // Character counter for description
        const description = document.getElementById('description');
        const counter = document.createElement('div');
        counter.className = 'form-text text-end';
        description.parentNode.appendChild(counter);
        
        function updateCounter() {
            const length = description.value.length;
            counter.textContent = `${length} characters`;
            counter.className = length > 1000 ? 'form-text text-end text-warning' : 'form-text text-end';
        }
        
        description.addEventListener('input', updateCounter);
        updateCounter();
    </script>
</body>
</html>