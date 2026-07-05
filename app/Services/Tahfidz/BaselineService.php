<?php
// app/Services/Tahfidz/BaselineService.php

class BaselineService {
    private $mysqli;

    public function __construct($mysqli = null) {
        if ($mysqli) {
            $this->mysqli = $mysqli;
        } else {
            global $mysqli;
            require_once __DIR__ . '/../../../config/db_mysqli.php';
            $this->mysqli = $mysqli;
        }
    }

    public function createBaseline($data) {
        $academic_year_id = isset($data['academic_year_id']) ? (int)$data['academic_year_id'] : 0;
        $student_id = isset($data['student_id']) ? (int)$data['student_id'] : 0;
        $baseline_juz = isset($data['baseline_juz']) ? (float)$data['baseline_juz'] : 0.0;
        $notes = isset($data['notes']) ? $data['notes'] : '';

        // Validation
        if ($academic_year_id <= 0 || $student_id <= 0 || $baseline_juz < 0) {
            throw new Exception("Validation failed: academic_year_id, student_id are required, and baseline_juz must be >= 0.");
        }

        // Check if Academic Year is active
        $stmt = $this->mysqli->prepare("SELECT is_active FROM academic_years WHERE id = ?");
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $ay = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ay || !$ay['is_active']) {
            throw new Exception("Academic Year must be active to set a baseline.");
        }

        // Check if Student is active
        $stmt = $this->mysqli->prepare("SELECT status FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $st = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$st || strcasecmp($st['status'], 'Aktif') !== 0) {
            throw new Exception("Student must be active to set a baseline.");
        }

        // Check for duplicates
        $stmt = $this->mysqli->prepare("SELECT id FROM memorization_baselines WHERE academic_year_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $academic_year_id, $student_id);
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($dup) {
            throw new Exception("Baseline already exists for this student and academic year.");
        }

        // Insert
        $stmt = $this->mysqli->prepare("INSERT INTO memorization_baselines (academic_year_id, student_id, baseline_juz, notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iids", $academic_year_id, $student_id, $baseline_juz, $notes);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert baseline: " . $stmt->error);
        }
        $id = $stmt->insert_id;
        $stmt->close();

        // Logging
        $this->logActivity($student_id, "Baseline created with value: $baseline_juz");

        return $id;
    }

    public function updateBaseline($id, $data) {
        $id = (int)$id;
        $baseline_juz = isset($data['baseline_juz']) ? (float)$data['baseline_juz'] : null;
        $notes = isset($data['notes']) ? $data['notes'] : null;

        // Fetch existing
        $stmt = $this->mysqli->prepare("SELECT * FROM memorization_baselines WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            throw new Exception("Baseline record not found.");
        }

        // Validation for active academic year
        $stmt = $this->mysqli->prepare("SELECT is_active FROM academic_years WHERE id = ?");
        $stmt->bind_param("i", $existing['academic_year_id']);
        $stmt->execute();
        $ay = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ay || !$ay['is_active']) {
            throw new Exception("Cannot modify baseline for an inactive academic year.");
        }

        $new_juz = ($baseline_juz !== null) ? $baseline_juz : $existing['baseline_juz'];
        $new_notes = ($notes !== null) ? $notes : $existing['notes'];

        $stmt = $this->mysqli->prepare("UPDATE memorization_baselines SET baseline_juz = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("dsi", $new_juz, $new_notes, $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update baseline: " . $stmt->error);
        }
        $stmt->close();

        // Logging
        $this->logActivity($existing['student_id'], "Baseline updated from {$existing['baseline_juz']} to $new_juz");

        return true;
    }

    public function deleteBaseline($id) {
        $id = (int)$id;
        $stmt = $this->mysqli->prepare("SELECT * FROM memorization_baselines WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            throw new Exception("Baseline record not found.");
        }

        // Delete
        $stmt = $this->mysqli->prepare("DELETE FROM memorization_baselines WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Logging
        $this->logActivity($existing['student_id'], "Baseline deleted (was: {$existing['baseline_juz']})");

        return true;
    }

    public function getBaseline($id) {
        $id = (int)$id;
        $stmt = $this->mysqli->prepare("SELECT b.*, s.nama_siswa as student_name, ay.name as academic_year_name 
            FROM memorization_baselines b
            JOIN students s ON b.student_id = s.id
            JOIN academic_years ay ON b.academic_year_id = ay.id
            WHERE b.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res;
    }

    public function listBaselines($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE 1=1";
        $params = [];
        $types = "";

        if (isset($filters['academic_year_id']) && $filters['academic_year_id'] > 0) {
            $where .= " AND b.academic_year_id = ?";
            $params[] = (int)$filters['academic_year_id'];
            $types .= "i";
        }
        if (isset($filters['student_id']) && $filters['student_id'] > 0) {
            $where .= " AND b.student_id = ?";
            $params[] = (int)$filters['student_id'];
            $types .= "i";
        }
        if (isset($filters['search']) && !empty($filters['search'])) {
            $where .= " AND s.nama_siswa LIKE ?";
            $params[] = "%" . $filters['search'] . "%";
            $types .= "s";
        }

        // Count total
        $count_query = "SELECT COUNT(*) FROM memorization_baselines b JOIN students s ON b.student_id = s.id $where";
        $stmt = $this->mysqli->prepare($count_query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        // Fetch data
        $query = "SELECT b.*, s.nama_siswa as student_name, ay.name as academic_year_name 
            FROM memorization_baselines b
            JOIN students s ON b.student_id = s.id
            JOIN academic_years ay ON b.academic_year_id = ay.id
            $where ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
        
        $fetch_params = $params;
        $fetch_params[] = $limit;
        $fetch_params[] = $offset;
        $fetch_types = $types . "ii";

        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param($fetch_types, ...$fetch_params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();

        return [
            'total' => $total,
            'pages' => ceil($total / $limit),
            'page' => $page,
            'limit' => $limit,
            'data' => $data
        ];
    }

    private function logActivity($student_id, $message) {
        $log_file = __DIR__ . '/../../../tmp/tahfidz_activity.log';
        if (!file_exists(dirname($log_file))) {
            mkdir(dirname($log_file), 0777, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [Student ID: $student_id] [BASELINE] $message" . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}
