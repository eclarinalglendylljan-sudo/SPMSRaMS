<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Stall Management';
$currentPage = 'stalls';
$db = getDB();

if (isset($_GET['delete'])) {
    try {
        $chk = $db->prepare("SELECT status, stall_number FROM market_stalls WHERE stall_id=?");
        $chk->execute([intval($_GET['delete'])]);
        $row = $chk->fetch();
        if ($row && $row['status']==='occupied') {
            setMessage('danger','Cannot delete stall '.$row['stall_number'].' — it is currently occupied.');
        } else {
            $db->prepare("DELETE FROM market_stalls WHERE stall_id=?")->execute([intval($_GET['delete'])]);
            setMessage('success','Stall deleted successfully.');
        }
    } catch(Exception $e){ setMessage('danger','Error: '.$e->getMessage()); }
    header('Location: stalls.php'); exit();
}

$sf = $_GET['section'] ?? '';
$tf = $_GET['status']  ?? '';
$q  = "SELECT ms.*, t.full_name AS tenant_name, t.business_name
       FROM market_stalls ms
       LEFT JOIN rental_records rr ON ms.stall_id=rr.stall_id AND rr.status='active'
       LEFT JOIN tenants t ON rr.tenant_id=t.tenant_id WHERE 1=1";
$p=[];
if($sf){$q.=" AND ms.section=?";$p[]=$sf;}
if($tf){$q.=" AND ms.status=?";$p[]=$tf;}
$q.=" ORDER BY ms.section, ms.stall_number";
$stmt=$db->prepare($q);$stmt->execute($p);$stalls=$stmt->fetchAll();

$total  =(int)$db->query("SELECT COUNT(*) FROM market_stalls")->fetchColumn();
$avail  =(int)$db->query("SELECT COUNT(*) FROM market_stalls WHERE status='available'")->fetchColumn();
$occ    =(int)$db->query("SELECT COUNT(*) FROM market_stalls WHERE status='occupied'")->fetchColumn();
$maint  =(int)$db->query("SELECT COUNT(*) FROM market_stalls WHERE status='maintenance'")->fetchColumn();
$sects  =$db->query("SELECT DISTINCT section FROM market_stalls ORDER BY section")->fetchAll(PDO::FETCH_COLUMN);
include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-store"></i> Stall Management</h1>
        <p class="page-subtitle">Manage market stalls and allocations</p>
    </div>
    <a href="add_stall.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add New Stall</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-store"></i></div><div class="stat-value"><?php echo $total;?></div><div class="stat-label">Total Stalls</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(40,167,69,.4)"><div class="stat-icon" style="color:var(--green)"><i class="fas fa-check-circle"></i></div><div class="stat-value" style="color:var(--green)"><?php echo $avail;?></div><div class="stat-label">Available</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(220,53,69,.4)"><div class="stat-icon" style="color:var(--red)"><i class="fas fa-user"></i></div><div class="stat-value" style="color:var(--red)"><?php echo $occ;?></div><div class="stat-label">Occupied</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(255,193,7,.4)"><div class="stat-icon" style="color:var(--amber)"><i class="fas fa-tools"></i></div><div class="stat-value" style="color:var(--amber)"><?php echo $maint;?></div><div class="stat-label">Maintenance</div></div></div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" data-no-spinner>
            <div class="col-md-4"><label class="form-label">Section</label>
                <select name="section" class="form-select"><option value="">All Sections</option>
                <?php foreach($sects as $s): ?><option value="<?php echo htmlspecialchars($s);?>" <?php echo $sf===$s?'selected':'';?>>Section <?php echo htmlspecialchars($s);?></option><?php endforeach;?>
                </select></div>
            <div class="col-md-4"><label class="form-label">Status</label>
                <select name="status" class="form-select"><option value="">All Status</option>
                <option value="available" <?php echo $tf==='available'?'selected':'';?>>Available</option>
                <option value="occupied" <?php echo $tf==='occupied'?'selected':'';?>>Occupied</option>
                <option value="maintenance" <?php echo $tf==='maintenance'?'selected':'';?>>Maintenance</option>
                <option value="reserved" <?php echo $tf==='reserved'?'selected':'';?>>Reserved</option>
                </select></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button></div>
            <div class="col-md-2"><a href="stalls.php" class="btn btn-secondary w-100"><i class="fas fa-sync me-1"></i>Reset</a></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><i class="fas fa-list me-1"></i> Stall List <span style="font-weight:400;font-size:.82rem;color:var(--txt-mut)">(<?php echo count($stalls);?> stalls)</span></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Stall #</th><th>Section</th><th>Location</th><th>Size</th><th>Daily</th><th>Monthly</th><th>Due Day</th><th>Status</th><th>Tenant</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($stalls)):?>
            <tr><td colspan="10" class="text-center py-5" style="color:var(--txt-mut)"><i class="fas fa-store fa-2x d-block mb-2" style="opacity:.3"></i>No stalls found. <a href="add_stall.php" class="btn btn-primary btn-sm ms-2"><i class="fas fa-plus me-1"></i>Add First Stall</a></td></tr>
            <?php else: foreach($stalls as $s):?>
            <tr>
                <td><strong style="color:var(--gold)"><?php echo htmlspecialchars($s['stall_number']);?></strong></td>
                <td><?php echo htmlspecialchars($s['section']);?></td>
                <td style="color:var(--txt-mut)"><?php echo htmlspecialchars($s['location']??'—');?></td>
                <td><?php echo $s['size_sqm']?number_format($s['size_sqm'],2).' sqm':'—';?></td>
                <td style="color:var(--green);font-weight:600"><?php echo formatCurrency($s['price_per_day']);?></td>
                <td style="color:var(--green);font-weight:600"><?php echo formatCurrency($s['price_per_month']);?></td>
                <td><span style="background:var(--blue-bg);color:var(--blue);border:1px solid rgba(23,162,184,.3);padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700">Day <?php echo intval($s['payment_due_day']??1);?></span></td>
                <td><span class="badge-status badge-<?php echo $s['status'];?>"><?php echo ucfirst($s['status']);?></span></td>
                <td><?php if($s['tenant_name']):?><div style="font-weight:600;font-size:.84rem"><?php echo htmlspecialchars($s['tenant_name']);?></div><div style="font-size:.74rem;color:var(--txt-mut)"><?php echo htmlspecialchars($s['business_name']);?></div><?php else:?><span style="color:var(--txt-mut)">—</span><?php endif;?></td>
                <td><div class="d-flex gap-1">
                    <a href="edit_stall.php?id=<?php echo $s['stall_id'];?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="stalls.php?delete=<?php echo $s['stall_id'];?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete stall <?php echo htmlspecialchars(addslashes($s['stall_number']));?>? This cannot be undone.')"><i class="fas fa-trash"></i></a>
                </div></td>
            </tr>
            <?php endforeach; endif;?>
            </tbody>
        </table>
    </div></div>
</div>
<?php include 'includes/footer.php'; ?>