<?php
require_once 'db_config.php';
try {
    $conn = getConnection();
    $stmt = $conn->query("SELECT id, email, created_at FROM users ORDER BY created_at DESC LIMIT 20");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($users, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
