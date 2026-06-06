<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MSRMS Diagnostic</title>
<style>
body{font-family:monospace;background:#111;color:#eee;padding:30px;font-size:14px}
h2{color:#FFD700;margin-bottom:20px}
.ok{color:#4cd964}.fail{color:#ff6464}.warn{color:#ffc107}
.box{background:#1a1a1a;border:1px solid #333;padding:14px;border-radius:8px;margin-bottom:12px}
</style>
</head>
<body>
<h2>🔍 Sibalom MSRMS — Diagnostic Report</h2>

<div class="box">
<strong style="color:#FFD700">1. Local Asset Files</strong><br><br>
<?php
$files = [
    'assets/css/bootstrap.min.css'    => 'Bootstrap CSS',
    'assets/css/all.min.css'          => 'Font Awesome CSS',
    'assets/js/bootstrap.bundle.min.js'=> 'Bootstrap JS',
    'assets/js/app.js'                => 'app.js ← CRITICAL',
    'assets/images/logo1.png'         => 'Logo image',
    'assets/images/marketbg.png'      => 'Background image',
    'includes/header.php'             => 'header.php',
    'includes/footer.php'             => 'footer.php',
    'config.php'                      => 'config.php',
];
foreach ($files as $path => $label) {
    $exists = file_exists($path);
    $color  = $exists ? 'ok' : 'fail';
    $icon   = $exists ? '✅' : '❌';
    echo "<span class='$color'>$icon $label</span> &nbsp;<small style='color:#666'>($path)</small><br>";
}
?>
</div>

<div class="box">
<strong style="color:#FFD700">2. app.js Contents Check</strong><br><br>
<?php
if (file_exists('assets/js/app.js')) {
    $js = file_get_contents('assets/js/app.js');
    $checks = [
        'window.openDrawer'      => 'openDrawer() defined on window',
        'window.closeDrawer'     => 'closeDrawer() defined on window',
        'window.toggleDrawer'    => 'toggleDrawer() defined on window',
        'window.toggleUserDropdown' => 'toggleUserDropdown() defined on window',
        'drawerClose'            => 'drawerClose button binding present',
        'DOMContentLoaded'       => 'DOMContentLoaded listener present',
        'drawerToggle'           => 'drawerToggle binding present',
    ];
    foreach ($checks as $needle => $label) {
        $found = strpos($js, $needle) !== false;
        $color = $found ? 'ok' : 'fail';
        $icon  = $found ? '✅' : '❌';
        echo "<span class='$color'>$icon $label</span><br>";
    }
} else {
    echo "<span class='fail'>❌ app.js NOT FOUND at assets/js/app.js — this will break everything</span>";
}
?>
</div>

<div class="box">
<strong style="color:#FFD700">3. header.php Inline onclick Check</strong><br><br>
<?php
if (file_exists('includes/header.php')) {
    $h = file_get_contents('includes/header.php');
    $bad = [
        'onclick="toggleDrawer()"'      => 'Drawer toggle has inline onclick (causes double-fire)',
        'onclick="closeDrawer()"'       => 'Drawer close has inline onclick (causes double-fire)',
        'onclick="toggleUserDropdown()"'=> 'Avatar has inline onclick (causes double-fire)',
        'cdn.jsdelivr.net'              => 'CDN link found (freezes offline)',
        'cdnjs.cloudflare.com'          => 'CDN link found (freezes offline)',
        'fonts.googleapis.com'          => 'Google Fonts CDN (freezes offline)',
        '<script>' => 'Inline <script> block in header.php (may conflict)',
    ];
    // app.js should be loaded via <script src=...>
    $appjsLoaded = strpos($h, 'app.js') !== false;
    echo "<span class='" . ($appjsLoaded?'ok':'fail') . "'>" . ($appjsLoaded?'✅':'❌') . " app.js is loaded in header.php</span><br>";

    foreach ($bad as $needle => $label) {
        if ($needle === '<script>') {
            // Having a <script> tag is only bad if it defines toggleDrawer etc
            $hasFn = preg_match('/function\s+(toggleDrawer|openDrawer|closeDrawer)/', $h);
            if ($hasFn) {
                echo "<span class='fail'>❌ header.php defines drawer functions inline — CONFLICT with app.js</span><br>";
            } else {
                echo "<span class='ok'>✅ No drawer functions defined inline in header.php</span><br>";
            }
            continue;
        }
        $found = strpos($h, $needle) !== false;
        $color = $found ? 'fail' : 'ok';
        $icon  = $found ? '❌' : '✅';
        echo "<span class='$color'>$icon $label</span><br>";
    }
} else {
    echo "<span class='fail'>❌ includes/header.php NOT FOUND</span>";
}
?>
</div>

<div class="box">
<strong style="color:#FFD700">4. footer.php Duplicate Functions Check</strong><br><br>
<?php
if (file_exists('includes/footer.php')) {
    $f = file_get_contents('includes/footer.php');
    $hasDupes = preg_match('/function\s+(toggleDrawer|openDrawer|closeDrawer|toggleUserDropdown)/', $f);
    if ($hasDupes) {
        echo "<span class='fail'>❌ footer.php STILL defines drawer functions — this causes the freeze (open then instantly closes)</span><br>";
    } else {
        echo "<span class='ok'>✅ footer.php has no duplicate drawer functions</span><br>";
    }
    $hasCDN = strpos($f,'cdn.') !== false || strpos($f,'cdnjs.') !== false;
    echo "<span class='" . ($hasCDN?'fail':'ok') . "'>" . ($hasCDN?'❌ CDN links in footer (offline freeze)':'✅ No CDN links in footer') . "</span><br>";
} else {
    echo "<span class='fail'>❌ includes/footer.php NOT FOUND</span>";
}
?>
</div>

<div class="box">
<strong style="color:#FFD700">5. receipt.php CDN Check</strong><br><br>
<?php
if (file_exists('receipt.php')) {
    $r = file_get_contents('receipt.php');
    $cdns = ['cdn.jsdelivr.net','cdnjs.cloudflare.com','fonts.googleapis.com','unpkg.com'];
    $found_any = false;
    foreach ($cdns as $cdn) {
        if (strpos($r, $cdn) !== false) {
            echo "<span class='fail'>❌ receipt.php loads from $cdn — THIS FREEZES OFFLINE SYSTEMS</span><br>";
            $found_any = true;
        }
    }
    if (!$found_any) echo "<span class='ok'>✅ receipt.php uses only local assets</span><br>";
} else {
    echo "<span class='warn'>⚠️ receipt.php not found in root</span><br>";
}
?>
</div>

<div class="box">
<strong style="color:#FFD700">6. JavaScript Runtime Test</strong><br>
<small style="color:#666">Tests whether the functions actually work in your browser</small><br><br>
<button onclick="runJSTest()" style="background:#FFD700;border:none;padding:8px 18px;font-weight:700;border-radius:6px;cursor:pointer">▶ Run JS Test</button>
<div id="jsResult" style="margin-top:12px"></div>
</div>

<script>
function runJSTest() {
    var out = document.getElementById('jsResult');
    var results = [];

    var fns = ['openDrawer','closeDrawer','toggleDrawer','toggleUserDropdown','closeUserDropdown'];
    fns.forEach(function(fn) {
        var exists = typeof window[fn] === 'function';
        results.push('<span class="' + (exists?'ok':'fail') + '">' + (exists?'✅':'❌') + ' window.' + fn + '() ' + (exists?'exists':'MISSING') + '</span>');
    });

    // Check elements exist
    var els = ['mainDrawer','drawerOverlay','drawerToggle','drawerToggleIcon','drawerClose'];
    els.forEach(function(id) {
        var el = document.getElementById(id);
        results.push('<span class="warn">⚠️ #' + id + ' ' + (el ? 'found on this page' : 'not on this page (normal for test.php)') + '</span>');
    });

    out.innerHTML = results.join('<br>');
}
</script>

<div class="box" style="border-color:#FFD700">
<strong style="color:#FFD700">7. Quick Fix Summary</strong><br><br>
<strong>If you see red (❌) above, here is what to do:</strong><br><br>
<span style="color:#ffc107">❌ app.js NOT FOUND</span> → Copy app.js to <code>assets/js/app.js</code><br>
<span style="color:#ffc107">❌ drawer functions defined inline in header.php</span> → Replace with latest header.php (no inline JS)<br>
<span style="color:#ffc107">❌ footer.php defines drawer functions</span> → Replace with latest footer.php (3 lines only)<br>
<span style="color:#ffc107">❌ CDN links in receipt.php</span> → Replace with latest receipt.php (local assets only)<br>
<span style="color:#ffc107">❌ inline onclick on buttons</span> → Replace header.php (buttons should have NO onclick attrs)<br>
</div>

</body>
</html>