<?php
require_once 'config.php';
requireLogin();

// Vendors go directly to their stall page — they have no dashboard
if ($_SESSION['role'] === 'vendor') {
    header('Location: mystall.php');
    exit();
}
// Applicants go to their application page
if ($_SESSION['role'] === 'applicant') {
    header('Location: applicant-dashboard.php');
    exit();
}

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
$db = getDB();

// Stats
$s = [];
$s['vendors']    = (int)$db->query("SELECT COUNT(*) FROM tenants WHERE status='active'")->fetchColumn();
$s['stalls']     = (int)$db->query("SELECT COUNT(*) FROM market_stalls")->fetchColumn();
$s['occupied']   = (int)$db->query("SELECT COUNT(*) FROM market_stalls WHERE status='occupied'")->fetchColumn();
$s['available']  = $s['stalls'] - $s['occupied'];
$s['revenue']    = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())")->fetchColumn();
$s['rev_prev']   = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND MONTH(payment_date)=MONTH(CURDATE()-INTERVAL 1 MONTH) AND YEAR(payment_date)=YEAR(CURDATE()-INTERVAL 1 MONTH)")->fetchColumn();
$s['maintenance']= (int)$db->query("SELECT COUNT(*) FROM maintenance_requests WHERE status='pending'")->fetchColumn();
$s['apps']       = (int)$db->query("SELECT COUNT(*) FROM rental_applications WHERE status='pending'")->fetchColumn();
$occ_pct = $s['stalls'] > 0 ? round(($s['occupied'] / $s['stalls']) * 100) : 0;
$rev_trend = $s['rev_prev'] > 0 ? round((($s['revenue'] - $s['rev_prev']) / $s['rev_prev']) * 100, 1) : 0;

// Recent payments
$payments = $db->query("SELECT p.*, t.full_name AS name, t.business_name FROM payments p JOIN tenants t ON p.tenant_id=t.tenant_id ORDER BY p.payment_date DESC LIMIT 6")->fetchAll();

// Recent maintenance
$maintenance = $db->query("SELECT mr.*, ms.stall_number FROM maintenance_requests mr JOIN market_stalls ms ON mr.stall_id=ms.stall_id ORDER BY mr.request_date DESC LIMIT 6")->fetchAll();

// Monthly revenue (6 months)
$monthly = $db->query("SELECT DATE_FORMAT(payment_date,'%b') AS mo, SUM(amount) AS total FROM payments WHERE status='paid' AND payment_date >= CURDATE()-INTERVAL 6 MONTH GROUP BY YEAR(payment_date),MONTH(payment_date) ORDER BY YEAR(payment_date),MONTH(payment_date)")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
        <p class="page-subtitle"><?php echo date('l, F j, Y'); ?> &nbsp;·&nbsp; Welcome back, <strong style="color:var(--gold)"><?php echo htmlspecialchars(explode(' ',$_SESSION['full_name']??'')[0]); ?></strong></p>
    </div>
    <div class="ph-actions">
        <?php if (isStaff()): ?>
        <a href="add_stall.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Add Stall</a>
        <a href="add_payment.php" class="btn btn-secondary btn-sm"><i class="fas fa-receipt me-1"></i> Record Payment</a>
        <?php endif; ?>
    </div>
</div>

<!-- ── KPI ROW ───────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?php echo $s['vendors']; ?></div>
            <div class="stat-label">Active Vendors</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3 mb-3">
                <!-- Occupancy ring -->
                <div style="position:relative;width:52px;height:52px;flex-shrink:0">
                    <svg viewBox="0 0 36 36" style="width:52px;height:52px;transform:rotate(-90deg)">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="rgba(255,255,255,.06)" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--gold)" stroke-width="3"
                            stroke-dasharray="<?php echo $occ_pct; ?> <?php echo 100-$occ_pct; ?>"
                            stroke-linecap="round"/>
                    </svg>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800;color:var(--gold)"><?php echo $occ_pct; ?>%</div>
                </div>
                <div>
                    <div class="stat-value" style="font-size:1.5rem"><?php echo $s['occupied']; ?><span style="font-size:.9rem;color:var(--tx-3);font-weight:500">/<?php echo $s['stalls']; ?></span></div>
                    <div class="stat-label" style="margin-top:2px">Occupancy</div>
                </div>
            </div>
            <div class="progress" style="height:3px"><div class="progress-bar" style="width:<?php echo $occ_pct; ?>%"></div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
            <div class="stat-value" style="font-size:1.4rem"><?php echo formatCurrency($s['revenue']); ?></div>
            <div class="stat-label">Monthly Revenue</div>
            <?php if ($rev_trend !== 0): ?>
            <div class="stat-trend <?php echo $rev_trend > 0 ? 'up' : 'down'; ?>">
                <i class="fas fa-arrow-<?php echo $rev_trend > 0 ? 'up' : 'down'; ?>"></i> <?php echo abs($rev_trend); ?>% vs last month
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card" <?php echo $s['maintenance'] > 0 ? 'style="border-color:var(--amber-bd)"' : ''; ?>>
            <div class="stat-icon" style="color:<?php echo $s['maintenance'] > 0 ? 'var(--amber)' : 'var(--gold)'; ?>"><i class="fas fa-tools"></i></div>
            <div class="stat-value" style="color:<?php echo $s['maintenance'] > 0 ? 'var(--amber)' : 'var(--tx-1)'; ?>"><?php echo $s['maintenance']; ?></div>
            <div class="stat-label">Pending Maintenance</div>
            <?php if ($s['maintenance'] > 0): ?>
            <a href="maintenance.php" class="btn btn-warning btn-sm mt-2"><i class="fas fa-wrench me-1"></i>Handle</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── PENDING APPLICATIONS ───────────────────────────────────── -->
<?php if ($s['apps'] > 0): ?>
<div class="card mb-4" style="border-color:var(--amber-bd)">
    <div class="card-body" style="display:flex;align-items:center;gap:16px">
        <div style="width:44px;height:44px;border-radius:50%;background:var(--amber-bg);border:1px solid var(--amber-bd);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas fa-file-alt" style="color:var(--amber)"></i>
        </div>
        <div style="flex:1">
            <div style="font-weight:700;color:var(--tx-1)"><?php echo $s['apps']; ?> pending application<?php echo $s['apps']>1?'s':''; ?> awaiting review</div>
            <div style="font-size:.78rem;color:var(--tx-3)">Review and approve or reject vendor applications</div>
        </div>
        <a href="appmanagement.php" class="btn btn-warning btn-sm"><i class="fas fa-eye me-1"></i>Review Now</a>
    </div>
</div>
<?php endif; ?>

<!-- ── TABLES ROW ────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Recent Payments -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header" style="justify-content:space-between">
                <span><i class="fas fa-receipt"></i> Recent Payments</span>
                <a href="payments.php" class="btn btn-outline-primary btn-sm">View all</a>
            </div>
            <div class="card-body p-0">
                <?php if(empty($payments)): ?>
                <div class="text-center py-5" style="color:var(--tx-3)"><i class="fas fa-receipt fa-2x d-block mb-2" style="opacity:.25"></i>No payments yet</div>
                <?php else: ?>
                <table class="table mb-0">
                    <thead><tr><th>Vendor</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach($payments as $p): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;color:var(--tx-1)"><?php echo htmlspecialchars($p['name']); ?></div>
                            <div style="font-size:.74rem;color:var(--tx-3)"><?php echo htmlspecialchars($p['business_name']); ?></div>
                        </td>
                        <td style="color:var(--tx-3)"><?php echo formatDate($p['payment_date']); ?></td>
                        <td style="font-weight:700;color:var(--green)"><?php echo formatCurrency($p['amount']); ?></td>
                        <td><span class="badge-status badge-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Maintenance -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header" style="justify-content:space-between">
                <span><i class="fas fa-tools"></i> Maintenance Requests</span>
                <a href="maintenance.php" class="btn btn-outline-primary btn-sm">View all</a>
            </div>
            <div class="card-body p-0">
                <?php if(empty($maintenance)): ?>
                <div class="text-center py-5" style="color:var(--tx-3)"><i class="fas fa-check-circle fa-2x d-block mb-2" style="opacity:.25;color:var(--green)"></i>No pending requests</div>
                <?php else: ?>
                <table class="table mb-0">
                    <thead><tr><th>Stall</th><th>Issue</th><th>Priority</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach($maintenance as $m):
                        $pc=['urgent'=>'var(--red)','high'=>'var(--amber)','medium'=>'var(--blue)','low'=>'var(--gray)'];
                    ?>
                    <tr>
                        <td><span style="background:var(--gold-dim);color:var(--gold);padding:1px 7px;border-radius:4px;font-size:.75rem;font-weight:700"><?php echo htmlspecialchars($m['stall_number']); ?></span></td>
                        <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--tx-1)"><?php echo htmlspecialchars($m['title']); ?></td>
                        <td><span style="font-size:.69rem;font-weight:700;text-transform:uppercase;color:<?php echo $pc[$m['priority']]??'var(--gray)'; ?>"><?php echo $m['priority']; ?></span></td>
                        <td><span class="badge-status badge-<?php echo $m['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$m['status'])); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── REVENUE CHART + STALL STATUS ─────────────────────────── -->
<div class="row g-3">
    <!-- Bar chart -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header" style="justify-content:space-between">
                <span><i class="fas fa-chart-bar"></i> Revenue — Last 6 Months</span>
                <a href="reports.php" class="btn btn-outline-primary btn-sm">Reports</a>
            </div>
            <div class="card-body">
                <?php if(empty($monthly)): ?>
                <div class="text-center py-4" style="color:var(--tx-3)"><i class="fas fa-chart-bar fa-2x d-block mb-2" style="opacity:.25"></i>No data yet</div>
                <?php else:
                    $maxR = max(array_column($monthly,'total'));
                    $maxR = $maxR ?: 1;
                ?>
                <div style="display:flex;align-items:flex-end;gap:8px;height:140px">
                    <?php foreach($monthly as $m):
                        $h = round(($m['total']/$maxR)*100);
                    ?>
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%">
                        <div style="font-size:.65rem;color:var(--tx-3);margin-bottom:4px;white-space:nowrap"><?php echo formatCurrency($m['total']); ?></div>
                        <div style="flex:1;display:flex;align-items:flex-end;width:100%">
                            <div style="width:100%;height:<?php echo max($h,2); ?>%;background:var(--gold);border-radius:4px 4px 0 0;opacity:.85;min-height:3px"></div>
                        </div>
                        <div style="font-size:.7rem;color:var(--tx-3);margin-top:6px;font-weight:600"><?php echo $m['mo']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding-top:12px;margin-top:12px;font-size:.8rem">
                    <span style="color:var(--tx-3)">This month</span>
                    <span style="font-weight:700;color:var(--green)"><?php echo formatCurrency($s['revenue']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stall status -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-store"></i> Stall Status</div>
            <div class="card-body">
                <?php
                $statuses = $db->query("SELECT status, COUNT(*) AS cnt FROM market_stalls GROUP BY status")->fetchAll();
                $slabels  = ['occupied'=>'Occupied','available'=>'Available','maintenance'=>'Maintenance','reserved'=>'Reserved'];
                $scolors  = ['occupied'=>'var(--red)','available'=>'var(--green)','maintenance'=>'var(--amber)','reserved'=>'var(--blue)'];
                foreach($statuses as $st):
                    $pct = $s['stalls'] > 0 ? round(($st['cnt']/$s['stalls'])*100) : 0;
                    $lbl = $slabels[$st['status']] ?? ucfirst($st['status']);
                    $clr = $scolors[$st['status']] ?? 'var(--gray)';
                ?>
                <div style="margin-bottom:16px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                        <span style="font-size:.82rem;font-weight:600;color:<?php echo $clr;?>"><?php echo $lbl;?></span>
                        <span style="font-size:.8rem;color:var(--tx-3)"><?php echo $st['cnt'];?> (<?php echo $pct;?>%)</span>
                    </div>
                    <div class="progress"><div class="progress-bar" style="width:<?php echo $pct;?>%;background:<?php echo $clr;?>"></div></div>
                </div>
                <?php endforeach; ?>

                <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:4px;display:flex;justify-content:space-between;font-size:.84rem">
                    <span style="color:var(--tx-3)">Total Stalls</span>
                    <strong style="color:var(--gold)"><?php echo $s['stalls']; ?></strong>
                </div>
                <div class="mt-3">
                    <a href="stalls.php" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-store me-1"></i>Manage Stalls</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>