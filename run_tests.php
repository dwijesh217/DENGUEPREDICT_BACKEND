<?php
ob_start();
include 'test_logic.php';
$output = ob_get_clean();
$lines = array_filter(explode("\n", trim($output)));
$pass = 0; $fail = 0;
foreach ($lines as $l) {
    if (strpos($l, 'FAIL') !== false) $fail++;
    elseif (strpos($l, 'PASS') !== false) $pass++;
}
echo "PASS: $pass, FAIL: $fail, TOTAL: " . ($pass + $fail) . "\n";
if ($fail > 0) {
    echo "FAILED TESTS:\n";
    foreach ($lines as $l) {
        if (strpos($l, 'FAIL') !== false) echo $l . "\n";
    }
}
?>
