<?php
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'config/db.php';

// Check if admin is logged in
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Add is_verified column if it doesn't exist
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'is_verified'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE companies ADD COLUMN is_verified BOOLEAN DEFAULT 0");
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error checking/adding is_verified column: " . $e->getMessage();
}

// Handle company actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['company_id'])) {
        $company_id = (int)$_POST['company_id'];
        $action = $_POST['action'];

        try {
            switch ($action) {
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
                    $stmt->execute([$company_id]);
                    $_SESSION['success'] = "Company deleted successfully.";
                    break;

                case 'update_status':
                    if (isset($_POST['status'])) {
                        $status = $_POST['status'];
                        $stmt = $pdo->prepare("UPDATE companies SET status = ? WHERE id = ?");
                        $stmt->execute([$status, $company_id]);
                        $_SESSION['success'] = "Company status updated successfully.";
                    }
                    break;

                case 'toggle_verification':
                    $stmt = $pdo->prepare("UPDATE companies SET is_verified = NOT is_verified WHERE id = ?");
                    $stmt->execute([$company_id]);
                    
                    // Get company info for notification
                    $stmt = $pdo->prepare("SELECT c.*, u.id as user_id FROM companies c JOIN users u ON c.email = u.email WHERE c.id = ?");
                    $stmt->execute([$company_id]);
                    $company = $stmt->fetch();
                    
                    if ($company) {
                        // Create notification for company
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (
                                user_id, type, message, link, created_at
                            ) VALUES (?, 'verification_status', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $company['user_id'],
                            "Your company account has been " . ($company['is_verified'] ? "verified" : "unverified"),
                            "company_dashboard.php"
                        ]);
                    }
                    
                    $_SESSION['success'] = "Company verification status updated successfully.";
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error performing action: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$verification = isset($_GET['verification']) ? $_GET['verification'] : '';

// Build query
$query = "SELECT c.*, u.email, u.status as user_status 
          FROM companies c 
          JOIN users u ON c.email = u.email 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (c.company_name LIKE ? OR c.location LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($status !== '') {
    $query .= " AND c.status = ?";
    $params[] = $status;
}

if ($verification !== '') {
    $query .= " AND c.is_verified = ?";
    $params[] = $verification;
}

$query .= " ORDER BY c.created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$companies = $stmt->fetchAll();
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
/* Filter and Table Card Styles */
.filter-card, .table-card {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 6px 24px rgba(75, 101, 79, 0.08);
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    margin-bottom: 2rem;
}
.table-card {
    padding: 0.5rem 0.5rem 1rem 0.5rem;
}
.table thead th {
    background: var(--primary-light) !important;
    color: var(--primary-dark) !important;
    font-weight: 700;
}
.table {
    border-radius: 12px;
    overflow: hidden;
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0" style="font-size:2rem; font-weight:700; color:var(--text-primary);">Manage Companies</h2>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 bg-transparent shadow-none">
                <div class="filter-card">
                    <form method="GET" action="" class="mb-0">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Search by name, location, or email">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Verification</label>
                                <select class="form-select" name="verification">
                                    <option value="">All Verification</option>
                                    <option value="1" <?php echo $verification === '1' ? 'selected' : ''; ?>>Verified</option>
                                    <option value="0" <?php echo $verification === '0' ? 'selected' : ''; ?>>Unverified</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Company Name</th>
                                    <th>Location</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Verification</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($companies as $company): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($company['location']); ?></td>
                                        <td><?php echo htmlspecialchars($company['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $company['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($company['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $company['is_verified'] ? 'primary' : 'secondary'; ?>">
                                                <?php echo $company['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($company['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editCompanyModal<?php echo $company['id']; ?>">
                                                    Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteCompanyModal<?php echo $company['id']; ?>">
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Edit Company Modal -->
                                    <div class="modal fade" id="editCompanyModal<?php echo $company['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Company</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Company Name</label>
                                                            <input type="text" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($company['company_name']); ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Email</label>
                                                            <input type="email" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($company['email']); ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="status<?php echo $company['id']; ?>" class="form-label">Status</label>
                                                            <select class="form-select" id="status<?php echo $company['id']; ?>" name="status" required>
                                                                <option value="active" <?php echo $company['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="inactive" <?php echo $company['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" 
                                                                       id="verification<?php echo $company['id']; ?>" 
                                                                       <?php echo $company['is_verified'] ? 'checked' : ''; ?>
                                                                       onchange="toggleCompanyVerification(<?php echo $company['id']; ?>)">
                                                                <label class="form-check-label" for="verification<?php echo $company['id']; ?>">
                                                                    Verified Company
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Company Modal -->
                                    <div class="modal fade" id="deleteCompanyModal<?php echo $company['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Delete Company</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete this company? This action cannot be undone.</p>
                                                    <p><strong>Company:</strong> <?php echo htmlspecialchars($company['company_name']); ?></p>
                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($company['email']); ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Delete Company</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCompanyVerification(companyId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'toggle_verification';

    const companyIdInput = document.createElement('input');
    companyIdInput.type = 'hidden';
    companyIdInput.name = 'company_id';
    companyIdInput.value = companyId;

    form.appendChild(actionInput);
    form.appendChild(companyIdInput);
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once 'includes/footer.php'; ?> 