<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Add New Vendor';
$currentPage = 'vendors';
$db = getDB();
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    // Validate
    if (empty(trim($_POST['full_name'] ?? '')))    $errors[] = 'Full name is required.';
    if (empty(trim($_POST['contact_no'] ?? '')))   $errors[] = 'Contact number is required.';
    if (empty(trim($_POST['business_name'] ?? ''))) $errors[] = 'Business name is required.';
    if (empty($_POST['tenant_type'] ?? ''))         $errors[] = 'Vendor type is required.';

    // If creating user account, validate username/password
    $create_account = !empty($_POST['create_account']);
    if ($create_account) {
        if (empty(trim($_POST['username'] ?? ''))) $errors[] = 'Username is required.';
        if (empty($_POST['password'] ?? ''))       $errors[] = 'Password is required.';
        if (strlen($_POST['password'] ?? '') < 6)  $errors[] = 'Password must be at least 6 characters.';
        // Check username unique
        if (empty($errors)) {
            $dup = $db->prepare("SELECT user_id FROM users WHERE username=?");
            $dup->execute([trim($_POST['username'])]);
            if ($dup->fetch()) $errors[] = 'Username already exists.';
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $user_id = null;
            if ($create_account) {
                $db->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?,?,?,?,'vendor','active')")
                   ->execute([
                       trim($_POST['username']),
                       password_hash($_POST['password'], PASSWORD_DEFAULT),
                       trim($_POST['full_name']),
                       trim($_POST['email'] ?? '')
                   ]);
                $user_id = $db->lastInsertId();
            }

            $db->prepare("INSERT INTO tenants (user_id, full_name, email, contact_no, address, business_name, business_type, tenant_type, status) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([
                   $user_id,
                   trim($_POST['full_name']),
                   trim($_POST['email'] ?? ''),
                   trim($_POST['contact_no']),
                   trim($_POST['address'] ?? ''),
                   trim($_POST['business_name']),
                   trim($_POST['business_type'] ?? ''),
                   $_POST['tenant_type'],
                   $_POST['status'] ?? 'active'
               ]);

            $db->commit();
            setMessage('success', 'Vendor "' . htmlspecialchars(trim($_POST['full_name'])) . '" added successfully!');
            header('Location: vendors.php'); exit();
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-plus me-2"></i> Add New Vendor</h1>
        <p class="page-subtitle">Register a new vendor or tenant</p>
    </div>
    <a href="vendors.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Vendors</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><strong>Please fix the following:</strong>
    <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST">
<div class="row g-4">

    <!-- Personal Info -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-user me-2"></i>Personal Information</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($old['full_name'] ?? ''); ?>" required placeholder="Juan dela Cruz">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" name="contact_no" class="form-control" value="<?php echo htmlspecialchars($old['contact_no'] ?? ''); ?>" required placeholder="09XXXXXXXXX">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" placeholder="email@example.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active"   <?php echo ($old['status'] ?? 'active') === 'active'  ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($old['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending"  <?php echo ($old['status'] ?? '') === 'pending'  ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Home address"><?php echo htmlspecialchars($old['address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Business Info -->
        <div class="card mt-4">
            <div class="card-header"><i class="fas fa-store me-2"></i>Business Information</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Business Name <span class="text-danger">*</span></label>
                        <input type="text" name="business_name" class="form-control" value="<?php echo htmlspecialchars($old['business_name'] ?? ''); ?>" required placeholder="Business / Trade Name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Business Type</label>
                        <input type="text" name="business_type" class="form-control" value="<?php echo htmlspecialchars($old['business_type'] ?? ''); ?>" placeholder="e.g. Food, Clothing, Goods">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vendor Type <span class="text-danger">*</span></label>
                        <select name="tenant_type" class="form-select" required>
                            <option value="">Select type…</option>
                            <option value="permanent"  <?php echo ($old['tenant_type'] ?? '') === 'permanent' ? 'selected' : ''; ?>>Permanent</option>
                            <option value="temporary"  <?php echo ($old['tenant_type'] ?? '') === 'temporary' ? 'selected' : ''; ?>>Temporary</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Account -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-key me-2"></i>System Account</div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="create_account" id="createAcct"
                               <?php echo !empty($old['create_account']) ? 'checked' : ''; ?>
                               onchange="document.getElementById('accountFields').style.display=this.checked?'block':'none'">
                        <label class="form-check-label" for="createAcct" style="color:var(--text)">
                            Create login account
                        </label>
                    </div>
                    <div class="form-text">Allow this vendor to log in to the system</div>
                </div>
                <div id="accountFields" style="display:<?php echo !empty($old['create_account']) ? 'block' : 'none'; ?>">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($old['username'] ?? ''); ?>" placeholder="login username" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" autocomplete="new-password">
                    </div>
                </div>
                <hr style="border-color:var(--gold-bd)">
                <div style="font-size:.82rem;color:var(--txt-mut)">
                    <i class="fas fa-info-circle me-1" style="color:var(--gold)"></i>
                    Vendors with accounts can log in to view their stall, payments, and submit maintenance requests.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> Save Vendor</button>
    <a href="vendors.php" class="btn btn-secondary btn-lg">Cancel</a>
</div>
</form>
<?php include 'includes/footer.php'; ?>