<?php
require_once 'config.php';
requireLogin();
requirePermission('vendor');

$pageTitle   = 'Submit Payment';
$currentPage = 'my-payments';
$db = getDB();

// Get tenant
$stmt = $db->prepare("SELECT * FROM tenants WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tenant = $stmt->fetch();
if (!$tenant) { header('Location: vendorapplication.php'); exit(); }

// Get active rental for monthly rate suggestion
$stmt = $db->prepare("
    SELECT rr.*, ms.stall_number, ms.section, ms.payment_due_day
    FROM rental_records rr
    JOIN market_stalls ms ON rr.stall_id = ms.stall_id
    WHERE rr.tenant_id = ? AND rr.status = 'active'
    LIMIT 1
");
$stmt->execute([$tenant['tenant_id']]);
$rental = $stmt->fetch();

$balance  = (float)($tenant['balance'] ?? 0);
$errors   = [];
$old      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $amount  = trim($_POST['amount'] ?? '');
    $method  = $_POST['payment_method'] ?? '';
    $date    = $_POST['payment_date'] ?? '';
    $ref     = trim($_POST['reference_number'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    // Validate
    if (!is_numeric($amount) || floatval($amount) <= 0) {
        $errors[] = 'Please enter a valid payment amount greater than ₱0.';
    }
    if (empty($method)) {
        $errors[] = 'Please select a payment method.';
    }
    if (empty($date)) {
        $errors[] = 'Payment date is required.';
    }
    // GCash / bank transfer require a reference number
    if (in_array($method, ['gcash', 'bank_transfer']) && empty($ref)) {
        $errors[] = 'Reference number is required for ' . ucfirst(str_replace('_', ' ', $method)) . ' payments.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Generate receipt number
            $yr   = date('Y');
            $maxN = (int)$db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(receipt_number,10) AS UNSIGNED)),0) FROM payments WHERE receipt_number LIKE 'REC-{$yr}-%'")->fetchColumn();
            $receipt = 'REC-' . $yr . '-' . str_pad($maxN + 1, 4, '0', STR_PAD_LEFT);

            // Insert payment as "pending" — admin will confirm/mark as paid
            $db->prepare("
                INSERT INTO payments
                    (tenant_id, amount, payment_date, payment_method,
                     reference_number, receipt_number, status, remarks)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
            ")->execute([
                $tenant['tenant_id'],
                floatval($amount),
                $date,
                $method,
                $ref ?: null,
                $receipt,
                $remarks ?: null,
            ]);

            $db->commit();

            setMessage('success', "Payment submitted! Receipt #{$receipt}. An admin will confirm your payment shortly.");
            header('Location: mypayment.php');
            exit();

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
        <h1 class="page-title"><i class="fas fa-credit-card"></i> Submit Payment</h1>
        <p class="page-subtitle">Record your payment — admin will confirm receipt</p>
    </div>
    <a href="mystall.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to My Stall
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i>
    <strong><?php echo count($errors) === 1 ? htmlspecialchars($errors[0]) : 'Please fix the following:'; ?></strong>
    <?php if (count($errors) > 1): ?>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── Payment form ─────────────────────────────────────── -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="fas fa-money-bill-wave"></i> Payment Details</div>
            <div class="card-body">
                <form method="POST" data-no-spinner>
                    <div class="row g-3">

                        <!-- Amount -->
                        <div class="col-12">
                            <label class="form-label">Amount (₱) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number"
                                       name="amount"
                                       class="form-control form-control-lg"
                                       step="0.01" min="1"
                                       value="<?php echo htmlspecialchars($old['amount'] ?? ($balance > 0 ? number_format($balance, 2, '.', '') : ($rental ? number_format($rental['monthly_rate'], 2, '.', '') : ''))); ?>"
                                       placeholder="0.00"
                                       required>
                            </div>
                            <?php if ($balance > 0): ?>
                            <div class="form-text" style="color:var(--red)">
                                <i class="fas fa-info-circle me-1"></i>
                                Your outstanding balance is <strong><?php echo formatCurrency($balance); ?></strong>
                            </div>
                            <?php elseif ($rental): ?>
                            <div class="form-text">
                                Monthly rate: <strong style="color:var(--green)"><?php echo formatCurrency($rental['monthly_rate']); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Payment Method -->
                        <div class="col-md-6">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" id="payMethod" class="form-select" required
                                    onchange="toggleRefField(this.value)">
                                <option value="">Select method…</option>
                                <option value="cash"          <?php echo ($old['payment_method'] ?? '') === 'cash'          ? 'selected' : ''; ?>>💵 Cash</option>
                                <option value="gcash"         <?php echo ($old['payment_method'] ?? '') === 'gcash'         ? 'selected' : ''; ?>>📱 GCash</option>
                                <option value="bank_transfer" <?php echo ($old['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>🏦 Bank Transfer</option>
                                <option value="check"         <?php echo ($old['payment_method'] ?? '') === 'check'         ? 'selected' : ''; ?>>📄 Check</option>
                                <option value="other"         <?php echo ($old['payment_method'] ?? '') === 'other'         ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <!-- Payment Date -->
                        <div class="col-md-6">
                            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date"
                                   name="payment_date"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($old['payment_date'] ?? date('Y-m-d')); ?>"
                                   max="<?php echo date('Y-m-d'); ?>"
                                   required>
                        </div>

                        <!-- Reference Number (shown for GCash / bank) -->
                        <div class="col-12" id="refField" style="display:<?php echo in_array($old['payment_method'] ?? '', ['gcash','bank_transfer','check']) ? 'block' : 'none'; ?>">
                            <label class="form-label">Reference / Transaction Number <span class="text-danger" id="refRequired">*</span></label>
                            <input type="text"
                                   name="reference_number"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($old['reference_number'] ?? ''); ?>"
                                   placeholder="e.g. GCash transaction ID, bank reference #">
                            <div class="form-text">Required for GCash, bank transfer, and check payments</div>
                        </div>

                        <!-- Remarks -->
                        <div class="col-12">
                            <label class="form-label">Remarks / Notes <span style="color:var(--tx-3)">(optional)</span></label>
                            <textarea name="remarks" class="form-control" rows="3"
                                      placeholder="e.g. Payment for January 2026 rent…"><?php echo htmlspecialchars($old['remarks'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <hr style="border-color:var(--border);margin:22px 0">

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-1"></i> Submit Payment
                        </button>
                        <a href="mystall.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Right sidebar: info ───────────────────────────────── -->
    <div class="col-lg-5">

        <!-- Balance summary -->
        <div class="card mb-3" style="<?php echo $balance > 0 ? 'border-color:var(--red-bd)' : 'border-color:var(--green-bd)'; ?>">
            <div class="card-header"><i class="fas fa-wallet"></i> Your Balance</div>
            <div class="card-body" style="text-align:center;padding:20px">
                <div style="font-size:1.9rem;font-weight:800;letter-spacing:-1px;color:<?php echo $balance > 0 ? 'var(--red)' : 'var(--green)'; ?>">
                    <?php echo formatCurrency($balance); ?>
                </div>
                <div style="font-size:.78rem;color:var(--tx-3);margin-top:4px">
                    <?php echo $balance > 0 ? 'Outstanding balance' : 'No outstanding balance'; ?>
                </div>
            </div>
        </div>

        <?php if ($rental): ?>
        <!-- Stall info -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-store"></i> Your Stall</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row">
                    <span class="info-key">Stall #</span>
                    <span class="info-val" style="color:var(--gold);font-weight:700"><?php echo htmlspecialchars($rental['stall_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Section</span>
                    <span class="info-val"><?php echo htmlspecialchars($rental['section']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Monthly Rate</span>
                    <span class="info-val" style="color:var(--green)"><?php echo formatCurrency($rental['monthly_rate']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Due Day</span>
                    <span class="info-val">Day <?php echo intval($rental['payment_due_day'] ?? 1); ?> of month</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- How it works -->
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle"></i> How It Works</div>
            <div class="card-body" style="font-size:.83rem;color:var(--tx-2);line-height:1.8">
                <div style="display:flex;gap:10px;margin-bottom:12px">
                    <div style="width:22px;height:22px;border-radius:50%;background:var(--gold);color:#000;font-weight:800;font-size:.72rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">1</div>
                    <div>Fill in the payment details and click <strong>Submit Payment</strong></div>
                </div>
                <div style="display:flex;gap:10px;margin-bottom:12px">
                    <div style="width:22px;height:22px;border-radius:50%;background:var(--gold);color:#000;font-weight:800;font-size:.72rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">2</div>
                    <div>Your payment is recorded as <span class="badge-status badge-pending">Pending</span></div>
                </div>
                <div style="display:flex;gap:10px">
                    <div style="width:22px;height:22px;border-radius:50%;background:var(--gold);color:#000;font-weight:800;font-size:.72rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">3</div>
                    <div>Admin confirms receipt and marks it as <span class="badge-status badge-paid">Paid</span> — your balance updates automatically</div>
                </div>

                <div style="border-top:1px solid var(--border);margin-top:14px;padding-top:12px;color:var(--tx-3)">
                    <i class="fas fa-phone me-1" style="color:var(--gold)"></i>
                    Questions? Call <strong>(036) 123-4567</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRefField(method) {
    var show   = ['gcash', 'bank_transfer', 'check'].includes(method);
    var field  = document.getElementById('refField');
    var star   = document.getElementById('refRequired');
    if (field) field.style.display = show ? 'block' : 'none';
    if (star)  star.style.display  = show ? 'inline' : 'none';
}
// Run on page load in case of validation error repopulation
toggleRefField(document.getElementById('payMethod')?.value || '');
</script>

<?php include 'includes/footer.php'; ?>