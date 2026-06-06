<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Delete Stall';
$currentPage = 'stalls';
$db = getDB();

$sid = intval($_GET['id'] ?? 0);
if (!$sid) { header('Location: map.php'); exit(); }

$stmt = $db->prepare("SELECT * FROM market_stalls WHERE stall_id = ?");
$stmt->execute([$sid]);
$stall = $stmt->fetch();
if (!$stall) {
    setMessage('danger', 'Stall not found.');
    header('Location: map.php'); exit();
}

// Block delete if stall is occupied
$stmt = $db->prepare("SELECT COUNT(*) FROM rental_records WHERE stall_id = ? AND status = 'active'");
$stmt->execute([$sid]);
$activeRentals = (int)$stmt->fetchColumn();

// Count all related records as a warning
$stmt = $db->prepare("SELECT COUNT(*) FROM rental_records WHERE stall_id = ?");
$stmt->execute([$sid]);
$totalRentals = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM maintenance_requests WHERE stall_id = ?");
$stmt->execute([$sid]);
$totalMaint = (int)$stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($activeRentals > 0) {
        setMessage('danger', 'Cannot delete — stall has an active rental. Terminate the contract first.');
        header('Location: map.php'); exit();
    }

    try {
        // Delete related records first to avoid FK constraint errors
        $db->prepare("DELETE FROM maintenance_requests WHERE stall_id = ?")->execute([$sid]);
        $db->prepare("DELETE FROM rental_records WHERE stall_id = ?")->execute([$sid]);
        $db->prepare("DELETE FROM market_stalls WHERE stall_id = ?")->execute([$sid]);

        setMessage('success', 'Stall ' . $stall['stall_number'] . ' has been permanently deleted.');
        header('Location: map.php'); exit();
    } catch (Exception $e) {
        setMessage('danger', 'Error deleting stall: ' . $e->getMessage());
        header('Location: map.php'); exit();
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title" style="color:var(--red)">
            <i class="fas fa-trash" style="color:var(--red)"></i>
            Delete Stall <?php echo htmlspecialchars($stall['stall_number']); ?>
        </h1>
        <p class="page-subtitle">This action is permanent and cannot be undone</p>
    </div>
    <div class="d-flex gap-2">
        <a href="view_stall.php?id=<?php echo $sid; ?>" class="btn btn-outline-primary">
            <i class="fas fa-eye me-1"></i> View Stall
        </a>
        <a href="map.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Map
        </a>
    </div>
</div>

<?php if ($activeRentals > 0): ?>
<!-- BLOCKED: Cannot delete occupied stall -->
<div class="card" style="max-width:560px;border-color:var(--red-bd)">
    <div class="card-body" style="text-align:center;padding:40px 28px">
        <div style="width:64px;height:64px;border-radius:50%;background:var(--red-bg);border:2px solid var(--red-bd);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:1.5rem;color:var(--red)">
            <i class="fas fa-lock"></i>
        </div>
        <div style="font-size:1rem;font-weight:700;color:var(--red);margin-bottom:8px">
            Cannot Delete — Stall is Currently Occupied
        </div>
        <div style="color:var(--tx-2);font-size:.86rem;margin-bottom:24px;line-height:1.7">
            This stall has an <strong style="color:var(--tx-1)">active rental contract</strong>.
            You must terminate the rental first before deleting the stall.
        </div>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="terminate_rental.php?id=<?php
                $r = $db->prepare("SELECT record_id FROM rental_records WHERE stall_id=? AND status='active' LIMIT 1");
                $r->execute([$sid]); echo intval($r->fetchColumn());
            ?>" class="btn btn-danger">
                <i class="fas fa-ban me-1"></i> Terminate Contract First
            </a>
            <a href="map.php" class="btn btn-secondary">Back to Map</a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Safe to delete -->
<div class="row g-4" style="max-width:860px">
    <div class="col-lg-7">

        <!-- Warning -->
        <div style="background:var(--red-bg);border:1px solid var(--red-bd);border-radius:var(--r);padding:18px 20px;margin-bottom:20px">
            <div style="display:flex;gap:12px;align-items:flex-start">
                <i class="fas fa-exclamation-triangle" style="color:var(--red);font-size:1.1rem;margin-top:2px;flex-shrink:0"></i>
                <div>
                    <div style="font-weight:700;color:var(--red);margin-bottom:6px">This will permanently delete:</div>
                    <ul style="color:var(--tx-2);font-size:.85rem;margin:0;padding-left:16px;line-height:1.9">
                        <li>Stall <strong style="color:var(--tx-1)"><?php echo htmlspecialchars($stall['stall_number']); ?></strong> and all its settings</li>
                        <?php if ($totalRentals > 0): ?>
                        <li><strong style="color:var(--tx-1)"><?php echo $totalRentals; ?></strong> rental record<?php echo $totalRentals !== 1 ? 's' : ''; ?> (historical)</li>
                        <?php endif; ?>
                        <?php if ($totalMaint > 0): ?>
                        <li><strong style="color:var(--tx-1)"><?php echo $totalMaint; ?></strong> maintenance request<?php echo $totalMaint !== 1 ? 's' : ''; ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Confirmation form -->
        <div class="card">
            <div class="card-header" style="border-color:var(--red-bd)">
                <i class="fas fa-trash" style="color:var(--red)"></i>
                <span style="color:var(--red)">Confirm Deletion</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div style="background:rgba(248,113,113,.06);border:1px solid var(--red-bd);border-radius:var(--r-sm);padding:14px;margin-bottom:20px">
                        <div class="form-check" style="margin:0">
                            <input class="form-check-input" type="checkbox" id="confirmCheck" required>
                            <label class="form-check-label" for="confirmCheck" style="color:var(--tx-1);font-size:.88rem;cursor:pointer">
                                I understand this will permanently delete Stall
                                <strong style="color:var(--gold)"><?php echo htmlspecialchars($stall['stall_number']); ?></strong>
                                and all its records. This cannot be undone.
                            </label>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-trash me-1"></i> Delete Permanently
                        </button>
                        <a href="map.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stall summary -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-store"></i> Stall Being Deleted</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row">
                    <span class="info-key">Stall</span>
                    <span class="info-val" style="color:var(--gold);font-weight:800;font-size:1.1rem">
                        <?php echo htmlspecialchars($stall['stall_number']); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-key">Section</span>
                    <span class="info-val">Section <?php echo htmlspecialchars($stall['section']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Location</span>
                    <span class="info-val"><?php echo htmlspecialchars($stall['location'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Size</span>
                    <span class="info-val"><?php echo $stall['size_sqm'] ? number_format($stall['size_sqm'], 2) . ' sqm' : '—'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Monthly Rate</span>
                    <span class="info-val" style="color:var(--green)"><?php echo formatCurrency($stall['price_per_month']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Status</span>
                    <span class="info-val">
                        <span class="badge-status badge-<?php echo $stall['status']; ?>">
                            <?php echo ucfirst($stall['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-key">Rental History</span>
                    <span class="info-val"><?php echo $totalRentals; ?> record<?php echo $totalRentals !== 1 ? 's' : ''; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Maintenance</span>
                    <span class="info-val"><?php echo $totalMaint; ?> request<?php echo $totalMaint !== 1 ? 's' : ''; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>