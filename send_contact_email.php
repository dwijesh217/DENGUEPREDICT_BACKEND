<?php
/**
 * Send Contact Email API
 * Endpoint: POST /send_contact_email.php
 * 
 * Sends contact form data to denguepredict1@gmail.com via Gmail SMTP
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
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

error_log("Contact form request received. Name: $name, Email: $email, Subject: $subject");

// Validate fields
if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    error_log("Validation failed: Missing fields.");
    sendResponse(false, 'All fields are required', [], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', [], 400);
}

try {
    $mail = new PHPMailer(true);
    
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;
    
    // Sender & Recipient
    $mail->setFrom(SMTP_USER, "$name ($email)"); // Show user's name and email as sender
    $mail->addAddress('denguepredict1@gmail.com'); // Recipient email address
    $mail->addReplyTo($email, $name); // Reply to the user's email
    
    // SSL certificate verification bypass for local XAMPP issues
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // Email content
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = "Contact Form: $subject";
    
    // HTML Body
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
            <h2 style='color: #1a1a2e; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;'>New Message from Contact Form</h2>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Subject:</strong> $subject</p>
            <div style='background-color: #f8fafc; padding: 15px; border-radius: 8px; margin-top: 20px;'>
                <p><strong>Message:</strong></p>
                <p style='white-space: pre-wrap;'>$message</p>
            </div>
            <p style='font-size: 12px; color: #94a3b8; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 10px;'>
                This message was sent from the DenguePredict Contact Us form.
            </p>
        </div>
    ";
    
    // Plain text fallback
    $mail->AltBody = "New Message from Contact Form\n\nName: $name\nEmail: $email\nSubject: $subject\n\nMessage:\n$message";
    
    if ($mail->send()) {
        error_log("Contact email sent successfully.");
        sendResponse(true, 'Your message has been sent successfully.', [], 200);
    } else {
        error_log("Mail send failed without exception.");
        // Fallback: Log to file if SMTP fails
        logMessageLocally($name, $email, $subject, $message);
        sendResponse(true, 'Your message has been received (logged locally as SMTP is unavailable).', ['logged_locally' => true], 200);
    }
    
} catch (Exception $e) {
    error_log("PHPMailer Exception in send_contact_email.php: " . $mail->ErrorInfo);
    
    // Fallback: Log to file if SMTP fails
    logMessageLocally($name, $email, $subject, $message);
    
    sendResponse(true, 'Your message has been received (logged locally as SMTP is unavailable).', ['logged_locally' => true, 'error' => $mail->ErrorInfo], 200);
}

/**
 * Fallback: Log message to a local file if email fails
 */
function logMessageLocally($name, $email, $subject, $message) {
    $logFile = __DIR__ . '/contact_messages.log';
    $logEntry = "--- " . date('Y-m-d H:i:s') . " ---\n";
    $logEntry .= "From: $name ($email)\n";
    $logEntry .= "Subject: $subject\n";
    $logEntry .= "Message: $message\n";
    $logEntry .= "----------------------------\n\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
?>
