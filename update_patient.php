<?php
/**
 * Update Patient API
 * Endpoint: POST /update_patient.php
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$patientId = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$age = isset($_POST['age']) ? intval($_POST['age']) : 0;
$gender = isset($_POST['gender']) ? strtolower(trim($_POST['gender'])) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$bloodGroup = isset($_POST['blood_group']) ? trim($_POST['blood_group']) : '';

// Validate required fields
if ($patientId <= 0) {
    sendResponse(false, 'Valid patient_id is required', [], 400);
}

$missing = validateRequired(['name', 'age', 'gender'], $_POST);
if (!empty($missing)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing), [], 400);
}

// Validate gender
if (!in_array($gender, ['male', 'female', 'other'])) {
    sendResponse(false, 'Gender must be male, female, or other', [], 400);
}

// Validate age
if ($age < 0 || $age > 150) {
    sendResponse(false, 'Invalid age value', [], 400);
}

try {
    $conn = getConnection();
    
    // Verify patient exists
    $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'Patient not found', [], 404);
    }
    
    // Update patient
    $stmt = $conn->prepare("
        UPDATE patients 
        SET name = ?, age = ?, gender = ?, phone = ?, address = ?, blood_group = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $age, $gender, $phone, $address, $bloodGroup, $patientId]);
    
    sendResponse(true, 'Patient updated successfully', [
        'patient_id' => $patientId,
        'name' => $name,
        'age' => $age,
        'gender' => $gender
    ]);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to update patient: ' . $e->getMessage(), [], 500);
}
?>
