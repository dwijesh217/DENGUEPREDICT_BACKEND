<?php
/**
 * Get Single Patient API
 * Endpoint: GET /get_patient.php?patient_id=X
 */

require_once 'db_config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get patient_id from query params
$patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patientId <= 0) {
    sendResponse(false, 'Valid patient_id is required', [], 400);
}

try {
    $conn = getConnection();
    
    // Get patient details
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.created_by,
            p.name,
            p.age,
            p.gender,
            p.phone,
            p.address,
            p.blood_group,
            p.created_at,
            p.updated_at,
            (SELECT COUNT(*) FROM predictions WHERE patient_id = p.id) as prediction_count
        FROM patients p
        WHERE p.id = ?
    ");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        error_log("Patient not found for ID: " . $patientId);
        sendResponse(false, 'Patient not found', [], 404);
    }
    
    sendResponse(true, 'Patient retrieved successfully', $patient);
    
} catch (PDOException $e) {
    error_log("Error fetching patient ID " . $patientId . ": " . $e->getMessage());
    sendResponse(false, 'Failed to get patient: ' . $e->getMessage(), [], 500);
}
?>
