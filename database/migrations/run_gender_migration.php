<?php
// database/migrations/run_gender_migration.php
require_once __DIR__ . '/../../config/db_mysqli.php';

echo "Running Migration: Add Gender Column and Heuristic Seeding...\n";

// Reset gender values for test run
$mysqli->query("UPDATE employees SET gender = NULL");

// Get all active/inactive employees
$result = $mysqli->query("SELECT id, full_name FROM employees");

// Strong prefixes
$strong_male_prefix = '/^(muhammad|mohammad|m\.|ahmad|abdul|abdi|yusuf|hasan|husein|umar|utsman|ali|abu|ibnu|faisal|doni|roni|eko|agus|dedi|deni|budi|hendra|bambang|arman|arif|aziz|dawud|dimas|djoko|efan|farhan|fikrul|ginanjar|irwan|kholid|nasir|nofan|supriyadi|supriyatna|sutrisno|suwadi|taslim|tata|tendy|toni|wawan|zulfiqar)\b/i';
$strong_female_prefix = '/^(siti|nur|fatimah|aisyah|putri|dewi|ratna|dian|wulan|fitria|fitri|khadijah|maryam|zahra|rahma|lia|nani|rina|rini|atikah|desi|desi|eva|farah|feriyani|hagya|halimatus|hasnah|hawa|ida|iis|ika|indri|ineng|irma|juroidah|juwatiningsih|kusumawati|lilis|lina|mega|mimin|mugiarti|muntakhobati|muthmainah|muzdalifah|nabila|neng|ninik|nuniek|nuraeni|nurhayati|nurita|nurjanah|nurlailah|nurmay|pravita|prawitasari|rahmawati|ratini|risa|rismiati|riyana|rofikoh|rofiqoh|rosita|rukiyati|rusdiana|shafiyyah|suhaeni|sumiah|sunipah|tanti|tina|titi|tuti|ulyani|uum|wiwik|yuhani|yulia|novita|lugina)\b/i';

// Suffixes
$female_suffix = '/(wati|tuti|yanti|astuti|lestari|nita|lina|sih|nila|fi|ni|ti|si|ra|ma)$/i';
$male_suffix = '/(o|wan|man|to|so|in|id|ad|us|ur|ad|an|al|ik|im|is)$/i';

$femaleCount = 0;
$maleCount = 0;
$skippedCount = 0;

while ($row = $result->fetch_assoc()) {
    $name = trim($row['full_name']);
    
    // Clean name from titles
    // Remove degrees after comma
    $clean_name = preg_replace('/,.*$/', '', $name);
    // Remove degrees at the end without comma
    $clean_name = preg_replace('/\s+(s\.\s*[^ ]+|lc|a\.md|se|s\.ag|m\.pd|b\.a|s\.farm|s\.si|st|ma)\.?$/i', '', $clean_name);
    // Remove prefixes
    $clean_name = preg_replace('/^(apt\.|dr\.|h\.|hj\.|u\.)\s+/i', '', $clean_name);
    $clean_name = trim($clean_name);

    $gender = null;

    // 1. Strong Male Prefix Check
    if (preg_match($strong_male_prefix, $clean_name)) {
        $gender = 'Male';
    }
    // 2. Strong Female Prefix Check
    elseif (preg_match($strong_female_prefix, $clean_name)) {
        $gender = 'Female';
    }
    // 3. Suffix Check (only if no strong prefix matched)
    else {
        if (preg_match($female_suffix, $clean_name)) {
            $gender = 'Female';
        } elseif (preg_match($male_suffix, $clean_name)) {
            $gender = 'Male';
        }
    }

    if ($gender) {
        $stmt = $mysqli->prepare("UPDATE employees SET gender = ? WHERE id = ?");
        $stmt->bind_param("si", $gender, $row['id']);
        if ($stmt->execute()) {
            if ($gender === 'Female') $femaleCount++;
            else $maleCount++;
        }
        $stmt->close();
    } else {
        $skippedCount++;
        echo "Skipped (Could not determine): {$name} (Clean: {$clean_name})\n";
    }
}

echo "\nMigration Summary:\n";
echo "- Categorized as Female (Akhwat): {$femaleCount}\n";
echo "- Categorized as Male (Ikhwan): {$maleCount}\n";
echo "- Skipped (Needs manual update): {$skippedCount}\n";
echo "Done!\n";
?>
