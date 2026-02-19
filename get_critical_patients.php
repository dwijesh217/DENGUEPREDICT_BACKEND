<?php
/**
 * Get Critical Patients API
 * Returns patients with severe/critical predictions
 * Endpoint: GET /get_critical_patients.php?user_id=X
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
    
    // Get patients with severe predictions or high risk scores
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.name,
            p.age,
            p.gender,
            p.blood_group,
            p.created_at,
            pr.severity,
            pr.outcome,
            pr.risk_score,
            pr.created_at as prediction_date,
            lr.platelet_count,
            lr.wbc_count,
            lr.hematocrit,
            lr.hemoglobin,
            lr.fever_days
        FROM patients p
        INNER JOIN predictions pr ON pr.patient_id = p.id
        INNER JOIN lab_reports lr ON pr.lab_report_id = lr.id
        WHERE p.created_by = ? 
          AND (pr.severity = 'severe' OR pr.risk_score >= 70 OR pr.outcome = 'critical')
          AND pr.id = (
              SELECT pr2.id FROM predictions pr2 
              WHERE pr2.patient_id = p.id 
              ORDER BY pr2.created_at DESC LIMIT 1
          )
        ORDER BY pr.risk_score DESC
    ");
    $stmt->execute([$userId]);
    $patients = $stmt->fetchAll();
    
    // Calculate summary stats
    $totalCritical = count($patients);
    $totalRiskScore = 0;
    $severeCount = 0;
    
    foreach ($patients as $p) {
        $totalRiskScore += intval($p['risk_score']);
        if (strtolower($p['severity']) === 'severe') {
            $severeCount++;
        }
    }
    
    $avgRiskScore = $totalCritical > 0 ? round($totalRiskScore / $totalCritical) : 0;
    
    sendResponse(true, 'Critical patients retrieved', [
        'patients' => $patients,
        'summary' => [
            'total_critical' => $totalCritical,
            'severe_count' => $severeCount,
            'avg_risk_score' => $avgRiskScore
        ]
    ]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get critical patients: ' . $e->getMessage(), [], 500);
}
?>
