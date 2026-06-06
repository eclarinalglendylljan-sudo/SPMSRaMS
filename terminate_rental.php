<?php
require_once 'config.php';
requireLogin();
requirePermission('administrator');

$pageTitle   = 'Terminate Rental';
$currentPage = 'map';
$db = getDB();

$rid = intval($_GET['id'] ?? 0);
if (!$rid) { header('Location: map.php'); exit(); }

// Get rental details
$stmt = $db->prepare("
    SELECT rr.*,
           ms.stall_number, ms.section, ms.stall_id,
           t.full_name AS tenant_name, t.business_name, t.tenant_id
    FROM rental_records rr
    JOIN market_stalls ms ON rr.stall_id = ms.stall_id
    JOIN tenants t ON rr.tenant_id = t.tenant_id
    WHERE rr.record_id = ? AND rr.status = 'active'
");
$stmt->execute([$rid]);
$rental = $stmt->fetch();

if (!$rental) {
    setMessage('danger', 'Rental record not found or already terminated.');
    header('Location: map.php'); exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['termination_reason'] ?? '');

    if (empty($reason)) {
        $errors[] = 'Please provide a reason for termination.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // 1. Terminate the rental record
            $db->prepare("
                UPDATE rental_records
                SET status = 'terminated', end_date = CURDATE()
                WHERE record_id = ?
            ")->execute([$rid]);

            // 2. Mark stall as available again
            $db->prepare("
                UPDATE market_stalls SET status = 'available' WHERE stall_id = ?
            ")->execute([$rental['stall_id']]);

            // 3. Update tenant status to inactive
            $db->prepare("
                UPDATE tenants SET status = 'inactive' WHERE tenant_id = ?
            ")->execute([$rental['tenant_id']]);

            $db->commit();

            setMessage('success',
                'Rental for Stall ' . $rental['stall_number'] . ' has been terminated. ' .
                'Vendor ' . $rental['tenant_name'] . ' has been marked inactive.'
            );
            header('Location: map.php'); exit();

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
        <h1 class="page-title" style="color:var(--red)">
            <i class="fas fa-ban" style="color:var(--red)"></i> Terminate Rental Contract
        </h1>
        <p class="page-subtitle">This action cannot be undone</p>
    </div>
    <a href="map.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Map
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo htmlspecialchars($errors[0]); ?>
</div>
<?php endif; ?>

<div class="row g-4" style="max-width:860px">

    <!-- Warning + form -->
    <div class="col-lg-7">

        <!-- Danger warning box -->
        <div style="background:var(--red-bg);border:1px solid var(--red-bd);border-radius:var(--r);padding:20px 22px;margin-bottom:20px">
            <div style="display:flex;gap:14px;align-items:flex-start">
                <div style="width:44px;height:44px;border-radius:50%;background:rgba(248,113,113,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="fas fa-exclamation-triangle" style="color:var(--red);font-size:1.1rem"></i>
                </div>
                <div>
                    <div style="font-weight:700;color:var(--red);font-size:1rem;margin-bottom:6px">
                        You are about to terminate an active rental contract
                    </div>
                    <div style="font-size:.85rem;color:var(--tx-2);line-height:1.7">
                        This will immediately:
                        <ul style="margin:8px 0 0 16px;color:var(--tx-2)">
                            <li>End the rental contract for <strong style="color:var(--tx-1)"><?php echo htmlspecialchars($rental['tenant_name']); ?></strong></li>
                            <li>Mark Stall <strong style="color:var(--gold)"><?php echo htmlspecialchars($rental['stall_number']); ?></strong> as Available</li>
                            <li>Set the vendor's status to Inactive</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Termination form -->
        <div class="card">
            <div class="card-header" style="border-color:var(--red-bd)">
                <i class="fas fa-clipboard-list" style="color:var(--red)"></i>
                <span style="color:var(--red)">Termination Details</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label">Reason for Termination <span class="text-danger">*</span></label>
                        <textarea name="termination_reason"
                                  class="form-control" rows="4"
                                  placeholder="Explain why this rental is being terminated (e.g. non-payment, violation of terms, vendor request, stall relocation…)"
                                  required><?php echo htmlspecialchars($_POST['termination_reason'] ?? ''); ?></textarea>
                        <div class="form-text">This will be recorded in the system for audit purposes.</div>
                    </div>

                    <!-- Final confirmation checkbox -->
                    <div style="background:rgba(248,113,113,.06);border:1px solid var(--red-bd);border-radius:var(--r-sm);padding:14px;margin-bottom:20px">
                        <div class="form-check" style="margin:0">
                            <input class="form-check-input" type="checkbox" id="confirmCheck" required>
                            <label class="form-check-label" for="confirmCheck" style="color:var(--tx-1);font-size:.88rem;cursor:pointer">
                                I understand this will terminate the rental and cannot be undone
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-ban me-1"></i> Confirm Termination
                        </button>
                        <a href="map.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rental summary sidebar -->
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-store"></i> Stall Details</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row">
                    <span class="info-key">Stall</span>
                    <span class="info-val" style="color:var(--gold);font-weight:700;font-size:1.05rem">
                        <?php echo htmlspecialchars($rental['stall_number']); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-key">Section</span>
                    <span class="info-val">Section <?php echo htmlspecialchars($rental['section']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Monthly Rate</span>
                    <span class="info-val" style="color:var(--green)"><?php echo formatCurrency($rental['monthly_rate']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Start Date</span>
                    <span class="info-val"><?php echo formatDate($rental['start_date']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">End Date</span>
                    <span class="info-val" style="color:var(--red)"><?php echo date('M d, Y'); ?> (today)</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-user"></i> Vendor</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row">
                    <span class="info-key">Name</span>
                    <span class="info-val"><?php echo htmlspecialchars($rental['tenant_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Business</span>
                    <span class="info-val"><?php echo htmlspecialchars($rental['business_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">After Termination</span>
                    <span class="info-val">
                        <span class="badge-status badge-inactive">Inactive</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>