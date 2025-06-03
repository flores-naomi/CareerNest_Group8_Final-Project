<?php
session_start();

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Function to check if user is logged in
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['company_id'])) {
        return false;
    }

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }

    // Check IP address
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_unset();
        session_destroy();
        return false;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

// Function to check user role
function getUserRole() {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['role'] ?? null;
}

// Function to verify specific role
function verifyRole($required_role) {
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['role'] === $required_role;
}

// Function to redirect based on role
function redirectBasedOnRole() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    // Verify role matches session
    $role = getUserRole();
    if (!$role) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }

    switch ($role) {
        case 'admin':
            if (!verifyRole('admin')) {
                session_unset();
                session_destroy();
                header('Location: login.php');
                exit();
            }
            header('Location: admin_dashboard.php');
            break;
        case 'company':
            if (!verifyRole('company')) {
                session_unset();
                session_destroy();
                header('Location: login.php');
                exit();
            }
            header('Location: company_dashboard.php');
            break;
        case 'user':
            if (!verifyRole('user')) {
                session_unset();
                session_destroy();
                header('Location: login.php');
                exit();
            }
            header('Location: user_dashboard.php');
            break;
        default:
            session_unset();
            session_destroy();
            header('Location: login.php');
    }
    exit();
}

// Function to generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to logout user
function logout() {
    // Update last login timestamp if user is logged in
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Log error but continue with logout
            error_log("Error updating last login: " . $e->getMessage());
        }
    }
    
    // Clear and destroy session
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
} 