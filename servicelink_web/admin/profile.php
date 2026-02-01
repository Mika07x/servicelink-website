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
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone_number'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Handle profile picture upload
    $profile_picture_path = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profiles/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['profile_picture']['name']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $file_extension = strtolower($file_info['extension']);
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Check file size (max 5MB)
            if ($_FILES['profile_picture']['size'] <= 5 * 1024 * 1024) {
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    $profile_picture_path = 'uploads/profiles/' . $new_filename;
                    
                    // Delete old profile picture if exists
                    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $old_picture = $stmt->fetch()['profile_picture'];
                    if ($old_picture && file_exists('../' . $old_picture)) {
                        unlink('../' . $old_picture);
                    }
                } else {
                    $error = "Failed to upload profile picture.";
                }
            } else {
                $error = "Profile picture must be less than 5MB.";
            }
        } else {
            $error = "Profile picture must be a JPG, JPEG, PNG, or GIF file.";
        }
    }
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            // Check if email is already taken by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = "Email address is already taken by another user.";
            } else {
                // Prepare update query - use phone_number instead of phone
                $update_fields = "first_name = ?, last_name = ?, email = ?, phone_number = ?, updated_at = NOW()";
                $update_params = [$first_name, $last_name, $email, $phone];
                
                // Add profile picture to update if uploaded
                if ($profile_picture_path) {
                    $update_fields .= ", profile_picture = ?";
                    $update_params[] = $profile_picture_path;
                }
                
                $update_params[] = $user_id;
                
                // Update basic profile info
                $stmt = $pdo->prepare("UPDATE users SET {$update_fields} WHERE id = ?");
                $stmt->execute($update_params);
                
                // Update session data
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_email'] = $email;
                if ($profile_picture_path) {
                    $_SESSION['profile_picture'] = $profile_picture_path;
                }
                
                // Handle password change if provided
                if (!empty($current_password) && !empty($new_password)) {
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if (!password_verify($current_password, $user['password_hash'])) {
                        $error = "Current password is incorrect.";
                    } elseif ($new_password !== $confirm_password) {
                        $error = "New passwords do not match.";
                    } elseif (strlen($new_password) < 6) {
                        $error = "New password must be at least 6 characters long.";
                    } else {
                        // Update password
                        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmt->execute([$new_password_hash, $user_id]);
                        
                        if (!$error) {
                            $message = "Profile and password updated successfully!";
                        }
                    }
                } else {
                    if (!$error) {
                        $message = "Profile updated successfully!";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Get current user data
$user_data = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.*, d.name as department_name, c.name as campus_name
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        LEFT JOIN campuses c ON u.campus_id = c.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Error loading profile data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - ServiceLink Admin</title>
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
                    <i class="fas fa-user text-success me-2"></i>
                    Profile Settings
                </h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Picture Upload Section -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-camera me-2"></i>
                                Profile Picture
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <form method="POST" enctype="multipart/form-data" id="profilePictureForm">
                                <!-- Current Profile Picture Display -->
                                <div class="mb-3">
                                    <?php if (isset($user_data['profile_picture']) && $user_data['profile_picture']): ?>
                                        <img src="../<?php echo htmlspecialchars($user_data['profile_picture']); ?>" 
                                             alt="Profile Picture" class="rounded-circle border border-3 border-success" 
                                             style="width: 120px; height: 120px; object-fit: cover;" id="currentProfilePic">
                                    <?php else: ?>
                                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center border border-3 border-success" 
                                             style="width: 120px; height: 120px;" id="defaultProfilePic">
                                            <i class="fas fa-user fa-3x text-success"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- File Upload Input -->
                                <div class="mb-3">
                                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        JPG, PNG, GIF (Max: 5MB)
                                    </div>
                                </div>

                                <!-- Upload Button -->
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-upload me-1"></i>
                                    Upload Picture
                                </button>

                                <!-- Hidden fields to maintain other form data -->
                                <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>">
                                <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>">
                            </form>
                        </div>
                    </div>

                    <!-- Account Summary Card -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Account Summary
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <?php if ($user_data['profile_picture']): ?>
                                    <img src="../<?php echo htmlspecialchars($user_data['profile_picture']); ?>" 
                                         alt="Profile Picture" class="rounded-circle" 
                                         style="width: 60px; height: 60px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" 
                                         style="width: 60px; height: 60px;">
                                        <i class="fas fa-user-shield fa-lg text-success"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <h6><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h6>
                                    <small class="text-muted">System Administrator</small>
                                </div>
                            </div>
                            
                            <div class="small">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">User ID:</span>
                                    <span>#<?php echo $user_data['id']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Status:</span>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Member Since:</span>
                                    <span><?php echo date('M j, Y', strtotime($user_data['created_at'] ?? '')); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Last Updated:</span>
                                    <span><?php echo $user_data['updated_at'] ? date('M j, Y', strtotime($user_data['updated_at'])) : 'Never'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user-edit me-2"></i>
                                Personal Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="profileInfoForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="Administrator" readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Account Status</label>
                                    <span class="badge bg-success ms-2">Active</span>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Member Since</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo date('F j, Y', strtotime($user_data['created_at'] ?? '')); ?>" readonly>
                                </div>

                                <hr class="my-4">

                                <h6 class="mb-3">
                                    <i class="fas fa-lock me-2"></i>
                                    Change Password (Optional)
                                </h6>

                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-1"></i>
                                        Save Changes
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth page transition effect
        document.addEventListener('DOMContentLoaded', function() {
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

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });ElementById('upload_buttons').style.display = 'none';
        }
    </script>
</body>
</html>