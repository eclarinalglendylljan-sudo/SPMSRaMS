<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Mark Stall Available';
$currentPage = 'map';
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

if ($stall['status'] === 'available') {
    setMessage('info', 'Stall ' . $stall['stall_number'] . ' is already marked as Available.');
    header('Location: map.php'); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->prepare("UPDATE market_stalls SET status = 'available' WHERE stall_id = ?")
           ->execute([$sid]);
        setMessage('success', 'Stall ' . $stall['stall_number'] . ' is now marked as Available.');
        header('Location: map.php'); exit();
    } catch (Exception $e) {
        setMessage('danger', 'Error: ' . $e->getMessage());
        header('Location: map.php'); exit();
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-check-circle"></i> Mark Stall as Available</h1>
        <p class="page-subtitle">Confirm status change</p>
    </div>
    <a href="map.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Map</a>
</div>

<div class="card" style="max-width:520px">
    <div class="card-body" style="text-align:center;padding:40px 28px">

        <div style="width:64px;height:64px;border-radius:50%;background:var(--green-bg);border:2px solid var(--green-bd);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:1.5rem;color:var(--green)">
            <i class="fas fa-check"></i>
        </div>

        <div style="font-size:1.1rem;font-weight:700;color:var(--tx-1);margin-bottom:6px">
            Mark Stall <?php echo htmlspecialchars($stall['stall_number']); ?> as Available?
        </div>

        <div style="color:var(--tx-3);font-size:.87rem;margin-bottom:8px">
            Current status:
            <span class="badge-status badge-<?php echo $stall['status']; ?>">
                <?php echo ucfirst($stall['status']); ?>
            </span>
        </div>

        <?php if ($stall['status'] === 'maintenance'): ?>
        <div style="color:var(--tx-2);font-size:.84rem;margin-bottom:24px">
            Marking this stall as Available means maintenance work is complete and the stall is ready for rental.
        </div>
        <?php elseif ($stall['status'] === 'reserved'): ?>
        <div style="color:var(--tx-2);font-size:.84rem;margin-bottom:24px">
            Marking this stall as Available will remove the reservation. It will appear as free on the map.
        </div>
        <?php else: ?>
        <div style="color:var(--tx-2);font-size:.84rem;margin-bottom:24px">
            This will make the stall available for new rentals.
        </div>
        <?php endif; ?>

        <!-- Stall info -->
        <div style="background:var(--glass-2);border:1px solid var(--bd);border-radius:var(--r-sm);padding:14px;margin-bottom:24px;text-align:left">
            <div class="info-row"><span class="info-key">Stall</span><span class="info-val" style="color:var(--gold);font-weight:700"><?php echo htmlspecialchars($stall['stall_number']); ?></span></div>
            <div class="info-row"><span class="info-key">Section</span><span class="info-val">Section <?php echo htmlspecialchars($stall['section']); ?></span></div>
            <div class="info-row"><span class="info-key">Location</span><span class="info-val"><?php echo htmlspecialchars($stall['location'] ?? '—'); ?></span></div>
            <div class="info-row"><span class="info-key">Monthly Rate</span><span class="info-val" style="color:var(--green)"><?php echo formatCurrency($stall['price_per_month']); ?></span></div>
            <div class="info-row"><span class="info-key">New Status</span><span class="info-val"><span class="badge-status badge-available">Available</span></span></div>
        </div>

        <form method="POST" style="display:flex;gap:10px;justify-content:center">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-check me-1"></i> Yes, Mark Available
            </button>
            <a href="map.php" class="btn btn-secondary btn-lg">Cancel</a>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>