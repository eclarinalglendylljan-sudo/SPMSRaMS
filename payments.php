<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Payment Management';
$currentPage = 'payments';
$db = getDB();

// Delete payment
if (isset($_GET['delete'])) {
    try {
        $pid = intval($_GET['delete']);
        // Restore balance if was paid
        $chk = $db->prepare("SELECT tenant_id, amount, status FROM payments WHERE payment_id=?");
        $chk->execute([$pid]); $row = $chk->fetch();
        if ($row && $row['status'] === 'paid') {
            $db->prepare("UPDATE tenants SET balance = balance + ? WHERE tenant_id=?")->execute([$row['amount'], $row['tenant_id']]);
        }
        $db->prepare("DELETE FROM payments WHERE payment_id=?")->execute([$pid]);
        setMessage('success', 'Payment deleted.');
    } catch (Exception $e) { setMessage('danger', 'Error: ' . $e->getMessage()); }
    header('Location: payments.php'); exit();
}

// Filters
$search    = $_GET['search'] ?? '';
$status    = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? '';

$q = "SELECT p.*, t.full_name as tenant_name, t.business_name
      FROM payments p JOIN tenants t ON p.tenant_id=t.tenant_id WHERE 1=1";
$par = [];
if ($search)    { $q .= " AND (t.full_name LIKE ? OR t.business_name LIKE ? OR p.receipt_number LIKE ?)"; $s="%$search%"; $par[]=$s;$par[]=$s;$par[]=$s; }
if ($status)    { $q .= " AND p.status=?"; $par[]=$status; }
if ($date_from) { $q .= " AND p.payment_date >= ?"; $par[]=$date_from; }
if ($date_to)   { $q .= " AND p.payment_date <= ?"; $par[]=$date_to; }
$q .= " ORDER BY p.payment_date DESC, p.created_at DESC";
$stmt=$db->prepare($q); $stmt->execute($par); $payments=$stmt->fetchAll();

$total_rev   = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetchColumn();
$month_rev   = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())")->fetchColumn();
$pending_cnt = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
$overdue_cnt = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status='overdue'")->fetchColumn();

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-money-bill-wave"></i> Payment Management</h1>
        <p class="page-subtitle">Track and manage all vendor payments</p>
    </div>
    <a href="add_payment.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Record Payment</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-peso-sign"></i></div><div class="stat-value" style="font-size:1.4rem"><?php echo formatCurrency($total_rev);?></div><div class="stat-label">Total Collected</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(40,167,69,.4)"><div class="stat-icon" style="color:var(--green)"><i class="fas fa-calendar-check"></i></div><div class="stat-value" style="color:var(--green);font-size:1.4rem"><?php echo formatCurrency($month_rev);?></div><div class="stat-label">This Month</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(255,193,7,.4)"><div class="stat-icon" style="color:var(--amber)"><i class="fas fa-clock"></i></div><div class="stat-value" style="color:var(--amber)"><?php echo $pending_cnt;?></div><div class="stat-label">Pending</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(220,53,69,.4)"><div class="stat-icon" style="color:var(--red)"><i class="fas fa-exclamation-circle"></i></div><div class="stat-value" style="color:var(--red)"><?php echo $overdue_cnt;?></div><div class="stat-label">Overdue</div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" data-no-spinner>
            <div class="col-md-3"><label class="form-label">Search</label><input type="text" name="search" class="form-control" placeholder="Vendor or receipt#" value="<?php echo htmlspecialchars($search);?>"></div>
            <div class="col-md-2"><label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="paid"    <?php echo $status==='paid'   ?'selected':'';?>>Paid</option>
                    <option value="pending" <?php echo $status==='pending'?'selected':'';?>>Pending</option>
                    <option value="overdue" <?php echo $status==='overdue'?'selected':'';?>>Overdue</option>
                    <option value="partial" <?php echo $status==='partial'?'selected':'';?>>Partial</option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from);?>"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to);?>"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button></div>
            <div class="col-md-1"><a href="payments.php" class="btn btn-secondary w-100"><i class="fas fa-sync"></i></a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-list me-1"></i> Payments <span style="font-weight:400;font-size:.82rem;color:var(--txt-mut)">(<?php echo count($payments);?> records)</span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Receipt #</th><th>Vendor</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th><th style="width:120px">Actions</th></tr></thead>
                <tbody>
                <?php if(empty($payments)):?>
                <tr><td colspan="7" class="text-center py-5" style="color:var(--txt-mut)">
                    <i class="fas fa-receipt fa-2x d-block mb-2" style="opacity:.3"></i>No payments found.
                    <a href="add_payment.php" class="btn btn-primary btn-sm ms-2"><i class="fas fa-plus me-1"></i>Record Payment</a>
                </td></tr>
                <?php else: foreach($payments as $p):?>
                <tr>
                    <td style="font-family:monospace;font-size:.82rem;color:var(--gold)"><?php echo htmlspecialchars($p['receipt_number']??'—');?></td>
                    <td>
                        <div style="font-weight:600"><?php echo htmlspecialchars($p['tenant_name']);?></div>
                        <div style="font-size:.75rem;color:var(--txt-mut)"><?php echo htmlspecialchars($p['business_name']);?></div>
                    </td>
                    <td style="font-weight:700;color:var(--green);font-size:1rem"><?php echo formatCurrency($p['amount']);?></td>
                    <td style="color:var(--txt-mut)"><?php echo formatDate($p['payment_date']);?></td>
                    <td style="font-size:.82rem"><?php echo ucfirst(str_replace('_',' ',$p['payment_method']));?></td>
                    <td><span class="badge-status badge-<?php echo $p['status'];?>"><?php echo ucfirst($p['status']);?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="receipt.php?id=<?php echo $p['payment_id'];?>" class="btn btn-sm btn-outline-primary" target="_blank" title="Receipt"><i class="fas fa-file-invoice"></i></a>
                            <a href="edit_payment.php?id=<?php echo $p['payment_id'];?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="payments.php?delete=<?php echo $p['payment_id'];?>" class="btn btn-sm btn-outline-danger" title="Delete"
                               onclick="return confirm('Delete this payment record?\nThis will restore the vendor balance.')"><i class="fas fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif;?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>