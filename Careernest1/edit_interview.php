<?php
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'config/db.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Get interview details
if (isset($_GET['id'])) {
    $interview_id = $_GET['id'];
    
    $stmt = $pdo->prepare("
        SELECT isch.*, u.name as applicant_name, 
               jl.title as job_title, c.company_name 
        FROM interview_schedules isch
        JOIN users u ON isch.user_id = u.id 
        JOIN job_listings jl ON isch.job_id = jl.id 
        JOIN companies c ON isch.company_id = c.id 
        WHERE isch.id = ?
    ");
    $stmt->execute([$interview_id]);
    $interview = $stmt->fetch();

    if (!$interview) {
        header('Location: approve_interview.php');
        exit();
    }
} else {
    header('Location: approve_interview.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $interview_date = $_POST['interview_date'];
    $interview_time = $_POST['interview_time'];
    $interview_mode = $_POST['interview_mode'];
    $interview_location = $_POST['interview_location'];
    $interview_link = $_POST['interview_link'];
    $company_notes = $_POST['company_notes'];
    $admin_notes = $_POST['admin_notes'];

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Update interview details
        $stmt = $pdo->prepare("
            UPDATE interview_schedules 
            SET interview_date = ?,
                interview_time = ?,
                interview_mode = ?,
                interview_location = ?,
                interview_link = ?,
                company_notes = ?,
                admin_notes = ?,
                status = 'admin_modified',
                status_changed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $interview_date,
            $interview_time,
            $interview_mode,
            $interview_location,
            $interview_link,
            $company_notes,
            $admin_notes,
            $interview_id
        ]);

        // Create notification for applicant
        $applicant_notification_title = "Interview Schedule Updated";
        $applicant_notification_message = "Your interview for {$interview['job_title']} at {$interview['company_name']} on " . 
            date('M d, Y', strtotime($interview_date)) . " at " . 
            date('h:i A', strtotime($interview_time)) . 
            " has been updated.";

        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            VALUES (?, ?, ?, 'interview', NOW())
        ");
        $stmt->execute([$interview['user_id'], $applicant_notification_title, $applicant_notification_message]);

        // Create notification for company
        $company_notification_title = "Interview Schedule Modified";
        $company_notification_message = "Interview for {$interview['job_title']} with {$interview['applicant_name']} on " . 
            date('M d, Y', strtotime($interview_date)) . " at " . 
            date('h:i A', strtotime($interview_time)) . 
            " has been modified by admin. Please review the changes.";

        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            VALUES (?, ?, ?, 'interview', NOW())
        ");
        $stmt->execute([$interview['company_id'], $company_notification_title, $company_notification_message]);

        $pdo->commit();
        $success = "Interview schedule has been updated successfully.";
        
        // Refresh interview data
        $stmt = $pdo->prepare("
            SELECT isch.*, u.name as applicant_name, 
                   jl.title as job_title, c.company_name 
            FROM interview_schedules isch
            JOIN users u ON isch.user_id = u.id 
            JOIN job_listings jl ON isch.job_id = jl.id 
            JOIN companies c ON isch.company_id = c.id 
            WHERE isch.id = ?
        ");
        $stmt->execute([$interview_id]);
        $interview = $stmt->fetch();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating interview: " . $e->getMessage();
    }
}
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Edit Interview</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h6>Interview Details</h6>
                        <p><strong>Applicant:</strong> <?php echo htmlspecialchars($interview['applicant_name']); ?></p>
                        <p><strong>Position:</strong> <?php echo htmlspecialchars($interview['job_title']); ?></p>
                        <p><strong>Company:</strong> <?php echo htmlspecialchars($interview['company_name']); ?></p>
                    </div>

                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="interview_date" class="form-label">Interview Date</label>
                                <input type="date" class="form-control" id="interview_date" name="interview_date" 
                                       value="<?php echo $interview['interview_date']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="interview_time" class="form-label">Interview Time</label>
                                <input type="time" class="form-control" id="interview_time" name="interview_time" 
                                       value="<?php echo $interview['interview_time']; ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="interview_mode" class="form-label">Interview Mode</label>
                            <select class="form-select" id="interview_mode" name="interview_mode" required>
                                <option value="onsite" <?php echo $interview['interview_mode'] === 'onsite' ? 'selected' : ''; ?>>Onsite</option>
                                <option value="online" <?php echo $interview['interview_mode'] === 'online' ? 'selected' : ''; ?>>Online</option>
                            </select>
                        </div>

                        <div class="mb-3" id="locationField" style="display: <?php echo $interview['interview_mode'] === 'onsite' ? 'block' : 'none'; ?>">
                            <label for="interview_location" class="form-label">Interview Location</label>
                            <input type="text" class="form-control" id="interview_location" name="interview_location" 
                                   value="<?php echo htmlspecialchars($interview['interview_location']); ?>">
                        </div>

                        <div class="mb-3" id="linkField" style="display: <?php echo $interview['interview_mode'] === 'online' ? 'block' : 'none'; ?>">
                            <label for="interview_link" class="form-label">Interview Link</label>
                            <input type="url" class="form-control" id="interview_link" name="interview_link" 
                                   value="<?php echo htmlspecialchars($interview['interview_link']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="company_notes" class="form-label">Notes for Applicant</label>
                            <textarea class="form-control" id="company_notes" name="company_notes" rows="3"><?php echo htmlspecialchars($interview['company_notes']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Admin Notes</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3"><?php echo htmlspecialchars($interview['admin_notes']); ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="approve_interview.php" class="btn btn-secondary">Back to Interviews</a>
                            <button type="submit" class="btn btn-primary">Update Interview</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('interview_mode').addEventListener('change', function() {
    const locationField = document.getElementById('locationField');
    const linkField = document.getElementById('linkField');
    
    if (this.value === 'onsite') {
        locationField.style.display = 'block';
        linkField.style.display = 'none';
    } else {
        locationField.style.display = 'none';
        linkField.style.display = 'block';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?> 