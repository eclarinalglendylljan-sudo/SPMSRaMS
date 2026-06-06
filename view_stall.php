<?php
require_once 'config.php';
requireLogin();

$pageTitle   = 'View Stall';
$currentPage = 'stalls';
$db = getDB();

$sid = intval($_GET['id'] ?? 0);
if (!$sid) { header('Location: map.php'); exit(); }

// Stall details
$stmt = $db->prepare("SELECT * FROM market_stalls WHERE stall_id = ?");
$stmt->execute([$sid]);
$stall = $stmt->fetch();
if (!$stall) {
    setMessage('danger', 'Stall not found.');
    header('Location: map.php'); exit();
}

// Active rental + current tenant
$stmt = $db->prepare("
    SELECT rr.*, t.full_name AS tenant_name, t.business_name,
           t.contact_no, t.tenant_id, t.tenant_type, t.status AS tenant_status
    FROM rental_records rr
    JOIN tenants t ON rr.tenant_id = t.tenant_id
    WHERE rr.stall_id = ? AND rr.status = 'active'
    LIMIT 1
");
$stmt->execute([$sid]);
$active = $stmt->fetch();

// Full rental history
$stmt = $db->prepare("
    SELECT rr.*, t.full_name AS tenant_name, t.business_name
    FROM rental_records rr
    JOIN tenants t ON rr.tenant_id = t.tenant_id
    WHERE rr.stall_id = ?
    ORDER BY rr.start_date DESC
");
$stmt->execute([$sid]);
$history = $stmt->fetchAll();

// Recent payments for this stall
$stmt = $db->prepare("
    SELECT p.*, t.full_name AS tenant_name
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.tenant_id
    JOIN rental_records rr ON p.tenant_id = rr.tenant_id AND rr.stall_id = ?
    GROUP BY p.payment_id
    ORDER BY p.payment_date DESC
    LIMIT 8
");
$stmt->execute([$sid]);
$payments = $stmt->fetchAll();

// Maintenance history
$stmt = $db->prepare("
    SELECT mr.*, t.full_name AS tenant_name, u.full_name AS assigned_name
    FROM maintenance_requests mr
    LEFT JOIN tenants t ON mr.tenant_id = t.tenant_id
    LEFT JOIN users u ON mr.assigned_to = u.user_id
    WHERE mr.stall_id = ?
    ORDER BY mr.request_date DESC
    LIMIT 6
");
$stmt->execute([$sid]);
$maintenance = $stmt->fetchAll();

// Revenue total for this stall
$stmt = $db->prepare("
    SELECT COALESCE(SUM(p.amount), 0)
    FROM payments p
    JOIN rental_records rr ON p.tenant_id = rr.tenant_id AND rr.stall_id = ?
    WHERE p.status = 'paid'
");
$stmt->execute([$sid]);
$total_revenue = (float)$stmt->fetchColumn();

$statusColor = [
    'available'   => 'var(--green)',
    'occupied'    => 'var(--red)',
    'maintenance' => 'var(--amber)',
    'reserved'    => 'var(--blue)',
][$stall['status']] ?? 'var(--gray)';

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="fas fa-store"></i>
            Stall <?php echo htmlspecialchars($stall['stall_number']); ?>
            <span class="badge-status badge-<?php echo $stall['status']; ?>" style="font-size:.75rem;margin-left:8px;vertical-align:middle">
                <?php echo ucfirst($stall['status']); ?>
            </span>
        </h1>
        <p class="page-subtitle">Section <?php echo htmlspecialchars($stall['section']); ?><?php echo $stall['location'] ? ' · ' . htmlspecialchars($stall['location']) : ''; ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (isStaff()): ?>
        <a href="edit_stall.php?id=<?php echo $sid; ?>" class="btn btn-warning">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        <a href="delete_stall.php?id=<?php echo $sid; ?>" class="btn btn-outline-danger">
            <i class="fas fa-trash me-1"></i> Delete
        </a>
        <?php endif; ?>
        <a href="map.php" class="btn btn-secondary">
            <i class="fas fa-map me-1"></i> Back to Map
        </a>
    </div>
</div>

<!-- ── QUICK STATS ─────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:<?php echo $statusColor;?>44">
            <div class="stat-icon" style="color:<?php echo $statusColor;?>"><i class="fas fa-store"></i></div>
            <div class="stat-value" style="font-size:1.3rem;color:<?php echo $statusColor;?>">
                <?php echo ucfirst($stall['status']); ?>
            </div>
            <div class="stat-label">Current Status</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
            <div class="stat-value" style="font-size:1.3rem"><?php echo formatCurrency($stall['price_per_month']); ?></div>
            <div class="stat-label">Monthly Rate</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:var(--green-bd)">
            <div class="stat-icon" style="color:var(--green)"><i class="fas fa-coins"></i></div>
            <div class="stat-value" style="font-size:1.1rem;color:var(--green)"><?php echo formatCurrency($total_revenue); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-history"></i></div>
            <div class="stat-value"><?php echo count($history); ?></div>
            <div class="stat-label">Total Rentals</div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ── LEFT: Stall + Tenant info ──────────────────────── -->
    <div class="col-lg-4">

        <!-- Stall details -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle"></i> Stall Details</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row">
                    <span class="info-key">Stall Number</span>
                    <span class="info-val" style="color:var(--gold);font-size:1.05rem;font-weight:800">
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
                    <span class="info-key">Daily Rate</span>
                    <span class="info-val" style="color:var(--green)"><?php echo formatCurrency($stall['price_per_day']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Monthly Rate</span>
                    <span class="info-val" style="color:var(--green);font-weight:700"><?php echo formatCurrency($stall['price_per_month']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Payment Due</span>
                    <span class="info-val">Day <?php echo intval($stall['payment_due_day'] ?? 1); ?> of month</span>
                </div>
                <div class="info-row">
                    <span class="info-key">Status</span>
                    <span class="info-val">
                        <span class="badge-status badge-<?php echo $stall['status']; ?>">
                            <?php echo ucfirst($stall['status']); ?>
                        </span>
                    </span>
                </div>
                <?php if ($stall['description']): ?>
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--bd)">
                    <div style="font-size:.72rem;color:var(--tx-3);margin-bottom:4px;font-weight:700;text-transform:uppercase;letter-spacing:.8px">Notes</div>
                    <div style="font-size:.85rem;color:var(--tx-2)"><?php echo htmlspecialchars($stall['description']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Current tenant -->
        <?php if ($active): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-user"></i> Current Tenant</div>
            <div class="card-body" style="padding:14px 18px">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
                    <div style="width:44px;height:44px;border-radius:50%;background:var(--gold);display:flex;align-items:center;justify-content:center;font-weight:800;color:#000;font-size:1rem;flex-shrink:0">
                        <?php echo strtoupper(substr($active['tenant_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight:700;color:var(--tx-1)"><?php echo htmlspecialchars($active['tenant_name']); ?></div>
                        <div style="font-size:.78rem;color:var(--tx-3)"><?php echo htmlspecialchars($active['business_name']); ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <span class="info-key">Type</span>
                    <span class="info-val">
                        <span class="badge-status <?php echo $active['tenant_type'] === 'permanent' ? 'badge-active' : 'badge-reserved'; ?>">
                            <?php echo ucfirst($active['tenant_type']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-key">Contact</span>
                    <span class="info-val" style="font-size:.84rem"><?php echo htmlspecialchars($active['contact_no'] ?? '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Renting Since</span>
                    <span class="info-val"><?php echo formatDate($active['start_date']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Monthly Rate</span>
                    <span class="info-val" style="color:var(--green);font-weight:700"><?php echo formatCurrency($active['monthly_rate']); ?></span>
                </div>
                <?php if (isStaff()): ?>
                <div style="margin-top:14px;display:flex;gap:8px">
                    <a href="view_vendor.php?id=<?php echo $active['tenant_id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">
                        <i class="fas fa-user me-1"></i> View Profile
                    </a>
                    <a href="add_payment.php?tenant_id=<?php echo $active['tenant_id']; ?>" class="btn btn-success btn-sm flex-fill">
                        <i class="fas fa-receipt me-1"></i> Payment
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card mb-3">
            <div class="card-body" style="text-align:center;padding:28px">
                <i class="fas fa-store-slash fa-2x d-block mb-2" style="opacity:.25;color:var(--tx-3)"></i>
                <div style="color:var(--tx-3);font-size:.86rem">No active tenant</div>
                <?php if (isStaff() && $stall['status'] === 'available'): ?>
                <a href="add_vendor.php" class="btn btn-primary btn-sm mt-3">
                    <i class="fas fa-user-plus me-1"></i> Assign Vendor
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick actions for staff -->
        <?php if (isStaff()): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
                <a href="edit_stall.php?id=<?php echo $sid; ?>" class="btn btn-warning w-100">
                    <i class="fas fa-edit me-1"></i> Edit Stall
                </a>
                <?php if ($stall['status'] !== 'available'): ?>
                <a href="mark_stall_available.php?id=<?php echo $sid; ?>" class="btn btn-outline-success w-100">
                    <i class="fas fa-check me-1"></i> Mark Available
                </a>
                <?php endif; ?>
                <?php if ($active && isAdmin()): ?>
                <a href="terminate_rental.php?id=<?php echo $active['record_id']; ?>" class="btn btn-outline-danger w-100">
                    <i class="fas fa-ban me-1"></i> Terminate Contract
                </a>
                <?php endif; ?>
                <a href="add_maintenance.php" class="btn btn-outline-warning w-100">
                    <i class="fas fa-tools me-1"></i> Log Maintenance
                </a>
                <a href="delete_stall.php?id=<?php echo $sid; ?>" class="btn btn-outline-danger w-100">
                    <i class="fas fa-trash me-1"></i> Delete Stall
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── RIGHT: History tabs ─────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Rental History -->
        <div class="card mb-4">
            <div class="card-header" style="justify-content:space-between">
                <span><i class="fas fa-history"></i> Rental History</span>
                <span style="font-weight:400;font-size:.8rem;color:var(--tx-3)"><?php echo count($history); ?> record<?php echo count($history) !== 1 ? 's' : ''; ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                <div style="text-align:center;padding:36px;color:var(--tx-3)">
                    <i class="fas fa-file-contract fa-2x d-block mb-2" style="opacity:.2"></i>
                    No rental history yet
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr><th>Tenant</th><th>Business</th><th>Start</th><th>End</th><th>Rate</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $r): ?>
                        <tr>
                            <td style="font-weight:600;color:var(--tx-1)"><?php echo htmlspecialchars($r['tenant_name']); ?></td>
                            <td style="color:var(--tx-3);font-size:.82rem"><?php echo htmlspecialchars($r['business_name']); ?></td>
                            <td style="color:var(--tx-2)"><?php echo formatDate($r['start_date']); ?></td>
                            <td style="color:var(--tx-3);font-size:.82rem">
                                <?php echo $r['end_date'] ? formatDate($r['end_date']) : '<span style="color:var(--green)">Active</span>'; ?>
                            </td>
                            <td style="color:var(--green);font-weight:600"><?php echo formatCurrency($r['monthly_rate']); ?></td>
                            <td>
                                <span class="badge-status badge-<?php echo $r['status']; ?>">
                                    <?php echo ucfirst($r['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-receipt"></i> Recent Payments</div>
            <div class="card-body p-0">
                <?php if (empty($payments)): ?>
                <div style="text-align:center;padding:36px;color:var(--tx-3)">
                    <i class="fas fa-receipt fa-2x d-block mb-2" style="opacity:.2"></i>
                    No payment records
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr><th>Receipt</th><th>Tenant</th><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td style="font-family:monospace;font-size:.79rem;color:var(--gold)"><?php echo htmlspecialchars($p['receipt_number'] ?? '—'); ?></td>
                            <td style="color:var(--tx-2)"><?php echo htmlspecialchars($p['tenant_name']); ?></td>
                            <td style="color:var(--tx-3);font-size:.82rem"><?php echo formatDate($p['payment_date']); ?></td>
                            <td style="font-weight:700;color:var(--green)"><?php echo formatCurrency($p['amount']); ?></td>
                            <td style="color:var(--tx-3);font-size:.82rem"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></td>
                            <td><span class="badge-status badge-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Maintenance History -->
        <div class="card">
            <div class="card-header"><i class="fas fa-tools"></i> Maintenance History</div>
            <div class="card-body p-0">
                <?php if (empty($maintenance)): ?>
                <div style="text-align:center;padding:36px;color:var(--tx-3)">
                    <i class="fas fa-check-circle fa-2x d-block mb-2" style="opacity:.2;color:var(--green)"></i>
                    No maintenance requests
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr><th>Title</th><th>Priority</th><th>Assigned To</th><th>Status</th><th>Date</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($maintenance as $m):
                            $pc = ['urgent'=>'pp-urgent','high'=>'pp-high','medium'=>'pp-medium','low'=>'pp-low'][$m['priority']] ?? 'pp-low';
                        ?>
                        <tr>
                            <td style="color:var(--tx-1);font-weight:500"><?php echo htmlspecialchars($m['title']); ?></td>
                            <td><span class="priority-pill <?php echo $pc; ?>"><?php echo strtoupper($m['priority']); ?></span></td>
                            <td style="color:var(--tx-3);font-size:.82rem"><?php echo htmlspecialchars($m['assigned_name'] ?? '—'); ?></td>
                            <td><span class="badge-status badge-<?php echo $m['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $m['status'])); ?></span></td>
                            <td style="color:var(--tx-3);font-size:.82rem"><?php echo formatDate($m['request_date']); ?></td>
                            <td>
                                <a href="view_maintenance.php?id=<?php echo $m['request_id']; ?>" class="btn btn-icon btn-outline-primary btn-sm" title="View">
                                    <i class="fas fa-eye" style="font-size:.72rem"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /col -->
</div><!-- /row -->

<?php include 'includes/footer.php'; ?>