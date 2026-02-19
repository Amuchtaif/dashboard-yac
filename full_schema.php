<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
file_put_contents("schema_output.txt", implode("\n", $tables));
foreach ($tables as $table) {
    $cols = $db->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
    $output = "Table: $table\n";
    foreach ($cols as $col) {
        $output .= "  - {$col['Field']} ({$col['Type']})\n";
    }
    file_put_contents("schema_output.txt", $output, FILE_APPEND);
}
