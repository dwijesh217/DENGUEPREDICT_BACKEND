<?php
require_once 'db_config.php';
try {
    $conn = getConnection();
    $stmt = $conn->query("DESCRIBE users");
    $schema = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($schema, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
