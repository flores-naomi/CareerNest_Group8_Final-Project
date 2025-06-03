<?php
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'config/db.php';

// Check if admin is logged in
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];

        try {
            switch ($action) {
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$user_id]);
                    $_SESSION['success'] = "User deleted successfully.";
                    break;

                case 'update_role':
                    if (isset($_POST['role'])) {
                        $role = $_POST['role'];
                        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'admin'");
                        $stmt->execute([$role, $user_id]);
                        $_SESSION['success'] = "User role updated successfully.";
                    }
                    break;

                case 'toggle_status':
                    $stmt = $pdo->prepare("UPDATE users SET status = NOT status WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$user_id]);
                    $_SESSION['success'] = "User status updated successfully.";
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error performing action: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($role)) {
    $query .= " AND role = ?";
    $params[] = $role;
}

if ($status !== '') {
    $query .= " AND status = ?";
    $params[] = $status;
}

$query .= " ORDER BY created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
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
        <h2 class="mb-0" style="font-size:2rem; font-weight:700; color:var(--text-primary);">Manage Users</h2>
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
                                       placeholder="Search by name or email">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role">
                                    <option value="">All Roles</option>
                                    <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="company" <?php echo $role === 'company' ? 'selected' : ''; ?>>Company</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Active</option>
                                    <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inactive</option>
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
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['status'] ? 'success' : 'warning'; ?>">
                                                <?php echo $user['status'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                                    Edit
                                                </button>
                                                <?php if ($user['role'] !== 'admin'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteUserModal<?php echo $user['id']; ?>">
                                                        Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Edit User Modal -->
                                    <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit User</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update_role">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Name</label>
                                                            <input type="text" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Email</label>
                                                            <input type="email" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                                        </div>
                                                        
                                                        <?php if ($user['role'] !== 'admin'): ?>
                                                            <div class="mb-3">
                                                                <label for="role<?php echo $user['id']; ?>" class="form-label">Role</label>
                                                                <select class="form-select" id="role<?php echo $user['id']; ?>" name="role" required>
                                                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                                    <option value="company" <?php echo $user['role'] === 'company' ? 'selected' : ''; ?>>Company</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" 
                                                                           id="status<?php echo $user['id']; ?>" 
                                                                           <?php echo $user['status'] ? 'checked' : ''; ?>
                                                                           onchange="toggleUserStatus(<?php echo $user['id']; ?>)">
                                                                    <label class="form-check-label" for="status<?php echo $user['id']; ?>">
                                                                        Active Status
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete User Modal -->
                                    <?php if ($user['role'] !== 'admin'): ?>
                                        <div class="modal fade" id="deleteUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Delete User</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                                                        <p><strong>User:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="POST" action="">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Delete User</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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
function toggleUserStatus(userId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'toggle_status';

    const userIdInput = document.createElement('input');
    userIdInput.type = 'hidden';
    userIdInput.name = 'user_id';
    userIdInput.value = userId;

    form.appendChild(actionInput);
    form.appendChild(userIdInput);
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once 'includes/footer.php'; ?> 