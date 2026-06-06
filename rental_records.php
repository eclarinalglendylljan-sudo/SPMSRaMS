<?php
require_once 'config.php';
requireLogin();
requirePermission('staff');

$pageTitle = 'Rental Records';
$currentPage = 'rentals';
$db = getDB();

// ── CREATE ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'add_rental') {
    try {
        $db->beginTransaction();
        $s = $db->prepare("SELECT status, stall_number FROM market_stalls WHERE stall_id=?");
        $s->execute([$_POST['stall_id']]);
        $stall = $s->fetch();
        if (!$stall) throw new Exception("Stall not found.");
        if ($stall['status'] !== 'available') throw new Exception("Stall {$stall['stall_number']} is not available (current: {$stall['status']}).");
        $db->prepare("INSERT INTO rental_records (stall_id, tenant_id, start_date, monthly_rate) VALUES (?,?,?,?)")
           ->execute([$_POST['stall_id'], $_POST['tenant_id'], $_POST['start_date'], $_POST['monthly_rate']]);
        $db->prepare("UPDATE tenants SET status='active' WHERE tenant_id=?")->execute([$_POST['tenant_id']]);
        $db->commit();
        setMessage('success', 'Rental record created successfully!');
    } catch (Exception $e) { $db->rollBack(); setMessage('danger', 'Error: ' . $e->getMessage()); }
    header('Location: rental_records.php'); exit();
}

// ── UPDATE ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'update_rental') {
    try {
        $db->prepare("UPDATE rental_records SET monthly_rate=?, end_date=?, status=? WHERE record_id=?")
           ->execute([$_POST['monthly_rate'], $_POST['end_date']?:null, $_POST['status'], $_POST['record_id']]);
        setMessage('success', 'Rental record updated!');
    } catch (Exception $e) { setMessage('danger', 'Error: ' . $e->getMessage()); }
    header('Location: rental_records.php'); exit();
}

// ── TERMINATE ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'terminate') {
    try {
        $db->beginTransaction();
        $db->prepare("UPDATE rental_records SET status='terminated', end_date=? WHERE record_id=?")
           ->execute([date('Y-m-d'), $_POST['record_id']]);
        // Check if vendor has any other active rentals
        $row = $db->prepare("SELECT tenant_id FROM rental_records WHERE record_id=?");
        $row->execute([$_POST['record_id']]);
        $tid = $row->fetchColumn();
        $active = $db->prepare("SELECT COUNT(*) FROM rental_records WHERE tenant_id=? AND status='active'");
        $active->execute([$tid]);
        if ((int)$active->fetchColumn() === 0) {
            $db->prepare("UPDATE tenants SET status='inactive' WHERE tenant_id=?")->execute([$tid]);
        }
        $db->commit();
        setMessage('success', 'Rental terminated. Stall is now available.');
    } catch (Exception $e) { $db->rollBack(); setMessage('danger', 'Error: ' . $e->getMessage()); }
    header('Location: rental_records.php'); exit();
}

// ── DELETE ─────────────────────────────────────────────────────────────
if (isset($_GET['delete']) && isAdmin()) {
    try {
        $db->prepare("DELETE FROM rental_records WHERE record_id=?")->execute([$_GET['delete']]);
        setMessage('success', 'Record deleted.');
    } catch (Exception $e) { setMessage('danger', 'Error: ' . $e->getMessage()); }
    header('Location: rental_records.php'); exit();
}

// ── READ / FILTER ───────────────────────────────────────────────────────
$status_filter = $_GET['status'] ?? 'active';
$search        = $_GET['search'] ?? '';

$q = "SELECT rr.*, ms.stall_number, ms.section,
             t.full_name as tenant_name, t.business_name, t.tenant_type,
             DATEDIFF(CURDATE(), rr.start_date) as days_rented
      FROM rental_records rr
      JOIN market_stalls ms ON rr.stall_id  = ms.stall_id
      JOIN tenants t         ON rr.tenant_id = t.tenant_id
      WHERE 1=1";
$params = [];
if ($status_filter) { $q .= " AND rr.status=?"; $params[] = $status_filter; }
if ($search) {
    $q .= " AND (t.full_name LIKE ? OR t.business_name LIKE ? OR ms.stall_number LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$q .= " ORDER BY rr.start_date DESC";
$stmt = $db->prepare($q); $stmt->execute($params);
$records = $stmt->fetchAll();

// Dropdowns
$available_stalls  = $db->query("SELECT stall_id, stall_number, section, price_per_month FROM market_stalls WHERE status='available' ORDER BY section, stall_number")->fetchAll();
$all_tenants       = $db->query("SELECT tenant_id, full_name, business_name FROM tenants WHERE status IN ('active','pending') ORDER BY full_name")->fetchAll();

// Stats
$cnt = $db->query("SELECT status, COUNT(*) as c FROM rental_records GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$monthly = $db->query("SELECT COALESCE(SUM(monthly_rate),0) FROM rental_records WHERE status='active'")->fetchColumn();

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-file-contract"></i> Rental Records</h1>
        <div class="page-subtitle">Manage all active, expired and terminated rental agreements</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRentalModal">
        <i class="fas fa-plus me-1"></i> New Rental
    </button>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card" style="border-color:rgba(40,167,69,0.4);">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Active</div>
                    <div class="stat-value"><?php echo $cnt['active'] ?? 0; ?></div>
                    <div class="stat-sub">Current rentals</div>
                </div>
                <div class="stat-icon" style="color:#28a745;font-size:1.8rem;"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card" style="border-color:rgba(255,193,7,0.4);">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Expired</div>
                    <div class="stat-value"><?php echo $cnt['expired'] ?? 0; ?></div>
                    <div class="stat-sub">Need renewal</div>
                </div>
                <div class="stat-icon" style="color:#ffc107;font-size:1.8rem;"><i class="fas fa-calendar-times"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card" style="border-color:rgba(220,53,69,0.4);">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Terminated</div>
                    <div class="stat-value"><?php echo $cnt['terminated'] ?? 0; ?></div>
                    <div class="stat-sub">Ended rentals</div>
                </div>
                <div class="stat-icon" style="color:#dc3545;font-size:1.8rem;"><i class="fas fa-ban"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Monthly Income</div>
                    <div class="stat-value" style="font-size:1.3rem;"><?php echo formatCurrency($monthly); ?></div>
                    <div class="stat-sub">From active rentals</div>
                </div>
                <div class="stat-icon" style="font-size:1.8rem;"><i class="fas fa-coins"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Vendor name, business or stall number..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active"     <?php echo $status_filter=='active'    ?'selected':''; ?>>Active</option>
                    <option value="expired"    <?php echo $status_filter=='expired'   ?'selected':''; ?>>Expired</option>
                    <option value="terminated" <?php echo $status_filter=='terminated'?'selected':''; ?>>Terminated</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="rental_records.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Records Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list"></i> Records (<?php echo count($records); ?> found)</span>
        <a href="rental_records.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync me-1"></i>Refresh</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Stall</th>
                        <th>Vendor</th>
                        <th>Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Monthly Rate</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th width="110">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="10" class="text-center py-5 text-muted">
                        <i class="fas fa-file-contract fa-2x d-block mb-2" style="color:rgba(255,215,0,0.2);"></i>
                        No rental records found
                    </td></tr>
                <?php else: foreach ($records as $r):
                    $bc = ['active'=>'active','expired'=>'expired','terminated'=>'terminated'][$r['status']] ?? 'inactive';
                    $days = (int)$r['days_rented'];
                    $dur  = $days >= 365 ? round($days/365,1).'y' : ($days >= 30 ? floor($days/30).'mo' : $days.'d');
                ?>
                    <tr>
                        <td class="text-muted" style="font-size:0.78rem;">#<?php echo $r['record_id']; ?></td>
                        <td>
                            <div style="font-weight:600;font-size:0.88rem;">
                                <span style="background:rgba(255,215,0,0.1);border:1px solid rgba(255,215,0,0.2);padding:2px 8px;border-radius:5px;font-size:0.8rem;">
                                    <?php echo htmlspecialchars($r['stall_number']); ?>
                                </span>
                            </div>
                            <div class="text-muted" style="font-size:0.76rem;margin-top:2px;">Section <?php echo $r['section']; ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600;font-size:0.88rem;"><?php echo htmlspecialchars($r['tenant_name']); ?></div>
                            <div class="text-muted" style="font-size:0.76rem;"><?php echo htmlspecialchars($r['business_name']); ?></div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $r['tenant_type']=='permanent'?'primary':'info'; ?>" style="font-size:0.72rem;">
                                <?php echo ucfirst($r['tenant_type']); ?>
                            </span>
                        </td>
                        <td style="font-size:0.83rem;"><?php echo formatDate($r['start_date']); ?></td>
                        <td style="font-size:0.83rem;">
                            <?php if ($r['end_date']): ?>
                                <?php echo formatDate($r['end_date']); ?>
                            <?php else: ?>
                                <span style="color:#4cd964;font-size:0.78rem;">Ongoing</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--gold);font-weight:600;font-size:0.88rem;"><?php echo formatCurrency($r['monthly_rate']); ?></td>
                        <td class="text-muted" style="font-size:0.8rem;"><?php echo $dur; ?></td>
                        <td><span class="badge-status badge-<?php echo $bc; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-warning btn-edit-rental"
                                    data-json="<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES); ?>"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($r['status'] === 'active'): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-terminate"
                                    data-id="<?php echo $r['record_id']; ?>"
                                    data-stall="<?php echo htmlspecialchars($r['stall_number'], ENT_QUOTES); ?>"
                                    title="Terminate">
                                    <i class="fas fa-stop-circle"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (isAdmin()): ?>
                                <a href="?delete=<?php echo $r['record_id']; ?>" class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Permanently delete this record?')"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Add Rental Modal ────────────────────────────────────────────── -->
<div class="modal fade" id="addRentalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>New Rental Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_rental">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Available Stall *</label>
                        <select name="stall_id" class="form-select" id="stallSelect" required onchange="fillRate(this)">
                            <option value="">Select an available stall...</option>
                            <?php foreach ($available_stalls as $st): ?>
                            <option value="<?php echo $st['stall_id']; ?>"
                                    data-rate="<?php echo $st['price_per_month']; ?>">
                                <?php echo htmlspecialchars($st['stall_number']); ?> — Section <?php echo $st['section']; ?> (<?php echo formatCurrency($st['price_per_month']); ?>/mo)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vendor *</label>
                        <select name="tenant_id" class="form-select" required>
                            <option value="">Select vendor...</option>
                            <?php foreach ($all_tenants as $t): ?>
                            <option value="<?php echo $t['tenant_id']; ?>">
                                <?php echo htmlspecialchars($t['full_name']); ?> — <?php echo htmlspecialchars($t['business_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Monthly Rate (₱) *</label>
                            <input type="number" name="monthly_rate" id="rateInput" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Create Rental</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Edit Rental Modal ───────────────────────────────────────────── -->
<div class="modal fade" id="editRentalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Rental Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_rental">
                <input type="hidden" name="record_id" id="er_record_id">
                <div class="modal-body">
                    <div class="mb-3 p-3 rounded" style="background:rgba(255,215,0,0.06);border:1px solid rgba(255,215,0,0.15);font-size:0.85rem;">
                        <div><span class="text-muted">Stall:</span> <strong id="er_stall" style="color:var(--gold);"></strong></div>
                        <div><span class="text-muted">Vendor:</span> <strong id="er_vendor"></strong></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Monthly Rate (₱) *</label>
                            <input type="number" name="monthly_rate" id="er_rate" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" id="er_end_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" id="er_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Terminate hidden form -->
<form id="terminateForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="terminate">
    <input type="hidden" name="record_id" id="term_id">
</form>

<script>
// Auto-fill rate from stall selection
function fillRate(sel) {
    var opt = sel.options[sel.selectedIndex];
    var rate = opt.getAttribute('data-rate');
    if (rate) document.getElementById('rateInput').value = rate;
}

// Edit rental
document.querySelectorAll('.btn-edit-rental').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var r = JSON.parse(this.getAttribute('data-json'));
        document.getElementById('er_record_id').value = r.record_id;
        document.getElementById('er_stall').textContent  = r.stall_number + ' (Sec ' + r.section + ')';
        document.getElementById('er_vendor').textContent = r.tenant_name + ' — ' + r.business_name;
        document.getElementById('er_rate').value         = r.monthly_rate;
        document.getElementById('er_end_date').value     = r.end_date || '';
        document.getElementById('er_status').value       = r.status;
        new bootstrap.Modal(document.getElementById('editRentalModal')).show();
    });
});

// Terminate rental
document.querySelectorAll('.btn-terminate').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id    = this.getAttribute('data-id');
        var stall = this.getAttribute('data-stall');
        if (confirm('Terminate rental for stall ' + stall + '?\n\nThis will:\n• Set end date to today\n• Mark the stall as available\n• Deactivate vendor if no other active rentals')) {
            document.getElementById('term_id').value = id;
            document.getElementById('terminateForm').submit();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>