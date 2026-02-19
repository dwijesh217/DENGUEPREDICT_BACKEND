<?php
/**
 * Check Email API
 * Endpoint: POST /check_email.php
 * 
 * Checks if email is already registered
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// Validate email
if (empty($email)) {
    sendResponse(false, 'Email is required', [], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', [], 400);
}

try {
    $conn = getConnection();
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        sendResponse(false, 'This email is already registered. Please sign in instead.', ['exists' => true], 409);
    }
    
    sendResponse(true, 'Email is available', ['exists' => false], 200);
    
} catch (PDOException $e) {
    sendResponse(false, 'Check failed: ' . $e->getMessage(), [], 500);
}
?>
