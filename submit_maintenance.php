<?php
require_once 'config.php';
requireLogin();
requirePermission('vendor');

$pageTitle   = 'Report Issue';
$currentPage = 'maintenance';
$db = getDB();

$stmt = $db->prepare("SELECT * FROM tenants WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tenant = $stmt->fetch();
if (!$tenant) { header('Location: vendorapplication.php'); exit(); }

// Get vendor's active stalls
$stmt = $db->prepare("
    SELECT DISTINCT ms.stall_id, ms.stall_number, ms.section
    FROM market_stalls ms
    JOIN rental_records rr ON ms.stall_id = rr.stall_id
    WHERE rr.tenant_id = ? AND rr.status = 'active'
    ORDER BY ms.section, ms.stall_number
");
$stmt->execute([$tenant['tenant_id']]);
$stalls = $stmt->fetchAll();

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $stall_id    = intval($_POST['stall_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority'] ?? 'medium';

    if (!$stall_id)          $errors[] = 'Please select a stall.';
    if (empty($title))       $errors[] = 'Issue title is required.';
    if (strlen($title) < 5)  $errors[] = 'Title must be at least 5 characters.';
    if (empty($description)) $errors[] = 'Please describe the issue in detail.';
    if (strlen($description) < 10) $errors[] = 'Description must be at least 10 characters.';

    // Verify the stall belongs to this vendor
    if ($stall_id && empty($errors)) {
        $chk = $db->prepare("SELECT COUNT(*) FROM rental_records WHERE stall_id=? AND tenant_id=? AND status='active'");
        $chk->execute([$stall_id, $tenant['tenant_id']]);
        if ((int)$chk->fetchColumn() === 0) {
            $errors[] = 'Invalid stall selection.';
        }
    }

    if (empty($errors)) {
        try {
            $db->prepare("
                INSERT INTO maintenance_requests
                    (stall_id, tenant_id, title, description, priority, status, created_by, request_date)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
            ")->execute([
                $stall_id,
                $tenant['tenant_id'],
                $title,
                $description,
                $priority,
                $_SESSION['user_id'],
            ]);

            setMessage('success', 'Maintenance request submitted! Admin has been notified.');
            header('Location: maintenance.php');
            exit();
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-tools"></i> Report an Issue</h1>
        <p class="page-subtitle">Submit a maintenance request for your stall</p>
    </div>
    <a href="mystall.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to My Stall
    </a>
</div>

<?php if (empty($stalls)): ?>
<div class="card" style="text-align:center;padding:48px 24px;max-width:480px;margin:0 auto">
    <i class="fas fa-store-slash fa-2x d-block mb-3" style="color:var(--tx-3);opacity:.4"></i>
    <div style="font-weight:700;color:var(--tx-1);margin-bottom:8px">No Active Stall</div>
    <div style="color:var(--tx-3);margin-bottom:20px">You need an active stall rental to submit a maintenance request.</div>
    <a href="mystall.php" class="btn btn-secondary">Back to My Stall</a>
</div>
<?php else: ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i>
    <strong><?php echo count($errors) === 1 ? htmlspecialchars($errors[0]) : 'Please fix the following:'; ?></strong>
    <?php if (count($errors) > 1): ?>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="fas fa-clipboard-list"></i> Request Details</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">

                        <!-- Stall selection -->
                        <div class="col-md-8">
                            <label class="form-label">Stall <span class="text-danger">*</span></label>
                            <select name="stall_id" class="form-select" required>
                                <option value="">Select your stall…</option>
                                <?php foreach ($stalls as $s): ?>
                                <option value="<?php echo $s['stall_id']; ?>"
                                    <?php echo intval($old['stall_id'] ?? 0) === intval($s['stall_id']) ? 'selected' : ''; ?>>
                                    Stall <?php echo htmlspecialchars($s['stall_number']); ?> — Section <?php echo htmlspecialchars($s['section']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Priority -->
                        <div class="col-md-4">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select" required>
                                <option value="low"    <?php echo ($old['priority'] ?? '') === 'low'    ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($old['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high"   <?php echo ($old['priority'] ?? '') === 'high'   ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo ($old['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>🚨 Urgent</option>
                            </select>
                        </div>

                        <!-- Title -->
                        <div class="col-12">
                            <label class="form-label">Issue Title <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="title"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($old['title'] ?? ''); ?>"
                                   placeholder="e.g. Leaking roof, broken door, electrical issue…"
                                   maxlength="200"
                                   required>
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label">Detailed Description <span class="text-danger">*</span></label>
                            <textarea name="description"
                                      class="form-control"
                                      rows="5"
                                      placeholder="Describe the issue in detail — location, when it started, how severe it is…"
                                      required><?php echo htmlspecialchars($old['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <hr style="border-color:var(--border);margin:22px 0">

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-1"></i> Submit Request
                        </button>
                        <a href="mystall.php" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar info -->
    <div class="col-lg-5">
        <!-- Priority guide -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle"></i> Priority Guide</div>
            <div class="card-body" style="font-size:.83rem">
                <div style="display:flex;gap:10px;margin-bottom:12px;align-items:flex-start">
                    <span style="color:var(--gray);font-weight:700;min-width:56px">LOW</span>
                    <span style="color:var(--tx-3)">Minor cosmetic issues, non-urgent improvements</span>
                </div>
                <div style="display:flex;gap:10px;margin-bottom:12px;align-items:flex-start">
                    <span style="color:var(--blue);font-weight:700;min-width:56px">MEDIUM</span>
                    <span style="color:var(--tx-3)">Issues affecting operations but not an emergency</span>
                </div>
                <div style="display:flex;gap:10px;margin-bottom:12px;align-items:flex-start">
                    <span style="color:var(--amber);font-weight:700;min-width:56px">HIGH</span>
                    <span style="color:var(--tx-3)">Significant damage or safety concern, needs prompt attention</span>
                </div>
                <div style="display:flex;gap:10px;align-items:flex-start">
                    <span style="color:var(--red);font-weight:700;min-width:56px">URGENT</span>
                    <span style="color:var(--tx-3)">Immediate hazard — flooding, fire risk, total failure</span>
                </div>
            </div>
        </div>

        <!-- What happens next -->
        <div class="card">
            <div class="card-header"><i class="fas fa-list-ol"></i> What Happens Next</div>
            <div class="card-body" style="font-size:.83rem;color:var(--tx-2);line-height:1.8">
                <div style="display:flex;gap:10px;margin-bottom:10px">
                    <div style="width:22px;height:22px;border-radius:50%;background:var(--gold);color:#000;font-weight:800;font-size:.72rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">1</div>
                    <div>Your request is submitted as <span class="badge-status badge-pending">Pending</span></div>
                </div>
                <div style="display:flex;gap:10px;margin-bottom:10px">
                    <div style="width:22px;height:22px;border-radius:50%;background:var(--gold);color:#000;font-weight:800;font-size:.72rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">2</div>
                    <div>Admin reviews and assigns it to a staff member</div>
                </div>
                <div style="display:flex;gap:10px">
                    <div style="width:22px;height:22px;border-radius:50%;background:var(--gold);color:#000;font-weight:800;font-size:.72rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">3</div>
                    <div>You can track status in <a href="maintenance.php" style="color:var(--gold)">Maintenance</a></div>
                </div>
                <div style="border-top:1px solid var(--border);margin-top:14px;padding-top:12px;color:var(--tx-3)">
                    <i class="fas fa-phone me-1" style="color:var(--gold)"></i>
                    For emergencies call <strong>(036) 123-4567</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
<?php include 'includes/footer.php'; ?>