<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Edit Payment';
$currentPage = 'payments';
$db = getDB();

$pid = intval($_GET['id'] ?? 0);
if (!$pid) { header('Location: payments.php'); exit(); }

$stmt = $db->prepare("SELECT p.*, t.full_name as tenant_name, t.business_name FROM payments p JOIN tenants t ON p.tenant_id=t.tenant_id WHERE p.payment_id=?");
$stmt->execute([$pid]); $payment = $stmt->fetch();
if (!$payment) { setMessage('danger','Payment not found.'); header('Location: payments.php'); exit(); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['amount']) || !is_numeric($_POST['amount']) || $_POST['amount'] <= 0) $errors[] = 'Valid amount is required.';
    if (empty($_POST['payment_date']))   $errors[] = 'Payment date is required.';
    if (empty($_POST['payment_method'])) $errors[] = 'Payment method is required.';

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            $old_status = $payment['status'];
            $old_amount = $payment['amount'];
            $new_status = $_POST['status'];
            $new_amount = floatval($_POST['amount']);

            $db->prepare("UPDATE payments SET amount=?,payment_date=?,due_date=?,payment_method=?,reference_number=?,status=?,remarks=?,processed_by=? WHERE payment_id=?")
               ->execute([
                   $new_amount,
                   $_POST['payment_date'],
                   $_POST['due_date'] ?: null,
                   $_POST['payment_method'],
                   trim($_POST['reference_number'] ?? ''),
                   $new_status,
                   trim($_POST['remarks'] ?? ''),
                   $_SESSION['user_id'],
                   $pid
               ]);

            // Adjust tenant balance
            if ($old_status === 'paid' && $new_status !== 'paid') {
                // Was paid, now not → restore balance
                $db->prepare("UPDATE tenants SET balance = balance + ? WHERE tenant_id=?")->execute([$old_amount, $payment['tenant_id']]);
            } elseif ($old_status !== 'paid' && $new_status === 'paid') {
                // Was not paid, now paid → reduce balance
                $db->prepare("UPDATE tenants SET balance = balance - ? WHERE tenant_id=?")->execute([$new_amount, $payment['tenant_id']]);
            } elseif ($old_status === 'paid' && $new_status === 'paid' && $old_amount != $new_amount) {
                // Both paid but amount changed → adjust difference
                $diff = $new_amount - $old_amount;
                $db->prepare("UPDATE tenants SET balance = balance - ? WHERE tenant_id=?")->execute([$diff, $payment['tenant_id']]);
            }

            $db->commit();
            setMessage('success', 'Payment updated successfully!');
            header('Location: payments.php'); exit();
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    $payment = array_merge($payment, $_POST);
}

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-edit me-2"></i> Edit Payment</h1>
        <p class="page-subtitle">Receipt: <?php echo htmlspecialchars($payment['receipt_number'] ?? '—'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="receipt.php?id=<?php echo $pid; ?>" class="btn btn-outline-primary" target="_blank"><i class="fas fa-file-invoice me-1"></i>View Receipt</a>
        <a href="payments.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><strong>Errors:</strong>
    <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card" style="max-width:700px">
    <div class="card-header"><i class="fas fa-money-bill-wave me-2"></i>Payment Details</div>
    <div class="card-body">
        <div class="mb-4 p-3 rounded" style="background:rgba(255,215,0,.04);border:1px solid var(--gold-bd)">
            <div style="font-size:.78rem;color:var(--txt-mut)">Vendor</div>
            <div style="font-weight:700;font-size:1rem"><?php echo htmlspecialchars($payment['tenant_name']); ?></div>
            <div style="font-size:.82rem;color:var(--txt-mut)"><?php echo htmlspecialchars($payment['business_name']); ?></div>
        </div>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Amount (₱) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                           value="<?php echo htmlspecialchars($payment['amount']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="paid"    <?php echo $payment['status']==='paid'   ?'selected':'';?>>Paid</option>
                        <option value="pending" <?php echo $payment['status']==='pending'?'selected':'';?>>Pending</option>
                        <option value="partial" <?php echo $payment['status']==='partial'?'selected':'';?>>Partial</option>
                        <option value="overdue" <?php echo $payment['status']==='overdue'?'selected':'';?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" name="payment_date" class="form-control"
                           value="<?php echo htmlspecialchars($payment['payment_date']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control"
                           value="<?php echo htmlspecialchars($payment['due_date'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cash"          <?php echo $payment['payment_method']==='cash'         ?'selected':'';?>>Cash</option>
                        <option value="gcash"         <?php echo $payment['payment_method']==='gcash'        ?'selected':'';?>>GCash</option>
                        <option value="bank_transfer" <?php echo $payment['payment_method']==='bank_transfer'?'selected':'';?>>Bank Transfer</option>
                        <option value="check"         <?php echo $payment['payment_method']==='check'        ?'selected':'';?>>Check</option>
                        <option value="other"         <?php echo $payment['payment_method']==='other'        ?'selected':'';?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference_number" class="form-control"
                           value="<?php echo htmlspecialchars($payment['reference_number'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2"><?php echo htmlspecialchars($payment['remarks'] ?? ''); ?></textarea>
                </div>
            </div>
            <hr style="border-color:var(--gold-bd);margin:22px 0">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> Update Payment</button>
                <a href="payments.php" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>