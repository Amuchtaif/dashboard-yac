<?php
// logic/permissions/update_role_permission.php

// Enable error reporting for debugging (Remove in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../../config/database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check login via session (return JSON error instead of redirect)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login first']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Read raw POST data
$rawData = file_get_contents('php://input');
$input = json_decode($rawData, true);

// Debug logging (optional, checks if input is received)
// file_put_contents('debug_permission.log', print_r($input, true));

$id = isset($input['id']) ? intval($input['id']) : 0;
// Security: preg_replace to ensure only alphanumeric and underscores
$permission_type = isset($input['permission_type']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $input['permission_type']) : '';
$value = isset($input['value']) ? intval($input['value']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

// Whitelist allowed columns to prevent SQL injection or arbitrary column updates
$allowed_columns = [
    'can_create_meeting', 
    'can_approve_permits', 
    'can_access_tahfidz', 
    'can_access_education',
    'can_manage_employees',
    'can_manage_academic',
    'can_manage_tahfidz',
    'can_manage_boarding',
    'can_manage_inventory',
    'can_manage_news',
    'can_manage_assignments',
    'can_access_kabid',
    'can_access_kesantrian',
    'can_access_documents',
    'can_manage_documents'
]; 
if (!in_array($permission_type, $allowed_columns)) {
    echo json_encode(['success' => false, 'message' => 'Invalid permission type: ' . htmlspecialchars($permission_type)]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Auto-migration: Ensure can_access_kesantrian column exists
    if ($permission_type === 'can_access_kesantrian') {
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_access_kesantrian'");
            if ($checkColumn->rowCount() === 0) {
                $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_access_kesantrian` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_access_kabid`");
            }
        } catch (Exception $e) {
            // Column might already exist, continue
        }
    }
    // Auto-migration: Ensure can_access_kabid column exists
    if ($permission_type === 'can_access_kabid') {
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_access_kabid'");
            if ($checkColumn->rowCount() === 0) {
                $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_access_kabid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_manage_assignments`");
            }
        } catch (Exception $e) {
            // Column might already exist, continue
        }
    }
    // Auto-migration: Ensure can_approve_permits column exists
    if ($permission_type === 'can_approve_permits') {
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_approve_permits'");
            if ($checkColumn->rowCount() === 0) {
                $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_approve_permits` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_create_meeting`");
            }
        } catch (Exception $e) {
            // Column might already exist, continue
        }
    }
    // Auto-migration: Ensure can_access_tahfidz column exists
    if ($permission_type === 'can_access_tahfidz') {
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_access_tahfidz'");
            if ($checkColumn->rowCount() === 0) {
                $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_access_tahfidz` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_approve_permits`");
            }
        } catch (Exception $e) {
            // Column might already exist, continue
        }
    }
    // Auto-migration: Ensure can_access_education column exists
    if ($permission_type === 'can_access_education') {
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_access_education'");
            if ($checkColumn->rowCount() === 0) {
                $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_access_education` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_access_tahfidz`");
            }
        } catch (Exception $e) {
            // Column might already exist, continue
        }
    }
    // Auto-migration: Ensure can_manage_news column exists
    if ($permission_type === 'can_manage_news') {
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM positions LIKE 'can_manage_news'");
            if ($checkColumn->rowCount() === 0) {
                $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_manage_news` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_manage_tahfidz`");
            }
        } catch (Exception $e) {
            // Column might already exist, continue
        }
    }

    // Dynamically build the query based on the whitelisted column
    // The column name is safe because it's checked against the whitelist
    $sql = "UPDATE positions SET `$permission_type` = :val WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':val', $value, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Permission updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update permission']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
