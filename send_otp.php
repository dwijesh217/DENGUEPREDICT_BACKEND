<?php
/**
 * Send OTP API
 * Endpoint: POST /send_otp.php
 * 
 * Generates and sends OTP to email for verification via Gmail SMTP
 */

require_once 'db_config.php';
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : 'registration';

// Validate email
if (empty($email)) {
    sendResponse(false, 'Email is required', [], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', [], 400);
}

try {
    $conn = getConnection();
    
    // For forgot password, check if email exists
    if ($purpose === 'forgot_password') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            sendResponse(false, 'Email not registered', [], 404);
        }
    }
    
    // For registration, check if email already exists
    if ($purpose === 'registration') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            sendResponse(false, 'Email already registered', [], 409);
        }
    }
    
    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // OTP expires in 30 minutes
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    // Delete any existing OTP for this email
    $stmt = $conn->prepare("DELETE FROM otp_codes WHERE email = ?");
    $stmt->execute([$email]);
    
    // Insert new OTP
    $stmt = $conn->prepare("INSERT INTO otp_codes (email, otp_code, purpose, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$email, $otp, $purpose, $expiresAt]);
    
    // Send OTP via email
    $emailSent = sendOtpEmail($email, $otp, $purpose);
    
    if ($emailSent) {
        sendResponse(true, 'OTP sent successfully to your email', ['expires_in' => '10 minutes'], 200);
    } else {
        sendResponse(false, 'Failed to send OTP email. Please try again.', [], 500);
    }
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to send OTP: ' . $e->getMessage(), [], 500);
}

/**
 * Send OTP email using PHPMailer with Gmail SMTP
 */
function sendOtpEmail($recipientEmail, $otp, $purpose) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Sender & Recipient
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($recipientEmail);
        
        // Email content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        if ($purpose === 'forgot_password') {
            $mail->Subject = 'Password Reset - DenguePredict';
            $heading = 'Reset Your Password';
            $message = 'You requested to reset your password. Use the verification code below to proceed.';
        } else {
            $mail->Subject = 'Email Verification - DenguePredict';
            $heading = 'Verify Your Email';
            $message = 'Thank you for registering with DenguePredict. Use the verification code below to complete your registration.';
        }
        
        // Professional HTML email template
        $mail->Body = getEmailTemplate($heading, $message, $otp);
        
        // Plain text fallback
        $mail->AltBody = "$heading\n\nYour verification code is: $otp\n\nThis code expires in 30 minutes.\n\n$message\n\n- DenguePredict Team";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Generate professional HTML email template
 */
function getEmailTemplate($heading, $message, $otp) {
    $otpDigits = str_split($otp);
    $otpHtml = '';
    foreach ($otpDigits as $digit) {
        $otpHtml .= '<span style="display:inline-block;width:45px;height:55px;line-height:55px;text-align:center;font-size:28px;font-weight:700;color:#1a1a2e;background:#f0f4ff;border:2px solid #3b82f6;border-radius:10px;margin:0 4px;font-family:\'Courier New\',monospace;">' . $digit . '</span>';
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin:0;padding:0;background-color:#f0f4ff;font-family:\'Segoe UI\',Arial,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4ff;padding:40px 20px;">
            <tr>
                <td align="center">
                    <table width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,0.08);overflow:hidden;">
                        
                        <!-- Header -->
                        <tr>
                            <td style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);padding:32px 40px;text-align:center;">
                                <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;letter-spacing:0.5px;">🏥 DenguePredict</h1>
                                <p style="margin:8px 0 0;color:#94a3b8;font-size:13px;">Dengue Severity Prediction System</p>
                            </td>
                        </tr>
                        
                        <!-- Body -->
                        <tr>
                            <td style="padding:40px;">
                                <h2 style="margin:0 0 12px;color:#1a1a2e;font-size:22px;font-weight:600;">' . $heading . '</h2>
                                <p style="margin:0 0 30px;color:#64748b;font-size:15px;line-height:1.6;">' . $message . '</p>
                                
                                <!-- OTP Code -->
                                <div style="text-align:center;margin:30px 0;">
                                    <p style="margin:0 0 14px;color:#475569;font-size:13px;text-transform:uppercase;letter-spacing:1.5px;font-weight:600;">Verification Code</p>
                                    <div style="display:inline-block;">' . $otpHtml . '</div>
                                </div>
                                
                                <!-- Expiry Notice -->
                                <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:14px 18px;border-radius:0 8px 8px 0;margin:28px 0;">
                                    <p style="margin:0;color:#92400e;font-size:13px;">⏱️ This code expires in <strong>30 minutes</strong>. Do not share this code with anyone.</p>
                                </div>
                                
                                <p style="margin:24px 0 0;color:#94a3b8;font-size:13px;">If you did not request this code, please ignore this email.</p>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0;">
                                <p style="margin:0;color:#94a3b8;font-size:12px;">© ' . date('Y') . ' DenguePredict. All rights reserved.</p>
                                <p style="margin:6px 0 0;color:#cbd5e1;font-size:11px;">This is an automated message. Please do not reply.</p>
                            </td>
                        </tr>
                        
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}
?>
