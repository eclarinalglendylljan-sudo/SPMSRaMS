<?php
require_once 'config.php';
requireLogin();

$pageTitle = 'Reports';
$currentPage = 'reports';

$db = getDB();

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

include 'includes/header.php';
?>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    body {
        background: white !important;
        color: black !important;
    }
    
    body::before {
        display: none;
    }
    
    .card {
        background: white !important;
        border: 1px solid #000 !important;
        color: black !important;
        page-break-inside: avoid;
    }
    
    .card-header {
        background: #f8f9fa !important;
        color: #000 !important;
        border-bottom: 2px solid #000 !important;
    }
    
    .table {
        color: black !important;
    }
    
    .table thead th {
        background: #f8f9fa !important;
        color: #000 !important;
        border-color: #000 !important;
    }
    
    .table tbody tr {
        border-color: #000 !important;
        background: white !important;
    }
    
    .text-warning, .text-success, .text-danger, .text-info, .text-muted {
        color: #000 !important;
    }
    
    h1, h2, h3, h4, h5, h6, p, span, div, td, th, label {
        text-shadow: none !important;
    }
    
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 3px solid #000;
        padding-bottom: 10px;
    }
    
    .print-footer {
        display: block !important;
        text-align: center;
        margin-top: 20px;
        border-top: 1px solid #000;
        padding-top: 10px;
        font-size: 12px;
    }
}

.print-header, .print-footer {
    display: none;
}
</style>

<!-- Print Header (only visible when printing) -->
<div class="print-header">
    <h2>Sibalom Market Stall Rental and Mapping System</h2>
    <h3>Reports & Analytics</h3>
    <p>Date Range: <?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?></p>
    <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
</div>

<div class="page-header no-print">
    <h1 class="page-title"><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
    <p class="text-muted">Generate comprehensive reports for market operations</p>
</div>

<!-- Date Range Selector -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sync"></i> Refresh Data
                </button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-success w-100" onclick="printReport()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Cards -->
<div class="row mb-4">
    <!-- Occupancy Report -->
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header bg-primary">
                <i class="fas fa-chart-pie"></i> Occupancy Report
            </div>
            <div class="card-body">
                <?php
                $stmt = $db->query("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
                        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
                    FROM market_stalls
                ");
                $occupancy = $stmt->fetch();
                $occupancy_rate = $occupancy['total'] > 0 ? round(($occupancy['occupied'] / $occupancy['total']) * 100, 1) : 0;
                ?>
                <div class="text-center mb-3">
                    <h1 class="display-3 text-warning"><?php echo $occupancy_rate; ?>%</h1>
                    <p class="text-muted">Current Occupancy Rate</p>
                </div>
                <table class="table table-sm">
                    <tr>
                        <td>Total Stalls:</td>
                        <td class="text-end"><strong><?php echo $occupancy['total']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Occupied:</td>
                        <td class="text-end"><strong class="text-danger"><?php echo $occupancy['occupied']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Available:</td>
                        <td class="text-end"><strong class="text-success"><?php echo $occupancy['available']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Maintenance:</td>
                        <td class="text-end"><strong class="text-warning"><?php echo $occupancy['maintenance']; ?></strong></td>
                    </tr>
                </table>
                <button class="btn btn-outline-primary btn-sm w-100 mt-2 no-print" onclick="printSingleReport('occupancy')">
                    <i class="fas fa-print"></i> Print This Report
                </button>
                <button class="btn btn-outline-secondary btn-sm w-100 mt-2 no-print" onclick="generateReport('occupancy')">
                    <i class="fas fa-download"></i> Download Report
                </button>
            </div>
        </div>
    </div>

    <!-- Revenue Report -->
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header bg-success">
                <i class="fas fa-money-bill-wave"></i> Revenue Report
            </div>
            <div class="card-body">
                <?php
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total_payments,
                        SUM(amount) as total_revenue,
                        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
                    FROM payments
                    WHERE payment_date BETWEEN ? AND ?
                ");
                $stmt->execute([$date_from, $date_to]);
                $revenue = $stmt->fetch();
                ?>
                <div class="text-center mb-3">
                    <h3 class="text-warning"><?php echo formatCurrency($revenue['total_revenue']); ?></h3>
                    <p class="text-muted">Total Revenue (<?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?>)</p>
                </div>
                <table class="table table-sm">
                    <tr>
                        <td>Total Payments:</td>
                        <td class="text-end"><strong><?php echo $revenue['total_payments']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Paid Amount:</td>
                        <td class="text-end"><strong class="text-success"><?php echo formatCurrency($revenue['paid_amount']); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Pending Amount:</td>
                        <td class="text-end"><strong class="text-warning"><?php echo formatCurrency($revenue['pending_amount']); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Collection Rate:</td>
                        <td class="text-end">
                            <strong><?php echo $revenue['total_revenue'] > 0 ? round(($revenue['paid_amount'] / $revenue['total_revenue']) * 100, 1) : 0; ?>%</strong>
                        </td>
                    </tr>
                </table>
                <button class="btn btn-outline-success btn-sm w-100 mt-2 no-print" onclick="printSingleReport('revenue')">
                    <i class="fas fa-print"></i> Print This Report
                </button>
                <button class="btn btn-outline-secondary btn-sm w-100 mt-2 no-print" onclick="generateReport('revenue')">
                    <i class="fas fa-download"></i> Download Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- More Reports -->
<div class="row">
    <!-- Vendor Directory -->
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-users"></i> Vendor Directory
            </div>
            <div class="card-body">
                <?php
                $stmt = $db->query("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN tenant_type = 'permanent' THEN 1 ELSE 0 END) as permanent,
                        SUM(CASE WHEN tenant_type = 'temporary' THEN 1 ELSE 0 END) as temporary
                    FROM tenants WHERE status = 'active'
                ");
                $vendors = $stmt->fetch();
                ?>
                <div class="text-center mb-3">
                    <h2 class="text-warning"><?php echo $vendors['total']; ?></h2>
                    <p class="text-muted">Active Vendors</p>
                </div>
                <table class="table table-sm">
                    <tr>
                        <td>Permanent:</td>
                        <td class="text-end"><strong><?php echo $vendors['permanent']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Temporary:</td>
                        <td class="text-end"><strong><?php echo $vendors['temporary']; ?></strong></td>
                    </tr>
                </table>
                <button class="btn btn-outline-primary btn-sm w-100 no-print" onclick="printSingleReport('vendors')">
                    <i class="fas fa-print"></i> Print This Report
                </button>
                <button class="btn btn-outline-secondary btn-sm w-100 mt-2 no-print" onclick="generateReport('vendors')">
                    <i class="fas fa-download"></i> Download Directory
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Compliance -->
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-check-circle"></i> Payment Compliance
            </div>
            <div class="card-body">
                <?php
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(DISTINCT t.tenant_id) as total_tenants,
                        COUNT(DISTINCT p.tenant_id) as paid_tenants
                    FROM tenants t
                    LEFT JOIN payments p ON t.tenant_id = p.tenant_id 
                        AND p.payment_date BETWEEN ? AND ? 
                        AND p.status = 'paid'
                    WHERE t.status = 'active' AND t.tenant_type = 'permanent'
                ");
                $stmt->execute([$date_from, $date_to]);
                $compliance = $stmt->fetch();
                $compliance_rate = $compliance['total_tenants'] > 0 ? 
                    round(($compliance['paid_tenants'] / $compliance['total_tenants']) * 100, 1) : 0;
                ?>
                <div class="text-center mb-3">
                    <h2 class="text-warning"><?php echo $compliance_rate; ?>%</h2>
                    <p class="text-muted">Compliance Rate</p>
                </div>
                <table class="table table-sm">
                    <tr>
                        <td>Active Tenants:</td>
                        <td class="text-end"><strong><?php echo $compliance['total_tenants']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Paid This Period:</td>
                        <td class="text-end"><strong class="text-success"><?php echo $compliance['paid_tenants']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Outstanding:</td>
                        <td class="text-end">
                            <strong class="text-danger"><?php echo $compliance['total_tenants'] - $compliance['paid_tenants']; ?></strong>
                        </td>
                    </tr>
                </table>
                <button class="btn btn-outline-primary btn-sm w-100 no-print" onclick="printSingleReport('compliance')">
                    <i class="fas fa-print"></i> Print This Report
                </button>
                <button class="btn btn-outline-secondary btn-sm w-100 mt-2 no-print" onclick="generateReport('compliance')">
                    <i class="fas fa-download"></i> Download Report
                </button>
            </div>
        </div>
    </div>

    <!-- Maintenance Summary -->
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-tools"></i> Maintenance Summary
            </div>
            <div class="card-body">
                <?php
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress
                    FROM maintenance_requests
                    WHERE request_date BETWEEN ? AND ?
                ");
                $stmt->execute([$date_from, $date_to]);
                $maintenance = $stmt->fetch();
                $completion_rate = $maintenance['total'] > 0 ? 
                    round(($maintenance['completed'] / $maintenance['total']) * 100, 1) : 0;
                ?>
                <div class="text-center mb-3">
                    <h2 class="text-warning"><?php echo $completion_rate; ?>%</h2>
                    <p class="text-muted">Completion Rate</p>
                </div>
                <table class="table table-sm">
                    <tr>
                        <td>Total Requests:</td>
                        <td class="text-end"><strong><?php echo $maintenance['total']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Completed:</td>
                        <td class="text-end"><strong class="text-success"><?php echo $maintenance['completed']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>In Progress:</td>
                        <td class="text-end"><strong class="text-info"><?php echo $maintenance['in_progress']; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Pending:</td>
                        <td class="text-end"><strong class="text-warning"><?php echo $maintenance['pending']; ?></strong></td>
                    </tr>
                </table>
                <button class="btn btn-outline-primary btn-sm w-100 no-print" onclick="printSingleReport('maintenance')">
                    <i class="fas fa-print"></i> Print This Report
                </button>
                <button class="btn btn-outline-secondary btn-sm w-100 mt-2 no-print" onclick="generateReport('maintenance')">
                    <i class="fas fa-download"></i> Download Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Payment List (for printing) -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-list"></i> Detailed Payment Records
    </div>
    <div class="card-body">
        <?php
        $stmt = $db->prepare("
            SELECT 
                p.*,
                t.full_name as tenant_name,
                t.business_name,
                ms.stall_number
            FROM payments p
            JOIN tenants t ON p.tenant_id = t.tenant_id
            LEFT JOIN rental_records rr ON p.tenant_id = rr.tenant_id AND rr.status = 'active'
            LEFT JOIN market_stalls ms ON rr.stall_id = ms.stall_id
            WHERE p.payment_date BETWEEN ? AND ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute([$date_from, $date_to]);
        $payments = $stmt->fetchAll();
        ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Tenant</th>
                        <th>Stall</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No payment records found for this period</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                                <td><?php echo htmlspecialchars($payment['stall_number'] ?? 'N/A'); ?></td>
                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $payment['status'] == 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td class="no-print">
                                    <button class="btn btn-sm btn-outline-info" onclick="viewPayment(<?php echo $payment['payment_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Print Footer (only visible when printing) -->
<div class="print-footer">
    <p>This is a computer-generated report from Sibalom Market Stall Rental and Mapping System</p>
    <p>Printed by: <?php echo htmlspecialchars($_SESSION['full_name']); ?> | Page 1 of 1</p>
</div>

<script>
function printReport() {
    window.print();
}

function printSingleReport(type) {
    // Hide all report cards except the one being printed
    const allReportCards = document.querySelectorAll('.row.mb-4, .row');
    const originalDisplay = [];
    
    // Store original display and hide everything
    allReportCards.forEach((row, index) => {
        originalDisplay[index] = row.style.display;
        const cards = row.querySelectorAll('.card');
        cards.forEach(card => {
            const header = card.querySelector('.card-header');
            if (header && !header.textContent.toLowerCase().includes(type)) {
                card.style.display = 'none';
            }
        });
    });
    
    // Print
    setTimeout(() => {
        window.print();
        
        // Restore original display after print dialog
        setTimeout(() => {
            const allCards = document.querySelectorAll('.card');
            allCards.forEach(card => {
                card.style.display = '';
            });
        }, 100);
    }, 100);
}

function generateReport(type) {
    const dateFrom = '<?php echo $date_from; ?>';
    const dateTo = '<?php echo $date_to; ?>';
    
    alert('Generating ' + type + ' report for ' + dateFrom + ' to ' + dateTo + '\n\nThis feature will generate a downloadable PDF/Excel file.');
    
}

function viewPayment(paymentId) {
    window.location.href = 'payments.php?payment_id=' + paymentId;
}
</script>

<?php include 'includes/footer.php'; ?>