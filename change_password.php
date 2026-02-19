<?php
/**
 * Change Password API
 * Endpoint: POST /change_password.php
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
$newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';

// Validate required fields
$missing = validateRequired(['user_id', 'current_password', 'new_password'], $_POST);
if (!empty($missing)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing), [], 400);
}

// Validate new password length
if (strlen($newPassword) < 6) {
    sendResponse(false, 'New password must be at least 6 characters', [], 400);
}

try {
    $conn = getConnection();
    
    // Get user with current password
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(false, 'User not found', [], 404);
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        sendResponse(false, 'Current password is incorrect', [], 401);
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $userId]);
    
    sendResponse(true, 'Password changed successfully');
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to change password: ' . $e->getMessage(), [], 500);
}
?>
