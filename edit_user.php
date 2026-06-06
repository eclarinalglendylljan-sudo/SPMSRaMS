<?php
require_once 'config.php';
requireLogin();
requirePermission('administrator');

$pageTitle   = 'Edit User';
$currentPage = 'users';
$db = getDB();

$uid = intval($_GET['id'] ?? 0);
if (!$uid) { header('Location: users.php'); exit(); }

$stmt = $db->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$uid]); $user = $stmt->fetch();
if (!$user) { setMessage('danger','User not found.'); header('Location: users.php'); exit(); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty(trim($_POST['full_name'] ?? ''))) $errors[] = 'Full name is required.';
    if (empty($_POST['role'] ?? ''))            $errors[] = 'Role is required.';

    $change_pw = !empty(trim($_POST['new_password'] ?? ''));
    if ($change_pw && strlen($_POST['new_password']) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors)) {
        try {
            $db->prepare("UPDATE users SET full_name=?,email=?,role=?,status=? WHERE user_id=?")
               ->execute([
                   trim($_POST['full_name']),
                   trim($_POST['email'] ?? ''),
                   $_POST['role'],
                   $_POST['status'],
                   $uid
               ]);
            if ($change_pw) {
                $db->prepare("UPDATE users SET password=? WHERE user_id=?")
                   ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $uid]);
            }
            setMessage('success', 'User updated successfully!');
            header('Location: users.php'); exit();
        } catch (Exception $e) { $errors[] = 'Database error: ' . $e->getMessage(); }
    }
    $user = array_merge($user, $_POST);
}

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-edit me-2"></i> Edit User — <?php echo htmlspecialchars($user['username']); ?></h1>
        <p class="page-subtitle">Update account details</p>
    </div>
    <a href="users.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><strong>Errors:</strong>
    <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($uid === $_SESSION['user_id']): ?>
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>You are editing your own account.
</div>
<?php endif; ?>

<div class="card" style="max-width:600px">
    <div class="card-header"><i class="fas fa-key me-2"></i>Account Details</div>
    <div class="card-body">
        <div class="mb-4 p-3 rounded" style="background:rgba(255,215,0,.04);border:1px solid var(--gold-bd)">
            <div style="font-size:.75rem;color:var(--txt-mut)">Username (cannot be changed)</div>
            <div style="font-weight:700;font-size:1.1rem;color:var(--gold);font-family:monospace"><?php echo htmlspecialchars($user['username']); ?></div>
        </div>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required <?php echo $uid===$_SESSION['user_id']?'disabled':'';?>>
                        <option value="administrator" <?php echo $user['role']==='administrator'?'selected':'';?>>Administrator</option>
                        <option value="staff"         <?php echo $user['role']==='staff'        ?'selected':'';?>>Staff</option>
                        <option value="vendor"        <?php echo $user['role']==='vendor'       ?'selected':'';?>>Vendor</option>
                    </select>
                    <?php if ($uid===$_SESSION['user_id']): ?>
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($user['role']); ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" <?php echo $uid===$_SESSION['user_id']?'disabled':'';?>>
                        <option value="active"   <?php echo $user['status']==='active'  ?'selected':'';?>>Active</option>
                        <option value="inactive" <?php echo $user['status']==='inactive'?'selected':'';?>>Inactive</option>
                    </select>
                    <?php if ($uid===$_SESSION['user_id']): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($user['status']); ?>">
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">New Password <span style="color:var(--txt-mut)">(leave blank to keep current)</span></label>
                    <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" autocomplete="new-password">
                </div>
            </div>
            <hr style="border-color:var(--gold-bd);margin:22px 0">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> Update User</button>
                <a href="users.php" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3" style="max-width:600px">
    <div class="card-header"><i class="fas fa-info-circle me-2"></i>Account Info</div>
    <div class="card-body" style="font-size:.86rem">
        <div class="row g-3">
            <div class="col-md-4"><div style="color:var(--txt-mut)">User ID</div><strong>#<?php echo $user['user_id']; ?></strong></div>
            <div class="col-md-4"><div style="color:var(--txt-mut)">Created</div><strong><?php echo formatDate($user['created_at']); ?></strong></div>
            <div class="col-md-4"><div style="color:var(--txt-mut)">Last Login</div><strong><?php echo $user['last_login'] ? formatDate($user['last_login']) : 'Never'; ?></strong></div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>