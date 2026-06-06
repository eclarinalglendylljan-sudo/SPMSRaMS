<?php
require_once 'config.php';
requireLogin();

$pageTitle   = 'View Maintenance Request';
$currentPage = 'maintenance';
$db = getDB();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: maintenance.php'); exit(); }

$stmt = $db->prepare("
    SELECT mr.*,
           ms.stall_number, ms.section, ms.location,
           t.full_name   AS tenant_name,  t.business_name,  t.contact_no,
           u.full_name   AS assigned_name,
           cb.full_name  AS created_by_name
    FROM maintenance_requests mr
    JOIN market_stalls ms ON mr.stall_id = ms.stall_id
    LEFT JOIN tenants t  ON mr.tenant_id = t.tenant_id
    LEFT JOIN users u    ON mr.assigned_to = u.user_id
    LEFT JOIN users cb   ON mr.created_by = cb.user_id
    WHERE mr.request_id = ?
");
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) { setMessage('danger','Request not found.'); header('Location: maintenance.php'); exit(); }

$prioColor = ['urgent'=>'var(--red)','high'=>'var(--amber)','medium'=>'var(--blue)','low'=>'var(--gray)'][$req['priority']] ?? 'var(--gray)';
$prioClass = ['urgent'=>'pp-urgent','high'=>'pp-high','medium'=>'pp-medium','low'=>'pp-low'][$req['priority']] ?? 'pp-low';

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-tools"></i> Maintenance Request #<?php echo $id;?></h1>
        <p class="page-subtitle">Submitted <?php echo formatDate($req['request_date']);?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if(isStaff()):?>
        <a href="edit_maintenance.php?id=<?php echo $id;?>" class="btn btn-warning"><i class="fas fa-edit me-1"></i>Edit</a>
        <?php endif;?>
        <a href="maintenance.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="row g-4">

    <!-- Left: Main details -->
    <div class="col-lg-8">

        <!-- Status banner -->
        <?php
        $bannerBg  = ['pending'=>'var(--amber-bg)','in_progress'=>'var(--blue-bg)','completed'=>'var(--green-bg)','cancelled'=>'var(--gray-bg)'][$req['status']]??'var(--gray-bg)';
        $bannerBd  = ['pending'=>'var(--amber-bd)','in_progress'=>'var(--blue-bd)','completed'=>'var(--green-bd)','cancelled'=>'var(--gray-bd)'][$req['status']]??'var(--gray-bd)';
        $bannerClr = ['pending'=>'var(--amber)','in_progress'=>'var(--blue)','completed'=>'var(--green)','cancelled'=>'var(--gray)'][$req['status']]??'var(--gray)';
        $bannerIco = ['pending'=>'clock','in_progress'=>'spinner fa-spin','completed'=>'check-circle','cancelled'=>'ban'][$req['status']]??'tools';
        ?>
        <div style="background:<?php echo $bannerBg;?>;border:1px solid <?php echo $bannerBd;?>;border-radius:var(--r);padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px">
            <div style="width:44px;height:44px;border-radius:50%;background:<?php echo $bannerBd;?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fas fa-<?php echo $bannerIco;?>" style="color:<?php echo $bannerClr;?>;font-size:1.1rem"></i>
            </div>
            <div>
                <div style="font-weight:700;color:<?php echo $bannerClr;?>;font-size:1rem"><?php echo ucfirst(str_replace('_',' ',$req['status']));?></div>
                <div style="font-size:.79rem;color:var(--tx-3)">
                    <?php if($req['status']==='completed'&&$req['completion_date']):?>
                        Completed on <?php echo formatDate($req['completion_date']);?>
                    <?php elseif($req['status']==='in_progress'):?>
                        Being handled<?php echo $req['assigned_name']?' by '.$req['assigned_name']:'';?>
                    <?php elseif($req['status']==='pending'):?>
                        Awaiting review and assignment
                    <?php else:?>
                        Request has been cancelled
                    <?php endif;?>
                </div>
            </div>
            <span class="priority-pill <?php echo $prioClass;?>" style="margin-left:auto"><?php echo strtoupper($req['priority']);?></span>
        </div>

        <!-- Issue details -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-exclamation-triangle"></i> Issue Details</div>
            <div class="card-body">
                <h5 style="font-weight:700;color:var(--tx-1);font-size:1.05rem;margin-bottom:12px"><?php echo htmlspecialchars($req['title']);?></h5>
                <div style="color:var(--tx-2);line-height:1.75;font-size:.88rem;background:rgba(0,0,0,.15);border:1px solid var(--bd);border-radius:var(--r-sm);padding:14px">
                    <?php echo nl2br(htmlspecialchars($req['description']));?>
                </div>
            </div>
        </div>

        <!-- Stall info -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-store"></i> Stall Information</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row"><span class="info-key">Stall Number</span><span class="info-val" style="color:var(--gold);font-weight:700"><?php echo htmlspecialchars($req['stall_number']);?></span></div>
                <div class="info-row"><span class="info-key">Section</span><span class="info-val">Section <?php echo htmlspecialchars($req['section']);?></span></div>
                <div class="info-row"><span class="info-key">Location</span><span class="info-val"><?php echo htmlspecialchars($req['location']??'—');?></span></div>
                <?php if($req['tenant_name']):?>
                <div class="info-row"><span class="info-key">Vendor</span><span class="info-val"><?php echo htmlspecialchars($req['tenant_name']);?></span></div>
                <div class="info-row"><span class="info-key">Business</span><span class="info-val"><?php echo htmlspecialchars($req['business_name']??'—');?></span></div>
                <div class="info-row"><span class="info-key">Contact</span><span class="info-val"><?php echo htmlspecialchars($req['contact_no']??'—');?></span></div>
                <?php endif;?>
            </div>
        </div>

        <!-- Internal notes (staff only) -->
        <?php if(isStaff()&&!empty($req['notes'])):?>
        <div class="card">
            <div class="card-header"><i class="fas fa-sticky-note"></i> Internal Notes</div>
            <div class="card-body">
                <div style="color:var(--tx-2);line-height:1.75;font-size:.88rem;background:rgba(245,200,66,.04);border:1px solid var(--gold-bd);border-radius:var(--r-sm);padding:14px">
                    <?php echo nl2br(htmlspecialchars($req['notes']));?>
                </div>
            </div>
        </div>
        <?php endif;?>
    </div>

    <!-- Right sidebar -->
    <div class="col-lg-4">

        <!-- Request metadata -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-clipboard"></i> Request Info</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row"><span class="info-key">Request ID</span><span class="info-val" style="color:var(--gold)">#<?php echo $req['request_id'];?></span></div>
                <div class="info-row">
                    <span class="info-key">Priority</span>
                    <span class="info-val"><span class="priority-pill <?php echo $prioClass;?>"><?php echo strtoupper($req['priority']);?></span></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Status</span>
                    <span class="info-val"><span class="badge-status badge-<?php echo $req['status'];?>"><?php echo ucfirst(str_replace('_',' ',$req['status']));?></span></span>
                </div>
                <div class="info-row"><span class="info-key">Submitted</span><span class="info-val"><?php echo formatDate($req['request_date']);?></span></div>
                <div class="info-row"><span class="info-key">Submitted By</span><span class="info-val"><?php echo htmlspecialchars($req['created_by_name']??'—');?></span></div>
                <div class="info-row"><span class="info-key">Assigned To</span><span class="info-val"><?php echo htmlspecialchars($req['assigned_name']??'Unassigned');?></span></div>
                <?php if($req['completion_date']):?>
                <div class="info-row"><span class="info-key">Completed</span><span class="info-val" style="color:var(--green)"><?php echo formatDate($req['completion_date']);?></span></div>
                <?php endif;?>
            </div>
        </div>

        <!-- Staff quick actions -->
        <?php if(isStaff()):?>
        <div class="card">
            <div class="card-header"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
                <a href="edit_maintenance.php?id=<?php echo $id;?>" class="btn btn-primary w-100">
                    <i class="fas fa-edit me-1"></i> Edit Request
                </a>
                <?php if($req['status']==='pending'):?>
                <form method="POST" action="edit_maintenance.php?id=<?php echo $id;?>" data-no-spinner>
                    <input type="hidden" name="title"       value="<?php echo htmlspecialchars($req['title']);?>">
                    <input type="hidden" name="description" value="<?php echo htmlspecialchars($req['description']);?>">
                    <input type="hidden" name="priority"    value="<?php echo htmlspecialchars($req['priority']);?>">
                    <input type="hidden" name="notes"       value="<?php echo htmlspecialchars($req['notes']??'');?>">
                    <input type="hidden" name="assigned_to" value="<?php echo htmlspecialchars($req['assigned_to']??'');?>">
                    <input type="hidden" name="status"      value="in_progress">
                    <button type="submit" class="btn btn-outline-primary w-100"><i class="fas fa-play me-1"></i>Start Work</button>
                </form>
                <?php elseif($req['status']==='in_progress'):?>
                <form method="POST" action="edit_maintenance.php?id=<?php echo $id;?>" data-no-spinner>
                    <input type="hidden" name="title"       value="<?php echo htmlspecialchars($req['title']);?>">
                    <input type="hidden" name="description" value="<?php echo htmlspecialchars($req['description']);?>">
                    <input type="hidden" name="priority"    value="<?php echo htmlspecialchars($req['priority']);?>">
                    <input type="hidden" name="notes"       value="<?php echo htmlspecialchars($req['notes']??'');?>">
                    <input type="hidden" name="assigned_to" value="<?php echo htmlspecialchars($req['assigned_to']??'');?>">
                    <input type="hidden" name="status"      value="completed">
                    <button type="submit" class="btn btn-outline-success w-100"><i class="fas fa-check me-1"></i>Mark Completed</button>
                </form>
                <?php endif;?>
                <?php if($req['status']!=='cancelled'):?>
                <form method="POST" action="edit_maintenance.php?id=<?php echo $id;?>" data-no-spinner>
                    <input type="hidden" name="title"       value="<?php echo htmlspecialchars($req['title']);?>">
                    <input type="hidden" name="description" value="<?php echo htmlspecialchars($req['description']);?>">
                    <input type="hidden" name="priority"    value="<?php echo htmlspecialchars($req['priority']);?>">
                    <input type="hidden" name="notes"       value="<?php echo htmlspecialchars($req['notes']??'');?>">
                    <input type="hidden" name="assigned_to" value="<?php echo htmlspecialchars($req['assigned_to']??'');?>">
                    <input type="hidden" name="status"      value="cancelled">
                    <button type="submit" class="btn btn-outline-danger w-100" data-confirm="Cancel this maintenance request?"><i class="fas fa-ban me-1"></i>Cancel Request</button>
                </form>
                <?php endif;?>
                <a href="maintenance.php?delete=<?php echo $id;?>" class="btn btn-outline-danger w-100" data-confirm="Permanently delete this request? Cannot be undone.">
                    <i class="fas fa-trash me-1"></i> Delete
                </a>
            </div>
        </div>
        <?php endif;?>
    </div>
</div>

<?php include 'includes/footer.php';?>