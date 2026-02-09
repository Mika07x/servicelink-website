<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Determine the base path for links
$base_path = '';
if ($current_dir == 'admin' || $current_dir == 'department' || $current_dir == 'staff' || $current_dir == 'student') {
    $base_path = '../';
} elseif ($current_dir == 'tickets' || $current_dir == 'reports') {
    $base_path = '../';
}
?>

<nav id="sidebarMenu" class="dashboard-sidebar">
    <div class="pt-3">
        <!-- User Profile Section -->
        <div class="text-center mb-4 p-3">
            <div class="mb-3">
                <?php 
                // Get user profile picture from database
                $profile_picture = null;
                try {
                    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    $profile_picture = $user['profile_picture'] ?? null;
                } catch (PDOException $e) {
                    // Fallback to session if database fails
                    $profile_picture = $_SESSION['profile_picture'] ?? null;
                }
                
                if ($profile_picture): ?>
                    <img src="<?php echo ($current_dir == 'admin' || $current_dir == 'department' || $current_dir == 'staff' || $current_dir == 'student') ? '../' . htmlspecialchars($profile_picture) : htmlspecialchars($profile_picture); ?>" 
                         alt="Profile" class="rounded-circle border border-2 border-white" 
                         style="width: 60px; height: 60px; object-fit: cover;">
                <?php else: ?>
                    <div class="bg-white bg-opacity-20 rounded-circle d-inline-flex align-items-center justify-content-center border border-2 border-white" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-user fa-2x text-white"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h6 class="text-white mb-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h6>
            <small class="text-white-50">
                <?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_role'])); ?>
            </small>
        </div>

        <!-- Navigation Menu -->
        <ul class="nav flex-column sidebar-nav">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" 
                   href="<?php 
                   switch ($_SESSION['user_role']) {
                       case 'admin':
                           echo ($current_dir == 'admin') ? 'dashboard.php' : 'admin/dashboard.php';
                           break;
                       case 'department_admin':
                           echo ($current_dir == 'department') ? 'dashboard.php' : 'department/dashboard.php';
                           break;
                       case 'staff':
                           echo ($current_dir == 'staff') ? 'dashboard.php' : 'staff/dashboard.php';
                           break;
                       case 'user':
                       default:
                           echo ($current_dir == 'student') ? 'dashboard.php' : 'student/dashboard.php';
                           break;
                   }
                   ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>

            <!-- Tickets Section -->
            <li class="nav-item">
                <a class="nav-link <?php 
                    // Check for main tickets page without any filters
                    $is_main_tickets = false;
                    if ($_SESSION['user_role'] == 'user') {
                        $is_main_tickets = ($current_page == 'index.php' && $current_dir == 'tickets' && !isset($_GET['status']) && !isset($_GET['priority']) && !isset($_GET['assigned']));
                    } else {
                        $is_main_tickets = ($current_page == 'tickets.php' && !isset($_GET['status']) && !isset($_GET['priority']) && !isset($_GET['assigned']));
                    }
                    echo $is_main_tickets ? 'active' : ''; 
                ?>" 
                   href="<?php 
                   switch ($_SESSION['user_role']) {
                       case 'admin':
                           echo ($current_dir == 'admin') ? 'tickets.php' : 'admin/tickets.php';
                           break;
                       case 'department_admin':
                           echo ($current_dir == 'department') ? 'tickets.php' : 'department/tickets.php';
                           break;
                       case 'staff':
                           echo ($current_dir == 'staff') ? 'tickets.php' : 'staff/tickets.php';
                           break;
                       case 'user':
                       default:
                           echo ($current_dir == 'student') ? 'tickets.php' : 'student/tickets.php';
                           break;
                   }
                   ?>">
                    <i class="fas fa-ticket-alt me-2"></i>
                    <?php echo ($_SESSION['user_role'] == 'user') ? 'My Tickets' : 'Tickets'; ?>
                </a>
            </li>

            <?php if ($_SESSION['user_role'] == 'user'): ?>
                <!-- User Menu Items -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'create.php') ? 'active' : ''; ?>" 
                       href="<?php 
                       switch ($_SESSION['user_role']) {
                           case 'user':
                           default:
                               echo ($current_dir == 'student') ? 'create.php' : 'student/create.php';
                               break;
                       }
                       ?>">
                        <i class="fas fa-plus me-2"></i>
                        New Request
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'tickets.php' && isset($_GET['status']) && $_GET['status'] == 'new') ? 'active' : ''; ?>" 
                       href="<?php 
                       switch ($_SESSION['user_role']) {
                           case 'user':
                           default:
                               echo ($current_dir == 'student') ? 'tickets.php?status=new' : 'student/tickets.php?status=new';
                               break;
                       }
                       ?>">
                        <i class="fas fa-plus-circle me-2"></i>
                        New Tickets
                        <?php
                        // Get new tickets count
                        try {
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE requester_id = ? AND status = 'new'");
                            $stmt->execute([$_SESSION['user_id']]);
                            $count = $stmt->fetch()['count'];
                            if ($count > 0) echo '<span class="badge bg-info ms-2">' . $count . '</span>';
                        } catch (PDOException $e) {}
                        ?>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'tickets.php' && isset($_GET['status']) && $_GET['status'] == 'in_progress') ? 'active' : ''; ?>" 
                       href="<?php 
                       switch ($_SESSION['user_role']) {
                           case 'user':
                           default:
                               echo ($current_dir == 'student') ? 'tickets.php?status=in_progress' : 'student/tickets.php?status=in_progress';
                               break;
                       }
                       ?>">
                        <i class="fas fa-cog me-2"></i>
                        In Progress
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'tickets.php' && isset($_GET['status']) && $_GET['status'] == 'resolved') ? 'active' : ''; ?>" 
                       href="<?php 
                       switch ($_SESSION['user_role']) {
                           case 'user':
                           default:
                               echo ($current_dir == 'student') ? 'tickets.php?status=resolved' : 'student/tickets.php?status=resolved';
                               break;
                       }
                       ?>">
                        <i class="fas fa-check-circle me-2"></i>
                        Resolved
                    </a>
                </li>

            <?php else: ?>
                <!-- Staff/Admin Menu Items -->
                <li class="nav-item">
                    <a class="nav-link <?php 
                        // Check for pending tickets (status=pending)
                        $is_pending = false;
                        if ($_SESSION['user_role'] != 'user') {
                            $is_pending = ($current_page == 'tickets.php' && isset($_GET['status']) && $_GET['status'] == 'pending');
                        }
                        echo $is_pending ? 'active' : ''; 
                    ?>" 
                       href="<?php 
                       switch ($_SESSION['user_role']) {
                           case 'admin':
                               echo ($current_dir == 'admin') ? 'tickets.php?status=pending' : 'admin/tickets.php?status=pending';
                               break;
                           case 'department_admin':
                               echo ($current_dir == 'department') ? 'tickets.php?status=pending' : 'department/tickets.php?status=pending';
                               break;
                           case 'staff':
                               echo ($current_dir == 'staff') ? 'tickets.php?status=pending' : 'staff/tickets.php?status=pending';
                               break;
                       }
                       ?>">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Pending Tickets
                        <?php
                        // Get pending tickets count for department/all
                        try {
                            if ($_SESSION['user_role'] == 'admin') {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE status = 'pending'");
                                $stmt->execute();
                            } else {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE department_id = ? AND status = 'pending'");
                                $stmt->execute([$_SESSION['department_id']]);
                            }
                            $count = $stmt->fetch()['count'];
                            if ($count > 0) echo '<span class="badge bg-warning ms-2">' . $count . '</span>';
                        } catch (PDOException $e) {}
                        ?>
                    </a>
                </li>
                
                <?php if ($_SESSION['user_role'] == 'staff'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php 
                        // Check for assigned to me tickets
                        $is_assigned = false;
                        if ($_SESSION['user_role'] == 'staff') {
                            $is_assigned = ($current_page == 'tickets.php' && isset($_GET['assigned']) && $_GET['assigned'] == 'me');
                        }
                        echo $is_assigned ? 'active' : ''; 
                    ?>" 
                       href="<?php echo ($current_dir == 'staff') ? 'tickets.php?assigned=me' : 'staff/tickets.php?assigned=me'; ?>">
                        <i class="fas fa-user-check me-2"></i>
                        Assigned to Me
                        <?php
                        // Get assigned tickets count
                        try {
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE assigned_to = ? AND status IN ('assigned', 'in_progress')");
                            $stmt->execute([$_SESSION['user_id']]);
                            $count = $stmt->fetch()['count'];
                            if ($count > 0) echo '<span class="badge bg-info ms-2">' . $count . '</span>';
                        } catch (PDOException $e) {}
                        ?>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link <?php 
                        // Check for high priority tickets
                        $is_high_priority = false;
                        if ($_SESSION['user_role'] != 'user') {
                            $is_high_priority = ($current_page == 'tickets.php' && isset($_GET['priority']) && 
                                               (strpos($_GET['priority'], 'high') !== false || strpos($_GET['priority'], 'emergency') !== false));
                        }
                        echo $is_high_priority ? 'active' : ''; 
                    ?>" 
                       href="<?php 
                       switch ($_SESSION['user_role']) {
                           case 'admin':
                               echo ($current_dir == 'admin') ? 'tickets.php?priority=high,emergency' : 'admin/tickets.php?priority=high,emergency';
                               break;
                           case 'department_admin':
                               echo ($current_dir == 'department') ? 'tickets.php?priority=high,emergency' : 'department/tickets.php?priority=high,emergency';
                               break;
                           case 'staff':
                               echo ($current_dir == 'staff') ? 'tickets.php?priority=high,emergency' : 'staff/tickets.php?priority=high,emergency';
                               break;
                       }
                       ?>">
                        <i class="fas fa-fire me-2"></i>
                        High Priority
                        <?php
                        // Get high priority tickets count
                        try {
                            if ($_SESSION['user_role'] == 'admin') {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE priority IN ('high', 'emergency') AND status NOT IN ('closed', 'resolved')");
                                $stmt->execute();
                            } else {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE department_id = ? AND priority IN ('high', 'emergency') AND status NOT IN ('closed', 'resolved')");
                                $stmt->execute([$_SESSION['department_id']]);
                            }
                            $count = $stmt->fetch()['count'];
                            if ($count > 0) echo '<span class="badge bg-danger ms-2">' . $count . '</span>';
                        } catch (PDOException $e) {}
                        ?>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Divider -->
            <hr class="my-3 text-white-50">

            <!-- Chat Support -->
            <?php if ($_SESSION['user_role'] != 'user'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'chat-support.php') ? 'active' : ''; ?>" 
                   href="<?php 
                   switch ($_SESSION['user_role']) {
                       case 'admin':
                           echo ($current_dir == 'admin') ? 'chat-support.php' : 'admin/chat-support.php';
                           break;
                       case 'department_admin':
                           echo ($current_dir == 'department') ? 'chat-support.php' : 'department/chat-support.php';
                           break;
                       case 'staff':
                           echo ($current_dir == 'staff') ? 'chat-support.php' : 'staff/chat-support.php';
                           break;
                   }
                   ?>">
                    <i class="fas fa-comments me-2"></i>
                    Chat Support
                </a>
            </li>
            <?php endif; ?>

            <!-- Reports Section -->
            <?php if ($_SESSION['user_role'] != 'user'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_dir == 'reports' || $current_page == 'reports.php') ? 'active' : ''; ?>" 
                   href="<?php 
                   switch ($_SESSION['user_role']) {
                       case 'admin':
                           echo ($current_dir == 'admin') ? 'reports.php' : 'admin/reports.php';
                           break;
                       case 'department_admin':
                           echo ($current_dir == 'department') ? 'reports.php' : 'department/reports.php';
                           break;
                       case 'staff':
                           echo ($current_dir == 'staff') ? 'reports.php' : 'staff/reports.php';
                           break;
                       default:
                           echo ($current_dir == 'reports') ? 'index.php' : 'reports/index.php';
                           break;
                   }
                   ?>">
                    <i class="fas fa-chart-bar me-2"></i>
                    Reports
                </a>
            </li>
            <?php endif; ?>

            <!-- Admin Section -->
            <?php if ($_SESSION['user_role'] == 'admin'): ?>

            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" 
                   href="<?php echo ($current_dir == 'admin') ? 'users.php' : 'admin/users.php'; ?>">
                    <i class="fas fa-users me-2"></i>
                    Manage Users
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'campuses.php') ? 'active' : ''; ?>" 
                   href="<?php echo ($current_dir == 'admin') ? 'campuses.php' : 'admin/campuses.php'; ?>">
                    <i class="fas fa-university me-2"></i>
                    Campuses
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'departments.php') ? 'active' : ''; ?>" 
                   href="<?php echo ($current_dir == 'admin') ? 'departments.php' : 'admin/departments.php'; ?>">
                    <i class="fas fa-building me-2"></i>
                    Departments
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>" 
                   href="<?php echo ($current_dir == 'admin') ? 'categories.php' : 'admin/categories.php'; ?>">
                    <i class="fas fa-tags me-2"></i>
                    Categories
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" 
                   href="<?php echo ($current_dir == 'admin') ? 'settings.php' : 'admin/settings.php'; ?>">
                    <i class="fas fa-cog me-2"></i>
                    System Settings
                </a>
            </li>
            <?php endif; ?>

            <!-- Department Admin Section -->
            <?php if ($_SESSION['user_role'] == 'department_admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo ($current_dir == 'department') ? 'staff.php' : 'department/staff.php'; ?>">
                    <i class="fas fa-user-tie me-2"></i>
                    Manage Staff
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="<?php echo ($current_dir == 'department') ? 'categories.php' : 'department/categories.php'; ?>">
                    <i class="fas fa-tags me-2"></i>
                    Service Categories
                </a>
            </li>
            <?php endif; ?>

            <!-- Divider -->
            <hr class="my-3 text-white-50">

            <!-- Notifications -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>" 
                   href="<?php 
                   switch ($_SESSION['user_role']) {
                       case 'admin':
                           echo ($current_dir == 'admin') ? 'notifications.php' : 'admin/notifications.php';
                           break;
                       case 'department_admin':
                           echo ($current_dir == 'department') ? 'notifications.php' : 'department/notifications.php';
                           break;
                       case 'staff':
                           echo ($current_dir == 'staff') ? 'notifications.php' : 'staff/notifications.php';
                           break;
                       case 'user':
                       default:
                           echo ($current_dir == 'student') ? 'notifications.php' : 'student/notifications.php';
                           break;
                   }
                   ?>">
                    <i class="fas fa-bell me-2"></i>
                    Notifications
                    <?php
                    // Get unread notifications count
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                        $stmt->execute([$_SESSION['user_id']]);
                        $count = $stmt->fetch()['count'];
                        if ($count > 0) echo '<span class="badge bg-danger ms-2">' . $count . '</span>';
                    } catch (PDOException $e) {}
                    ?>
                </a>
            </li>

            <!-- Profile -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" 
                   href="<?php 
                   switch ($_SESSION['user_role']) {
                       case 'admin':
                           echo ($current_dir == 'admin') ? 'profile.php' : 'admin/profile.php';
                           break;
                       case 'department_admin':
                           echo ($current_dir == 'department') ? 'profile.php' : 'department/profile.php';
                           break;
                       case 'staff':
                           echo ($current_dir == 'staff') ? 'profile.php' : 'staff/profile.php';
                           break;
                       case 'user':
                       default:
                           echo ($current_dir == 'student') ? 'profile.php' : 'student/profile.php';
                           break;
                   }
                   ?>">
                    <i class="fas fa-user me-2"></i>
                    Profile Settings
                </a>
            </li>

            <!-- Help -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'help.php') ? 'active' : ''; ?>" 
                   href="<?php 
                    switch ($_SESSION['user_role']) {
                        case 'admin':
                            echo ($current_dir == 'admin') ? 'help.php' : 'admin/help.php';
                            break;
                        case 'department_admin':
                            echo ($current_dir == 'department') ? 'help.php' : 'department/help.php';
                            break;
                        case 'staff':
                            echo ($current_dir == 'staff') ? 'help.php' : 'staff/help.php';
                            break;
                        case 'user':
                        default:
                            echo ($current_dir == 'admin' || $current_dir == 'department' || $current_dir == 'staff' || $current_dir == 'student') ? '../help.php' : 'help.php';
                            break;
                    }
                ?>">
                    <i class="fas fa-question-circle me-2"></i>
                    Help & Support
                </a>
            </li>

            <!-- Divider -->
            <hr class="my-3 text-white-50">

            <!-- Logout -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>" 
                   href="<?php echo ($current_dir == 'admin' || $current_dir == 'department' || $current_dir == 'staff' || $current_dir == 'student') ? '../logout.php' : 'logout.php'; ?>" 
                   onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
        </ul>

        <!-- System Status (for admins) -->
        <?php if ($_SESSION['user_role'] == 'admin'): ?>
        <div class="mt-4 p-3 bg-white bg-opacity-10 rounded mx-3">
            <h6 class="text-white mb-2">
                <i class="fas fa-server me-1"></i>
                System Status
            </h6>
            <div class="small text-white-50">
                <?php
                try {
                    // Get system stats
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tickets WHERE DATE(created_at) = CURDATE()");
                    $today_tickets = $stmt->fetch()['total'];
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
                    $active_users = $stmt->fetch()['total'];
                    
                    echo "Today: {$today_tickets} tickets<br>";
                    echo "Active users: {$active_users}";
                } catch (PDOException $e) {
                    echo "Status unavailable";
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</nav>

<!-- Mobile Menu Toggle -->
<button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" 
        style="position: fixed; top: 10px; left: 10px; z-index: 1050;">
    <span class="navbar-toggler-icon"></span>
</button>

<style>
/* Force mobile sidebar behavior */
@media (max-width: 768px) {
    #sidebarMenu {
        position: fixed !important;
        top: 60px !important;
        left: 0 !important;
        width: 280px !important;
        height: calc(100vh - 60px) !important;
        transform: translateX(-100%) !important;
        transition: transform 0.3s ease !important;
        z-index: 1040 !important;
        overflow-y: auto !important;
        display: block !important;
    }
    
    #sidebarMenu.show {
        transform: translateX(0) !important;
    }
    
    /* Ensure sidebar doesn't interfere with content on mobile */
    .dashboard-content {
        margin-left: 0 !important;
        width: 100% !important;
    }
}

/* Desktop sidebar styles */
@media (min-width: 769px) {
    #sidebarMenu {
        position: fixed !important;
        top: 70px !important;
        left: 0 !important;
        width: 250px !important;
        height: calc(100vh - 70px) !important;
        transform: translateX(0) !important;
        z-index: 1040 !important;
        overflow-y: auto !important;
        display: block !important;
    }
    
    .dashboard-content {
        margin-left: 250px !important;
        padding-top: 90px !important;
        padding-left: 30px !important;
        padding-right: 30px !important;
        padding-bottom: 30px !important;
    }
}
</style>

<script>
// Mobile sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebarMenu');
    
    if (sidebar) {
        // Restore sidebar scroll position (with delay to ensure page is fully loaded)
        setTimeout(function() {
            const savedScrollPosition = localStorage.getItem('sidebarScrollPosition');
            if (savedScrollPosition && !isNaN(savedScrollPosition)) {
                // Restore scroll position smoothly without animation
                sidebar.style.scrollBehavior = 'auto';
                sidebar.scrollTop = parseInt(savedScrollPosition);
                // Reset scroll behavior after restoration
                setTimeout(() => {
                    sidebar.style.scrollBehavior = 'smooth';
                }, 50);
            }
        }, 100);
        
        // Save sidebar scroll position when scrolling (throttled)
        let scrollTimeout;
        sidebar.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
            }, 150);
        });
        
        // Save scroll position when clicking nav links (NO ANIMATIONS)
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Only save position for navigation links, not form submissions
                if (!this.closest('form')) {
                    localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
                }
                
                // Close sidebar on mobile after saving position
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('show');
                    const overlay = document.querySelector('.sidebar-overlay');
                    if (overlay) overlay.classList.remove('show');
                }
            });
        });
        
        // Ensure sidebar starts hidden on mobile
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('show');
        }
        
        // Create overlay if it doesn't exist
        let overlay = document.querySelector('.sidebar-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }
        
        // Toggle function for mobile menu
        window.toggleSidebar = function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        };
        
        // Close sidebar when clicking on overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
        
        // Save scroll position before page unload (only for navigation)
        window.addEventListener('beforeunload', function(e) {
            // Don't save position if it's a form submission
            if (!e.target.activeElement || e.target.activeElement.type !== 'submit') {
                localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
            }
        });
    }
    
    // Ensure main page can scroll properly
    document.body.style.overflow = 'auto';
    document.documentElement.style.overflow = 'auto';
});
</script>