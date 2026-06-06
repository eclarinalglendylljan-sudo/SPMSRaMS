<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Edit Maintenance Request';
$currentPage = 'maintenance';
$db = getDB();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: maintenance.php'); exit(); }

$stmt = $db->prepare("SELECT mr.*,ms.stall_number,ms.section FROM maintenance_requests mr JOIN market_stalls ms ON mr.stall_id=ms.stall_id WHERE mr.request_id=?");
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) { setMessage('danger','Request not found.'); header('Location: maintenance.php'); exit(); }

$errors = [];
$stalls = $db->query("SELECT stall_id,stall_number,section FROM market_stalls ORDER BY section,stall_number")->fetchAll();
$staff  = $db->query("SELECT user_id,full_name FROM users WHERE role IN ('staff','administrator') AND status='active' ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority'] ?? 'medium';
    $status      = $_POST['status']   ?? 'pending';
    $assigned_to = intval($_POST['assigned_to'] ?? 0) ?: null;
    $notes       = trim($_POST['notes'] ?? '');
    $completion  = ($status==='completed' && !$req['completion_date']) ? date('Y-m-d H:i:s') : $req['completion_date'];

    if (empty($title))     $errors[]='Title is required.';
    if (empty($description))$errors[]='Description is required.';

    if (empty($errors)) {
        try {
            $db->prepare("UPDATE maintenance_requests SET title=?,description=?,priority=?,status=?,assigned_to=?,notes=?,completion_date=? WHERE request_id=?")
               ->execute([$title,$description,$priority,$status,$assigned_to,$notes,$completion,$id]);
            setMessage('success','Request updated successfully!');
            header('Location: view_maintenance.php?id='.$id); exit();
        } catch(Exception $e){ $errors[]='Database error: '.$e->getMessage(); }
    }
    $req=array_merge($req,$_POST);
}

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-edit"></i> Edit Request #<?php echo $id;?></h1>
        <p class="page-subtitle">Update maintenance request details</p>
    </div>
    <div class="d-flex gap-2">
        <a href="view_maintenance.php?id=<?php echo $id;?>" class="btn btn-outline-primary"><i class="fas fa-eye me-1"></i>View</a>
        <a href="maintenance.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<?php if(!empty($errors)):?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i>
    <strong><?php echo count($errors)===1?htmlspecialchars($errors[0]):'Errors:';?></strong>
    <?php if(count($errors)>1):?><ul class="mb-0 mt-2"><?php foreach($errors as $e):?><li><?php echo htmlspecialchars($e);?></li><?php endforeach;?></ul><?php endif;?>
</div>
<?php endif;?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-clipboard-list"></i> Request Details</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">

                        <!-- Stall (read-only — changing stall on an existing request is confusing) -->
                        <div class="col-md-6">
                            <label class="form-label">Stall</label>
                            <input type="text" class="form-control" value="Stall <?php echo htmlspecialchars($req['stall_number']);?> — Section <?php echo htmlspecialchars($req['section']);?>" disabled>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select">
                                <option value="low"    <?php echo $req['priority']==='low'   ?'selected':'';?>>Low</option>
                                <option value="medium" <?php echo $req['priority']==='medium'?'selected':'';?>>Medium</option>
                                <option value="high"   <?php echo $req['priority']==='high'  ?'selected':'';?>>High</option>
                                <option value="urgent" <?php echo $req['priority']==='urgent'?'selected':'';?>>🚨 Urgent</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select">
                                <option value="pending"     <?php echo $req['status']==='pending'    ?'selected':'';?>>Pending</option>
                                <option value="in_progress" <?php echo $req['status']==='in_progress'?'selected':'';?>>In Progress</option>
                                <option value="completed"   <?php echo $req['status']==='completed'  ?'selected':'';?>>Completed</option>
                                <option value="cancelled"   <?php echo $req['status']==='cancelled'  ?'selected':'';?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Assign To</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">Unassigned</option>
                                <?php foreach($staff as $u):?>
                                <option value="<?php echo $u['user_id'];?>" <?php echo intval($req['assigned_to']??0)==$u['user_id']?'selected':'';?>>
                                    <?php echo htmlspecialchars($u['full_name']);?>
                                </option>
                                <?php endforeach;?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($req['title']);?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($req['description']);?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Internal Notes <span style="color:var(--tx-3)">(staff only)</span></label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Resolution steps, parts needed, follow-up actions…"><?php echo htmlspecialchars($req['notes']??'');?></textarea>
                        </div>
                    </div>

                    <hr style="border-color:var(--bd);margin:22px 0">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> Save Changes</button>
                        <a href="maintenance.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle"></i> Request Info</div>
            <div class="card-body" style="padding:14px 18px">
                <div class="info-row"><span class="info-key">Request ID</span><span class="info-val" style="color:var(--gold)">#<?php echo $req['request_id'];?></span></div>
                <div class="info-row"><span class="info-key">Submitted</span><span class="info-val"><?php echo formatDate($req['request_date']);?></span></div>
                <div class="info-row">
                    <span class="info-key">Status</span>
                    <span class="info-val"><span class="badge-status badge-<?php echo $req['status'];?>"><?php echo ucfirst(str_replace('_',' ',$req['status']));?></span></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Priority</span>
                    <?php $pc=['urgent'=>'pp-urgent','high'=>'pp-high','medium'=>'pp-medium','low'=>'pp-low'];?>
                    <span class="info-val"><span class="priority-pill <?php echo $pc[$req['priority']]??'pp-low';?>"><?php echo strtoupper($req['priority']);?></span></span>
                </div>
                <?php if($req['completion_date']):?>
                <div class="info-row"><span class="info-key">Completed</span><span class="info-val" style="color:var(--green)"><?php echo formatDate($req['completion_date']);?></span></div>
                <?php endif;?>
            </div>
        </div>

        <!-- Quick status buttons -->
        <div class="card">
            <div class="card-header"><i class="fas fa-bolt"></i> Quick Status Update</div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
                <?php foreach([
                    ['pending',    'btn-outline-warning','clock',      'Mark Pending'],
                    ['in_progress','btn-outline-primary','spinner',    'Mark In Progress'],
                    ['completed',  'btn-outline-success','check-circle','Mark Completed'],
                    ['cancelled',  'btn-outline-danger', 'ban',        'Mark Cancelled'],
                ] as [$sv,$cls,$ico,$lbl]):?>
                <form method="POST" data-no-spinner>
                    <input type="hidden" name="title"       value="<?php echo htmlspecialchars($req['title']);?>">
                    <input type="hidden" name="description" value="<?php echo htmlspecialchars($req['description']);?>">
                    <input type="hidden" name="priority"    value="<?php echo htmlspecialchars($req['priority']);?>">
                    <input type="hidden" name="notes"       value="<?php echo htmlspecialchars($req['notes']??'');?>">
                    <input type="hidden" name="assigned_to" value="<?php echo htmlspecialchars($req['assigned_to']??'');?>">
                    <input type="hidden" name="status"      value="<?php echo $sv;?>">
                    <button type="submit" class="btn <?php echo $cls;?> btn-sm w-100 <?php echo $req['status']===$sv?'disabled':'';?>" <?php echo $req['status']===$sv?'disabled':'';?>>
                        <i class="fas fa-<?php echo $ico;?> me-1"></i><?php echo $lbl;?>
                    </button>
                </form>
                <?php endforeach;?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php';?>