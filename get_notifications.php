<?php
/**
 * Get Notifications API
 * Generates notifications from predictions, patient registrations, and alerts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

try {
    $pdo = getConnection();
    $notifications = [];
    
    // 1. Critical Patient Alerts - Severe predictions with low platelet/high risk
    $stmt = $pdo->prepare("
        SELECT 
            pr.id,
            pr.severity,
            pr.outcome,
            pr.risk_score,
            pr.created_at,
            p.name as patient_name,
            p.id as patient_id,
            lr.platelet_count
        FROM predictions pr
        JOIN patients p ON pr.patient_id = p.id
        LEFT JOIN lab_reports lr ON pr.lab_report_id = lr.id
        WHERE pr.predicted_by = ?
        AND (pr.severity = 'severe' OR pr.outcome = 'critical')
        ORDER BY pr.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $criticals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($criticals as $c) {
        $platelets = $c['platelet_count'] ? number_format($c['platelet_count']) : 'N/A';
        $notifications[] = [
            'id' => 'critical_' . $c['id'],
            'type' => 'critical_alert',
            'title' => 'Critical Patient Alert',
            'description' => 'Patient ' . $c['patient_name'] . ' - Platelet count dropped to ' . $platelets . '. Immediate attention required.',
            'timestamp' => $c['created_at'],
            'patient_id' => (int)$c['patient_id'],
            'patient_name' => $c['patient_name'],
            'severity' => $c['severity'],
            'risk_score' => (float)$c['risk_score']
        ];
    }
    
    // 2. High Risk Predictions - moderate severity or high risk score
    $stmt = $pdo->prepare("
        SELECT 
            pr.id,
            pr.severity,
            pr.risk_score,
            pr.created_at,
            p.name as patient_name,
            p.id as patient_id
        FROM predictions pr
        JOIN patients p ON pr.patient_id = p.id
        WHERE pr.predicted_by = ?
        AND pr.severity = 'moderate'
        AND pr.risk_score >= 50
        ORDER BY pr.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $highRisks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($highRisks as $hr) {
        $notifications[] = [
            'id' => 'highrisk_' . $hr['id'],
            'type' => 'high_risk',
            'title' => 'High Risk Prediction',
            'description' => 'New patient ' . $hr['patient_name'] . ' predicted as ' . $hr['severity'] . ' dengue (Risk: ' . round($hr['risk_score']) . ')',
            'timestamp' => $hr['created_at'],
            'patient_id' => (int)$hr['patient_id'],
            'patient_name' => $hr['patient_name'],
            'severity' => $hr['severity'],
            'risk_score' => (float)$hr['risk_score']
        ];
    }
    
    // 3. Patient Recovery - predictions with recovery outcome
    $stmt = $pdo->prepare("
        SELECT 
            pr.id,
            pr.outcome,
            pr.created_at,
            p.name as patient_name,
            p.id as patient_id
        FROM predictions pr
        JOIN patients p ON pr.patient_id = p.id
        WHERE pr.predicted_by = ?
        AND pr.outcome = 'recovery'
        ORDER BY pr.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recoveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recoveries as $r) {
        $notifications[] = [
            'id' => 'recovery_' . $r['id'],
            'type' => 'recovery',
            'title' => 'Patient Recovery',
            'description' => $r['patient_name'] . ' successfully discharged. Recovery complete.',
            'timestamp' => $r['created_at'],
            'patient_id' => (int)$r['patient_id'],
            'patient_name' => $r['patient_name'],
            'severity' => null,
            'risk_score' => 0
        ];
    }
    
    // 4. Lab Results Available - recent lab reports
    $stmt = $pdo->prepare("
        SELECT 
            lr.id,
            lr.created_at,
            p.name as patient_name,
            p.id as patient_id
        FROM lab_reports lr
        JOIN patients p ON lr.patient_id = p.id
        WHERE p.created_by = ?
        ORDER BY lr.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $labResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($labResults as $lr) {
        $notifications[] = [
            'id' => 'lab_' . $lr['id'],
            'type' => 'lab_results',
            'title' => 'Lab Results Available',
            'description' => 'New test results uploaded for ' . $lr['patient_name'],
            'timestamp' => $lr['created_at'],
            'patient_id' => (int)$lr['patient_id'],
            'patient_name' => $lr['patient_name'],
            'severity' => null,
            'risk_score' => 0
        ];
    }
    
    // Sort all by timestamp (newest first)
    usort($notifications, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Limit
    $notifications = array_slice($notifications, 0, $limit);
    
    // Count unread (critical + high risk are considered unread by default)
    $unread_count = 0;
    foreach ($notifications as &$n) {
        if ($n['type'] === 'critical_alert' || $n['type'] === 'high_risk') {
            $n['is_unread'] = true;
            $unread_count++;
        } else {
            $n['is_unread'] = false;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'unread_count' => $unread_count,
            'total' => count($notifications)
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
