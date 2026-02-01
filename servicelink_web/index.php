<?php
require_once 'config/session.php'; // Include session config FIRST
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceLink - University Service Ticketing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="me-2 p-2 rounded-3 bg-success">
                    <i class="fas fa-ticket-alt text-white"></i>
                </div>
                <span class="fw-bold text-success fs-4">ServiceLink</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact Us</a>
                    </li>
                </ul>
                
                <div class="d-flex">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="btn btn-success me-2">Dashboard</a>
                        <a href="logout.php" class="btn btn-outline-success">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-success me-2">Login</a>
                        <a href="register.php" class="btn btn-outline-success">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section bg-gradient-success text-white py-5">
        <div class="container">
            <div class="row align-items-center min-vh-75">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Welcome to ServiceLink</h1>
                    <p class="lead mb-4">Your comprehensive university service ticketing system. Submit requests, track progress, and get the support you need efficiently.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="register.php" class="btn btn-light btn-lg px-4">Get Started</a>
                            <a href="login.php" class="btn btn-outline-light btn-lg px-4">Sign In</a>
                        <?php else: ?>
                            <a href="dashboard.php" class="btn btn-light btn-lg px-4">Go to Dashboard</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-ticket-alt display-1 opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="fw-bold text-success mb-3">Why Choose ServiceLink?</h2>
                    <p class="lead text-muted">Streamlined service management for the entire university community</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-robot text-success fs-2"></i>
                            </div>
                            <h5 class="fw-bold mb-3">AI-Powered Routing</h5>
                            <p class="text-muted">Intelligent ticket routing and priority assignment using OpenAI GPT-4 technology.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-comments text-success fs-2"></i>
                            </div>
                            <h5 class="fw-bold mb-3">Real-time Communication</h5>
                            <p class="text-muted">Chat and comment system for seamless communication between users and staff.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="feature-icon bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-chart-bar text-success fs-2"></i>
                            </div>
                            <h5 class="fw-bold mb-3">Comprehensive Reports</h5>
                            <p class="text-muted">Generate detailed reports and analytics with PDF export functionality.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="fw-bold text-success mb-3">Our Services</h2>
                    <p class="lead text-muted">We handle various university service requests</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="service-item text-center p-4">
                        <i class="fas fa-tools text-success fs-1 mb-3"></i>
                        <h6 class="fw-bold">Technical Support</h6>
                        <p class="text-muted small">IT and technical assistance</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="service-item text-center p-4">
                        <i class="fas fa-building text-success fs-1 mb-3"></i>
                        <h6 class="fw-bold">Facilities</h6>
                        <p class="text-muted small">Maintenance and facility requests</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="service-item text-center p-4">
                        <i class="fas fa-graduation-cap text-success fs-1 mb-3"></i>
                        <h6 class="fw-bold">Academic Support</h6>
                        <p class="text-muted small">Non-grade related academic assistance</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="service-item text-center p-4">
                        <i class="fas fa-users text-success fs-1 mb-3"></i>
                        <h6 class="fw-bold">Student Services</h6>
                        <p class="text-muted small">General student support services</p>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12 text-center">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Service requests related to payments, grades, or student documents are handled by respective offices (Accounting, Registrar, Academic Affairs).
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-success">ServiceLink</h5>
                    <p class="text-muted">University Service Ticketing System</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">&copy; 2024 ServiceLink. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>