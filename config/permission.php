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
            $stmtEmp = $conn->prepare("SELECT e.position_id, p.name as position_name, p.level FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.id = ? LIMIT 1");
            $stmtEmp->execute([$employee_id]);
            $employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);

            if (!$employee) return false;

            // --- ADMINISTRATOR OVERRIDE ---
            // If the position name is 'Administrator', grant all permissions automatically
            if (isset($employee['position_name']) && $employee['position_name'] === 'Administrator') {
                return true;
            }

            // --- KEPALA BIDANG OVERRIDE ---
            // Level 1 = Mudir, Level 2 = Kepala Bidang
            if ($permission_name === 'can_access_kabid') {
                if (isset($employee['level']) && ($employee['level'] == 1 || $employee['level'] == 2)) {
                    return true;
                }
            }

            // --- KESANTRIAN OVERRIDE ---
            if ($permission_name === 'can_access_kesantrian') {
                $posName = strtolower($employee['position_name'] ?? '');
                if (isset($employee['level']) && $employee['level'] <= 3) {
                    return true;
                }
                if (strpos($posName, 'musyrif') !== false || strpos($posName, 'kesantrian') !== false) {
                    return true;
                }
            }

            // --- TEACHING SCHEDULE OVERRIDE ---
            // If the permission requested is 'access_education', check if they have a teaching schedule
            if ($permission_name === 'access_education') {
                $stmtSched = $conn->prepare("SELECT COUNT(*) FROM class_schedules WHERE employee_id = ?");
                $stmtSched->execute([$employee_id]);
                if ($stmtSched->fetchColumn() > 0) {
                    return true;
                }
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
                'manage_news' => 'can_manage_news',
                'manage_assignments' => 'can_manage_assignments',
                'can_access_kabid' => 'can_access_kabid',
                'can_access_kesantrian' => 'can_access_kesantrian',
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
