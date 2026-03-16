<?php
/**
 * Get Predictions API
 * Endpoint: GET /get_predictions.php?predicted_by=X or GET /get_predictions.php?patient_id=X
 */

require_once 'db_config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get query params
$predictedBy = isset($_GET['predicted_by']) ? intval($_GET['predicted_by']) : 0;
$patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($predictedBy <= 0 && $patientId <= 0) {
    sendResponse(false, 'Either predicted_by or patient_id is required', [], 400);
}

try {
    $conn = getConnection();
    
    if ($patientId > 0) {
        // Get predictions for a specific patient
        $stmt = $conn->prepare("
            SELECT 
                pr.id,
                pr.patient_id,
                pr.lab_report_id,
                pr.predicted_by,
                p.name as patient_name,
                p.age as patient_age,
                p.gender as patient_gender,
                lr.platelet_count,
                lr.wbc_count,
                lr.hemoglobin,
                lr.hematocrit,
                lr.pdw,
                lr.ast,
                lr.alt,
                lr.fever_days,
                lr.symptoms,
                pr.severity,
                pr.outcome,
                pr.risk_score,
                pr.severity_confidence,
                pr.outcome_confidence,
                pr.model_version,
                pr.notes,
                pr.created_at
            FROM predictions pr
            JOIN patients p ON pr.patient_id = p.id
            JOIN lab_reports lr ON pr.lab_report_id = lr.id
            WHERE pr.patient_id = ?
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([$patientId]);
    } else {
        // Get all predictions by a user
        $stmt = $conn->prepare("
            SELECT 
                pr.id,
                pr.patient_id,
                pr.lab_report_id,
                pr.predicted_by,
                p.name as patient_name,
                p.age as patient_age,
                p.gender as patient_gender,
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
                pr.created_at
            FROM predictions pr
            JOIN patients p ON pr.patient_id = p.id
            JOIN lab_reports lr ON pr.lab_report_id = lr.id
            WHERE pr.predicted_by = ?
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([$predictedBy]);
    }
    
    $predictions = $stmt->fetchAll();
    
    sendResponse(true, 'Predictions retrieved successfully', $predictions);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get predictions: ' . $e->getMessage(), [], 500);
}
?>
