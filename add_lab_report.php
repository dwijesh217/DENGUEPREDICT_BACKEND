<?php
/**
 * Add Lab Report API
 * Endpoint: POST /add_lab_report.php
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$patientId = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
$recordedBy = isset($_POST['recorded_by']) ? intval($_POST['recorded_by']) : 0;
$plateletCount = isset($_POST['platelet_count']) ? floatval($_POST['platelet_count']) : null;
$wbcCount = isset($_POST['wbc_count']) ? floatval($_POST['wbc_count']) : null;
$hemoglobin = isset($_POST['hemoglobin']) ? floatval($_POST['hemoglobin']) : null;
$hematocrit = isset($_POST['hematocrit']) ? floatval($_POST['hematocrit']) : null;
$pdw = isset($_POST['pdw']) ? floatval($_POST['pdw']) : null;
$ast = isset($_POST['ast']) ? floatval($_POST['ast']) : null;
$alt = isset($_POST['alt']) ? floatval($_POST['alt']) : null;
$feverDays = isset($_POST['fever_days']) ? intval($_POST['fever_days']) : null;

// Validate required fields
$missing = validateRequired(['patient_id', 'recorded_by'], $_POST);
if (!empty($missing)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing), [], 400);
}

try {
    $conn = getConnection();
    
    // Verify patient exists
    $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'Patient not found', [], 404);
    }
    
    // Insert lab report
    $stmt = $conn->prepare("
        INSERT INTO lab_reports (patient_id, recorded_by, platelet_count, wbc_count, hemoglobin, hematocrit, pdw, ast, alt, fever_days) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$patientId, $recordedBy, $plateletCount, $wbcCount, $hemoglobin, $hematocrit, $pdw, $ast, $alt, $feverDays]);
    
    $labReportId = $conn->lastInsertId();
    
    sendResponse(true, 'Lab report added successfully', [
        'lab_report_id' => $labReportId,
        'patient_id' => $patientId,
        'platelet_count' => $plateletCount,
        'wbc_count' => $wbcCount
    ], 201);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to add lab report: ' . $e->getMessage(), [], 500);
}
?>
