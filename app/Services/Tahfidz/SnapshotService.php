<?php
// app/Services/Tahfidz/SnapshotService.php

class SnapshotService {
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

    public function getStudentSnapshot($student_id, $academic_year_id, $semester) {
        $student_id = (int)$student_id;
        $academic_year_id = (int)$academic_year_id;

        $stmt = $this->mysqli->prepare("SELECT sn.*, s.nama_siswa as student_name, ay.name as academic_year_name 
            FROM semester_snapshots sn
            JOIN students s ON sn.student_id = s.id
            JOIN academic_years ay ON sn.academic_year_id = ay.id
            WHERE sn.student_id = ? AND sn.academic_year_id = ? AND sn.semester = ? LIMIT 1");
        
        $stmt->bind_param("iis", $student_id, $academic_year_id, $semester);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $res;
    }

    public function listSnapshots($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE 1=1";
        $params = [];
        $types = "";

        if (isset($filters['academic_year_id']) && $filters['academic_year_id'] > 0) {
            $where .= " AND sn.academic_year_id = ?";
            $params[] = (int)$filters['academic_year_id'];
            $types .= "i";
        }
        if (isset($filters['semester']) && !empty($filters['semester'])) {
            $where .= " AND sn.semester = ?";
            $params[] = $filters['semester'];
            $types .= "s";
        }
        if (isset($filters['student_id']) && $filters['student_id'] > 0) {
            $where .= " AND sn.student_id = ?";
            $params[] = (int)$filters['student_id'];
            $types .= "i";
        }

        // Count total
        $count_query = "SELECT COUNT(*) FROM semester_snapshots sn $where";
        $stmt = $this->mysqli->prepare($count_query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        // Fetch data
        $query = "SELECT sn.*, s.nama_siswa as student_name, ay.name as academic_year_name 
            FROM semester_snapshots sn
            JOIN students s ON sn.student_id = s.id
            JOIN academic_years ay ON sn.academic_year_id = ay.id
            $where ORDER BY sn.generated_at DESC LIMIT ? OFFSET ?";
        
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
}
