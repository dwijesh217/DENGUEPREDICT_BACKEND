<?php
require_once 'db_config.php';
try {
    $conn = getConnection();
    $conn->exec("ALTER TABLE lab_reports ADD COLUMN IF NOT EXISTS symptoms TEXT AFTER fever_days");
    echo "Successfully updated lab_reports schema.";
} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage();
}
?>
