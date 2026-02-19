<?php
/**
 * Get Lab Reports API
 * Endpoint: GET /get_lab_reports.php?patient_id=X
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
    
    // Get all lab reports for this patient
    $stmt = $conn->prepare("
        SELECT 
            lr.id,
            lr.patient_id,
            lr.recorded_by,
            u.name as recorded_by_name,
            lr.platelet_count,
            lr.wbc_count,
            lr.hemoglobin,
            lr.hematocrit,
            lr.pdw,
            lr.ast,
            lr.alt,
            lr.fever_days,
            lr.created_at,
            (SELECT COUNT(*) FROM predictions WHERE lab_report_id = lr.id) as has_prediction
        FROM lab_reports lr
        JOIN users u ON lr.recorded_by = u.id
        WHERE lr.patient_id = ?
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute([$patientId]);
    $labReports = $stmt->fetchAll();
    
    sendResponse(true, 'Lab reports retrieved successfully', $labReports);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get lab reports: ' . $e->getMessage(), [], 500);
}
?>
