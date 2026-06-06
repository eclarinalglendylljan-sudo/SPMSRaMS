<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle   = 'Add New Stall';
$currentPage = 'stalls';
$db = getDB();
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    // Validate
    if (empty(trim($_POST['stall_number'] ?? ''))) $errors[] = 'Stall number is required.';
    if (empty(trim($_POST['section'] ?? '')))      $errors[] = 'Section is required.';
    if (!is_numeric($_POST['price_per_day'] ?? ''))   $errors[] = 'Valid daily rate is required.';
    if (!is_numeric($_POST['price_per_month'] ?? '')) $errors[] = 'Valid monthly rate is required.';
    // Check duplicate stall number
    if (empty($errors)) {
        $dup = $db->prepare("SELECT stall_id FROM market_stalls WHERE stall_number = ?");
        $dup->execute([trim($_POST['stall_number'])]);
        if ($dup->fetch()) $errors[] = 'Stall number already exists.';
    }
    if (empty($errors)) {
        try {
            $db->prepare("INSERT INTO market_stalls
                (stall_number,section,location,size_sqm,price_per_day,price_per_month,payment_due_day,status,description)
                VALUES (?,?,?,?,?,?,?,?,?)")
              ->execute([
                trim($_POST['stall_number']),
                trim($_POST['section']),
                trim($_POST['location'] ?? ''),
                $_POST['size_sqm'] ?: null,
                $_POST['price_per_day'],
                $_POST['price_per_month'],
                intval($_POST['payment_due_day'] ?? 1),
                $_POST['status'],
                trim($_POST['description'] ?? '')
            ]);
            setMessage('success','Stall "'.htmlspecialchars(trim($_POST['stall_number'])).'" added successfully!');
            header('Location: stalls.php'); exit();
        } catch(Exception $e){
            $errors[] = 'Database error: '.$e->getMessage();
        }
    }
}
include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-plus me-2"></i>Add New Stall</h1>
        <p class="page-subtitle">Fill in the details below to add a new stall</p>
    </div>
    <a href="stalls.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Stalls</a>
</div>

<?php if(!empty($errors)):?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach($errors as $e):?><li><?php echo htmlspecialchars($e);?></li><?php endforeach;?>
    </ul>
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
                           value="<?php echo htmlspecialchars($old['stall_number']??'');?>"
                           placeholder="e.g. A-01" required>
                    <div class="form-text">Must be unique across all stalls</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Section <span class="text-danger">*</span></label>
                    <input type="text" name="section" class="form-control"
                           value="<?php echo htmlspecialchars($old['section']??'');?>"
                           placeholder="e.g. A" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($old['location']??'');?>"
                           placeholder="e.g. Ground Floor, North Wing">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Size (sqm)</label>
                    <input type="number" name="size_sqm" class="form-control" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($old['size_sqm']??'');?>"
                           placeholder="e.g. 12.50">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Daily Rate (₱) <span class="text-danger">*</span></label>
                    <input type="number" name="price_per_day" class="form-control" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($old['price_per_day']??'');?>"
                           placeholder="0.00" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monthly Rate (₱) <span class="text-danger">*</span></label>
                    <input type="number" name="price_per_month" class="form-control" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($old['price_per_month']??'');?>"
                           placeholder="0.00" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Due Day</label>
                    <select name="payment_due_day" class="form-select">
                        <?php for($i=1;$i<=31;$i++):?>
                        <option value="<?php echo $i;?>" <?php echo ($old['payment_due_day']??1)==$i?'selected':'';?>>
                            Day <?php echo $i;?> of month
                        </option>
                        <?php endfor;?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Initial Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="available" <?php echo ($old['status']??'available')==='available'?'selected':'';?>>Available</option>
                        <option value="occupied"  <?php echo ($old['status']??'')==='occupied' ?'selected':'';?>>Occupied</option>
                        <option value="maintenance" <?php echo ($old['status']??'')==='maintenance'?'selected':'';?>>Under Maintenance</option>
                        <option value="reserved"  <?php echo ($old['status']??'')==='reserved' ?'selected':'';?>>Reserved</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description / Notes</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="Any additional notes about this stall…"><?php echo htmlspecialchars($old['description']??'');?></textarea>
                </div>
            </div>
            <hr style="border-color:var(--gold-bd);margin:24px 0">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Save Stall
                </button>
                <a href="stalls.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>