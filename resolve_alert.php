<?php
/**
 * Update Alert (Resolve) API
 * Endpoint: POST /resolve_alert.php
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$alertId = isset($_POST['alert_id']) ? intval($_POST['alert_id']) : 0;
$isResolved = isset($_POST['is_resolved']) ? intval($_POST['is_resolved']) : 1;

// Validate required field
if ($alertId <= 0) {
    sendResponse(false, 'Valid alert_id is required', [], 400);
}

try {
    $conn = getConnection();
    
    // Verify alert exists
    $stmt = $conn->prepare("SELECT id FROM alerts WHERE id = ?");
    $stmt->execute([$alertId]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'Alert not found', [], 404);
    }
    
    // Update alert
    $stmt = $conn->prepare("UPDATE alerts SET is_resolved = ? WHERE id = ?");
    $stmt->execute([$isResolved, $alertId]);
    
    sendResponse(true, 'Alert updated successfully', ['alert_id' => $alertId, 'is_resolved' => $isResolved]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to update alert: ' . $e->getMessage(), [], 500);
}
?>
