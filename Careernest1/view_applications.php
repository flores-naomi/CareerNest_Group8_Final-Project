<?php 
require_once 'includes/session.php';
require_once 'config/db.php';

// Check if company is logged in
if (!isLoggedIn() || getUserRole() !== 'company') {
    header('Location: login.php');
    exit();
}

// Check if application ID or job ID is provided
if (!isset($_GET['id']) && !isset($_GET['job_id'])) {
    header('Location: company_dashboard.php');
    exit();
}

// Get the application details
if (isset($_GET['id'])) {
    // View single application
    $application_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("
        SELECT ja.*, u.name as applicant_name, u.email as applicant_email,
               up.phone as applicant_phone, up.location as applicant_location,
               jl.title as job_title, jl.id as job_id
        FROM job_applications ja 
        JOIN users u ON ja.user_id = u.id 
        JOIN user_profiles up ON u.id = up.user_id
        JOIN job_listings jl ON ja.job_id = jl.id
        WHERE ja.id = ? AND jl.company_id = ?
    ");
    $stmt->execute([$application_id, $_SESSION['company_id']]);
    $application = $stmt->fetch();

    if (!$application) {
        $_SESSION['error'] = "Application not found.";
        header('Location: company_dashboard.php');
        exit();
    }

    $job_id = $application['job_id'];
    $job_title = $application['job_title'];
    $applications = [$application];
} else {
    // View all applications for a job
    $job_id = (int)$_GET['job_id'];
    
    // Verify job belongs to company
    $stmt = $pdo->prepare("SELECT title FROM job_listings WHERE id = ? AND company_id = ?");
    $stmt->execute([$job_id, $_SESSION['company_id']]);
    $job = $stmt->fetch();

    if (!$job) {
        $_SESSION['error'] = "Job not found.";
        header('Location: company_dashboard.php');
        exit();
    }

    $job_title = $job['title'];

    // Get all applications for the job
    $stmt = $pdo->prepare("
        SELECT ja.*, 
               u.name as applicant_name, 
               u.email as applicant_email,
               up.phone as applicant_phone, 
               up.location as applicant_location,
               jl.title as job_title,
               jl.company_id,
               ja.cover_letter,
               ja.status,
               ja.feedback,
               ja.applied_at,
               ja.updated_at
        FROM job_applications ja 
        JOIN users u ON ja.user_id = u.id 
        LEFT JOIN user_profiles up ON u.id = up.user_id
        JOIN job_listings jl ON ja.job_id = jl.id
        WHERE ja.job_id = ? 
        ORDER BY ja.applied_at DESC
    ");
    $stmt->execute([$job_id]);
    $applications = $stmt->fetchAll();

    // Debug information
    error_log("Number of applications found: " . count($applications));
    error_log("Job ID: " . $job_id);
    if (empty($applications)) {
        error_log("No applications found for job ID: " . $job_id);
    }
}

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['status'])) {
    $application_id = (int)$_POST['application_id'];
    $status = $_POST['status'];
    $feedback = trim($_POST['feedback'] ?? '');

    // Validate status against allowed ENUM values
    $allowed_statuses = ['pending', 'reviewed', 'interview', 'rejected', 'accepted'];
    if (!in_array($status, $allowed_statuses)) {
        $_SESSION['error'] = "Invalid status selected.";
        header("Location: view_applications.php?id=" . $application_id);
        exit();
    }

    try {
        // First verify the application belongs to the company
        $stmt = $pdo->prepare("
            SELECT ja.*, jl.company_id 
            FROM job_applications ja 
            JOIN job_listings jl ON ja.job_id = jl.id 
            WHERE ja.id = ?
        ");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch();

        if (!$application || $application['company_id'] != $_SESSION['company_id']) {
            throw new Exception("Invalid application or unauthorized access.");
        }

        // Update the application status
        $stmt = $pdo->prepare("
            UPDATE job_applications 
            SET status = ?, feedback = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$status, $feedback, $application_id]);

        // Get application details for notification
        $stmt = $pdo->prepare("
            SELECT ja.*, u.name as applicant_name, jl.title as job_title, u.id as user_id
            FROM job_applications ja 
            JOIN users u ON ja.user_id = u.id 
            JOIN job_listings jl ON ja.job_id = jl.id 
            WHERE ja.id = ?
        ");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch();

        // Create notification for applicant
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id, title, type, message, link, is_read, created_at
            ) VALUES (?, ?, 'application_status', ?, ?, 0, NOW())
        ");
        $stmt->execute([
            $application['user_id'],
            'Application Status Updated',
            "Your application for {$application['job_title']} has been {$status}",
            "view_application.php?id=" . $application_id
        ]);

        $_SESSION['success'] = "Application status updated successfully.";
        
        // Redirect to schedule interview page if status is interview
        if ($status === 'interview') {
            header("Location: schedule_interview.php?application_id=" . $application_id);
            exit();
        }
        
        // If viewing single application, redirect back to that view
        if (isset($_GET['id'])) {
            header("Location: view_applications.php?id=" . $application_id);
        } else {
            header("Location: view_applications.php?job_id=" . $application['job_id']);
        }
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating application: " . $e->getMessage();
        // Redirect back to the same page
        if (isset($_GET['id'])) {
            header("Location: view_applications.php?id=" . $application_id);
        } else {
            header("Location: view_applications.php?job_id=" . $job_id);
        }
        exit();
    }
}
require_once 'includes/header.php';
require_once 'includes/navigation.php';
?>

<style>
    body {
        background: #F5F7F5;
    }
    .custom-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
        margin-top: 0.5rem;
    }
    .custom-page-title {
        font-size: 2rem;
        font-weight: 600;
        color: #222;
        margin-bottom: 0;
    }
    .custom-btn-outline {
        border: 1.5px solid #4B654F !important;
        color: #4B654F !important;
        border-radius: 8px !important;
        background: #fff !important;
        font-weight: 500;
        font-size: 1rem;
        padding: 4px 16px;
        transition: 0.2s;
    }
    .custom-btn-outline:hover {
        background: #E9F5E9 !important;
        color: #3A463A !important;
        border-color: #3A463A !important;
    }
    .custom-card {
        border-radius: 18px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        border: 1px solid #BFCABF;
        background: #fff;
    }
    .custom-table {
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 0;
    }
    .custom-table thead tr {
        background-color: #D6EFD6;
        color: #23422B;
        font-weight: 700;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }
    .custom-table th {
        font-weight: 700;
        border: none;
        color: #23422B;
        background: transparent;
    }
    .custom-table td {
        vertical-align: middle;
        border-top: 1px solid #E9F5E9;
        border-bottom: none;
    }
    .custom-table tr:last-child td {
        border-bottom: none;
    }
    .custom-badge {
        background: #4B654F !important;
        color: #fff !important;
        border-radius: 6px;
        font-weight: 400;
        font-size: 0.82em;
        padding: 0.18em 0.7em;
        letter-spacing: 0.01em;
    }
    .custom-link {
        color: #4B654F;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s, background 0.2s;
        border-radius: 4px;
        padding: 1px 3px;
    }
    .custom-link:hover, .custom-link:focus {
        color: #3A463A;
        background: #E9F5E9;
        text-decoration: none;
    }
    .custom-btn-outline.btn-sm {
        font-size: 0.85rem;
        padding: 2px 10px;
    }
</style>
<div class="container py-4">
    <div class="custom-header-row">
        <div class="custom-page-title">Applications for: <?php echo htmlspecialchars($job_title); ?></div>
        <a href="company_dashboard.php" class="btn custom-btn-outline btn-sm">Back to Dashboard</a>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card custom-card">
                <div class="card-body">
                    <?php if (empty($applications)): ?>
                        <div class="text-center py-4">
                            <h6 class="text-muted">No applications received yet.</h6>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table custom-table">
                                <thead>
                                    <tr>
                                        <th>Applicant</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $application): 
                                        // Check for existing interview schedule with proper status check
                                        $stmt = $pdo->prepare("
                                            SELECT * FROM interview_schedules 
                                            WHERE application_id = ? 
                                            AND status IN ('proposed', 'pending', 'approved', 'cancelled')
                                            ORDER BY interview_date DESC, interview_time DESC 
                                            LIMIT 1
                                        ");
                                        $stmt->execute([$application['id']]);
                                        $interview = $stmt->fetch();
                                        $interviewExists = $interview !== false;
                                        $interviewApproved = $interview && $interview['status'] === 'approved';
                                        $interviewPending = $interview && $interview['status'] === 'pending';
                                        $interviewProposed = $interview && $interview['status'] === 'proposed';
                                        $interviewCancelled = $interview && $interview['status'] === 'cancelled';
                                    ?>
                                        <tr>
                                            <td>
                                                <h6 class="mb-1">
                                                    <a href="#" class="custom-link"
                                                       data-bs-toggle="modal" 
                                                       data-bs-target="#profileModal<?php echo $application['user_id']; ?>">
                                                        <?php echo htmlspecialchars($application['applicant_name']); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($application['applicant_email']); ?><br>
                                                    <?php echo htmlspecialchars($application['applicant_location']); ?>
                                                </small>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($application['applied_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    echo $application['status'] === 'pending' ? 'warning' :
                                                        ($application['status'] === 'reviewed' ? 'info' :
                                                        ($application['status'] === 'interview' ? 'primary' :
                                                        ($application['status'] === 'rejected' ? 'danger' :
                                                        ($application['status'] === 'accepted' ? 'success' : 'secondary'))));
                                                ?>">
                                                    <?php echo ucfirst($application['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn custom-btn-outline btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#applicationModal<?php echo $application['id']; ?>">
                                                    View
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- Application Modal -->
                                        <div class="modal fade" id="applicationModal<?php echo $application['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Application Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row mb-4">
                                                            <div class="col-md-6">
                                                                <h6>Applicant Information</h6>
                                                                <p class="mb-1">
                                                                    <strong>Name:</strong> 
                                                                    <a href="#" class="custom-link"
                                                                       data-bs-toggle="modal" 
                                                                       data-bs-target="#profileModal<?php echo $application['user_id']; ?>">
                                                                        <?php echo htmlspecialchars($application['applicant_name']); ?>
                                                                    </a>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <strong>Email:</strong> <?php echo htmlspecialchars($application['applicant_email']); ?>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($application['applicant_phone']); ?>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <strong>Location:</strong> <?php echo htmlspecialchars($application['applicant_location']); ?>
                                                                </p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Application Details</h6>
                                                                <p class="mb-1">
                                                                    <strong>Applied:</strong> <?php echo date('M d, Y H:i', strtotime($application['applied_at'])); ?>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <strong>Status:</strong> 
                                                                    <span class="badge bg-<?php
                                                                        echo $application['status'] === 'pending' ? 'warning' :
                                                                            ($application['status'] === 'reviewed' ? 'info' :
                                                                            ($application['status'] === 'interview' ? 'primary' :
                                                                            ($application['status'] === 'rejected' ? 'danger' :
                                                                            ($application['status'] === 'accepted' ? 'success' : 'secondary'))));
                                                                    ?>">
                                                                        <?php echo ucfirst($application['status']); ?>
                                                                    </span>
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <div class="mb-4">
                                                            <h6>Cover Letter</h6>
                                                            <p><?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?></p>
                                                        </div>

                                                        <div class="mb-4">
                                                            <h6>Resume</h6>
                                                            <a href="<?php echo htmlspecialchars($application['resume_path']); ?>" 
                                                               class="btn custom-btn-outline btn-sm" target="_blank">
                                                                View Resume
                                                            </a>
                                                        </div>

                                                        <?php if (!empty($application['feedback'])): ?>
                                                            <div class="mb-4">
                                                                <h6>Feedback</h6>
                                                                <p><?php echo nl2br(htmlspecialchars($application['feedback'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if ($interviewApproved || $interviewCancelled): ?>
                                                            <div style="border:1px solid <?php echo $interviewCancelled ? '#f5c2c7' : '#b6d4fe'; ?>; background:<?php echo $interviewCancelled ? '#f8d7da' : '#e7f1ff'; ?>; color:#333; border-radius:6px; padding:16px; margin-bottom:24px;">
                                                                <strong style="color:<?php echo $interviewCancelled ? '#842029' : '#084298'; ?>;">
                                                                    <?php echo $interviewCancelled ? 'Interview Cancelled' : 'Interview Approved'; ?>
                                                                </strong>
                                                                <?php if ($interviewCancelled && !empty($interview['admin_notes'])): ?>
                                                                    <div class="mt-2">
                                                                        <small class="text-muted">
                                                                            Reason: <?php echo htmlspecialchars($interview['admin_notes']); ?>
                                                                        </small>
                                                                    </div>
                                                                <?php elseif (!$interviewCancelled): ?>
                                                                    <div class="mt-2">
                                                                        <small class="text-muted">
                                                                            Date: <?php echo date('M d, Y', strtotime($interview['interview_date'])); ?><br>
                                                                            Time: <?php echo date('h:i A', strtotime($interview['interview_time'])); ?><br>
                                                                            Mode: <?php echo ucfirst($interview['interview_mode']); ?>
                                                                            <?php if ($interview['interview_mode'] === 'online' && $interview['interview_link']): ?>
                                                                                <br><a href="<?php echo htmlspecialchars($interview['interview_link']); ?>" target="_blank">Join Interview</a>
                                                                            <?php endif; ?>
                                                                            <?php if ($interview['interview_mode'] === 'onsite' && $interview['interview_location']): ?>
                                                                                <br>Location: <?php echo htmlspecialchars($interview['interview_location']); ?>
                                                                            <?php endif; ?>
                                                                        </small>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <form method="POST" action="">
                                                            <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label for="status<?php echo $application['id']; ?>" class="form-label">Update Status</label>
                                                                <select class="form-select" id="status<?php echo $application['id']; ?>" name="status" required <?php if ($interviewApproved || $interviewCancelled) echo 'disabled'; ?>>
                                                                    <option value="pending" <?php echo $application['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="reviewed" <?php echo $application['status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                                                    <option value="interview" <?php echo $application['status'] === 'interview' ? 'selected' : ''; ?>>Interview</option>
                                                                    <option value="accepted" <?php echo $application['status'] === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                                                    <option value="rejected" <?php echo $application['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                                </select>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="feedback<?php echo $application['id']; ?>" class="form-label">Feedback (Optional)</label>
                                                                <textarea class="form-control" id="feedback<?php echo $application['id']; ?>" 
                                                                          name="feedback" rows="3" <?php if ($interviewApproved || $interviewCancelled) echo 'disabled'; ?>><?php echo htmlspecialchars($application['feedback']); ?></textarea>
                                                            </div>

                                                            <div class="d-grid gap-2">
                                                                <button type="submit" class="btn custom-btn-outline btn-sm">Update Application</button>
                                                                <?php if ($application['status'] === 'interview' && !$interviewExists): ?>
                                                                    <a href="schedule_interview.php?application_id=<?php echo $application['id']; ?>" class="btn custom-btn-outline btn-sm">
                                                                        Schedule Interview
                                                                    </a>
                                                                <?php endif; ?>
                                                                <?php if ($interviewExists): ?>
                                                                    <div class="mt-2">
                                                                        <?php if ($interviewProposed): ?>
                                                                            <span class="badge custom-badge">Interview Proposed</span>
                                                                        <?php elseif ($interviewPending): ?>
                                                                            <span class="badge custom-badge">Interview Pending</span>
                                                                        <?php else: ?>
                                                                            <span class="badge custom-badge">Interview Scheduled</span>
                                                                        <?php endif; ?>
                                                                        <?php if ($interview): ?>
                                                                            <div class="mt-2">
                                                                                <small class="text-muted">
                                                                                    Date: <?php echo date('M d, Y', strtotime($interview['interview_date'])); ?><br>
                                                                                    Time: <?php echo date('h:i A', strtotime($interview['interview_time'])); ?><br>
                                                                                    Mode: <?php echo ucfirst($interview['interview_mode']); ?>
                                                                                    <?php if ($interview['interview_mode'] === 'online' && $interview['interview_link']): ?>
                                                                                        <br><a href="<?php echo htmlspecialchars($interview['interview_link']); ?>" target="_blank">Join Interview</a>
                                                                                    <?php endif; ?>
                                                                                    <?php if ($interview['interview_mode'] === 'onsite' && $interview['interview_location']): ?>
                                                                                        <br>Location: <?php echo htmlspecialchars($interview['interview_location']); ?>
                                                                                    <?php endif; ?>
                                                                                </small>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Modals -->
<?php foreach ($applications as $application): 
    // Get detailed profile information
    $stmt = $pdo->prepare("
        SELECT u.*, up.*
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$application['user_id']]);
    $profile = $stmt->fetch();
?>
    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal<?php echo $application['user_id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Applicant Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Personal Information</h6>
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($profile['name']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($profile['email']); ?></p>
                            <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($profile['phone'] ?? 'Not provided'); ?></p>
                            <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($profile['location'] ?? 'Not provided'); ?></p>
                            <p class="mb-1"><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($profile['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Contact Information</h6>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($profile['email']); ?></p>
                            <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($profile['phone'] ?? 'Not provided'); ?></p>
                            <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($profile['location'] ?? 'Not provided'); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($profile['bio'])): ?>
                        <div class="mb-4">
                            <h6>Bio</h6>
                            <p><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($profile['skills'])): ?>
                        <div class="mb-4">
                            <h6>Skills</h6>
                            <p><?php echo htmlspecialchars($profile['skills']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($profile['education'])): ?>
                        <div class="mb-4">
                            <h6>Education</h6>
                            <p><?php echo nl2br(htmlspecialchars($profile['education'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($profile['experience'])): ?>
                        <div class="mb-4">
                            <h6>Work Experience</h6>
                            <p><?php echo nl2br(htmlspecialchars($profile['experience'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($profile['resume_path'])): ?>
                        <div class="mb-4">
                            <h6>Resume</h6>
                            <a href="<?php echo htmlspecialchars($profile['resume_path']); ?>" 
                               class="btn custom-btn-outline btn-sm" target="_blank">
                                View Resume
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn custom-btn-outline" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once 'includes/footer.php'; ?> 