<?php
/**
 * Get Alerts API
 * Endpoint: GET /get_alerts.php?patient_id=X or GET /get_alerts.php?all=1&user_id=X
 */

require_once 'db_config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get query params
$patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$showResolved = isset($_GET['show_resolved']) ? ($_GET['show_resolved'] === '1') : false;

try {
    $conn = getConnection();
    
    if ($patientId > 0) {
        // Get alerts for a specific patient
        $sql = "
            SELECT 
                a.id,
                a.patient_id,
                a.prediction_id,
                p.name as patient_name,
                a.alert_level,
                a.message,
                a.is_resolved,
                a.created_at
            FROM alerts a
            JOIN patients p ON a.patient_id = p.id
            WHERE a.patient_id = ?
        ";
        if (!$showResolved) {
            $sql .= " AND a.is_resolved = 0";
        }
        $sql .= " ORDER BY a.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$patientId]);
    } else if ($userId > 0) {
        // Get all alerts for patients created by this user
        $sql = "
            SELECT 
                a.id,
                a.patient_id,
                a.prediction_id,
                p.name as patient_name,
                a.alert_level,
                a.message,
                a.is_resolved,
                a.created_at
            FROM alerts a
            JOIN patients p ON a.patient_id = p.id
            WHERE p.created_by = ?
        ";
        if (!$showResolved) {
            $sql .= " AND a.is_resolved = 0";
        }
        $sql .= " ORDER BY a.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId]);
    } else {
        sendResponse(false, 'Either patient_id or user_id is required', [], 400);
    }
    
    $alerts = $stmt->fetchAll();
    
    sendResponse(true, 'Alerts retrieved successfully', $alerts);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get alerts: ' . $e->getMessage(), [], 500);
}
?>
