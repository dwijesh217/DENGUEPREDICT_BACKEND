<?php
/**
 * Login API
 * Endpoint: POST /login.php
 * 
 * NOTE: Using plain text password comparison for testing.
 * In production, use password_hash and password_verify.
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validate required fields
$missing = validateRequired(['email', 'password'], $_POST);
if (!empty($missing)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing), [], 400);
}

try {
    $conn = getConnection();
    
    // Get user by email
    $stmt = $conn->prepare("SELECT id, name, email, password, role, hospital, phone FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(false, 'Invalid email or password', [], 401);
    }
    
    // Plain text password comparison (for testing only)
    // In production, replace with: if (!password_verify($password, $user['password']))
    if ($password !== $user['password']) {
        sendResponse(false, 'Invalid email or password', [], 401);
    }
    
    // Remove password from response
    unset($user['password']);
    
    sendResponse(true, 'Login successful', $user);
    
} catch (PDOException $e) {
    sendResponse(false, 'Login failed: ' . $e->getMessage(), [], 500);
}
?>
