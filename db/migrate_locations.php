<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

try {
    // 1. Rename office_settings to locations
    $db->exec("ALTER TABLE `office_settings` RENAME TO `locations`;");
    
    // 2. Change columns to match instructions
    $db->exec("ALTER TABLE `locations` CHANGE `office_name` `name` VARCHAR(100);");
    $db->exec("ALTER TABLE `locations` CHANGE `radius_meters` `radius_meter` INT(11);");
    
    // 3. Add is_active column
    $db->exec("ALTER TABLE `locations` ADD IF NOT EXISTS `is_active` TINYINT(1) DEFAULT 1;");

    // 4. Update attendances table
    // (Assuming attendance -> attendances rename if instruction 1A was followed by user)
    // We already saw 'attendance' exists in my previous checks.
    $db->exec("ALTER TABLE `attendance` RENAME TO `attendances`;");
    $db->exec("ALTER TABLE `attendances` 
        MODIFY `lat_in` DECIMAL(10,8),
        MODIFY `long_in` DECIMAL(11,8),
        MODIFY `lat_out` DECIMAL(10,8),
        MODIFY `long_out` DECIMAL(11,8),
        ADD IF NOT EXISTS `location_id` INT NOT NULL AFTER `user_id`,
        ADD INDEX IF NOT EXISTS `idx_location_id` (`location_id`),
        ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`),
        ADD INDEX IF NOT EXISTS `idx_date` (`date`);");

    echo "Migration successful!";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
