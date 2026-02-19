<?php
/**
 * Get Single Prediction API
 * Endpoint: GET /get_prediction.php?prediction_id=X
 */

require_once 'db_config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get prediction_id from query params
$predictionId = isset($_GET['prediction_id']) ? intval($_GET['prediction_id']) : 0;

if ($predictionId <= 0) {
    sendResponse(false, 'Valid prediction_id is required', [], 400);
}

try {
    $conn = getConnection();
    
    // Get prediction with patient and lab report details
    $stmt = $conn->prepare("
        SELECT 
            pr.id,
            pr.patient_id,
            pr.lab_report_id,
            pr.predicted_by,
            p.name as patient_name,
            p.age as patient_age,
            p.gender as patient_gender,
            p.blood_group as patient_blood_group,
            lr.platelet_count,
            lr.wbc_count,
            lr.hemoglobin,
            lr.hematocrit,
            lr.pdw,
            lr.ast,
            lr.alt,
            lr.fever_days,
            pr.severity,
            pr.outcome,
            pr.risk_score,
            pr.severity_confidence,
            pr.outcome_confidence,
            pr.model_version,
            pr.notes,
            pr.created_at,
            u.name as predicted_by_name
        FROM predictions pr
        JOIN patients p ON pr.patient_id = p.id
        JOIN lab_reports lr ON pr.lab_report_id = lr.id
        JOIN users u ON pr.predicted_by = u.id
        WHERE pr.id = ?
    ");
    $stmt->execute([$predictionId]);
    $prediction = $stmt->fetch();
    
    if (!$prediction) {
        sendResponse(false, 'Prediction not found', [], 404);
    }
    
    sendResponse(true, 'Prediction retrieved successfully', $prediction);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get prediction: ' . $e->getMessage(), [], 500);
}
?>
