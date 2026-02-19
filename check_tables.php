<?php
try {
    $dsn = "mysql:host=localhost;dbname=assunnah_payroll;charset=utf8mb4";
    $db = new PDO($dsn, "root", "");
    $stmt = $db->query("SHOW TABLES");
    echo "Tables in assunnah_payroll:\n";
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "- " . $row[0] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
