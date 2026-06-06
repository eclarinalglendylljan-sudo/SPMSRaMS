<?php
require_once 'config.php';
requireLogin();

$pageTitle   = 'My Profile';
$currentPage = 'profile';
$db = getDB();

$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$tenant = null;
$rental = null;
if (isVendor()) {
    $stmt = $db->prepare("SELECT * FROM tenants WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $tenant = $stmt->fetch();

    if ($tenant) {
        /* FIX: monthly_rate is in rental_records (rr), NOT market_stalls (ms)
           market_stalls has price_per_month — rr has monthly_rate (the agreed rate) */
        $stmt = $db->prepare("
            SELECT rr.*, ms.stall_number, ms.section, ms.location,
                   ms.size_sqm, ms.price_per_month, ms.payment_due_day
            FROM rental_records rr
            JOIN market_stalls ms ON rr.stall_id = ms.stall_id
            WHERE rr.tenant_id = ? AND rr.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$tenant['tenant_id']]);
        $rental = $stmt->fetch();
    }
}

$hasPic  = !empty($user['profile_picture']) && file_exists($user['profile_picture']);
$initial = strtoupper(substr($user['full_name'] ?? 'U', 0, 1));
$roleBadgeColor = [
    'administrator' => 'var(--red)',
    'staff'         => 'var(--blue)',
    'vendor'        => 'var(--gold)',
    'applicant'     => 'var(--amber)',
][$user['role']] ?? 'var(--gray)';

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-circle"></i> My Profile</h1>
        <p class="page-subtitle">Manage your account settings and information</p>
    </div>
</div>

<div class="row g-4">

    <!-- ── LEFT: Avatar + quick info ──────────────────────── -->
    <div class="col-lg-4">

        <!-- Avatar card -->
        <div class="card mb-3">
            <div class="card-body" style="text-align:center;padding:32px 22px">
                <div style="position:relative;display:inline-block;margin-bottom:20px">
                    <?php if ($hasPic): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>"
                         alt="Profile" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid var(--gold-bd);box-shadow:0 0 24px rgba(245,197,24,.2)">
                    <?php else: ?>
                    <div style="width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#c8960e);display:flex;align-items:center;justify-content:center;font-size:2.8rem;font-weight:800;color:#000;border:3px solid var(--gold-bd);box-shadow:0 0 24px rgba(245,197,24,.2)">
                        <?php echo $initial; ?>
                    </div>
                    <?php endif; ?>
                    <a href="upload_profile_picture.php" style="position:absolute;bottom:4px;right:4px;width:30px;height:30px;border-radius:50%;background:var(--gold);display:flex;align-items:center;justify-content:center;color:#000;font-size:.72rem;text-decoration:none;border:2px solid var(--bg-card);box-shadow:0 2px 8px rgba(0,0,0,.5)" title="Change photo">
                        <i class="fas fa-camera"></i>
                    </a>
                </div>

                <div style="font-size:1.1rem;font-weight:700;color:var(--tx-1);margin-bottom:4px">
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </div>
                <div style="font-family:monospace;font-size:.82rem;color:var(--tx-3);margin-bottom:12px">
                    @<?php echo htmlspecialchars($user['username']); ?>
                </div>
                <span style="background:rgba(0,0,0,.3);color:<?php echo $roleBadgeColor;?>;border:1px solid <?php echo $roleBadgeColor;?>44;padding:3px 14px;border-radius:20px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px">
                    <?php echo htmlspecialchars($user['role']); ?>
                </span>

                <div style="display:flex;gap:8px;justify-content:center;margin-top:16px">
                    <a href="upload_profile_picture.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-camera me-1"></i><?php echo $hasPic ? 'Change' : 'Upload'; ?> Photo
                    </a>
                    <?php if ($hasPic): ?>
                    <a href="remove_profile_picture.php" class="btn btn-secondary btn-sm" data-confirm="Remove your profile picture?">
                        <i class="fas fa-trash me-1"></i>Remove
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Account info -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle"></i> Account Info</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row">
                    <span class="info-key">Status</span>
                    <span class="info-val"><span class="badge-status badge-<?php echo $user['status'];?>"><?php echo ucfirst($user['status']);?></span></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Email</span>
                    <span class="info-val" style="font-size:.84rem"><?php echo htmlspecialchars($user['email'] ?? '—');?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Member Since</span>
                    <span class="info-val"><?php echo formatDate($user['created_at']);?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Last Login</span>
                    <span class="info-val"><?php echo $user['last_login'] ? formatDate($user['last_login']) : 'N/A';?></span>
                </div>
            </div>
        </div>

        <?php if ($tenant): ?>
        <!-- Vendor stall info -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-store"></i> Stall &amp; Business</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row"><span class="info-key">Business</span><span class="info-val"><?php echo htmlspecialchars($tenant['business_name']);?></span></div>
                <div class="info-row"><span class="info-key">Type</span><span class="info-val"><?php echo htmlspecialchars($tenant['business_type']??'—');?></span></div>
                <div class="info-row">
                    <span class="info-key">Vendor</span>
                    <span class="info-val"><span class="badge-status <?php echo $tenant['tenant_type']==='permanent'?'badge-active':'badge-reserved';?>"><?php echo ucfirst($tenant['tenant_type']);?></span></span>
                </div>
                <?php if ($rental): ?>
                <div class="info-row"><span class="info-key">Active Stall</span><span class="info-val" style="color:var(--gold);font-weight:700"><?php echo htmlspecialchars($rental['stall_number']);?></span></div>
                <div class="info-row">
                    <span class="info-key">Monthly Rate</span>
                    <span class="info-val" style="color:var(--green)"><?php echo formatCurrency($rental['monthly_rate']);?></span>
                </div>
                <?php else: ?>
                <div class="info-row"><span class="info-key">Stall</span><span class="info-val" style="color:var(--tx-3)">No active stall</span></div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-key">Balance</span>
                    <span class="info-val" style="color:<?php echo ($tenant['balance']??0)>0?'var(--red)':'var(--green)';?>;font-weight:700">
                        <?php echo formatCurrency($tenant['balance']??0);?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── RIGHT: Edit forms ───────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Update profile -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-edit"></i> Update Profile</div>
            <div class="card-body">
                <form method="POST" action="update_profile.php">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']);?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']);?>" disabled>
                            <div class="form-text">Username cannot be changed</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']??'');?>" placeholder="your@email.com">
                        </div>
                        <?php if ($tenant): ?>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_no" class="form-control" value="<?php echo htmlspecialchars($tenant['contact_no']);?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Business Name</label>
                            <input type="text" name="business_name" class="form-control" value="<?php echo htmlspecialchars($tenant['business_name']);?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($tenant['address']??'');?></textarea>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change password -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-lock"></i> Change Password</div>
            <div class="card-body">
                <form method="POST" action="change_password.php">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required autocomplete="current-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters" minlength="6" required autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" minlength="6" required autocomplete="new-password">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-warning"><i class="fas fa-key me-1"></i> Change Password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security -->
        <div class="card">
            <div class="card-header"><i class="fas fa-shield-alt"></i> Security</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row">
                    <span class="info-key"><i class="fas fa-check-circle me-2" style="color:var(--green)"></i>Account Status</span>
                    <span class="info-val"><span class="badge-status badge-active">Active</span></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fas fa-clock me-2" style="color:var(--blue)"></i>Session Timeout</span>
                    <span class="info-val" style="color:var(--tx-3)">2 hours of inactivity</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fas fa-calendar me-2" style="color:var(--tx-3)"></i>Account Created</span>
                    <span class="info-val"><?php echo formatDate($user['created_at']); ?></span>
                </div>
                <div class="alert alert-info mt-3 mb-0" style="font-size:.82rem">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Tip:</strong> Use a strong password with uppercase, lowercase, numbers and symbols. Never share your password.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>