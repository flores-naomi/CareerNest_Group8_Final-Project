<?php
require_once 'includes/session.php';
require_once 'config/db.php';

// Check if company is logged in
if (!isLoggedIn() || getUserRole() !== 'company') {
    header('Location: login.php');
    exit();
}

// Check if company is verified
$stmt = $pdo->prepare("SELECT is_verified FROM companies WHERE id = ?");
$stmt->execute([$_SESSION['company_id']]);
$company = $stmt->fetch();

if (!$company['is_verified']) {
    $_SESSION['error'] = "Your company account is not verified yet. Please wait for admin verification before posting jobs.";
    header('Location: company_dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $location = trim($_POST['location']);
    $type = $_POST['type'];
    $salary_min = (int)$_POST['salary_min'];
    $salary_max = (int)$_POST['salary_max'];
    $experience_level = $_POST['experience_level'];
    $education_level = $_POST['education_level'];
    $skills = trim($_POST['skills']);
    $deadline = $_POST['deadline'];
    $work_arrangement = $_POST['work_arrangement'];

    // Validate input
    $errors = [];
    if (empty($title)) $errors[] = "Job title is required";
    if (empty($description)) $errors[] = "Job description is required";
    if (empty($requirements)) $errors[] = "Job requirements are required";
    if (empty($location)) $errors[] = "Job location is required";
    if ($salary_min > $salary_max) $errors[] = "Minimum salary cannot be greater than maximum salary";
    if (empty($deadline)) $errors[] = "Application deadline is required";
    if (strtotime($deadline) < time()) $errors[] = "Deadline must be a future date";
    if (strlen($title) > 100) $errors[] = "Job title must be less than 100 characters";
    if (strlen($description) > 5000) $errors[] = "Job description must be less than 5000 characters";
    if (strlen($requirements) > 2000) $errors[] = "Job requirements must be less than 2000 characters";

    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Check for duplicate job posting (same title and company within last 24 hours)
            $stmt = $pdo->prepare("
                SELECT id FROM job_listings 
                WHERE company_id = ? 
                AND title = ? 
                AND date_posted > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$_SESSION['company_id'], $title]);
            if ($stmt->fetch()) {
                throw new Exception("A similar job posting was created in the last 24 hours. Please wait before posting again.");
            }

            // Insert job listing
            $stmt = $pdo->prepare("
                INSERT INTO job_listings (
                    company_id, title, description, requirements, location, 
                    work_type, salary_min, salary_max, experience_level, 
                    education_level, skills, deadline, status, date_posted,
                    work_arrangement
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)
            ");

            $stmt->execute([
                $_SESSION['company_id'],
                $title,
                $description,
                $requirements,
                $location,
                $type,
                $salary_min,
                $salary_max,
                $experience_level,
                $education_level,
                $skills,
                $deadline,
                $work_arrangement
            ]);

            $job_id = $pdo->lastInsertId();

            // Get admin user ID
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $stmt->execute();
            $admin = $stmt->fetch();

            if ($admin) {
                // Create notification for admin using the function from functions.php
                $notification_result = createNotification(
                    $admin['id'],
                    "New Job Posted",
                    "job_posted",
                    "New job posted: " . $title,
                    "manage_jobs.php?id=" . $job_id
                );

                if (!$notification_result['success']) {
                    error_log("Failed to create admin notification: " . $notification_result['message']);
                }
            }

            $pdo->commit();
            $_SESSION['success'] = "Job posted successfully! It will be visible after admin approval.";
            header('Location: company_dashboard.php');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
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
.btn-outline-secondary {
    color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-outline-secondary:hover, .btn-outline-secondary:focus {
    background-color: var(--primary-color) !important;
    color: #fff !important;
    box-shadow: 0 4px 8px rgba(75, 101, 79, 0.15);
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
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Post New Job</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="title" class="form-label">Job Title</label>
                            <input type="text" class="form-control" id="title" name="title" required
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Job Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="requirements" class="form-label">Requirements</label>
                            <textarea class="form-control" id="requirements" name="requirements" rows="4" required><?php echo isset($_POST['requirements']) ? htmlspecialchars($_POST['requirements']) : ''; ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" required
                                   value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Job Type</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="full-time" <?php echo (isset($_POST['type']) && $_POST['type'] === 'full-time') ? 'selected' : ''; ?>>Full Time</option>
                                    <option value="part-time" <?php echo (isset($_POST['type']) && $_POST['type'] === 'part-time') ? 'selected' : ''; ?>>Part Time</option>
                                    <option value="contract" <?php echo (isset($_POST['type']) && $_POST['type'] === 'contract') ? 'selected' : ''; ?>>Contract</option>
                                    <option value="internship" <?php echo (isset($_POST['type']) && $_POST['type'] === 'internship') ? 'selected' : ''; ?>>Internship</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="work_arrangement" class="form-label">Work Arrangement</label>
                                <select class="form-select" id="work_arrangement" name="work_arrangement" required>
                                    <option value="remote" <?php echo (isset($_POST['work_arrangement']) && $_POST['work_arrangement'] === 'remote') ? 'selected' : ''; ?>>Remote</option>
                                    <option value="onsite" <?php echo (isset($_POST['work_arrangement']) && $_POST['work_arrangement'] === 'onsite') ? 'selected' : ''; ?>>On-site</option>
                                    <option value="hybrid" <?php echo (isset($_POST['work_arrangement']) && $_POST['work_arrangement'] === 'hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                                    <option value="WFH" <?php echo (isset($_POST['work_arrangement']) && $_POST['work_arrangement'] === 'WFH') ? 'selected' : ''; ?>>Work from Home</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="experience_level" class="form-label">Experience Level</label>
                                <select class="form-select" id="experience_level" name="experience_level" required>
                                    <option value="entry level" <?php echo (isset($_POST['experience_level']) && $_POST['experience_level'] === 'entry level') ? 'selected' : ''; ?>>Entry Level</option>
                                    <option value="mid level" <?php echo (isset($_POST['experience_level']) && $_POST['experience_level'] === 'mid level') ? 'selected' : ''; ?>>Mid Level</option>
                                    <option value="senior level" <?php echo (isset($_POST['experience_level']) && $_POST['experience_level'] === 'senior level') ? 'selected' : ''; ?>>Senior Level</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="education_level" class="form-label">Education Level</label>
                                <select class="form-select" id="education_level" name="education_level" required>
                                    <option value="high school" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] === 'high school') ? 'selected' : ''; ?>>High School</option>
                                    <option value="associate degree" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] === 'associate degree') ? 'selected' : ''; ?>>Associate Degree</option>
                                    <option value="bachelors degree" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] === 'bachelors degree') ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                    <option value="masters degree" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] === 'masters degree') ? 'selected' : ''; ?>>Master's Degree</option>
                                    <option value="phd" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] === 'phd') ? 'selected' : ''; ?>>PhD</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="salary_min" class="form-label">Minimum Salary</label>
                                <input type="number" class="form-control" id="salary_min" name="salary_min" required
                                       value="<?php echo isset($_POST['salary_min']) ? htmlspecialchars($_POST['salary_min']) : ''; ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="salary_max" class="form-label">Maximum Salary</label>
                                <input type="number" class="form-control" id="salary_max" name="salary_max" required
                                       value="<?php echo isset($_POST['salary_max']) ? htmlspecialchars($_POST['salary_max']) : ''; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="skills" class="form-label">Required Skills (comma-separated)</label>
                            <input type="text" class="form-control" id="skills" name="skills" required
                                   value="<?php echo isset($_POST['skills']) ? htmlspecialchars($_POST['skills']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="deadline" class="form-label">Application Deadline</label>
                            <input type="date" class="form-control" id="deadline" name="deadline" required
                                   value="<?php echo isset($_POST['deadline']) ? htmlspecialchars($_POST['deadline']) : ''; ?>">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Post Job</button>
                            <a href="company_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 