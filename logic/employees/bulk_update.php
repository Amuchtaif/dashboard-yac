<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_ids = $_POST['employee_ids'] ?? [];
    $division_id = $_POST['division_id'] ?? '';
    // unit_id can be 'NULL' string if we want to explicitly set to NULL (e.g. Directly under Division)
    // or empty string '' if we want to "No Change"
    $unit_id = $_POST['unit_id'] ?? '';
    $position_id = $_POST['position_id'] ?? '';
    $schedule_id = $_POST['schedule_id'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $return_filters = $_POST['return_filters'] ?? '';
    $redirect_qs = $return_filters ? "&" . $return_filters : "";

    if (!empty($employee_ids) && is_array($employee_ids)) {
        $db = new Database();
        $conn = $db->getConnection();

        $update_parts = [];
        $execute_params = [];

        // DIVISION UPDATE
        if ($division_id !== '') {
            $update_parts[] = "division_id = ?";
            $execute_params[] = $division_id;

            // If Division is changed but Unit is NOT specified, reset Unit to NULL to avoid mismatch
            if ($unit_id === '') {
                $update_parts[] = "unit_id = NULL";
            }
        }

        // UNIT UPDATE
        if ($unit_id !== '') {
            if ($unit_id === 'NULL') {
                $update_parts[] = "unit_id = NULL";
            } else {
                $update_parts[] = "unit_id = ?";
                $execute_params[] = $unit_id;
            }
        }

        // POSITION UPDATE
        if ($position_id !== '') {
            $update_parts[] = "position_id = ?";
            $execute_params[] = $position_id;
        }

        // SCHEDULE UPDATE
        if ($schedule_id !== '') {
            if ($schedule_id === 'NULL') {
                $update_parts[] = "schedule_id = NULL";
            } else {
                $update_parts[] = "schedule_id = ?";
                $execute_params[] = $schedule_id;
            }
        }

        // GENDER UPDATE
        if ($gender !== '') {
            $update_parts[] = "gender = ?";
            $execute_params[] = $gender;
        }

        if (!empty($update_parts)) {
            $sql = "UPDATE employees SET " . implode(', ', $update_parts);
            $sql .= " WHERE id IN (" . str_repeat('?,', count($employee_ids) - 1) . "?)";

            // Append IDs to params
            foreach ($employee_ids as $id) {
                $execute_params[] = $id;
            }

            try {
                $stmt = $conn->prepare($sql);
                $stmt->execute($execute_params);

                $count = count($employee_ids);
                Logger::activity(
                    'Pegawai',
                    'BULK_UPDATE',
                    "Pembaruan massal data untuk $count pegawai",
                    [
                        'table' => 'employees',
                        'new_data' => [
                            'employee_count' => $count,
                            'employee_ids' => $employee_ids,
                            'division_id' => $division_id,
                            'unit_id' => $unit_id,
                            'position_id' => $position_id,
                            'schedule_id' => $schedule_id,
                            'gender' => $gender
                        ]
                    ]
                );

                // Redirect with success
                header("Location: ../../views/employees/index.php?success=Pembaruan+massal+berhasil" . $redirect_qs);
                exit();
            } catch (PDOException $e) {
                // Redirect with error
                header("Location: ../../views/employees/index.php?error=" . urlencode($e->getMessage()) . $redirect_qs);
                exit();
            }
        }
    }
}

        header("Location: ../../views/employees/index.php?success=Operasi+berhasil" . $redirect_qs);
exit();
?>
