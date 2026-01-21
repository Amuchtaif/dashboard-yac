<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- Fixing Missing Units ---\n";

// 1. Get all Units
$units_map = [];
$stmt = $conn->query("SELECT id, name FROM education_units");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $units_map[strtoupper($row['name'])] = $row['id'];
    echo "Loaded Unit: " . $row['name'] . " (ID: " . $row['id'] . ")\n";
}

// 2. Define Mapping Rules (Regex -> Unit Name Key)
$rules = [
    '/^(TK|Kelompok|Hijau|Kuning)/i' => 'TKIT',
    '/^(1|2|3|4|5|6)[A-Z]?$/' => 'SDIT', // Matches 1, 1A, 2C etc.
    '/^(7|8|9)[A-Z]?$/' => 'MTS',       // Hypothetical
    '/^(10|11|12)[A-Z]?$/' => 'MA',      // Hypothetical
    '/Semester/i' => 'MA',               // Assumption: Semester classes are HS/MA
    '/Intensif/i' => 'MA',
    '/^Mustawa/i' => "MA'HAD"           // Map Mustawa to Ma'had Aly
];

// 3. Find Orphans
$stmt = $conn->query("SELECT id, name FROM grade_levels WHERE education_unit_id IS NULL");
$orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($orphans) . " orphans.\n";

foreach ($orphans as $grade) {
    $name = $grade['name'];
    $target_unit_id = null;

    foreach ($rules as $pattern => $unit_key) {
        if (preg_match($pattern, $name)) {
            // Check if Unit Exists
            if (isset($units_map[$unit_key])) {
                $target_unit_id = $units_map[$unit_key];
                echo "Mapping '$name' -> $unit_key (ID: $target_unit_id)\n";
                break;
            } else {
                // Try fuzzy match?
                foreach ($units_map as $u_name => $u_id) {
                    if (strpos($u_name, $unit_key) !== false) {
                        $target_unit_id = $u_id;
                        echo "Mapping '$name' -> $u_name (ID: $target_unit_id) via fuzzy $unit_key\n";
                        break 2;
                    }
                }
            }
        }
    }

    if ($target_unit_id) {
        $update = $conn->prepare("UPDATE grade_levels SET education_unit_id = :uid WHERE id = :id");
        $update->execute([':uid' => $target_unit_id, ':id' => $grade['id']]);
        echo "Updated Grade ID " . $grade['id'] . "\n";
    } else {
        echo "Could not map '$name'\n";
    }
}
?>