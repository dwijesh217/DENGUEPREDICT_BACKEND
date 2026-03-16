<?php
require_once 'db_config.php';
try {
    $conn = getConnection();
    $stmt = $conn->prepare("DELETE FROM users WHERE id = 11");
    $stmt->execute();
    echo "Deleted user id 11 successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
