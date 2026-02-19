<?php
/**
 * Dashboard Statistics API
 * Endpoint: GET /get_stats.php?user_id=X
 */

require_once 'db_config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get user_id from query params
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId <= 0) {
    sendResponse(false, 'Valid user_id is required', [], 400);
}

try {
    $conn = getConnection();
    
    // Total patients (created by this user)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM patients WHERE created_by = ?");
    $stmt->execute([$userId]);
    $totalPatients = $stmt->fetch()['total'];
    
    // Total predictions (made by this user)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM predictions WHERE predicted_by = ?");
    $stmt->execute([$userId]);
    $totalPredictions = $stmt->fetch()['total'];
    
    // Severity distribution
    $stmt = $conn->prepare("
        SELECT severity, COUNT(*) as count 
        FROM predictions 
        WHERE predicted_by = ? 
        GROUP BY severity
    ");
    $stmt->execute([$userId]);
    $severityDist = $stmt->fetchAll();
    
    // Outcome distribution
    $stmt = $conn->prepare("
        SELECT outcome, COUNT(*) as count 
        FROM predictions 
        WHERE predicted_by = ? 
        GROUP BY outcome
    ");
    $stmt->execute([$userId]);
    $outcomeDist = $stmt->fetchAll();
    
    // Critical cases (severe severity or critical outcome)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM predictions 
        WHERE predicted_by = ? AND (severity = 'severe' OR outcome = 'critical')
    ");
    $stmt->execute([$userId]);
    $criticalCases = $stmt->fetch()['total'];
    
    // Unresolved alerts count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM alerts a
        JOIN patients p ON a.patient_id = p.id
        WHERE p.created_by = ? AND a.is_resolved = 0
    ");
    $stmt->execute([$userId]);
    $unresolvedAlerts = $stmt->fetch()['total'];
    
    // Recent predictions (last 7 days)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM predictions 
        WHERE predicted_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$userId]);
    $recentPredictions = $stmt->fetch()['total'];
    
    // High risk count (severe predictions)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM predictions 
        WHERE predicted_by = ? AND (severity = 'severe' OR risk_score >= 70)
    ");
    $stmt->execute([$userId]);
    $highRiskCount = $stmt->fetch()['total'];
    
    // Today's assessments
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM predictions 
        WHERE predicted_by = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$userId]);
    $todayAssessments = $stmt->fetch()['total'];
    
    // Average risk score
    $stmt = $conn->prepare("SELECT AVG(risk_score) as avg_score FROM predictions WHERE predicted_by = ?");
    $stmt->execute([$userId]);
    $avgRiskScore = round($stmt->fetch()['avg_score'] ?? 0, 2);
    
    sendResponse(true, 'Statistics retrieved successfully', [
        'total_patients' => intval($totalPatients),
        'total_predictions' => intval($totalPredictions),
        'critical_cases' => intval($criticalCases),
        'unresolved_alerts' => intval($unresolvedAlerts),
        'recent_predictions' => intval($recentPredictions),
        'high_risk_count' => intval($highRiskCount),
        'today_assessments' => intval($todayAssessments),
        'critical_alerts' => intval($unresolvedAlerts), // Alias for dashboard use
        'avg_risk_score' => $avgRiskScore,
        'severity_distribution' => $severityDist,
        'outcome_distribution' => $outcomeDist
    ]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get statistics: ' . $e->getMessage(), [], 500);
}
?>
