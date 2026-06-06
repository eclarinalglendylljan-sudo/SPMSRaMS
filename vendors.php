<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Vendor Management';
$currentPage = 'vendors';
$db = getDB();

// Delete vendor
if (isset($_GET['delete'])) {
    try {
        $vid = intval($_GET['delete']);
        $chk = $db->prepare("SELECT t.full_name, rr.record_id FROM tenants t LEFT JOIN rental_records rr ON t.tenant_id=rr.tenant_id AND rr.status='active' WHERE t.tenant_id=?");
        $chk->execute([$vid]);
        $row = $chk->fetch();
        if ($row && $row['record_id']) {
            setMessage('danger', 'Cannot delete ' . htmlspecialchars($row['full_name']) . ' — they have an active rental.');
        } else {
            $db->prepare("DELETE FROM tenants WHERE tenant_id=?")->execute([$vid]);
            setMessage('success', 'Vendor deleted successfully.');
        }
    } catch (Exception $e) { setMessage('danger', 'Error: ' . $e->getMessage()); }
    header('Location: vendors.php'); exit();
}

// Filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$type   = $_GET['type']   ?? '';

$q = "SELECT t.*, u.username, u.status as user_status,
             ms.stall_number, ms.section
      FROM tenants t
      LEFT JOIN users u ON t.user_id = u.user_id
      LEFT JOIN rental_records rr ON t.tenant_id = rr.tenant_id AND rr.status = 'active'
      LEFT JOIN market_stalls ms ON rr.stall_id = ms.stall_id
      WHERE 1=1";
$p = [];
if ($search) { $q .= " AND (t.full_name LIKE ? OR t.business_name LIKE ? OR t.contact_no LIKE ?)"; $s="%$search%"; $p[]=$s;$p[]=$s;$p[]=$s; }
if ($status)  { $q .= " AND t.status = ?";      $p[] = $status; }
if ($type)    { $q .= " AND t.tenant_type = ?";  $p[] = $type; }
$q .= " ORDER BY t.full_name";
$stmt = $db->prepare($q); $stmt->execute($p);
$vendors = $stmt->fetchAll();

$total  = (int)$db->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
$active = (int)$db->query("SELECT COUNT(*) FROM tenants WHERE status='active'")->fetchColumn();
$perm   = (int)$db->query("SELECT COUNT(*) FROM tenants WHERE tenant_type='permanent'")->fetchColumn();
$temp   = (int)$db->query("SELECT COUNT(*) FROM tenants WHERE tenant_type='temporary'")->fetchColumn();

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-users"></i> Vendor Management</h1>
        <p class="page-subtitle">Manage all market vendors and tenants</p>
    </div>
    <a href="add_vendor.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add New Vendor</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-value"><?php echo $total;?></div><div class="stat-label">Total Vendors</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(40,167,69,.4)"><div class="stat-icon" style="color:var(--green)"><i class="fas fa-user-check"></i></div><div class="stat-value" style="color:var(--green)"><?php echo $active;?></div><div class="stat-label">Active</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(255,215,0,.4)"><div class="stat-icon" style="color:var(--gold)"><i class="fas fa-home"></i></div><div class="stat-value" style="color:var(--gold)"><?php echo $perm;?></div><div class="stat-label">Permanent</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(23,162,184,.4)"><div class="stat-icon" style="color:var(--blue)"><i class="fas fa-clock"></i></div><div class="stat-value" style="color:var(--blue)"><?php echo $temp;?></div><div class="stat-label">Temporary</div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" data-no-spinner>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, business, contact…" value="<?php echo htmlspecialchars($search);?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active"   <?php echo $status==='active'  ?'selected':'';?>>Active</option>
                    <option value="inactive" <?php echo $status==='inactive'?'selected':'';?>>Inactive</option>
                    <option value="pending"  <?php echo $status==='pending' ?'selected':'';?>>Pending</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="permanent"  <?php echo $type==='permanent' ?'selected':'';?>>Permanent</option>
                    <option value="temporary"  <?php echo $type==='temporary'?'selected':'';?>>Temporary</option>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Search</button></div>
            <div class="col-md-2"><a href="vendors.php" class="btn btn-secondary w-100"><i class="fas fa-sync me-1"></i>Reset</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-list me-1"></i> Vendors <span style="font-weight:400;font-size:.82rem;color:var(--txt-mut)">(<?php echo count($vendors);?> results)</span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Vendor</th><th>Business</th><th>Contact</th><th>Type</th><th>Stall</th><th>Balance</th><th>Status</th><th style="width:110px">Actions</th></tr>
                </thead>
                <tbody>
                <?php if(empty($vendors)):?>
                <tr><td colspan="8" class="text-center py-5" style="color:var(--txt-mut)">
                    <i class="fas fa-users fa-2x d-block mb-2" style="opacity:.3"></i>No vendors found.
                    <a href="add_vendor.php" class="btn btn-primary btn-sm ms-2"><i class="fas fa-plus me-1"></i>Add First Vendor</a>
                </td></tr>
                <?php else: foreach($vendors as $v):?>
                <tr>
                    <td>
                        <div style="font-weight:600"><?php echo htmlspecialchars($v['full_name']);?></div>
                        <div style="font-size:.75rem;color:var(--txt-mut)"><?php echo htmlspecialchars($v['username']??'—');?></div>
                    </td>
                    <td>
                        <div style="font-weight:500"><?php echo htmlspecialchars($v['business_name']);?></div>
                        <div style="font-size:.75rem;color:var(--txt-mut)"><?php echo htmlspecialchars($v['business_type']??'—');?></div>
                    </td>
                    <td style="font-size:.84rem"><?php echo htmlspecialchars($v['contact_no']);?></td>
                    <td>
                        <span class="badge-status <?php echo $v['tenant_type']==='permanent'?'badge-active':'badge-reserved';?>">
                            <?php echo ucfirst($v['tenant_type']);?>
                        </span>
                    </td>
                    <td>
                        <?php if($v['stall_number']):?>
                            <span style="background:var(--gold-dim);border:1px solid var(--gold-bd);color:var(--gold);padding:2px 9px;border-radius:6px;font-size:.78rem;font-weight:700">
                                <?php echo htmlspecialchars($v['stall_number']);?>
                            </span>
                            <div style="font-size:.74rem;color:var(--txt-mut)">Sec <?php echo htmlspecialchars($v['section']);?></div>
                        <?php else:?>
                            <span style="color:var(--txt-mut)">No stall</span>
                        <?php endif;?>
                    </td>
                    <td style="font-weight:600;color:<?php echo ($v['balance']??0)>0?'var(--red)':'var(--green)';?>">
                        <?php echo formatCurrency($v['balance']??0);?>
                    </td>
                    <td><span class="badge-status badge-<?php echo $v['status'];?>"><?php echo ucfirst($v['status']);?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="view_vendor.php?id=<?php echo $v['tenant_id'];?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                            <a href="edit_vendor.php?id=<?php echo $v['tenant_id'];?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="vendors.php?delete=<?php echo $v['tenant_id'];?>" class="btn btn-sm btn-outline-danger" title="Delete"
                               onclick="return confirm('Delete vendor <?php echo htmlspecialchars(addslashes($v['full_name']));?>?\nThis cannot be undone.')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif;?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>