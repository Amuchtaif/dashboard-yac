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

        if (!class_exists('Database')) return false;

        try {
            $db = new Database();
            $conn = $db->getConnection();

            // 0. Preliminary Check: Get Employee Position
            $pos_name_col = 'name';
            try {
                $checkCol = $conn->query("SHOW COLUMNS FROM positions LIKE 'position_name'");
                if ($checkCol && $checkCol->rowCount() > 0) {
                    $pos_name_col = 'position_name';
                }
            } catch (Exception $e) {
                // fallback to 'name'
            }

            $stmtEmp = $conn->prepare("SELECT e.position_id, p.{$pos_name_col} as position_name, p.level FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.id = ? LIMIT 1");
            $stmtEmp->execute([$employee_id]);
            $employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);

            if (!$employee) return false;

            // --- ADMINISTRATOR OVERRIDE ---
            if (isset($employee['position_name']) && $employee['position_name'] === 'Administrator') {
                return true;
            }

            // 1. HIGHEST PRIORITY: Manual User Exception/Override (Database 'user_permissions')
            $stmtIndex = $conn->prepare("SELECT is_allowed FROM user_permissions WHERE employee_id = ? AND permission_name = ? LIMIT 1");
            $stmtIndex->execute([$employee_id, $permission_name]);
            $user_perm = $stmtIndex->fetch(PDO::FETCH_ASSOC);

            if ($user_perm) {
                return (bool) $user_perm['is_allowed'];
            }

            // 1.1 Alias Check for user_permissions (to match web admin naming)
            if ($permission_name === 'create_meeting') {
                $stmtAlias = $conn->prepare("SELECT is_allowed FROM user_permissions WHERE employee_id = ? AND permission_name = 'access_meeting' LIMIT 1");
                $stmtAlias->execute([$employee_id]);
                $alias_perm = $stmtAlias->fetch(PDO::FETCH_ASSOC);
                if ($alias_perm) {
                    return (bool) $alias_perm['is_allowed'];
                }
            }

            // 2. SECOND PRIORITY: Role-based Permissions (Database 'positions')
            $permission_map = [
                'access_tahfidz' => 'can_access_tahfidz',
                'create_meeting' => 'can_create_meeting',
                'approve_permits' => 'can_approve_permits',
                'access_education' => 'can_access_education',
                'manage_employees' => 'can_manage_employees',
                'manage_academic' => 'can_manage_academic',
                'manage_tahfidz' => 'can_manage_tahfidz',
                'manage_boarding' => 'can_manage_boarding',
                'manage_inventory' => 'can_manage_inventory',
                'manage_news' => 'can_manage_news',
                'manage_assignments' => 'can_manage_assignments',
                'can_access_kabid' => 'can_access_kabid',
                'can_access_kesantrian' => 'can_access_kesantrian',
            ];

            if (array_key_exists($permission_name, $permission_map) && !empty($employee['position_id'])) {
                $column_name = $permission_map[$permission_name];
                $stmtRole = $conn->prepare("SELECT $column_name FROM positions WHERE id = ? LIMIT 1");
                $stmtRole->execute([$employee['position_id']]);
                $role_perm = $stmtRole->fetch(PDO::FETCH_ASSOC);

                if ($role_perm && !empty($role_perm[$column_name])) {
                    return true;
                }
            }

            // 3. LOWEST PRIORITY: Hardcoded Legacy Fallbacks
            
            // --- KEPALA BIDANG FALLBACK ---
            if ($permission_name === 'can_access_kabid') {
                // Hardcoded fallback removed as per user request to treat all levels same as teachers.
                // Access should now be managed via database permissions.
            }

            // --- KESANTRIAN FALLBACK ---
            if ($permission_name === 'can_access_kesantrian') {
                $posName = strtolower($employee['position_name'] ?? '');
                if (strpos($posName, 'musyrif') !== false || strpos($posName, 'kesantrian') !== false) return true;
            }

            // --- TEACHING SCHEDULE FALLBACK (Academic & Education) ---
            if ($permission_name === 'access_education' || $permission_name === 'manage_academic') {
                $stmtSched = $conn->prepare("SELECT COUNT(*) FROM class_schedules WHERE employee_id = ?");
                $stmtSched->execute([$employee_id]);
                if ($stmtSched->fetchColumn() > 0) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            error_log("Permission Check Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
