<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS ramadan_overrides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        start_time TIME,
        end_time TIME,
        days TEXT
    )");
    
    // Migrate existing data if table is empty
    $count = $conn->query("SELECT COUNT(*) FROM ramadan_overrides")->fetchColumn();
    if ($count == 0) {
        $old = $conn->query("SELECT * FROM ramadan_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if ($old && !empty($old['half_day_days'])) {
            $stmt = $conn->prepare("INSERT INTO ramadan_overrides (start_time, end_time, days) VALUES (?, ?, ?)");
            $stmt->execute([$old['half_day_start_time'], $old['half_day_end_time'], $old['half_day_days']]);
        }
    }
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
