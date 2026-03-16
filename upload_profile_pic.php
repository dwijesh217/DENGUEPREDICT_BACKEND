<?php
/**
 * Upload Profile Picture API
 * Endpoint: POST /upload_profile_pic.php
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get user_id
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if ($userId <= 0) {
    sendResponse(false, 'Valid user_id is required', [], 400);
}

$uploadDir = 'uploads/profile_pics/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$fileName = '';
$fileUrl = '';

// Handle Base64 (from Camera)
if (isset($_POST['image_data'])) {
    $imageData = $_POST['image_data'];
    // Remove data:image/png;base64, etc.
    if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
        $imageData = substr($imageData, strpos($imageData, ',') + 1);
        $type = strtolower($type[1]); // png, jpg, etc.
        if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
            sendResponse(false, 'Invalid image type from camera', [], 400);
        }
        $imageData = base64_decode($imageData);
        if ($imageData === false) {
            sendResponse(false, 'Base64 decode failed', [], 400);
        }
    } else {
        sendResponse(false, 'Invalid image data format', [], 400);
    }
    
    $fileName = 'user_' . $userId . '_' . time() . '.' . $type;
    $filePath = $uploadDir . $fileName;
    
    if (file_put_contents($filePath, $imageData)) {
        $fileUrl = $uploadDir . $fileName;
    } else {
        sendResponse(false, 'Failed to save camera image', [], 500);
    }
} 
// Handle File Upload (from Gallery)
elseif (isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($ext, $allowed)) {
        sendResponse(false, 'Invalid file type. Allowed: jpg, jpeg, png, gif', [], 400);
    }
    
    $fileName = 'user_' . $userId . '_' . time() . '.' . $ext;
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $fileUrl = $uploadDir . $fileName;
    } else {
        sendResponse(false, 'Failed to move uploaded file', [], 500);
    }
} else {
    sendResponse(false, 'No image data or file provided', [], 400);
}

try {
    $conn = getConnection();
    
    // Optional: Delete old profile pic file if it exists
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldPic = $stmt->fetchColumn();
    if ($oldPic && file_exists($oldPic) && strpos($oldPic, 'uploads/') !== false) {
        unlink($oldPic);
    }
    
    // Update database
    $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
    $stmt->execute([$fileUrl, $userId]);
    
    // Return updated user object
    $stmt = $conn->prepare("SELECT id, name, email, role, hospital, phone, profile_pic, specialization FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    sendResponse(true, 'Profile picture updated successfully', [
        'user' => $user,
        'profile_pic' => $fileUrl
    ]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), [], 500);
}
?>
