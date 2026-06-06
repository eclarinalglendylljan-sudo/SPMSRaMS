<?php
require_once 'config.php';
requireLogin();
requirePermission('vendor');

$pageTitle   = 'My Payments';
$currentPage = 'my-payments';
$db = getDB();

$stmt = $db->prepare("SELECT * FROM tenants WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tenant = $stmt->fetch();
if (!$tenant) { header('Location: vendorapplication.php'); exit(); }

$status_filter = $_GET['status'] ?? '';

$q  = "SELECT * FROM payments WHERE tenant_id = ?";
$p  = [$tenant['tenant_id']];
if ($status_filter) { $q .= " AND status = ?"; $p[] = $status_filter; }
$q .= " ORDER BY payment_date DESC";
$stmt = $db->prepare($q);
$stmt->execute($p);
$payments = $stmt->fetchAll();

// Stats
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = 'paid'");
$stmt->execute([$tenant['tenant_id']]);
$total_paid = (float)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = ? AND status = 'pending'");
$stmt->execute([$tenant['tenant_id']]);
$total_pending = (float)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM payments WHERE tenant_id = ?");
$stmt->execute([$tenant['tenant_id']]);
$total_count = (int)$stmt->fetchColumn();

$balance = (float)($tenant['balance'] ?? 0);

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-receipt"></i> My Payments</h1>
        <p class="page-subtitle">Your payment history and balance</p>
    </div>
    <a href="submit_payment.php" class="btn btn-primary">
        <i class="fas fa-credit-card me-1"></i> Make a Payment
    </a>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-list"></i></div>
            <div class="stat-value"><?php echo $total_count; ?></div>
            <div class="stat-label">Total Records</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:var(--green-bd)">
            <div class="stat-icon" style="color:var(--green)"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value" style="font-size:1.3rem;color:var(--green)"><?php echo formatCurrency($total_paid); ?></div>
            <div class="stat-label">Total Paid</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="<?php echo $balance > 0 ? 'border-color:var(--red-bd)' : 'border-color:var(--green-bd)'; ?>">
            <div class="stat-icon" style="color:<?php echo $balance > 0 ? 'var(--red)' : 'var(--green)'; ?>">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-value" style="font-size:1.3rem;color:<?php echo $balance > 0 ? 'var(--red)' : 'var(--green)'; ?>">
                <?php echo formatCurrency($balance); ?>
            </div>
            <div class="stat-label">Balance Due</div>
            <?php if ($balance > 0): ?>
            <a href="submit_payment.php" class="btn btn-primary btn-sm mt-2">Pay Now</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="<?php echo $total_pending > 0 ? 'border-color:var(--amber-bd)' : ''; ?>">
            <div class="stat-icon" style="color:var(--amber)"><i class="fas fa-clock"></i></div>
            <div class="stat-value" style="font-size:1.3rem;color:var(--amber)"><?php echo formatCurrency($total_pending); ?></div>
            <div class="stat-label">Awaiting Confirmation</div>
            <?php if ($total_pending > 0): ?>
            <div style="font-size:.72rem;color:var(--tx-3);margin-top:4px">Admin review in progress</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 align-items-end flex-wrap" data-no-spinner>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm" style="min-width:160px">
                    <option value="">All Payments</option>
                    <option value="paid"    <?php echo $status_filter === 'paid'    ? 'selected' : ''; ?>>Paid</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Confirmation</option>
                    <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    <option value="partial" <?php echo $status_filter === 'partial' ? 'selected' : ''; ?>>Partial</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
            <a href="mypayment.php" class="btn btn-secondary btn-sm"><i class="fas fa-sync me-1"></i>Reset</a>
        </form>
    </div>
</div>

<!-- Payment table -->
<div class="card">
    <div class="card-header" style="justify-content:space-between">
        <span><i class="fas fa-list"></i> Payment History
            <span style="font-weight:400;font-size:.8rem;color:var(--tx-3)">(<?php echo count($payments); ?> records)</span>
        </span>
        <a href="submit_payment.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> New Payment
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
        <div style="text-align:center;padding:56px 24px;color:var(--tx-3)">
            <i class="fas fa-receipt fa-2x d-block mb-3" style="opacity:.2"></i>
            No payment records found.
            <div style="margin-top:12px">
                <a href="submit_payment.php" class="btn btn-primary">
                    <i class="fas fa-credit-card me-1"></i> Make Your First Payment
                </a>
            </div>
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
                        <th>Reference</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td style="font-family:monospace;font-size:.79rem;color:var(--gold)">
                        <?php echo htmlspecialchars($p['receipt_number'] ?? '—'); ?>
                    </td>
                    <td style="color:var(--tx-2)"><?php echo formatDate($p['payment_date']); ?></td>
                    <td style="font-weight:700;color:var(--green)"><?php echo formatCurrency($p['amount']); ?></td>
                    <td style="color:var(--tx-3);font-size:.81rem">
                        <?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?>
                    </td>
                    <td style="color:var(--tx-3);font-size:.8rem">
                        <?php echo htmlspecialchars($p['reference_number'] ?? '—'); ?>
                    </td>
                    <td>
                        <span class="badge-status badge-<?php echo $p['status']; ?>">
                            <?php echo $p['status'] === 'pending' ? 'Awaiting Confirmation' : ucfirst($p['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'paid'): ?>
                        <a href="receipt.php?id=<?php echo $p['payment_id']; ?>"
                           class="btn btn-icon btn-outline-primary btn-sm"
                           target="_blank" title="View Receipt">
                            <i class="fas fa-file-invoice" style="font-size:.75rem"></i>
                        </a>
                        <?php else: ?>
                        <span style="font-size:.72rem;color:var(--tx-3)">Pending</span>
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

<?php include 'includes/footer.php'; ?>