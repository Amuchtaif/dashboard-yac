<?php
require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->getConnection();

$units = $conn->query("SELECT id, name FROM education_units")->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('units_output.txt', json_encode($units, JSON_PRETTY_PRINT));
