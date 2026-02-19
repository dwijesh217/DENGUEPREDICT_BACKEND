<?php
/**
 * Get Patients API
 * Endpoint: GET /get_patients.php?created_by=X
 */

require_once 'db_config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get created_by from query params
$createdBy = isset($_GET['created_by']) ? intval($_GET['created_by']) : 0;

if ($createdBy <= 0) {
    sendResponse(false, 'Valid created_by is required', [], 400);
}

try {
    $conn = getConnection();
    
    // Get all patients for this user with prediction data
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.name,
            p.age,
            p.gender,
            p.phone,
            p.address,
            p.blood_group,
            p.created_at,
            (SELECT COUNT(*) FROM predictions WHERE patient_id = p.id) as prediction_count,
            (SELECT severity FROM predictions WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as last_severity,
            (SELECT risk_score FROM predictions WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as risk_score
        FROM patients p
        WHERE p.created_by = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$createdBy]);
    $patients = $stmt->fetchAll();
    
    sendResponse(true, 'Patients retrieved successfully', $patients);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get patients: ' . $e->getMessage(), [], 500);
}
?>
