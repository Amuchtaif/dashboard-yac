<?php
// config/permission.php

if (!function_exists('hasPermission')) {
    function hasPermission($employee_id, $permission_name) {
        // Ensure Database class is loaded
        if (!class_exists('Database')) {
            $possible_paths = [
                __DIR__ . '/database.php',
                __DIR__ . '/../config/database.php',
                $_SERVER['DOCUMENT_ROOT'] . '/dashboard-yac/config/database.php'
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    break;
                }
            }
        }

        if (!class_exists('Database')) {
            error_log("Database class not found in hasPermission");
            return false;
        }

        try {
            $db = new Database();
            $conn = $db->getConnection();

            // 0. Preliminary Check: Get Employee Position
            $stmtEmp = $conn->prepare("SELECT e.position_id, p.name as position_name FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.id = ? LIMIT 1");
            $stmtEmp->execute([$employee_id]);
            $employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);

            if (!$employee) return false;

            // --- ADMINISTRATOR OVERRIDE ---
            // If the position name is 'Administrator', grant all permissions automatically
            if (isset($employee['position_name']) && $employee['position_name'] === 'Administrator') {
                return true;
            }

            // 1. Check Specific User Permission (Exception/Override)
            $stmtIndex = $conn->prepare("SELECT is_allowed FROM user_permissions WHERE employee_id = ? AND permission_name = ? LIMIT 1");
            $stmtIndex->execute([$employee_id, $permission_name]);
            $user_perm = $stmtIndex->fetch(PDO::FETCH_ASSOC);

            if ($user_perm) {
                return (bool) $user_perm['is_allowed'];
            }

            // 2. Check Role-based Permission (Positions Table)
            // Map permission names to database columns
            $permission_map = [
                'access_tahfidz' => 'can_access_tahfidz',
                'create_meeting' => 'can_create_meeting',
                'approve_permits' => 'can_approve_permits',
                'access_education' => 'can_access_education',
                'manage_employees' => 'can_manage_employees',
                'manage_academic' => 'can_manage_academic',
                'manage_tahfidz' => 'can_manage_tahfidz',
            ];

            if (!array_key_exists($permission_name, $permission_map)) {
                return false; 
            }

            $column_name = $permission_map[$permission_name];

            if (empty($employee['position_id'])) {
                return false;
            }

            // Check Position column
            $stmtRole = $conn->prepare("SELECT $column_name FROM positions WHERE id = ? LIMIT 1");
            $stmtRole->execute([$employee['position_id']]);
            $role_perm = $stmtRole->fetch(PDO::FETCH_ASSOC);

            if ($role_perm && isset($role_perm[$column_name])) {
                return (bool) $role_perm[$column_name];
            }

            return false;

        } catch (Exception $e) {
            error_log("Permission Check Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
