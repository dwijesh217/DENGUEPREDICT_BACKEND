<?php
require_once 'db_config.php';
try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = 11");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($user, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
