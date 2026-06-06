<?php
require_once 'config.php';
requireLogin();
requirePermission('administrator');

$pageTitle   = 'Add User';
$currentPage = 'users';
$db = getDB();
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    if (empty(trim($_POST['full_name'] ?? ''))) $errors[] = 'Full name is required.';
    if (empty(trim($_POST['username'] ?? '')))  $errors[] = 'Username is required.';
    if (empty($_POST['role'] ?? ''))            $errors[] = 'Role is required.';
    if (empty($_POST['password'] ?? ''))        $errors[] = 'Password is required.';
    if (strlen($_POST['password'] ?? '') < 6)   $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors)) {
        $dup = $db->prepare("SELECT user_id FROM users WHERE username=?");
        $dup->execute([trim($_POST['username'])]);
        if ($dup->fetch()) $errors[] = 'Username already exists.';
    }

    if (empty($errors)) {
        try {
            $db->prepare("INSERT INTO users (username,password,full_name,email,role,status) VALUES (?,?,?,?,?,?)")
               ->execute([
                   trim($_POST['username']),
                   password_hash($_POST['password'], PASSWORD_DEFAULT),
                   trim($_POST['full_name']),
                   trim($_POST['email'] ?? ''),
                   $_POST['role'],
                   $_POST['status'] ?? 'active'
               ]);
            setMessage('success', 'User "' . htmlspecialchars(trim($_POST['username'])) . '" created successfully!');
            header('Location: users.php'); exit();
        } catch (Exception $e) { $errors[] = 'Database error: ' . $e->getMessage(); }
    }
}

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-plus me-2"></i> Add User</h1>
        <p class="page-subtitle">Create a new system account</p>
    </div>
    <a href="users.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><strong>Errors:</strong>
    <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card" style="max-width:600px">
    <div class="card-header"><i class="fas fa-key me-2"></i>Account Details</div>
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($old['full_name'] ?? ''); ?>" required placeholder="Juan dela Cruz">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($old['username'] ?? ''); ?>" required placeholder="login_name" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" placeholder="email@example.com">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="">Select role…</option>
                        <option value="administrator" <?php echo ($old['role'] ?? '') === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="staff"         <?php echo ($old['role'] ?? '') === 'staff'         ? 'selected' : ''; ?>>Staff</option>
                        <option value="vendor"        <?php echo ($old['role'] ?? '') === 'vendor'        ? 'selected' : ''; ?>>Vendor</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Min 6 characters" autocomplete="new-password" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?php echo ($old['status'] ?? 'active') === 'active'   ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($old['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <hr style="border-color:var(--gold-bd);margin:22px 0">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> Create User</button>
                <a href="users.php" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>