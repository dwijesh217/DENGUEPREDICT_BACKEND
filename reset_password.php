<?php
/**
 * Reset Password API
 * Endpoint: POST /reset_password.php
 * 
 * Resets user password after OTP verification
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';

// Validate inputs
if (empty($email) || empty($newPassword)) {
    sendResponse(false, 'Email and new password are required', [], 400);
}

if (strlen($newPassword) < 6) {
    sendResponse(false, 'Password must be at least 6 characters', [], 400);
}

try {
    $conn = getConnection();
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        sendResponse(false, 'Email not found', [], 404);
    }
    
    // Update password (plain text for testing)
    // In production: use password_hash($newPassword, PASSWORD_DEFAULT)
    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
    $stmt->execute([$newPassword, $email]);
    
    sendResponse(true, 'Password reset successfully', [], 200);
    
} catch (PDOException $e) {
    sendResponse(false, 'Password reset failed: ' . $e->getMessage(), [], 500);
}
?>
