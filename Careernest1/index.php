<?php
//index.php
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'config/db.php';

// Filtering logic
$where = [];
$params = [];

if (!empty($_GET['keyword'])) {
    $where[] = "(j.title LIKE ? OR j.description LIKE ?)";
    $params[] = '%' . $_GET['keyword'] . '%';
    $params[] = '%' . $_GET['keyword'] . '%';
}
if (!empty($_GET['location'])) {
    $where[] = "j.location LIKE ?";
    $params[] = '%' . $_GET['location'] . '%';
}
if (!empty($_GET['work_type'])) {
    $where[] = "j.work_type = ?";
    $params[] = $_GET['work_type'];
}

$sql = "SELECT j.*, c.company_name 
        FROM job_listings j 
        JOIN companies c ON j.company_id = c.id 
        WHERE j.status = 'approved'";
if ($where) {
    $sql .= " AND " . implode(" AND ", $where);
}
$sql .= " ORDER BY j.date_posted DESC LIMIT 6";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$featured_jobs = $stmt->fetchAll();
?>

<style>
/* Button Styles */
.btn-primary-custom {
    background: #4B654F;
    color: #fff;
    border-radius: 6px;
    font-weight: bold;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary-custom:hover {
    background: #3A463A;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(75, 101, 79, 0.25);
}

.btn-primary-custom:focus {
    box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25);
    background: #3A463A;
}

.btn-outline-custom {
    border: 1.5px solid #4B654F;
    color: #4B654F;
    border-radius: 6px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-outline-custom:hover {
    background: #4B654F;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(75, 101, 79, 0.25);
}

.btn-outline-custom:focus {
    box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25);
}

/* Form Element Styles */
.form-control:focus, .form-select:focus {
    border-color: #4B654F;
    box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25);
}

/* Card Hover Effects */
.job-card {
    transition: all 0.3s ease;
}

.job-card:hover {
    transform: translateY(-5px);
    border-color: #4B654F !important;
    box-shadow: 0 10px 20px rgba(75, 101, 79, 0.15) !important;
}

.dashboard-card {
    transition: all 0.3s ease;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    border-color: #4B654F !important;
    box-shadow: 0 10px 20px rgba(75, 101, 79, 0.15) !important;
}

/* Icon Hover Effects */
.dashboard-card i {
    transition: all 0.3s ease;
}

.dashboard-card:hover i {
    transform: scale(1.1);
    color: #3A463A !important;
}
</style>

<!-- Hero Section -->
<section class="hero-section text-center py-5" style="background:#E6EFE6; color:#3A463A;">
    <div class="container">
        <h1 class="display-4 mb-4 fw-bold" style="color:#3A463A;">Search for that Job!</h1>
        <p class="lead mb-5" style="color:#3A463A;">Connecting you to opportunities that fit your future.</p>
        <!-- Job Search Form -->
        <div class="row justify-content-center">
            <div class="col-md-10">
                <form id="jobSearchForm" class="search-form p-4 rounded shadow-sm" style="background:#fff; border:1px solid #BFCABF;" action="" method="GET">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="keyword" placeholder="Job title or keyword" value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>" style="border:1px solid #BFCABF; color:#3A463A;">
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="location" placeholder="Location" value="<?php echo isset($_GET['location']) ? htmlspecialchars($_GET['location']) : ''; ?>" style="border:1px solid #BFCABF; color:#3A463A;">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="work_type" style="border:1px solid #BFCABF; color:#3A463A;">
                                <option value="">Work Type</option>
                                <option value="full-time" <?php if(isset($_GET['work_type']) && $_GET['work_type']=='full-time') echo 'selected'; ?>>Full Time</option>
                                <option value="part-time" <?php if(isset($_GET['work_type']) && $_GET['work_type']=='part-time') echo 'selected'; ?>>Part Time</option>
                                <option value="contract" <?php if(isset($_GET['work_type']) && $_GET['work_type']=='contract') echo 'selected'; ?>>Contract</option>
                                <option value="internship" <?php if(isset($_GET['work_type']) && $_GET['work_type']=='internship') echo 'selected'; ?>>Internship</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary-custom w-100">Search</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Featured Jobs Section -->
<section class="container mb-5">
    <h2 class="text-center mb-4 fw-bold" style="color:#3A463A;">Featured Jobs</h2>
    <div class="row">
        <?php if (empty($featured_jobs)): ?>
            <div class="col-12 text-center text-muted">No jobs found matching your criteria.</div>
        <?php else: ?>
            <?php foreach ($featured_jobs as $job): ?>
                <div class="col-md-4 mb-4">
                    <div class="job-card p-4 rounded shadow-sm h-100" style="background:#fff; border:1px solid #BFCABF; color:#3A463A;">
                        <h5 class="fw-bold" style="color:#3A463A;"><?php echo htmlspecialchars($job['title']); ?></h5>
                        <h6 class="text-muted mb-2"><?php echo htmlspecialchars($job['company_name']); ?></h6>
                        <p class="mb-2"><i class="fas fa-map-marker-alt me-1" style="color:#4B654F;"></i> <?php echo htmlspecialchars($job['location']); ?></p>
                        <p class="mb-2"><i class="fas fa-briefcase me-1" style="color:#4B654F;"></i> <?php echo htmlspecialchars($job['work_type']); ?></p>
                        <p class="mb-3"><i class="fas fa-money-bill-wave me-1" style="color:#4B654F;"></i> $<?php echo number_format($job['salary_min']); ?> - $<?php echo number_format($job['salary_max']); ?></p>
                        <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-custom w-100">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="text-center mt-4">
        <a href="search_jobs.php" class="btn btn-primary-custom">View All Jobs</a>
    </div>
</section>

<!-- How It Works Section -->
<section class="container mb-5">
    <h2 class="text-center mb-4 fw-bold" style="color:#3A463A;">How It Works</h2>
    <div class="row text-center">
        <div class="col-md-4 mb-4">
            <div class="dashboard-card p-4 rounded shadow-sm h-100" style="background:#E6EFE6; border:1px solid #BFCABF; color:#3A463A;">
                <i class="fas fa-user-plus fa-3x mb-3" style="color:#4B654F;"></i>
                <h4 class="fw-bold">Create Account</h4>
                <p>Sign up as a job seeker or employer to get started</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="dashboard-card p-4 rounded shadow-sm h-100" style="background:#E6EFE6; border:1px solid #BFCABF; color:#3A463A;">
                <i class="fas fa-search fa-3x mb-3" style="color:#4B654F;"></i>
                <h4 class="fw-bold">Search Jobs</h4>
                <p>Browse through thousands of job listings</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="dashboard-card p-4 rounded shadow-sm h-100" style="background:#E6EFE6; border:1px solid #BFCABF; color:#3A463A;">
                <i class="fas fa-paper-plane fa-3x mb-3" style="color:#4B654F;"></i>
                <h4 class="fw-bold">Apply Now</h4>
                <p>Submit your application and get hired</p>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>