<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Edit Vendor';
$currentPage = 'vendors';
$db = getDB();

$tid = intval($_GET['id'] ?? 0);
if (!$tid) { header('Location: vendors.php'); exit(); }

$stmt = $db->prepare("SELECT t.*, u.username, u.status as user_status, u.user_id as uid FROM tenants t LEFT JOIN users u ON t.user_id=u.user_id WHERE t.tenant_id=?");
$stmt->execute([$tid]);
$vendor = $stmt->fetch();
if (!$vendor) { setMessage('danger', 'Vendor not found.'); header('Location: vendors.php'); exit(); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty(trim($_POST['full_name'] ?? '')))     $errors[] = 'Full name is required.';
    if (empty(trim($_POST['contact_no'] ?? '')))    $errors[] = 'Contact number is required.';
    if (empty(trim($_POST['business_name'] ?? ''))) $errors[] = 'Business name is required.';
    if (empty($_POST['tenant_type'] ?? ''))         $errors[] = 'Vendor type is required.';

    // Password change (optional)
    $change_pw = !empty(trim($_POST['new_password'] ?? ''));
    if ($change_pw && strlen($_POST['new_password']) < 6) $errors[] = 'New password must be at least 6 characters.';

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE tenants SET full_name=?,email=?,contact_no=?,address=?,business_name=?,business_type=?,tenant_type=?,status=? WHERE tenant_id=?")
               ->execute([
                   trim($_POST['full_name']),
                   trim($_POST['email'] ?? ''),
                   trim($_POST['contact_no']),
                   trim($_POST['address'] ?? ''),
                   trim($_POST['business_name']),
                   trim($_POST['business_type'] ?? ''),
                   $_POST['tenant_type'],
                   $_POST['status'],
                   $tid
               ]);

            // Update linked user if exists
            if ($vendor['uid']) {
                $db->prepare("UPDATE users SET full_name=?,email=?,status=? WHERE user_id=?")
                   ->execute([trim($_POST['full_name']), trim($_POST['email'] ?? ''), $_POST['status'] === 'active' ? 'active' : 'inactive', $vendor['uid']]);
                if ($change_pw) {
                    $db->prepare("UPDATE users SET password=? WHERE user_id=?")
                       ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $vendor['uid']]);
                }
            }

            $db->commit();
            setMessage('success', 'Vendor updated successfully!');
            header('Location: vendors.php'); exit();
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    // On error, merge posted values
    $vendor = array_merge($vendor, $_POST);
}

// Get rental info
$rental = $db->prepare("SELECT rr.*, ms.stall_number, ms.section, ms.price_per_month FROM rental_records rr JOIN market_stalls ms ON rr.stall_id=ms.stall_id WHERE rr.tenant_id=? AND rr.status='active' LIMIT 1");
$rental->execute([$tid]); $rental = $rental->fetch();

// Get payment summary
$pay = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM payments WHERE tenant_id=? AND status='paid'");
$pay->execute([$tid]); $pay = $pay->fetch();

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-edit me-2"></i> Edit Vendor — <?php echo htmlspecialchars($vendor['full_name']); ?></h1>
        <p class="page-subtitle">Update vendor information</p>
    </div>
    <div class="d-flex gap-2">
        <a href="view_vendor.php?id=<?php echo $tid; ?>" class="btn btn-outline-primary"><i class="fas fa-eye me-1"></i>View</a>
        <a href="vendors.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><strong>Please fix the following:</strong>
    <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST">
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-user me-2"></i>Personal Information</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($vendor['full_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" name="contact_no" class="form-control" value="<?php echo htmlspecialchars($vendor['contact_no']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($vendor['email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active"   <?php echo $vendor['status']==='active'  ?'selected':'';?>>Active</option>
                            <option value="inactive" <?php echo $vendor['status']==='inactive'?'selected':'';?>>Inactive</option>
                            <option value="pending"  <?php echo $vendor['status']==='pending' ?'selected':'';?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($vendor['address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><i class="fas fa-store me-2"></i>Business Information</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Business Name <span class="text-danger">*</span></label>
                        <input type="text" name="business_name" class="form-control" value="<?php echo htmlspecialchars($vendor['business_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Business Type</label>
                        <input type="text" name="business_type" class="form-control" value="<?php echo htmlspecialchars($vendor['business_type'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vendor Type <span class="text-danger">*</span></label>
                        <select name="tenant_type" class="form-select" required>
                            <option value="permanent" <?php echo $vendor['tenant_type']==='permanent'?'selected':'';?>>Permanent</option>
                            <option value="temporary" <?php echo $vendor['tenant_type']==='temporary'?'selected':'';?>>Temporary</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Account info -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-key me-2"></i>System Account</div>
            <div class="card-body">
                <?php if ($vendor['username']): ?>
                <div class="mb-3">
                    <div class="form-label">Username</div>
                    <div style="background:rgba(255,215,0,.05);border:1px solid var(--gold-bd);padding:8px 12px;border-radius:8px;font-weight:600;color:var(--gold)">
                        <?php echo htmlspecialchars($vendor['username']); ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Change Password <span style="color:var(--txt-mut)">(leave blank to keep current)</span></label>
                    <input type="password" name="new_password" class="form-control" placeholder="New password (min 6 chars)" autocomplete="new-password">
                </div>
                <?php else: ?>
                <div style="color:var(--txt-mut);font-size:.84rem;text-align:center;padding:12px">
                    <i class="fas fa-user-slash d-block fa-2x mb-2" style="opacity:.3"></i>
                    No system account linked.<br>
                    <a href="add_user.php" class="btn btn-outline-primary btn-sm mt-2">Create Account</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rental info -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-store me-2"></i>Current Stall</div>
            <div class="card-body">
                <?php if ($rental): ?>
                <div style="font-size:.86rem">
                    <div class="d-flex justify-content-between mb-2">
                        <span style="color:var(--txt-mut)">Stall</span>
                        <strong style="color:var(--gold)"><?php echo htmlspecialchars($rental['stall_number']); ?> (Sec <?php echo htmlspecialchars($rental['section']); ?>)</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span style="color:var(--txt-mut)">Monthly Rate</span>
                        <strong style="color:var(--green)"><?php echo formatCurrency($rental['price_per_month']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span style="color:var(--txt-mut)">Since</span>
                        <strong><?php echo formatDate($rental['start_date']); ?></strong>
                    </div>
                </div>
                <?php else: ?>
                <div style="color:var(--txt-mut);font-size:.84rem;text-align:center;padding:8px">
                    No active stall rental
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment summary -->
        <div class="card">
            <div class="card-header"><i class="fas fa-wallet me-2"></i>Balance</div>
            <div class="card-body text-center">
                <div style="font-size:1.8rem;font-weight:800;color:<?php echo ($vendor['balance']??0)>0?'var(--red)':'var(--green)';?>">
                    <?php echo formatCurrency($vendor['balance'] ?? 0); ?>
                </div>
                <div style="font-size:.78rem;color:var(--txt-mut);margin-top:4px">
                    <?php echo $pay['cnt']; ?> payment(s) · <?php echo formatCurrency($pay['total']); ?> total paid
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> Update Vendor</button>
    <a href="vendors.php" class="btn btn-secondary btn-lg">Cancel</a>
</div>
</form>
<?php include 'includes/footer.php'; ?>