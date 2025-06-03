<?php
require_once 'includes/session.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Only allow companies
if (!isLoggedIn() || getUserRole() !== 'company') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = $_POST['schedule_id'] ?? null;

    if (!$schedule_id) {
        $_SESSION['error'] = "Invalid schedule ID.";
        header("Location: company_dashboard.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Get schedule details
        $stmt = $pdo->prepare("
            SELECT isch.*, jl.title as job_title, u.name as applicant_name
            FROM interview_schedules isch
            JOIN job_listings jl ON isch.job_id = jl.id
            JOIN users u ON isch.user_id = u.id
            WHERE isch.id = ? 
            AND isch.status = 'admin_modified'
            AND isch.company_id = ?
        ");
        $stmt->execute([$schedule_id, $_SESSION['company_id']]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            throw new Exception("Schedule not found or not in correct status.");
        }

        // Update schedule status
        $stmt = $pdo->prepare("
            UPDATE interview_schedules 
            SET status = 'approved', 
                status_changed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$schedule_id]);

        // Notify applicant only after company approval
        $notification_title = "Interview Schedule Confirmed";
        $notification_message = "Your interview for {$schedule['job_title']} has been confirmed for " . 
            date('M d, Y', strtotime($schedule['interview_date'])) . " at " . 
            date('h:i A', strtotime($schedule['interview_time'])) . 
            ". The interview will be conducted " . 
            ($schedule['interview_mode'] === 'online' ? 'online' : 'at ' . $schedule['interview_location']);

        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            VALUES (?, ?, ?, 'interview', NOW())
        ");
        $stmt->execute([$schedule['user_id'], $notification_title, $notification_message]);

        $pdo->commit();
        $_SESSION['success'] = "Interview schedule has been finalized.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error finalizing schedule: " . $e->getMessage();
    }

    header("Location: company_dashboard.php");
    exit();
}
require_once 'includes/header.php';
// If not POST, redirect
header("Location: company_dashboard.php");
exit(); 