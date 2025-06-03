<?php
require_once 'includes/session.php';
require_once 'config/db.php';

// Check if admin is logged in
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle job actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['job_id'])) {
    $job_id = $_POST['job_id'];
    $action = $_POST['action'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Verify job exists and get current status
        $stmt = $pdo->prepare("
            SELECT jl.*, c.id as company_id, c.user_id, c.company_name
            FROM job_listings jl 
            JOIN companies c ON jl.company_id = c.id 
            WHERE jl.id = ?
        ");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch();

        if (!$job) {
            throw new Exception("Job listing not found.");
        }

        if ($action === 'approve') {
            // Check if job is already approved
            if ($job['status'] === 'approved') {
                throw new Exception("This job is already approved.");
            }

            // Update job status to approved
            $stmt = $pdo->prepare("
                UPDATE job_listings 
                SET status = 'approved', 
                    approved_at = NOW(),
                    admin_notes = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$job_id]);

            // Create notification for company
            $notification_result = createNotification(
                $job['user_id'],
                "Job Approved",
                "job_approved",
                "Your job listing '{$job['title']}' has been approved and is now visible to job seekers.",
                "company_dashboard.php"
            );

            if (!$notification_result['success']) {
                error_log("Failed to create company notification: " . $notification_result['message']);
            }

            $_SESSION['success'] = "Job has been approved successfully.";
        } 
        elseif ($action === 'reject') {
            // Validate rejection reason
            if (!isset($_POST['admin_notes']) || empty(trim($_POST['admin_notes']))) {
                throw new Exception("Please provide a reason for rejection.");
            }

            // Check if job is already rejected
            if ($job['status'] === 'rejected') {
                throw new Exception("This job is already rejected.");
            }

            // Update job status to rejected and add admin notes
            $stmt = $pdo->prepare("
                UPDATE job_listings 
                SET status = 'rejected', 
                    admin_notes = ?,
                    rejected_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([trim($_POST['admin_notes']), $job_id]);

            // Create notification for company
            $notification_result = createNotification(
                $job['user_id'],
                "Job Rejected",
                "job_rejected",
                "Your job listing '{$job['title']}' has been rejected. Reason: " . trim($_POST['admin_notes']),
                "company_dashboard.php"
            );

            if (!$notification_result['success']) {
                error_log("Failed to create company notification: " . $notification_result['message']);
            }

            $_SESSION['success'] = "Job has been rejected successfully.";
        }
        elseif ($action === 'edit') {
            // Validate input
            $errors = [];
            if (empty($_POST['title'])) $errors[] = "Job title is required";
            if (empty($_POST['description'])) $errors[] = "Job description is required";
            if (empty($_POST['requirements'])) $errors[] = "Job requirements are required";
            if (empty($_POST['location'])) $errors[] = "Job location is required";
            if ($_POST['salary_min'] > $_POST['salary_max']) $errors[] = "Minimum salary cannot be greater than maximum salary";
            if (empty($_POST['deadline'])) $errors[] = "Application deadline is required";
            if (strtotime($_POST['deadline']) < time()) $errors[] = "Deadline must be a future date";
            if (strlen($_POST['title']) > 100) $errors[] = "Job title must be less than 100 characters";
            if (strlen($_POST['description']) > 5000) $errors[] = "Job description must be less than 5000 characters";
            if (strlen($_POST['requirements']) > 2000) $errors[] = "Job requirements must be less than 2000 characters";

            if (!empty($errors)) {
                throw new Exception(implode("<br>", $errors));
            }

            // Check for duplicate job title for the same company
            $stmt = $pdo->prepare("
                SELECT id FROM job_listings 
                WHERE company_id = ? 
                AND title = ? 
                AND id != ?
                AND date_posted > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$job['company_id'], $_POST['title'], $job_id]);
            if ($stmt->fetch()) {
                throw new Exception("A similar job posting was created in the last 24 hours. Please use a different title.");
            }

            // Update job listing
            $stmt = $pdo->prepare("
                UPDATE job_listings 
                SET title = ?, 
                    description = ?, 
                    requirements = ?, 
                    location = ?, 
                    work_type = ?, 
                    work_arrangement = ?,
                    salary_min = ?, 
                    salary_max = ?, 
                    experience_level = ?, 
                    education_level = ?, 
                    skills = ?, 
                    deadline = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $_POST['title'],
                $_POST['description'],
                $_POST['requirements'],
                $_POST['location'],
                $_POST['work_type'],
                $_POST['work_arrangement'],
                $_POST['salary_min'],
                $_POST['salary_max'],
                $_POST['experience_level'],
                $_POST['education_level'],
                $_POST['skills'],
                $_POST['deadline'],
                $job_id
            ]);

            // Create notification for company
            $notification_result = createNotification(
                $job['user_id'],
                "Job Updated",
                "job_edited",
                "Your job listing '{$job['title']}' has been updated by an administrator.",
                "company_dashboard.php"
            );

            if (!$notification_result['success']) {
                error_log("Failed to create company notification: " . $notification_result['message']);
            }

            $_SESSION['success'] = "Job listing has been updated successfully.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
    
    // Redirect back to prevent form resubmission
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$query = "
    SELECT jl.*, c.company_name, 
           COUNT(ja.id) as application_count
    FROM job_listings jl 
    JOIN companies c ON jl.company_id = c.id 
    LEFT JOIN job_applications ja ON jl.id = ja.job_id
    WHERE 1=1
";
$params = [];

if (!empty($status)) {
    $query .= " AND jl.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $query .= " AND (jl.title LIKE ? OR c.company_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

$query .= " GROUP BY jl.id ORDER BY 
    CASE jl.status 
        WHEN 'pending' THEN 1 
        WHEN 'rejected' THEN 2 
        ELSE 3 
    END,
    jl.date_posted DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll();
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
.btn-info, .btn-outline-primary {
    color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    background: transparent !important;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-info:hover, .btn-info:focus, .btn-outline-primary:hover, .btn-outline-primary:focus {
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
.table th, .table td {
    padding: 0.35rem 0.5rem !important;
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
.table thead th:first-child {
    border-top-left-radius: 12px;
}
.table thead th:last-child {
    border-top-right-radius: 12px;
}
.btn-outline-success{
    border-radius: 0 !important;
}
</style>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-12 d-flex justify-content-between align-items-center">
            <h2>Manage Job Listings</h2>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search by job title or company name">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Job Listings -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($jobs)): ?>
                <p class="text-muted">No jobs found matching your criteria.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Company</th>
                                <th>Posted Date</th>
                                <th>Applications</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($job['title']); ?></td>
                                    <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($job['date_posted'])); ?></td>
                                    <td><?php echo $job['application_count']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $job['status'] === 'approved' ? 'success' : 
                                                ($job['status'] === 'rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst($job['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewJobModal<?php echo $job['id']; ?>">
                                                View
                                            </button>
                                        <?php if ($job['status'] === 'pending'): ?>
                                                    <div class="btn-group">
                                                     <form method="POST" class="d-inline">
                                                         <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                         <input type="hidden" name="action" value="approve">
                                                         <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                                                    </form>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#rejectJobModal<?php echo $job['id']; ?>">
                                                                    Reject
                                                        </button>
                                                    </div>
                                        <?php endif; ?>
                                        </div>

                                        <!-- View Job Modal -->
                                        <div class="modal fade" id="viewJobModal<?php echo $job['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><?php echo htmlspecialchars($job['title']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <h6>Company: <?php echo htmlspecialchars($job['company_name']); ?></h6>
                                                        <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                                                        <p><strong>Type:</strong> <?php echo ucfirst($job['work_type']); ?></p>
                                                        <p><strong>Experience Level:</strong> <?php echo ucfirst($job['experience_level']); ?></p>
                                                        <p><strong>Education Level:</strong> <?php echo ucfirst($job['education_level']); ?></p>
                                                        <p><strong>Salary:</strong> $<?php echo number_format($job['salary_min']); ?> - $<?php echo number_format($job['salary_max']); ?></p>
                                                        <p><strong>Description:</strong></p>
                                                        <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                                                        <p><strong>Requirements:</strong></p>
                                                        <p><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></p>
                                                        <?php if ($job['status'] === 'rejected' && !empty($job['admin_notes'])): ?>
                                                            <p><strong>Rejection Reason:</strong></p>
                                                            <p class="text-danger"><?php echo nl2br(htmlspecialchars($job['admin_notes'])); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="button" class="btn btn-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editJobModal<?php echo $job['id']; ?>">
                                                            Edit
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Edit Job Modal -->
                                        <div class="modal fade" id="editJobModal<?php echo $job['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Job Listing</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="edit">
                                                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label for="title" class="form-label">Job Title</label>
                                                                <input type="text" class="form-control" id="title" name="title" 
                                                                       value="<?php echo htmlspecialchars($job['title']); ?>" required>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="description" class="form-label">Description</label>
                                                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="requirements" class="form-label">Requirements</label>
                                                                <textarea class="form-control" id="requirements" name="requirements" rows="4" required><?php echo htmlspecialchars($job['requirements']); ?></textarea>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="location" class="form-label">Location</label>
                                                                <input type="text" class="form-control" id="location" name="location" 
                                                                       value="<?php echo htmlspecialchars($job['location']); ?>" required>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label for="work_type" class="form-label">Job Type</label>
                                                                    <select class="form-select" id="work_type" name="work_type" required>
                                                                        <option value="full-time" <?php echo $job['work_type'] === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                                                                        <option value="part-time" <?php echo $job['work_type'] === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                                                                        <option value="contract" <?php echo $job['work_type'] === 'contract' ? 'selected' : ''; ?>>Contract</option>
                                                                        <option value="internship" <?php echo $job['work_type'] === 'internship' ? 'selected' : ''; ?>>Internship</option>
                                                                    </select>
                                                                </div>

                                                                <div class="col-md-6 mb-3">
                                                                    <label for="work_arrangement" class="form-label">Work Arrangement</label>
                                                                    <select class="form-select" id="work_arrangement" name="work_arrangement" required>
                                                                        <option value="remote" <?php echo $job['work_arrangement'] === 'remote' ? 'selected' : ''; ?>>Remote</option>
                                                                        <option value="onsite" <?php echo $job['work_arrangement'] === 'onsite' ? 'selected' : ''; ?>>On-site</option>
                                                                        <option value="hybrid" <?php echo $job['work_arrangement'] === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                                                        <option value="WFH" <?php echo $job['work_arrangement'] === 'WFH' ? 'selected' : ''; ?>>Work from Home</option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label for="salary_min" class="form-label">Minimum Salary</label>
                                                                    <input type="number" class="form-control" id="salary_min" name="salary_min" 
                                                                           value="<?php echo $job['salary_min']; ?>" required>
                                                                </div>

                                                                <div class="col-md-6 mb-3">
                                                                    <label for="salary_max" class="form-label">Maximum Salary</label>
                                                                    <input type="number" class="form-control" id="salary_max" name="salary_max" 
                                                                           value="<?php echo $job['salary_max']; ?>" required>
                                                                </div>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="experience_level" class="form-label">Experience Level</label>
                                                                <select class="form-select" id="experience_level" name="experience_level" required>
                                                                    <option value="entry level" <?php echo $job['experience_level'] === 'entry level' ? 'selected' : ''; ?>>Entry Level</option>
                                                                    <option value="mid level" <?php echo $job['experience_level'] === 'mid level' ? 'selected' : ''; ?>>Mid Level</option>
                                                                    <option value="senior level" <?php echo $job['experience_level'] === 'senior level' ? 'selected' : ''; ?>>Senior Level</option>
                                                                </select>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="education_level" class="form-label">Education Level</label>
                                                                <select class="form-select" id="education_level" name="education_level" required>
                                                                    <option value="high school" <?php echo $job['education_level'] === 'high school' ? 'selected' : ''; ?>>High School</option>
                                                                    <option value="associate degree" <?php echo $job['education_level'] === 'associate degree' ? 'selected' : ''; ?>>Associate Degree</option>
                                                                    <option value="bachelors degree" <?php echo $job['education_level'] === 'bachelors degree' ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                                                    <option value="masters degree" <?php echo $job['education_level'] === 'masters degree' ? 'selected' : ''; ?>>Master's Degree</option>
                                                                    <option value="phd" <?php echo $job['education_level'] === 'phd' ? 'selected' : ''; ?>>PhD</option>
                                                                </select>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="skills" class="form-label">Required Skills (comma-separated)</label>
                                                                <input type="text" class="form-control" id="skills" name="skills" 
                                                                       value="<?php echo htmlspecialchars($job['skills']); ?>" required>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="deadline" class="form-label">Application Deadline</label>
                                                                <input type="date" class="form-control" id="deadline" name="deadline" 
                                                                       value="<?php echo $job['deadline']; ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reject Job Modal -->
                                        <div class="modal fade" id="rejectJobModal<?php echo $job['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Job Listing</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="admin_notes" class="form-label">Reason for Rejection</label>
                                                                <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" required></textarea>
                                                            </div>
                                                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Reject</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 