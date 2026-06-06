<?php
require_once 'config.php';
requireLogin();

$db = getDB();

// Show confirmation page on GET; process on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $pic = $stmt->fetchColumn();

        if ($pic && file_exists($pic)) {
            @unlink($pic);
        }

        $db->prepare("UPDATE users SET profile_picture = NULL WHERE user_id = ?")
           ->execute([$_SESSION['user_id']]);
        unset($_SESSION['profile_picture']);

        setMessage('success', 'Profile picture removed.');
    } catch (Exception $e) {
        setMessage('danger', 'Error: ' . $e->getMessage());
    }
    header('Location: profile.php');
    exit();
}

// GET — show confirmation page
$stmt = $db->prepare("SELECT profile_picture, full_name FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$hasPic = !empty($user['profile_picture']) && file_exists($user['profile_picture']);

if (!$hasPic) {
    setMessage('info', 'You have no profile picture to remove.');
    header('Location: profile.php');
    exit();
}

$pageTitle   = 'Remove Profile Picture';
$currentPage = 'profile';
include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-trash"></i> Remove Profile Picture</h1>
        <p class="page-subtitle">Confirm you want to remove your photo</p>
    </div>
    <a href="profile.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Cancel
    </a>
</div>

<div class="card" style="max-width:480px">
    <div class="card-body" style="text-align:center;padding:36px 24px">

        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>"
             alt="Current photo"
             style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--border-md);margin-bottom:16px">

        <div style="font-size:1rem;font-weight:700;color:var(--tx-1);margin-bottom:6px">
            Remove your profile picture?
        </div>
        <div style="font-size:.84rem;color:var(--tx-3);margin-bottom:24px">
            Your photo will be deleted permanently and replaced with your initials avatar. This action cannot be undone.
        </div>

        <form method="POST" style="display:flex;gap:10px;justify-content:center">
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash me-1"></i> Yes, Remove Photo
            </button>
            <a href="profile.php" class="btn btn-secondary">
                Keep Photo
            </a>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>