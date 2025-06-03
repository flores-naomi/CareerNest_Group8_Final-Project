<?php
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'config/db.php';

// Check if company is logged in
if (!isLoggedIn() || getUserRole() !== 'company') {
    header('Location: login.php');
    exit();
}

// Get company information
$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$_SESSION['company_id']]);
$company = $stmt->fetch();

// Show verification status alert if not verified
if (!$company['is_verified']) {
    echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>Verification in Progress!</strong> Your company account is currently under review. You will be able to post jobs once verified by the admin.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}

// Get company's job listings
$stmt = $pdo->prepare("
    SELECT jl.*, 
           COUNT(ja.id) as application_count,
           CASE 
               WHEN jl.deadline < CURDATE() THEN 'expired'
               WHEN jl.status = 'approved' THEN 'active'
               WHEN jl.status = 'rejected' THEN 'rejected'
               ELSE jl.status
           END as display_status
    FROM job_listings jl 
    LEFT JOIN job_applications ja ON jl.id = ja.job_id 
    WHERE jl.company_id = ? 
    GROUP BY jl.id 
    ORDER BY jl.date_posted DESC
");
$stmt->execute([$_SESSION['company_id']]);
$job_listings = $stmt->fetchAll();

// Get recent applications
$stmt = $pdo->prepare("
    SELECT ja.*, 
           jl.title, 
           u.name as applicant_name,
           u.id as user_id,
           ja.id as application_id,
           ja.status as application_status,
           ja.applied_at,
           ja.resume_path,
           ja.cover_letter
    FROM job_applications ja 
    JOIN job_listings jl ON ja.job_id = jl.id 
    JOIN users u ON ja.user_id = u.id 
    WHERE jl.company_id = ? 
    ORDER BY ja.applied_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['company_id']]);
$recent_applications = $stmt->fetchAll();

// Get unread notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    AND is_read = 0 
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();
$unreadNotifications = count($notifications);

// Get interview schedules
$stmt = $pdo->prepare("
    SELECT 
        interview_schedules.*, 
        job_applications.id as application_id,
        job_listings.title as job_title,
        users.name as applicant_name,
        interview_schedules.status as status
    FROM interview_schedules
    JOIN job_applications ON interview_schedules.application_id = job_applications.id
    JOIN job_listings ON interview_schedules.job_id = job_listings.id
    JOIN users ON interview_schedules.user_id = users.id
    WHERE interview_schedules.company_id = ?
    ORDER BY interview_schedules.interview_date DESC, interview_schedules.interview_time DESC
");
$stmt->execute([$_SESSION['company_id']]);
$interview_schedules = $stmt->fetchAll();
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

    .card-title,
    .card-header h5,
    .card-header h4 {
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

    .btn-primary:hover,
    .btn-primary:focus {
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

    .btn-outline-primary:hover,
    .btn-outline-primary:focus {
        background-color: var(--primary-color) !important;
        color: #fff !important;
        box-shadow: 0 4px 8px rgba(75, 101, 79, 0.15);
    }

    .btn-secondary {
        background: #BFCABF !important;
        color: #3A463A !important;
        border: none;
    }

    .badge-success {
        background: var(--primary-color) !important;
    }

    .badge-danger {
        background: #dc3545 !important;
    }

    .badge-secondary {
        background: #6c757d !important;
    }

    .list-group-item {
        border: none;
        border-bottom: 1px solid #E6EFE6;
        background: #fff;
        transition: background 0.2s;
    }

    .list-group-item:last-child {
        border-bottom: none;
    }

    .list-group-item:hover {
        background: var(--accent-color);
    }

    .nav.flex-column .nav-link {
        color: var(--primary-dark);
        font-weight: 500;
        border-radius: 6px;
        margin-bottom: 0.5rem;
        transition: all 0.2s;
    }

    .nav.flex-column .nav-link.active,
    .nav.flex-column .nav-link:focus,
    .nav.flex-column .nav-link:hover {
        background: var(--primary-light);
        color: var(--primary-color);
        padding-left: 1rem;
        text-decoration: none;
    }

    .badge {
        font-weight: 500;
        border-radius: 8px;
        padding: 0.35em 0.7em;
    }
</style>

<div class="container py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($company['company_name']); ?></h5>
                    <hr>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="company_profile.php">
                                <i class="fas fa-building me-2"></i> Company Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#jobs">
                                <i class="fas fa-briefcase me-2"></i> Job Listings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#applications">
                                <i class="fas fa-file-alt me-2"></i> Applications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#notifications">
                                <i class="fas fa-bell me-2"></i> Notifications
                                <?php if (count($notifications) > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo count($notifications); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Job Listings Section -->
            <div id="jobs" class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Job Listings</h5>
                    <div>
                        <?php if ($company['is_verified']): ?>
                            <a href="post_job.php" class="btn btn-primary">Post New Job</a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled title="Company verification required">Post New
                                Job</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($job_listings)): ?>
                        <p class="text-muted">No job listings found.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($job_listings as $job): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                            <p class="mb-1"><?php echo htmlspecialchars($job['description']); ?></p>
                                            <small class="text-muted">
                                                Posted: <?php echo date('M d, Y', strtotime($job['date_posted'])); ?>
                                            </small>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-<?php
                                            echo match ($job['display_status']) {
                                                'active' => 'success',
                                                'expired' => 'danger',
                                                'rejected' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?> me-2">
                                                <?php echo ucfirst($job['display_status']); ?>
                                            </span>
                                            <a href="view_applications.php?job_id=<?php echo $job['id']; ?>"
                                                class="btn btn-sm btn-outline-primary">View Applications</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications Section -->
            <div id="notifications" class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Notifications</h5>
                    <div>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="badge bg-danger me-2"><?php echo $unreadNotifications; ?> new</span>
                        <?php endif; ?>
                        <?php if (!empty($notifications)): ?>
                            <form method="POST" action="mark_notifications_read.php" class="d-inline">
                                <input type="hidden" name="mark_all_read" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Mark All as Read</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <p class="text-muted">No notifications.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <div>
                                            <p class="mb-1 <?php echo $notification['is_read'] ? '' : 'fw-bold'; ?>">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="POST" action="mark_notifications_read.php" class="d-inline">
                                                <input type="hidden" name="notification_id"
                                                    value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Mark as Read</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Interview Schedules Section -->
            <div id="interviews" class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Interview Schedules</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($interview_schedules)): ?>
                        <p class="text-muted">No interview schedules found.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($interview_schedules as $schedule): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($schedule['job_title']); ?></h6>
                                            <p class="mb-1">Applicant: <?php echo htmlspecialchars($schedule['applicant_name']); ?></p>
                                            <p class="mb-0">
                                                Date: <?php echo date('F d, Y', strtotime($schedule['interview_date'])); ?><br>
                                                Time: <?php echo date('h:i A', strtotime($schedule['interview_time'])); ?><br>
                                                Mode: <?php echo ucfirst($schedule['interview_mode']); ?>
                                                <?php if ($schedule['interview_mode'] === 'online' && $schedule['interview_link']): ?>
                                                    <br>Link: <a href="<?php echo htmlspecialchars($schedule['interview_link']); ?>" target="_blank">Join Interview</a>
                                                <?php elseif ($schedule['interview_mode'] === 'onsite' && $schedule['interview_location']): ?>
                                                    <br>Location: <?php echo htmlspecialchars($schedule['interview_location']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <?php if ($schedule['company_notes']): ?>
                                                <p class="mb-0 mt-2"><small class="text-muted">Notes: <?php echo htmlspecialchars($schedule['company_notes']); ?></small></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php 
                                                echo match($schedule['status']) {
                                                    'approved' => 'success',
                                                    'pending' => 'warning',
                                                    'proposed' => 'warning',
                                                    'admin_modified' => 'info',
                                                    'finalized' => 'success',
                                                    'completed' => 'primary',
                                                    'cancelled' => 'danger',
                                                    'rejected' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?> mb-2">
                                                <?php echo ucfirst($schedule['status']); ?>
                                            </span>
                                            <?php if ($schedule['status'] === 'admin_modified'): ?>
                                                <form method="POST" action="company_approve_schedule.php" class="mt-2 d-inline">
                                                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">Approve Schedule</button>
                                                </form>
                                                <form method="POST" action="company_reject_schedule.php" class="mt-2 d-inline ms-2">
                                                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>