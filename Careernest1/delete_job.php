<?php
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'config/db.php';

// Check if company is logged in
if (!isLoggedIn() || getUserRole() !== 'company') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id'])) {
    $job_id = (int)$_POST['job_id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Verify the job belongs to the company
        $stmt = $pdo->prepare("SELECT id FROM job_listings WHERE id = ? AND company_id = ?");
        $stmt->execute([$job_id, $_SESSION['company_id']]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Job not found or unauthorized access.");
        }
        
        // Delete related records first
        $stmt = $pdo->prepare("DELETE FROM job_applications WHERE job_id = ?");
        $stmt->execute([$job_id]);
        
        // Delete the job listing
        $stmt = $pdo->prepare("DELETE FROM job_listings WHERE id = ?");
        $stmt->execute([$job_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Job listing deleted successfully.";
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['error'] = "Error deleting job: " . $e->getMessage();
    }
}

// Redirect back to company dashboard
header('Location: company_dashboard.php');
exit(); 