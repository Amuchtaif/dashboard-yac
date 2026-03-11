<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("DESCRIBE subjects");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}
?>
