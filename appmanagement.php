<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Application Management';
$currentPage = 'appmanagement';
$db = getDB();

// ── POST actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'approve_application') {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("SELECT ra.stall_id,ra.tenant_id,ra.application_id,ms.price_per_month FROM rental_applications ra JOIN market_stalls ms ON ra.stall_id=ms.stall_id WHERE ra.application_id=?");
            $stmt->execute([$_POST['application_id']]);
            $appData = $stmt->fetch();
            if (!$appData) throw new Exception("Application not found");

            $db->prepare("UPDATE rental_applications SET status='approved',reviewed_by=?,reviewed_date=NOW() WHERE application_id=?")->execute([$_SESSION['user_id'],$_POST['application_id']]);
            $db->prepare("UPDATE tenants SET status='active' WHERE tenant_id=?")->execute([$appData['tenant_id']]);
            $db->prepare("UPDATE users SET role='vendor',status='active' WHERE user_id=(SELECT user_id FROM tenants WHERE tenant_id=?)")->execute([$appData['tenant_id']]);
            $db->prepare("INSERT INTO rental_records (stall_id,tenant_id,application_id,start_date,monthly_rate,status) VALUES (?,?,?,CURDATE(),?,'active')")->execute([$appData['stall_id'],$appData['tenant_id'],$appData['application_id'],$appData['price_per_month']]);
            $db->commit();
            setMessage('success','Application approved! Vendor account activated.');
        } catch (Exception $e) { $db->rollBack(); setMessage('danger','Error: '.$e->getMessage()); }

    } elseif ($_POST['action'] === 'reject_application') {
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE rental_applications SET status='rejected',reviewed_by=?,reviewed_date=NOW(),notes=? WHERE application_id=?")->execute([$_SESSION['user_id'],$_POST['rejection_reason'],$_POST['application_id']]);
            $db->prepare("UPDATE tenants SET status='inactive' WHERE tenant_id=(SELECT tenant_id FROM rental_applications WHERE application_id=?)")->execute([$_POST['application_id']]);
            $db->prepare("UPDATE users SET status='inactive' WHERE user_id=(SELECT user_id FROM tenants WHERE tenant_id=(SELECT tenant_id FROM rental_applications WHERE application_id=?))")->execute([$_POST['application_id']]);
            $db->commit();
            setMessage('info','Application rejected. User account deactivated.');
        } catch (Exception $e) { $db->rollBack(); setMessage('danger','Error: '.$e->getMessage()); }

    } elseif ($_POST['action'] === 'delete_application') {
        try {
            $db->prepare("DELETE FROM rental_applications WHERE application_id=?")->execute([$_POST['application_id']]);
            setMessage('success','Application deleted.');
        } catch (Exception $e) { setMessage('danger','Error: '.$e->getMessage()); }
    }
    header('Location: appmanagement.php'); exit();
}

// ── Query ────────────────────────────────────────────────────────
$status_filter = $_GET['status'] ?? 'pending';
$query  = "SELECT ra.*, t.full_name AS tenant_name, t.business_name, t.business_type, t.contact_no, t.email, t.tenant_type,
                  ms.stall_number, ms.section, ms.price_per_month,
                  u.full_name AS reviewed_by_name,
                  usr.username, usr.role AS user_role, usr.status AS user_status
           FROM rental_applications ra
           JOIN tenants t ON ra.tenant_id=t.tenant_id
           JOIN market_stalls ms ON ra.stall_id=ms.stall_id
           LEFT JOIN users u ON ra.reviewed_by=u.user_id
           LEFT JOIN users usr ON t.user_id=usr.user_id WHERE 1=1";
$params = [];
if ($status_filter) { $query .= " AND ra.status=?"; $params[] = $status_filter; }
$query .= " ORDER BY ra.application_date DESC";
$stmt = $db->prepare($query); $stmt->execute($params);
$applications = $stmt->fetchAll();

$stats = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected FROM rental_applications")->fetch();

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-file-alt"></i> Stall Rental Applications</h1>
        <p class="page-subtitle">Review and manage vendor applications</p>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Applications</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:rgba(255,193,7,.4)">
            <div class="stat-icon" style="color:var(--amber)"><i class="fas fa-clock"></i></div>
            <div class="stat-value" style="color:var(--amber)"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:rgba(40,167,69,.4)">
            <div class="stat-icon" style="color:var(--green)"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value" style="color:var(--green)"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">Approved</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:rgba(220,53,69,.4)">
            <div class="stat-icon" style="color:var(--red)"><i class="fas fa-times-circle"></i></div>
            <div class="stat-value" style="color:var(--red)"><?php echo $stats['rejected']; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" data-no-spinner>
            <div class="col-md-4">
                <label class="form-label">Filter by Status</label>
                <select name="status" class="form-select">
                    <option value="">All Applications</option>
                    <option value="pending"  <?php echo $status_filter==='pending' ?'selected':''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter==='approved'?'selected':''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter==='rejected'?'selected':''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button></div>
            <div class="col-auto"><a href="appmanagement.php" class="btn btn-secondary"><i class="fas fa-sync me-1"></i>Reset</a></div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header"><i class="fas fa-list"></i> Applications <span style="font-weight:400;font-size:.82rem;color:var(--txt-mut)">(<?php echo count($applications); ?> records)</span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th><th>Date</th><th>Applicant</th><th>Business</th>
                        <th>Stall</th><th>Account</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                    <tr><td colspan="8" class="text-center py-4" style="color:var(--txt-mut)"><i class="fas fa-inbox fa-2x d-block mb-2" style="opacity:.3"></i>No applications found</td></tr>
                    <?php else: foreach ($applications as $app): ?>
                    <tr>
                        <td><strong style="color:var(--gold)">#<?php echo $app['application_id']; ?></strong></td>
                        <td style="color:var(--txt-mut)"><?php echo formatDate($app['application_date']); ?></td>
                        <td>
                            <div style="font-weight:600"><?php echo htmlspecialchars($app['tenant_name']); ?></div>
                            <div style="font-size:.76rem;color:var(--txt-mut)"><?php echo htmlspecialchars($app['email']); ?></div>
                            <div style="font-size:.74rem;color:var(--blue)"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($app['username']); ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600"><?php echo htmlspecialchars($app['business_name']); ?></div>
                            <div style="font-size:.76rem;color:var(--txt-mut)"><?php echo htmlspecialchars($app['business_type']); ?></div>
                            <span class="badge-status <?php echo $app['tenant_type']==='permanent'?'badge-active':'badge-reserved'; ?>"><?php echo ucfirst($app['tenant_type']); ?></span>
                        </td>
                        <td>
                            <span style="background:var(--gold-dim);border:1px solid var(--gold-bd);color:var(--gold);padding:2px 8px;border-radius:6px;font-size:.78rem;font-weight:700"><?php echo htmlspecialchars($app['stall_number']); ?></span>
                            <div style="font-size:.74rem;color:var(--txt-mut)">Section <?php echo $app['section']; ?></div>
                            <div style="font-size:.74rem;color:var(--green);font-weight:600"><?php echo formatCurrency($app['price_per_month']); ?>/mo</div>
                        </td>
                        <td>
                            <span class="badge-status <?php echo $app['user_status']==='active'?'badge-active':($app['user_status']==='pending'?'badge-pending':'badge-inactive'); ?>"><?php echo ucfirst($app['user_role']); ?></span>
                            <div style="font-size:.74rem;color:var(--txt-mut)"><?php echo ucfirst($app['user_status']); ?></div>
                        </td>
                        <td><span class="badge-status badge-<?php echo $app['status']; ?>"><?php echo ucfirst($app['status']); ?></span>
                            <?php if ($app['reviewed_by_name']): ?><div style="font-size:.72rem;color:var(--txt-mut)">by <?php echo htmlspecialchars($app['reviewed_by_name']); ?></div><?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick='viewApplication(<?php echo json_encode($app); ?>)' title="View"><i class="fas fa-eye"></i></button>
                                <?php if ($app['status']==='pending'): ?>
                                <button class="btn btn-outline-success" onclick="approveApplication(<?php echo $app['application_id']; ?>)" title="Approve"><i class="fas fa-check"></i></button>
                                <button class="btn btn-outline-danger"  onclick="rejectApplication(<?php echo $app['application_id']; ?>)"  title="Reject"><i class="fas fa-times"></i></button>
                                <?php endif; ?>
                                <button class="btn btn-outline-danger"  onclick="deleteApplication(<?php echo $app['application_id']; ?>)"  title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Application Modal — uses header.php modal styles -->
<div class="modal fade" id="viewApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="applicationDetails"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden action forms -->
<form id="approveForm" method="POST" style="display:none"><input type="hidden" name="action" value="approve_application"><input type="hidden" name="application_id" id="approve_app_id"></form>
<form id="rejectForm"  method="POST" style="display:none"><input type="hidden" name="action" value="reject_application"><input type="hidden" name="application_id" id="reject_app_id"><input type="hidden" name="rejection_reason" id="rejection_reason"></form>
<form id="deleteForm"  method="POST" style="display:none"><input type="hidden" name="action" value="delete_application"><input type="hidden" name="application_id" id="delete_app_id"></form>

<script>
function viewApplication(app) {
    var modal = document.getElementById('viewApplicationModal');
    var details = document.getElementById('applicationDetails');
    details.innerHTML = '<div class="text-center py-4"><div class="spinner-border" style="color:var(--gold)"></div></div>';
    new bootstrap.Modal(modal).show();

    fetch('get_application_documents.php?id=' + app.application_id)
        .then(function(r){ return r.json(); })
        .then(function(documents) {
            var docsHTML = '<p style="color:var(--txt-mut)">No documents uploaded</p>';
            if (documents.length) {
                docsHTML = '<div class="row g-2">';
                documents.forEach(function(doc) {
                    var label = doc.document_type.replace(/_/g,' ').replace(/\b\w/g,function(l){return l.toUpperCase();});
                    var icon  = doc.file_path.split('.').pop().toLowerCase()==='pdf' ? 'file-pdf' : 'file-image';
                    docsHTML += '<div class="col-md-6"><a href="uploads/documents/'+doc.file_path+'" target="_blank" class="btn btn-outline-warning btn-sm w-100"><i class="fas fa-'+icon+'"></i> '+label+'</a></div>';
                });
                docsHTML += '</div>';
            }
            var sColor = app.status==='pending'?'var(--amber)':app.status==='approved'?'var(--green)':'var(--red)';
            details.innerHTML =
                '<div class="row g-3 mb-3">'
                +'<div class="col-md-6"><div class="form-label">Application ID</div><strong>#'+app.application_id+'</strong></div>'
                +'<div class="col-md-6"><div class="form-label">Date Applied</div>'+new Date(app.application_date).toLocaleDateString()+'</div>'
                +'<div class="col-md-6"><div class="form-label">Status</div><span style="color:'+sColor+';font-weight:700;text-transform:uppercase">'+app.status+'</span></div>'
                +'<div class="col-md-6"><div class="form-label">Username</div>'+app.username+'</div>'
                +(app.reviewed_by_name?'<div class="col-md-6"><div class="form-label">Reviewed By</div>'+app.reviewed_by_name+'</div>':'')
                +'</div><hr style="border-color:var(--gold-bd)">'
                +'<div class="row g-3 mb-3">'
                +'<div class="col-md-6"><div class="form-label">Full Name</div><strong>'+app.tenant_name+'</strong></div>'
                +'<div class="col-md-6"><div class="form-label">Email</div>'+(app.email||'—')+'</div>'
                +'<div class="col-md-6"><div class="form-label">Contact</div>'+app.contact_no+'</div>'
                +'<div class="col-md-6"><div class="form-label">Business</div><strong>'+app.business_name+'</strong></div>'
                +'<div class="col-md-6"><div class="form-label">Business Type</div>'+(app.business_type||'—')+'</div>'
                +'<div class="col-md-6"><div class="form-label">Vendor Type</div>'+app.tenant_type+'</div>'
                +'</div><hr style="border-color:var(--gold-bd)">'
                +'<div class="row g-3 mb-3">'
                +'<div class="col-md-4"><div class="form-label">Stall</div><strong style="color:var(--gold)">'+app.stall_number+'</strong></div>'
                +'<div class="col-md-4"><div class="form-label">Section</div>'+app.section+'</div>'
                +'<div class="col-md-4"><div class="form-label">Monthly Rate</div><strong style="color:var(--green)">₱'+parseFloat(app.price_per_month).toLocaleString('en-PH',{minimumFractionDigits:2})+'</strong></div>'
                +'</div>'
                +(app.notes?'<hr style="border-color:var(--gold-bd)"><div class="form-label">Notes</div><p style="color:var(--txt-dim)">'+app.notes+'</p>':'')
                +'<hr style="border-color:var(--gold-bd)"><div class="form-label mb-2">Documents</div>'+docsHTML;
        })
        .catch(function() {
            details.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Could not load documents. Application data shown above is still valid.</div>';
        });
}

function approveApplication(id) {
    if (confirm('APPROVE this application?\n\n✓ Activate vendor account\n✓ Grant vendor access\n✓ Allocate stall')) {
        document.getElementById('approve_app_id').value = id;
        window.showSpinner && window.showSpinner();
        document.getElementById('approveForm').submit();
    }
}
function rejectApplication(id) {
    var reason = prompt('Reason for rejection (required):');
    if (reason && reason.trim()) {
        if (confirm('REJECT this application and deactivate the user account?')) {
            document.getElementById('reject_app_id').value = id;
            document.getElementById('rejection_reason').value = reason;
            window.showSpinner && window.showSpinner();
            document.getElementById('rejectForm').submit();
        }
    }
}
function deleteApplication(id) {
    if (confirm('DELETE this application? This cannot be undone.')) {
        document.getElementById('delete_app_id').value = id;
        window.showSpinner && window.showSpinner();
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>