<?php
/**
 * Save Prediction API
 * Endpoint: POST /save_prediction.php
 * 
 * This endpoint saves a prediction linked to a lab report
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$patientId = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
$labReportId = isset($_POST['lab_report_id']) ? intval($_POST['lab_report_id']) : 0;
$predictedBy = isset($_POST['predicted_by']) ? intval($_POST['predicted_by']) : 0;
$severity = isset($_POST['severity']) ? strtolower(trim($_POST['severity'])) : '';
$outcome = isset($_POST['outcome']) ? strtolower(trim($_POST['outcome'])) : '';
$riskScore = isset($_POST['risk_score']) ? floatval($_POST['risk_score']) : 0;
$severityConfidence = isset($_POST['severity_confidence']) ? floatval($_POST['severity_confidence']) : 0;
$outcomeConfidence = isset($_POST['outcome_confidence']) ? floatval($_POST['outcome_confidence']) : 0;
$modelVersion = isset($_POST['model_version']) ? trim($_POST['model_version']) : 'v1.0';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Validate required fields
$missing = validateRequired(['patient_id', 'lab_report_id', 'predicted_by', 'severity', 'outcome'], $_POST);
if (!empty($missing)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing), [], 400);
}

// Validate severity
$validSeverity = ['none', 'mild', 'moderate', 'severe'];
if (!in_array($severity, $validSeverity)) {
    sendResponse(false, 'Invalid severity value. Must be: mild, moderate, or severe', [], 400);
}

// Validate outcome
$validOutcome = ['recovery', 'hospitalization', 'critical'];
if (!in_array($outcome, $validOutcome)) {
    sendResponse(false, 'Invalid outcome value. Must be: recovery, hospitalization, or critical', [], 400);
}

try {
    $conn = getConnection();
    
    // Verify lab report exists
    $stmt = $conn->prepare("SELECT id FROM lab_reports WHERE id = ?");
    $stmt->execute([$labReportId]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'Lab report not found', [], 404);
    }
    
    // Insert prediction
    $stmt = $conn->prepare("
        INSERT INTO predictions (
            patient_id, lab_report_id, predicted_by, severity, outcome,
            risk_score, severity_confidence, outcome_confidence, model_version, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $patientId, $labReportId, $predictedBy, $severity, $outcome,
        $riskScore, $severityConfidence, $outcomeConfidence, $modelVersion, $notes
    ]);
    
    $predictionId = $conn->lastInsertId();
    
    // Create alert if severity is moderate or severe
    if ($severity === 'moderate' || $severity === 'severe' || $outcome === 'critical') {
        $alertLevel = ($severity === 'severe' || $outcome === 'critical') ? 'critical' : 
                      ($severity === 'moderate' ? 'high' : 'medium');
        $alertMessage = "Patient requires attention: Severity - " . ucfirst($severity) . ", Outcome - " . ucfirst($outcome);
        
        $stmt = $conn->prepare("INSERT INTO alerts (patient_id, prediction_id, alert_level, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$patientId, $predictionId, $alertLevel, $alertMessage]);
    }
    
    sendResponse(true, 'Prediction saved successfully', [
        'prediction_id' => $predictionId,
        'severity' => $severity,
        'outcome' => $outcome,
        'risk_score' => $riskScore
    ], 201);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to save prediction: ' . $e->getMessage(), [], 500);
}
?>
