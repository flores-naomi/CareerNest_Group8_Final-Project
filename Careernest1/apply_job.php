<?php
require_once 'includes/session.php';
require_once 'config/db.php';

// Check if user is logged in
if (!isLoggedIn() || getUserRole() !== 'user') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $job_id = $_POST['job_id'];
    $resume = $_FILES['resume'];
    $cover_letter = $_POST['cover_letter'];
    $experience = $_POST['experience'];
    $education = $_POST['education'];
    $skills = $_POST['skills'];
    $portfolio = $_POST['portfolio'];
    $expected_salary = $_POST['expected_salary'];
    $availability = $_POST['availability'];

    // Validate required fields
    $errors = [];
    if (empty($resume['name'])) $errors[] = "Resume is required";
    if (empty($cover_letter)) $errors[] = "Cover letter is required";
    if (empty($experience)) $errors[] = "Experience is required";
    if (empty($education)) $errors[] = "Education is required";
    if (empty($expected_salary)) $errors[] = "Expected salary is required";
    if (empty($availability)) $errors[] = "Availability is required";

    // Validate file type
    $allowedTypes = ['pdf', 'doc', 'docx'];
    $fileType = strtolower(pathinfo($resume['name'], PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "Invalid file type. Allowed types: PDF, DOC, DOCX";
    }

    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024;
    if ($resume['size'] > $maxSize) {
        $errors[] = "File size exceeds 5MB limit";
    }

    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Check if user already applied for this job
            $stmt = $pdo->prepare("SELECT id FROM job_applications WHERE user_id = ? AND job_id = ?");
            $stmt->execute([$user_id, $job_id]);
            if ($stmt->fetch()) {
                throw new Exception("You have already applied for this job.");
            }

            // Generate unique filename
            $resume_filename = uniqid() . '_' . time() . '.' . $fileType;
            $resume_path = 'uploads/resumes/' . $resume_filename;

            // Create uploads directory if it doesn't exist
            if (!file_exists('uploads/resumes')) {
                mkdir('uploads/resumes', 0777, true);
            }

            // Move uploaded file
            if (!move_uploaded_file($resume['tmp_name'], $resume_path)) {
                throw new Exception("Failed to upload resume. Please try again.");
            }

            // Insert application
            $stmt = $pdo->prepare("
                INSERT INTO job_applications (
                    job_id, user_id, resume_path, cover_letter, experience, 
                    education, skills, portfolio, expected_salary, availability, 
                    status, applied_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $job_id, $user_id, $resume_path, $cover_letter, $experience,
                $education, $skills, $portfolio, $expected_salary, $availability
            ]);

            $application_id = $pdo->lastInsertId();

            // Get company info for notification
            $stmt = $pdo->prepare("
                SELECT c.user_id, jl.title 
                FROM job_listings jl 
                JOIN companies c ON jl.company_id = c.id 
                WHERE jl.id = ?
            ");
            $stmt->execute([$job_id]);
            $job_info = $stmt->fetch();

            if ($job_info) {
                // Create notification for company
                $notification_result = createNotification(
                    $job_info['user_id'],
                    "New Application",
                    "new_application",
                    "New application received for position: " . $job_info['title'],
                    "view_applications.php?id=" . $application_id
                );

                if (!$notification_result['success']) {
                    error_log("Failed to create notification: " . $notification_result['message']);
                }
            }

            $pdo->commit();
            $success = "Application submitted successfully!";
            
            // Redirect to success page
            $_SESSION['success'] = $success;
            header('Location: job_details.php?id=' . $job_id);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            // Clean up uploaded file if it exists
            if (isset($resume_path) && file_exists($resume_path)) {
                unlink($resume_path);
            }
            $error = $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get job details
$job_id = $_GET['id'] ?? null;
if (!$job_id) {
    header('Location: search_jobs.php');
    exit();
}

$stmt = $pdo->prepare("
    SELECT jl.*, c.company_name 
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
require_once 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Apply for: <?php echo htmlspecialchars($job['title']); ?></h4>
                    <h6 class="card-subtitle mb-3 text-muted"><?php echo htmlspecialchars($job['company_name']); ?></h6>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data" id="applicationForm">
                        <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">

                        <div class="mb-3">
                            <label for="resume" class="form-label">Resume (PDF/DOC/DOCX)</label>
                            <input type="file" class="form-control" id="resume" name="resume" required accept=".pdf,.doc,.docx">
                        </div>

                        <div class="mb-3">
                            <label for="cover_letter" class="form-label">Cover Letter</label>
                            <textarea class="form-control" id="cover_letter" name="cover_letter" rows="4" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="experience" class="form-label">Relevant Experience</label>
                            <textarea class="form-control" id="experience" name="experience" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="education" class="form-label">Education</label>
                            <textarea class="form-control" id="education" name="education" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="skills" class="form-label">Skills</label>
                            <textarea class="form-control" id="skills" name="skills" rows="2"></textarea>
                            <div class="form-text">List your skills separated by commas</div>
                        </div>

                        <div class="mb-3">
                            <label for="portfolio" class="form-label">Portfolio/Projects URL</label>
                            <input type="url" class="form-control" id="portfolio" name="portfolio">
                        </div>

                        <div class="mb-3">
                            <label for="expected_salary" class="form-label">Expected Salary</label>
                            <input type="number" class="form-control" id="expected_salary" name="expected_salary" required>
                        </div>

                        <div class="mb-3">
                            <label for="availability" class="form-label">Availability</label>
                            <select class="form-select" id="availability" name="availability" required>
                                <option value="immediate">Immediate</option>
                                <option value="1_week">Within 1 week</option>
                                <option value="2_weeks">Within 2 weeks</option>
                                <option value="1_month">Within 1 month</option>
                                <option value="negotiable">Negotiable</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Submit Application</button>
                            <a href="job_details.php?id=<?php echo $job_id; ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 