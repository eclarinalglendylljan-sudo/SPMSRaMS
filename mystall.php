<?php
require_once 'config.php';
requireLogin();
requirePermission('vendor');

$pageTitle   = 'My Stall';
$currentPage = 'my-stall';
$db = getDB();

// Tenant record
$stmt = $db->prepare("SELECT * FROM tenants WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tenant = $stmt->fetch();
if (!$tenant) { header('Location: vendorapplication.php'); exit(); }

// Active rental + stall info
$stmt = $db->prepare("
    SELECT rr.*, ms.stall_number, ms.section, ms.location,
           ms.size_sqm, ms.description, ms.payment_due_day
    FROM rental_records rr
    JOIN market_stalls ms ON rr.stall_id = ms.stall_id
    WHERE rr.tenant_id = ? AND rr.status = 'active'
    ORDER BY rr.start_date DESC LIMIT 1
");
$stmt->execute([$tenant['tenant_id']]);
$rental = $stmt->fetch();

// Recent payments
$stmt = $db->prepare("
    SELECT * FROM payments
    WHERE tenant_id = ?
    ORDER BY payment_date DESC LIMIT 6
");
$stmt->execute([$tenant['tenant_id']]);
$payments = $stmt->fetchAll();

// Total paid
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = 'paid'");
$stmt->execute([$tenant['tenant_id']]);
$total_paid = (float)$stmt->fetchColumn();

// Pending payments count
$stmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE tenant_id = ? AND status = 'pending'");
$stmt->execute([$tenant['tenant_id']]);
$pending_count = (int)$stmt->fetchColumn();

// Recent maintenance
$stmt = $db->prepare("
    SELECT mr.*, ms.stall_number FROM maintenance_requests mr
    JOIN market_stalls ms ON mr.stall_id = ms.stall_id
    WHERE mr.tenant_id = ?
    ORDER BY mr.request_date DESC LIMIT 4
");
$stmt->execute([$tenant['tenant_id']]);
$maint = $stmt->fetchAll();

$balance = (float)($tenant['balance'] ?? 0);

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-store"></i> My Stall</h1>
        <p class="page-subtitle">Welcome back, <strong style="color:var(--gold)"><?php echo htmlspecialchars(explode(' ', $tenant['full_name'])[0]); ?></strong></p>
    </div>
    <?php if ($rental): ?>
    <div class="d-flex gap-2">
        <a href="submit_payment.php" class="btn btn-primary">
            <i class="fas fa-credit-card me-1"></i> Pay Now
        </a>
        <a href="submit_maintenance.php" class="btn btn-secondary">
            <i class="fas fa-tools me-1"></i> Report Issue
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (!$rental): ?>
<!-- No active stall -->
<div class="card" style="text-align:center;padding:56px 24px;max-width:480px;margin:0 auto">
    <div style="width:64px;height:64px;border-radius:50%;background:var(--gold-dim);border:1px solid var(--gold-bd);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:1.8rem;color:var(--gold)">
        <i class="fas fa-store-slash"></i>
    </div>
    <div style="font-size:1.15rem;font-weight:700;color:var(--tx-1);margin-bottom:8px">No Active Stall</div>
    <div style="color:var(--tx-3);margin-bottom:24px;font-size:.9rem">You don't have an active stall rental at the moment.</div>
    <a href="vendorapplication.php" class="btn btn-primary">
        <i class="fas fa-file-signature me-1"></i> Apply for a Stall
    </a>
</div>

<?php else: ?>

<!-- ── TOP ROW ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Stall Info -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-store"></i> Stall Information</div>
            <div class="card-body">
                <div style="display:flex;align-items:flex-start;gap:20px;margin-bottom:20px">
                    <div style="width:64px;height:64px;border-radius:var(--r);background:var(--gold-dim);border:1px solid var(--gold-bd);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <span style="font-size:1.4rem;font-weight:900;color:var(--gold);line-height:1"><?php echo htmlspecialchars($rental['stall_number']); ?></span>
                    </div>
                    <div>
                        <div style="font-size:1.1rem;font-weight:700;color:var(--tx-1)">
                            Stall <?php echo htmlspecialchars($rental['stall_number']); ?>
                        </div>
                        <div style="color:var(--tx-3);font-size:.84rem;margin-top:2px">
                            Section <?php echo htmlspecialchars($rental['section']); ?>
                            <?php if ($rental['location']): ?> · <?php echo htmlspecialchars($rental['location']); ?><?php endif; ?>
                        </div>
                        <span class="badge-status badge-active mt-2">Active Rental</span>
                    </div>
                </div>

                <div class="row g-0">
                    <div class="col-6">
                        <div class="info-row">
                            <span class="info-key">Monthly Rate</span>
                            <span class="info-val" style="color:var(--green);font-size:1rem"><?php echo formatCurrency($rental['monthly_rate']); ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="info-row">
                            <span class="info-key">Payment Due</span>
                            <span class="info-val">Day <?php echo intval($rental['payment_due_day'] ?? 1); ?> of month</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="info-row">
                            <span class="info-key">Size</span>
                            <span class="info-val"><?php echo $rental['size_sqm'] ? number_format($rental['size_sqm'], 2) . ' sqm' : '—'; ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="info-row">
                            <span class="info-key">Start Date</span>
                            <span class="info-val"><?php echo formatDate($rental['start_date']); ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($rental['description']): ?>
                <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);font-size:.84rem;color:var(--tx-2)">
                    <?php echo htmlspecialchars($rental['description']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Balance + Business -->
    <div class="col-lg-4">

        <!-- BALANCE CARD — the main pay button is here -->
        <div class="card mb-3" style="<?php echo $balance > 0 ? 'border-color:var(--red-bd)' : 'border-color:var(--green-bd)'; ?>">
            <div class="card-header" style="<?php echo $balance > 0 ? 'color:var(--red)' : 'color:var(--green)'; ?>">
                <i class="fas fa-wallet"></i> Account Balance
            </div>
            <div class="card-body" style="text-align:center;padding:24px 18px">
                <div style="font-size:2.2rem;font-weight:800;letter-spacing:-1.5px;line-height:1;color:<?php echo $balance > 0 ? 'var(--red)' : 'var(--green)'; ?>">
                    <?php echo formatCurrency($balance); ?>
                </div>
                <div style="font-size:.78rem;color:var(--tx-3);margin-top:6px;margin-bottom:16px">
                    <?php if ($balance > 0): ?>
                        Outstanding balance due
                    <?php else: ?>
                        <i class="fas fa-check-circle me-1" style="color:var(--green)"></i> No outstanding balance
                    <?php endif; ?>
                </div>

                <!-- PAY NOW BUTTON -->
                <a href="submit_payment.php" class="btn btn-primary w-100 mb-2">
                    <i class="fas fa-credit-card me-2"></i>
                    <?php echo $balance > 0 ? 'Pay Balance Now' : 'Make a Payment'; ?>
                </a>

                <?php if ($pending_count > 0): ?>
                <div style="font-size:.76rem;color:var(--amber);margin-top:4px">
                    <i class="fas fa-clock me-1"></i>
                    <?php echo $pending_count; ?> payment<?php echo $pending_count > 1 ? 's' : ''; ?> pending admin review
                </div>
                <?php endif; ?>

                <div style="border-top:1px solid var(--border);margin-top:14px;padding-top:12px;font-size:.8rem;color:var(--tx-3)">
                    Total paid to date: <strong style="color:var(--green)"><?php echo formatCurrency($total_paid); ?></strong>
                </div>
            </div>
        </div>

        <!-- Business info -->
        <div class="card">
            <div class="card-header"><i class="fas fa-briefcase"></i> Business</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row">
                    <span class="info-key">Name</span>
                    <span class="info-val" style="font-size:.88rem"><?php echo htmlspecialchars($tenant['business_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Type</span>
                    <span class="info-val" style="font-size:.88rem"><?php echo htmlspecialchars($tenant['business_type'] ?: '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Vendor</span>
                    <span class="info-val">
                        <span class="badge-status <?php echo $tenant['tenant_type'] === 'permanent' ? 'badge-active' : 'badge-reserved'; ?>">
                            <?php echo ucfirst($tenant['tenant_type']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-key">Contact</span>
                    <span class="info-val" style="font-size:.84rem"><?php echo htmlspecialchars($tenant['contact_no']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── PAYMENTS ─────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header" style="justify-content:space-between">
        <span><i class="fas fa-receipt"></i> Recent Payments</span>
        <div class="d-flex gap-2">
            <a href="submit_payment.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> New Payment
            </a>
            <a href="mypayment.php" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
        <div style="text-align:center;padding:40px;color:var(--tx-3)">
            <i class="fas fa-receipt fa-2x d-block mb-2" style="opacity:.2"></i>
            No payments yet.
            <a href="submit_payment.php" class="btn btn-primary btn-sm ms-2">Make first payment</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td style="font-family:monospace;font-size:.79rem;color:var(--gold)"><?php echo htmlspecialchars($p['receipt_number'] ?? '—'); ?></td>
                    <td style="color:var(--tx-2)"><?php echo formatDate($p['payment_date']); ?></td>
                    <td style="font-weight:700;color:var(--green)"><?php echo formatCurrency($p['amount']); ?></td>
                    <td style="color:var(--tx-3);font-size:.81rem"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></td>
                    <td><span class="badge-status badge-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                    <td>
                        <?php if ($p['status'] === 'paid'): ?>
                        <a href="receipt.php?id=<?php echo $p['payment_id']; ?>"
                           class="btn btn-icon btn-outline-primary btn-sm"
                           target="_blank" title="View Receipt">
                            <i class="fas fa-file-invoice" style="font-size:.75rem"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── MAINTENANCE ───────────────────────────────────────────── -->
<div class="card">
    <div class="card-header" style="justify-content:space-between">
        <span><i class="fas fa-tools"></i> Maintenance Requests</span>
        <a href="submit_maintenance.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Report Issue
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($maint)): ?>
        <div style="text-align:center;padding:40px;color:var(--tx-3)">
            <i class="fas fa-check-circle fa-2x d-block mb-2" style="opacity:.2;color:var(--green)"></i>
            No maintenance requests
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Issue</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($maint as $m):
                    $pc = ['urgent'=>'var(--red)', 'high'=>'var(--amber)', 'medium'=>'var(--blue)', 'low'=>'var(--gray)'];
                ?>
                <tr>
                    <td style="color:var(--tx-1);font-weight:500"><?php echo htmlspecialchars($m['title']); ?></td>
                    <td><span style="font-size:.69rem;font-weight:700;text-transform:uppercase;color:<?php echo $pc[$m['priority']] ?? 'var(--gray)'; ?>"><?php echo $m['priority']; ?></span></td>
                    <td><span class="badge-status badge-<?php echo $m['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $m['status'])); ?></span></td>
                    <td style="color:var(--tx-3);font-size:.81rem"><?php echo formatDate($m['request_date']); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-footer">
        <a href="maintenance.php" class="btn btn-secondary btn-sm">View All Requests</a>
    </div>
</div>

<?php endif; ?>
<?php include 'includes/footer.php'; ?>