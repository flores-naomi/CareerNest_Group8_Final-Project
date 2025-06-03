<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';


// Check if user is logged in and has user role
if (!isLoggedIn() || getUserRole() !== 'user') {
    header('Location: login.php');
    exit();
}

// Get user details
$userId = getUserId();
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get user's applications
$applications = getUserApplications($userId);

// Get recent job listings
$stmt = $pdo->prepare("
    SELECT jl.*, c.company_name, c.location as company_location
    FROM job_listings jl
    JOIN companies c ON jl.company_id = c.id
    WHERE jl.status = 'approved'
    ORDER BY jl.date_posted DESC
    LIMIT 5
");
$stmt->execute();
$recentJobs = $stmt->fetchAll();

// Get unread notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    AND is_read = 0 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();
$unreadNotifications = count($notifications);

// Fetch approved interview schedules for the user
$stmt = $pdo->prepare("
    SELECT isch.*, jl.title as job_title, c.company_name
    FROM interview_schedules isch
    JOIN job_listings jl ON isch.job_id = jl.id
    JOIN companies c ON isch.company_id = c.id
    WHERE isch.user_id = ? AND isch.status = 'approved'
    ORDER BY isch.interview_date ASC, isch.interview_time ASC
");
$stmt->execute([$userId]);
$user_interview_schedules = $stmt->fetchAll();
require_once 'includes/header.php';
?>
<style>
    /* Custom Theme Colors */
    :root {
        --primary-color: #4B654F;
        --primary-dark: #3A463A;
        --primary-light: #D6EFD6;
        --accent-color: #E9F5E9;
        --text-primary: #333333;
        --text-muted: #6c757d;
        --light-gray: #f8f9fa;
        --border-radius: 15px;
        --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    /* Body Styles */
    body {
        background: linear-gradient(135deg, #f5f7f5 0%, #e8f0e8 100%);
        min-height: 100vh;
        color: var(--text-primary);
        font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
    }

    /* Container Padding */
    .container.py-4 {
        padding-top: 2rem !important;
        padding-bottom: 2rem !important;
    }

    /* Card Styles */
    .card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: all 0.3s ease;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .card:hover {
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        transform: translateY(-3px);
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

    .card-body {
        padding: 1.5rem;
    }

    /* Sidebar Styles */
    .list-unstyled li {
        margin-bottom: 0.75rem;
    }

    .list-unstyled li a {
        display: block;
        padding: 0.5rem 0;
        color: var(--text-primary);
        border-radius: 5px;
        transition: all 0.2s ease;
    }

    .list-unstyled li a:hover {
        color: var(--primary-color);
        background-color: var(--accent-color);
        padding-left: 0.5rem;
        text-decoration: none;
    }

    .list-unstyled li a i {
        width: 1.5rem;
        color: var(--primary-color);
        transition: transform 0.2s ease;
    }

    .list-unstyled li a:hover i {
        transform: translateX(3px);
    }

    /* Badge Styles */
    .badge {
        font-weight: 500;
        padding: 0.35em 0.65em;
        border-radius: 10px;
    }

    .badge.bg-danger {
        background-color: #dc3545 !important;
    }

    .badge.bg-warning {
        background-color: #ffc107 !important;
        color: #212529;
    }

    .badge.bg-success {
        background-color: #4B654F !important;
    }

    .badge.bg-secondary {
        background-color: #6c757d !important;
    }

    /* Button Styles */
    .btn {
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }

    .btn-primary:hover,
    .btn-primary:focus {
        background-color: var(--primary-dark) !important;
        border-color: var(--primary-dark) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .btn-outline-primary {
        color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }

    .btn-outline-primary:hover,
    .btn-outline-primary:focus {
        background-color: var(--primary-color) !important;
        color: white !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    /* Table Styles */
    .table {
        margin-bottom: 0;
    }

    .table th {
        border-top: none;
        background-color: var(--light-gray);
        color: var(--text-primary);
        font-weight: 600;
    }

    .table td,
    .table th {
        padding: 0.75rem 1rem;
        vertical-align: middle;
    }

    /* Form Control Styles */
    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color) !important;
        box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25) !important;
    }

    .form-control:hover,
    .form-select:hover {
        border-color: var(--primary-dark) !important;
    }

    .form-check-input:checked {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
    }

    /* List Group Styles */
    .list-group-item {
        border-left: none;
        border-right: none;
        border-top: none;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        padding: 1rem 1.25rem;
        transition: all 0.2s ease;
    }

    .list-group-item:last-child {
        border-bottom: none;
    }

    .list-group-item:hover {
        background-color: var(--accent-color);
    }

    .list-group-item-action {
        color: var(--text-primary);
    }

    .list-group-item-action:hover {
        color: var(--primary-color);
        background-color: var(--accent-color);
    }

    /* Notifications Styles */
    #notifications {
        transition: all 0.3s ease;
    }

    .form-check-input {
        margin-right: 0.5rem;
        cursor: pointer;
    }

    /* Hover & Focus Effects */
    a:hover,
    a:focus {
        color: var(--primary-dark);
        text-decoration: none;
    }

    a:focus-visible {
        outline: 3px solid rgba(75, 101, 79, 0.5);
        outline-offset: 2px;
    }

    .list-group-item-action:focus,
    .list-group-item-action:active {
        background-color: var(--accent-color);
        color: var(--primary-dark);
    }

    .btn:focus,
    .btn:active {
        box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25) !important;
    }

    /* Animation Effects */
    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.05);
        }

        100% {
            transform: scale(1);
        }
    }

    .btn:hover {
        animation: pulse 0.5s;
    }

    /* Recent Jobs Section */
    .list-group-item .d-flex .mb-1 {
        transition: color 0.2s ease;
    }

    .list-group-item:hover .d-flex .mb-1 {
        color: var(--primary-color);
    }

    .list-group-item small.text-muted {
        transition: color 0.2s ease;
    }

    .list-group-item:hover small.text-muted {
        color: var(--primary-dark) !important;
    }

    /* Interview Schedules Section */
    .table tbody tr {
        transition: all 0.2s ease;
    }

    .table tbody tr:hover {
        background-color: var(--accent-color);
        color: var(--primary-dark);
    }

    .table tbody tr td a {
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .table tbody tr:hover td a {
        color: var(--primary-dark);
        text-decoration: underline;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .card-body {
            padding: 1.25rem !important;
        }

        .container {
            max-width: 100%;
        }
    }

    @media (max-width: 576px) {
        .container {
            padding: 0 15px;
        }

        .card-body {
            padding: 1rem !important;
        }

        .card-title {
            font-size: 1.25rem;
        }

        .table th,
        .table td {
            padding: 0.5rem;
        }
    }
</style>
<div class="container py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h5>
                    <p class="card-text text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                    <hr>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <a href="user_profile.php" class="text-decoration-none d-flex align-items-center">
                                <i class="fas fa-user me-2"></i> <span>My Profile</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="search_jobs.php" class="text-decoration-none d-flex align-items-center">
                                <i class="fas fa-search me-2"></i> <span>Find Jobs</span>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#notifications" class="text-decoration-none d-flex align-items-center" data-bs-toggle="collapse">
                                <i class="fas fa-bell me-2"></i> <span>Notifications</span>
                                <?php if ($unreadNotifications > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $unreadNotifications; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Notifications Section -->
            <div id="notifications" class="card mb-4 collapse">
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
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <p class="text-muted p-3">No new notifications.</p>
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
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
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
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Applications Overview -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">My Applications</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($applications)): ?>
                        <p class="text-muted">You haven't applied for any jobs yet.</p>
                        <a href="search_jobs.php" class="btn btn-primary">Find Jobs</a>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Job Title</th>
                                        <th>Company</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $application): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($application['job_title']); ?></td>
                                            <td><?php echo htmlspecialchars($application['company_name']); ?></td>
                                            <td><?php echo formatDate($application['applied_at']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                echo $application['status'] === 'pending' ? 'warning' :
                                                    ($application['status'] === 'accepted' ? 'success' :
                                                        ($application['status'] === 'rejected' ? 'danger' : 'secondary'));
                                                ?>">
                                                    <?php echo ucfirst($application['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="job_details.php?id=<?php echo $application['job_id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">View Job</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Job Listings -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Job Listings</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentJobs)): ?>
                        <p class="text-muted">No recent job listings available.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($recentJobs as $job): ?>
                                <a href="job_details.php?id=<?php echo $job['id']; ?>"
                                    class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                        <small><?php echo getTimeAgo($job['date_posted']); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($job['company_location']); ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="search_jobs.php" class="btn btn-outline-primary">View All Jobs</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Interview Schedules Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">My Approved Interview Schedules</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($user_interview_schedules)): ?>
                        <p class="text-muted">You have no approved interview schedules.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Job Title</th>
                                        <th>Company</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Mode</th>
                                        <th>Location/Link</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_interview_schedules as $schedule): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($schedule['job_title']); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['company_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($schedule['interview_date'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($schedule['interview_time'])); ?></td>
                                            <td><?php echo ucfirst($schedule['interview_mode']); ?></td>
                                            <td>
                                                <?php if ($schedule['interview_mode'] === 'online' && $schedule['interview_link']): ?>
                                                    <a href="<?php echo htmlspecialchars($schedule['interview_link']); ?>"
                                                        target="_blank">Join Interview</a>
                                                <?php elseif ($schedule['interview_mode'] === 'onsite' && $schedule['interview_location']): ?>
                                                    <?php echo htmlspecialchars($schedule['interview_location']); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-success">Approved</span></td>
                                        </tr>
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

<?php require_once 'includes/footer.php'; ?>