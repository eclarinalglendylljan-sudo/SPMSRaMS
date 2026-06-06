<?php
require_once 'config.php';
requireLogin();

$payment_id = intval($_GET['id'] ?? 0);
if (!$payment_id) { header('Location: dashboard.php'); exit(); }

$db = getDB();
$stmt = $db->prepare("
    SELECT p.*, t.full_name, t.business_name, t.address, t.contact_no,
           u.full_name AS processed_by_name
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.tenant_id
    LEFT JOIN users u ON p.processed_by = u.user_id
    WHERE p.payment_id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) { echo 'Payment not found.'; exit(); }

// Vendors can only see their own receipts
if (isVendor()) {
    $stmt = $db->prepare("SELECT tenant_id FROM tenants WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $tenant = $stmt->fetch();
    if (!$tenant || $tenant['tenant_id'] != $payment['tenant_id']) {
        echo 'Unauthorized.'; exit();
    }
}
$print_mode = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo htmlspecialchars($payment['receipt_number'] ?? ''); ?></title>
    <!-- LOCAL assets — no CDN, no network freeze -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <style>
        body { background:#f0f0f0; padding:24px; font-family:-apple-system,'Segoe UI',Arial,sans-serif; }
        .receipt { background:#fff; max-width:720px; margin:0 auto; padding:44px; box-shadow:0 4px 24px rgba(0,0,0,.12); border-radius:4px; }
        .receipt-header { text-align:center; border-bottom:3px solid #FFD700; padding-bottom:22px; margin-bottom:28px; }
        .receipt-header h2 { color:#111; font-weight:800; margin-bottom:4px; }
        .receipt-header p  { color:#666; margin:0; font-size:.9rem; }
        .receipt-num { display:inline-block; background:#FFD700; color:#000; font-weight:700; font-size:1.1rem; padding:8px 22px; margin:16px 0 0; border-radius:4px; }
        .section-title { color:#c8a800; font-weight:700; font-size:.82rem; text-transform:uppercase; letter-spacing:1px; border-bottom:2px solid #FFD700; padding-bottom:5px; margin:22px 0 12px; }
        .info-row { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px dashed #e5e5e5; font-size:.88rem; }
        .info-label { font-weight:600; color:#333; }
        .info-value { color:#555; }
        .amount-box { background:#fffbe6; border:2px solid #FFD700; border-radius:6px; padding:20px; text-align:center; margin:24px 0; }
        .amount-box .lbl { font-size:.82rem; font-weight:600; color:#666; text-transform:uppercase; letter-spacing:.8px; }
        .amount-box .val { font-size:2.2rem; font-weight:800; color:#c8a800; line-height:1.2; margin-top:4px; }
        .qr-box { text-align:center; padding:20px; background:#f8f9fa; border:2px dashed #FFD700; border-radius:6px; margin:20px 0; }
        .footer-notes { border-top:2px solid #FFD700; padding-top:18px; font-size:.82rem; color:#777; margin-top:30px; }
        .status-badge { display:inline-block; padding:3px 12px; border-radius:20px; font-size:.78rem; font-weight:700; }
        .status-paid { background:#d4edda; color:#155724; }
        .status-pending { background:#fff3cd; color:#856404; }
        @media print {
            body { background:#fff; padding:0; }
            .receipt { box-shadow:none; }
            .no-print { display:none!important; }
        }
    </style>
</head>
<body>
<div class="receipt">

    <div class="receipt-header">
        <?php if (file_exists('assets/images/logo1.png')): ?>
            <img src="assets/images/logo1.png" alt="Sibalom" style="height:56px;margin-bottom:8px">
        <?php endif; ?>
        <h2>OFFICIAL RECEIPT</h2>
        <p>Sibalom Market Stall Rental and Mapping System</p>
        <p>Municipality of Sibalom, Antique</p>
        <div class="receipt-num">Receipt #: <?php echo htmlspecialchars($payment['receipt_number'] ?? 'N/A'); ?></div>
    </div>

    <div class="section-title">Tenant Information</div>
    <div class="info-row"><span class="info-label">Name</span><span class="info-value"><?php echo htmlspecialchars($payment['full_name']); ?></span></div>
    <div class="info-row"><span class="info-label">Business</span><span class="info-value"><?php echo htmlspecialchars($payment['business_name'] ?? '—'); ?></span></div>
    <div class="info-row"><span class="info-label">Contact</span><span class="info-value"><?php echo htmlspecialchars($payment['contact_no'] ?? '—'); ?></span></div>
    <?php if ($payment['address']): ?>
    <div class="info-row"><span class="info-label">Address</span><span class="info-value"><?php echo htmlspecialchars($payment['address']); ?></span></div>
    <?php endif; ?>

    <div class="section-title">Payment Details</div>
    <div class="info-row"><span class="info-label">Payment Date</span><span class="info-value"><?php echo formatDate($payment['payment_date']); ?></span></div>
    <div class="info-row"><span class="info-label">Payment Method</span><span class="info-value"><?php echo ucfirst(str_replace('_',' ',$payment['payment_method'] ?? '')); ?></span></div>
    <?php if ($payment['reference_number']): ?>
    <div class="info-row"><span class="info-label">Reference No.</span><span class="info-value"><?php echo htmlspecialchars($payment['reference_number']); ?></span></div>
    <?php endif; ?>
    <?php if ($payment['due_date']): ?>
    <div class="info-row"><span class="info-label">Due Date</span><span class="info-value"><?php echo formatDate($payment['due_date']); ?></span></div>
    <?php endif; ?>
    <div class="info-row">
        <span class="info-label">Status</span>
        <span class="info-value">
            <span class="status-badge status-<?php echo $payment['status']==='paid'?'paid':'pending'; ?>">
                <?php echo strtoupper($payment['status'] ?? ''); ?>
            </span>
        </span>
    </div>
    <?php if ($payment['processed_by_name']): ?>
    <div class="info-row"><span class="info-label">Processed By</span><span class="info-value"><?php echo htmlspecialchars($payment['processed_by_name']); ?></span></div>
    <?php endif; ?>

    <div class="amount-box">
        <div class="lbl">Total Amount Paid</div>
        <div class="val"><?php echo formatCurrency($payment['amount']); ?></div>
    </div>

    <?php if ($payment['remarks']): ?>
    <div class="section-title">Remarks</div>
    <p style="font-size:.88rem;color:#555"><?php echo nl2br(htmlspecialchars($payment['remarks'])); ?></p>
    <?php endif; ?>

    <?php if (!empty($payment['qr_code']) && file_exists('uploads/qr_codes/'.$payment['qr_code'])): ?>
    <div class="qr-box">
        <div style="font-size:.8rem;font-weight:700;color:#c8a800;margin-bottom:8px">PAYMENT VERIFICATION QR CODE</div>
        <img src="uploads/qr_codes/<?php echo htmlspecialchars($payment['qr_code']); ?>" alt="QR Code" style="max-width:200px">
        <p style="font-size:.78rem;color:#777;margin-top:8px">Scan to verify payment authenticity</p>
    </div>
    <?php endif; ?>

    <div class="footer-notes">
        <p><strong>Note:</strong> This is an official receipt from the Municipality of Sibalom Market Management. Please keep this receipt for your records.</p>
        <p style="margin-top:8px"><strong>Contact:</strong> Market Administration Office — (036) 123-4567 · admin@sibalom.gov.ph</p>
        <p style="margin-top:12px;font-size:.76rem;color:#aaa">Generated: <?php echo date('F d, Y h:i A'); ?></p>
    </div>

    <div class="text-center mt-4 no-print" style="display:flex;gap:10px;justify-content:center">
        <button onclick="window.print()" class="btn btn-warning fw-bold">
            <i class="fas fa-print me-1"></i> Print Receipt
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i> Close
        </button>
    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<?php if ($print_mode): ?><script>window.onload=function(){window.print()}</script><?php endif; ?>
</body>
</html>