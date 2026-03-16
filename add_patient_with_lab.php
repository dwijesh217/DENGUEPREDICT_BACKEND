<?php
/**
 * Add Patient with Lab Report
 * Creates a new patient record and associated lab report
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method', [], 405);
}

// Support both JSON and Form Data
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$data = !empty($jsonData) ? $jsonData : $_POST;

// Get patient data
$created_by = isset($data['created_by']) ? intval($data['created_by']) : 0;
$name = isset($data['name']) ? trim($data['name']) : '';
$age = isset($data['age']) ? intval($data['age']) : 0;
$gender = isset($data['gender']) ? strtolower(trim($data['gender'])) : '';
$blood_group = isset($data['blood_group']) ? trim($data['blood_group']) : '';
$phone = isset($data['phone']) ? trim($data['phone']) : '';
$address = isset($data['address']) ? trim($data['address']) : '';

// Get lab values
$platelet_count = isset($data['platelet_count']) ? floatval($data['platelet_count']) : 0;
$wbc_count = isset($data['wbc_count']) ? floatval($data['wbc_count']) : 0;
$fever_days = isset($data['fever_days']) ? intval($data['fever_days']) : 0;
$hematocrit = isset($data['hematocrit']) ? floatval($data['hematocrit']) : 0;
$hemoglobin = isset($data['hemoglobin']) ? floatval($data['hemoglobin']) : 0;
$symptoms = isset($data['symptoms']) ? trim($data['symptoms']) : '';

// Validate required fields
if ($created_by <= 0 || empty($name) || $age <= 0 || empty($gender)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required patient information'
    ]);
    exit;
}

if ($platelet_count <= 0 || $wbc_count <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required lab values'
    ]);
    exit;
}

// Validate gender enum
if (!in_array($gender, ['male', 'female', 'other'])) {
    $gender = 'other';
}

try {
    $conn = getConnection();
    
    // Start transaction
    $conn->beginTransaction();

    // Insert patient
    $stmt = $conn->prepare("INSERT INTO patients (created_by, name, age, gender, phone, address, blood_group) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$created_by, $name, $age, $gender, $phone, $address, $blood_group]);
    
    $patient_id = $conn->lastInsertId();
    
    // Insert lab report
    $stmt = $conn->prepare("INSERT INTO lab_reports (patient_id, recorded_by, platelet_count, wbc_count, hemoglobin, hematocrit, fever_days, symptoms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$patient_id, $created_by, $platelet_count, $wbc_count, $hemoglobin, $hematocrit, $fever_days, $symptoms]);
    
    $lab_report_id = $conn->lastInsertId();
    
    // Commit transaction
    $conn->commit();
    
    sendResponse(true, 'Patient added successfully', [
        'patient_id' => intval($patient_id),
        'lab_report_id' => intval($lab_report_id)
    ], 201);
    
} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    sendResponse(false, $e->getMessage(), [], 500);
}
?>
