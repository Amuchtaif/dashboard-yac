<?php
try {
    $dsn = "mysql:host=localhost;dbname=assunnah_payroll;charset=utf8mb4";
    $db = new PDO($dsn, "root", "");
    $stmt = $db->query("DESCRIBE payrolls");
    echo "Columns in payrolls:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
