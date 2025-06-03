<?php
require_once 'config/db.php';
require_once 'includes/session.php';

// Check if user is logged in and is a company
if (!isLoggedIn() || $_SESSION['role'] !== 'company') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);
    
    try {
        // Update company profile
        $stmt = $pdo->prepare("
            UPDATE companies 
            SET company_name = ?, location = ?, description = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$company_name, $location, $description, $_SESSION['user_id']]);
        
        $_SESSION['success'] = "Company profile updated successfully!";
        header('Location: company_dashboard.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
        header('Location: company_dashboard.php');
        exit();
    }
} else {
    // If not a POST request, redirect to dashboard
    header('Location: company_dashboard.php');
    exit();
} 