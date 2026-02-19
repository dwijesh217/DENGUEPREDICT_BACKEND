<?php
/**
 * Add Patient with Lab Report
 * Creates a new patient record and associated lab report
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "denguepredict";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get patient data
$created_by = isset($_POST['created_by']) ? intval($_POST['created_by']) : 0;
$name = isset($_POST['name']) ? $conn->real_escape_string($_POST['name']) : '';
$age = isset($_POST['age']) ? intval($_POST['age']) : 0;
$gender = isset($_POST['gender']) ? $conn->real_escape_string(strtolower($_POST['gender'])) : '';
$blood_group = isset($_POST['blood_group']) ? $conn->real_escape_string($_POST['blood_group']) : '';
$phone = isset($_POST['phone']) ? $conn->real_escape_string($_POST['phone']) : '';
$address = isset($_POST['address']) ? $conn->real_escape_string($_POST['address']) : '';

// Get lab values
$platelet_count = isset($_POST['platelet_count']) ? floatval($_POST['platelet_count']) : 0;
$wbc_count = isset($_POST['wbc_count']) ? floatval($_POST['wbc_count']) : 0;
$fever_days = isset($_POST['fever_days']) ? intval($_POST['fever_days']) : 0;
$hematocrit = isset($_POST['hematocrit']) ? floatval($_POST['hematocrit']) : 0;
$hemoglobin = isset($_POST['hemoglobin']) ? floatval($_POST['hemoglobin']) : 0;

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

// Start transaction
$conn->begin_transaction();

try {
    // Insert patient
    $patient_sql = "INSERT INTO patients (created_by, name, age, gender, phone, address, blood_group) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($patient_sql);
    $stmt->bind_param("isissss", $created_by, $name, $age, $gender, $phone, $address, $blood_group);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert patient: " . $stmt->error);
    }
    
    $patient_id = $conn->insert_id;
    $stmt->close();
    
    // Insert lab report
    $lab_sql = "INSERT INTO lab_reports (patient_id, recorded_by, platelet_count, wbc_count, hemoglobin, hematocrit, fever_days) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($lab_sql);
    $stmt->bind_param("iiddddi", $patient_id, $created_by, $platelet_count, $wbc_count, $hemoglobin, $hematocrit, $fever_days);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert lab report: " . $stmt->error);
    }
    
    $lab_report_id = $conn->insert_id;
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Patient added successfully',
        'patient_id' => $patient_id,
        'lab_report_id' => $lab_report_id
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
