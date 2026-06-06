<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Edit Stall';
$currentPage = 'stalls';
$db = getDB();

$stall_id = intval($_GET['id'] ?? 0);
if (!$stall_id) { header('Location: stalls.php'); exit(); }

$stmt = $db->prepare("SELECT * FROM market_stalls WHERE stall_id = ?");
$stmt->execute([$stall_id]);
$stall = $stmt->fetch();
if (!$stall) { setMessage('danger','Stall not found.'); header('Location: stalls.php'); exit(); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty(trim($_POST['stall_number'] ?? ''))) $errors[] = 'Stall number is required.';
    if (empty(trim($_POST['section'] ?? '')))      $errors[] = 'Section is required.';
    if (!is_numeric($_POST['price_per_day'] ?? ''))   $errors[] = 'Valid daily rate is required.';
    if (!is_numeric($_POST['price_per_month'] ?? '')) $errors[] = 'Valid monthly rate is required.';
    // Check duplicate (excluding self)
    if (empty($errors)) {
        $dup = $db->prepare("SELECT stall_id FROM market_stalls WHERE stall_number=? AND stall_id!=?");
        $dup->execute([trim($_POST['stall_number']), $stall_id]);
        if ($dup->fetch()) $errors[] = 'That stall number is already used by another stall.';
    }
    if (empty($errors)) {
        try {
            $db->prepare("UPDATE market_stalls SET
                stall_number=?,section=?,location=?,size_sqm=?,
                price_per_day=?,price_per_month=?,payment_due_day=?,status=?,description=?
                WHERE stall_id=?")
              ->execute([
                trim($_POST['stall_number']),
                trim($_POST['section']),
                trim($_POST['location'] ?? ''),
                $_POST['size_sqm'] ?: null,
                $_POST['price_per_day'],
                $_POST['price_per_month'],
                intval($_POST['payment_due_day'] ?? 1),
                $_POST['status'],
                trim($_POST['description'] ?? ''),
                $stall_id
            ]);
            setMessage('success','Stall updated successfully!');
            header('Location: stalls.php'); exit();
        } catch(Exception $e){ $errors[]='Database error: '.$e->getMessage(); }
    }
    // On error, use posted values
    $stall = array_merge($stall, $_POST);
}

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-edit me-2"></i>Edit Stall — <?php echo htmlspecialchars($stall['stall_number']);?></h1>
        <p class="page-subtitle">Update stall information</p>
    </div>
    <a href="stalls.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Stalls</a>
</div>

<?php if(!empty($errors)):?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-2"><?php foreach($errors as $e):?><li><?php echo htmlspecialchars($e);?></li><?php endforeach;?></ul>
</div>
<?php endif;?>

<div class="card" style="max-width:780px">
    <div class="card-header"><i class="fas fa-store me-2"></i>Stall Details</div>
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Stall Number <span class="text-danger">*</span></label>
                    <input type="text" name="stall_number" class="form-control"
                           value="<?php echo htmlspecialchars($stall['stall_number']);?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Section <span class="text-danger">*</span></label>
                    <input type="text" name="section" class="form-control"
                           value="<?php echo htmlspecialchars($stall['section']);?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($stall['location']??'');?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Size (sqm)</label>
                    <input type="number" name="size_sqm" class="form-control" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($stall['size_sqm']??'');?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Daily Rate (₱) <span class="text-danger">*</span></label>
                    <input type="number" name="price_per_day" class="form-control" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($stall['price_per_day']);?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monthly Rate (₱) <span class="text-danger">*</span></label>
                    <input type="number" name="price_per_month" class="form-control" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($stall['price_per_month']);?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Due Day</label>
                    <select name="payment_due_day" class="form-select">
                        <?php for($i=1;$i<=31;$i++):?>
                        <option value="<?php echo $i;?>" <?php echo intval($stall['payment_due_day']??1)===$i?'selected':'';?>>Day <?php echo $i;?> of month</option>
                        <?php endfor;?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="available"   <?php echo $stall['status']==='available'  ?'selected':'';?>>Available</option>
                        <option value="occupied"    <?php echo $stall['status']==='occupied'   ?'selected':'';?>>Occupied</option>
                        <option value="maintenance" <?php echo $stall['status']==='maintenance'?'selected':'';?>>Under Maintenance</option>
                        <option value="reserved"    <?php echo $stall['status']==='reserved'   ?'selected':'';?>>Reserved</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description / Notes</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($stall['description']??'');?></textarea>
                </div>
            </div>
            <hr style="border-color:var(--gold-bd);margin:24px 0">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Stall</button>
                <a href="stalls.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Stall info panel -->
<div class="card mt-3" style="max-width:780px">
    <div class="card-header"><i class="fas fa-info-circle me-2"></i>Stall Info</div>
    <div class="card-body">
        <div class="row g-3" style="font-size:.86rem">
            <div class="col-md-4"><div style="color:var(--txt-mut)">Stall ID</div><strong>#<?php echo $stall['stall_id'];?></strong></div>
            <div class="col-md-4"><div style="color:var(--txt-mut)">Created</div><strong><?php echo formatDate($stall['created_at']);?></strong></div>
            <div class="col-md-4"><div style="color:var(--txt-mut)">Current Status</div><span class="badge-status badge-<?php echo $stall['status'];?>"><?php echo ucfirst($stall['status']);?></span></div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>