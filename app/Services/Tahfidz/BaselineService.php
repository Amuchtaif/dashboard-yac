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

    public function getActiveAcademicYear() {
        $res = $this->mysqli->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            return $row;
        }
        return null;
    }

    public function createBaseline($data) {
        $academic_year_id = isset($data['academic_year_id']) ? (int)$data['academic_year_id'] : (isset($data['tahun_ajaran_id']) ? (int)$data['tahun_ajaran_id'] : 0);
        $student_id = isset($data['student_id']) ? (int)$data['student_id'] : (isset($data['santri_id']) ? (int)$data['santri_id'] : (isset($data['id_siswa']) ? (int)$data['id_siswa'] : 0));
        $baseline_juz = isset($data['baseline_juz']) ? (float)$data['baseline_juz'] : (isset($data['baseline']) ? (float)$data['baseline'] : (isset($data['juz']) ? (float)$data['juz'] : 0.0));
        $notes = isset($data['notes']) ? $data['notes'] : (isset($data['keterangan']) ? $data['keterangan'] : '');

        // Auto-detect active Academic Year if not explicitly provided
        if ($academic_year_id <= 0) {
            $stmtAY = $this->mysqli->prepare("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
            if ($stmtAY) {
                $stmtAY->execute();
                $ayRes = $stmtAY->get_result()->fetch_assoc();
                $stmtAY->close();
                if ($ayRes) {
                    $academic_year_id = (int)$ayRes['id'];
                }
            }
        }

        // Validation
        if ($student_id <= 0) {
            throw new Exception("Siswa (student_id) wajib dipilih.");
        }
        if ($academic_year_id <= 0) {
            throw new Exception("Tahun Akademik aktif tidak ditemukan.");
        }
        if ($baseline_juz < 0) {
            throw new Exception("Baseline Juz tidak boleh bernilai negatif.");
        }

        // Check if Student exists
        $stmt = $this->mysqli->prepare("SELECT id, status FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $st = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$st) {
            throw new Exception("Siswa dengan ID $student_id tidak ditemukan di database.");
        }

        // Check for duplicate baseline -> UPSERT if duplicate exists
        $stmt = $this->mysqli->prepare("SELECT id FROM memorization_baselines WHERE academic_year_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $academic_year_id, $student_id);
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($dup) {
            // Update existing baseline instead of failing
            $this->updateBaseline($dup['id'], [
                'baseline_juz' => $baseline_juz,
                'notes' => $notes
            ]);
            return $dup['id'];
        }

        // Insert new baseline
        $stmt = $this->mysqli->prepare("INSERT INTO memorization_baselines (academic_year_id, student_id, baseline_juz, notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iids", $academic_year_id, $student_id, $baseline_juz, $notes);
        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan baseline: " . $stmt->error);
        }
        $id = $stmt->insert_id;
        $stmt->close();

        // Logging
        $this->logActivity($student_id, "Baseline created with value: $baseline_juz");

        return $id;
    }

    public function updateBaseline($id, $data) {
        $id = (int)$id;
        $baseline_juz = isset($data['baseline_juz']) ? (float)$data['baseline_juz'] : (isset($data['baseline']) ? (float)$data['baseline'] : (isset($data['juz']) ? (float)$data['juz'] : null));
        $notes = isset($data['notes']) ? $data['notes'] : (isset($data['keterangan']) ? $data['keterangan'] : null);

        // Fetch existing
        $stmt = $this->mysqli->prepare("SELECT * FROM memorization_baselines WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            throw new Exception("Data baseline tidak ditemukan.");
        }

        $new_juz = ($baseline_juz !== null) ? $baseline_juz : $existing['baseline_juz'];
        $new_notes = ($notes !== null) ? $notes : $existing['notes'];

        $stmt = $this->mysqli->prepare("UPDATE memorization_baselines SET baseline_juz = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("dsi", $new_juz, $new_notes, $id);
        if (!$stmt->execute()) {
            throw new Exception("Gagal memperbarui baseline: " . $stmt->error);
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

    public function listBaselines($filters = [], $page = 1, $limit = 100) {
        $activeAY = $this->getActiveAcademicYear();
        $academic_year_id = isset($filters['academic_year_id']) && $filters['academic_year_id'] > 0 
                            ? (int)$filters['academic_year_id'] 
                            : ($activeAY ? (int)$activeAY['id'] : 0);

        $teacher_id = isset($filters['teacher_id']) ? (int)$filters['teacher_id'] : (isset($filters['user_id']) ? (int)$filters['user_id'] : 0);
        $group_id = isset($filters['group_id']) ? (int)$filters['group_id'] : 0;
        $search = isset($filters['search']) ? trim($filters['search']) : null;

        // If teacher_id or group_id is specified, list all students assigned to the halaqah group
        if ($teacher_id > 0 || $group_id > 0) {
            $where = [];
            $params = [$academic_year_id, $academic_year_id];
            $types = "ii";

            if ($teacher_id > 0 && $group_id > 0) {
                $where[] = "(hg.teacher_id = ? OR hg.id = ?)";
                $params[] = $teacher_id;
                $params[] = $group_id;
                $types .= "ii";
            } elseif ($teacher_id > 0) {
                $where[] = "hg.teacher_id = ?";
                $params[] = $teacher_id;
                $types .= "i";
            } else {
                $where[] = "hg.id = ?";
                $params[] = $group_id;
                $types .= "i";
            }

            if (!empty($search)) {
                $where[] = "s.nama_siswa LIKE ?";
                $params[] = "%" . $search . "%";
                $types .= "s";
            }

            $where_sql = implode(" AND ", $where);

            $query = "SELECT 
                        s.id as student_id,
                        s.id as id,
                        s.nama_siswa as full_name,
                        s.nama_siswa as student_name,
                        s.nama_siswa as nama_siswa,
                        s.nomor_induk as nis,
                        gl.name as kelas,
                        s.tingkat,
                        hg.group_name as halaqah_name,
                        mb.id as baseline_id,
                        COALESCE(mb.baseline_juz, 
                                 (SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id ORDER BY id DESC LIMIT 1), 
                                 0.0) as baseline_juz,
                        COALESCE(mb.notes, '') as notes,
                        COALESCE(ay.name, '2026/2027') as academic_year_name
                      FROM halaqah_members hm
                      JOIN halaqah_groups hg ON hm.group_id = hg.id
                      JOIN students s ON hm.student_id = s.id
                      LEFT JOIN memorization_baselines mb ON s.id = mb.student_id AND mb.academic_year_id = ?
                      LEFT JOIN academic_years ay ON mb.academic_year_id = ay.id
                      LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                      LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                      WHERE $where_sql
                      ORDER BY s.nama_siswa ASC";

            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();

            $data = [];
            $filled_count = 0;

            while ($row = $res->fetch_assoc()) {
                $baselineVal = (float)$row['baseline_juz'];
                $isFilled = ($row['baseline_id'] !== null || $baselineVal > 0);
                if ($isFilled) $filled_count++;

                $data[] = [
                    'id' => (int)$row['id'],
                    'student_id' => (int)$row['student_id'],
                    'baseline_id' => $row['baseline_id'] ? (int)$row['baseline_id'] : null,
                    'full_name' => $row['full_name'],
                    'student_name' => $row['student_name'],
                    'nama_siswa' => $row['nama_siswa'],
                    'nis' => $row['nis'] ?: '-',
                    'kelas' => $row['kelas'] ?: '-',
                    'tingkat' => $row['tingkat'],
                    'halaqah_name' => $row['halaqah_name'],
                    'academic_year_name' => $row['academic_year_name'],
                    'baseline_juz' => $baselineVal,
                    'baseline' => $baselineVal,
                    'initial_juz' => $baselineVal,
                    'juz' => $baselineVal,
                    'is_filled' => $isFilled,
                    'is_set' => $isFilled,
                    'has_baseline' => $isFilled,
                    'status' => $isFilled ? 'Sudah Diisi' : 'Belum Diisi',
                    'baseline_status' => $isFilled ? 'Sudah Diisi' : 'Belum Diisi',
                    'status_text' => $isFilled ? 'Sudah Diisi' : 'Belum Diisi',
                    'notes' => $row['notes']
                ];
            }
            $stmt->close();

            $total = count($data);
            return [
                'success' => true,
                'academic_year' => $activeAY ? $activeAY['name'] : '2026/2027',
                'academic_year_id' => $academic_year_id,
                'total_students' => $total,
                'filled_count' => $filled_count,
                'progress_pengisian' => "$filled_count dari $total Santri",
                'progress_text' => "$filled_count dari $total Santri",
                'total' => $total,
                'pages' => 1,
                'page' => 1,
                'limit' => $limit,
                'data' => $data
            ];
        } else {
            // General listing for web/mobile
            $offset = ($page - 1) * $limit;
            $where = "WHERE s.status = 'Aktif'";
            $params = [];
            $types = "";

            if ($academic_year_id > 0) {
                $where .= " AND (b.academic_year_id = ? OR b.academic_year_id IS NULL)";
                $params[] = $academic_year_id;
                $types .= "i";
            }
            if ($search) {
                $where .= " AND s.nama_siswa LIKE ?";
                $params[] = "%" . $search . "%";
                $types .= "s";
            }

            $count_query = "SELECT COUNT(DISTINCT s.id) FROM students s LEFT JOIN memorization_baselines b ON s.id = b.student_id $where";
            $stmt = $this->mysqli->prepare($count_query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $total = $stmt->get_result()->fetch_row()[0];
            $stmt->close();

            $query = "SELECT s.id as student_id, s.id, s.nama_siswa as student_name, s.nama_siswa as full_name, s.nomor_induk as nis,
                             gl.name as kelas, s.tingkat,
                             b.id as baseline_id,
                             COALESCE(b.baseline_juz, 0.0) as baseline_juz,
                             COALESCE(b.notes, '') as notes,
                             COALESCE(ay.name, '2026/2027') as academic_year_name
                      FROM students s
                      LEFT JOIN memorization_baselines b ON s.id = b.student_id " . ($academic_year_id > 0 ? "AND b.academic_year_id = $academic_year_id" : "") . "
                      LEFT JOIN academic_years ay ON b.academic_year_id = ay.id
                      LEFT JOIN student_class_history sch ON s.id = sch.student_id " . ($academic_year_id > 0 ? "AND sch.academic_year_id = $academic_year_id" : "") . "
                      LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                      $where
                      ORDER BY b.created_at DESC, s.nama_siswa ASC
                      LIMIT ? OFFSET ?";

            $fetch_params = $params;
            $fetch_params[] = $limit;
            $fetch_params[] = $offset;
            $fetch_types = $types . "ii";

            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param($fetch_types, ...$fetch_params);
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            $filled_count = 0;

            while ($row = $result->fetch_assoc()) {
                $baselineVal = (float)$row['baseline_juz'];
                $isFilled = ($row['baseline_id'] !== null || $baselineVal > 0);
                if ($isFilled) $filled_count++;

                $data[] = [
                    'id' => $row['baseline_id'] ? (int)$row['baseline_id'] : (int)$row['id'],
                    'student_id' => (int)$row['student_id'],
                    'full_name' => $row['full_name'],
                    'student_name' => $row['student_name'],
                    'nama_siswa' => $row['student_name'],
                    'nis' => $row['nis'] ?: '-',
                    'kelas' => $row['kelas'] ?: '-',
                    'tingkat' => $row['tingkat'],
                    'academic_year_name' => $row['academic_year_name'],
                    'baseline_juz' => $baselineVal,
                    'baseline' => $baselineVal,
                    'initial_juz' => $baselineVal,
                    'juz' => $baselineVal,
                    'is_filled' => $isFilled,
                    'is_set' => $isFilled,
                    'has_baseline' => $isFilled,
                    'status' => $isFilled ? 'Sudah Diisi' : 'Belum Diisi',
                    'baseline_status' => $isFilled ? 'Sudah Diisi' : 'Belum Diisi',
                    'status_text' => $isFilled ? 'Sudah Diisi' : 'Belum Diisi',
                    'notes' => $row['notes']
                ];
            }
            $stmt->close();

            return [
                'success' => true,
                'academic_year' => $activeAY ? $activeAY['name'] : '2026/2027',
                'academic_year_id' => $academic_year_id,
                'total_students' => $total,
                'filled_count' => $filled_count,
                'progress_pengisian' => "$filled_count dari $total Santri",
                'progress_text' => "$filled_count dari $total Santri",
                'total' => $total,
                'pages' => ceil($total / $limit),
                'page' => $page,
                'limit' => $limit,
                'data' => $data
            ];
        }
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
