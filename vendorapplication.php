<?php
require_once 'config.php';

$pageTitle   = 'Apply for Stall Rental';
$db = getDB();

$success_msg  = '';
$error_msg    = '';
$gen_username = '';
$gen_password = '';

/* ── FORM SUBMISSION ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_application') {
    try {
        $db->beginTransaction();

        /* Generate unique username */
        $base = strtolower(preg_replace('/[^a-z]/i', '', $_POST['full_name'] ?? ''));
        $base = $base ?: 'vendor';
        $username = $base . rand(100, 999);
        for ($i = 0; $i < 10; $i++) {
            $chk = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ((int)$chk->fetchColumn() === 0) break;
            $username = $base . rand(100, 999);
        }

        /* Generate plain-text password (system will hash on first change) */
        $password = 'Sibalom' . rand(1000, 9999);

        /* 1. Create user */
        $db->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?,?,?,?,'applicant','pending')")
           ->execute([$username, $password, trim($_POST['full_name']), trim($_POST['email'])]);
        $user_id = $db->lastInsertId();

        /* 2. Create tenant */
        $db->prepare("INSERT INTO tenants (user_id, full_name, email, contact_no, business_name, business_type, tenant_type, status) VALUES (?,?,?,?,?,?,?,'pending')")
           ->execute([$user_id, trim($_POST['full_name']), trim($_POST['email']), trim($_POST['contact_no']), trim($_POST['business_name']), $_POST['business_type'], $_POST['tenant_type']]);
        $tenant_id = $db->lastInsertId();

        /* 3. Create application — NOTE: stall_id (lowercase) not Stall_id */
        $db->prepare("INSERT INTO rental_applications (tenant_id, stall_id, notes) VALUES (?,?,?)")
           ->execute([$tenant_id, intval($_POST['stall_id']), trim($_POST['notes'] ?? '')]);
        $application_id = $db->lastInsertId();

        /* 4. Document uploads */
        $doc_types = ['barangay_clearance', 'business_permit', 'valid_id', 'cedula'];
        foreach ($doc_types as $dt) {
            if (!isset($_FILES[$dt]) || $_FILES[$dt]['error'] !== UPLOAD_ERR_OK) continue;
            if (function_exists('handleFileUpload')) {
                $upload = handleFileUpload($_FILES[$dt], DOCUMENTS_PATH ?? 'uploads/documents/', ['jpg','jpeg','png','pdf']);
                if ($upload['success']) {
                    $db->prepare("INSERT INTO application_documents (application_id, document_type, document_name, file_path) VALUES (?,?,?,?)")
                       ->execute([$application_id, $dt, $_FILES[$dt]['name'], $upload['filename']]);
                }
            }
        }

        $db->commit();
        $gen_username = $username;
        $gen_password = $password;
        $success_msg  = 'APP-' . str_pad($application_id, 6, '0', STR_PAD_LEFT);

    } catch (Exception $e) {
        $db->rollBack();
        $error_msg = $e->getMessage();
    }
}

/* ── Available stalls ────────────────────────────────────── */
$available_stalls = $db->query("SELECT * FROM market_stalls WHERE status='available' ORDER BY section, stall_number")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> — Sibalom MSRMS</title>
    <!-- LOCAL assets only — no CDN -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/fontawesome-free-6.4.0-web/css/all.min.css" rel="stylesheet">
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --gold:    #F5C842;
        --gold-lt: #FADA6B;
        --gold-dim:rgba(245,200,66,.10);
        --gold-bd: rgba(245,200,66,.22);
        --green:   #34D399;
        --red:     #F87171;
        --amber:   #FBBF24;
        --blue:    #60A5FA;
        --tx1:     #FFFFFF;
        --tx2:     #C8C8D8;
        --tx3:     #7878A0;
        --glass:   rgba(14,14,26,.78);
        --glass2:  rgba(20,20,34,.85);
        --bd:      rgba(255,255,255,.09);
        --bd-md:   rgba(255,255,255,.15);
        --inp:     rgba(255,255,255,.05);
        --r: 12px; --r-sm: 8px;
    }

    body {
        background: #07070F;
        font-family: -apple-system, 'Segoe UI', system-ui, sans-serif;
        font-size: 14px;
        color: var(--tx1);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
        position: relative;
    }
    body::before {
        content: '';
        position: fixed; inset: 0;
        background:
            radial-gradient(ellipse 50% 35% at 15% 15%, rgba(245,200,66,.08) 0%, transparent 60%),
            radial-gradient(ellipse 40% 40% at 85% 85%, rgba(52,211,153,.05) 0%, transparent 60%);
        pointer-events: none; z-index: 0;
    }

    /* ── TOPBAR ──────────────────────────────────────────── */
    .topbar {
        position: sticky; top: 0; z-index: 100;
        background: rgba(7,7,15,.92);
        backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--bd);
        padding: 0 24px; height: 60px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .topbar::after {
        content: ''; position: absolute; bottom: 0; left: 5%; right: 5%; height: 1px;
        background: linear-gradient(90deg, transparent, var(--gold-bd), transparent);
    }
    .tb-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .tb-mark {
        width: 32px; height: 32px; border-radius: 8px; background: var(--gold);
        display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;
    }
    .tb-mark img { width: 100%; height: 100%; object-fit: contain; }
    .tb-mark span { font-weight: 900; color: #000; font-size: .95rem; }
    .tb-name { font-size: .86rem; font-weight: 700; color: var(--tx1); }
    .tb-sub  { font-size: .6rem; color: var(--tx3); }
    .btn-login-link {
        background: transparent; border: 1.5px solid var(--gold-bd);
        color: var(--gold); border-radius: var(--r-sm);
        padding: 7px 16px; font-size: .82rem; font-weight: 600;
        text-decoration: none; cursor: pointer;
        display: flex; align-items: center; gap: 6px;
        transition: background .18s, border-color .18s;
    }
    .btn-login-link:hover { background: var(--gold-dim); border-color: var(--gold); }

    /* ── PAGE LAYOUT ──────────────────────────────────────── */
    .page-wrap {
        position: relative; z-index: 1;
        max-width: 1200px; margin: 0 auto;
        padding: 36px 24px 60px;
    }
    .page-hero { text-align: center; margin-bottom: 36px; }
    .page-hero h1 {
        font-size: 1.9rem; font-weight: 800;
        color: var(--tx1); letter-spacing: -.5px; margin-bottom: 8px;
    }
    .page-hero h1 i { color: var(--gold); margin-right: 10px; }
    .page-hero p { color: var(--tx3); font-size: .9rem; }

    /* ── GLASS CARDS ──────────────────────────────────────── */
    .gc {
        background: var(--glass);
        border: 1px solid var(--bd);
        border-radius: var(--r);
        margin-bottom: 20px;
        box-shadow: 0 4px 24px rgba(0,0,0,.4);
    }
    .gc-header {
        background: rgba(0,0,0,.25);
        border-bottom: 1px solid var(--bd);
        padding: 14px 20px;
        border-radius: var(--r) var(--r) 0 0;
        display: flex; align-items: center; gap: 9px;
        font-size: .9rem; font-weight: 700; color: var(--tx1);
    }
    .gc-header i { color: var(--gold); font-size: .85rem; }
    .gc-body { padding: 22px; }

    /* Section label inside form */
    .sec-lbl {
        display: flex; align-items: center; gap: 9px;
        font-size: .85rem; font-weight: 700; color: var(--gold);
        margin-bottom: 18px; margin-top: 8px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--gold-bd);
    }

    /* Form controls */
    .form-label { font-size: .8rem; font-weight: 600; color: var(--tx2); margin-bottom: 5px; }
    .form-text  { font-size: .74rem; color: var(--tx3); margin-top: 4px; }

    .form-control, .form-select {
        background: var(--inp) !important;
        border: 1.5px solid var(--bd-md) !important;
        color: var(--tx1) !important;
        border-radius: var(--r-sm) !important;
        font-family: inherit; font-size: .875rem;
        transition: border-color .18s, box-shadow .18s;
    }
    .form-control:focus, .form-select:focus {
        background: rgba(245,200,66,.04) !important;
        border-color: var(--gold) !important;
        color: var(--tx1) !important;
        box-shadow: 0 0 0 3px rgba(245,200,66,.12) !important;
    }
    .form-control::placeholder { color: var(--tx3) !important; }
    .form-select option { background: #1a1a2e; color: var(--tx1); }

    /* File input */
    .form-control[type="file"] { padding: 9px 12px; }
    .form-control[type="file"]::file-selector-button {
        background: var(--gold-dim); border: 1px solid var(--gold-bd);
        color: var(--gold); border-radius: 5px; padding: 4px 10px;
        font-size: .78rem; font-weight: 600; cursor: pointer;
        margin-right: 10px; transition: background .15s;
    }
    .form-control[type="file"]::file-selector-button:hover { background: rgba(245,200,66,.18); }

    /* Checkbox */
    .form-check-input:checked { background-color: var(--gold); border-color: var(--gold); }
    .form-check-label { color: var(--tx2); font-size: .84rem; line-height: 1.6; }

    /* Divider */
    .form-divider {
        height: 1px; background: var(--bd);
        margin: 24px 0;
    }

    /* Stall info box */
    .stall-info-box {
        background: rgba(96,165,250,.07);
        border: 1px solid rgba(96,165,250,.2);
        border-radius: var(--r-sm);
        padding: 14px 16px;
        margin-top: 10px;
        display: none;
    }
    .stall-info-box.show { display: block; }
    .stall-info-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: .85rem; }
    .stall-info-key { color: var(--tx3); }
    .stall-info-val { color: var(--tx1); font-weight: 600; }

    /* Buttons */
    .btn-submit {
        background: var(--gold); border: none;
        color: #000; font-weight: 700; font-size: .92rem;
        border-radius: var(--r-sm); padding: 12px 28px;
        cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
        transition: background .18s, transform .14s, box-shadow .18s;
        font-family: inherit;
    }
    .btn-submit:hover { background: var(--gold-lt); transform: translateY(-1px); box-shadow: 0 5px 18px rgba(245,200,66,.28); }

    .btn-cancel {
        background: rgba(255,255,255,.07); border: 1.5px solid var(--bd-md);
        color: var(--tx2); font-weight: 600; font-size: .88rem;
        border-radius: var(--r-sm); padding: 11px 22px;
        cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
        text-decoration: none; transition: background .18s, color .18s;
        font-family: inherit;
    }
    .btn-cancel:hover { background: rgba(255,255,255,.12); color: var(--tx1); }

    /* ── SUCCESS SCREEN ───────────────────────────────────── */
    .success-wrap { text-align: center; padding: 20px 0 40px; }
    .success-icon {
        width: 72px; height: 72px; border-radius: 50%;
        background: rgba(52,211,153,.12); border: 2px solid rgba(52,211,153,.3);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px; font-size: 1.8rem; color: var(--green);
    }
    .success-title { font-size: 1.5rem; font-weight: 800; color: var(--tx1); margin-bottom: 8px; }
    .success-ref {
        display: inline-block;
        background: var(--gold-dim); border: 1px solid var(--gold-bd);
        color: var(--gold); font-weight: 700; font-size: 1.05rem;
        font-family: monospace; padding: 6px 18px; border-radius: 20px;
        margin-bottom: 28px; letter-spacing: 1px;
    }

    .creds-card {
        background: var(--glass2);
        border: 1px solid rgba(52,211,153,.25);
        border-radius: var(--r);
        padding: 24px;
        max-width: 480px; margin: 0 auto 24px;
        text-align: left;
    }
    .creds-title {
        font-size: .72rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 1.3px; color: var(--green); margin-bottom: 16px;
        display: flex; align-items: center; gap: 6px;
    }
    .cred-item {
        background: rgba(0,0,0,.3); border: 1px solid var(--bd-md);
        border-left: 3px solid var(--gold);
        border-radius: var(--r-sm); padding: 12px 14px; margin-bottom: 10px;
    }
    .cred-item:last-child { margin-bottom: 0; }
    .cred-label { font-size: .7rem; font-weight: 700; color: var(--gold); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 4px; }
    .cred-value { font-size: 1.15rem; font-weight: 800; color: var(--tx1); font-family: 'Courier New', monospace; letter-spacing: .5px; }

    .warn-box {
        background: rgba(251,191,36,.08); border: 1px solid rgba(251,191,36,.22);
        border-radius: var(--r-sm); padding: 13px 16px;
        font-size: .82rem; color: var(--amber);
        max-width: 480px; margin: 0 auto 24px; text-align: left;
    }
    .warn-box ul { margin: 8px 0 0 16px; color: var(--tx2); }
    .warn-box ul li { margin-bottom: 4px; }

    .success-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .btn-login-now {
        background: var(--gold); border: none; color: #000;
        font-weight: 700; padding: 12px 28px; border-radius: var(--r-sm);
        font-size: .92rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
        font-family: inherit; text-decoration: none;
        transition: background .18s;
    }
    .btn-login-now:hover { background: var(--gold-lt); }
    .btn-print {
        background: transparent; border: 1.5px solid var(--bd-md); color: var(--tx2);
        padding: 11px 22px; border-radius: var(--r-sm); font-size: .88rem;
        cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
        font-family: inherit; transition: background .18s;
    }
    .btn-print:hover { background: rgba(255,255,255,.07); color: var(--tx1); }

    /* ── SIDEBAR ──────────────────────────────────────────── */
    .sidebar-step {
        display: flex; gap: 12px; margin-bottom: 16px; align-items: flex-start;
    }
    .step-num {
        width: 26px; height: 26px; border-radius: 50%;
        background: var(--gold); color: #000;
        font-weight: 800; font-size: .75rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; margin-top: 1px;
    }
    .step-title { font-weight: 700; color: var(--gold); font-size: .85rem; margin-bottom: 2px; }
    .step-desc  { font-size: .79rem; color: var(--tx3); line-height: 1.6; }

    .doc-item {
        display: flex; align-items: center; gap: 9px;
        padding: 8px 0; border-bottom: 1px solid var(--bd); font-size: .85rem;
    }
    .doc-item:last-child { border-bottom: none; }
    .doc-item.optional { color: var(--tx3); }

    .contact-row {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 0; font-size: .85rem; color: var(--tx2);
    }
    .contact-row i { color: var(--gold); width: 16px; text-align: center; }

    /* ── ERROR ALERT ──────────────────────────────────────── */
    .err-alert {
        background: rgba(248,113,113,.08); border: 1px solid rgba(248,113,113,.25);
        border-radius: var(--r-sm); padding: 13px 16px; color: var(--red);
        font-size: .85rem; margin-bottom: 20px;
        display: flex; align-items: flex-start; gap: 9px;
    }

    hr { border-color: var(--bd); opacity: 1; }
    </style>
</head>
<body>

<!-- Topbar -->
<header class="topbar">
    <a class="tb-brand" href="login.php">
        <div class="tb-mark">
            <?php if (file_exists('assets/images/logo1.png')): ?>
                <img src="assets/images/logo1.png" alt="">
            <?php else: ?><span>S</span><?php endif; ?>
        </div>
        <div>
            <div class="tb-name">Sibalom Market Stall</div>
            <div class="tb-sub">Rental &amp; Mapping System</div>
        </div>
    </a>
    <a href="login.php" class="btn-login-link">
        <i class="fas fa-sign-in-alt"></i> Sign In
    </a>
</header>

<div class="page-wrap">

    <!-- Page hero -->
    <div class="page-hero">
        <h1><i class="fas fa-file-signature"></i> Apply for Stall Rental</h1>
        <p>Fill in the form below to apply for a market stall in Sibalom Public Market</p>
    </div>

    <?php if ($error_msg): ?>
    <div class="err-alert">
        <i class="fas fa-exclamation-circle" style="margin-top:1px;flex-shrink:0"></i>
        <div><strong>Submission Error:</strong><br><?php echo htmlspecialchars($error_msg); ?></div>
    </div>
    <?php endif; ?>

    <!-- ── SUCCESS SCREEN ──────────────────────────────────── -->
    <?php if ($success_msg): ?>
    <div class="gc">
        <div class="gc-body">
            <div class="success-wrap">
                <div class="success-icon"><i class="fas fa-check"></i></div>
                <h2 class="success-title">Application Submitted!</h2>
                <p style="color:var(--tx3);margin-bottom:12px">Your reference number is</p>
                <div class="success-ref"><?php echo htmlspecialchars($success_msg); ?></div>

                <div class="creds-card">
                    <div class="creds-title">
                        <i class="fas fa-key"></i> Your Login Credentials
                    </div>
                    <div class="cred-item">
                        <div class="cred-label">Username</div>
                        <div class="cred-value"><?php echo htmlspecialchars($gen_username); ?></div>
                    </div>
                    <div class="cred-item">
                        <div class="cred-label">Password</div>
                        <div class="cred-value"><?php echo htmlspecialchars($gen_password); ?></div>
                    </div>
                </div>

                <div class="warn-box">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Save these credentials now!</strong>
                    <ul>
                        <li>Screenshot or write them down — they will not be shown again</li>
                        <li>Use them to log in and track your application status</li>
                        <li>Full vendor access is granted after admin approval</li>
                        <?php if (!empty($_POST['email'])): ?>
                        <li>We will contact you at <strong><?php echo htmlspecialchars($_POST['email']); ?></strong></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="success-btns">
                    <a href="login.php" class="btn-login-now">
                        <i class="fas fa-sign-in-alt"></i> Login Now
                    </a>
                    <button class="btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Credentials
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ── APPLICATION FORM ────────────────────────────────── -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="gc">
                <div class="gc-header"><i class="fas fa-file-alt"></i> Application Form</div>
                <div class="gc-body">
                    <form method="POST" enctype="multipart/form-data" id="appForm">
                        <input type="hidden" name="action" value="submit_application">

                        <!-- Personal Info -->
                        <div class="sec-lbl"><i class="fas fa-user"></i> Personal Information</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control"
                                       placeholder="Juan Dela Cruz" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control"
                                       placeholder="juan@example.com" required>
                                <div class="form-text">We'll send application updates here</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="text" name="contact_no" class="form-control"
                                       placeholder="09XX-XXX-XXXX" required>
                            </div>
                        </div>

                        <div class="form-divider"></div>

                        <!-- Business Info -->
                        <div class="sec-lbl"><i class="fas fa-briefcase"></i> Business Information</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Business Name <span class="text-danger">*</span></label>
                                <input type="text" name="business_name" class="form-control"
                                       placeholder="Your Business Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Type <span class="text-danger">*</span></label>
                                <select name="business_type" class="form-select" required>
                                    <option value="">Select type…</option>
                                    <option>Food &amp; Beverages</option>
                                    <option>Fresh Produce</option>
                                    <option>Meat &amp; Seafood</option>
                                    <option>Dry Goods</option>
                                    <option>Clothing</option>
                                    <option>Hardware</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Vendor Type <span class="text-danger">*</span></label>
                                <select name="tenant_type" class="form-select" required>
                                    <option value="">Select type…</option>
                                    <option value="permanent">Permanent Vendor — monthly rental</option>
                                    <option value="temporary">Temporary Vendor — daily rental</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-divider"></div>

                        <!-- Stall Selection -->
                        <div class="sec-lbl"><i class="fas fa-store"></i> Stall Selection</div>
                        <?php if (empty($available_stalls)): ?>
                        <div style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.22);border-radius:var(--r-sm);padding:14px;color:var(--amber);font-size:.85rem;margin-bottom:16px">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No stalls are currently available. Please check back later or contact the market office.
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Select Available Stall <span class="text-danger">*</span></label>
                            <select name="stall_id" id="stallSelect" class="form-select" required>
                                <option value="">Choose an available stall…</option>
                                <?php
                                $cur_sec = '';
                                foreach ($available_stalls as $st):
                                    if ($cur_sec !== $st['section']) {
                                        if ($cur_sec !== '') echo '</optgroup>';
                                        echo '<optgroup label="Section ' . htmlspecialchars($st['section']) . '">';
                                        $cur_sec = $st['section'];
                                    }
                                ?>
                                <option value="<?php echo $st['stall_id']; ?>"
                                        data-daily="<?php echo $st['price_per_day']; ?>"
                                        data-monthly="<?php echo $st['price_per_month']; ?>"
                                        data-size="<?php echo $st['size_sqm']; ?>"
                                        data-loc="<?php echo htmlspecialchars($st['location'] ?? ''); ?>">
                                    Stall <?php echo htmlspecialchars($st['stall_number']); ?>
                                    — ₱<?php echo number_format($st['price_per_month'], 0); ?>/mo
                                </option>
                                <?php endforeach; if ($cur_sec !== '') echo '</optgroup>'; ?>
                            </select>

                            <!-- Stall detail preview -->
                            <div class="stall-info-box" id="stallInfoBox">
                                <div class="stall-info-row">
                                    <span class="stall-info-key">Stall</span>
                                    <span class="stall-info-val" id="si-num">—</span>
                                </div>
                                <div class="stall-info-row">
                                    <span class="stall-info-key">Location</span>
                                    <span class="stall-info-val" id="si-loc">—</span>
                                </div>
                                <div class="stall-info-row">
                                    <span class="stall-info-key">Size</span>
                                    <span class="stall-info-val" id="si-size">—</span>
                                </div>
                                <div class="stall-info-row">
                                    <span class="stall-info-key">Daily Rate</span>
                                    <span class="stall-info-val" id="si-daily" style="color:var(--green)">—</span>
                                </div>
                                <div class="stall-info-row" style="border-bottom:none">
                                    <span class="stall-info-key">Monthly Rate</span>
                                    <span class="stall-info-val" id="si-monthly" style="color:var(--green);font-size:1.05rem">—</span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-divider"></div>

                        <!-- Documents -->
                        <div class="sec-lbl"><i class="fas fa-file-upload"></i> Required Documents</div>
                        <p style="color:var(--tx3);font-size:.83rem;margin-bottom:16px">
                            Upload clear copies in PDF, JPG, or PNG format. Max 5MB per file.
                        </p>

                        <?php foreach ([
                            ['barangay_clearance', 'Barangay Clearance', 'Recent issuance required', true],
                            ['business_permit',    'Business Permit',    'Or proof of application', true],
                            ['valid_id',           'Valid Government ID', 'Driver\'s License, Passport, UMID, etc.', true],
                            ['cedula',             'Cedula',             'Optional but recommended', false],
                        ] as [$name, $label, $hint, $required]): ?>
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-file-alt" style="color:var(--gold);margin-right:5px"></i>
                                <?php echo $label; ?>
                                <?php echo $required ? '<span class="text-danger">*</span>' : '<span style="color:var(--tx3)"> (optional)</span>'; ?>
                            </label>
                            <input type="file" name="<?php echo $name; ?>" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   <?php echo $required ? 'required' : ''; ?>>
                            <div class="form-text"><?php echo $hint; ?></div>
                        </div>
                        <?php endforeach; ?>

                        <div class="form-divider"></div>

                        <!-- Notes -->
                        <div class="mb-3">
                            <label class="form-label">Additional Notes <span style="color:var(--tx3)">(optional)</span></label>
                            <textarea name="notes" class="form-control" rows="3"
                                      placeholder="Any other information you'd like to share with the review team…"></textarea>
                        </div>

                        <!-- Terms -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I certify that all information provided is true and correct. I understand that false information may result in rejection.
                                    I agree that my account credentials will be provided upon submission. <span class="text-danger">*</span>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex gap-3 justify-content-end">
                            <a href="login.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── SIDEBAR ──────────────────────────────────────── -->
        <div class="col-lg-4">

            <!-- Steps -->
            <div class="gc mb-4">
                <div class="gc-header"><i class="fas fa-list-ol"></i> What Happens Next?</div>
                <div class="gc-body">
                    <?php foreach ([
                        ['Submit Application',   'Fill this form and upload your documents'],
                        ['Get Login Credentials','An account is created automatically — save your login info'],
                        ['Track Your Status',    'Log in anytime to check your application status'],
                        ['Admin Review',         'Our team reviews within 3–5 business days'],
                        ['Approval &amp; Access','Full vendor access granted on approval'],
                    ] as $i => [$title, $desc]): ?>
                    <div class="sidebar-step">
                        <div class="step-num"><?php echo $i + 1; ?></div>
                        <div>
                            <div class="step-title"><?php echo $title; ?></div>
                            <div class="step-desc"><?php echo $desc; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Documents -->
            <div class="gc mb-4">
                <div class="gc-header"><i class="fas fa-clipboard-check"></i> Document Checklist</div>
                <div class="gc-body" style="padding:16px 20px">
                    <div class="doc-item">
                        <i class="fas fa-check-circle" style="color:var(--green)"></i>
                        <span>Barangay Clearance</span>
                    </div>
                    <div class="doc-item">
                        <i class="fas fa-check-circle" style="color:var(--green)"></i>
                        <span>Business Permit / Application</span>
                    </div>
                    <div class="doc-item">
                        <i class="fas fa-check-circle" style="color:var(--green)"></i>
                        <span>Valid Government ID</span>
                    </div>
                    <div class="doc-item optional">
                        <i class="fas fa-circle" style="color:var(--tx3)"></i>
                        <span>Cedula <span style="color:var(--tx3)">(optional)</span></span>
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div class="gc">
                <div class="gc-header"><i class="fas fa-question-circle"></i> Need Help?</div>
                <div class="gc-body" style="padding:16px 20px">
                    <p style="color:var(--tx3);font-size:.82rem;margin-bottom:12px">For questions or assistance:</p>
                    <div class="contact-row"><i class="fas fa-phone"></i><span><strong style="color:var(--tx1)">(036) 123-4567</strong></span></div>
                    <div class="contact-row"><i class="fas fa-envelope"></i><span>admin@sibalom.gov.ph</span></div>
                    <div class="contact-row"><i class="fas fa-clock"></i><span>Mon–Fri, 8 AM – 5 PM</span></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.page-wrap -->

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
/* Stall detail preview */
var sel = document.getElementById('stallSelect');
if (sel) {
    sel.addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        var box = document.getElementById('stallInfoBox');
        if (!this.value || !box) return;

        var fc = function(n){ return '₱' + parseFloat(n||0).toLocaleString('en-PH',{minimumFractionDigits:2}); };

        document.getElementById('si-num').textContent     = opt.text.split('—')[0].trim();
        document.getElementById('si-loc').textContent     = opt.dataset.loc  || '—';
        document.getElementById('si-size').textContent    = opt.dataset.size ? opt.dataset.size + ' sqm' : '—';
        document.getElementById('si-daily').textContent   = fc(opt.dataset.daily);
        document.getElementById('si-monthly').textContent = fc(opt.dataset.monthly);
        box.classList.add('show');
    });
}

/* Submit loading state */
var form = document.getElementById('appForm');
if (form) {
    form.addEventListener('submit', function () {
        var btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
        }
    });
}
</script>
</body>
</html>