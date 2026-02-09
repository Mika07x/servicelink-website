<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
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
                
                if ($profile_picture && file_exists('../uploads/profiles/' . $profile_picture)): ?>
                    <img src="../uploads/profiles/<?php echo htmlspecialchars($profile_picture); ?>" 
                         alt="Profile" class="rounded-circle border border-2 border-white" 
                         style="width: 60px; height: 60px; object-fit: cover;">
                <?php else: ?>
                    <div class="bg-white bg-opacity-20 rounded-circle d-inline-flex align-items-center justify-content-center border border-2 border-white" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-user-tie fa-2x text-white"></i>
                    </div>
                <?php endif; ?>
            </div>
            <h6 class="text-white mb-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h6>
            <small class="text-white-50">Staff</small>
        </div>

        <!-- Navigation Menu -->
        <ul class="nav flex-column sidebar-nav">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>

            <!-- All Tickets -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'tickets.php' && !isset($_GET['status']) && !isset($_GET['assigned'])) ? 'active' : ''; ?>" href="tickets.php">
                    <i class="fas fa-ticket-alt me-2"></i>
                    All Tickets
                </a>
            </li>

            <!-- Assigned to Me -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'tickets.php' && isset($_GET['assigned']) && $_GET['assigned'] == 'me') ? 'active' : ''; ?>" href="tickets.php?assigned=me">
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

            <!-- New Tickets -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'tickets.php' && isset($_GET['status']) && $_GET['status'] == 'new') ? 'active' : ''; ?>" href="tickets.php?status=new">
                    <i class="fas fa-plus-circle me-2"></i>
                    New Tickets
                    <?php
                    // Get new tickets count for department
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE department_id = ? AND status = 'new'");
                        $stmt->execute([$_SESSION['department_id']]);
                        $count = $stmt->fetch()['count'];
                        if ($count > 0) echo '<span class="badge bg-info ms-2">' . $count . '</span>';
                    } catch (PDOException $e) {}
                    ?>
                </a>
            </li>

            <!-- High Priority -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'tickets.php' && isset($_GET['priority']) && (strpos($_GET['priority'], 'high') !== false || strpos($_GET['priority'], 'emergency') !== false)) ? 'active' : ''; ?>" href="tickets.php?priority=high,emergency">
                    <i class="fas fa-fire me-2"></i>
                    High Priority
                    <?php
                    // Get high priority tickets count for department
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE department_id = ? AND priority IN ('high', 'emergency') AND status NOT IN ('closed', 'resolved')");
                        $stmt->execute([$_SESSION['department_id']]);
                        $count = $stmt->fetch()['count'];
                        if ($count > 0) echo '<span class="badge bg-danger ms-2">' . $count . '</span>';
                    } catch (PDOException $e) {}
                    ?>
                </a>
            </li>

            <!-- Divider -->
            <hr class="my-3 text-white-50">

            <!-- Chat Support -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'chat-support.php') ? 'active' : ''; ?>" href="chat-support.php">
                    <i class="fas fa-comments me-2"></i>
                    Chat Support
                </a>
            </li>

            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Reports
                </a>
            </li>

            <!-- Divider -->
            <hr class="my-3 text-white-50">

            <!-- Notifications -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>" href="notifications.php">
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
                <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user me-2"></i>
                    Profile
                </a>
            </li>

            <!-- Help -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'help.php') ? 'active' : ''; ?>" href="help.php">
                    <i class="fas fa-question-circle me-2"></i>
                    Help
                </a>
            </li>
        </ul>
    </div>
</nav>