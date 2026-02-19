<?php
/**
 * Get Analytics API
 * Returns analytics data: total cases, this month, critical, severity distribution, monthly trend
 * Endpoint: GET /get_analytics.php?user_id=X
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
    
    // Total cases (all patients)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM patients WHERE created_by = ?");
    $stmt->execute([$userId]);
    $totalCases = $stmt->fetch()['total'];
    
    // This month cases
    $currentMonth = date('Y-m');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM patients WHERE created_by = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$userId, $currentMonth]);
    $thisMonthCount = $stmt->fetch()['total'];
    
    // Last month cases (for percentage change)
    $lastMonth = date('Y-m', strtotime('-1 month'));
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM patients WHERE created_by = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$userId, $lastMonth]);
    $lastMonthCount = $stmt->fetch()['total'];
    
    // Previous month total (for total change)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM patients WHERE created_by = ? AND created_at < DATE_FORMAT(NOW(), '%Y-%m-01')");
    $stmt->execute([$userId]);
    $prevTotal = $stmt->fetch()['total'];
    
    // Critical cases (severe predictions)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT p.id) as total FROM patients p
        INNER JOIN predictions pr ON pr.patient_id = p.id
        WHERE p.created_by = ? AND (pr.severity = 'severe' OR pr.risk_score >= 70)
        AND pr.id = (SELECT pr2.id FROM predictions pr2 WHERE pr2.patient_id = p.id ORDER BY pr2.created_at DESC LIMIT 1)
    ");
    $stmt->execute([$userId]);
    $criticalCases = $stmt->fetch()['total'];
    
    // Critical cases last month
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT p.id) as total FROM patients p
        INNER JOIN predictions pr ON pr.patient_id = p.id
        WHERE p.created_by = ? AND (pr.severity = 'severe' OR pr.risk_score >= 70)
        AND pr.id = (SELECT pr2.id FROM predictions pr2 WHERE pr2.patient_id = p.id ORDER BY pr2.created_at DESC LIMIT 1)
        AND DATE_FORMAT(p.created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$userId, $lastMonth]);
    $criticalLastMonth = $stmt->fetch()['total'];
    
    // Severity distribution
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(pr.severity, 'mild') as severity,
            COUNT(*) as count
        FROM patients p
        LEFT JOIN predictions pr ON pr.patient_id = p.id
            AND pr.id = (SELECT pr2.id FROM predictions pr2 WHERE pr2.patient_id = p.id ORDER BY pr2.created_at DESC LIMIT 1)
        WHERE p.created_by = ?
        GROUP BY COALESCE(pr.severity, 'mild')
    ");
    $stmt->execute([$userId]);
    $severityRows = $stmt->fetchAll();
    
    $severityDist = ['mild' => 0, 'moderate' => 0, 'severe' => 0];
    foreach ($severityRows as $row) {
        $sev = strtolower($row['severity']);
        if (isset($severityDist[$sev])) {
            $severityDist[$sev] = intval($row['count']);
        } else {
            $severityDist['mild'] += intval($row['count']);
        }
    }
    
    // Monthly trend (last 6 months)
    $monthlyTrend = [];
    for ($i = 5; $i >= 0; $i--) {
        $monthDate = date('Y-m', strtotime("-$i months"));
        $monthLabel = date('M', strtotime("-$i months"));
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM patients WHERE created_by = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmt->execute([$userId, $monthDate]);
        $count = $stmt->fetch()['total'];
        
        $monthlyTrend[] = [
            'month' => $monthLabel,
            'count' => intval($count)
        ];
    }
    
    // Calculate percentage changes
    $totalChange = $prevTotal > 0 ? round(($thisMonthCount / $prevTotal) * 100) : 0;
    $monthChange = $lastMonthCount > 0 ? round((($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100) : 0;
    $criticalChange = $criticalCases - $criticalLastMonth;
    
    sendResponse(true, 'Analytics retrieved', [
        'total_cases' => intval($totalCases),
        'total_change_percent' => $totalChange,
        'this_month' => intval($thisMonthCount),
        'month_change_percent' => $monthChange,
        'critical_cases' => intval($criticalCases),
        'critical_change' => $criticalChange,
        'severity_distribution' => $severityDist,
        'monthly_trend' => $monthlyTrend
    ]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get analytics: ' . $e->getMessage(), [], 500);
}
?>
