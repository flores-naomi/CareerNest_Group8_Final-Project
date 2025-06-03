<?php
//register_individual.php
require_once 'config/db.php';
require_once 'includes/session.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $error = "Email already registered.";
        } else {
            // Hash password and create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            
            try {
                $stmt->execute([$name, $email, $hashed_password]);
                $success = "Registration successful! You can now login.";
            } catch (PDOException $e) {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CareerNest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4B654F;
            --primary-color-hover: #3A463A;
            --primary-color-light: rgba(75, 101, 79, 0.25);
            --success-bg: #D6EFD6;
            --danger-bg: #F9D6D6;
        }

        /* General Styles */
        body {
            background: linear-gradient(135deg, #f5f7f5 0%, #e8f0e8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .card-body {
            padding: 2rem;
        }

        .card:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.1) !important;
            transform: translateY(-3px);
        }

        /* Form Control Styling */
        .form-control, .form-select {
            border-radius: 6px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.25rem var(--primary-color-light) !important;
            transform: translateY(-2px);
        }

        .form-control:hover, .form-select:hover {
            border-color: var(--primary-color-hover) !important;
        }

        /* File Input Specific Styling */
        input[type="file"].form-control:hover {
            background-color: #f8f9fa;
            border-color: var(--primary-color-hover) !important;
        }

        input[type="file"].form-control:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.25rem var(--primary-color-light) !important;
        }

        /* Checkbox Styling */
        .form-check-input:checked {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .form-check-input:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.25rem var(--primary-color-light) !important;
        }

        /* Button Styling */
        .btn-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary:hover {
            background-color: var(--primary-color-hover) !important;
            border-color: var(--primary-color-hover) !important;
            animation: pulse 0.5s;
        }

        .btn-primary:focus {
            box-shadow: 0 0 0 0.25rem var(--primary-color-light) !important;
        }

        .btn-primary:after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            opacity: 0;
        }

        .btn-primary:focus:after {
            animation: ripple 0.6s ease-out;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        @keyframes ripple {
            0% {
                width: 0;
                height: 0;
                opacity: 0.5;
            }
            100% {
                width: 500px;
                height: 500px;
                opacity: 0;
            }
        }

        /* Alert Styling */
        .alert-success {
            background-color: var(--success-bg) !important;
            border-color: var(--primary-color) !important;
            color: var(--primary-color) !important;
        }

        .alert-danger {
            background-color: var(--danger-bg) !important;
        }

        /* Link Styling */
        a {
            color: var(--primary-color);
            text-decoration: none;
            position: relative;
            transition: all 0.3s ease;
        }

        a:hover {
            color: var(--primary-color-hover) !important;
        }

        a:before {
            content: "";
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: var(--primary-color-hover);
            visibility: hidden;
            transition: all 0.3s ease-in-out;
        }

        a:hover:before {
            visibility: visible;
            width: 100%;
        }

        /* Accessibility Focus Styles */
        a:focus-visible, button:focus-visible, input:focus-visible, 
        textarea:focus-visible, select:focus-visible {
            outline: 3px solid rgba(75, 101, 79, 0.5);
            outline-offset: 2px;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            
            h2 {
                font-size: 1.75rem;
            }
            
            .btn-primary {
                padding: 0.6rem 1.2rem;
            }
        }

        @media (max-width: 576px) {
            .card-body {
                padding: 1.25rem;
            }
            
            h2 {
                font-size: 1.5rem;
                margin-bottom: 1rem !important;
            }
            
            .form-control, .form-select {
                padding: 0.6rem 0.8rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5 mb-5">
            <div class="col-lg-6 col-md-8 col-sm-12">
                <div class="card shadow">
                    <div class="card-body p-md-5 p-4">
                        <h2 class="text-center mb-4">Create Your Account</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" id="registrationForm">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Password must be at least 8 characters long.</div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#">Terms and Conditions</a>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mt-3">Register</button>
                        </form>

                        <div class="text-center mt-4">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            <p>Are you an employer? <a href="register_company.php">Register your company</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>