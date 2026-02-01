<?php
// Common functions for ServiceLink

/**
 * Get user dashboard statistics
 */
function getUserDashboardStats($pdo, $user_id, $user_role, $department_id) {
    $stats = [];
    
    try {
        if ($user_role == 'user') {
            // User statistics
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_tickets,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets
                FROM tickets 
                WHERE requester_id = ?
            ");
            $stmt->execute([$user_id]);
            $stats = $stmt->fetch();
        } else {
            // Staff/Admin statistics
            $where_clause = "";
            $params = [];
            
            if ($user_role == 'department_admin' || $user_role == 'staff') {
                $where_clause = "WHERE department_id = ?";
                $params[] = $department_id;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_tickets,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tickets,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
                    SUM(CASE WHEN priority IN ('high', 'emergency') THEN 1 ELSE 0 END) as high_priority_tickets
                FROM tickets 
                $where_clause
            ");
            $stmt->execute($params);
            $stats = $stmt->fetch();
            
            // Get assigned tickets for staff
            if ($user_role == 'staff') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as assigned_tickets FROM tickets WHERE assigned_to = ?");
                $stmt->execute([$user_id]);
                $assigned = $stmt->fetch();
                $stats['assigned_tickets'] = $assigned['assigned_tickets'];
            }
        }
    } catch (PDOException $e) {
        // Return default stats on error
        $stats = [
            'total_tickets' => 0,
            'open_tickets' => 0,
            'in_progress_tickets' => 0,
            'resolved_tickets' => 0,
            'closed_tickets' => 0,
            'high_priority_tickets' => 0,
            'assigned_tickets' => 0
        ];
    }
    
    return $stats;
}

/**
 * Get recent tickets based on user role
 */
function getRecentTickets($pdo, $user_id, $user_role, $department_id, $limit = 10) {
    try {
        $where_clause = "";
        $params = [];
        
        if ($user_role == 'user') {
            $where_clause = "WHERE t.requester_id = ?";
            $params[] = $user_id;
        } elseif ($user_role == 'department_admin' || $user_role == 'staff') {
            $where_clause = "WHERE t.department_id = ?";
            $params[] = $department_id;
        }
        
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as requester_name,
                   d.name as department_name
            FROM tickets t
            LEFT JOIN users u ON t.requester_id = u.id
            LEFT JOIN departments d ON t.department_id = d.id
            $where_clause
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        
        $params[] = $limit;
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get unread notifications for user
 */
function getUnreadNotifications($pdo, $user_id, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get priority color for badges
 */
function getPriorityColor($priority) {
    switch ($priority) {
        case 'low':
            return 'success';
        case 'medium':
            return 'warning';
        case 'high':
            return 'danger';
        case 'emergency':
            return 'dark';
        default:
            return 'secondary';
    }
}

/**
 * Get status color for badges
 */
function getStatusColor($status) {
    switch ($status) {
        case 'open':
            return 'primary';
        case 'in_progress':
            return 'warning';
        case 'resolved':
            return 'success';
        case 'closed':
            return 'secondary';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Time ago function
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hr ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

/**
 * Generate unique ticket number
 */
function generateTicketNumber($pdo) {
    do {
        $ticket_number = 'TK' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT id FROM tickets WHERE ticket_number = ?");
        $stmt->execute([$ticket_number]);
    } while ($stmt->fetch());
    
    return $ticket_number;
}

/**
 * Send notification to user
 */
function sendNotification($pdo, $user_id, $ticket_id, $title, $message, $type = 'system') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, ticket_id, title, message, type) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $ticket_id, $title, $message, $type]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Upload file and return file info
 */
function uploadFile($file, $upload_dir = 'uploads/') {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid parameters.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException('Exceeded filesize limit.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        throw new RuntimeException('Invalid file format.');
    }

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = sprintf('%s_%s.%s',
        uniqid(),
        bin2hex(random_bytes(8)),
        $extension
    );

    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return [
        'filename' => $filename,
        'original_filename' => $file['name'],
        'file_path' => $filepath,
        'file_size' => $file['size'],
        'mime_type' => $mime_type
    ];
}

/**
 * Check if user has permission
 */
function hasPermission($user_role, $required_role) {
    $role_hierarchy = [
        'user' => 1,
        'staff' => 2,
        'department_admin' => 3,
        'admin' => 4
    ];
    
    return $role_hierarchy[$user_role] >= $role_hierarchy[$required_role];
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random password
 */
function generateRandomPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

/**
 * Log activity
 */
function logActivity($pdo, $user_id, $action, $details = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Get user's full name
 */
function getUserFullName($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? $result['full_name'] : 'Unknown User';
    } catch (PDOException $e) {
        return 'Unknown User';
    }
}

/**
 * Check if user can access ticket
 */
function canAccessTicket($pdo, $user_id, $user_role, $department_id, $ticket_id) {
    try {
        $stmt = $pdo->prepare("SELECT requester_id, assigned_to, department_id FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) return false;
        
        // Admin can access all tickets
        if ($user_role == 'admin') return true;
        
        // Users can access their own tickets
        if ($user_role == 'user' && $ticket['requester_id'] == $user_id) return true;
        
        // Staff can access tickets in their department or assigned to them
        if (($user_role == 'staff' || $user_role == 'department_admin') && 
            ($ticket['department_id'] == $department_id || $ticket['assigned_to'] == $user_id)) {
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get ticket priority options
 */
function getPriorityOptions() {
    return [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'emergency' => 'Emergency'
    ];
}

/**
 * Get ticket status options
 */
function getStatusOptions() {
    return [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled'
    ];
}

/**
 * Send email notification
 */
function sendEmailNotification($to, $subject, $message, $headers = null) {
    if (!$headers) {
        $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Create breadcrumb navigation
 */
function createBreadcrumb($items) {
    $breadcrumb = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    $count = count($items);
    foreach ($items as $index => $item) {
        if ($index == $count - 1) {
            $breadcrumb .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($item['title']) . '</li>';
        } else {
            $breadcrumb .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['title']) . '</a></li>';
        }
    }
    
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}
/**
 * Get role color for badges
 */
function getRoleColor($role) {
    switch ($role) {
        case 'admin':
            return 'danger';
        case 'department_admin':
            return 'warning';
        case 'staff':
            return 'info';
        case 'user':
            return 'primary';
        default:
            return 'secondary';
    }
}
?>

