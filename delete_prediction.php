<?php
/**
 * Delete Prediction API
 * Endpoint: POST /delete_prediction.php
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$predictionId = isset($_POST['prediction_id']) ? intval($_POST['prediction_id']) : 0;
$predictedBy = isset($_POST['predicted_by']) ? intval($_POST['predicted_by']) : 0;

// Validate required fields
if ($predictionId <= 0) {
    sendResponse(false, 'Valid prediction_id is required', [], 400);
}

if ($predictedBy <= 0) {
    sendResponse(false, 'Valid predicted_by is required', [], 400);
}

try {
    $conn = getConnection();
    
    // Verify prediction exists and belongs to user
    $stmt = $conn->prepare("SELECT id FROM predictions WHERE id = ? AND predicted_by = ?");
    $stmt->execute([$predictionId, $predictedBy]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'Prediction not found or access denied', [], 404);
    }
    
    // Delete prediction (alerts will be deleted via CASCADE)
    $stmt = $conn->prepare("DELETE FROM predictions WHERE id = ?");
    $stmt->execute([$predictionId]);
    
    sendResponse(true, 'Prediction deleted successfully', ['prediction_id' => $predictionId]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to delete prediction: ' . $e->getMessage(), [], 500);
}
?>
