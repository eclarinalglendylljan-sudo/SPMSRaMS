<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Vendor Profile';
$currentPage = 'vendors';
$db = getDB();

$tid = intval($_GET['id'] ?? 0);
if (!$tid) { header('Location: vendors.php'); exit(); }

$stmt = $db->prepare("SELECT t.*, u.username, u.status as user_status, u.last_login FROM tenants t LEFT JOIN users u ON t.user_id=u.user_id WHERE t.tenant_id=?");
$stmt->execute([$tid]); $vendor = $stmt->fetch();
if (!$vendor) { setMessage('danger','Vendor not found.'); header('Location: vendors.php'); exit(); }

$rental = $db->prepare("SELECT rr.*, ms.stall_number, ms.section, ms.location, ms.size_sqm FROM rental_records rr JOIN market_stalls ms ON rr.stall_id=ms.stall_id WHERE rr.tenant_id=? AND rr.status='active' LIMIT 1");
$rental->execute([$tid]); $rental = $rental->fetch();

$payments = $db->prepare("SELECT * FROM payments WHERE tenant_id=? ORDER BY payment_date DESC LIMIT 10");
$payments->execute([$tid]); $payments = $payments->fetchAll();

$maintenance = $db->prepare("SELECT mr.*, ms.stall_number FROM maintenance_requests mr JOIN market_stalls ms ON mr.stall_id=ms.stall_id WHERE mr.tenant_id=? ORDER BY mr.request_date DESC LIMIT 5");
$maintenance->execute([$tid]); $maintenance = $maintenance->fetchAll();

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($vendor['full_name']); ?></h1>
        <p class="page-subtitle"><?php echo htmlspecialchars($vendor['business_name']); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="edit_vendor.php?id=<?php echo $tid;?>" class="btn btn-warning"><i class="fas fa-edit me-1"></i>Edit</a>
        <a href="vendors.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Left: info -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-id-card me-2"></i>Vendor Information</div>
            <div class="card-body">
                <div class="row g-3" style="font-size:.88rem">
                    <div class="col-md-6"><div style="color:var(--txt-mut)">Full Name</div><strong><?php echo htmlspecialchars($vendor['full_name']);?></strong></div>
                    <div class="col-md-6"><div style="color:var(--txt-mut)">Contact</div><strong><?php echo htmlspecialchars($vendor['contact_no']);?></strong></div>
                    <div class="col-md-6"><div style="color:var(--txt-mut)">Email</div><?php echo htmlspecialchars($vendor['email']??'—');?></div>
                    <div class="col-md-6"><div style="color:var(--txt-mut)">Status</div><span class="badge-status badge-<?php echo $vendor['status'];?>"><?php echo ucfirst($vendor['status']);?></span></div>
                    <div class="col-12"><div style="color:var(--txt-mut)">Address</div><?php echo htmlspecialchars($vendor['address']??'—');?></div>
                    <div class="col-md-6"><div style="color:var(--txt-mut)">Business Name</div><strong><?php echo htmlspecialchars($vendor['business_name']);?></strong></div>
                    <div class="col-md-6"><div style="color:var(--txt-mut)">Business Type</div><?php echo htmlspecialchars($vendor['business_type']??'—');?></div>
                    <div class="col-md-6"><div style="color:var(--txt-mut)">Vendor Type</div><span class="badge-status <?php echo $vendor['tenant_type']==='permanent'?'badge-active':'badge-reserved';?>"><?php echo ucfirst($vendor['tenant_type']);?></span></div>
                    <div class="col-md-6"><div style="color:var(--txt-mut)">Member Since</div><?php echo formatDate($vendor['created_at']);?></div>
                    <?php if($vendor['username']):?>
                    <div class="col-md-6"><div style="color:var(--txt-mut)">Username</div><span style="color:var(--gold);font-weight:600"><?php echo htmlspecialchars($vendor['username']);?></span></div>
                    <div class="col-md-6"><div style="color:var(--txt-mut)">Last Login</div><?php echo $vendor['last_login']?formatDate($vendor['last_login']):'Never';?></div>
                    <?php endif;?>
                </div>
            </div>
        </div>

        <!-- Payments -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-money-bill-wave me-2"></i>Recent Payments</span>
                <a href="add_payment.php?tenant_id=<?php echo $tid;?>" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Record Payment</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($payments)): ?>
                <div class="text-center py-4" style="color:var(--txt-mut)"><i class="fas fa-receipt fa-2x d-block mb-2" style="opacity:.3"></i>No payments recorded</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Receipt</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?php echo formatDate($p['payment_date']);?></td>
                            <td style="font-weight:600;color:var(--green)"><?php echo formatCurrency($p['amount']);?></td>
                            <td style="font-size:.8rem"><?php echo ucfirst(str_replace('_',' ',$p['payment_method']));?></td>
                            <td><span class="badge-status badge-<?php echo $p['status'];?>"><?php echo ucfirst($p['status']);?></span></td>
                            <td><a href="receipt.php?id=<?php echo $p['payment_id'];?>" class="btn btn-icon btn-sm btn-outline-primary" target="_blank"><i class="fas fa-file-invoice"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Maintenance -->
        <div class="card">
            <div class="card-header"><i class="fas fa-tools me-2"></i>Maintenance Requests</div>
            <div class="card-body p-0">
                <?php if (empty($maintenance)): ?>
                <div class="text-center py-4" style="color:var(--txt-mut)"><i class="fas fa-check-circle fa-2x d-block mb-2" style="opacity:.3;color:var(--green)"></i>No maintenance requests</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Title</th><th>Stall</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($maintenance as $m): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['title']);?></td>
                            <td><span style="background:var(--gold-dim);color:var(--gold);padding:1px 7px;border-radius:4px;font-size:.75rem;font-weight:700"><?php echo htmlspecialchars($m['stall_number']);?></span></td>
                            <td><span style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:<?php echo ['urgent'=>'var(--red)','high'=>'var(--amber)','medium'=>'var(--blue)','low'=>'var(--gray)'][$m['priority']]??'var(--gray)';?>"><?php echo $m['priority'];?></span></td>
                            <td><span class="badge-status badge-<?php echo $m['status'];?>"><?php echo ucfirst(str_replace('_',' ',$m['status']));?></span></td>
                            <td style="font-size:.8rem;color:var(--txt-mut)"><?php echo formatDate($m['request_date']);?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right sidebar -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-wallet me-2"></i>Balance</div>
            <div class="card-body text-center py-4">
                <div style="font-size:2.2rem;font-weight:800;color:<?php echo ($vendor['balance']??0)>0?'var(--red)':'var(--green)';?>">
                    <?php echo formatCurrency($vendor['balance']??0);?>
                </div>
                <div style="font-size:.78rem;color:var(--txt-mut);margin-top:6px">
                    <?php echo ($vendor['balance']??0)>0?'Outstanding Balance':'No Outstanding Balance';?>
                </div>
            </div>
        </div>

        <?php if ($rental): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-store me-2"></i>Active Stall</div>
            <div class="card-body" style="font-size:.86rem">
                <div class="d-flex justify-content-between mb-2">
                    <span style="color:var(--txt-mut)">Stall #</span>
                    <strong style="color:var(--gold);font-size:1.1rem"><?php echo htmlspecialchars($rental['stall_number']);?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="color:var(--txt-mut)">Section</span>
                    <strong><?php echo htmlspecialchars($rental['section']);?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="color:var(--txt-mut)">Monthly Rate</span>
                    <strong style="color:var(--green)"><?php echo formatCurrency($rental['monthly_rate']);?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span style="color:var(--txt-mut)">Start Date</span>
                    <strong><?php echo formatDate($rental['start_date']);?></strong>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>