<?php
//login.php
require_once 'config/db.php';
require_once 'includes/session.php';

$error = '';

// Check if admin exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
$stmt->execute();
$admin_exists = $stmt->rowCount() > 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];

    // Validate user type
    if (!in_array($user_type, ['user', 'company', 'admin'])) {
        $error = "Invalid user type selected.";
    } else {
        // Check users table for admin and job seeker
        if ($user_type === 'user' || $user_type === 'admin') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
            $stmt->execute([$email, $user_type]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Clear any existing session data
                session_unset();
                session_destroy();
                session_start();
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['login_time'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                
                // Update last login timestamp
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: user_dashboard.php');
                }
                exit();
            }
        } else if ($user_type === 'company') {
            // Check companies table
            $stmt = $pdo->prepare("SELECT c.*, u.id as user_id, u.password, u.role FROM companies c JOIN users u ON c.email = u.email WHERE c.email = ? AND u.role = 'company'");
            $stmt->execute([$email]);
            $company = $stmt->fetch();

            if ($company && password_verify($password, $company['password'])) {
                // Clear any existing session data
                session_unset();
                session_destroy();
                session_start();
                
                // Set session variables
                $_SESSION['company_id'] = $company['id'];
                $_SESSION['user_id'] = $company['user_id'];
                $_SESSION['role'] = 'company';
                $_SESSION['last_activity'] = time();
                $_SESSION['login_time'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                
                // Update last login timestamp
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$company['user_id']]);
                
                header('Location: company_dashboard.php');
                exit();
            }
        }
        
        // If we get here, login failed
        $error = "Invalid email or password for the selected user type.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CareerNest</title>
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
                        <h2 class="text-center mb-4">Welcome Back</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="user_type" class="form-label">I am a:</label>
                                <select class="form-select" id="user_type" name="user_type" required>
                                    <option value="user">Job Seeker</option>
                                    <option value="company">Employer</option>
                                    <?php if ($admin_exists): ?>
                                        <option value="admin">Admin</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>

                        <div class="text-center mt-4">
                            <p>Don't have an account?</p>
                            <div class="d-grid gap-2">
                                <a href="register_individual.php" class="btn btn-outline-primary">Register as Job Seeker</a>
                                <a href="register_company.php" class="btn btn-outline-primary">Register as Employer</a>
                                <?php if (!$admin_exists): ?>
                                    <a href="register_admin.php" class="btn btn-outline-primary">Register as Admin</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>