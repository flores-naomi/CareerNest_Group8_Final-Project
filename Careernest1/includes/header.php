<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerNest</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4B654F;
            --primary-dark: #3A463A;
            --primary-light: #D6EFD6;
            --accent-color: #E9F5E9;
            --text-primary: #333333;
            --text-muted: #6c757d;
            --light-gray: #f8f9fa;
            --border-radius: 15px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        /* Alert Styles */
        .alert-success {
            background: #E6EFE6;
            color: #3A463A;
            border: 1px solid #BFCABF;
        }
        .alert-danger {
            background: #fff0f0;
            color: #3A463A;
            border: 1px solid #BFCABF;
        }

        /* Navigation Styles */
        .navbar {
            background: #fff !important;
            border-bottom: 1px solid #BFCABF;
            padding: 1rem 0;
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: 700;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            color: var(--primary-dark) !important;
            transform: translateY(-1px);
        }

        .nav-link {
            color: var(--text-primary) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            position: relative;
        }
        .nav-link:hover, .nav-link:focus {
            color: var(--primary-color) !important;
            background-color: var(--accent-color);
            border-radius: 6px;
            text-decoration: none;
        }

        .nav-link.active {
            color: var(--primary-color) !important;
            font-weight: 600;
        }

        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 1rem;
            right: 1rem;
            height: 2px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }

        .dropdown-menu {
            border: 1px solid #BFCABF;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            padding: 0.5rem;
        }

        .dropdown-item {
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: var(--accent-color);
            color: var(--primary-color);
        }

        .dropdown-divider {
            border-color: #BFCABF;
            margin: 0.5rem 0;
        }

        .navbar-toggler {
            border: 1px solid #BFCABF;
            padding: 0.5rem;
        }

        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25);
        }

        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: white;
                padding: 1rem;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                margin-top: 1rem;
            }

            .nav-link.active::after {
                display: none;
            }

            .nav-link.active {
                background-color: var(--accent-color);
                border-radius: 6px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
</body>
</html> 