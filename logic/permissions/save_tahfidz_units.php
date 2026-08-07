<?php
// logic/permissions/save_tahfidz_units.php

require_once '../../config/database.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $target_type = isset($_GET['type']) ? $_GET['type'] : '';
    $target_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($target_type === 'position' && $target_id > 0) {
        $stmt = $conn->prepare("SELECT unit_name FROM position_tahfidz_units WHERE position_id = ?");
        $stmt->execute([$target_id]);
        $units = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'units' => $units, 'has_custom' => count($units) > 0]);
        exit;
    } elseif ($target_type === 'employee' && $target_id > 0) {
        $stmt = $conn->prepare("SELECT unit_name FROM user_tahfidz_units WHERE employee_id = ?");
        $stmt->execute([$target_id]);
        $units = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Also get position defaults for reference
        $empStmt = $conn->prepare("SELECT position_id FROM employees WHERE id = ?");
        $empStmt->execute([$target_id]);
        $posId = $empStmt->fetchColumn();
        $posUnits = [];
        if ($posId) {
            $posStmt = $conn->prepare("SELECT unit_name FROM position_tahfidz_units WHERE position_id = ?");
            $posStmt->execute([$posId]);
            $posUnits = $posStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        echo json_encode([
            'success' => true, 
            'units' => $units, 
            'position_units' => $posUnits,
            'has_override' => count($units) > 0
        ]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $target_type = isset($input['type']) ? $input['type'] : '';
    $target_id = isset($input['id']) ? intval($input['id']) : 0;
    $units = isset($input['units']) && is_array($input['units']) ? $input['units'] : [];
    $revert = isset($input['revert']) && $input['revert'] === true;

    if ($target_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    try {
        if ($target_type === 'position') {
            $del = $conn->prepare("DELETE FROM position_tahfidz_units WHERE position_id = ?");
            $del->execute([$target_id]);

            if (!$revert && !empty($units)) {
                $ins = $conn->prepare("INSERT INTO position_tahfidz_units (position_id, unit_name) VALUES (?, ?)");
                foreach ($units as $u) {
                    $cleanU = strtoupper(trim($u));
                    if (!empty($cleanU)) {
                        $ins->execute([$target_id, $cleanU]);
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => 'Akses unit Tahfidz posisi berhasil diperbarui']);
            exit;
        } elseif ($target_type === 'employee') {
            $del = $conn->prepare("DELETE FROM user_tahfidz_units WHERE employee_id = ?");
            $del->execute([$target_id]);

            if (!$revert && !empty($units)) {
                $ins = $conn->prepare("INSERT INTO user_tahfidz_units (employee_id, unit_name) VALUES (?, ?)");
                foreach ($units as $u) {
                    $cleanU = strtoupper(trim($u));
                    if (!empty($cleanU)) {
                        $ins->execute([$target_id, $cleanU]);
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => 'Akses unit Tahfidz karyawan berhasil diperbarui']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
