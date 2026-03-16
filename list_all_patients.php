<?php
require_once 'db_config.php';
try {
    $conn = getConnection();
    $stmt = $conn->query("SELECT id, name FROM patients");
    $patients = $stmt->fetchAll();
    echo json_encode($patients);
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
