<?php
/**
 * Verify OTP API
 * Endpoint: POST /verify_otp.php
 * 
 * Verifies the OTP code entered by user
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$otpCode = isset($_POST['otp_code']) ? trim($_POST['otp_code']) : '';
$purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : 'registration';

// Validate inputs
if (empty($email) || empty($otpCode)) {
    sendResponse(false, 'Email and OTP code are required', [], 400);
}

if (strlen($otpCode) !== 6) {
    sendResponse(false, 'Invalid OTP format', [], 400);
}

try {
    $conn = getConnection();
    
    // Find valid OTP
    $stmt = $conn->prepare("
        SELECT id, otp_code, expires_at 
        FROM otp_codes 
        WHERE email = ? AND purpose = ? AND is_used = 0
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$email, $purpose]);
    $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otpRecord) {
        sendResponse(false, 'No OTP found. Please request a new one.', [], 404);
    }
    
    // Check if OTP is expired
    if (strtotime($otpRecord['expires_at']) < time()) {
        sendResponse(false, 'OTP has expired. Please request a new one.', [], 410);
    }
    
    // Verify OTP
    if ($otpCode !== $otpRecord['otp_code']) {
        sendResponse(false, 'Invalid OTP code', [], 401);
    }
    
    // Mark OTP as used
    $stmt = $conn->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
    $stmt->execute([$otpRecord['id']]);
    
    sendResponse(true, 'OTP verified successfully', ['verified' => true], 200);
    
} catch (PDOException $e) {
    sendResponse(false, 'Verification failed: ' . $e->getMessage(), [], 500);
}
?>
