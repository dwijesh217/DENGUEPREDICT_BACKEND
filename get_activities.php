<?php
/**
 * Get Recent Activities for Dashboard
 * Returns patient registrations, predictions, critical cases, etc.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'db_config.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get user_id from query params
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

try {
    $pdo = getConnection();
    $activities = [];
    
    // Get recent patient registrations
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.created_at,
            'patient_registered' as type
        FROM patients p
        WHERE p.created_by = ?
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($patients as $patient) {
        $activities[] = [
            'id' => (int)$patient['id'],
            'type' => 'patient_registered',
            'title' => 'New patient registered',
            'description' => $patient['name'] . ' added to system',
            'timestamp' => $patient['created_at'],
            'patient_id' => (int)$patient['id'],
            'patient_name' => $patient['name'],
            'severity' => null,
            'is_critical' => false
        ];
    }
    
    // Get recent predictions
    $stmt = $pdo->prepare("
        SELECT 
            pr.id,
            pr.severity as severity_prediction,
            pr.risk_score,
            pr.created_at,
            p.id as patient_id,
            p.name as patient_name
        FROM predictions pr
        JOIN patients p ON pr.patient_id = p.id
        WHERE pr.predicted_by = ?
        ORDER BY pr.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $predictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($predictions as $pred) {
        $isCritical = strtolower($pred['severity_prediction']) === 'severe' || $pred['risk_score'] >= 70;
        $type = $isCritical ? 'critical_case' : 'prediction_completed';
        
        if ($isCritical) {
            $title = 'Critical case detected';
            $description = $pred['patient_name'] . ' flagged as severe dengue - Immediate attention required';
        } else {
            $title = 'AI prediction completed';
            $description = 'Risk assessment for ' . $pred['patient_name'] . ' - ' . ucfirst($pred['severity_prediction']) . ' severity';
        }
        
        $activities[] = [
            'id' => (int)$pred['id'],
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'timestamp' => $pred['created_at'],
            'patient_id' => (int)$pred['patient_id'],
            'patient_name' => $pred['patient_name'],
            'severity' => $pred['severity_prediction'],
            'is_critical' => $isCritical
        ];
    }
    
    // Sort all activities by timestamp (newest first)
    usort($activities, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Limit to requested number
    $activities = array_slice($activities, 0, $limit);
    
    echo json_encode([
        'success' => true,
        'message' => 'Activities retrieved successfully',
        'data' => $activities
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
