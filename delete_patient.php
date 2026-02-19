<?php
/**
 * Delete Patient API
 * Endpoint: POST /delete_patient.php
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$patientId = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
$createdBy = isset($_POST['created_by']) ? intval($_POST['created_by']) : 0;

// Validate required fields
if ($patientId <= 0) {
    sendResponse(false, 'Valid patient_id is required', [], 400);
}

if ($createdBy <= 0) {
    sendResponse(false, 'Valid created_by is required', [], 400);
}

try {
    $conn = getConnection();
    
    // Verify patient exists and belongs to user
    $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ? AND created_by = ?");
    $stmt->execute([$patientId, $createdBy]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'Patient not found or access denied', [], 404);
    }
    
    // Delete patient (lab_reports, predictions, alerts will be deleted via CASCADE)
    $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    
    sendResponse(true, 'Patient deleted successfully', ['patient_id' => $patientId]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to delete patient: ' . $e->getMessage(), [], 500);
}
?>
