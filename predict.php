<?php
/**
 * Evaluate Prediction API
 * Endpoint: POST /predict.php
 * Takes laboratory inputs and returns severity, risk_score, and outcome.
 */

require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', [], 405);
}

// Ensure JSON data is parsed (handled by db_config.php)
$data = $_POST;

$requiredFields = ['platelets', 'wbc', 'feverDays', 'hct', 'hb'];
$missing = validateRequired($requiredFields, $data);

if (!empty($missing)) {
    sendResponse(false, 'Missing required fields', ['missing' => $missing], 400);
}

// Accept gender and age
$gender = isset($data['gender']) ? $data['gender'] : 'Male';
$age = isset($data['age']) ? intval($data['age']) : 30;

$p = floatval($data['platelets']);
$w = floatval($data['wbc']);
$h = floatval($data['hct']);
$hb = floatval($data['hb']);
$f = intval($data['feverDays']);

$points = 0;

// --- Track individual parameter scores for safety-rule checks ---
$plateletScore = 0;
$wbcScore = 0;
$hctScore = 0;
$hbScore = 0;
$feverScore = 0;

// 1. Platelets
if ($p < 50000) $plateletScore = 35;
elseif ($p < 100000) $plateletScore = 25;
elseif ($p < 150000) $plateletScore = 10;

// 2. WBC
if ($w < 3000) $wbcScore = 10;
elseif ($w < 4000) $wbcScore = 5;

// 3. HCT & HB based on Gender
$isMale = (strtolower($gender) === 'male');
if ($isMale) {
    // HCT Male
    if ($h > 50) $hctScore = 25;
    elseif ($h > 45) $hctScore = 15;
    
    // HB Male
    if ($hb < 12) $hbScore = 15;
    elseif ($hb > 17) $hbScore = 10;
} else {
    // HCT Female
    if ($h > 48) $hctScore = 25;
    elseif ($h > 42) $hctScore = 15;
    
    // HB Female
    if ($hb < 11) $hbScore = 15;
    elseif ($hb > 15.5) $hbScore = 10;
}

// 4. Fever Days
if ($f > 6) $feverScore = 8;
elseif ($f >= 5) $feverScore = 10;
elseif ($f >= 3) $feverScore = 6;
elseif ($f >= 1) $feverScore = 2;

// Total score (age is NOT scored)
$totalScore = $plateletScore + $wbcScore + $hctScore + $hbScore + $feverScore;
$totalScore = min($totalScore, 100);

// --- Score-based Classification ---
if ($totalScore >= 61) {
    $severity = "Severe Dengue";
    $theme = "red";
    $recommendation = "Emergency ICU (24 hrs monitoring).";
    $outcome = "Critical Attention";
} else if ($totalScore >= 31) {
    $severity = "Moderate Dengue";
    $theme = "orange";
    $recommendation = "IV fluids, daily labs.";
    $outcome = "Hospitalized";
} else if ($totalScore >= 11) {
    $severity = "Mild Dengue";
    $theme = "green";
    $recommendation = "Oral fluids, rest, follow-up.";
    $outcome = "Home Care";
} else {
    $severity = "No Dengue / Very Low Risk";
    $theme = "blue";
    $recommendation = "Normal monitoring, hydration.";
    $outcome = "Home Care";
}

// --- Safety Override Rules ---
$highHCT = ($isMale && $h > 50) || (!$isMale && $h > 48);

if ($p < 50000) {
    // Rule 2: Platelet < 50,000 + high HCT → at least Severe
    if ($highHCT) {
        if (!in_array($severity, ["Severe Dengue"])) {
            $severity = "Severe Dengue";
            $theme = "red";
            $recommendation = "Emergency ICU (24 hrs monitoring).";
            $outcome = "Critical Attention";
        }
    }
    // Rule 1: Platelet < 50,000 but other values normal → at least Moderate
    else if ($wbcScore === 0 && $hctScore === 0 && $hbScore === 0 && $feverScore <= 2) {
        if (in_array($severity, ["No Dengue / Very Low Risk", "Mild Dengue"])) {
            $severity = "Moderate Dengue";
            $theme = "orange";
            $recommendation = "IV fluids, daily labs.";
            $outcome = "Hospitalized";
        }
    }
}

$predictionStatus = ($totalScore >= 11) ? "Positive" : "Negative";

$responsePayload = [
    'score' => $totalScore,
    'severity' => $severity,
    'prediction' => $predictionStatus,
    'theme' => $theme,
    'recommendation' => $recommendation,
    'outcome' => $outcome
];

sendResponse(true, 'Prediction calculated successfully', $responsePayload);
?>
