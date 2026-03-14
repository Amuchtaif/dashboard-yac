<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $groups = $_POST['groups'] ?? [];

    try {
        $conn->beginTransaction();

        // 1. Update Global Switch
        $stmt = $conn->prepare("UPDATE ramadan_settings SET is_active = ? WHERE id = 1");
        $stmt->execute([$is_active]);

        // 2. Clear Overrides
        $conn->exec("DELETE FROM ramadan_overrides");

        // 3. Process Groups
        $all_affected_units = [];

        if (!empty($groups)) {
            $stmtInsert = $conn->prepare("INSERT INTO ramadan_overrides (label, start_time, end_time, days, unit_ids) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($groups as $group) {
                $label = trim($group['label']) ?: "Grup Jadwal";
                $units = $group['units'] ?? [];
                $days_config = $group['days'] ?? [];

                if (empty($units)) continue; // No units, no need to save

                // Collect units for sync
                foreach ($units as $u) $all_affected_units[] = (int)$u;

                $unit_ids_str = implode(',', $units);

                // Group days by hours to minimize DB rows
                $hours_map = []; // key: "start|end" => array of days
                foreach ($days_config as $day_name => $config) {
                    if (isset($config['is_off'])) continue; // Skip if marked off

                    $start = $config['start'];
                    $end = $config['end'];
                    $time_key = "$start|$end";

                    if (!isset($hours_map[$time_key])) {
                        $hours_map[$time_key] = [];
                    }
                    $hours_map[$time_key][] = $day_name;
                }

                // Insert grouped rows
                foreach ($hours_map as $time_key => $days) {
                    list($start, $end) = explode('|', $time_key);
                    $days_str = implode(',', $days);
                    $stmtInsert->execute([$label, $start, $end, $days_str, $unit_ids_str]);
                }
            }
        }

        // 4. Sycn is_ramadan_affected status in units table
        $conn->exec("UPDATE units SET is_ramadan_affected = 0");
        $all_affected_units = array_unique($all_affected_units);
        
        if (!empty($all_affected_units)) {
            $ids = implode(',', $all_affected_units);
            $conn->exec("UPDATE units SET is_ramadan_affected = 1 WHERE id IN ($ids)");
        }

        $conn->commit();
        header("Location: " . BASE_URL . "/views/settings/ramadan.php?success=" . urlencode("Pengaturan Ramadan Grid berhasil disimpan."));
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: " . BASE_URL . "/views/settings/ramadan.php?error=" . urlencode("Gagal simpan: " . $e->getMessage()));
        exit;
    }
}
