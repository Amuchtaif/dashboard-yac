<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // 1. Get current data
    $data = $conn->query("SELECT * FROM academic_years")->fetchAll(PDO::FETCH_ASSOC);
    echo "Backing up " . count($data) . " rows.\n";

    // 2. Drop old table
    $conn->exec("DROP TABLE IF EXISTS academic_years_old");
    $conn->exec("RENAME TABLE academic_years TO academic_years_old");
    echo "Renamed existing table to academic_years_old.\n";

    // 3. Create new table
    $sql = "CREATE TABLE academic_years (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        status ENUM('Active', 'Inactive') DEFAULT 'Inactive',
        start_date DATE NULL,
        end_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    $conn->exec($sql);
    echo "Created new academic_years table.\n";

    // 4. Migrate data if possible
    if (!empty($data)) {
        foreach ($data as $row) {
            $cols = array_keys($row);
            $vals = array_values($row);

            // Only insert columns that exist in the new table
            $valid_cols = ['id', 'name', 'status', 'start_date', 'end_date', 'created_at', 'updated_at'];
            $insert_cols = [];
            $placeholders = [];
            $insert_vals = [];

            foreach ($row as $k => $v) {
                if (in_array($k, $valid_cols)) {
                    $insert_cols[] = $k;
                    $placeholders[] = "?";
                    $insert_vals[] = $v;
                }
            }

            if (!empty($insert_cols)) {
                $insert_sql = "INSERT INTO academic_years (" . implode(", ", $insert_cols) . ") VALUES (" . implode(", ", $placeholders) . ")";
                $stmt = $conn->prepare($insert_sql);
                $stmt->execute($insert_vals);
            }
        }
        echo "Migrated data.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$final = $conn->query("DESCRIBE academic_years")->fetchAll(PDO::FETCH_ASSOC);
echo "Final Columns:\n";
foreach ($final as $f) {
    echo "{$f['Field']} ({$f['Type']})\n";
}
?>