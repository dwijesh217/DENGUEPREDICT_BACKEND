<?php
/**
 * Get Today's Patients API
 * Returns patients registered today with their latest prediction
 * Endpoint: GET /get_today_patients.php?user_id=X
 */

require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', [], 405);
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId <= 0) {
    sendResponse(false, 'Valid user_id is required', [], 400);
}

try {
    $conn = getConnection();
    
    $today = date('Y-m-d');
    
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.name,
            p.age,
            p.gender,
            p.blood_group,
            p.created_at,
            (SELECT severity FROM predictions WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as last_severity,
            (SELECT risk_score FROM predictions WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as risk_score
        FROM patients p
        WHERE p.created_by = ? AND DATE(p.created_at) = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$userId, $today]);
    $patients = $stmt->fetchAll();
    
    sendResponse(true, 'Today patients retrieved', [
        'patients' => $patients,
        'count' => count($patients),
        'date' => $today
    ]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get today patients: ' . $e->getMessage(), [], 500);
}
?>
