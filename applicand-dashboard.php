<?php
require_once 'config.php';
requireLogin();

if ($_SESSION['role'] !== 'applicant') { header('Location: dashboard.php'); exit(); }

$pageTitle   = 'My Application';
$currentPage = 'my-application';
$db = getDB();

$stmt = $db->prepare("SELECT * FROM tenants WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tenant = $stmt->fetch();

$application = null; $documents = [];
if ($tenant) {
    $stmt = $db->prepare("
        SELECT ra.*, ms.stall_number, ms.section, ms.price_per_month, ms.price_per_day,
               u.full_name AS reviewed_by_name, ra.reviewed_date
        FROM rental_applications ra
        JOIN market_stalls ms ON ra.stall_id = ms.stall_id
        LEFT JOIN users u ON ra.reviewed_by = u.user_id
        WHERE ra.tenant_id = ?
        ORDER BY ra.application_date DESC LIMIT 1
    ");
    $stmt->execute([$tenant['tenant_id']]);
    $application = $stmt->fetch();
    if ($application) {
        $stmt = $db->prepare("SELECT * FROM application_documents WHERE application_id = ?");
        $stmt->execute([$application['application_id']]);
        $documents = $stmt->fetchAll();
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-file-alt"></i> My Application Status</h1>
        <p class="page-subtitle">Track your stall rental application</p>
    </div>
</div>

<?php if (!$application): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>No Application Found.</strong> We couldn't find an application associated with your account.
    Please contact the administrator if you believe this is an error.
</div>
<?php else: ?>

<div class="row g-3">

    <!-- LEFT: Application details -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle"></i> Application Information</div>
            <div class="card-body">

                <!-- Reference + Status -->
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--txt-mut);font-weight:600">Application Reference</div>
                        <div style="font-size:1.5rem;font-weight:800;color:var(--gold);letter-spacing:-1px">
                            APP-<?php echo str_pad($application['application_id'], 6, '0', STR_PAD_LEFT); ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--txt-mut);font-weight:600;margin-bottom:6px">Status</div>
                        <?php
                        $smap = ['pending'=>['amber','clock','Under Review'],'approved'=>['green','check-circle','Approved'],'rejected'=>['red','times-circle','Rejected']];
                        [$sc,$si,$sl] = $smap[$application['status']] ?? ['gray','question','Unknown'];
                        ?>
                        <span class="badge-status badge-<?php echo $application['status']; ?>" style="font-size:.82rem;padding:6px 14px">
                            <i class="fas fa-<?php echo $si; ?>"></i> <?php echo $sl; ?>
                        </span>
                    </div>
                </div>

                <hr style="border-color:var(--gold-bd)">

                <!-- Applicant info -->
                <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                        <div class="form-label">Applicant Name</div>
                        <div style="font-weight:600"><?php echo htmlspecialchars($tenant['full_name']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-label">Contact Number</div>
                        <div><?php echo htmlspecialchars($tenant['contact_no']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-label">Email</div>
                        <div><?php echo htmlspecialchars($tenant['email']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-label">Application Date</div>
                        <div><?php echo formatDate($application['application_date']); ?></div>
                    </div>
                </div>

                <hr style="border-color:var(--gold-bd)">
                <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--txt-mut);font-weight:600;margin-bottom:12px">Business Information</div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                        <div class="form-label">Business Name</div>
                        <div style="font-weight:600"><?php echo htmlspecialchars($tenant['business_name']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-label">Business Type</div>
                        <div><?php echo htmlspecialchars($tenant['business_type']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-label">Vendor Type</div>
                        <span class="badge-status <?php echo $tenant['tenant_type']==='permanent'?'badge-active':'badge-reserved'; ?>">
                            <?php echo ucfirst($tenant['tenant_type']); ?>
                        </span>
                    </div>
                </div>

                <hr style="border-color:var(--gold-bd)">
                <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--txt-mut);font-weight:600;margin-bottom:12px">Requested Stall</div>
                <div class="row mb-3">
                    <div class="col-md-3 mb-3">
                        <div class="form-label">Stall Number</div>
                        <div style="font-size:1.4rem;font-weight:800;color:var(--gold)"><?php echo htmlspecialchars($application['stall_number']); ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="form-label">Section</div>
                        <div><?php echo htmlspecialchars($application['section']); ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="form-label">Daily Rate</div>
                        <div style="font-weight:600;color:var(--green)"><?php echo formatCurrency($application['price_per_day']); ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="form-label">Monthly Rate</div>
                        <div style="font-weight:600;color:var(--green)"><?php echo formatCurrency($application['price_per_month']); ?></div>
                    </div>
                </div>

                <?php if ($application['notes']): ?>
                <hr style="border-color:var(--gold-bd)">
                <div class="form-label">Your Notes</div>
                <p style="color:var(--txt-dim)"><?php echo nl2br(htmlspecialchars($application['notes'])); ?></p>
                <?php endif; ?>

                <?php if ($application['status'] === 'approved'): ?>
                <hr style="border-color:var(--gold-bd)">
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Congratulations!</strong> Your application has been approved. An administrator will contact you soon to complete the rental process.
                    <?php if ($application['reviewed_by_name']): ?>
                    <div style="font-size:.78rem;margin-top:4px;opacity:.8">Reviewed by <?php echo htmlspecialchars($application['reviewed_by_name']); ?> on <?php echo formatDate($application['reviewed_date']); ?></div>
                    <?php endif; ?>
                </div>
                <?php elseif ($application['status'] === 'rejected'): ?>
                <hr style="border-color:var(--gold-bd)">
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-times-circle me-2"></i>
                    <strong>Application Rejected.</strong> Unfortunately your application was not approved at this time.
                    <?php if ($application['reviewed_by_name']): ?>
                    <div style="font-size:.78rem;margin-top:4px;opacity:.8">Reviewed by <?php echo htmlspecialchars($application['reviewed_by_name']); ?> on <?php echo formatDate($application['reviewed_date']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Timeline + docs + help -->
    <div class="col-lg-4">

        <!-- Timeline -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-list-ol"></i> Application Timeline</div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item completed">
                        <div class="timeline-icon" style="background:#27ae60"><i class="fas fa-check"></i></div>
                        <div class="timeline-content">
                            <h6>Application Submitted</h6>
                            <small><?php echo formatDate($application['application_date']); ?></small>
                        </div>
                    </div>
                    <div class="timeline-item <?php echo in_array($application['status'],['approved','rejected'])?'completed':($application['status']==='pending'?'active':''); ?>">
                        <div class="timeline-icon" style="background:<?php echo $application['status']==='pending'?'#f39c12':'#27ae60'; ?>">
                            <i class="fas fa-<?php echo $application['status']==='pending'?'clock':'check'; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <h6>Under Review</h6>
                            <small><?php echo $application['status']==='pending'?'In progress…':'Reviewed'; ?></small>
                        </div>
                    </div>
                    <div class="timeline-item <?php echo $application['status']==='approved'?'active':''; ?>">
                        <div class="timeline-icon" style="background:<?php echo $application['status']==='approved'?'#27ae60':($application['status']==='rejected'?'#e74c3c':'#555'); ?>">
                            <i class="fas fa-<?php echo $application['status']==='approved'?'check-circle':($application['status']==='rejected'?'times-circle':'hourglass'); ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <h6><?php echo $application['status']==='approved'?'Approved':($application['status']==='rejected'?'Rejected':'Awaiting Decision'); ?></h6>
                            <small><?php echo ($application['status']==='pending')?'Pending review':($application['reviewed_date']?formatDate($application['reviewed_date']):'—'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-paperclip"></i> Submitted Documents</div>
            <div class="card-body">
                <?php if (!empty($documents)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($documents as $doc):
                        $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                        $icon = $ext==='pdf' ? 'file-pdf' : 'file-image';
                    ?>
                    <div class="list-group-item list-group-item-action bg-transparent d-flex align-items-center gap-3">
                        <i class="fas fa-<?php echo $icon; ?>" style="color:var(--gold);font-size:1.2rem"></i>
                        <div>
                            <div style="font-weight:600;font-size:.84rem"><?php echo ucwords(str_replace('_',' ',$doc['document_type'])); ?></div>
                            <div style="font-size:.74rem;color:var(--txt-mut)"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-3" style="color:var(--txt-mut)"><i class="fas fa-folder-open fa-2x mb-2 d-block" style="opacity:.3"></i>No documents uploaded</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Help -->
        <div class="card">
            <div class="card-header"><i class="fas fa-question-circle"></i> Need Help?</div>
            <div class="card-body">
                <p style="color:var(--txt-dim);font-size:.84rem;margin-bottom:12px">For questions about your application:</p>
                <div class="d-flex flex-column gap-2" style="font-size:.84rem">
                    <div><i class="fas fa-phone" style="color:var(--gold);width:18px"></i> <strong>(036) 123-4567</strong></div>
                    <div><i class="fas fa-envelope" style="color:var(--gold);width:18px"></i> <strong>admin@sibalom.gov.ph</strong></div>
                    <div><i class="fas fa-clock" style="color:var(--gold);width:18px"></i> Mon–Fri, 8 AM–5 PM</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
<?php include 'includes/footer.php'; ?>