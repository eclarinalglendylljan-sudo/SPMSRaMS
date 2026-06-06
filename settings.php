<?php
require_once 'config.php';
requireLogin();
requirePermission('administrator');

$pageTitle = 'System Settings';
$currentPage = 'settings';

$db = getDB();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_background') {
        $_SESSION['custom_bg'] = $_POST['background_image'];
        setMessage('success', 'Background image updated successfully!');
    } elseif ($_POST['action'] == 'clear_data') {
        try {
            $db->beginTransaction();
            
            if ($_POST['clear_type'] == 'payments') {
                $db->exec("DELETE FROM payments WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
                setMessage('success', 'Old payment records cleared!');
            } elseif ($_POST['clear_type'] == 'maintenance') {
                $db->exec("DELETE FROM maintenance_requests WHERE status = 'completed' AND completion_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
                setMessage('success', 'Old maintenance records cleared!');
            } elseif ($_POST['clear_type'] == 'inactive_vendors') {
                $db->exec("DELETE FROM tenants WHERE status = 'inactive' AND created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
                setMessage('success', 'Inactive vendors cleared!');
            }
            
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            setMessage('danger', 'Error: ' . $e->getMessage());
        }
    } elseif ($_POST['action'] == 'add_user') {
        try {
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['username'], $password, $_POST['full_name'], $_POST['email'], $_POST['role']]);
            setMessage('success', 'User added successfully!');
        } catch (Exception $e) {
            setMessage('danger', 'Error adding user: ' . $e->getMessage());
        }
    }
    header('Location: settings.php');
    exit();
}

// Get system statistics
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM tenants");
$stats['total_tenants'] = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM market_stalls");
$stats['total_stalls'] = $stmt->fetch()['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM payments");
$stats['total_payments'] = $stmt->fetch()['count'];

// Get all users
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-cog"></i> System Settings</h1>
    <p class="text-muted">Manage system configuration and preferences</p>
</div>

<!-- System Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Vendors</div>
                    <div class="stat-value"><?php echo $stats['total_tenants']; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-store"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Stalls</div>
                    <div class="stat-value"><?php echo $stats['total_stalls']; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-building"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Payment Records</div>
                    <div class="stat-value"><?php echo $stats['total_payments']; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <!-- Background Settings -->
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-image"></i> Background Settings
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_background">
                    <div class="mb-3">
                        <label class="form-label">Background Image Path</label>
                        <input type="text" name="background_image" class="form-control" 
                               value="<?php echo $_SESSION['custom_bg'] ?? 'assets/images/marketbg.png'; ?>"
                               placeholder="assets/images/marketbg.png">
                        <small class="text-muted">Enter the path to your background image</small>
                    </div>
                    <div class="alert alert-info">
                        <strong>Available backgrounds:</strong><br>
                        <code>assets/images/marketbg.png</code> (Default)<br>
                        Upload your images to <code>assets/images/</code> folder
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Background
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Data Management -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-database"></i> Data Management
            </div>
            <div class="card-body">
                <h6 class="text-warning">Clear Old Records</h6>
                <p class="text-muted">Remove old data to optimize database performance</p>
                
                <form method="POST" action="" onsubmit="return confirm('Are you sure? This action cannot be undone!');">
                    <input type="hidden" name="action" value="clear_data">
                    <div class="mb-3">
                        <label class="form-label">Select Data Type</label>
                        <select name="clear_type" class="form-select" required>
                            <option value="">Choose what to clear...</option>
                            <option value="payments">Payment records older than 1 year</option>
                            <option value="maintenance">Completed maintenance (older than 6 months)</option>
                            <option value="inactive_vendors">Inactive vendors (older than 1 year)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Clear Selected Data
                    </button>
                </form>
                
                <hr class="border-warning my-3">
                
                <div class="alert alert-warning">
                    <strong><i class="fas fa-exclamation-triangle"></i> Warning:</strong><br>
                    Clearing data is permanent and cannot be reversed. Always backup your database before performing this action.
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <!-- User Management -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-users-cog"></i> User Management</span>
                <!--<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Add User
                </button>-->
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['role'] == 'administrator' ? 'danger' : ($user['role'] == 'staff' ? 'primary' : 'info'); ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- System Information -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> System Information
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th width="40%">System Name:</th>
                        <td>Sibalom MSRMS</td>
                    </tr>
                    <tr>
                        <th>Version:</th>
                        <td>1.0.0</td>
                    </tr>
                    <tr>
                        <th>PHP Version:</th>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <th>Database:</th>
                        <td>MySQL/MariaDB</td>
                    </tr>
                    <tr>
                        <th>Server Time:</th>
                        <td><?php echo date('F d, Y h:i A'); ?></td>
                    </tr>
                </table>
                
                <hr class="border-warning">
                
                <h6 class="text-warning">Quick Actions</h6>
                <div class="d-grid gap-2">
                    <a href="reports.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-chart-bar"></i> View Reports
                    </a>
                    <button class="btn btn-outline-warning btn-sm" onclick="alert('Backup feature coming soon!')">
                        <i class="fas fa-download"></i> Backup Database
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="alert('Export feature coming soon!')">
                        <i class="fas fa-file-export"></i> Export Data
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border border-warning">
            <div class="modal-header border-warning">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select" required>
                            <option value="vendor">Vendor</option>
                            <option value="staff">Staff</option>
                            <option value="administrator">Administrator</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                        <small class="text-muted">At least 6 characters</small>
                    </div>
                </div>
                <div class="modal-footer border-warning">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>