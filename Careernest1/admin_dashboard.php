<?php
require_once 'includes/session.php';
require_once 'config/db.php';

// Check if admin is logged in
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get statistics
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $totalUsers = $stmt->fetch()['count'];

    // Total companies
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies");
    $totalCompanies = $stmt->fetch()['count'];

    // Total jobs
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM job_listings");
    $totalJobs = $stmt->fetch()['count'];

    // Total applications
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM job_applications");
    $totalApplications = $stmt->fetch()['count'];

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

    // Recent job listings
    $stmt = $pdo->query("
        SELECT jl.*, c.company_name 
        FROM job_listings jl 
        JOIN companies c ON jl.company_id = c.id 
        ORDER BY 
            CASE jl.status 
                WHEN 'pending' THEN 1 
                WHEN 'rejected' THEN 2 
                ELSE 3 
            END,
            jl.date_posted DESC 
        LIMIT 5
    ");
    $recentJobs = $stmt->fetchAll();

    // Recent applications
    $stmt = $pdo->query("
        SELECT ja.*, jl.title, u.name as applicant_name, c.company_name 
        FROM job_applications ja 
        JOIN job_listings jl ON ja.job_id = jl.id 
        JOIN users u ON ja.user_id = u.id 
        JOIN companies c ON jl.company_id = c.id 
        ORDER BY ja.applied_at DESC 
        LIMIT 5
    ");
    $recentApplications = $stmt->fetchAll();

    // Get recent interview schedules
    $stmt = $pdo->prepare("
        SELECT 
            isch.*,
            jl.title AS job_title,
            u.name AS applicant_name,
            c.company_name
        FROM interview_schedules isch
        JOIN job_listings jl ON isch.job_id = jl.id
        JOIN users u ON isch.user_id = u.id
        JOIN companies c ON isch.company_id = c.id
        ORDER BY isch.interview_date DESC, isch.interview_time DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_interviews = $stmt->fetchAll();

} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database error: " . $e->getMessage());
    $error = "An error occurred while fetching dashboard data.";
}
require_once 'includes/header.php';
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
    --users-color: #4B654F;
    --companies-color: #3A463A;
    --jobs-color: #BFCABF;
    --applications-color: #FFD600;
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
.stats-card {
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 1.5rem;
    padding: 1.5rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-height: 120px;
}
.stats-card.users {
    background: var(--users-color);
    color: #fff;
}
.stats-card.companies {
    background: var(--companies-color);
    color: #fff;
}
.stats-card.jobs {
    background: var(--jobs-color);
    color: #3A463A;
}
.stats-card.applications {
    background: var(--applications-color);
    color: #3A463A;
}
.stats-card .card-title {
    font-size: 1.1rem;
    color: inherit;
    margin-bottom: 0.5rem;
}
.stats-card .card-text {
    font-size: 2.2rem;
    font-weight: 700;
    color: inherit;
}
@media (max-width: 767.98px) {
    .stats-card {
        min-height: 100px;
        padding: 1rem 0.75rem;
    }
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
.badge-info {
    background: #BFCABF !important;
    color: #3A463A !important;
}
.badge-secondary {
    background: #6c757d !important;
}
.badge {
    font-weight: 500;
    border-radius: 8px;
    padding: 0.35em 0.7em;
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
@media (max-width: 767.98px) {
    .row {
        flex-direction: column;
    }
    .stats-card {
        margin-bottom: 1rem;
    }
}
.card.recent-interviews {
    margin-top: 2.5rem;
}
@media (max-width: 767.98px) {
    .card.recent-interviews {
        margin-top: 1.5rem;
    }
}
.btn-outline-success {
    color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-outline-success:hover, .btn-outline-success:focus {
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
</style>

<div class="container py-4">
    <div class="row">
        <div class="col-12 mb-4">
            <h2>Admin Dashboard</h2>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Notifications Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
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
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4 g-3">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stats-card users">
                <div class="card-title">Total Users</div>
                <div class="card-text"><?php echo $totalUsers; ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stats-card companies">
                <div class="card-title">Total Companies</div>
                <div class="card-text"><?php echo $totalCompanies; ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stats-card jobs">
                <div class="card-title">Total Jobs</div>
                <div class="card-text"><?php echo $totalJobs; ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="stats-card applications">
                <div class="card-title">Total Applications</div>
                <div class="card-text"><?php echo $totalApplications; ?></div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row g-3">
        <!-- Recent Jobs -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Recent Job Listings</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentJobs as $job): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($job['title']); ?></td>
                                    <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                                    <td>
                                        <?php if ($job['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($job['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($job['status'] === 'pending'): ?>
                                            <form method="POST" action="manage_jobs.php" class="d-inline">
                                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-outline-success btn-sm">
                                                    Approve
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#rejectModal<?php echo $job['id']; ?>">
                                                Reject
                                            </button>
                                            
                                            <!-- Reject Modal -->
                                            <div class="modal fade" id="rejectModal<?php echo $job['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Reject Job Listing</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="manage_jobs.php">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <div class="mb-3">
                                                                    <label for="admin_notes" class="form-label">Reason for Rejection</label>
                                                                    <textarea class="form-control" name="admin_notes" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-outline-danger">Reject</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Applications -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Recent Applications</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentApplications)): ?>
                        <p class="text-muted">No recent applications.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($recentApplications as $application): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($application['title']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($application['applied_at'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1">
                                        <?php echo htmlspecialchars($application['applicant_name']); ?> - 
                                        <?php echo htmlspecialchars($application['company_name']); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Schedules -->
    <div class="row g-3">
        <div class="col-12 col-lg-6 mt-4">
            <div class="card h-100 recent-interviews">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Recent Interview Schedules</span>
                    <a href="manage_schedules.php" class="btn btn-primary btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <?php
                    if (empty($recent_interviews)): ?>
                        <p class="text-muted mb-0">No interview schedules found.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recent_interviews as $schedule): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($schedule['job_title']); ?></strong> <br>
                                        <?php echo htmlspecialchars($schedule['applicant_name']); ?> - <?php echo htmlspecialchars($schedule['company_name']); ?>
                                    </div>
                                    <span class="text-muted small">
                                        <?php echo date('M d, Y', strtotime($schedule['interview_date'])); ?>
                                        <?php echo date('h:i A', strtotime($schedule['interview_time'])); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 