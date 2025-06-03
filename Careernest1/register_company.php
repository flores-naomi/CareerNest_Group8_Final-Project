<?php
//register_company.php
require_once 'config/db.php';
require_once 'includes/session.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_name = trim($_POST['company_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);

    // Validation
    if (empty($company_name) || empty($email) || empty($password)) {
        $error = "Company name, email, and password are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check if email already exists in either table
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $error = "Email already registered as a company.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "Email already registered as a user.";
            } else {
                try {
                    // Start transaction
                    $pdo->beginTransaction();

                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert into users table first
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'company', 1)");
                    $stmt->execute([$company_name, $email, $hashed_password]);
                    $user_id = $pdo->lastInsertId();

                    // Insert into companies table with user_id, email, and password
                    $stmt = $pdo->prepare("INSERT INTO companies (user_id, company_name, email, password, location, description, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$user_id, $company_name, $email, $hashed_password, $location, $description]);
                    $company_id = $pdo->lastInsertId();

                    // Create notification for admin about new company registration
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                    $stmt->execute();
                    $admin = $stmt->fetch();

                    if ($admin) {
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (
                                user_id, type, message, link, created_at
                            ) VALUES (?, 'new_company', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $admin['id'],
                            "New company registered: " . $company_name,
                            "manage_companies.php"
                        ]);
                    }

                    // Commit transaction
                    $pdo->commit();

                    $success = "Registration successful! You can now login.";
                } catch (PDOException $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
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
    <title>Register Company - CareerNest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4B654F;
            --primary-dark: #3A463A;
            --primary-light: #D6EFD6;
            --accent-color: #E9F5E9;
            --success-bg: #D6EFD6;
            --danger-bg: #F9D6D6;
        }

        /* Body Styles */
        body {
            background: linear-gradient(135deg, #f5f7f5 0%, #e8f0e8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }

        .card-body {
            padding: 2rem;
        }

        /* Form Control Styling */
        .form-control, .form-select {
            border-radius: 6px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25) !important;
            transform: translateY(-2px);
        }

        .form-control:hover, .form-select:hover {
            border-color: var(--primary-dark) !important;
        }

        /* Checkbox Styling */
        .form-check-input:checked {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .form-check-input:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25) !important;
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
            background-color: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
            animation: pulse 0.5s;
        }

        .btn-primary:focus {
            box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25) !important;
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
            transition: all 0.2s ease;
        }

        a:hover {
            color: var(--primary-dark);
        }

        a:before {
            content: "";
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: var(--primary-dark);
            visibility: hidden;
            transition: all 0.3s ease-in-out;
        }

        a:hover:before {
            visibility: visible;
            width: 100%;
        }

        /* Logo Styles */
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 1.5rem;
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
                padding: 1.5rem !important;
            }
            
            h2 {
                font-size: 1.75rem;
            }
            
            .btn-primary {
                padding: 0.6rem 1.2rem;
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
                margin-bottom: 1rem !important;
            }
            
            .form-control, .form-select {
                padding: 0.6rem 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5 mb-5">
            <div class="col-lg-8 col-md-10 col-sm-12">
                <div class="card shadow">
                    <div class="card-body p-md-5 p-4">
                        <div class="logo-text">CareerNest</div>
                        <h2 class="text-center mb-4">Register Your Company</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" id="companyRegistrationForm">
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Company Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label for="location" class="form-label">Company Location</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Company Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
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

                            <button type="submit" class="btn btn-primary w-100 mt-3">Register Company</button>
                        </form>

                        <div class="text-center mt-4">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            <p>Are you a job seeker? <a href="register_individual.php">Register as Job Seeker</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('companyRegistrationForm').addEventListener('submit', function(e) {
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