<?php
/**
 * Add Patient API
 * Endpoint: POST /add_patient.php
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$createdBy = isset($_POST['created_by']) ? intval($_POST['created_by']) : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$age = isset($_POST['age']) ? intval($_POST['age']) : 0;
$gender = isset($_POST['gender']) ? strtolower(trim($_POST['gender'])) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$bloodGroup = isset($_POST['blood_group']) ? trim($_POST['blood_group']) : '';

// Validate required fields
$missing = validateRequired(['created_by', 'name', 'age', 'gender'], $_POST);
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
    
    // Verify user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$createdBy]);
    if (!$stmt->fetch()) {
        sendResponse(false, 'User not found', [], 404);
    }
    
    // Insert patient
    $stmt = $conn->prepare("INSERT INTO patients (created_by, name, age, gender, phone, address, blood_group) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$createdBy, $name, $age, $gender, $phone, $address, $bloodGroup]);
    
    $patientId = $conn->lastInsertId();
    
    sendResponse(true, 'Patient added successfully', [
        'patient_id' => $patientId,
        'name' => $name,
        'age' => $age,
        'gender' => $gender
    ], 201);
    
} catch (PDOException $e) {
    sendResponse(false, 'Failed to add patient: ' . $e->getMessage(), [], 500);
}
?>
