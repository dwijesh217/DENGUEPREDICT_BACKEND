<?php
require_once 'db_config.php';
try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE email LIKE ?");
    $stmt->execute(['%kingsdevil%']);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($users, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
