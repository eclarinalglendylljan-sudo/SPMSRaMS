<?php
require_once 'config.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    header('Location: ' . ($role === 'vendor' ? 'mystall.php' : ($role === 'applicant' ? 'applicant-dashboard.php' : 'dashboard.php')));
    exit();
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            $isHash = $user && strlen($user['password']) > 30 && $user['password'][0] === '$';
            $pwOk   = $user && ($isHash ? password_verify($password, $user['password']) : $user['password'] === $password);

            if ($pwOk) {
                $_SESSION['user_id']        = $user['user_id'];
                $_SESSION['username']        = $user['username'];
                $_SESSION['full_name']       = $user['full_name'];
                $_SESSION['role']            = $user['role'];
                $_SESSION['profile_picture'] = $user['profile_picture'] ?? null;

                $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);

                if ($user['role'] === 'vendor')    { header('Location: mystall.php'); exit(); }
                if ($user['role'] === 'applicant') { header('Location: applicant-dashboard.php'); exit(); }
                setMessage('success', 'Welcome back, ' . $user['full_name'] . '!');
                header('Location: dashboard.php'); exit();
            } else {
                $error = 'Incorrect username or password.';
            }
        } catch (Exception $e) {
            $error = 'System error. Please try again.';
        }
    }
}

/* Live stats for the left panel */
try {
    $db2      = getDB();
    $nStalls  = (int)$db2->query("SELECT COUNT(*) FROM market_stalls")->fetchColumn();
    $nVendors = (int)$db2->query("SELECT COUNT(*) FROM tenants WHERE status='active'")->fetchColumn();
    $nAvail   = (int)$db2->query("SELECT COUNT(*) FROM market_stalls WHERE status='available'")->fetchColumn();
} catch (Exception $e) { $nStalls = $nVendors = $nAvail = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Sibalom MSRMS</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/fontawesome-free-6.4.0-web/css/all.min.css" rel="stylesheet">
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --gold:    #F5C842;
        --gold-lt: #FADA6B;
        --green:   #34D399;
        --red:     #F87171;
        --tx1:     #FFFFFF;
        --tx2:     #C8C8D8;
        --tx3:     #7878A0;
        --bd:      rgba(255,255,255,.10);
        --bd-md:   rgba(255,255,255,.16);
    }

    html, body {
        height: 100%;
        font-family: -apple-system, 'Segoe UI', system-ui, sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    body {
        min-height: 100vh;
        background: #07070F;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        overflow: hidden;
    }

    /* Ambient glow blobs */
    body::before {
        content: '';
        position: fixed;
        width: 600px; height: 600px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(245,200,66,.12) 0%, transparent 65%);
        top: -150px; left: -150px;
        pointer-events: none;
    }
    body::after {
        content: '';
        position: fixed;
        width: 500px; height: 500px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(52,211,153,.07) 0%, transparent 65%);
        bottom: -100px; right: -100px;
        pointer-events: none;
    }

    /* ── OUTER WRAPPER ─────────────────────────────────────── */
    .login-wrap {
        width: 100%;
        max-width: 980px;
        min-height: 580px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        border-radius: 20px;
        overflow: hidden;
        position: relative;
        z-index: 1;
        box-shadow: 0 32px 80px rgba(0,0,0,.7), 0 0 0 1px rgba(255,255,255,.06);
    }

    /* ── LEFT PANEL — glassmorphism ────────────────────────── */
    .lp {
        background: rgba(12,12,22,.75);
        backdrop-filter: blur(32px);
        -webkit-backdrop-filter: blur(32px);
        border-right: 1px solid var(--bd);
        padding: 48px 42px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }
    /* Decorative ring */
    .lp::before {
        content: '';
        position: absolute;
        width: 360px; height: 360px;
        border-radius: 50%;
        border: 1px solid rgba(245,200,66,.08);
        top: -80px; right: -120px;
        pointer-events: none;
    }
    .lp::after {
        content: '';
        position: absolute;
        width: 220px; height: 220px;
        border-radius: 50%;
        border: 1px solid rgba(245,200,66,.05);
        bottom: -40px; left: -60px;
        pointer-events: none;
    }

    /* Logo */
    .lp-logo { display: flex; align-items: center; gap: 12px; position: relative; z-index: 1; }
    .lp-logo-mark {
        width: 44px; height: 44px; border-radius: 11px;
        background: var(--gold);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; overflow: hidden;
    }
    .lp-logo-mark img { width: 100%; height: 100%; object-fit: contain; }
    .lp-logo-mark span { font-weight: 900; color: #000; font-size: 1.2rem; }
    .lp-logo-name { font-size: .9rem; font-weight: 700; color: var(--tx1); line-height: 1.3; }
    .lp-logo-sub  { font-size: .62rem; color: var(--tx3); }

    /* Hero */
    .lp-hero { position: relative; z-index: 1; }
    .lp-tag {
        display: inline-flex; align-items: center; gap: 7px;
        background: rgba(245,200,66,.08);
        border: 1px solid rgba(245,200,66,.20);
        color: var(--gold); font-size: .7rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 1.3px;
        padding: 4px 12px; border-radius: 20px;
        margin-bottom: 18px;
    }
    .lp-title {
        font-size: 1.65rem; font-weight: 800;
        color: var(--tx1); line-height: 1.25;
        letter-spacing: -.5px; margin-bottom: 14px;
    }
    .lp-title span { color: var(--gold); }
    .lp-desc { font-size: .87rem; color: var(--tx2); line-height: 1.75; margin-bottom: 28px; }

    /* Stats grid */
    .lp-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .lp-stat {
        background: rgba(255,255,255,.04);
        border: 1px solid var(--bd);
        border-radius: 10px;
        padding: 13px 12px;
    }
    .lp-stat-num { font-size: 1.45rem; font-weight: 800; color: var(--gold); letter-spacing: -1px; }
    .lp-stat-lbl { font-size: .65rem; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; margin-top: 2px; }

    /* Footer */
    .lp-foot { font-size: .72rem; color: var(--tx3); position: relative; z-index: 1; }

    /* ── RIGHT PANEL — solid dark ──────────────────────────── */
    .rp {
        background: #0E0E1A;
        padding: 48px 44px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .rp-head { margin-bottom: 30px; }
    .rp-head h2 { font-size: 1.5rem; font-weight: 800; color: var(--tx1); letter-spacing: -.4px; margin-bottom: 5px; }
    .rp-head p  { font-size: .85rem; color: var(--tx3); }

    /* Error */
    .err-box {
        background: rgba(248,113,113,.08);
        border: 1px solid rgba(248,113,113,.25);
        border-radius: 9px;
        padding: 11px 14px;
        font-size: .84rem; color: var(--red);
        display: flex; align-items: center; gap: 9px;
        margin-bottom: 22px;
        animation: shake .35s ease;
    }
    @keyframes shake {
        0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)}
    }

    /* Fields */
    .field { margin-bottom: 18px; }
    .field label {
        display: block; font-size: .8rem; font-weight: 600;
        color: var(--tx2); margin-bottom: 7px;
    }
    .inp-wrap { position: relative; }
    .inp-icon {
        position: absolute; left: 13px; top: 50%;
        transform: translateY(-50%);
        color: var(--tx3); font-size: .8rem; pointer-events: none;
    }
    .inp {
        width: 100%;
        background: rgba(255,255,255,.04);
        border: 1.5px solid var(--bd);
        border-radius: 9px;
        color: var(--tx1);
        padding: 11px 40px;
        font-size: .9rem; font-family: inherit;
        outline: none;
        transition: border-color .18s, box-shadow .18s, background .18s;
    }
    .inp:focus {
        border-color: var(--gold);
        background: rgba(245,200,66,.04);
        box-shadow: 0 0 0 3px rgba(245,200,66,.12);
    }
    .inp::placeholder { color: var(--tx3); }
    .eye-btn {
        position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
        background: none; border: none; cursor: pointer;
        color: var(--tx3); padding: 3px; transition: color .15s;
    }
    .eye-btn:hover { color: var(--tx1); }

    /* Submit */
    .btn-go {
        width: 100%; background: var(--gold); border: none;
        border-radius: 9px; color: #000;
        font-weight: 700; font-size: .95rem;
        padding: 12px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        transition: background .18s, transform .14s, box-shadow .18s;
        margin-top: 6px;
    }
    .btn-go:hover { background: var(--gold-lt); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(245,200,66,.28); }
    .btn-go:active { transform: scale(.97); }
    .btn-go:disabled { opacity: .55; cursor: not-allowed; pointer-events: none; }

    /* Divider */
    .divider {
        display: flex; align-items: center; gap: 12px;
        margin: 22px 0; color: var(--tx3); font-size: .77rem;
    }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--bd); }

    /* Apply button */
    .btn-apply {
        width: 100%;
        background: transparent;
        border: 1.5px solid rgba(52,211,153,.28);
        border-radius: 9px;
        color: var(--green); font-weight: 600; font-size: .88rem;
        padding: 10px;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        transition: background .18s, border-color .18s, transform .14s;
        font-family: inherit;
    }
    .btn-apply:hover { background: rgba(52,211,153,.07); border-color: rgba(52,211,153,.55); transform: translateY(-1px); }

    /* Dev quick-login */
    .dev {
        margin-top: 22px;
        padding: 12px 14px;
        background: rgba(245,200,66,.03);
        border: 1px dashed rgba(245,200,66,.14);
        border-radius: 9px;
    }
    .dev-lbl {
        font-size: .66rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 1.2px; color: var(--tx3); margin-bottom: 9px;
        display: flex; align-items: center; gap: 5px;
    }
    .dev-btns { display: flex; gap: 7px; flex-wrap: wrap; }
    .dev-btn {
        background: transparent; border: 1px solid rgba(245,200,66,.18);
        border-radius: 6px; color: rgba(245,200,66,.6);
        padding: 5px 12px; font-size: .76rem; cursor: pointer;
        font-family: inherit; transition: all .15s;
    }
    .dev-btn:hover { background: rgba(245,200,66,.08); color: var(--gold); border-color: rgba(245,200,66,.45); }

    .rp-foot { margin-top: 16px; text-align: center; font-size: .73rem; color: var(--tx3); }

    /* Mobile: single column */
    @media (max-width: 680px) {
        .login-wrap { grid-template-columns: 1fr; max-width: 440px; }
        .lp { display: none; }
        .rp { padding: 36px 26px; }
    }
    </style>
</head>
<body>

<div class="login-wrap">

    <!-- ── LEFT: Branding + Stats ───────────────────────────── -->
    <div class="lp">
        <div class="lp-logo">
            <div class="lp-logo-mark">
                <?php if (file_exists('assets/images/logo1.png')): ?>
                    <img src="assets/images/logo1.png" alt="Logo">
                <?php else: ?><span>S</span><?php endif; ?>
            </div>
            <div>
                <div class="lp-logo-name">Sibalom Market Stall</div>
                <div class="lp-logo-sub">Municipality of Sibalom, Antique</div>
            </div>
        </div>

        <div class="lp-hero">
            <div class="lp-tag"><i class="fas fa-map-marked-alt"></i> Market Management System</div>
            <h2 class="lp-title">
                Your market,<br>
                fully managed &<br>
                <span>always organized.</span>
            </h2>
            <p class="lp-desc">
                Manage stall rentals, track payments, handle maintenance,
                and visualize your entire market — all from one platform.
            </p>
            <div class="lp-stats">
                <div class="lp-stat">
                    <div class="lp-stat-num"><?php echo $nStalls; ?></div>
                    <div class="lp-stat-lbl">Total Stalls</div>
                </div>
                <div class="lp-stat">
                    <div class="lp-stat-num"><?php echo $nVendors; ?></div>
                    <div class="lp-stat-lbl">Vendors</div>
                </div>
                <div class="lp-stat">
                    <div class="lp-stat-num"><?php echo $nAvail; ?></div>
                    <div class="lp-stat-lbl">Available</div>
                </div>
            </div>
        </div>

        <div class="lp-foot">&copy; <?php echo date('Y'); ?> Municipality of Sibalom, Antique</div>
    </div>

    <!-- ── RIGHT: Login form ─────────────────────────────────── -->
    <div class="rp">
        <div class="rp-head">
            <h2>Welcome back</h2>
            <p>Sign in to your account to continue</p>
        </div>

        <?php if ($error): ?>
        <div class="err-box"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm" autocomplete="on">
            <div class="field">
                <label for="username"><i class="fas fa-user" style="margin-right:5px;color:var(--gold)"></i>Username</label>
                <div class="inp-wrap">
                    <i class="fas fa-user inp-icon"></i>
                    <input type="text" class="inp" id="username" name="username"
                           value="<?php echo htmlspecialchars($username); ?>"
                           placeholder="Enter your username"
                           autocomplete="username" required autofocus>
                </div>
            </div>

            <div class="field">
                <label for="password"><i class="fas fa-lock" style="margin-right:5px;color:var(--gold)"></i>Password</label>
                <div class="inp-wrap">
                    <i class="fas fa-lock inp-icon"></i>
                    <input type="password" class="inp" id="password" name="password"
                           placeholder="Enter your password"
                           autocomplete="current-password" required>
                    <button type="button" class="eye-btn" id="eyeBtn" tabindex="-1">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-go" id="signinBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="divider">New vendor?</div>

        <button type="button" class="btn-apply" onclick="window.location.href='vendorapplication.php'">
            <i class="fas fa-store"></i> Apply for Stall Rental
        </button>

        <div class="dev">
            <div class="dev-lbl"><i class="fas fa-flask"></i> Quick Login (Testing)</div>
            <div class="dev-btns">
                <button class="dev-btn" onclick="ql('admin','admin123')"><i class="fas fa-user-shield"></i> Admin</button>
                <button class="dev-btn" onclick="ql('staff01','admin123')"><i class="fas fa-user-tie"></i> Staff</button>
                <button class="dev-btn" onclick="ql('vendor01','admin123')"><i class="fas fa-store"></i> Vendor</button>
            </div>
        </div>

        <div class="rp-foot">Having trouble? Contact the market office.</div>
    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('eyeBtn').addEventListener('click', function () {
    var i = document.getElementById('password'), ic = document.getElementById('eyeIcon');
    var s = i.type === 'password';
    i.type = s ? 'text' : 'password';
    ic.className = s ? 'fas fa-eye-slash' : 'fas fa-eye';
});
document.getElementById('loginForm').addEventListener('submit', function () {
    var b = document.getElementById('signinBtn');
    b.disabled = true;
    b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';
});
document.getElementById('username').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('password').focus(); }
});
function ql(u, p) {
    document.getElementById('username').value = u;
    document.getElementById('password').value = p;
    document.getElementById('loginForm').submit();
}
</script>
</body>
</html>