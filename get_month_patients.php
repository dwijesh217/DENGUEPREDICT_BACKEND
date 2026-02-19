<?php
/**
 * Get This Month's Patients API
 * Endpoint: GET /get_month_patients.php?user_id=X
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
    
    $currentMonth = date('Y-m');
    $daysInMonth = date('t');
    $dayOfMonth = date('j');
    
    // Get patients for this month
    $stmt = $conn->prepare("
        SELECT 
            p.id, p.name, p.age, p.gender, p.blood_group, p.created_at,
            (SELECT severity FROM predictions WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as last_severity,
            (SELECT risk_score FROM predictions WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as risk_score
        FROM patients p
        WHERE p.created_by = ? AND DATE_FORMAT(p.created_at, '%Y-%m') = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$userId, $currentMonth]);
    $patients = $stmt->fetchAll();
    
    $totalRegistered = count($patients);
    $dailyAverage = $dayOfMonth > 0 ? round($totalRegistered / $dayOfMonth, 1) : 0;
    
    // Severity counts
    $severeCount = 0;
    $moderateCount = 0;
    $mildCount = 0;
    
    foreach ($patients as $p) {
        $sev = strtolower($p['last_severity'] ?? '');
        if ($sev === 'severe') $severeCount++;
        elseif ($sev === 'moderate') $moderateCount++;
        else $mildCount++;
    }
    
    sendResponse(true, 'Month patients retrieved', [
        'patients' => $patients,
        'total_registered' => $totalRegistered,
        'daily_average' => $dailyAverage,
        'severe_count' => $severeCount,
        'moderate_count' => $moderateCount,
        'mild_count' => $mildCount,
        'month_name' => date('F Y')
    ]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to get month patients: ' . $e->getMessage(), [], 500);
}
?>
