<?php
require_once 'config.php';
requireLogin();

$pageTitle   = 'Maintenance';
$currentPage = 'maintenance';
$db = getDB();

// Delete
if (isset($_GET['delete']) && isStaff()) {
    try {
        $db->prepare("DELETE FROM maintenance_requests WHERE request_id=?")->execute([intval($_GET['delete'])]);
        setMessage('success', 'Request deleted.');
    } catch (Exception $e) { setMessage('danger', 'Error: '.$e->getMessage()); }
    header('Location: maintenance.php'); exit();
}

// Filters
$sf = $_GET['status']   ?? '';
$pf = $_GET['priority'] ?? '';
$qf = $_GET['search']   ?? '';

$q = "SELECT mr.*,
             ms.stall_number,
             t.full_name  AS tenant_name,
             u.full_name  AS assigned_name
      FROM maintenance_requests mr
      JOIN market_stalls ms ON mr.stall_id = ms.stall_id
      LEFT JOIN tenants t   ON mr.tenant_id = t.tenant_id
      LEFT JOIN users u     ON mr.assigned_to = u.user_id
      WHERE 1=1";
$p = [];
if ($sf) { $q .= " AND mr.status=?";   $p[]=$sf; }
if ($pf) { $q .= " AND mr.priority=?"; $p[]=$pf; }
if ($qf) { $q .= " AND (mr.title LIKE ? OR ms.stall_number LIKE ? OR t.full_name LIKE ?)";
           $s="%$qf%"; $p[]=$s;$p[]=$s;$p[]=$s; }

// Vendors only see their own
if (isVendor()) {
    $stmt = $db->prepare("SELECT tenant_id FROM tenants WHERE user_id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $tid = $stmt->fetchColumn();
    if ($tid) { $q .= " AND mr.tenant_id=?"; $p[]=$tid; }
}

$q .= " ORDER BY FIELD(mr.priority,'urgent','high','medium','low'), mr.request_date DESC";
$stmt=$db->prepare($q);$stmt->execute($p);
$requests=$stmt->fetchAll();

// Stats
$total     =(int)$db->query("SELECT COUNT(*) FROM maintenance_requests")->fetchColumn();
$pending   =(int)$db->query("SELECT COUNT(*) FROM maintenance_requests WHERE status='pending'")->fetchColumn();
$inprog    =(int)$db->query("SELECT COUNT(*) FROM maintenance_requests WHERE status='in_progress'")->fetchColumn();
$completed =(int)$db->query("SELECT COUNT(*) FROM maintenance_requests WHERE status='completed'")->fetchColumn();

// Staff list for dropdown
$staff = [];
if (isStaff()) {
    $staff = $db->query("SELECT user_id,full_name FROM users WHERE role IN ('staff','administrator') AND status='active' ORDER BY full_name")->fetchAll();
}

// Stalls for add form
$stalls_all = $db->query("SELECT stall_id,stall_number,section FROM market_stalls ORDER BY section,stall_number")->fetchAll();

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-tools"></i> Maintenance Management</h1>
        <p class="page-subtitle">Track and manage stall maintenance requests</p>
    </div>
    <a href="add_maintenance.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> New Request
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-value"><?php echo $total;?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:var(--amber-bd)">
            <div class="stat-icon" style="color:var(--amber)"><i class="fas fa-clock"></i></div>
            <div class="stat-value" style="color:var(--amber)"><?php echo $pending;?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:var(--blue-bd)">
            <div class="stat-icon" style="color:var(--blue)"><i class="fas fa-spinner"></i></div>
            <div class="stat-value" style="color:var(--blue)"><?php echo $inprog;?></div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:var(--green-bd)">
            <div class="stat-icon" style="color:var(--green)"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value" style="color:var(--green)"><?php echo $completed;?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" data-no-spinner>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Title, stall, vendor…" value="<?php echo htmlspecialchars($qf);?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending"     <?php echo $sf==='pending'    ?'selected':'';?>>Pending</option>
                    <option value="in_progress" <?php echo $sf==='in_progress'?'selected':'';?>>In Progress</option>
                    <option value="completed"   <?php echo $sf==='completed'  ?'selected':'';?>>Completed</option>
                    <option value="cancelled"   <?php echo $sf==='cancelled'  ?'selected':'';?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All Priority</option>
                    <option value="urgent" <?php echo $pf==='urgent'?'selected':'';?>>Urgent</option>
                    <option value="high"   <?php echo $pf==='high'  ?'selected':'';?>>High</option>
                    <option value="medium" <?php echo $pf==='medium'?'selected':'';?>>Medium</option>
                    <option value="low"    <?php echo $pf==='low'   ?'selected':'';?>>Low</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <a href="maintenance.php" class="btn btn-secondary w-100"><i class="fas fa-sync me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-header" style="justify-content:space-between">
        <span><i class="fas fa-list"></i> Requests <span style="font-weight:400;font-size:.8rem;color:var(--tx-3)">(<?php echo count($requests);?>)</span></span>
    </div>
    <div class="card-body p-0">
        <?php if(empty($requests)):?>
        <div style="text-align:center;padding:56px;color:var(--tx-3)">
            <i class="fas fa-tools fa-2x d-block mb-3" style="opacity:.2"></i>
            No maintenance requests found.
            <div class="mt-3"><a href="add_maintenance.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Request</a></div>
        </div>
        <?php else:?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>ID</th><th>Stall</th><th>Title</th><th>Priority</th>
                        <th>Status</th><th>Requested By</th><th>Assigned To</th><th>Date</th><th style="width:110px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($requests as $r):
                    $prioClass=['urgent'=>'pp-urgent','high'=>'pp-high','medium'=>'pp-medium','low'=>'pp-low'][$r['priority']]??'pp-low';
                ?>
                <tr>
                    <td style="color:var(--tx-3);font-size:.8rem">#<?php echo $r['request_id'];?></td>
                    <td>
                        <span style="background:var(--gold-dim);color:var(--gold);border:1px solid var(--gold-bd);padding:2px 8px;border-radius:5px;font-size:.75rem;font-weight:700">
                            <?php echo htmlspecialchars($r['stall_number']);?>
                        </span>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--tx-1)"><?php echo htmlspecialchars($r['title']);?></div>
                        <div style="font-size:.75rem;color:var(--tx-3);margin-top:1px"><?php echo htmlspecialchars(mb_strimwidth($r['description'],0,50,'…'));?></div>
                    </td>
                    <td><span class="priority-pill <?php echo $prioClass;?>"><?php echo strtoupper($r['priority']);?></span></td>
                    <td><span class="badge-status badge-<?php echo $r['status'];?>"><?php echo ucfirst(str_replace('_',' ',$r['status']));?></span></td>
                    <td style="color:var(--tx-2)"><?php echo htmlspecialchars($r['tenant_name']??'Staff');?></td>
                    <td style="color:var(--tx-2)"><?php echo htmlspecialchars($r['assigned_name']??'—');?></td>
                    <td style="color:var(--tx-3);font-size:.8rem"><?php echo formatDate($r['request_date']);?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="view_maintenance.php?id=<?php echo $r['request_id'];?>" class="btn btn-sm btn-outline-primary btn-icon" title="View"><i class="fas fa-eye" style="font-size:.72rem"></i></a>
                            <?php if(isStaff()):?>
                            <a href="edit_maintenance.php?id=<?php echo $r['request_id'];?>" class="btn btn-sm btn-outline-warning btn-icon" title="Edit"><i class="fas fa-edit" style="font-size:.72rem"></i></a>
                            <a href="maintenance.php?delete=<?php echo $r['request_id'];?>" class="btn btn-sm btn-outline-danger btn-icon" title="Delete"
                               data-confirm="Delete this maintenance request? This cannot be undone.">
                                <i class="fas fa-trash" style="font-size:.72rem"></i>
                            </a>
                            <?php endif;?>
                        </div>
                    </td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
        <?php endif;?>
    </div>
</div>

<?php include 'includes/footer.php';?>