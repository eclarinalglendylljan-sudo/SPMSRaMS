<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit();
}

$db = getDB();
$errors = [];

$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email']     ?? '');

if (empty($full_name)) $errors[] = 'Full name is required.';

if (empty($errors)) {
    try {
        $db->beginTransaction();

        // Update users table
        $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?")
           ->execute([$full_name, $email, $_SESSION['user_id']]);

        // Update session
        $_SESSION['full_name'] = $full_name;

        // If vendor, also update tenant record
        if (isVendor()) {
            $contact_no    = trim($_POST['contact_no']    ?? '');
            $business_name = trim($_POST['business_name'] ?? '');
            $address       = trim($_POST['address']       ?? '');

            $stmt = $db->prepare("SELECT tenant_id FROM tenants WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $tid = $stmt->fetchColumn();

            if ($tid) {
                $db->prepare("UPDATE tenants SET full_name = ?, email = ?, contact_no = ?, business_name = ?, address = ? WHERE tenant_id = ?")
                   ->execute([$full_name, $email, $contact_no, $business_name, $address, $tid]);
            }
        }

        $db->commit();
        setMessage('success', 'Profile updated successfully!');
    } catch (Exception $e) {
        $db->rollBack();
        setMessage('danger', 'Error updating profile: ' . $e->getMessage());
    }
} else {
    setMessage('danger', implode(' ', $errors));
}

header('Location: profile.php');
exit();