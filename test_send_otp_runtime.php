<?php
// Mock POST data
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['email'] = 'kingsdevil789@gmail.com';
$_POST['purpose'] = 'registration';

// Include the script
include 'send_otp.php';
?>
