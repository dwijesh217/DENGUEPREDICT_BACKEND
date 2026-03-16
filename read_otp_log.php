<?php
$logFile = __DIR__ . '/otp_log.txt';
if (file_exists($logFile)) {
    echo file_get_contents($logFile);
} else {
    echo "Log file not found.";
}
?>
