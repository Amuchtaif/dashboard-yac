<?php
$names = [
    "Sakan 1 (Abu Bakar)",
    "Sakan 9 (Sa'id Bin Zaid)",
    "Sakan 2 (Umar Bin Khatab)",
    "Sakan 3 (Utsman Bin Affan)",
    "Sakan 6 (Zubair Bin Awwam)",
    "Sakan 4 (Ali Bin Abi Thalib)",
    "Sakan 10 (Example)"
];

// Mocking the MySQL logic in PHP for testing
usort($names, function($a, $b) {
    $a_part = explode(' (', $a)[0];
    $b_part = explode(' (', $b)[0];
    
    if (strlen($a_part) != strlen($b_part)) {
        return strlen($a_part) - strlen($b_part);
    }
    return strcmp($a, $b);
});

print_r($names);
