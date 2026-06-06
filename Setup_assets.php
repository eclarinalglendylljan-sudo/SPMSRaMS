<?php
/**
 * ============================================================
 *  setup_assets.php  —  Run this ONCE to download all offline assets
 *  Place this file in your project root and open it in a browser
 *  while you still have internet access.
 *  After it finishes you can delete this file.
 * ============================================================
 */

// Only allow this to run from CLI or by an admin who is logged in
// Remove the block below if you need to run it without login
if (php_sapi_name() !== 'cli') {
    // Simple protection: require a secret token in the URL
    // Example: http://localhost/yourproject/setup_assets.php?token=setup2024
    $token = $_GET['token'] ?? '';
    if ($token !== 'setup2024') {
        die('<h2>Access denied.</h2><p>Add <code>?token=setup2024</code> to the URL to run setup.</p>');
    }
}

set_time_limit(300);
echo "<pre style='font-family:monospace; background:#111; color:#FFD700; padding:20px;'>\n";
echo "=== Sibalom MSRMS Offline Asset Setup ===\n\n";

// ── Directory structure to create ──────────────────────────────────────────
$dirs = [
    'assets/css',
    'assets/js',
    'assets/webfonts',
    'assets/images',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "Created directory: $dir\n";
    } else {
        echo "Directory exists:  $dir\n";
    }
}

// ── Files to download ──────────────────────────────────────────────────────
$files = [
    // Bootstrap 5.3.0
    'assets/css/bootstrap.min.css'        => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'assets/js/bootstrap.bundle.min.js'   => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',

    // Font Awesome 6.4.0 — main CSS + individual style sheets
    'assets/css/fontawesome.min.css'      => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'assets/webfonts/fa-solid.css'        => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/solid.min.css',
    'assets/webfonts/fa-brands.css'       => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/brands.min.css',
    'assets/webfonts/fa-regular.css'      => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/regular.min.css',

    // Font Awesome WebFont files (woff2)
    'assets/webfonts/fa-solid-900.woff2'      => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.woff2',
    'assets/webfonts/fa-solid-900.ttf'        => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.ttf',
    'assets/webfonts/fa-brands-400.woff2'     => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-brands-400.woff2',
    'assets/webfonts/fa-brands-400.ttf'       => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-brands-400.ttf',
    'assets/webfonts/fa-regular-400.woff2'    => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-regular-400.woff2',
    'assets/webfonts/fa-regular-400.ttf'      => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-regular-400.ttf',
];

echo "\nDownloading files...\n";
echo str_repeat('-', 60) . "\n";

$success = 0;
$failed  = 0;

foreach ($files as $local => $url) {
    if (file_exists($local) && filesize($local) > 100) {
        echo "SKIP  (exists): $local\n";
        $success++;
        continue;
    }

    echo "GET   $url\n      → $local ... ";
    flush();

    $context = stream_context_create([
        'http' => [
            'timeout'       => 30,
            'follow_location' => true,
            'user_agent'    => 'Mozilla/5.0 SibalomMSRMS-Setup/1.0',
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $data = @file_get_contents($url, false, $context);

    if ($data === false || strlen($data) < 100) {
        echo "FAILED ✗\n";
        $failed++;
    } else {
        file_put_contents($local, $data);
        echo "OK (" . round(strlen($data)/1024, 1) . " KB) ✓\n";
        $success++;
    }
    flush();
}

// ── Fix font paths in downloaded CSS ──────────────────────────────────────
echo "\n" . str_repeat('-', 60) . "\n";
echo "Fixing font paths in CSS...\n";

$css_to_fix = [
    'assets/css/fontawesome.min.css',
    'assets/webfonts/fa-solid.css',
    'assets/webfonts/fa-brands.css',
    'assets/webfonts/fa-regular.css',
];

foreach ($css_to_fix as $css_file) {
    if (!file_exists($css_file)) continue;
    $css = file_get_contents($css_file);

    // CDN path pattern → local relative path
    $css = preg_replace(
        '#url\(["\']?https?://[^/]+/[^/]+/[^/]+/webfonts/([^"\')]+)["\']?\)#',
        "url('../webfonts/$1')",
        $css
    );
    // Also handle relative ../webfonts/ paths from cdnjs
    $css = str_replace('../webfonts/', '../webfonts/', $css);

    file_put_contents($css_file, $css);
    echo "Fixed: $css_file\n";
}

// ── Summary ────────────────────────────────────────────────────────────────
echo "\n" . str_repeat('=', 60) . "\n";
echo "Done!  Success: $success   Failed: $failed\n";

if ($failed > 0) {
    echo "\nSome files failed to download. Possible reasons:\n";
    echo "  - No internet connection on this server\n";
    echo "  - CDN blocked by firewall\n";
    echo "\nManual alternative: Download and place files yourself:\n";
    echo "  Bootstrap CSS  → assets/css/bootstrap.min.css\n";
    echo "  Bootstrap JS   → assets/js/bootstrap.bundle.min.js\n";
    echo "  FontAwesome    → assets/css/fontawesome.min.css\n";
    echo "  FA Webfonts    → assets/webfonts/fa-solid-900.woff2 etc.\n";
    echo "\n  Download from:\n";
    echo "  https://getbootstrap.com/docs/5.3/getting-started/download/\n";
    echo "  https://fontawesome.com/download\n";
} else {
    echo "\nAll assets downloaded. Your system is now fully offline!\n";
    echo "You can delete setup_assets.php.\n";
}
echo "</pre>";
?>