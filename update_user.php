<?php
/**
 * Update User Profile API
 * Endpoint: POST /update_user.php
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$hospital = isset($_POST['hospital']) ? trim($_POST['hospital']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
$profilePic = isset($_POST['profile_pic']) ? trim($_POST['profile_pic']) : null;

// Validate required fields
if ($userId <= 0) {
    sendResponse(false, 'Valid user_id is required', [], 400);
}

if (empty($name)) {
    sendResponse(false, 'Name is required', [], 400);
}

try {
    $conn = getConnection();
    
    // Verify user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'User not found', [], 404);
    }
    
    // Build update query based on whether profile_pic is provided
    if ($profilePic !== null) {
        // If deleting/clearing photo
        if (empty($profilePic)) {
            // Optional: delete old file
            $stmtFile = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmtFile->execute([$userId]);
            $oldPic = $stmtFile->fetchColumn();
            if ($oldPic && file_exists($oldPic) && strpos($oldPic, 'uploads/') !== false) {
                unlink($oldPic);
            }
            $stmt = $conn->prepare("UPDATE users SET name = ?, hospital = ?, phone = ?, specialization = ?, profile_pic = NULL WHERE id = ?");
            $stmt->execute([$name, $hospital, $phone, $specialization, $userId]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, hospital = ?, phone = ?, specialization = ?, profile_pic = ? WHERE id = ?");
            $stmt->execute([$name, $hospital, $phone, $specialization, $profilePic, $userId]);
        }
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, hospital = ?, phone = ?, specialization = ? WHERE id = ?");
        $stmt->execute([$name, $hospital, $phone, $specialization, $userId]);
    }
    
    // Get updated user data
    $stmt = $conn->prepare("SELECT id, name, email, role, hospital, phone, profile_pic, specialization FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    sendResponse(true, 'Profile updated successfully', $user);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to update profile: ' . $e->getMessage(), [], 500);
}
?>

