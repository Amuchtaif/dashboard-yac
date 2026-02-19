<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$conn->exec("SET FOREIGN_KEY_CHECKS = 0");
$conn->exec("TRUNCATE TABLE class_schedules");
$conn->exec("SET FOREIGN_KEY_CHECKS = 1");
require 'demo_migrate.php';
