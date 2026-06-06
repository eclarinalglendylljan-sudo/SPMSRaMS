<?php
require_once 'config.php';
requireLogin();
requirePermission('administrator');

$pageTitle   = 'User Management';
$currentPage = 'users';
$db = getDB();

// Delete user
if (isset($_GET['delete'])) {
    $uid = intval($_GET['delete']);
    if ($uid === $_SESSION['user_id']) {
        setMessage('danger', 'You cannot delete your own account.');
    } else {
        try {
            $db->prepare("DELETE FROM users WHERE user_id=?")->execute([$uid]);
            setMessage('success', 'User deleted.');
        } catch (Exception $e) { setMessage('danger', 'Error: ' . $e->getMessage()); }
    }
    header('Location: users.php'); exit();
}

$search = $_GET['search'] ?? '';
$role   = $_GET['role']   ?? '';
$status = $_GET['status'] ?? '';

$q = "SELECT u.*, t.tenant_id, t.business_name FROM users u LEFT JOIN tenants t ON u.user_id=t.user_id WHERE 1=1";
$p = [];
if ($search) { $q .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)"; $s="%$search%"; $p[]=$s;$p[]=$s;$p[]=$s; }
if ($role)   { $q .= " AND u.role=?";   $p[]=$role; }
if ($status) { $q .= " AND u.status=?"; $p[]=$status; }
$q .= " ORDER BY u.role, u.full_name";
$stmt=$db->prepare($q); $stmt->execute($p); $users=$stmt->fetchAll();

$total = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$admins= (int)$db->query("SELECT COUNT(*) FROM users WHERE role='administrator'")->fetchColumn();
$staff = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
$vends = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='vendor'")->fetchColumn();

include 'includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-users-cog"></i> User Management</h1>
        <p class="page-subtitle">Manage system user accounts</p>
    </div>
    <a href="add_user.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add User</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-value"><?php echo $total;?></div><div class="stat-label">Total Users</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(220,53,69,.4)"><div class="stat-icon" style="color:var(--red)"><i class="fas fa-crown"></i></div><div class="stat-value" style="color:var(--red)"><?php echo $admins;?></div><div class="stat-label">Admins</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(23,162,184,.4)"><div class="stat-icon" style="color:var(--blue)"><i class="fas fa-user-tie"></i></div><div class="stat-value" style="color:var(--blue)"><?php echo $staff;?></div><div class="stat-label">Staff</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="border-color:rgba(255,215,0,.4)"><div class="stat-icon" style="color:var(--gold)"><i class="fas fa-store"></i></div><div class="stat-value" style="color:var(--gold)"><?php echo $vends;?></div><div class="stat-label">Vendors</div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" data-no-spinner>
            <div class="col-md-4"><label class="form-label">Search</label><input type="text" name="search" class="form-control" placeholder="Name, username, email…" value="<?php echo htmlspecialchars($search);?>"></div>
            <div class="col-md-2"><label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    <option value="administrator" <?php echo $role==='administrator'?'selected':'';?>>Administrator</option>
                    <option value="staff"         <?php echo $role==='staff'        ?'selected':'';?>>Staff</option>
                    <option value="vendor"        <?php echo $role==='vendor'       ?'selected':'';?>>Vendor</option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active"   <?php echo $status==='active'  ?'selected':'';?>>Active</option>
                    <option value="inactive" <?php echo $status==='inactive'?'selected':'';?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Search</button></div>
            <div class="col-md-2"><a href="users.php" class="btn btn-secondary w-100"><i class="fas fa-sync me-1"></i>Reset</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-list me-1"></i> Users <span style="font-weight:400;font-size:.82rem;color:var(--txt-mut)">(<?php echo count($users);?> results)</span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>User</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th style="width:100px">Actions</th></tr></thead>
                <tbody>
                <?php if(empty($users)):?>
                <tr><td colspan="7" class="text-center py-5" style="color:var(--txt-mut)"><i class="fas fa-users fa-2x d-block mb-2" style="opacity:.3"></i>No users found.</td></tr>
                <?php else: foreach($users as $u):
                    $roleColor = ['administrator'=>'var(--red)','staff'=>'var(--blue)','vendor'=>'var(--gold)'][$u['role']] ?? 'var(--gray)';
                ?>
                <tr>
                    <td><div style="font-weight:600"><?php echo htmlspecialchars($u['full_name']);?></div>
                        <?php if($u['business_name']):?><div style="font-size:.75rem;color:var(--txt-mut)"><?php echo htmlspecialchars($u['business_name']);?></div><?php endif;?>
                    </td>
                    <td style="font-family:monospace;color:var(--gold)"><?php echo htmlspecialchars($u['username']);?></td>
                    <td style="font-size:.83rem;color:var(--txt-mut)"><?php echo htmlspecialchars($u['email']??'—');?></td>
                    <td><span style="background:rgba(0,0,0,.3);color:<?php echo $roleColor;?>;border:1px solid <?php echo $roleColor;?>33;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase"><?php echo $u['role'];?></span></td>
                    <td><span class="badge-status badge-<?php echo $u['status'];?>"><?php echo ucfirst($u['status']);?></span></td>
                    <td style="font-size:.81rem;color:var(--txt-mut)"><?php echo $u['last_login']?formatDate($u['last_login']):'Never';?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="edit_user.php?id=<?php echo $u['user_id'];?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if($u['user_id'] != $_SESSION['user_id']):?>
                            <a href="users.php?delete=<?php echo $u['user_id'];?>" class="btn btn-sm btn-outline-danger" title="Delete"
                               onclick="return confirm('Delete user <?php echo htmlspecialchars(addslashes($u['username']));?>?\nThis cannot be undone.')"><i class="fas fa-trash"></i></a>
                            <?php endif;?>
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