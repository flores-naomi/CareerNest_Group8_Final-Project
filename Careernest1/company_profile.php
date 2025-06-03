<?php
require_once 'config/db.php';
require_once 'includes/session.php';


// Auto-create company row if missing
$checkStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ?");
$checkStmt->execute([$_SESSION['user_id']]);
if (!$checkStmt->fetch()) {
    // Optionally, pre-fill company_name with user's name from users table
    $userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $userStmt->execute([$_SESSION['user_id']]);
    $user = $userStmt->fetch();
    $company_name = $user ? $user['name'] : '';
    $insertStmt = $pdo->prepare("INSERT INTO companies (user_id, company_name) VALUES (?, ?)");
    $insertStmt->execute([$_SESSION['user_id'], $company_name]);
}

// Get company and user information
$stmt = $pdo->prepare("
    SELECT 
        c.id as company_id,
        c.company_name,
        c.description,
        c.industry,
        c.location,
        c.website,
        c.contact_email,
        c.contact_phone,
        u.name,
        u.email,
        u.id as user_id
    FROM users u
    LEFT JOIN companies c ON u.id = c.user_id
    WHERE u.id = ? AND u.role = 'company'
");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

// Debug information
if (!$company) {
    error_log("No company data found for user_id: " . $_SESSION['user_id']);
} else {
    error_log("Company data retrieved: " . print_r($company, true));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $description = trim($_POST['description']);
    $industry = trim($_POST['industry']);
    $location = trim($_POST['location']);
    $website = trim($_POST['website']);
    $contact_email = trim($_POST['contact_email']);
    $contact_phone = trim($_POST['contact_phone']);

    try {
        // Check if company record exists
        $checkStmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ?");
        $checkStmt->execute([$_SESSION['user_id']]);
        $companyExists = $checkStmt->fetch();

        if ($companyExists) {
            // Update existing company
            $stmt = $pdo->prepare("
                UPDATE companies 
                SET company_name = ?,
                    description = ?,
                    industry = ?,
                    location = ?,
                    website = ?,
                    contact_email = ?,
                    contact_phone = ?
                WHERE user_id = ?
            ");
        } else {
            // Insert new company
            $stmt = $pdo->prepare("
                INSERT INTO companies 
                (company_name, description, industry, location, website, contact_email, contact_phone, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }

        $stmt->execute([
            $company_name,
            $description,
            $industry,
            $location,
            $website,
            $contact_email,
            $contact_phone,
            $_SESSION['user_id']
        ]);

        $_SESSION['success'] = "Profile updated successfully!";
        header('Location: company_profile.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
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

<div class="container py-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Company Profile</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success">
                            <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                   value="<?php echo isset($company['company_name']) ? htmlspecialchars($company['company_name']) : ''; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Company Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="4" required><?php echo isset($company['description']) ? htmlspecialchars($company['description']) : ''; ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="industry" class="form-label">Industry</label>
                            <select class="form-select" id="industry" name="industry" required>
                                <option value="">Select Industry</option>
                                <?php
                                $industries = [
                                    'Technology',
                                    'Finance',
                                    'Healthcare',
                                    'Education',
                                    'Retail',
                                    'Hospitality',
                                    'Manufacturing',
                                    'Construction',
                                    'Transportation',
                                    'Other'
                                ];
                                $selectedIndustry = isset($company['industry']) ? $company['industry'] : '';
                                foreach ($industries as $industryOption) {
                                    $selected = ($selectedIndustry === $industryOption) ? 'selected' : '';
                                    echo "<option value=\"$industryOption\" $selected>$industryOption</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo isset($company['location']) ? htmlspecialchars($company['location']) : ''; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" class="form-control" id="website" name="website" 
                                   value="<?php echo isset($company['website']) ? htmlspecialchars($company['website']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                   value="<?php echo isset($company['contact_email']) ? htmlspecialchars($company['contact_email']) : ''; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                   value="<?php echo isset($company['contact_phone']) ? htmlspecialchars($company['contact_phone']) : ''; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Login Email</label>
                            <input type="email" class="form-control" value="<?php echo isset($company['email']) ? htmlspecialchars($company['email']) : ''; ?>" 
                                   disabled>
                            <small class="text-muted">Login email cannot be changed</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 