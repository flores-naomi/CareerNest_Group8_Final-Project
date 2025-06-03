<?php
require_once 'config/db.php';
require_once 'includes/session.php';

// Check if user is logged in and is a regular user
if (!isLoggedIn() || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("
    SELECT u.*, up.* 
    FROM users u 
    LEFT JOIN user_profiles up ON u.id = up.user_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
require_once 'includes/header.php';
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
.form-control:focus {
    border-color: #4B654F;
    box-shadow: 0 0 0 0.25rem rgba(75, 101, 79, 0.25);
}

.form-control {
    border: 1px solid #BFCABF;
    color: #3A463A;
}

/* Card Styles */
.profile-card {
    transition: all 0.3s ease;
    background: #fff;
    border: 1px solid #BFCABF;
}

.profile-card:hover {
    transform: translateY(-5px);
    border-color: #4B654F !important;
    box-shadow: 0 10px 20px rgba(75, 101, 79, 0.15) !important;
}

.card-header {
    background: #E6EFE6;
    border-bottom: 1px solid #BFCABF;
}

.card-header h4 {
    color: #3A463A;
    font-weight: bold;
}
</style>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card profile-card">
                <div class="card-header">
                    <h4 class="mb-0">User Profile</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="update_user_profile.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="name" class="form-label" style="color: #3A463A;">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label" style="color: #3A463A;">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   disabled>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label" style="color: #3A463A;">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label" style="color: #3A463A;">Location</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label" style="color: #3A463A;">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" 
                                      rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="skills" class="form-label" style="color: #3A463A;">Skills (comma-separated)</label>
                            <input type="text" class="form-control" id="skills" name="skills" 
                                   value="<?php echo htmlspecialchars($user['skills'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="education" class="form-label" style="color: #3A463A;">Education</label>
                            <textarea class="form-control" id="education" name="education" 
                                      rows="3"><?php echo htmlspecialchars($user['education'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="experience" class="form-label" style="color: #3A463A;">Work Experience</label>
                            <textarea class="form-control" id="experience" name="experience" 
                                      rows="4"><?php echo htmlspecialchars($user['experience'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="resume" class="form-label" style="color: #3A463A;">Resume</label>
                            <?php if (!empty($user['resume_path'])): ?>
                                <div class="mb-2">
                                    <a href="<?php echo htmlspecialchars($user['resume_path']); ?>" 
                                       class="btn btn-outline-custom btn-sm" target="_blank">
                                        View Current Resume
                                    </a>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="resume" name="resume" accept=".pdf,.doc,.docx">
                            <small class="text-muted">Upload a new resume (PDF, DOC, or DOCX)</small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary-custom">Update Profile</button>
                            <a href="user_dashboard.php" class="btn btn-outline-custom">Back to Dashboard</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 