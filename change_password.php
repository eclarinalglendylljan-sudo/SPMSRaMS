<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit();
}

$db = getDB();

$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password']     ?? '';
$confirm = $_POST['confirm_password'] ?? '';

// Basic validation
if (empty($current)) {
    setMessage('danger', 'Current password is required.');
    header('Location: profile.php'); exit();
}
if (strlen($new) < 6) {
    setMessage('danger', 'New password must be at least 6 characters.');
    header('Location: profile.php'); exit();
}
if ($new !== $confirm) {
    setMessage('danger', 'New passwords do not match.');
    header('Location: profile.php'); exit();
}

// Get stored password
$stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stored = $stmt->fetchColumn();

/*
 * COMPATIBILITY FIX:
 * Some accounts have plain-text passwords (e.g. 'admin123' set during install).
 * Others may already be bcrypt hashed (from password_hash()).
 * We check both so nobody gets locked out.
 *
 * After a successful change we always save as a proper bcrypt hash.
 */
$isHash     = (strlen($stored) === 60 && substr($stored, 0, 4) === '$2y$')
           || (strlen($stored) > 20 && substr($stored, 0, 1) === '$');
$verified   = $isHash
    ? password_verify($current, $stored)        // hashed password
    : ($current === $stored);                    // plain-text (legacy)

if (!$verified) {
    setMessage('danger', 'Current password is incorrect.');
    header('Location: profile.php'); exit();
}

// Save new password as proper bcrypt hash
try {
    $hash = password_hash($new, PASSWORD_BCRYPT);
    $db->prepare("UPDATE users SET password = ? WHERE user_id = ?")
       ->execute([$hash, $_SESSION['user_id']]);
    setMessage('success', 'Password changed successfully!');
} catch (Exception $e) {
    setMessage('danger', 'Error changing password: ' . $e->getMessage());
}

header('Location: profile.php');
exit();