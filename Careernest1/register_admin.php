<?php
//register_admin.php
require_once 'config/db.php';
require_once 'includes/session.php';

$error = '';
$success = '';

// Check if admin already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
$stmt->execute();
if ($stmt->rowCount() > 0) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_code = trim($_POST['admin_code']); // Special code required for admin registration

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($admin_code !== 'ADMIN2024') {
        $error = "Invalid admin registration code.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $error = "Email already registered.";
        } else {
            // Check if admin already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $error = "Admin account already exists.";
            } else {
                // Hash password and create admin user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
                
                try {
                    $stmt->execute([$name, $email, $hashed_password]);
                    $success = "Admin registration successful! You can now login.";
                } catch (PDOException $e) {
                    $error = "Registration failed. Please try again.";
                }
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
    <title>Register Admin - CareerNest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4B654F;
            --primary-dark: #3A463A;
            --primary-light: #D6EFD6;
            --accent-color: #E9F5E9;
        }

        /* Body Styles */
        body {
            background: linear-gradient(135deg, #f5f7f5 0%, #e8f0e8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        /* Card Styles */
        .card-custom {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .card-custom:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }

        /* Form Control Styles */
        .form-control:focus, .form-select:focus {
            border-color: #4B654F !important;
            box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25) !important;
        }

        .form-control:hover, .form-select:hover {
            border-color: #3A463A !important;
        }

        /* Button Styles */
        .btn-primary {
            background-color: #4B654F !important;
            border-color: #4B654F !important;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #3A463A !important;
            border-color: #3A463A !important;
            animation: pulse 0.5s;
        }

        .btn-outline-primary {
            color: #4B654F !important;
            border-color: #4B654F !important;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background-color: #4B654F !important;
            color: white !important;
            animation: pulse 0.5s;
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

        /* Link Styles */
        a {
            color: #4B654F;
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
        }

        a:hover {
            color: #3A463A;
            text-decoration: underline;
        }

        a:focus-visible {
            outline: 3px solid rgba(75, 101, 79, 0.5);
            outline-offset: 2px;
        }

        /* Logo Styles */
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4B654F;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        /* Alert Styles */
        .alert-success {
            background-color: rgba(214, 239, 214, 0.5);
            border-color: rgba(75, 101, 79, 0.3);
            color: #3A463A;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem !important;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 15px;
            }
            .card-body {
                padding: 1.25rem !important;
            }
            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 col-sm-10 col-11">
                <div class="card card-custom shadow">
                    <div class="card-body p-4 p-sm-5">
                        <div class="logo-text">CareerNest</div>
                        <h2 class="text-center mb-4">Register as Admin</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" id="adminRegistrationForm">
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

                            <div class="mb-3">
                                <label for="admin_code" class="form-label">Admin Registration Code</label>
                                <input type="password" class="form-control" id="admin_code" name="admin_code" required>
                                <div class="form-text">Enter the special admin registration code.</div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#">Terms and Conditions</a>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Register as Admin</button>
                        </form>

                        <div class="text-center mt-4">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            <div class="d-flex flex-column gap-1 mt-2">
                                <a href="register_individual.php">Register as Job Seeker</a>
                                <a href="register_company.php">Register as Employer</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('adminRegistrationForm').addEventListener('submit', function(e) {
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