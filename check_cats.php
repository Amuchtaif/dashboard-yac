<?php
require_once 'config/app.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

$cats = $conn->query("SELECT DISTINCT category FROM grade_levels")->fetchAll(PDO::FETCH_COLUMN);

echo "Categories Found:\n";
foreach ($cats as $c) {
    echo "- '" . $c . "'\n";
}
?>