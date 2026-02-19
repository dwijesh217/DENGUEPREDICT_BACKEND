<?php
/**
 * Get User Profile API
 * Endpoint: GET /get_profile.php?user_id=X
 * Returns user profile data with activity statistics
 */

require_once 'db_config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get user ID
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId <= 0) {
    sendResponse(false, 'Valid user_id is required', [], 400);
}

try {
    $conn = getConnection();
    
    // Get user data
    $stmt = $conn->prepare("
        SELECT id, name, email, role, hospital, phone, profile_pic, specialization, created_at 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(false, 'User not found', [], 404);
    }
    
    // Get total patients count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM patients WHERE created_by = ?");
    $stmt->execute([$userId]);
    $totalPatients = $stmt->fetch()['count'];
    
    // Get this month's patients count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM patients 
        WHERE created_by = ? 
        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$userId]);
    $thisMonth = $stmt->fetch()['count'];
    
    // Get total predictions count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM predictions WHERE predicted_by = ?");
    $stmt->execute([$userId]);
    $totalPredictions = $stmt->fetch()['count'];
    
    // Get critical cases count (severe severity)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM predictions 
        WHERE predicted_by = ? AND severity = 'severe'
    ");
    $stmt->execute([$userId]);
    $criticalCases = $stmt->fetch()['count'];
    
    // Generate medical license number based on user id and creation year
    $createdYear = date('Y', strtotime($user['created_at']));
    $medicalLicense = "MD-" . $createdYear . "-" . str_pad($user['id'], 6, '0', STR_PAD_LEFT);
    
    // Build response
    $response = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'hospital' => $user['hospital'],
        'phone' => $user['phone'],
        'profile_pic' => $user['profile_pic'],
        'specialization' => $user['specialization'],
        'medical_license' => $medicalLicense,
        'stats' => [
            'total_patients' => (int) $totalPatients,
            'this_month' => (int) $thisMonth,
            'predictions' => (int) $totalPredictions,
            'critical_cases' => (int) $criticalCases
        ]
    ];
    
    sendResponse(true, 'Profile fetched successfully', $response);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to fetch profile: ' . $e->getMessage(), [], 500);
}
?>
