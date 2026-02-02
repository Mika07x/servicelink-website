<?php
require_once 'config/session.php'; // Include session config FIRST
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Get departments for dropdown
$departments = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to load departments.';
}

// Get campuses for dropdown
$campuses = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM campuses WHERE is_active = 1 ORDER BY name");
    $campuses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to load campuses.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_number = trim($_POST['student_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $department_id = $_POST['department_id'];
    $campus_id = $_POST['campus_id'];
    $year_level = $_POST['year_level'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($student_number) || empty($first_name) || empty($last_name) || 
        empty($email) || empty($phone_number) || empty($department_id) || 
        empty($campus_id) || empty($year_level) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if email or student number already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR user_number = ?");
            $stmt->execute([$email, $student_number]);
            
            if ($stmt->fetch()) {
                $error = 'Email or student number already exists.';
            } else {
                // Create new user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (user_number, first_name, last_name, email, phone_number, password_hash, department_id, campus_id, year_level, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'user')");
                
                if ($stmt->execute([$student_number, $first_name, $last_name, $email, $phone_number, $password_hash, $department_id, $campus_id, $year_level])) {
                    $success = 'Registration successful! You can now log in.';
                    
                    // Clear form data
                    $_POST = [];
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card auth-card">
                    <div class="auth-header">
                        <h3 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>
                            Create Account
                        </h3>
                        <p class="mb-0 opacity-75">Join the ServiceLink community</p>
                    </div>
                    
                    <div class="auth-body">
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
                            </div>
                        <?php endif; ?>
                        
                        <!-- Google Sign-up Button -->
                        <div class="mb-4">
                            <a href="auth/google-login.php" class="btn btn-google">
                                <i class="fab fa-google me-2"></i>
                                Sign up with Google
                            </a>
                        </div>
                        
                        <div class="text-center mb-4">
                            <span class="text-muted">or create account with email</span>
                        </div>
                        
                        <form method="POST" action="" id="registerForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="student_number" class="form-label">Student Number *</label>
                                <input type="text" class="form-control" id="student_number" name="student_number" 
                                       value="<?php echo htmlspecialchars($_POST['student_number'] ?? ''); ?>" 
                                       placeholder="e.g., 2024-12345" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                       value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>" 
                                       placeholder="e.g., +63 912 345 6789" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="department_id" class="form-label">Department *</label>
                                    <select class="form-select" id="department_id" name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>" 
                                                    <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="campus_id" class="form-label">Campus *</label>
                                    <select class="form-select" id="campus_id" name="campus_id" required>
                                        <option value="">Select Campus</option>
                                        <?php foreach ($campuses as $campus): ?>
                                            <option value="<?php echo $campus['id']; ?>" 
                                                    <?php echo (($_POST['campus_id'] ?? '') == $campus['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($campus['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="year_level" class="form-label">Year Level *</label>
                                    <select class="form-select" id="year_level" name="year_level" required>
                                        <option value="">Select Year Level</option>
                                        <option value="1st Year" <?php echo (($_POST['year_level'] ?? '') == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2nd Year" <?php echo (($_POST['year_level'] ?? '') == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3rd Year" <?php echo (($_POST['year_level'] ?? '') == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4th Year" <?php echo (($_POST['year_level'] ?? '') == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="text-success">Terms of Service</a> and 
                                    <a href="#" class="text-success">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100 mb-3">
                                <i class="fas fa-user-plus me-2"></i>
                                Create Account
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">Already have an account?</p>
                            <a href="login.php" class="btn btn-outline-success w-100 mt-2">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Sign In Here
                            </a>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="index.php" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>
                                Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const password = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Auto-focus on first name field
        document.getElementById('first_name').focus();
    </script>
</body>
</html>