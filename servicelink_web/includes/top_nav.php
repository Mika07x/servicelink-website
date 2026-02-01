<?php
// Top Navigation Bar Template
// Usage: include this file in any page that needs the top navigation

// Determine base path for assets and links
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$base_path = '';
if ($current_dir == 'admin' || $current_dir == 'department' || $current_dir == 'staff' || $current_dir == 'student') {
    $base_path = '../';
}

// Get user profile picture directly from database (simplified approach)
$user_profile_picture = null;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['profile_picture'])) {
            $user_profile_picture = $result['profile_picture'];
        }
    } catch (Exception $e) {
        // Silently fail and use default icon
        $user_profile_picture = null;
    }
}
?>

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo $base_path; ?>dashboard.php">
            <img src="<?php echo $base_path; ?>assets/images/logo.png" alt="ServiceLink" height="40" class="me-2">
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
                    <?php if (!empty($user_profile_picture) && file_exists($base_path . $user_profile_picture)): ?>
                        <img src="<?php echo $base_path . htmlspecialchars($user_profile_picture); ?>" 
                             alt="Profile" class="rounded-circle me-2" 
                             style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #28a745;">
                    <?php else: ?>
                        <div class="rounded-circle me-2 d-flex align-items-center justify-content-center" 
                             style="width: 40px; height: 40px; background-color: #28a745;">
                            <i class="fas fa-user text-white"></i>
                        </div>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                    <li>
                        <a class="dropdown-item" href="<?php 
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
                            <i class="fas fa-user me-2 text-success"></i>
                            Profile
                        </a>
                    </li>
                    <?php if ($_SESSION['user_role'] == 'admin'): ?>
                    <li>
                        <a class="dropdown-item" href="<?php echo ($current_dir == 'admin') ? 'settings.php' : 'admin/settings.php'; ?>">
                            <i class="fas fa-cog me-2 text-success"></i>
                            Settings
                        </a>
                    </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?php echo $base_path; ?>logout.php">
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