<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';


if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

// Check if user is admin
if (!isAdmin()) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $interview_id = $_POST['interview_id'] ?? null;
    $action = $_POST['action'] ?? 'approve';
    $admin_notes = $_POST['notes'] ?? '';

    if (!$interview_id) {
        $_SESSION['error'] = "Invalid interview ID.";
        header("Location: admin_dashboard.php");
        exit();
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get interview details
        $stmt = $pdo->prepare("
            SELECT isch.*, jl.title as job_title, u.name as applicant_name
            FROM interview_schedules isch
            JOIN job_listings jl ON isch.job_id = jl.id
            JOIN users u ON isch.user_id = u.id
            WHERE isch.id = ?
        ");
        $stmt->execute([$interview_id]);
        $interview = $stmt->fetch();

        if (!$interview) {
            throw new Exception("Interview not found.");
        }

        // Update interview status
        $new_status = $action === 'reject' ? 'rejected' : 'approved';
        $stmt = $pdo->prepare("
            UPDATE interview_schedules 
            SET status = ?, 
                admin_notes = ?, 
                status_changed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $admin_notes, $interview_id]);

        // Create notification for company
        $notification_title = $action === 'reject' ? 
            "Interview Schedule Rejected" : 
            "Interview Schedule Approved";
        $notification_message = $action === 'reject' ?
            "Your interview schedule for {$interview['job_title']} with {$interview['applicant_name']} on " . 
            date('M d, Y', strtotime($interview['interview_date'])) . " at " . 
            date('h:i A', strtotime($interview['interview_time'])) . 
            " has been rejected. Reason: {$admin_notes}" :
            "Your interview schedule for {$interview['job_title']} with {$interview['applicant_name']} on " . 
            date('M d, Y', strtotime($interview['interview_date'])) . " at " . 
            date('h:i A', strtotime($interview['interview_time'])) . 
            " has been approved.";

        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            VALUES (?, ?, ?, 'interview', NOW())
        ");
        $stmt->execute([$interview['company_id'], $notification_title, $notification_message]);

        // Only create notification for applicant if approved
        if ($action === 'approve') {
            $applicant_notification_title = "Interview Schedule Approved";
            $applicant_notification_message = "Your interview schedule for {$interview['job_title']} at {$interview['company_name']} on " . 
                date('M d, Y', strtotime($interview['interview_date'])) . " at " . 
                date('h:i A', strtotime($interview['interview_time'])) . 
                " has been approved.";

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at)
                VALUES (?, ?, ?, 'interview', NOW())
            ");
            $stmt->execute([$interview['user_id'], $applicant_notification_title, $applicant_notification_message]);
        }

        // Commit transaction
        $pdo->commit();

        $_SESSION['success'] = $action === 'reject' ? 
            "Interview schedule has been rejected." : 
            "Interview schedule has been approved.";
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    }

    header("Location: admin_dashboard.php");
    exit();
}

// If not POST request, redirect to dashboard
header("Location: admin_dashboard.php");
exit(); 