<?php
/**
 * Test script for dengue severity logic (updated thresholds + safety rules)
 */

function mockPredict($p, $w, $f, $h, $hb, $gender = 'Male', $age = 30) {
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
        if ($h > 50) $hctScore = 25;
        elseif ($h > 45) $hctScore = 15;
        if ($hb < 12) $hbScore = 15;
        elseif ($hb > 17) $hbScore = 10;
    } else {
        if ($h > 48) $hctScore = 25;
        elseif ($h > 42) $hctScore = 15;
        if ($hb < 11) $hbScore = 15;
        elseif ($hb > 15.5) $hbScore = 10;
    }

    // 4. Fever Days
    if ($f > 6) $feverScore = 8;
    elseif ($f >= 5) $feverScore = 10;
    elseif ($f >= 3) $feverScore = 6;
    elseif ($f >= 1) $feverScore = 2;

    // Total (no age bonus)
    $totalScore = min($plateletScore + $wbcScore + $hctScore + $hbScore + $feverScore, 100);

    // Score-based classification
    if ($totalScore >= 61) {
        $severity = "Severe Dengue";
    } else if ($totalScore >= 31) {
        $severity = "Moderate Dengue";
    } else if ($totalScore >= 11) {
        $severity = "Mild Dengue";
    } else {
        $severity = "No Dengue / Very Low Risk";
    }

    // Safety overrides
    $highHCT = ($isMale && $h > 50) || (!$isMale && $h > 48);

    if ($p < 50000) {
        if ($highHCT) {
            if ($severity !== "Severe Dengue") {
                $severity = "Severe Dengue";
            }
        } else if ($wbcScore === 0 && $hctScore === 0 && $hbScore === 0 && $feverScore <= 2) {
            if (in_array($severity, ["No Dengue / Very Low Risk", "Mild Dengue"])) {
                $severity = "Moderate Dengue";
            }
        }
    }

    return [
        'totalScore' => $totalScore,
        'severity' => $severity
    ];
}

$testCases = [
    [
        'name' => 'All Normal → No Dengue (Score 0)',
        'p' => 160000, 'w' => 5000, 'f' => 0, 'h' => 40, 'hb' => 14, 'gender' => 'Male', 'age' => 30,
        'expected' => 'No Dengue / Very Low Risk'
    ],
    [
        'name' => 'Platelet 140k + Fever 1d → No Dengue (Score 12→Mild)',
        'p' => 140000, 'w' => 5000, 'f' => 1, 'h' => 40, 'hb' => 14, 'gender' => 'Male', 'age' => 30,
        'expected' => 'Mild Dengue' // 10(p) + 2(f) = 12
    ],
    [
        'name' => 'Platelet 140k + Fever 3d → Mild (Score 16)',
        'p' => 140000, 'w' => 5000, 'f' => 3, 'h' => 40, 'hb' => 14, 'gender' => 'Male', 'age' => 30,
        'expected' => 'Mild Dengue' // 10(p) + 6(f) = 16
    ],
    [
        'name' => 'Platelet 70k + Fever 5d → Moderate (Score 35)',
        'p' => 70000, 'w' => 5000, 'f' => 5, 'h' => 40, 'hb' => 14, 'gender' => 'Male', 'age' => 30,
        'expected' => 'Moderate Dengue' // 25(p) + 10(f) = 35
    ],
    [
        'name' => 'Platelet 70k + WBC 3500 + Fever 5d → Moderate (Score 40)',
        'p' => 70000, 'w' => 3500, 'f' => 5, 'h' => 40, 'hb' => 14, 'gender' => 'Male', 'age' => 30,
        'expected' => 'Moderate Dengue' // 25(p) + 5(w) + 10(f) = 40
    ],
    [
        'name' => 'Severe Dengue Multi-param (Score 95)',
        'p' => 40000, 'w' => 2500, 'f' => 5, 'h' => 55, 'hb' => 11, 'gender' => 'Male', 'age' => 30,
        'expected' => 'Severe Dengue' // 35+10+10+25+15 = 95
    ],
    [
        'name' => 'SAFETY: Platelet <50k + all normal → Moderate',
        'p' => 40000, 'w' => 5000, 'f' => 0, 'h' => 40, 'hb' => 14, 'gender' => 'Male', 'age' => 30,
        'expected' => 'Moderate Dengue' // Score 35 = Moderate, but safety rule also bumps to Moderate
    ],
    [
        'name' => 'SAFETY: Platelet <50k + high HCT (male) → Severe',
        'p' => 40000, 'w' => 5000, 'f' => 0, 'h' => 55, 'hb' => 14, 'gender' => 'Male', 'age' => 30,
        'expected' => 'Severe Dengue' // Score 35+25=60 → Moderate by score, but safety bumps to Severe
    ],
    [
        'name' => 'SAFETY: Platelet <50k + high HCT (female) → Severe',
        'p' => 40000, 'w' => 5000, 'f' => 0, 'h' => 50, 'hb' => 13, 'gender' => 'Female', 'age' => 25,
        'expected' => 'Severe Dengue' // Score 35+25=60 → Moderate by score, but safety bumps to Severe
    ],
    [
        'name' => 'Age <5 no longer adds points → No Dengue',
        'p' => 160000, 'w' => 5000, 'f' => 0, 'h' => 40, 'hb' => 14, 'gender' => 'Male', 'age' => 4,
        'expected' => 'No Dengue / Very Low Risk' // 0 points (age no longer scored)
    ]
];

foreach ($testCases as $tc) {
    $res = mockPredict($tc['p'], $tc['w'], $tc['f'], $tc['h'], $tc['hb'], $tc['gender'], $tc['age']);
    $status = ($res['severity'] === $tc['expected']) ? "PASS" : "FAIL";
    echo "Test: {$tc['name']} | Score: {$res['totalScore']} | Result: {$res['severity']} | Expected: {$tc['expected']} | $status\n";
}
?>
