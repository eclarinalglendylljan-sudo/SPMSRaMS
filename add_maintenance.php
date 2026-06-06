<?php
require_once 'config.php';
requireLogin();

$pageTitle   = 'New Maintenance Request';
$currentPage = 'maintenance';
$db = getDB();

$errors = [];
$old    = [];

// Stalls dropdown — staff sees all, vendor sees only their rented stalls
if (isVendor()) {
    $stmt = $db->prepare("SELECT tenant_id FROM tenants WHERE user_id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $myTid = $stmt->fetchColumn();
    $stalls = $myTid
        ? $db->prepare("SELECT DISTINCT ms.stall_id,ms.stall_number,ms.section FROM market_stalls ms JOIN rental_records rr ON ms.stall_id=rr.stall_id WHERE rr.tenant_id=? AND rr.status='active' ORDER BY ms.section,ms.stall_number")
        : null;
    if ($stalls) { $stalls->execute([$myTid]); $stalls=$stalls->fetchAll(); } else { $stalls=[]; }
} else {
    $stalls = $db->query("SELECT stall_id,stall_number,section FROM market_stalls ORDER BY section,stall_number")->fetchAll();
}

$staff = isStaff()
    ? $db->query("SELECT user_id,full_name FROM users WHERE role IN ('staff','administrator') AND status='active' ORDER BY full_name")->fetchAll()
    : [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $old = $_POST;
    $stall_id    = intval($_POST['stall_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority'] ?? 'medium';
    $assigned_to = intval($_POST['assigned_to'] ?? 0) ?: null;

    if (!$stall_id)        $errors[]='Please select a stall.';
    if (empty($title))     $errors[]='Title is required.';
    if (strlen($title)<5)  $errors[]='Title must be at least 5 characters.';
    if (empty($description))    $errors[]='Description is required.';
    if (strlen($description)<10)$errors[]='Description must be at least 10 characters.';

    if (empty($errors)) {
        try {
            // Resolve tenant_id
            $tid = null;
            if (isVendor()) {
                $stmt=$db->prepare("SELECT tenant_id FROM tenants WHERE user_id=?");
                $stmt->execute([$_SESSION['user_id']]);
                $tid=$stmt->fetchColumn()?:null;
            } else {
                $stmt=$db->prepare("SELECT t.tenant_id FROM rental_records rr JOIN tenants t ON rr.tenant_id=t.tenant_id WHERE rr.stall_id=? AND rr.status='active' LIMIT 1");
                $stmt->execute([$stall_id]);
                $tid=$stmt->fetchColumn()?:null;
            }

            $db->prepare("INSERT INTO maintenance_requests (stall_id,tenant_id,title,description,priority,status,assigned_to,created_by) VALUES (?,?,?,?,?,'pending',?,?)")
               ->execute([$stall_id,$tid,$title,$description,$priority,$assigned_to,$_SESSION['user_id']]);

            setMessage('success','Maintenance request submitted!');
            header('Location: maintenance.php'); exit();
        } catch(Exception $e){ $errors[]='Database error: '.$e->getMessage(); }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-plus-circle"></i> New Maintenance Request</h1>
        <p class="page-subtitle">Submit a new stall maintenance request</p>
    </div>
    <a href="maintenance.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<?php if(!empty($errors)):?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i>
    <strong><?php echo count($errors)===1?htmlspecialchars($errors[0]):'Please fix the following:';?></strong>
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
                        <div class="col-md-8">
                            <label class="form-label">Stall <span class="text-danger">*</span></label>
                            <select name="stall_id" class="form-select" required>
                                <option value="">Select stall…</option>
                                <?php foreach($stalls as $s):?>
                                <option value="<?php echo $s['stall_id'];?>" <?php echo intval($old['stall_id']??0)==$s['stall_id']?'selected':'';?>>
                                    Stall <?php echo htmlspecialchars($s['stall_number']);?> — Section <?php echo htmlspecialchars($s['section']);?>
                                </option>
                                <?php endforeach;?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select" required>
                                <option value="low"    <?php echo($old['priority']??'')==='low'   ?'selected':'';?>>Low</option>
                                <option value="medium" <?php echo($old['priority']??'medium')==='medium'?'selected':'';?>>Medium</option>
                                <option value="high"   <?php echo($old['priority']??'')==='high'  ?'selected':'';?>>High</option>
                                <option value="urgent" <?php echo($old['priority']??'')==='urgent'?'selected':'';?>>🚨 Urgent</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Issue Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control"
                                   value="<?php echo htmlspecialchars($old['title']??'');?>"
                                   placeholder="e.g. Leaking roof, broken door, electrical issue…"
                                   maxlength="200" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Detailed Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="5"
                                      placeholder="Describe the issue: location, when it started, severity…" required><?php echo htmlspecialchars($old['description']??'');?></textarea>
                        </div>
                        <?php if(isStaff()&&!empty($staff)):?>
                        <div class="col-12">
                            <label class="form-label">Assign To <span style="color:var(--tx-3)">(optional)</span></label>
                            <select name="assigned_to" class="form-select">
                                <option value="">Unassigned</option>
                                <?php foreach($staff as $u):?>
                                <option value="<?php echo $u['user_id'];?>" <?php echo intval($old['assigned_to']??0)==$u['user_id']?'selected':'';?>>
                                    <?php echo htmlspecialchars($u['full_name']);?>
                                </option>
                                <?php endforeach;?>
                            </select>
                        </div>
                        <?php endif;?>
                    </div>
                    <hr style="border-color:var(--bd);margin:22px 0">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-1"></i> Submit Request</button>
                        <a href="maintenance.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Priority guide -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle"></i> Priority Guide</div>
            <div class="card-body" style="font-size:.83rem">
                <?php foreach([
                    ['LOW','var(--gray)','Minor or cosmetic — non-urgent'],
                    ['MEDIUM','var(--blue)','Needs attention soon, no emergency'],
                    ['HIGH','var(--amber)','Significant damage or safety concern'],
                    ['URGENT','var(--red)','Immediate hazard — flooding, fire risk'],
                ] as [$lbl,$clr,$desc]):?>
                <div style="display:flex;gap:10px;margin-bottom:12px;align-items:flex-start">
                    <span style="color:<?php echo $clr;?>;font-weight:700;min-width:58px;font-size:.7rem"><?php echo $lbl;?></span>
                    <span style="color:var(--tx-3)"><?php echo $desc;?></span>
                </div>
                <?php endforeach;?>
            </div>
        </div>
        <!-- What happens next -->
        <div class="card">
            <div class="card-header"><i class="fas fa-list-ol"></i> What Happens Next</div>
            <div class="card-body" style="font-size:.83rem;color:var(--tx-2)">
                <?php foreach(['Request submitted as Pending','Admin reviews and assigns staff','Work is carried out','Marked Completed when done'] as $i=>$step):?>
                <div style="display:flex;gap:10px;margin-bottom:<?php echo $i<3?'12px':'0';?>">
                    <div style="width:22px;height:22px;border-radius:50%;background:var(--gold);color:#000;font-weight:800;font-size:.72rem;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?php echo $i+1;?></div>
                    <div style="padding-top:2px"><?php echo $step;?></div>
                </div>
                <?php endforeach;?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php';?>