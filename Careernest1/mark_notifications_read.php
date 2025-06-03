<?php
require_once 'config/db.php';
require_once 'includes/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Handle single notification marking (GET request)
    if (isset($_GET['id'])) {
        $notification_id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notification_id, $user_id]);
    }
    // Handle multiple notifications marking (POST request)
    else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // If specific notification IDs are provided
        if (isset($_POST['notification_ids']) && is_array($_POST['notification_ids'])) {
            $notification_ids = array_map('intval', $_POST['notification_ids']);
            $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
            
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id IN ($placeholders) 
                AND user_id = ?
            ");
            
            $params = array_merge($notification_ids, [$user_id]);
            $stmt->execute($params);
        } 
        // If "Mark All as Read" is clicked
        else if (isset($_POST['mark_all_read'])) {
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? 
                AND is_read = 0
            ");
            $stmt->execute([$user_id]);
        }
    }

    // Redirect back to the previous page or appropriate dashboard
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : getDefaultDashboard();
    header('Location: ' . $redirect);
    exit();
} catch (PDOException $e) {
    error_log("Error marking notifications as read: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while updating notifications.";
    header('Location: ' . getDefaultDashboard());
    exit();
}

// Helper function to get the appropriate dashboard URL based on user role
function getDefaultDashboard() {
    $role = getUserRole();
    switch ($role) {
        case 'admin':
            return 'admin_dashboard.php';
        case 'company':
            return 'company_dashboard.php';
        case 'user':
        default:
            return 'user_dashboard.php';
    }
} 