<?php
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'config/db.php';

// Check if admin is logged in
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle schedule actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['schedule_id'])) {
        $schedule_id = (int)$_POST['schedule_id'];
        $action = $_POST['action'];

        try {
            // Start transaction
            $pdo->beginTransaction();

            // Verify schedule exists and get current status
            $stmt = $pdo->prepare("
                SELECT isch.*, jl.title as job_title, jl.deadline
                FROM interview_schedules isch
                JOIN job_listings jl ON isch.job_id = jl.id
                WHERE isch.id = ?
            ");
            $stmt->execute([$schedule_id]);
            $schedule = $stmt->fetch();

            if (!$schedule) {
                throw new Exception("Schedule not found.");
            }

            // Check if job posting is still active
            if (strtotime($schedule['deadline']) < time()) {
                throw new Exception("Cannot modify interview schedule for an expired job posting.");
            }

            switch ($action) {
                case 'approve':
                    // Check if schedule is already approved
                    if ($schedule['status'] === 'approved') {
                        throw new Exception("This schedule is already approved.");
                    }

                    // Check if schedule is in a valid state for approval
                    if ($schedule['status'] !== 'proposed') {
                        throw new Exception("Only proposed schedules can be approved.");
                    }

                    $stmt = $pdo->prepare("
                        UPDATE interview_schedules 
                        SET status = 'approved',
                            status_changed_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ? AND status = 'proposed'
                    ");
                    $stmt->execute([$schedule_id]);

                    // Create notification for applicant using centralized function
                    $notification_result = createNotification(
                        $schedule['user_id'],
                        "Interview Scheduled",
                        "interview_scheduled",
                        "Interview scheduled for {$schedule['job_title']} on " . 
                        date('M d, Y', strtotime($schedule['interview_date'])) . " at " . 
                        date('h:i A', strtotime($schedule['interview_time'])),
                        "view_schedule.php?id=" . $schedule_id
                    );

                    if (!$notification_result['success']) {
                        error_log("Failed to create applicant notification: " . $notification_result['message']);
                    }

                    $_SESSION['success'] = "Interview schedule approved and applicant notified.";
                    break;

                case 'modify':
                    if (!isset($_POST['interview_date'], $_POST['interview_time'], $_POST['interview_mode'], 
                             $_POST['interview_location'], $_POST['interview_link'], $_POST['admin_notes'])) {
                        throw new Exception("All fields are required for modification.");
                    }

                    // Validate input
                    $errors = [];
                    if (strtotime($_POST['interview_date']) < strtotime('today')) {
                        $errors[] = "Interview date cannot be in the past";
                    }
                    if (strtotime($_POST['interview_date']) > strtotime('+6 months')) {
                        $errors[] = "Interview date cannot be more than 6 months in the future";
                    }
                    if ($_POST['interview_mode'] === 'online' && !filter_var($_POST['interview_link'], FILTER_VALIDATE_URL)) {
                        $errors[] = "Please provide a valid URL for online interview";
                    }
                    if ($_POST['interview_mode'] === 'onsite' && empty($_POST['interview_location'])) {
                        $errors[] = "Location is required for onsite interviews";
                    }
                    if (strlen($_POST['admin_notes']) > 1000) {
                        $errors[] = "Admin notes must be less than 1000 characters";
                    }

                    if (!empty($errors)) {
                        throw new Exception(implode("<br>", $errors));
                    }

                    // Check for scheduling conflicts
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM interview_schedules 
                        WHERE company_id = ? 
                        AND interview_date = ? 
                        AND interview_time = ?
                        AND id != ?
                        AND status NOT IN ('cancelled', 'rejected')
                    ");
                    $stmt->execute([
                        $schedule['company_id'],
                        $_POST['interview_date'],
                        $_POST['interview_time'],
                        $schedule_id
                    ]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception("This time slot conflicts with another interview schedule.");
                    }

                    $stmt = $pdo->prepare("
                        UPDATE interview_schedules 
                        SET interview_date = ?,
                            interview_time = ?,
                            interview_mode = ?,
                            interview_location = ?,
                            interview_link = ?,
                            admin_notes = ?,
                            status = 'admin_modified',
                            status_changed_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['interview_date'],
                        $_POST['interview_time'],
                        $_POST['interview_mode'],
                        $_POST['interview_mode'] === 'onsite' ? $_POST['interview_location'] : null,
                        $_POST['interview_mode'] === 'online' ? $_POST['interview_link'] : null,
                        $_POST['admin_notes'],
                        $schedule_id
                    ]);

                    // Get company user ID
                    $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ?");
                    $stmt->execute([$schedule['company_id']]);
                    $company = $stmt->fetch();

                    if ($company) {
                        $notification_result = createNotification(
                            $company['user_id'],
                            "Schedule Modified",
                            "schedule_modified",
                            "Interview schedule for {$schedule['job_title']} has been modified. Please review the changes.",
                            "view_schedule.php?id=" . $schedule_id
                        );

                        if (!$notification_result['success']) {
                            error_log("Failed to create company notification: " . $notification_result['message']);
                        }
                    }

                    $_SESSION['success'] = "Interview schedule modified and company notified.";
                    break;

                case 'cancel':
                    if (!isset($_POST['admin_notes']) || empty(trim($_POST['admin_notes']))) {
                        throw new Exception("Please provide a reason for cancellation.");
                    }

                    if (strlen($_POST['admin_notes']) > 1000) {
                        throw new Exception("Cancellation reason must be less than 1000 characters");
                    }

                    // Check if schedule is already cancelled
                    if ($schedule['status'] === 'cancelled') {
                        throw new Exception("This schedule is already cancelled.");
                    }

                    $stmt = $pdo->prepare("
                        UPDATE interview_schedules 
                        SET status = 'cancelled',
                            admin_notes = ?,
                            status_changed_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([trim($_POST['admin_notes']), $schedule_id]);

                    // Get company user ID
                    $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ?");
                    $stmt->execute([$schedule['company_id']]);
                    $company = $stmt->fetch();

                    if ($company) {
                        $notification_result = createNotification(
                            $company['user_id'],
                            "Schedule Cancelled",
                            "schedule_cancelled",
                            "Interview schedule for {$schedule['job_title']} has been cancelled.",
                            "view_schedule.php?id=" . $schedule_id
                        );

                        if (!$notification_result['success']) {
                            error_log("Failed to create company notification: " . $notification_result['message']);
                        }
                    }

                    // Notify applicant
                    $notification_result = createNotification(
                        $schedule['user_id'],
                        "Interview Cancelled",
                        "schedule_cancelled",
                        "Your interview for {$schedule['job_title']} has been cancelled.",
                        "view_schedule.php?id=" . $schedule_id
                    );

                    if (!$notification_result['success']) {
                        error_log("Failed to create applicant notification: " . $notification_result['message']);
                    }

                    $_SESSION['success'] = "Interview schedule cancelled and all parties notified.";
                    break;
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = $e->getMessage();
            error_log("Schedule management error: " . $e->getMessage());
        }
    }
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "
    SELECT isch.*, 
           u.name as applicant_name,
           jl.title as job_title,
           c.company_name
    FROM interview_schedules isch
    JOIN users u ON isch.user_id = u.id
    JOIN job_listings jl ON isch.job_id = jl.id
    JOIN companies c ON isch.company_id = c.id
    WHERE 1=1
";
$params = [];

if ($status !== '') {
    $query .= " AND isch.status = ?";
    $params[] = $status;
}

if ($date_from !== '') {
    $query .= " AND isch.interview_date >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $query .= " AND isch.interview_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY isch.interview_date ASC, isch.interview_time ASC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$schedules = $stmt->fetchAll();
?>

<style>
:root {
    --primary-color: #4B654F;
    --primary-dark: #3A463A;
    --primary-light: #E6EFE6;
    --accent-color: #E9F5E9;
    --text-primary: #333333;
    --text-muted: #6c757d;
    --border-radius: 15px;
    --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}
body {
    background: linear-gradient(135deg, #f5f7f5 0%, #e8f0e8 100%);
    color: var(--text-primary);
}
.card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 1.5rem;
}
.card-header {
    background: var(--primary-light);
    border-bottom: 1px solid #BFCABF;
    color: var(--primary-dark);
    font-weight: 600;
}
.card-title, .card-header h5, .card-header h4 {
    color: var(--primary-dark);
    font-weight: 700;
}
.btn-primary {
    background-color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: #fff !important;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
}
.btn-primary:hover, .btn-primary:focus {
    background-color: var(--primary-dark) !important;
    border-color: var(--primary-dark) !important;
    color: #fff !important;
    box-shadow: 0 4px 8px rgba(75, 101, 79, 0.15);
}
.btn-outline-primary {
    color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-outline-primary:hover, .btn-outline-primary:focus {
    background-color: var(--primary-color) !important;
    color: #fff !important;
    box-shadow: 0 4px 8px rgba(75, 101, 79, 0.15);
}
.btn-outline-danger {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-outline-danger:hover, .btn-outline-danger:focus {
    background-color: #dc3545 !important;
    color: #fff !important;
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.15);
}
.btn-outline-secondary {
    color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    background: transparent !important;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-outline-secondary:hover, .btn-outline-secondary:focus {
    background: var(--primary-color) !important;
    color: #fff !important;
    border-color: var(--primary-color) !important;
    box-shadow: 0 4px 8px rgba(75, 101, 79, 0.15);
}
.btn-secondary {
    background: #BFCABF !important;
    color: #3A463A !important;
    border: none;
}
.badge-primary {
    background: var(--primary-color) !important;
}
.badge-success {
    background: var(--primary-color) !important;
}
.badge-danger {
    background: #dc3545 !important;
}
.badge-warning {
    background: #ffc107 !important;
    color: #212529 !important;
}
.badge-secondary {
    background: #6c757d !important;
}
.badge {
    font-weight: 500;
    border-radius: 8px;
    padding: 0.35em 0.7em;
}
.table th {
    background: var(--primary-light);
    color: var(--primary-dark);
    font-weight: 600;
}
.table td, .table th {
    vertical-align: middle;
}
.form-label {
    color: var(--primary-dark);
    font-weight: 500;
}
.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.15);
}
.form-control, .form-select {
    border: 1px solid #BFCABF;
    color: var(--primary-dark);
}
.alert-success {
    background: var(--primary-light);
    color: var(--primary-dark);
    border: 1px solid #BFCABF;
}
.alert-danger {
    background: #fff0f0;
    color: var(--primary-dark);
    border: 1px solid #BFCABF;
}
/* Filter and Table Card Styles */
.filter-card, .table-card {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 6px 24px rgba(75, 101, 79, 0.08);
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    margin-bottom: 2rem;
}
.table-card {
    padding: 0.5rem 0.5rem 1rem 0.5rem;
}
.table thead th {
    background: var(--primary-light) !important;
    color: var(--primary-dark) !important;
    font-weight: 700;
}
.table {
    border-radius: 12px;
    overflow: hidden;
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0" style="font-size:2rem; font-weight:700; color:var(--text-primary);">Manage Interview Schedules</h2>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 bg-transparent shadow-none">
                <div class="filter-card">
                    <form method="GET" action="" class="mb-0">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="proposed" <?php echo $status === 'proposed' ? 'selected' : ''; ?>>Proposed</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="admin_modified" <?php echo $status === 'admin_modified' ? 'selected' : ''; ?>>Admin Modified</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">From</label>
                                <input type="date" class="form-control" name="date_from" 
                                       value="<?php echo htmlspecialchars($date_from); ?>"
                                       placeholder="From Date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To</label>
                                <input type="date" class="form-control" name="date_to" 
                                       value="<?php echo htmlspecialchars($date_to); ?>"
                                       placeholder="To Date">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Applicant</th>
                                    <th>Company</th>
                                    <th>Interview Mode</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $schedule): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($schedule['job_title']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['applicant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['company_name']); ?></td>
                                        <td><?php echo ucfirst($schedule['interview_mode']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($schedule['interview_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($schedule['interview_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['interview_location']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($schedule['status']) {
                                                    'proposed' => 'warning',
                                                    'pending' => 'info',
                                                    'approved' => 'success',
                                                    'admin_modified' => 'info',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($schedule['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewScheduleModal<?php echo $schedule['id']; ?>">
                                                    View
                                                </button>
                                                <?php if ($schedule['status'] === 'proposed'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#approveScheduleModal<?php echo $schedule['id']; ?>">
                                                        Approve
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modifyScheduleModal<?php echo $schedule['id']; ?>">
                                                        Modify
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#cancelScheduleModal<?php echo $schedule['id']; ?>">
                                                        Cancel
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- View Schedule Modal -->
                                    <div class="modal fade" id="viewScheduleModal<?php echo $schedule['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">View Schedule</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Applicant:</strong> <?php echo htmlspecialchars($schedule['applicant_name']); ?></p>
                                                    <p><strong>Job Title:</strong> <?php echo htmlspecialchars($schedule['job_title']); ?></p>
                                                    <p><strong>Company:</strong> <?php echo htmlspecialchars($schedule['company_name']); ?></p>
                                                    <p><strong>Interview Mode:</strong> <?php echo ucfirst($schedule['interview_mode']); ?></p>
                                                    <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($schedule['interview_date'])); ?></p>
                                                    <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($schedule['interview_time'])); ?></p>
                                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($schedule['interview_location']); ?></p>
                                                    <?php if ($schedule['interview_mode'] === 'online' && $schedule['interview_link']): ?>
                                                        <p><strong>Interview Link:</strong> <a href="<?php echo htmlspecialchars($schedule['interview_link']); ?>" target="_blank"><?php echo htmlspecialchars($schedule['interview_link']); ?></a></p>
                                                    <?php endif; ?>
                                                    <p><strong>Status:</strong> <?php echo ucfirst($schedule['status']); ?></p>
                                                    <?php if ($schedule['admin_notes']): ?>
                                                        <p><strong>Admin Notes:</strong> <?php echo nl2br(htmlspecialchars($schedule['admin_notes'])); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($schedule['company_notes']): ?>
                                                        <p><strong>Company Notes:</strong> <?php echo nl2br(htmlspecialchars($schedule['company_notes'])); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($schedule['user_notes']): ?>
                                                        <p><strong>User Notes:</strong> <?php echo nl2br(htmlspecialchars($schedule['user_notes'])); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Approve Schedule Modal -->
                                    <div class="modal fade" id="approveScheduleModal<?php echo $schedule['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Approve Schedule</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to approve this interview schedule?</p>
                                                        <p><strong>Applicant:</strong> <?php echo htmlspecialchars($schedule['applicant_name']); ?></p>
                                                        <p><strong>Job Title:</strong> <?php echo htmlspecialchars($schedule['job_title']); ?></p>
                                                        <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($schedule['interview_date'])); ?></p>
                                                        <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($schedule['interview_time'])); ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success">Approve</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modify Schedule Modal -->
                                    <div class="modal fade" id="modifyScheduleModal<?php echo $schedule['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Modify Schedule</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Interview Date</label>
                                                            <input type="date" class="form-control" name="interview_date" 
                                                                   value="<?php echo date('Y-m-d', strtotime($schedule['interview_date'])); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Interview Time</label>
                                                            <input type="time" class="form-control" name="interview_time" 
                                                                   value="<?php echo date('H:i', strtotime($schedule['interview_time'])); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Interview Mode</label>
                                                            <select class="form-select interview-mode" name="interview_mode" required 
                                                                    onchange="toggleInterviewFields(this, <?php echo $schedule['id']; ?>)">
                                                                <option value="online" <?php echo $schedule['interview_mode'] === 'online' ? 'selected' : ''; ?>>Online</option>
                                                                <option value="onsite" <?php echo $schedule['interview_mode'] === 'onsite' ? 'selected' : ''; ?>>Onsite</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3 location-field" id="location-field-<?php echo $schedule['id']; ?>" 
                                                             style="display: <?php echo $schedule['interview_mode'] === 'online' ? 'none' : 'block'; ?>">
                                                            <label class="form-label">Location</label>
                                                            <input type="text" class="form-control" name="interview_location" 
                                                                   value="<?php echo htmlspecialchars($schedule['interview_location']); ?>"
                                                                   <?php echo $schedule['interview_mode'] === 'online' ? '' : 'required'; ?>>
                                                        </div>
                                                        <div class="mb-3 link-field" id="link-field-<?php echo $schedule['id']; ?>"
                                                             style="display: <?php echo $schedule['interview_mode'] === 'onsite' ? 'none' : 'block'; ?>">
                                                            <label class="form-label">Interview Link</label>
                                                            <input type="url" class="form-control" name="interview_link" 
                                                                   value="<?php echo htmlspecialchars($schedule['interview_link']); ?>"
                                                                   placeholder="For online interviews"
                                                                   <?php echo $schedule['interview_mode'] === 'onsite' ? '' : 'required'; ?>>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Admin Notes</label>
                                                            <textarea class="form-control" name="admin_notes" rows="3" required
                                                                      placeholder="Explain the reason for modification"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <input type="hidden" name="action" value="modify">
                                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cancel Schedule Modal -->
                                    <div class="modal fade" id="cancelScheduleModal<?php echo $schedule['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Cancel Schedule</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to cancel this interview schedule?</p>
                                                        <p><strong>Applicant:</strong> <?php echo htmlspecialchars($schedule['applicant_name']); ?></p>
                                                        <p><strong>Job Title:</strong> <?php echo htmlspecialchars($schedule['job_title']); ?></p>
                                                        <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($schedule['interview_date'])); ?></p>
                                                        <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($schedule['interview_time'])); ?></p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Reason for Cancellation</label>
                                                            <textarea class="form-control" name="admin_notes" rows="3" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-danger">Cancel Schedule</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleInterviewFields(selectElement, scheduleId) {
    const locationField = document.getElementById('location-field-' + scheduleId);
    const linkField = document.getElementById('link-field-' + scheduleId);
    const locationInput = locationField.querySelector('input');
    const linkInput = linkField.querySelector('input');

    if (selectElement.value === 'online') {
        locationField.style.display = 'none';
        linkField.style.display = 'block';
        locationInput.removeAttribute('required');
        linkInput.setAttribute('required', 'required');
    } else {
        locationField.style.display = 'block';
        linkField.style.display = 'none';
        locationInput.setAttribute('required', 'required');
        linkInput.removeAttribute('required');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?> 