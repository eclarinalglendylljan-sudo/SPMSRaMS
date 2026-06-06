<?php
require_once 'config.php';
requireLogin();

$pageTitle   = 'Upload Profile Picture';
$currentPage = 'profile';
$db = getDB();

/* ── Check that the column exists before doing anything ─────────
   If the ALTER TABLE hasn't been run yet we show a clear message
   instead of a cryptic PDO stack trace.
──────────────────────────────────────────────────────────────── */
$colExists = false;
try {
    $chk = $db->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    $colExists = ($chk->rowCount() > 0);
} catch (Exception $e) { /* fail silently, shown below */ }

if (!$colExists) {
    $pageTitle = 'Setup Required';
    include 'includes/header.php';
    ?>
    <div class="page-header">
        <div><h1 class="page-title"><i class="fas fa-exclamation-triangle" style="color:var(--amber)"></i> Database Setup Required</h1></div>
        <a href="profile.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
    <div class="card" style="max-width:620px">
        <div class="card-header" style="border-color:var(--amber-bd)"><i class="fas fa-database"></i> Missing Column</div>
        <div class="card-body">
            <p style="color:var(--tx-2);margin-bottom:16px">
                The <code style="color:var(--amber)">profile_picture</code> column does not exist in the
                <code style="color:var(--gold)">users</code> table.
                Run the following SQL in <strong>phpMyAdmin</strong> to add it:
            </p>
            <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--r-sm);padding:14px;font-family:monospace;font-size:.88rem;color:var(--green);margin-bottom:20px;user-select:all">
                ALTER TABLE `users`<br>
                ADD COLUMN `profile_picture` VARCHAR(255) DEFAULT NULL AFTER `email`;
            </div>
            <div style="font-size:.82rem;color:var(--tx-3)">
                <strong>How:</strong> Open phpMyAdmin → select your database → click the <em>SQL</em> tab → paste the query above → click <em>Go</em>.
            </div>
            <div class="alert alert-info mt-3 mb-0" style="font-size:.82rem">
                <i class="fas fa-info-circle me-2"></i>
                After running the SQL, refresh this page and the upload form will appear.
            </div>
        </div>
    </div>
    <?php
    include 'includes/footer.php';
    exit();
}

/* ── Process upload ─────────────────────────────────────────── */
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the file.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by a server extension.',
        ];
        $code     = $_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errors[] = $uploadErrors[$code] ?? 'Upload error (code ' . $code . ').';
    } else {
        $file    = $_FILES['profile_picture'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxSize = 5 * 1024 * 1024; // 5 MB

        if (!in_array($ext, $allowed)) {
            $errors[] = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP.';
        }
        if ($file['size'] > $maxSize) {
            $errors[] = 'File too large. Maximum size is 5 MB.';
        }
        if ($file['size'] === 0) {
            $errors[] = 'The uploaded file is empty.';
        }
        if (empty($errors) && @getimagesize($file['tmp_name']) === false) {
            $errors[] = 'The file does not appear to be a valid image.';
        }

        if (empty($errors)) {
            // Ensure upload directory exists and is writable
            $uploadDir = 'uploads/profiles/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    $errors[] = 'Could not create upload directory. Check folder permissions.';
                }
            }
            if (empty($errors) && !is_writable($uploadDir)) {
                $errors[] = 'Upload directory is not writable. Ask your server admin to chmod 755 uploads/profiles/.';
            }

            if (empty($errors)) {
                // Delete old picture
                $old = $db->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
                $old->execute([$_SESSION['user_id']]);
                $oldPic = $old->fetchColumn();
                if ($oldPic && file_exists($oldPic)) {
                    @unlink($oldPic);
                }

                // Save new file
                $newName = 'profile_' . intval($_SESSION['user_id']) . '_' . time() . '.' . $ext;
                $newPath = $uploadDir . $newName;

                if (move_uploaded_file($file['tmp_name'], $newPath)) {
                    $db->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?")
                       ->execute([$newPath, $_SESSION['user_id']]);
                    $_SESSION['profile_picture'] = $newPath;
                    setMessage('success', 'Profile picture updated successfully!');
                    header('Location: profile.php');
                    exit();
                } else {
                    $errors[] = 'Failed to move the uploaded file. Check folder permissions.';
                }
            }
        }
    }
}

// Get current user data for display
$stmt = $db->prepare("SELECT profile_picture, full_name FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user    = $stmt->fetch();
$hasPic  = !empty($user['profile_picture']) && file_exists($user['profile_picture']);
$initial = strtoupper(substr($user['full_name'] ?? 'U', 0, 1));

include 'includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-camera"></i> Upload Profile Picture</h1>
        <p class="page-subtitle">Choose a photo to represent your account</p>
    </div>
    <a href="profile.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Profile
    </a>
</div>

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

<div class="row g-4" style="max-width:780px">

    <!-- Current / Preview -->
    <div class="col-md-4" style="text-align:center">
        <div class="card">
            <div class="card-header"><i class="fas fa-user"></i> Current Photo</div>
            <div class="card-body" style="padding:24px">

                <?php if ($hasPic): ?>
                <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>"
                     id="previewImg"
                     alt="Current photo"
                     style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--gold-bd)">
                <?php else: ?>
                <div id="previewInitial"
                     style="width:100px;height:100px;border-radius:50%;background:var(--gold);display:inline-flex;align-items:center;justify-content:center;font-size:2.4rem;font-weight:800;color:#000;border:3px solid var(--gold-bd)">
                    <?php echo $initial; ?>
                </div>
                <img id="previewImg" src="" alt=""
                     style="display:none;width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--gold-bd)">
                <?php endif; ?>

                <div style="font-size:.78rem;color:var(--tx-3);margin-top:10px" id="previewLabel">
                    <?php echo $hasPic ? 'Your current photo' : 'No photo yet'; ?>
                </div>
            </div>
        </div>

        <?php if ($hasPic): ?>
        <div class="mt-3">
            <a href="remove_profile_picture.php"
               class="btn btn-secondary btn-sm w-100"
               data-confirm="Remove your profile picture?">
                <i class="fas fa-trash me-1"></i> Remove Current Photo
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Upload form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-upload"></i> Upload New Photo</div>
            <div class="card-body">
                <form method="POST"
                      enctype="multipart/form-data"
                      data-no-spinner
                      id="uploadForm">

                    <!-- Drop zone -->
                    <div id="dropzone"
                         style="border:2px dashed var(--border-md);border-radius:var(--r);padding:32px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:16px"
                         onclick="document.getElementById('fileInput').click()"
                         ondragover="event.preventDefault();this.style.borderColor='var(--gold)';this.style.background='var(--gold-dim)'"
                         ondragleave="this.style.borderColor='var(--border-md)';this.style.background=''"
                         ondrop="handleDrop(event)">
                        <i class="fas fa-cloud-upload-alt"
                           style="font-size:2rem;color:var(--tx-3);margin-bottom:8px;display:block"></i>
                        <div style="font-weight:600;color:var(--tx-2);margin-bottom:4px">
                            Click to browse or drag &amp; drop
                        </div>
                        <div style="font-size:.78rem;color:var(--tx-3)">
                            JPG · PNG · GIF · WEBP &nbsp;—&nbsp; max 5 MB
                        </div>
                    </div>

                    <input type="file"
                           id="fileInput"
                           name="profile_picture"
                           accept="image/jpeg,image/png,image/gif,image/webp"
                           style="display:none"
                           onchange="previewFile(this)">

                    <!-- File info row (shown after selection) -->
                    <div id="fileInfo"
                         style="display:none;background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--r-sm);padding:12px 14px;margin-bottom:16px">
                        <div style="display:flex;align-items:center;gap:10px">
                            <i class="fas fa-file-image" style="color:var(--gold)"></i>
                            <div style="flex:1">
                                <div style="font-size:.84rem;font-weight:600;color:var(--tx-1)" id="fileName">—</div>
                                <div style="font-size:.75rem;color:var(--tx-3)" id="fileSize">—</div>
                            </div>
                            <button type="button" onclick="clearFile()"
                                    style="background:none;border:none;color:var(--tx-3);cursor:pointer;font-size:.9rem"
                                    title="Remove selection">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Tips -->
                    <div style="background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--r-sm);padding:12px 14px;margin-bottom:20px;font-size:.8rem;color:var(--tx-3)">
                        <div style="font-weight:600;color:var(--tx-2);margin-bottom:6px">
                            <i class="fas fa-lightbulb me-1" style="color:var(--gold)"></i> Tips
                        </div>
                        <ul style="margin:0;padding-left:16px;line-height:1.9">
                            <li>Square images (e.g. 400 × 400 px) look best</li>
                            <li>Your face centred and well-lit works great</li>
                            <li>Supported: JPG, PNG, GIF, WEBP — max 5 MB</li>
                        </ul>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="uploadBtn">
                            <i class="fas fa-upload me-1"></i> Upload Photo
                        </button>
                        <a href="profile.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var _hasPic = <?php echo $hasPic ? 'true' : 'false'; ?>;
var _oldSrc  = <?php echo $hasPic ? json_encode($user['profile_picture']) : '""'; ?>;

function previewFile(input) {
    var file = input.files[0];
    if (!file) return;

    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
    document.getElementById('fileInfo').style.display = 'block';

    var reader = new FileReader();
    reader.onload = function(e) {
        var img   = document.getElementById('previewImg');
        var init  = document.getElementById('previewInitial');
        img.src   = e.target.result;
        img.style.display = 'block';
        if (init) init.style.display = 'none';
        document.getElementById('previewLabel').textContent = 'Preview of new photo';
    };
    reader.readAsDataURL(file);
}

function clearFile() {
    document.getElementById('fileInput').value = '';
    document.getElementById('fileInfo').style.display = 'none';
    document.getElementById('previewLabel').textContent = _hasPic ? 'Your current photo' : 'No photo yet';
    var img  = document.getElementById('previewImg');
    var init = document.getElementById('previewInitial');
    if (_hasPic) {
        img.src = _oldSrc;
        img.style.display = 'block';
        if (init) init.style.display = 'none';
    } else {
        img.style.display = 'none';
        if (init) init.style.display = 'inline-flex';
    }
}

function handleDrop(e) {
    e.preventDefault();
    var dz = document.getElementById('dropzone');
    dz.style.borderColor = 'var(--border-md)';
    dz.style.background  = '';
    var files = e.dataTransfer.files;
    if (!files.length) return;
    var input = document.getElementById('fileInput');
    try {
        var dt = new DataTransfer();
        dt.items.add(files[0]);
        input.files = dt.files;
        previewFile(input);
    } catch(err) {
        input.click(); // fallback: open file dialog
    }
}
</script>

<?php include 'includes/footer.php'; ?>