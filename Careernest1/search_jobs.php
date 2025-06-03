<?php
//search_jobs.php
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'config/db.php';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$work_arrangement = isset($_GET['work_arrangement']) ? $_GET['work_arrangement'] : '';
$experience = isset($_GET['experience']) ? $_GET['experience'] : '';
$salary_min = isset($_GET['salary_min']) ? (int)$_GET['salary_min'] : 0;
$salary_max = isset($_GET['salary_max']) ? (int)$_GET['salary_max'] : 0;
$education = isset($_GET['education']) ? $_GET['education'] : '';
$skills = isset($_GET['skills']) ? trim($_GET['skills']) : '';

// Build query
$query = "
    SELECT jl.*, c.company_name, c.location as company_location,
           CASE 
               WHEN jl.deadline < CURDATE() THEN 'expired'
               WHEN jl.status = 'approved' THEN 'active'
               ELSE 'inactive'
           END as display_status,
           (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = jl.id) as application_count
    FROM job_listings jl 
    JOIN companies c ON jl.company_id = c.id 
    WHERE jl.status = 'approved' 
    AND jl.deadline >= CURDATE()
";
$params = [];

if (!empty($search)) {
    $query .= " AND (jl.title LIKE ? OR jl.description LIKE ? OR c.company_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($location)) {
    $query .= " AND jl.location LIKE ?";
    $params[] = "%$location%";
}

if (!empty($type)) {
    $query .= " AND jl.work_type = ?";
    $params[] = $type;
}

if (!empty($work_arrangement)) {
    $query .= " AND jl.work_arrangement = ?";
    $params[] = $work_arrangement;
}

if (!empty($experience)) {
    $query .= " AND jl.experience_level = ?";
    $params[] = $experience;
}

if ($salary_min > 0) {
    $query .= " AND jl.salary_max >= ?";
    $params[] = $salary_min;
}

if ($salary_max > 0) {
    $query .= " AND jl.salary_min <= ?";
    $params[] = $salary_max;
}

if (!empty($education)) {
    $query .= " AND jl.education_level = ?";
    $params[] = $education;
}

if (!empty($skills)) {
    $skills_array = array_map('trim', explode(',', $skills));
    $placeholders = str_repeat('?,', count($skills_array) - 1) . '?';
    $query .= " AND (";
    foreach ($skills_array as $index => $skill) {
        if ($index > 0) $query .= " OR ";
        $query .= "jl.skills LIKE ?";
        $params[] = "%$skill%";
    }
    $query .= ")";
}

$query .= " GROUP BY jl.id ORDER BY jl.date_posted DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Debug output
echo "<!-- Debug: First job data -->";
if (!empty($jobs)) {
    echo "<!-- " . print_r($jobs[0], true) . " -->";
}

// Get unique locations for filter
$stmt = $pdo->query("SELECT DISTINCT location FROM job_listings WHERE status = 'approved' ORDER BY location");
$locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    body {
        background: linear-gradient(135deg, #f5f7f5 0%, #e8f0e8 100%);
        min-height: 100vh;
        color: var(--text-primary);
        font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
    }
    
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

.form-control, .form-select {
    border: 1px solid #BFCABF;
    color: #3A463A;
}

/* Card Styles */
.filter-card {
    transition: all 0.3s ease;
    background: #fff;
    border: 1px solid #BFCABF;
}

.filter-card:hover {
    transform: translateY(-5px);
    border-color: #4B654F !important;
    box-shadow: 0 10px 20px rgba(75, 101, 79, 0.15) !important;
}

.job-card {
    transition: all 0.3s ease;
    background: #fff;
    border: 1px solid #BFCABF;
}

.job-card:hover {
    transform: translateY(-5px);
    border-color: #4B654F !important;
    box-shadow: 0 10px 20px rgba(75, 101, 79, 0.15) !important;
}

.card-header {
    background: #E6EFE6;
    border-bottom: 1px solid #BFCABF;
}

.card-header h5 {
    color: #3A463A;
    font-weight: bold;
}

/* Label Styles */
.form-label {
    color: #3A463A;
    font-weight: 500;
}

/* Badge Styles */
.badge-custom {
    background: #E6EFE6;
    color: #3A463A;
    font-weight: 500;
    padding: 0.5em 1em;
    border-radius: 4px;
}

.count {
    background: #3A463A;
    color: #E6EFE6;
    font-weight: 500;
    padding: 0.5em 1em;
    border-radius: 4px;
}

/* Text Colors */
.text-primary-custom {
    color: #3A463A !important;
}

.text-secondary-custom {
    color: #4B654F !important;
}
</style>

<div class="container py-5">
    <div class="row">
        <!-- Filters Sidebar -->
        <div class="col-md-3">
            <div class="card mb-4 filter-card">
                <div class="card-header">
                    <h5 class="mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="Job title, company, or keywords">
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <select class="form-select" id="location" name="location">
                                <option value="">Any Location</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>"
                                            <?php echo $location === $loc ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="type" class="form-label">Job Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">Any Type</option>
                                <option value="full-time" <?php echo $type === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                                <option value="part-time" <?php echo $type === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                                <option value="contract" <?php echo $type === 'contract' ? 'selected' : ''; ?>>Contract</option>
                                <option value="internship" <?php echo $type === 'internship' ? 'selected' : ''; ?>>Internship</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="work_arrangement" class="form-label">Work Arrangement</label>
                            <select class="form-select" id="work_arrangement" name="work_arrangement">
                                <option value="">Any Arrangement</option>
                                <option value="remote" <?php echo $work_arrangement === 'remote' ? 'selected' : ''; ?>>Remote</option>
                                <option value="onsite" <?php echo $work_arrangement === 'onsite' ? 'selected' : ''; ?>>On-site</option>
                                <option value="hybrid" <?php echo $work_arrangement === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="experience" class="form-label">Experience Level</label>
                            <select class="form-select" id="experience" name="experience">
                                <option value="">Any Experience</option>
                                <option value="entry" <?php echo $experience === 'entry' ? 'selected' : ''; ?>>Entry Level</option>
                                <option value="mid" <?php echo $experience === 'mid' ? 'selected' : ''; ?>>Mid Level</option>
                                <option value="senior" <?php echo $experience === 'senior' ? 'selected' : ''; ?>>Senior Level</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="salary_min" class="form-label">Minimum Salary</label>
                            <input type="number" class="form-control" id="salary_min" name="salary_min" 
                                   value="<?php echo $salary_min; ?>" min="0" step="1000">
                        </div>

                        <div class="mb-3">
                            <label for="salary_max" class="form-label">Maximum Salary</label>
                            <input type="number" class="form-control" id="salary_max" name="salary_max" 
                                   value="<?php echo $salary_max; ?>" min="0" step="1000">
                        </div>

                        <div class="mb-3">
                            <label for="education" class="form-label">Education Level</label>
                            <select class="form-select" id="education" name="education">
                                <option value="">Any Education</option>
                                <option value="high-school" <?php echo $education === 'high-school' ? 'selected' : ''; ?>>High School</option>
                                <option value="bachelors" <?php echo $education === 'bachelors' ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                <option value="masters" <?php echo $education === 'masters' ? 'selected' : ''; ?>>Master's Degree</option>
                                <option value="phd" <?php echo $education === 'phd' ? 'selected' : ''; ?>>PhD</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="skills" class="form-label">Skills (comma-separated)</label>
                            <input type="text" class="form-control" id="skills" name="skills" 
                                   value="<?php echo htmlspecialchars($skills); ?>"
                                   placeholder="e.g., PHP, MySQL, JavaScript">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary-custom">Apply Filters</button>
                            <a href="search_jobs.php" class="btn btn-outline-custom">Reset Filters</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Job Listings -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-primary-custom mb-0">Job Listings</h2>
                <span class="badge badge-custom count"><?php echo count($jobs); ?> jobs found</span>
            </div>

            <?php if (empty($jobs)): ?>
                <div class="alert alert-info">
                    No jobs found matching your criteria. Try adjusting your filters.
                </div>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                    <div class="card mb-4 job-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title text-primary-custom mb-2">
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </h5>
                                    <h6 class="text-secondary-custom mb-3">
                                        <?php echo htmlspecialchars($job['company_name']); ?>
                                    </h6>
                                </div>
                                <span class="badge badge-custom">
                                    <?php echo ucfirst($job['work_type']); ?>
                                </span>
                            </div>

                            <div class="mb-3">
                                <p class="mb-2">
                                    <i class="fas fa-map-marker-alt me-2" style="color: #4B654F;"></i>
                                    <?php echo htmlspecialchars($job['location']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-money-bill-wave me-2" style="color: #4B654F;"></i>
                                    $<?php echo number_format($job['salary_min']); ?> - $<?php echo number_format($job['salary_max']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-briefcase me-2" style="color: #4B654F;"></i>
                                    <?php echo ucfirst($job['work_arrangement']); ?>
                                </p>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge badge-custom me-2">
                                        <i class="fas fa-users me-1"></i>
                                        <?php echo $job['application_count']; ?> applications
                                    </span>
                                    <span class="badge badge-custom">
                                        <i class="fas fa-clock me-1"></i>
                                        Posted <?php echo date('M d, Y', strtotime($job['date_posted'])); ?>
                                    </span>
                                </div>
                                <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-primary-custom">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>