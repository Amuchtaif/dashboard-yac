<?php
// app/Services/Activity/StudentActivityService.php

class StudentActivityService {
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

    public function createActivity($data, $created_by) {
        $activity_type_id = isset($data['activity_type_id']) ? (int)$data['activity_type_id'] : 0;
        $student_id = isset($data['student_id']) ? (int)$data['student_id'] : 0;
        $activity_date = isset($data['activity_date']) ? trim($data['activity_date']) : '';
        $start_time = !empty($data['start_time']) ? trim($data['start_time']) : null;
        $end_time = !empty($data['end_time']) ? trim($data['end_time']) : null;
        $status = isset($data['status']) ? trim($data['status']) : 'completed';
        $note = isset($data['note']) ? trim($data['note']) : null;
        $created_by = (int)$created_by;

        if ($activity_type_id <= 0) throw new Exception("Jenis aktivitas wajib diisi.");
        if ($student_id <= 0) throw new Exception("Santri wajib diisi.");
        if (empty($activity_date)) throw new Exception("Tanggal wajib diisi.");
        if (!in_array($status, ['completed', 'not_completed', 'excused'])) {
            throw new Exception("Status tidak valid. Harus completed, not_completed, atau excused.");
        }

        // Validate active activity type
        $stmt = $this->mysqli->prepare("SELECT id FROM activity_types WHERE id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param("i", $activity_type_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception("Jenis aktivitas tidak aktif atau tidak ditemukan.");
        }
        $stmt->close();

        // Validate student exists
        $stmt = $this->mysqli->prepare("SELECT id FROM students WHERE id = ? AND status = 'Aktif' LIMIT 1");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception("Santri tidak aktif atau tidak ditemukan.");
        }
        $stmt->close();

        $stmt = $this->mysqli->prepare("INSERT INTO student_activities 
            (activity_type_id, student_id, activity_date, start_time, end_time, status, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssssi", $activity_type_id, $student_id, $activity_date, $start_time, $end_time, $status, $note, $created_by);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan aktivitas santri: " . $stmt->error);
        }
        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    public function updateActivity($id, $data, $updated_by) {
        $id = (int)$id;
        $updated_by = (int)$updated_by;

        // Fetch existing
        $stmt = $this->mysqli->prepare("SELECT * FROM student_activities WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            throw new Exception("Aktivitas tidak ditemukan.");
        }

        $activity_type_id = isset($data['activity_type_id']) ? (int)$data['activity_type_id'] : (int)$existing['activity_type_id'];
        $student_id = isset($data['student_id']) ? (int)$data['student_id'] : (int)$existing['student_id'];
        $activity_date = isset($data['activity_date']) ? trim($data['activity_date']) : $existing['activity_date'];
        $start_time = isset($data['start_time']) ? trim($data['start_time']) : $existing['start_time'];
        $end_time = isset($data['end_time']) ? trim($data['end_time']) : $existing['end_time'];
        $status = isset($data['status']) ? trim($data['status']) : $existing['status'];
        $note = isset($data['note']) ? trim($data['note']) : $existing['note'];

        if ($activity_type_id <= 0) throw new Exception("Jenis aktivitas wajib diisi.");
        if ($student_id <= 0) throw new Exception("Santri wajib diisi.");
        if (empty($activity_date)) throw new Exception("Tanggal wajib diisi.");
        if (!in_array($status, ['completed', 'not_completed', 'excused'])) {
            throw new Exception("Status tidak valid.");
        }

        $stmt = $this->mysqli->prepare("UPDATE student_activities SET 
            activity_type_id = ?, student_id = ?, activity_date = ?, start_time = ?, end_time = ?, status = ?, note = ?, updated_by = ?
            WHERE id = ?");
        $stmt->bind_param("iisssssii", $activity_type_id, $student_id, $activity_date, $start_time, $end_time, $status, $note, $updated_by, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal memperbarui aktivitas: " . $stmt->error);
        }
        $stmt->close();

        return true;
    }

    public function deleteActivity($id) {
        $id = (int)$id;
        $stmt = $this->mysqli->prepare("UPDATE student_activities SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Gagal menghapus aktivitas: " . $stmt->error);
        }
        $stmt->close();
        return true;
    }

    public function getActivity($id) {
        $id = (int)$id;
        $stmt = $this->mysqli->prepare("
            SELECT a.*, 
                   s.nama_siswa as student_name, s.kelas as student_class, s.tingkat as student_unit,
                   t.name as activity_name, t.type as activity_type, t.icon, t.color, t.point,
                   e.full_name as creator_name
            FROM student_activities a
            JOIN students s ON a.student_id = s.id
            JOIN activity_types t ON a.activity_type_id = t.id
            LEFT JOIN employees e ON a.created_by = e.id
            WHERE a.id = ? AND a.deleted_at IS NULL LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res) {
            $res['attachments'] = $this->getAttachments($id);
        }

        return $res;
    }

    public function getAttachments($activity_id) {
        $activity_id = (int)$activity_id;
        $stmt = $this->mysqli->prepare("SELECT * FROM activity_files WHERE activity_id = ? ORDER BY id ASC");
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $files = [];
        while ($row = $result->fetch_assoc()) {
            $files[] = $row;
        }
        $stmt->close();
        return $files;
    }

    public function listActivities($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $where = "WHERE a.deleted_at IS NULL";
        $params = [];
        $types = "";

        if (isset($filters['student_id']) && $filters['student_id'] > 0) {
            $where .= " AND a.student_id = ?";
            $params[] = (int)$filters['student_id'];
            $types .= "i";
        }
        if (isset($filters['activity_type_id']) && $filters['activity_type_id'] > 0) {
            $where .= " AND a.activity_type_id = ?";
            $params[] = (int)$filters['activity_type_id'];
            $types .= "i";
        }
        if (isset($filters['status']) && !empty($filters['status'])) {
            $where .= " AND a.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        if (isset($filters['created_by']) && $filters['created_by'] > 0) {
            $where .= " AND a.created_by = ?";
            $params[] = (int)$filters['created_by'];
            $types .= "i";
        }
        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $where .= " AND a.activity_date >= ?";
            $params[] = $filters['start_date'];
            $types .= "s";
        }
        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $where .= " AND a.activity_date <= ?";
            $params[] = $filters['end_date'];
            $types .= "s";
        }
        if (isset($filters['class']) && !empty($filters['class'])) {
            $where .= " AND s.kelas = ?";
            $params[] = $filters['class'];
            $types .= "s";
        }
        if (isset($filters['unit']) && !empty($filters['unit'])) {
            $where .= " AND s.tingkat = ?";
            $params[] = $filters['unit'];
            $types .= "s";
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $where .= " AND (s.nama_siswa LIKE ? OR a.note LIKE ?)";
            $search_param = "%" . $filters['search'] . "%";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "ss";
        }

        // Count total
        $count_query = "SELECT COUNT(*) FROM student_activities a JOIN students s ON a.student_id = s.id $where";
        $stmt = $this->mysqli->prepare($count_query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        // Fetch data
        $query = "
            SELECT a.*, 
                   s.nama_siswa as student_name, s.kelas as student_class, s.tingkat as student_unit,
                   t.name as activity_name, t.type as activity_type, t.icon, t.color, t.point,
                   e.full_name as creator_name
            FROM student_activities a
            JOIN students s ON a.student_id = s.id
            JOIN activity_types t ON a.activity_type_id = t.id
            LEFT JOIN employees e ON a.created_by = e.id
            $where 
            ORDER BY a.activity_date DESC, a.created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
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
            $row['attachments'] = $this->getAttachments($row['id']);
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

    public function getStudentsForUser($user_id) {
        $user_id = (int)$user_id;

        // 1. Check if user is admin / has global permission
        $stmt = $this->mysqli->prepare("SELECT p.name as position_name, p.can_manage_amaliyah 
            FROM employees e 
            LEFT JOIN positions p ON e.position_id = p.id 
            WHERE e.id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $is_admin = false;
        if ($user_info) {
            if ($user_info['position_name'] === 'Administrator' || $user_info['can_manage_amaliyah']) {
                $is_admin = true;
            }
        }

        if ($is_admin) {
            // Admin can access all active students
            $res = $this->mysqli->query("SELECT id, nama_siswa as full_name, kelas, tingkat FROM students WHERE status = 'Aktif' ORDER BY nama_siswa ASC");
            $students = [];
            while ($row = $res->fetch_assoc()) {
                $students[] = $row;
            }
            return $students;
        }

        $student_ids = [];
        $classes = [];

        // Source A: Homeroom (Wali Kelas)
        $stmt = $this->mysqli->prepare("SELECT name FROM grade_levels WHERE teacher_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $classes[] = $row['name'];
        }
        $stmt->close();

        // Source B: Teacher (class schedules)
        $stmt = $this->mysqli->prepare("SELECT DISTINCT gl.name 
            FROM class_schedules cs
            JOIN grade_levels gl ON cs.grade_level_id = gl.id
            WHERE cs.employee_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $classes[] = $row['name'];
        }
        $stmt->close();

        $classes = array_unique($classes);

        // Fetch students in these classes
        if (!empty($classes)) {
            $placeholders = implode(',', array_fill(0, count($classes), '?'));
            $stmt = $this->mysqli->prepare("SELECT id FROM students WHERE status = 'Aktif' AND kelas IN ($placeholders)");
            $stmt->bind_param(str_repeat('s', count($classes)), ...$classes);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $student_ids[] = (int)$row['id'];
            }
            $stmt->close();
        }

        // Source C: Tahfidz Halaqah
        $stmt = $this->mysqli->prepare("SELECT hm.student_id 
            FROM halaqah_members hm
            JOIN halaqah_groups hg ON hm.group_id = hg.id
            WHERE hg.teacher_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $student_ids[] = (int)$row['student_id'];
        }
        $stmt->close();

        // Source D: Boarding
        $stmt = $this->mysqli->prepare("SELECT brm.student_id 
            FROM boarding_room_members brm
            JOIN boarding_rooms br ON brm.room_id = br.id
            LEFT JOIN boarding_room_supervisors brs ON brs.room_id = br.id
            WHERE br.supervisor_id = ? OR brs.supervisor_id = ?");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $student_ids[] = (int)$row['student_id'];
        }
        $stmt->close();

        $student_ids = array_unique($student_ids);

        if (empty($student_ids)) {
            $res = $this->mysqli->query("SELECT id, nama_siswa as full_name, kelas, tingkat FROM students WHERE status = 'Aktif' OR status LIKE 'Aktif%' ORDER BY nama_siswa ASC");
            $students = [];
            while ($row = $res->fetch_assoc()) {
                $students[] = $row;
            }
            return $students;
        }

        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $stmt = $this->mysqli->prepare("SELECT id, nama_siswa as full_name, kelas, tingkat FROM students WHERE status = 'Aktif' AND id IN ($placeholders) ORDER BY nama_siswa ASC");
        $stmt->bind_param(str_repeat('i', count($student_ids)), ...$student_ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $students = [];
        while ($row = $res->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();

        return $students;
    }

    public function addAttachment($activity_id, $file, $caption, $uploaded_by) {
        $activity_id = (int)$activity_id;
        $uploaded_by = (int)$uploaded_by;

        $target_dir = __DIR__ . '/../../../uploads/activities/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($file['name']);
        $target_file = $target_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (!move_uploaded_file($file['tmp_name'], $target_file)) {
            throw new Exception("Gagal mengupload file.");
        }

        $relative_path = 'uploads/activities/' . $file_name;

        $stmt = $this->mysqli->prepare("INSERT INTO activity_files (activity_id, file_path, file_type, caption, uploaded_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $activity_id, $relative_path, $file_type, $caption, $uploaded_by);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan metadata file: " . $stmt->error);
        }
        $id = $stmt->insert_id;
        $stmt->close();

        return [
            'id' => $id,
            'file_path' => $relative_path,
            'file_type' => $file_type,
            'caption' => $caption
        ];
    }

    public function deleteAttachment($activity_id, $attachment_id) {
        $activity_id = (int)$activity_id;
        $attachment_id = (int)$attachment_id;

        // Fetch attachment details
        $stmt = $this->mysqli->prepare("SELECT * FROM activity_files WHERE id = ? AND activity_id = ? LIMIT 1");
        $stmt->bind_param("ii", $attachment_id, $activity_id);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$file) {
            throw new Exception("Dokumentasi tidak ditemukan.");
        }

        // Delete physical file
        $physical_path = __DIR__ . '/../../../' . $file['file_path'];
        if (file_exists($physical_path)) {
            unlink($physical_path);
        }

        // Delete from database
        $stmt = $this->mysqli->prepare("DELETE FROM activity_files WHERE id = ?");
        $stmt->bind_param("i", $attachment_id);
        $stmt->execute();
        $stmt->close();

        return true;
    }

    public function getDashboardStats($filters = []) {
        $where = "WHERE a.deleted_at IS NULL";
        $params = [];
        $types = "";

        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $where .= " AND a.activity_date >= ?";
            $params[] = $filters['start_date'];
            $types .= "s";
        }
        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $where .= " AND a.activity_date <= ?";
            $params[] = $filters['end_date'];
            $types .= "s";
        }

        // Total Hari ini
        $today_where = $where . " AND a.activity_date = CURDATE()";
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM student_activities a $today_where");
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total_today = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        // Total Bulan ini
        $month_where = $where . " AND MONTH(a.activity_date) = MONTH(CURDATE()) AND YEAR(a.activity_date) = YEAR(CURDATE())";
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM student_activities a $month_where");
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total_month = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        // Personal vs Event
        $stmt = $this->mysqli->prepare("
            SELECT t.type, COUNT(*) as count 
            FROM student_activities a 
            JOIN activity_types t ON a.activity_type_id = t.id 
            $where 
            GROUP BY t.type
        ");
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $personal_vs_event = ['personal' => 0, 'event' => 0];
        while ($row = $res->fetch_assoc()) {
            $personal_vs_event[$row['type']] = (int)$row['count'];
        }
        $stmt->close();

        // Aktivitas Terbanyak (Top 5)
        $stmt = $this->mysqli->prepare("
            SELECT t.name, t.color, COUNT(*) as count 
            FROM student_activities a 
            JOIN activity_types t ON a.activity_type_id = t.id 
            $where 
            GROUP BY a.activity_type_id 
            ORDER BY count DESC 
            LIMIT 5
        ");
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $top_activities = [];
        while ($row = $res->fetch_assoc()) {
            $top_activities[] = [
                'name' => $row['name'],
                'color' => $row['color'],
                'count' => (int)$row['count']
            ];
        }
        $stmt->close();

        // Tren Bulanan (Last 6 Months)
        $stmt = $this->mysqli->prepare("
            SELECT DATE_FORMAT(a.activity_date, '%Y-%m') as month, COUNT(*) as count 
            FROM student_activities a 
            WHERE a.deleted_at IS NULL AND a.activity_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY month 
            ORDER BY month ASC
        ");
        $stmt->execute();
        $res = $stmt->get_result();
        $monthly_trend = [];
        while ($row = $res->fetch_assoc()) {
            $monthly_trend[] = [
                'month' => date('M Y', strtotime($row['month'] . '-01')),
                'count' => (int)$row['count']
            ];
        }
        $stmt->close();

        return [
            'total_today' => $total_today,
            'total_month' => $total_month,
            'personal_vs_event' => $personal_vs_event,
            'top_activities' => $top_activities,
            'monthly_trend' => $monthly_trend
        ];
    }
}
