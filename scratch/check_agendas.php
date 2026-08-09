<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT id, title, category, source_type, unit_id FROM academic_calendar");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
