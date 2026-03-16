<?php
/**
 * Register API
 * Endpoint: POST /register.php
 * 
 * Creates a new healthcare professional account
 */

require_once 'db_config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Get POST data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$hospital = isset($_POST['hospital']) ? trim($_POST['hospital']) : '';
$specialty = isset($_POST['specialty']) ? trim($_POST['specialty']) : '';
$license = isset($_POST['license']) ? trim($_POST['license']) : '';

// Validate required fields
$missing = validateRequired(['name', 'email', 'password', 'phone'], $_POST);
if (!empty($missing)) {
    sendResponse(false, 'Missing required fields: ' . implode(', ', $missing), [], 400);
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', [], 400);
}

// Validate password length
if (strlen($password) < 6) {
    sendResponse(false, 'Password must be at least 6 characters', [], 400);
}

try {
    $conn = getConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        sendResponse(false, 'Email already registered', [], 409);
    }
    
    // Hash password with bcrypt before storing
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, hospital, specialization, role) VALUES (?, ?, ?, ?, ?, ?, 'doctor')");
    $stmt->execute([$name, $email, $hashedPassword, $phone, $hospital, $specialty]);
    
    $userId = $conn->lastInsertId();
    
    // Return success with user data
    $userData = [
        'id' => intval($userId),
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'hospital' => $hospital,
        'role' => 'doctor'
    ];
    
    sendResponse(true, 'Registration successful', $userData, 201);
    
} catch (PDOException $e) {
    sendResponse(false, 'Registration failed: ' . $e->getMessage(), [], 500);
}
?>
