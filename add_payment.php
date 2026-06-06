<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Record Payment';
$currentPage = 'payments';
$db = getDB();

$errors = [];
$old = [];
// Pre-fill tenant if passed from vendor page
$preselect_tenant = intval($_GET['tenant_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    if (empty($_POST['tenant_id']))      $errors[] = 'Vendor is required.';
    if (empty($_POST['amount']) || !is_numeric($_POST['amount']) || $_POST['amount'] <= 0) $errors[] = 'Valid amount is required.';
    if (empty($_POST['payment_date']))   $errors[] = 'Payment date is required.';
    if (empty($_POST['payment_method'])) $errors[] = 'Payment method is required.';

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Generate receipt number
            $yr = date('Y');
            $maxN = $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(receipt_number,10) AS UNSIGNED)),0) FROM payments WHERE receipt_number LIKE 'REC-$yr-%'")->fetchColumn();
            $receipt = 'REC-' . $yr . '-' . str_pad($maxN + 1, 4, '0', STR_PAD_LEFT);

            $db->prepare("INSERT INTO payments (tenant_id,amount,payment_date,due_date,payment_method,reference_number,receipt_number,status,remarks,processed_by)
                VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([
                   intval($_POST['tenant_id']),
                   floatval($_POST['amount']),
                   $_POST['payment_date'],
                   $_POST['due_date'] ?: null,
                   $_POST['payment_method'],
                   trim($_POST['reference_number'] ?? ''),
                   $receipt,
                   $_POST['status'],
                   trim($_POST['remarks'] ?? ''),
                   $_SESSION['user_id']
               ]);

            // Update balance if paid
            if ($_POST['status'] === 'paid') {
                $db->prepare("UPDATE tenants SET balance = balance - ? WHERE tenant_id=?")
                   ->execute([floatval($_POST['amount']), intval($_POST['tenant_id'])]);
            }

            $db->commit();
            setMessage('success', "Payment recorded! Receipt: $receipt");
            header('Location: payments.php'); exit();
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Vendors for dropdown
$vendors = $db->query("SELECT t.tenant_id, t.full_name, t.business_name, t.balance FROM tenants t WHERE t.status='active' ORDER BY t.full_name")->fetchAll();

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-receipt me-2"></i> Record Payment</h1>
        <p class="page-subtitle">Record a new vendor payment</p>
    </div>
    <a href="payments.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><strong>Please fix the following:</strong>
    <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card" style="max-width:700px">
    <div class="card-header"><i class="fas fa-money-bill-wave me-2"></i>Payment Details</div>
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Vendor <span class="text-danger">*</span></label>
                    <select name="tenant_id" class="form-select" required>
                        <option value="">Select vendor…</option>
                        <?php foreach ($vendors as $v): ?>
                        <option value="<?php echo $v['tenant_id']; ?>"
                            <?php echo (($old['tenant_id'] ?? $preselect_tenant) == $v['tenant_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($v['full_name']); ?> — <?php echo htmlspecialchars($v['business_name']); ?>
                            (Balance: <?php echo formatCurrency($v['balance']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Amount (₱) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                           value="<?php echo htmlspecialchars($old['amount'] ?? ''); ?>" required placeholder="0.00">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="paid"    <?php echo ($old['status'] ?? 'paid')==='paid'   ?'selected':'';?>>Paid</option>
                        <option value="pending" <?php echo ($old['status'] ?? '')==='pending'?'selected':'';?>>Pending</option>
                        <option value="partial" <?php echo ($old['status'] ?? '')==='partial'?'selected':'';?>>Partial</option>
                        <option value="overdue" <?php echo ($old['status'] ?? '')==='overdue'?'selected':'';?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" name="payment_date" class="form-control"
                           value="<?php echo $old['payment_date'] ?? date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control"
                           value="<?php echo htmlspecialchars($old['due_date'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="">Select…</option>
                        <option value="cash"          <?php echo ($old['payment_method'] ?? '')==='cash'         ?'selected':'';?>>Cash</option>
                        <option value="gcash"         <?php echo ($old['payment_method'] ?? '')==='gcash'        ?'selected':'';?>>GCash</option>
                        <option value="bank_transfer" <?php echo ($old['payment_method'] ?? '')==='bank_transfer'?'selected':'';?>>Bank Transfer</option>
                        <option value="check"         <?php echo ($old['payment_method'] ?? '')==='check'        ?'selected':'';?>>Check</option>
                        <option value="other"         <?php echo ($old['payment_method'] ?? '')==='other'        ?'selected':'';?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control"
                           value="<?php echo htmlspecialchars($old['reference_number'] ?? ''); ?>" placeholder="GCash / bank ref #">
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes…"><?php echo htmlspecialchars($old['remarks'] ?? ''); ?></textarea>
                </div>
            </div>
            <hr style="border-color:var(--gold-bd);margin:22px 0">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> Record Payment</button>
                <a href="payments.php" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>