<?php
require_once 'db_config.php';
try {
    $conn = getConnection();
    $stmt = $conn->prepare("DELETE FROM users WHERE id = 13");
    $stmt->execute();
    echo "Deleted user id 13 successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
