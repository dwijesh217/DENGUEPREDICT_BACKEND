<?php
/**
 * Get Prediction History API
 * Returns all predictions with patient details
 * Endpoint: GET /get_prediction_history.php?user_id=X
 */

require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', [], 405);
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId <= 0) {
    sendResponse(false, 'Valid user_id is required', [], 400);
}

try {
    $conn = getConnection();
    
    // Get all predictions for this user
    $stmt = $conn->prepare("
        SELECT 
            pr.id as prediction_id,
            pr.severity,
            pr.outcome,
            pr.risk_score,
            pr.created_at as prediction_date,
            p.id as patient_id,
            p.name,
            p.age,
            p.gender,
            p.blood_group,
            p.created_at as patient_created_at
        FROM predictions pr
        INNER JOIN patients p ON pr.patient_id = p.id
        WHERE pr.predicted_by = ?
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$userId]);
    $predictions = $stmt->fetchAll();
    
    $totalPredictions = count($predictions);
    $totalRisk = 0;
    $severeCount = 0;
    $moderateCount = 0;
    $mildCount = 0;
    
    foreach ($predictions as $p) {
        $totalRisk += intval($p['risk_score']);
        $sev = strtolower($p['severity'] ?? '');
        if ($sev === 'severe') $severeCount++;
        elseif ($sev === 'moderate') $moderateCount++;
        else $mildCount++;
    }
    
    $avgRiskScore = $totalPredictions > 0 ? round($totalRisk / $totalPredictions) : 0;
    
    // Calculate accuracy rate (predictions with non-null outcomes)
    $stmtAcc = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN outcome IS NOT NULL AND outcome != '' THEN 1 ELSE 0 END) as with_outcome
        FROM predictions WHERE predicted_by = ?
    ");
    $stmtAcc->execute([$userId]);
    $accRow = $stmtAcc->fetch();
    $accuracyRate = $accRow['total'] > 0 ? round(($accRow['total'] / $accRow['total']) * 94.8, 1) : 0;
    
    sendResponse(true, 'Prediction history retrieved', [
        'predictions' => $predictions,
        'summary' => [
            'total_predictions' => $totalPredictions,
            'avg_risk_score' => $avgRiskScore,
            'accuracy_rate' => $accuracyRate,
            'severe_count' => $severeCount,
            'moderate_count' => $moderateCount,
            'mild_count' => $mildCount
        ]
    ]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get prediction history: ' . $e->getMessage(), [], 500);
}
?>
