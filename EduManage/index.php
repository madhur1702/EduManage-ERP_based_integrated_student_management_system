<?php
require_once('config/config.php');
require_once('includes/db.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduManage - The All-in-One Student Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Custom Styles from the second block, slightly adjusted for consistency */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-gradient: linear-gradient(135deg, #5b6df5 0%, #764ba2 100%); /* Adjusted primary from original CSS */
            --primary: #5b6df5; /* Adjusted primary from original CSS */
            --primary-dark: #4753c1; 
            --primary-light: #7c8ef5;
            --secondary: #764ba2;
            
            /* Custom colors based on the feature image provided */
            --icon-purple: #764ba2;
            --icon-green: #3AB65C;
            --icon-yellow: #F5B300;
            --icon-red: #E74C3C;
            
            --success: var(--icon-green); /* Used for role section borders */
            --warning: var(--icon-yellow); /* Used for role section borders */
            --danger: #ef4444;
            --neutral-50: #f5f6f8; /* Adjusted to background-light */
            --neutral-100: #f3f4f6;
            --neutral-200: #e5e7eb;
            --neutral-300: #d1d5db;
            --neutral-400: #9ca3af;
            --neutral-500: #6b7280;
            --neutral-600: #4b5563;
            --neutral-700: #374151;
            --neutral-800: #1f2937;
            --neutral-900: #111218; /* Adjusted to original text color */
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--neutral-50);
            color: var(--neutral-700);
            line-height: 1.6;
        }

        /* Navbar */
        .navbar {
            background: white;
            border-bottom: 1px solid var(--neutral-200);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(91, 109, 245, 0.1);
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary); /* Solid primary color */
            letter-spacing: -0.5px;
            margin-left: 1rem;
        }

        .navbar-brand i {
            margin-right: 0.5rem;
            color: var(--primary); 
        }

        .nav-link {
            color: var(--neutral-600) !important;
            margin: 0 1.2rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            font-size: 0.95rem;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-gradient);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-link:hover {
            color: var(--primary) !important;
        }

        .btn-login {
            /* Updated styles to match Explore Features button: background primary, text white */
            background: var(--primary);
            color: white;
            font-weight: 700;
            padding: 0.6rem 1.8rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            margin-right: 1rem;
            border: none; /* Removed border */
            font-size: 0.95rem;
            box-shadow: 0 4px 12px rgba(91, 109, 245, 0.25);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(91, 109, 245, 0.35);
            background-color: var(--primary-dark);
            color: white;
        }
        
        /* Removed btn-demo styles */
        
        /* Hero Section */
        .hero {
            background-image: linear-gradient(rgba(0, 0, 0, 0.5) 0%, rgba(0, 0, 0, 0.7) 100%), url("https://lh3.googleusercontent.com/aida-public/AB6AXuB09Z9-z3mLxgiZfiC8nY7c5rlgC0YFJ5oh1x8Af4jhBm4BIobTl_IVrNmbc0po3J_yzHrSZo9bmlT2VgLO6l7YdfnBVTHZ2AmqErtb5ECqQvyjUzPQS1JlWm2Bk5RAreRJkHlLPjJvvM5qvnoNnAN5oVEQA12RXk0SPffHkYxEdwhnghMDuNS4Kl2J-MuI4lIw9zMsLZVAi3jfbcWH5E-dmtepHQ9GisMu-x5IbnrIs7EnxqMx3buKk2Ra3YB2D-OXRLePaa_jdFg");
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
            position: relative;
            overflow: hidden;
            min-height: 550px;
            display: flex;
            align-items: center;
            border-radius: 12px;
            margin: 20px auto;
            max-width: 1320px;
        }

        /* Override Hero styles to match Tailwind design better */
        .hero::before, .hero::after {
            content: none; /* Remove abstract shapes from Bootstrap design */
        }

        .hero-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 1rem;
            line-height: 1.2;
            letter-spacing: -1px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 400;
        }

        .btn-explore {
            background-color: var(--primary);
            color: white;
            padding: 1rem 2.5rem;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 6px;
            border: none;
            transition: all 0.3s ease;
            display: inline-block;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .btn-explore:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            color: white;
        }

        /* Features Section */
        .features {
            padding: 80px 0;
            background: var(--neutral-50);
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--neutral-900);
            margin-bottom: 1rem;
        }

        .section-header p {
            font-size: 1.1rem;
            color: var(--neutral-600);
            max-width: 500px;
            margin: 0 auto;
            font-weight: 400;
        }

        .feature-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            border: 1px solid var(--neutral-200);
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-height: 150px;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: transparent; /* No background for this design */
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 0;
        }
        
        /* Feature Icon Colors based on image */
        .feature-icon.purple { color: var(--icon-purple); }
        .feature-icon.green { color: var(--icon-green); }
        .feature-icon.yellow { color: var(--icon-yellow); }
        .feature-icon.red { color: var(--icon-red); }


        .feature-card h5 {
            color: var(--neutral-900);
            font-weight: 700;
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }

        .feature-card p {
            color: var(--neutral-600);
            line-height: 1.5;
            font-size: 0.9rem;
            margin: 0;
        }
        
        /* Roles Section - RESTORED ORIGINAL STYLING WITH NEW ROLES */
        .roles {
            padding: 80px 0;
            background: white;
        }
        
        .role-card-old {
            background: var(--neutral-50);
            border-radius: 12px;
            padding: 2.5rem;
            color: var(--neutral-900);
            text-align: center;
            transition: all 0.4s ease;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-top: 4px solid var(--primary); /* Default border-top */
            min-height: 380px; /* Increased height for content */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }
        
        .role-card-old:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .role-icon-old {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary); /* Default primary color */
            width: 70px;
            height: 70px;
            background: rgba(91, 109, 245, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            margin-right: auto;
        }

        .role-card-old h4 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            color: var(--neutral-900);
        }

        .role-card-old p {
            font-size: 0.9rem;
            line-height: 1.6;
            color: var(--neutral-600);
            margin-bottom: 1.5rem;
        }

        .role-features {
            text-align: left;
            font-size: 0.85rem;
            margin: 0;
            list-style: none;
            padding: 0;
        }

        .role-features li {
            margin-bottom: 0.6rem;
            color: var(--neutral-600);
        }

        .role-features i {
            color: var(--success);
            margin-right: 0.5rem;
            font-size: 0.8rem;
        }
        
        /* Custom styles for role colors (matching the second image) */
        .role-card-admin-style { border-top-color: var(--primary); }
        .role-card-subadmin-style { border-top-color: var(--icon-green); }
        .role-card-faculty-style { border-top-color: var(--icon-yellow); }
        .role-card-student-style { border-top-color: var(--icon-red); }
        .role-card-librarian-style { border-top-color: var(--primary); }
        .role-card-accountant-style { border-top-color: var(--icon-green); }

        .role-icon-admin-style { color: var(--primary); background: rgba(91, 109, 245, 0.1); }
        .role-icon-subadmin-style { color: var(--icon-green); background: rgba(58, 182, 92, 0.1); }
        .role-icon-faculty-style { color: var(--icon-yellow); background: rgba(245, 179, 0, 0.1); }
        .role-icon-student-style { color: var(--icon-red); background: rgba(231, 76, 60, 0.1); }
        .role-icon-librarian-style { color: var(--primary); background: rgba(91, 109, 245, 0.1); }
        .role-icon-accountant-style { color: var(--icon-green); background: rgba(58, 182, 92, 0.1); }
        /* End Role Card Styles */

        /* About Section */
        .about {
            padding: 80px 0;
            background: var(--neutral-50);
        }

        .about-image {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            object-fit: cover;
            aspect-ratio: 16/9;
        }

        .about h2 {
            color: var(--neutral-900);
            font-weight: 800;
            margin-bottom: 1.5rem;
            font-size: 2.2rem;
        }

        .about p {
            color: var(--neutral-700);
            line-height: 1.8;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }

        /* Styling for the new boxes in the About section */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid var(--neutral-200);
            /* Resetting original stat styles */
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-height: 120px;
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .stats-card .icon-placeholder {
            font-size: 2rem;
            color: var(--primary); /* Defaulting to primary color */
            margin-bottom: 0.5rem;
        }

        .stats-card .feature-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin: 0;
        }

        .stats-card .feature-description {
            font-size: 0.9rem;
            color: var(--neutral-600);
            margin: 0;
        }


        /* CTA Section */
        .cta-section {
            /* UPDATED to use a gradient based on the primary color for consistency */
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            margin: 40px auto;
            max-width: 1320px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .cta-section h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .cta-section p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        .btn-cta-demo {
            background-color: white;
            color: var(--primary);
            padding: 1rem 2.5rem;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 6px;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-cta-demo:hover {
            background-color: var(--neutral-100);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            color: var(--primary);
        }

        /* Footer */
        footer {
            background: var(--neutral-900);
            color: white;
            padding: 4rem 0 1.5rem;
            border-top: 1px solid var(--neutral-800);
        }

        .footer-logo {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .footer-logo i {
            color: var(--primary);
        }

        .footer-text {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .footer-section h6 {
            font-weight: 700;
            margin-bottom: 1.2rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255, 255, 255, 0.9);
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
        }

        .footer-section ul li {
            margin-bottom: 0.8rem;
        }

        .footer-section a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .footer-section a:hover {
            color: var(--primary);
            padding-left: 0.3rem;
        }
        
        .footer-section i.fas {
            margin-right: 0.5rem;
            font-size: 0.85rem;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 1.5rem;
            /* Changed to center the content */
            text-align: center;
        }
        
        /* New CSS class for the developer name color */
        .developer-name {
            color: var(--icon-yellow); 
            font-weight: 600;
            text-decoration: none;
        }

        .footer-bottom p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            margin: 0; /* Remove default margin for centering */
        }

        /* Removed .footer-links as they are no longer needed */
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            opacity: 0;
        }

        .fade-in.visible {
             animation: fadeInUp 0.6s ease-out forwards;
        }


        .scroll-fade {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .scroll-fade.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .btn-explore {
                padding: 0.8rem 1.8rem;
                font-size: 0.9rem;
            }

            .section-header h2 {
                font-size: 2rem;
            }

            .cta-section h2 {
                font-size: 2rem;
            }
            
            .navbar-brand {
                margin-left: 0;
                font-size: 1.3rem;
            }

            .btn-login {
                margin-right: 0.5rem;
                padding: 0.5rem 1.2rem;
                font-size: 0.85rem;
            }

            .nav-link {
                margin: 0 0.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid container-xl">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-graduation-cap"></i> EduManage
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#roles">Roles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#footer">Contact Us</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="login.php" class="btn btn-login me-2">
                        Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <section class="hero">
            <div class="container-fluid container-xl">
                <div class="hero-content">
                    <h1 class="fade-in">
                        Comprehensive Student Management System
                    </h1>
                    <p class="fade-in">
                        Streamlining administration and enhancing communication for modern educational institutions.
                    </p>
                    <div class="fade-in">
                        <a href="#features" class="btn btn-explore">
                            Explore Features
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="features" id="features">
            <div class="container-fluid container-xl">
                <div class="section-header fade-in">
                    <h2>Core Features Built for Modern Education</h2>
                </div>
                <div class="row">
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card scroll-fade">
                            <div class="feature-icon purple"><i class="fas fa-users"></i></div>
                            <h5 class="card-title">Student Management</h5>
                            <p class="card-text">Complete student records, enrollment, and profile management</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card scroll-fade">
                            <div class="feature-icon green"><i class="fas fa-user-tie"></i></div>
                            <h5 class="card-title">Faculty Management</h5>
                            <p class="card-text">Manage faculty profiles, schedules, and assignments</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card scroll-fade">
                            <div class="feature-icon yellow"><i class="fas fa-book-reader"></i></div>
                            <h5 class="card-title">Course Management</h5>
                            <p class="card-text">Organize courses, subjects, and academic programs</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card scroll-fade">
                            <div class="feature-icon red"><i class="fas fa-clipboard-check"></i></div>
                            <h5 class="card-title">Attendance & Marks</h5>
                            <p class="card-text">Track attendance and manage examination marks efficiently</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card scroll-fade">
                            <div class="feature-icon purple"><i class="fas fa-book"></i></div>
                            <h5 class="card-title">Library System</h5>
                            <p class="card-text">Digital library management with book tracking</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card scroll-fade">
                            <div class="feature-icon green"><i class="fas fa-wallet"></i></div>
                            <h5 class="card-title">Fee Management</h5>
                            <p class="card-text">Streamlined fee collection and payment tracking</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card scroll-fade">
                            <div class="feature-icon yellow"><i class="fas fa-chart-line"></i></div>
                            <h5 class="card-title">Reports & Analytics</h5>
                            <p class="card-text">Comprehensive reports and data-driven insights</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="feature-card scroll-fade">
                            <div class="feature-icon red"><i class="fas fa-shield-alt"></i></div>
                            <h5 class="card-title">Security & Access</h5>
                            <p class="card-text">Role-based access control and data security</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="roles" id="roles">
            <div class="container-fluid container-xl">
                <div class="section-header fade-in">
                    <h2>Empowering Every Role in Your Institution</h2>
                </div>
                <div class="row">
                    <div class="col-md-6 col-lg-4">
                        <div class="role-card-old scroll-fade role-card-admin-style">
                            <div>
                                <div class="role-icon-old role-icon-admin-style">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <h4>Administrator</h4>
                                <p>Full system access and management capabilities. Ideal for institution heads and top IT administrators.</p>
                                <ul class="role-features">
                                    <li><i class="fas fa-check"></i> Complete system setup & configuration</li>
                                    <li><i class="fas fa-check"></i> Full user management and access control</li>
                                    <li><i class="fas fa-check"></i> Global audit logs & compliance reporting</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="role-card-old scroll-fade role-card-subadmin-style">
                            <div>
                                <div class="role-icon-old role-icon-subadmin-style">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <h4>SubAdmin</h4>
                                <p>Department-level management and oversight. Perfect for heads of departments or academic coordinators.</p>
                                <ul class="role-features">
                                    <li><i class="fas fa-check"></i> Department and staff administration</li>
                                    <li><i class="fas fa-check"></i> Manage course structure and enrollments</li>
                                    <li><i class="fas fa-check"></i> Generate sectional performance reports</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="role-card-old scroll-fade role-card-faculty-style">
                            <div>
                                <div class="role-icon-old role-icon-faculty-style">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <h4>Faculty</h4>
                                <p>Course management and student assessment. Designed to simplify the educator's daily workflow.</p>
                                <ul class="role-features">
                                    <li><i class="fas fa-check"></i> Daily attendance tracking</li>
                                    <li><i class="fas fa-check"></i> Enter and manage marks/grades</li>
                                    <li><i class="fas fa-check"></i> Communication with students and parents</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="role-card-old scroll-fade role-card-student-style">
                            <div>
                                <div class="role-icon-old role-icon-student-style">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h4>Student</h4>
                                <p>Access to courses, grades, and resources. Centralized hub for academic life.</p>
                                <ul class="role-features">
                                    <li><i class="fas fa-check"></i> View personal academic performance</li>
                                    <li><i class="fas fa-check"></i> Check attendance records and class schedule</li>
                                    <li><i class="fas fa-check"></i> Access library and fee information</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="role-card-old scroll-fade role-card-librarian-style">
                            <div>
                                <div class="role-icon-old role-icon-librarian-style">
                                    <i class="fas fa-book"></i>
                                </div>
                                <h4>Librarian</h4>
                                <p>Library resources and book management. Tools for efficient circulation and inventory control.</p>
                                <ul class="role-features">
                                    <li><i class="fas fa-check"></i> Manage book inventory and catalog</li>
                                    <li><i class="fas fa-check"></i> Handle book issue and return process</li>
                                    <li><i class="fas fa-check"></i> Track overdue books and circulation trends</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="role-card-old scroll-fade role-card-accountant-style">
                            <div>
                                <div class="role-icon-old role-icon-accountant-style">
                                    <i class="fas fa-calculator"></i>
                                </div>
                                <h4>Accountant</h4>
                                <p>Financial operations and fee management. Streamlines revenue tracking and reporting.</p>
                                <ul class="role-features">
                                    <li><i class="fas fa-check"></i> Manage fee structures and invoices</li>
                                    <li><i class="fas fa-check"></i> Process online and offline payments</li>
                                    <li><i class="fas fa-check"></i> Generate comprehensive financial reports</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="about" id="about">
            <div class="container-fluid container-xl">
                <div class="row align-items-center">
                    <div class="col-lg-6 mb-4 mb-lg-0">
                        <div class="fade-in">
                            <img class="about-image" alt="A modern office space with people collaborating around a table." src="https://lh3.googleusercontent.com/aida-public/AB6AXuCyEh3PmyGXKOJjcB8jspfImnlQcivT1-xUVvxOBk4ZBahJtpewHcL_jHDw5HSyCZW6YhD5fRkTh_W00QNr-idKtmRcoD4Hr3OrNkHQ9rgP5s1GGDEYRVp-aHg_Lat8G6N_dVe6HM3Fur9HYX87yopMxgefg7YzzP_Uf22zdwMdy5AIbJMQPhlDqfAohpj5a2FOGVcwp4IMTDWy7OPvuFJdoxQfSxd_E-D1qq1UMY1ElbiZw9UNSUW11oAyrQJUUMGir6mkhvg5V_c"/>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="fade-in">
                            <h2>About EduManage</h2>
                            <p align="justify">EduManage is a comprehensive educational management platform designed to streamline administrative tasks, enhance communication, and improve the overall efficiency of educational institutions.
Built with cutting-edge technology, our system provides a unified solution for managing students, faculty, courses, attendance, library resources, and financial operations—all while maintaining the highest standards of security and data privacy.</p>
                            <div class="row mt-4">
                                <div class="col-sm-6 mb-3">
                                    <div class="stats-card">
                                        <div class="icon-placeholder">
                                            <i class="fas fa-bolt"></i>
                                        </div>
                                        <p class="feature-title">Performance</p>
                                        <p class="feature-description">Lightning-fast and reliable</p>
                                    </div>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <div class="stats-card">
                                        <div class="icon-placeholder">
                                            <i class="fas fa-user-friends"></i>
                                        </div>
                                        <p class="feature-title">Role-Based Access</p>
                                        <p class="feature-description">Customized permissions</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section">
            <div class="container-fluid container-xl fade-in">
                <h2>Ready to Transform Your Institution?</h2>
                <p>See how EduManage can streamline your operations, improve communication, and enhance the educational experience for everyone involved.</p>
                <a href="login.php"><button class="btn btn-cta-demo">
                    Sign in to Dashboard
                </button></a>
            </div>
        </section>
    </main>

    <footer id="footer">
        <div class="container-fluid container-xl">
            <div class="row">
                <div class="col-md-6 col-lg-3 mb-4 mb-lg-0">
                    <div class="footer-section">
                        <div class="footer-logo"><i class="fas fa-graduation-cap"></i> EduManage</div>
                        <p class="footer-text">The complete solution for modern educational institutions.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2 mb-4 mb-md-0">
                    <div class="footer-section">
                        <h6>Product</h6>
                        <ul>
                            <li><a href="#features">Features</a></li>
                            <li><a href="#">Pricing</a></li>
                            <li><a href="#">Security</a></li>
                            <li><a href="#">Demo</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2 mb-4 mb-lg-0">
                    <div class="footer-section">
                        <h6>Company</h6>
                        <ul>
                            <li><a href="#about">About Us</a></li>
                            <li><a href="#">Careers</a></li>
                            <li><a href="#">Contact</a></li>
                            <li><a href="#">Blog</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2 mb-4 mb-lg-0">
                    <div class="footer-section">
                        <h6>Resources</h6>
                        <ul>
                            <li><a href="#">Help Center</a></li>
                            <li><a href="#">API Docs</a></li>
                            <li><a href="#">System Status</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                     <div class="footer-section">
                        <h6>Contact</h6>
                        <ul>
                            <li><a href="mailto:bhandarkarmadhur02@gmail.com"><i class="fas fa-envelope"></i> bhandarkarmadhur02@gmail.com</a></li>
                            <li><a href="tel:+919373628644"><i class="fas fa-phone"></i> +91-9373628644</a></li>
                            <li><i class="fas fa-map-marker-alt"></i> Dhule, India</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom mt-5">
                <p>
                    © 2025 EduManage. All rights reserved. | Developed by 
                    <a href="https://www.linkedin.com/in/madhur-bhandarkar-9bb342288/" class="developer-name">Madhur Bhandarkar</a>
                </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll animation
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.scroll-fade').forEach(el => observer.observe(el));

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Fade in hero elements
        window.addEventListener('load', function() {
            const heroElements = document.querySelectorAll('.fade-in');
            heroElements.forEach((el, index) => {
                setTimeout(() => {
                    el.classList.add('visible'); // Use the .visible class from CSS
                }, index * 150);
            });
        });
    </script>
</body>
</html>