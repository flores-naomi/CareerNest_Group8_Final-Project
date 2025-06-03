<?php
//job_details.php
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'config/db.php';

// Check if job ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$job_id = (int) $_GET['id'];

// Get job details
$stmt = $pdo->prepare("
    SELECT jl.*, c.company_name, c.location as company_location,
           CASE 
               WHEN jl.deadline < CURDATE() THEN 'expired'
               WHEN jl.status = 'approved' THEN 'active'
               WHEN jl.status = 'rejected' THEN 'rejected'
               ELSE jl.status
           END as display_status
    FROM job_listings jl 
    JOIN companies c ON jl.company_id = c.id 
    WHERE jl.id = ?
");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    header('Location: search_jobs.php');
    exit();
}

// Check if user has already applied
$has_applied = false;
if (isLoggedIn() && getUserRole() === 'user') {
    $stmt = $pdo->prepare("SELECT id FROM job_applications WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$_SESSION['user_id'], $job_id]);
    $has_applied = (bool) $stmt->fetch();
}

// Get similar jobs
$stmt = $pdo->prepare("
    SELECT jl.*, c.company_name 
    FROM job_listings jl 
    JOIN companies c ON jl.company_id = c.id 
    WHERE jl.id != ? 
    AND jl.status = 'approved' 
    AND jl.deadline > CURDATE()
    AND (
        jl.work_type = ? 
        OR jl.experience_level = ? 
        OR jl.location = ?
    )
    LIMIT 3
");
$stmt->execute([
    $job_id,
    $job['work_type'],
    $job['experience_level'],
    $job['location']
]);
$similar_jobs = $stmt->fetchAll();
?>

<style>
:root {
    --primary-color: #4B654F;
    --primary-dark: #3A463A;
    --primary-light: #D6EFD6;
    --accent-color: #E9F5E9;
    --border-color: #BFCABF;
    --text-primary: #333333;
    --text-muted: #6c757d;
}
body {
    background: linear-gradient(135deg, #f5f7f5 0%, #e8f0e8 100%);
    color: var(--text-primary);
}
.card-custom {
    border-radius: 18px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: 1px solid var(--border-color);
    background: #fff;
}
.text-primary-custom {
    color: var(--primary-color) !important;
}
.text-secondary-custom {
    color: var(--primary-dark) !important;
}
.badge-success-custom {
    background: var(--primary-color) !important;
    color: #fff !important;
}
.badge-secondary-custom {
    background: var(--primary-light) !important;
    color: var(--primary-dark) !important;
}
.badge-warning-custom {
    background: #FFD600 !important;
    color: #3A463A !important;
}
.badge-danger-custom {
    background: #dc3545 !important;
    color: #fff !important;
}
.badge-info-custom {
    background: #BFCABF !important;
    color: #3A463A !important;
}
.btn-primary-custom {
    background-color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: #fff !important;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
}
.btn-primary-custom:hover, .btn-primary-custom:focus {
    background-color: var(--primary-dark) !important;
    border-color: var(--primary-dark) !important;
    color: #fff !important;
    box-shadow: 0 4px 8px rgba(75, 101, 79, 0.15);
}
.btn-outline-custom {
    color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-outline-custom:hover, .btn-outline-custom:focus {
    background-color: var(--primary-color) !important;
    color: #fff !important;
    box-shadow: 0 4px 8px rgba(75, 101, 79, 0.15);
}
.alert-custom {
    background: var(--accent-color) !important;
    color: var(--primary-dark) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px;
}

/* Enhanced Form Elements */
.form-control:focus, .form-select:focus {
    border-color: #4B654F !important;
    box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25) !important;
}

.form-control:hover, .form-select:hover {
    border-color: #3A463A !important;
}

/* File Input Specific Styling */
input[type="file"].form-control:hover {
    background-color: #f8f9fa;
    border-color: #3A463A !important;
}

input[type="file"].form-control:focus {
    border-color: #4B654F !important;
    box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25) !important;
}

/* Button Focus State */
.btn-primary-custom:focus, .btn-outline-custom:focus {
    box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.4) !important;
}

/* Button Hover Animation */
.btn-primary-custom:hover, .btn-outline-custom:hover {
    animation: pulse 0.5s;
}

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

/* Modal Content Hover Effects */
.modal-content {
    transition: all 0.3s ease;
}

.modal-content:hover {
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15) !important;
}

/* Modal Button Focus States */
.modal .btn-close:focus {
    box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25) !important;
}

/* Similar Jobs Link Hover */
.card-body a.text-primary-custom {
    position: relative;
    transition: all 0.3s ease;
}

.card-body a.text-primary-custom:hover {
    color: #3A463A !important;
    text-decoration: underline !important;
}

.card-body a.text-primary-custom:before {
    content: "";
    position: absolute;
    width: 0;
    height: 2px;
    bottom: -2px;
    left: 0;
    background-color: #3A463A;
    visibility: hidden;
    transition: all 0.3s ease-in-out;
}

.card-body a.text-primary-custom:hover:before {
    visibility: visible;
    width: 100%;
}

/* Card Hover Effects */
.card-custom:hover {
    box-shadow: 0 8px 16px rgba(0,0,0,0.1) !important;
    transform: translateY(-3px);
}

/* Badge Hover Effects */
.badge {
    transition: all 0.2s ease;
}

.badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Similar Jobs Section Hover */
.border-bottom:hover {
    background-color: rgba(75, 101, 79, 0.05);
    border-radius: 6px;
    padding-left: 8px;
    margin-left: -8px;
    transition: all 0.3s ease;
}

/* Alert Links */
.alert-link:hover {
    color: #3A463A !important;
    text-decoration: underline !important;
}

/* Apply Now Button Special Effect */
button[data-bs-toggle="modal"] {
    position: relative;
    overflow: hidden;
}

button[data-bs-toggle="modal"]:after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    opacity: 0;
}

button[data-bs-toggle="modal"]:focus:after {
    animation: ripple 0.6s ease-out;
}

@keyframes ripple {
    0% {
        width: 0;
        height: 0;
        opacity: 0.5;
    }
    100% {
        width: 500px;
        height: 500px;
        opacity: 0;
    }
}

/* Modal Input Focus Animation */
.modal-body .form-control:focus,
.modal-body .form-select:focus {
    transform: translateY(-2px);
    transition: transform 0.3s ease;
}

/* Accessibility Focus Styles */
a:focus-visible, button:focus-visible, input:focus-visible, 
textarea:focus-visible, select:focus-visible {
    outline: 3px solid rgba(75, 101, 79, 0.5);
    outline-offset: 2px;
}

/* Job Status Badges */
.badge-success-custom:hover {
    background: #D6EFD6 !important;
}

.badge-secondary-custom:hover {
    background: #D6EFD6 !important;
}

.badge-warning-custom:hover {
    background: #FFEFCC !important;
}

.badge-danger-custom:hover {
    background: #F9D6D6 !important;
}

.badge-info-custom:hover {
    background: #D6E6F9 !important;
}
</style>

<div class="container py-4">
    <div class="row">
        <!-- Main Content -->
        <div class="col-md-8">
            <div class="card mb-4 border-0 shadow-sm card-custom">
                <div class="card-body">
                    <h5 class="card-title text-primary-custom"><?php echo htmlspecialchars($job['title']); ?></h5>
                    <h6 class="card-subtitle mb-3 text-secondary-custom"><?php echo htmlspecialchars($job['company_name']); ?></h6>

                    <div class="mb-3">
                        <?php if ($job['display_status'] === 'active'): ?>
                            <span class="badge badge-success-custom">Accepting Applications</span>
                        <?php elseif ($job['display_status'] === 'expired'): ?>
                            <span class="badge badge-secondary-custom">Applications Closed</span>
                        <?php elseif ($job['display_status'] === 'rejected'): ?>
                            <span class="badge badge-danger-custom">Not Available</span>
                        <?php else: ?>
                            <span class="badge badge-warning-custom">Pending Approval</span>
                        <?php endif; ?>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-2"><i class="fas fa-map-marker-alt text-primary-custom me-2"></i> <?php echo htmlspecialchars($job['location']); ?></p>
                            <p class="mb-2"><i class="fas fa-briefcase text-primary-custom me-2"></i> <?php echo ucfirst($job['work_type']); ?></p>
                            <p class="mb-2"><i class="fas fa-clock text-primary-custom me-2"></i> <?php echo ucfirst($job['work_arrangement']); ?></p>
                            <p class="mb-2"><i class="fas fa-user-tie text-primary-custom me-2"></i> <?php echo ucfirst($job['experience_level']); ?></p>
                            <p class="mb-2"><i class="fas fa-graduation-cap text-primary-custom me-2"></i> <?php echo ucfirst($job['education_level']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><i class="fas fa-money-bill-wave text-primary-custom me-2"></i> $<?php echo number_format($job['salary_min']); ?> - $<?php echo number_format($job['salary_max']); ?></p>
                            <p class="mb-2"><i class="fas fa-calendar-alt text-primary-custom me-2"></i> Posted: <?php echo date('M d, Y', strtotime($job['date_posted'])); ?></p>
                            <p class="mb-2"><i class="fas fa-hourglass-end text-primary-custom me-2"></i> Deadline: <?php echo date('M d, Y', strtotime($job['deadline'])); ?></p>
                        </div>
                    </div>

                    <h5 class="text-primary-custom mt-4">Job Description</h5>
                    <p class="mb-4 text-secondary-custom"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>

                    <h5 class="text-primary-custom">Requirements</h5>
                    <p class="mb-4 text-secondary-custom"><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></p>

                    <h5 class="text-primary-custom">Required Skills</h5>
                    <div class="mb-4">
                        <?php foreach (explode(',', $job['skills']) as $skill): ?>
                            <span class="badge me-2 mb-2" style="background:#E6EFE6; color:#4B654F; border:1px solid #BFCABF;"><?php echo htmlspecialchars(trim($skill)); ?></span>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($job['display_status'] === 'active' && isLoggedIn() && getUserRole() === 'user'): ?>
                        <?php if ($has_applied): ?>
                            <div class="alert alert-custom">
                                <i class="fas fa-info-circle me-2"></i> You have already applied for this position.
                            </div>
                        <?php else: ?>
                            <button type="button" class="btn btn-primary-custom px-4" data-bs-toggle="modal" data-bs-target="#applyModal">
                                <i class="fas fa-paper-plane me-2"></i> Apply Now
                            </button>
                        <?php endif; ?>
                    <?php elseif ($job['display_status'] === 'expired'): ?>
                        <div class="alert alert-custom" style="background:#F2F2F2; color:#6C757D; border:1px solid #BFBFBF;">
                            <i class="fas fa-clock me-2"></i> This position is no longer accepting applications as the deadline has passed.
                        </div>
                    <?php elseif ($job['display_status'] === 'rejected'): ?>
                        <div class="alert alert-custom" style="background:#F9E6E6; color:#8A5A5A; border:1px solid #D9A6A6;">
                            <i class="fas fa-times-circle me-2"></i> This position is not available.
                        </div>
                    <?php elseif ($job['display_status'] === 'pending'): ?>
                        <div class="alert alert-custom" style="background:#FFF8E6; color:#8A7A5A; border:1px solid #D9C9A6;">
                            <i class="fas fa-hourglass-half me-2"></i> This position is pending approval.
                        </div>
                    <?php elseif (!isLoggedIn()): ?>
                        <div class="alert alert-custom" style="background:#E6EFF9; color:#5A708A; border:1px solid #A6BFD9;">
                            <i class="fas fa-sign-in-alt me-2"></i> Please <a href="login.php" class="alert-link" style="color:#4B654F;">login</a> to apply for this position.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Company Information -->
            <div class="card border-0 shadow-sm card-custom">
                <div class="card-body">
                    <h5 class="card-title text-primary-custom">About <?php echo htmlspecialchars($job['company_name']); ?></h5>
                    <p class="mb-2 text-secondary-custom">
                        <i class="fas fa-map-marker-alt text-primary-custom me-2"></i>
                        <?php echo htmlspecialchars($job['company_location']); ?>
                    </p>
                    <p class="text-secondary-custom"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Similar Jobs -->
            <div class="card border-0 shadow-sm card-custom">
                <div class="card-header" style="background: var(--primary-light); border-radius: 18px 18px 0 0; border-bottom: 1px solid var(--border-color);">
                    <h5 class="mb-0" style="color: var(--primary-dark);">Similar Jobs</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($similar_jobs)): ?>
                        <p class="text-secondary-custom">No similar jobs found.</p>
                    <?php else: ?>
                        <?php foreach ($similar_jobs as $similar_job): ?>
                            <div class="mb-3 pb-3 border-bottom">
                                <h6>
                                    <a href="job_details.php?id=<?php echo $similar_job['id']; ?>" class="text-decoration-none text-primary-custom">
                                        <?php echo htmlspecialchars($similar_job['title']); ?>
                                    </a>
                                </h6>
                                <p class="text-secondary-custom mb-1">
                                    <?php echo htmlspecialchars($similar_job['company_name']); ?>
                                </p>
                                <p class="text-secondary-custom mb-0">
                                    <small>
                                        <i class="fas fa-map-marker-alt text-primary-custom me-1"></i>
                                        <?php echo htmlspecialchars($similar_job['location']); ?>
                                        &nbsp;|&nbsp;
                                        <i class="fas fa-money-bill-wave text-primary-custom me-1"></i>
                                        $<?php echo number_format($similar_job['salary_min']); ?> -
                                        $<?php echo number_format($similar_job['salary_max']); ?>
                                    </small>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Application Modal -->
<div class="modal fade" id="applyModal" tabindex="-1" aria-labelledby="applyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form action="apply_job.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-primary-custom" id="applyModalLabel">Apply for <?php echo htmlspecialchars($job['title']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                    <div class="mb-3">
                        <label for="resume" class="form-label text-secondary-custom">Resume (PDF/DOC/DOCX)</label>
                        <input type="file" class="form-control border-0 shadow-sm" id="resume" name="resume" required accept=".pdf,.doc,.docx" style="border:1px solid #BFCABF !important;">
                    </div>
                    <div class="mb-3">
                        <label for="cover_letter" class="form-label text-secondary-custom">Cover Letter</label>
                        <textarea class="form-control border-0 shadow-sm" id="cover_letter" name="cover_letter" rows="4" required style="border:1px solid #BFCABF !important;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="experience" class="form-label text-secondary-custom">Relevant Experience</label>
                        <textarea class="form-control border-0 shadow-sm" id="experience" name="experience" rows="3" required style="border:1px solid #BFCABF !important;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="education" class="form-label text-secondary-custom">Education</label>
                        <textarea class="form-control border-0 shadow-sm" id="education" name="education" rows="3" required style="border:1px solid #BFCABF !important;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="skills" class="form-label text-secondary-custom">Skills</label>
                        <textarea class="form-control border-0 shadow-sm" id="skills" name="skills" rows="2" style="border:1px solid #BFCABF !important;"></textarea>
                        <div class="form-text text-secondary-custom">List your skills separated by commas</div>
                    </div>
                    <div class="mb-3">
                        <label for="portfolio" class="form-label text-secondary-custom">Portfolio/Projects URL</label>
                        <input type="url" class="form-control border-0 shadow-sm" id="portfolio" name="portfolio" style="border:1px solid #BFCABF !important;">
                    </div>
                    <div class="mb-3">
                        <label for="expected_salary" class="form-label text-secondary-custom">Expected Salary</label>
                        <input type="number" class="form-control border-0 shadow-sm" id="expected_salary" name="expected_salary" required style="border:1px solid #BFCABF !important;">
                    </div>
                    <div class="mb-3">
                        <label for="availability" class="form-label text-secondary-custom">Availability</label>
                        <select class="form-select border-0 shadow-sm" id="availability" name="availability" required style="border:1px solid #BFCABF !important;">
                            <option value="immediate">Immediate</option>
                            <option value="1_week">Within 1 week</option>
                            <option value="2_weeks">Within 2 weeks</option>
                            <option value="1_month">Within 1 month</option>
                            <option value="negotiable">Negotiable</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>