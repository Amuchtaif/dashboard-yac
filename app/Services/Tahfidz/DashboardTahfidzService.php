<?php
// app/Services/Tahfidz/DashboardTahfidzService.php

class DashboardTahfidzService {
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

    // Helper: get active academic year
    public function getActiveAcademicYear() {
        $res = $this->mysqli->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            return $row;
        }
        return null;
    }

    // 1. Resolve Scope based on employee ID and position role
    public function resolveScope($user_id) {
        $user_id = (int)$user_id;
        
        $stmt = $this->mysqli->prepare("
            SELECT e.position_id, e.unit_id, p.name as position_name, p.level 
            FROM employees e 
            LEFT JOIN positions p ON e.position_id = p.id 
            WHERE e.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$user_info) {
            return [
                'role' => 'restricted',
                'units' => [],
                'halaqahs' => []
            ];
        }
        
        $position_id = isset($user_info['position_id']) ? (int)$user_info['position_id'] : 0;
        $role_name = strtolower($user_info['position_name'] ?? '');
        $level = isset($user_info['level']) ? (int)$user_info['level'] : 99;
        $unit_id = isset($user_info['unit_id']) ? (int)$user_info['unit_id'] : 0;

        // Priority 1: Employee-specific override (user_tahfidz_units)
        $user_units = [];
        $u_stmt = $this->mysqli->prepare("SELECT unit_name FROM user_tahfidz_units WHERE employee_id = ?");
        if ($u_stmt) {
            $u_stmt->bind_param("i", $user_id);
            $u_stmt->execute();
            $res = $u_stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $user_units[] = strtoupper(trim($r['unit_name']));
            }
            $u_stmt->close();
        }

        if (!empty($user_units)) {
            return [
                'role' => 'custom',
                'units' => array_values(array_unique($user_units)),
                'halaqahs' => []
            ];
        }

        // Priority 2: Position-specific custom configuration (position_tahfidz_units)
        $pos_units = [];
        if ($position_id > 0) {
            $p_stmt = $this->mysqli->prepare("SELECT unit_name FROM position_tahfidz_units WHERE position_id = ?");
            if ($p_stmt) {
                $p_stmt->bind_param("i", $position_id);
                $p_stmt->execute();
                $res = $p_stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $pos_units[] = strtoupper(trim($r['unit_name']));
                }
                $p_stmt->close();
            }
        }

        if (!empty($pos_units)) {
            return [
                'role' => 'custom',
                'units' => array_values(array_unique($pos_units)),
                'halaqahs' => []
            ];
        }

        // Fallback to role level defaults
        $unit_name = '';
        if ($unit_id > 0) {
            $stmt = $this->mysqli->prepare("SELECT name FROM units WHERE id = ?");
            $stmt->bind_param("i", $unit_id);
            $stmt->execute();
            $unit_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            // Normalisasi unit name: uppercase and strip whitespace & quotes to prevent matching bugs
            $unit_name = strtoupper(str_replace(["'", "`", " "], "", $unit_info['name'] ?? ''));
        }

        // Override for Kepala Unit Ma'had and Koordinator: sees both MA and MTS
        $is_koordinator = (strpos($role_name, 'koordinator') !== false);
        $is_kepala_unit_mahad = (strpos($role_name, 'kepala unit mahad') !== false) || ($level === 3 && strpos($unit_name, 'MAHAD') !== false && strpos($unit_name, 'MAHADALY') === false);

        if ($is_koordinator || $is_kepala_unit_mahad) {
            return [
                'role' => 'mudir',
                'units' => ['MA', 'MTS'],
                'halaqahs' => []
            ];
        }
        
        // Kepala Pondok (Mudir Pondok / Level 1) & Administrator: sees all
        if ($role_name === 'administrator' || $level === 1 || strpos($role_name, 'kepala pondok') !== false) {
            return [
                'role' => 'kepala_pondok',
                'units' => ['MA', 'MTS', 'SDIT', 'MAHAD ALY', 'PLAY GROUP', 'TPA', 'IDAD LUGOH'],
                'halaqahs' => [] // empty means all
            ];
        }
        
        // Mudir, Kamad, Kanit, and other supervisory roles (Level 2 or 3)
        if ($level === 2 || $level === 3) {
            // Check if unit is a specific school unit
            if (strpos($unit_name, 'MTS') !== false) {
                return ['role' => 'kamad', 'units' => ['MTS'], 'halaqahs' => []];
            } else if (strpos($unit_name, 'MAHADALY') !== false) {
                return ['role' => 'kamad', 'units' => ['MAHAD ALY'], 'halaqahs' => []];
            } else if (strpos($unit_name, 'MA') !== false && strpos($unit_name, 'MAHAD') === false) {
                return ['role' => 'kamad', 'units' => ['MA'], 'halaqahs' => []];
            } else if (strpos($unit_name, 'SDIT') !== false) {
                return ['role' => 'kamad', 'units' => ['SDIT'], 'halaqahs' => []];
            } else if (strpos($unit_name, 'TKIT') !== false) {
                return ['role' => 'kamad', 'units' => ['TKIT'], 'halaqahs' => []];
            } else {
                // If it is a global management unit like Sub. Kurikulum, let them see MTs and MA (Mudir scope)
                return [
                    'role' => 'mudir',
                    'units' => ['MA', 'MTS'],
                    'halaqahs' => []
                ];
            }
        }
        
        // Kanit Tahfidz / Koordinator level 4 (who has unit set to MTs or MA)
        if ($level === 4 && (strpos($role_name, 'kanit') !== false || strpos($role_name, 'koordinator') !== false)) {
            $target_units = [];
            if (strpos($unit_name, 'MTS') !== false) {
                $target_units = ['MTS'];
            } else if (strpos($unit_name, 'MAHADALY') !== false) {
                $target_units = ['MAHAD ALY'];
            } else if (strpos($unit_name, 'MA') !== false && strpos($unit_name, 'MAHAD') === false) {
                $target_units = ['MA'];
            } else if (strpos($unit_name, 'SDIT') !== false) {
                $target_units = ['SDIT'];
            } else if (strpos($unit_name, 'TKIT') !== false) {
                $target_units = ['TKIT'];
            }
            
            if (!empty($target_units)) {
                return [
                    'role' => 'kanit',
                    'units' => $target_units,
                    'halaqahs' => [] // empty means all halaqahs under these units
                ];
            }
        }
        
        // Fallback for regular teachers: only see their own halaqah!
        $stmt = $this->mysqli->prepare("SELECT id FROM halaqah_groups WHERE teacher_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $halaqah_ids = [];
        while ($row = $res->fetch_assoc()) {
            $halaqah_ids[] = (int)$row['id'];
        }
        $stmt->close();
        
        return [
            'role' => 'teacher',
            'units' => [],
            'halaqahs' => $halaqah_ids
        ];
    }

    // Helper: build scope and filter queries
    private function buildScopeAndFilters($scope, $filters) {
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;

        $where = ["s.tingkat != 'TKIT'"];
        $params = [];
        $types = "";

        // Dynamic active academic year scope constraint
        if ($ay_id > 0) {
            $where[] = "s.id IN (
                SELECT sch_sub.student_id 
                FROM student_class_history sch_sub 
                WHERE sch_sub.academic_year_id = ? AND sch_sub.status = 'ACTIVE'
            )";
            $params[] = $ay_id;
            $types .= "i";
        }
        
        // Apply scope restrictions
        if (!empty($scope['units'])) {
            $placeholders = implode(',', array_fill(0, count($scope['units']), '?'));
            $where[] = "s.tingkat IN ($placeholders)";
            foreach ($scope['units'] as $u) {
                $params[] = $u;
                $types .= "s";
            }
        }
        
        if (!empty($scope['halaqahs'])) {
            $placeholders = implode(',', array_fill(0, count($scope['halaqahs']), '?'));
            $where[] = "s.id IN (SELECT student_id FROM halaqah_members WHERE group_id IN ($placeholders))";
            foreach ($scope['halaqahs'] as $h) {
                $params[] = $h;
                $types .= "i";
            }
        }
        
        // Apply incoming filters
        if (!empty($filters['unit'])) {
            $where[] = "s.tingkat = ?";
            $params[] = $filters['unit'];
            $types .= "s";
        }
        
        if (!empty($filters['kelas'])) {
            if ($ay_id > 0) {
                $where[] = "s.id IN (
                    SELECT sch_sub.student_id 
                    FROM student_class_history sch_sub 
                    JOIN grade_levels gl_sub ON sch_sub.class_id = gl_sub.id
                    WHERE sch_sub.academic_year_id = ? AND sch_sub.status = 'ACTIVE' AND gl_sub.name = ?
                )";
                $params[] = $ay_id;
                $params[] = $filters['kelas'];
                $types .= "is";
            } else {
                $where[] = "s.kelas = ?";
                $params[] = $filters['kelas'];
                $types .= "s";
            }
        }
        
        if (!empty($filters['halaqah_id'])) {
            $where[] = "s.id IN (SELECT student_id FROM halaqah_members WHERE group_id = ?)";
            $params[] = (int)$filters['halaqah_id'];
            $types .= "i";
        }
        
        if (!empty($filters['pengampu_id'])) {
            $where[] = "s.id IN (SELECT hm.student_id FROM halaqah_members hm JOIN halaqah_groups hg ON hm.group_id = hg.id WHERE hg.teacher_id = ?)";
            $params[] = (int)$filters['pengampu_id'];
            $types .= "i";
        }
        
        return [
            'where' => $where,
            'params' => $params,
            'types' => $types
        ];
    }

    // Helper to execute query with parameters safely
    private function queryWithParams($sql, $params, $types) {
        if (empty($params)) {
            return $this->mysqli->query($sql);
        }
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }

    // API 1: Executive Summary
    public function getExecutiveSummary($user_id, $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $where_clause_base = !empty($sf['where']) ? " WHERE " . implode(" AND ", $sf['where']) : "";
        
        // Total active students
        $sql = "SELECT COUNT(*) FROM students s WHERE s.status = 'Aktif'" . $where_clause;
        $res = $this->queryWithParams($sql, $sf['params'], $sf['types']);
        $total_santri = $res ? (int)$res->fetch_row()[0] : 0;
        
        // Total halaqoh
        $sql = "SELECT COUNT(DISTINCT hg.id) 
                FROM halaqah_groups hg 
                JOIN halaqah_members hm ON hm.group_id = hg.id 
                JOIN students s ON hm.student_id = s.id 
                WHERE s.status = 'Aktif'" . $where_clause;
        $res = $this->queryWithParams($sql, $sf['params'], $sf['types']);
        $total_halaqah = $res ? (int)$res->fetch_row()[0] : 0;

        // Total pengampu
        $sql = "SELECT COUNT(DISTINCT hg.teacher_id) 
                FROM halaqah_groups hg 
                JOIN halaqah_members hm ON hm.group_id = hg.id 
                JOIN students s ON hm.student_id = s.id 
                WHERE s.status = 'Aktif'" . $where_clause;
        $res = $this->queryWithParams($sql, $sf['params'], $sf['types']);
        $total_pengampu = $res ? (int)$res->fetch_row()[0] : 0;

        // Date filter
        $target_date = isset($filters['date']) ? $filters['date'] : date('Y-m-d');

        // Attendance count for today
        $sql = "SELECT ta.status, COUNT(*) as count 
                FROM tahfidz_attendance ta 
                JOIN students s ON ta.student_id = s.id 
                WHERE ta.date = ?" . $where_clause . " 
                GROUP BY ta.status";
        $params_att = array_merge([$target_date], $sf['params']);
        $types_att = "s" . $sf['types'];
        $res = $this->queryWithParams($sql, $params_att, $types_att);
        $attendance = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpha' => 0];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $status = ucfirst(strtolower($row['status']));
                if (isset($attendance[$status])) {
                    $attendance[$status] = (int)$row['count'];
                }
            }
        }
        $total_attendance_submitted = array_sum($attendance);

        // Teacher attendance
        $sql = "SELECT tta.status, COUNT(DISTINCT tta.teacher_id) as count 
                FROM tahfidz_teacher_attendance tta 
                JOIN halaqah_groups hg ON tta.teacher_id = hg.teacher_id
                JOIN halaqah_members hm ON hm.group_id = hg.id
                JOIN students s ON hm.student_id = s.id
                WHERE tta.date = ?" . $where_clause . " 
                GROUP BY tta.status";
        $res = $this->queryWithParams($sql, $params_att, $types_att);
        $teacher_att = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpha' => 0];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $status = ucfirst(strtolower($row['status']));
                if (isset($teacher_att[$status])) {
                    $teacher_att[$status] = (int)$row['count'];
                }
            }
        }

        // Memorization entries today (Hafalan Baru & Murojaah)
        $sql = "SELECT me.entry_type, COUNT(*) as count 
                FROM memorization_entries me 
                JOIN students s ON me.student_id = s.id 
                WHERE me.date = ?" . $where_clause . " 
                GROUP BY me.entry_type";
        $res = $this->queryWithParams($sql, $params_att, $types_att);
        $entries = ['HAFALAN_BARU' => 0, 'MUROJAAH' => 0];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (isset($entries[$row['entry_type']])) {
                    $entries[$row['entry_type']] = (int)$row['count'];
                }
            }
        }

        // Students who haven't memorized today
        $sql = "SELECT COUNT(*) FROM students s 
                WHERE s.status = 'Aktif' " . $where_clause . " 
                  AND s.id NOT IN (
                      SELECT student_id FROM memorization_entries 
                      WHERE date = ? AND entry_type = 'HAFALAN_BARU'
                  )";
        $params_no_setor = array_merge($sf['params'], [$target_date]);
        $types_no_setor = $sf['types'] . "s";
        $res = $this->queryWithParams($sql, $params_no_setor, $types_no_setor);
        $santri_belum_setor = $res ? (int)$res->fetch_row()[0] : 0;

        // Halaqah without activities today
        $sql = "SELECT COUNT(DISTINCT hg.id) 
                FROM halaqah_groups hg
                JOIN halaqah_members hm ON hm.group_id = hg.id
                JOIN students s ON hm.student_id = s.id
                WHERE s.status = 'Aktif' " . $where_clause . " 
                  AND hg.id NOT IN (
                      SELECT DISTINCT hgm.group_id 
                      FROM halaqah_members hgm 
                      JOIN memorization_entries me ON hgm.student_id = me.student_id 
                      WHERE me.date = ?
                  )";
        $res = $this->queryWithParams($sql, $params_no_setor, $types_no_setor);
        $halaqah_belum_isi = $res ? (int)$res->fetch_row()[0] : 0;

        return [
            'total_santri' => $total_santri,
            'total_pengampu' => $total_pengampu,
            'total_halaqah' => $total_halaqah,
            'kehadiran_santri_hari_ini' => $total_attendance_submitted,
            'kehadiran_santri_detail' => $attendance,
            'kehadiran_pengampu_hari_ini' => array_sum($teacher_att),
            'kehadiran_pengampu_detail' => $teacher_att,
            'total_setoran_hari_ini' => $entries['HAFALAN_BARU'],
            'total_murajaah_hari_ini' => $entries['MUROJAAH'],
            'santri_belum_setor' => $santri_belum_setor,
            'halaqah_belum_aktif' => $halaqah_belum_isi
        ];
    }

    // API 2: Dashboard Kehadiran
    public function getAttendanceDashboard($user_id, $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $target_date = isset($filters['date']) ? $filters['date'] : date('Y-m-d');
        
        // 1. Santri Attendance Breakdown
        $sql = "SELECT ta.status, COUNT(*) as count 
                FROM tahfidz_attendance ta 
                JOIN students s ON ta.student_id = s.id 
                WHERE ta.date = ?" . $where_clause . " 
                GROUP BY ta.status";
        $params_att = array_merge([$target_date], $sf['params']);
        $types_att = "s" . $sf['types'];
        $res = $this->queryWithParams($sql, $params_att, $types_att);
        
        $santri = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alfa' => 0];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $status = ucfirst(strtolower($row['status']));
                if ($status === 'Alpha') $status = 'Alfa';
                if (isset($santri[$status])) {
                    $santri[$status] = (int)$row['count'];
                }
            }
        }

        // 2. Pengampu Attendance Breakdown
        $sql = "SELECT tta.status, COUNT(DISTINCT tta.teacher_id) as count 
                FROM tahfidz_teacher_attendance tta 
                JOIN halaqah_groups hg ON tta.teacher_id = hg.teacher_id
                JOIN halaqah_members hm ON hm.group_id = hg.id
                JOIN students s ON hm.student_id = s.id
                WHERE tta.date = ?" . $where_clause . " 
                GROUP BY tta.status";
        $res = $this->queryWithParams($sql, $params_att, $types_att);
        
        $pengampu = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Tidak Hadir' => 0, 'Belum Absen' => 0];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $status = ucfirst(strtolower($row['status']));
                if ($status === 'Alpha' || $status === 'Alfa') $status = 'Tidak Hadir';
                if (isset($pengampu[$status])) {
                    $pengampu[$status] = (int)$row['count'];
                }
            }
        }
        
        // Calculate Total Active Teachers under this scope
        $sql = "SELECT COUNT(DISTINCT hg.teacher_id) 
                FROM halaqah_groups hg 
                JOIN halaqah_members hm ON hm.group_id = hg.id 
                JOIN students s ON hm.student_id = s.id 
                WHERE s.status = 'Aktif'" . $where_clause;
        $res = $this->queryWithParams($sql, $sf['params'], $sf['types']);
        $total_teachers = $res ? (int)$res->fetch_row()[0] : 0;
        
        $submitted_teachers = $pengampu['Hadir'] + $pengampu['Izin'] + $pengampu['Sakit'] + $pengampu['Tidak Hadir'];
        $pengampu['Belum Absen'] = max(0, $total_teachers - $submitted_teachers);

        return [
            'date' => $target_date,
            'santri' => $santri,
            'pengampu' => $pengampu
        ];
    }

    // API 3: Live Activity
    public function getLiveActivity($user_id, $filters = [], $limit = 15, $page = 1) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $offset = ($page - 1) * $limit;

        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;
        
        $sql = "SELECT 
                    me.id,
                    me.student_id,
                    s.nama_siswa as student_name,
                    COALESCE(gl.name, s.kelas) as student_class,
                    me.date,
                    me.entry_type,
                    me.start_surah_id,
                    me.start_ayah,
                    me.end_surah_id,
                    me.end_ayah,
                    me.line_count,
                    me.juz,
                    me.surah_id,
                    me.notes,
                    me.created_at,
                    e.full_name as teacher_name,
                    sur.name_latin as surah_name,
                    sur_start.name_latin as start_surah_name,
                    sur_end.name_latin as end_surah_name
                FROM memorization_entries me
                JOIN students s ON me.student_id = s.id
                LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                LEFT JOIN employees e ON me.teacher_id = e.id
                LEFT JOIN surahs sur ON me.surah_id = sur.id
                LEFT JOIN surahs sur_start ON me.start_surah_id = sur_start.id
                LEFT JOIN surahs sur_end ON me.end_surah_id = sur_end.id
                WHERE s.status = 'Aktif'" . $where_clause . " 
                ORDER BY me.created_at DESC, me.id DESC 
                LIMIT ? OFFSET ?";
                
        $params = array_merge([$ay_id], $sf['params'], [(int)$limit, (int)$offset]);
        $types = "i" . $sf['types'] . "ii";
        
        $res = $this->queryWithParams($sql, $params, $types);
        $activities = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $act_type = 'Setoran';
                switch ($row['entry_type']) {
                    case 'HAFALAN_BARU':
                        $act_type = 'Setoran Hafalan Baru';
                        break;
                    case 'MUROJAAH':
                        $act_type = 'Murojaah';
                        break;
                    case 'TASMI':
                        $act_type = 'Tasmi';
                        break;
                    case 'UJIAN':
                        $act_type = 'Ujian Hafalan';
                        break;
                }

                // Format detail surah & ayat
                $surah_display = $row['surah_name'] ?? $row['start_surah_name'] ?? $row['end_surah_name'] ?? '';
                $detail_text = "";
                if (!empty($surah_display)) {
                    $detail_text = "Surah " . $surah_display;
                    if (!empty($row['start_ayah'])) {
                        $detail_text .= ": " . $row['start_ayah'];
                        if (!empty($row['end_ayah']) && $row['end_ayah'] != $row['start_ayah']) {
                            $detail_text .= "-" . $row['end_ayah'];
                        }
                    }
                }
                
                // Format meta (baris & juz)
                $meta_items = [];
                if (!empty($row['line_count']) && (int)$row['line_count'] > 0) {
                    $meta_items[] = (int)$row['line_count'] . " Baris";
                }
                if (!empty($row['juz']) && (int)$row['juz'] > 0) {
                    $meta_items[] = "Juz " . (int)$row['juz'];
                }
                $hafalan_meta = implode(" • ", $meta_items);

                $start_s_name = $row['start_surah_name'] ?? $row['surah_name'] ?? '';
                $end_s_name = $row['end_surah_name'] ?? $row['surah_name'] ?? '';
                $start_a = $row['start_ayah'] ?? null;
                $end_a = $row['end_ayah'] ?? null;

                $activities[] = [
                    'id' => $row['id'],
                    'student_id' => $row['student_id'],
                    'student_name' => $row['student_name'],
                    'student_class' => $row['student_class'],
                    'date' => $row['date'],
                    'activity_name' => $act_type,
                    'teacher_name' => $row['teacher_name'] ?? 'Sistem',
                    'start_surah' => $start_s_name,
                    'start_ayah' => $start_a,
                    'end_surah' => $end_s_name,
                    'end_ayah' => $end_a,
                    'hafalan_detail' => $detail_text,
                    'hafalan_meta' => $hafalan_meta,
                    'notes' => (!empty($notes_clean) && $notes_clean !== '-') ? $notes_clean : null,
                    'timestamp' => $row['created_at']
                ];
            }
        }
        return $activities;
    }

    // API 4: Progress Hafalan
    public function getProgressHafalan($user_id, $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;
        
        $start_date = $ay ? $ay['start_date'] : date('Y-01-01');
        $end_date = $ay ? $ay['end_date'] : date('Y-12-31');

        // Total memorization lines to Juz conversion for active year in selected scope
        $sql = "SELECT 
                    SUM(CASE WHEN me.entry_type = 'HAFALAN_BARU' THEN me.line_count ELSE 0 END) as total_new_lines,
                    COUNT(DISTINCT s.id) as active_count
                FROM students s
                LEFT JOIN memorization_entries me ON me.student_id = s.id AND me.date BETWEEN ? AND ?
                WHERE s.status = 'Aktif'" . $where_clause;
                
        $params = array_merge([$start_date, $end_date], $sf['params']);
        $types = "ss" . $sf['types'];
        
        $res = $this->queryWithParams($sql, $params, $types);
        $row = $res ? $res->fetch_assoc() : null;
        
        $new_lines = $row ? (int)$row['total_new_lines'] : 0;
        $active_count = $row ? (int)$row['active_count'] : 0;
        $total_hafalan_semester = round($new_lines / 300.0, 2);
        
        // Query target_hafalan specific to the selected unit/kelas filter & scope
        $sql_target = "SELECT AVG(t.target_juz) as avg_target
                        FROM students s
                        JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                        JOIN grade_levels gl ON sch.class_id = gl.id
                        LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
                        JOIN target_hafalan t ON (
                            t.kelas_id = gl.id 
                            OR t.unit_id = gl.education_unit_id
                            OR t.kelas_id = (SELECT id FROM grade_levels WHERE name = s.kelas LIMIT 1)
                        ) AND (t.tahun_ajaran_id = ? OR t.tahun_ajaran_id IS NULL OR t.tahun_ajaran_id = 0) AND t.status_aktif = 'Aktif'
                        WHERE s.status = 'Aktif'" . $where_clause;
                        
        $params_target = array_merge([$ay_id, $ay_id], $sf['params']);
        $types_target = "ii" . $sf['types'];
        
        $res_target = $this->queryWithParams($sql_target, $params_target, $types_target);
        $target_row = $res_target ? $res_target->fetch_assoc() : null;
        
        $target_juz = 0.0;
        if ($target_row && !empty($target_row['avg_target']) && (float)$target_row['avg_target'] > 0) {
            $target_juz = round((float)$target_row['avg_target'], 2);
        } else {
            // Fallback query matching target_hafalan by unit_id / unit name if filter has unit
            $unit_filter_where = "";
            $unit_filter_params = [];
            $unit_filter_types = "";
            if (!empty($filters['unit'])) {
                $unit_name = strtoupper(trim($filters['unit']));
                $unit_filter_where = " AND (UPPER(TRIM(eu.name)) = ? OR UPPER(TRIM(eu.code)) = ?)";
                $unit_filter_params = [$unit_name, $unit_name];
                $unit_filter_types = "ss";
            }
            $sql_fallback = "SELECT AVG(t.target_juz) as avg_target 
                             FROM target_hafalan t 
                             LEFT JOIN education_units eu ON t.unit_id = eu.id
                             WHERE t.status_aktif = 'Aktif'" . $unit_filter_where;
            $res_fb = $this->queryWithParams($sql_fallback, $unit_filter_params, $unit_filter_types);
            $fb_row = $res_fb ? $res_fb->fetch_assoc() : null;
            
            if ($fb_row && !empty($fb_row['avg_target']) && (float)$fb_row['avg_target'] > 0) {
                $target_juz = round((float)$fb_row['avg_target'], 2);
            } else {
                $target_juz = 2.0; // Default fallback target per student (2 Juz)
            }
        }
        
        // Progress percentage calculation
        $progress_percentage = 0.00;
        if ($target_juz > 0 && $active_count > 0) {
            $total_target_group = $target_juz * $active_count;
            $progress_percentage = round(($total_hafalan_semester / $total_target_group) * 100.0, 2);
        } else if ($target_juz > 0) {
            $progress_percentage = round(($total_hafalan_semester / $target_juz) * 100.0, 2);
        }

        return [
            'total_hafalan_baru_juz' => $total_hafalan_semester,
            'target_semester_juz' => $target_juz,
            'target_tahunan_juz' => round($target_juz * 2.0, 2),
            'progress_percentage' => $progress_percentage
        ];
    }

    // API 5: Distribusi Hafalan
    public function getDistribusiHafalan($user_id, $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;

        $start_date = $ay ? $ay['start_date'] : date('Y-01-01');

        // Dynamic calculation of total juz per student
        $sql = "SELECT s.id,
                       (COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = ? LIMIT 1), 0.00) + 
                        COALESCE((SELECT SUM(line_count) FROM memorization_entries WHERE student_id = s.id AND entry_type = 'HAFALAN_BARU' AND date >= ?), 0) / 300.0) as total_juz
                FROM students s
                WHERE s.status = 'Aktif'" . $where_clause;
                
        $params = array_merge([$ay_id, $start_date], $sf['params']);
        $types = "is" . $sf['types'];
        
        $res = $this->queryWithParams($sql, $params, $types);
        
        $distribution = [
            'Belum 1 Juz' => 0,
            '1-5 Juz' => 0,
            '6-10 Juz' => 0,
            '11-20 Juz' => 0,
            '21-29 Juz' => 0,
            '30 Juz' => 0
        ];
        
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $j = (float)$row['total_juz'];
                if ($j < 1.0) {
                    $distribution['Belum 1 Juz']++;
                } else if ($j <= 5.0) {
                    $distribution['1-5 Juz']++;
                } else if ($j <= 10.0) {
                    $distribution['6-10 Juz']++;
                } else if ($j <= 20.0) {
                    $distribution['11-20 Juz']++;
                } else if ($j <= 29.0) {
                    $distribution['21-29 Juz']++;
                } else {
                    $distribution['30 Juz']++;
                }
            }
        }
        
        return $distribution;
    }

    // API 6: Monitoring Halaqoh
    public function getMonitoringHalaqoh($user_id, $filters = [], $limit = 10, $page = 1) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $offset = ($page - 1) * $limit;
        
        // Find halaqohs having members matching our scope/filters
        $sql = "SELECT DISTINCT hg.id, hg.group_name, e.full_name as teacher_name
                FROM halaqah_groups hg
                JOIN halaqah_members hm ON hm.group_id = hg.id
                JOIN students s ON hm.student_id = s.id
                LEFT JOIN employees e ON hg.teacher_id = e.id
                WHERE s.status = 'Aktif'" . $where_clause . " 
                ORDER BY LENGTH(hg.group_name) ASC, hg.group_name ASC 
                LIMIT ? OFFSET ?";
                
        $params = array_merge($sf['params'], [(int)$limit, (int)$offset]);
        $types = $sf['types'] . "ii";
        
        $res = $this->queryWithParams($sql, $params, $types);
        $halaqah_list = [];
        
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $group_id = $row['id'];
                
                // Member count
                $stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM halaqah_members hm JOIN students s ON hm.student_id = s.id WHERE hm.group_id = ? AND s.status = 'Aktif'");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $member_count = $stmt->get_result()->fetch_row()[0];
                $stmt->close();
                
                // Attendance percentage (last 30 days)
                $stmt = $this->mysqli->prepare("
                    SELECT 
                        SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as present,
                        COUNT(*) as total
                    FROM tahfidz_attendance ta
                    JOIN halaqah_members hm ON ta.student_id = hm.student_id
                    WHERE hm.group_id = ? AND ta.date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                ");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $att_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $present = $att_row['present'] ?? 0;
                $total_att = $att_row['total'] ?? 0;
                $attendance_rate = $total_att > 0 ? round(($present / $total_att) * 100.0, 2) : 100.0;
                
                // Setoran & Murojaah counts (last 30 days)
                $stmt = $this->mysqli->prepare("
                    SELECT 
                        SUM(CASE WHEN me.entry_type = 'HAFALAN_BARU' THEN 1 ELSE 0 END) as setoran_count,
                        SUM(CASE WHEN me.entry_type = 'MUROJAAH' THEN 1 ELSE 0 END) as murojaah_count
                    FROM memorization_entries me
                    JOIN halaqah_members hm ON me.student_id = hm.student_id
                    WHERE hm.group_id = ? AND me.date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                ");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $entries_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $setoran = $entries_row['setoran_count'] ?? 0;
                $murojaah = $entries_row['murojaah_count'] ?? 0;
                
                $halaqah_list[] = [
                    'id' => $group_id,
                    'group_name' => $row['group_name'],
                    'teacher_name' => $row['teacher_name'] ?? 'Belum Ditunjuk',
                    'member_count' => (int)$member_count,
                    'attendance_rate' => $attendance_rate,
                    'setoran_last_30_days' => (int)$setoran,
                    'murojaah_last_30_days' => (int)$murojaah
                ];
            }
        }
        
        return $halaqah_list;
    }

    // API 7: Detail Halaqoh
    public function getDetailHalaqoh($user_id, $halaqah_id, $filters = []) {
        $halaqah_id = (int)$halaqah_id;
        
        // Verify user has access to this halaqah
        $scope = $this->resolveScope($user_id);
        if (!empty($scope['halaqahs']) && !in_array($halaqah_id, $scope['halaqahs'])) {
            throw new Exception("Forbidden: Anda tidak memiliki akses ke halaqoh ini.");
        }
        
        // Halaqah Info
        $stmt = $this->mysqli->prepare("
            SELECT hg.id, hg.group_name, e.full_name as teacher_name, e.phone_number
            FROM halaqah_groups hg
            LEFT JOIN employees e ON hg.teacher_id = e.id
            WHERE hg.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $halaqah_id);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$info) {
            throw new Exception("Halaqah not found.");
        }
        
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;
        $start_date = $ay ? $ay['start_date'] : date('Y-01-01');

        // member_count
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM halaqah_members hm JOIN students s ON hm.student_id = s.id WHERE hm.group_id = ? AND s.status = 'Aktif'");
        $stmt->bind_param("i", $halaqah_id);
        $stmt->execute();
        $member_count = (int)$stmt->get_result()->fetch_row()[0];
        $stmt->close();

        // attendance_rate (last 30 days)
        $stmt = $this->mysqli->prepare("
            SELECT 
                SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as present,
                COUNT(*) as total
            FROM tahfidz_attendance ta
            JOIN halaqah_members hm ON ta.student_id = hm.student_id
            WHERE hm.group_id = ? AND ta.date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
        ");
        $stmt->bind_param("i", $halaqah_id);
        $stmt->execute();
        $att_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $present = $att_row['present'] ?? 0;
        $total_att = $att_row['total'] ?? 0;
        $attendance_rate = $total_att > 0 ? round(($present / $total_att) * 100.0, 2) : 100.0;

        // avg_progress
        $total_juz_sum = 0.00;
        if ($member_count > 0) {
            $stmt = $this->mysqli->prepare("
                SELECT s.id
                FROM halaqah_members hm
                JOIN students s ON hm.student_id = s.id
                WHERE hm.group_id = ? AND s.status = 'Aktif'
            ");
            $stmt->bind_param("i", $halaqah_id);
            $stmt->execute();
            $student_ids_res = $stmt->get_result();
            $student_ids = [];
            while ($row_id = $student_ids_res->fetch_assoc()) {
                $student_ids[] = (int)$row_id['id'];
            }
            $stmt->close();

            foreach ($student_ids as $std_id) {
                // baseline
                $stmt = $this->mysqli->prepare("
                    SELECT baseline_juz FROM memorization_baselines WHERE student_id = ? AND academic_year_id = ? LIMIT 1
                ");
                $stmt->bind_param("ii", $std_id, $ay_id);
                $stmt->execute();
                $baseline_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $baseline_juz = $baseline_row ? (float)$baseline_row['baseline_juz'] : 0.00;

                // sum lines
                $stmt = $this->mysqli->prepare("
                    SELECT SUM(line_count) FROM memorization_entries WHERE student_id = ? AND entry_type = 'HAFALAN_BARU' AND date >= ?
                ");
                $stmt->bind_param("is", $std_id, $start_date);
                $stmt->execute();
                $lines_row = $stmt->get_result()->fetch_row();
                $stmt->close();
                $new_lines = $lines_row ? (int)$lines_row[0] : 0;

                $total_juz_sum += ($baseline_juz + round($new_lines / 300.0, 2));
            }
            $avg_progress = round($total_juz_sum / $member_count, 2);
        } else {
            $avg_progress = 0.00;
        }

        // Add to info array for Flutter compatibility
        $info['name'] = $info['group_name'] ?? 'Halaqah Tahfidz';
        $info['member_count'] = $member_count;
        $info['attendance_rate'] = $attendance_rate;
        $info['avg_progress'] = $avg_progress;

        // Students List
        $stmt = $this->mysqli->prepare("
            SELECT s.id, s.nama_siswa as full_name, COALESCE(gl.name, s.kelas) as kelas, s.tingkat
            FROM halaqah_members hm
            JOIN students s ON hm.student_id = s.id
            LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
            LEFT JOIN grade_levels gl ON sch.class_id = gl.id
            WHERE hm.group_id = ? AND s.status = 'Aktif'
            ORDER BY s.nama_siswa ASC
        ");
        $stmt->bind_param("ii", $ay_id, $halaqah_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $students = [];
        while ($row = $res->fetch_assoc()) {
            $std_id = (int)$row['id'];
            
            // baseline
            $stmt_b = $this->mysqli->prepare("
                SELECT baseline_juz FROM memorization_baselines WHERE student_id = ? AND academic_year_id = ? LIMIT 1
            ");
            $stmt_b->bind_param("ii", $std_id, $ay_id);
            $stmt_b->execute();
            $baseline_row = $stmt_b->get_result()->fetch_assoc();
            $stmt_b->close();
            $baseline_juz = $baseline_row ? (float)$baseline_row['baseline_juz'] : 0.00;

            // sum lines
            $stmt_l = $this->mysqli->prepare("
                SELECT SUM(line_count) FROM memorization_entries WHERE student_id = ? AND entry_type = 'HAFALAN_BARU' AND date >= ?
            ");
            $stmt_l->bind_param("is", $std_id, $start_date);
            $stmt_l->execute();
            $lines_row = $stmt_l->get_result()->fetch_row();
            $stmt_l->close();
            $new_lines = $lines_row ? (int)$lines_row[0] : 0;
            $total_juz = $baseline_juz + round($new_lines / 300.0, 2);

            // last setor date
            $stmt_d = $this->mysqli->prepare("
                SELECT date FROM memorization_entries WHERE student_id = ? AND entry_type = 'HAFALAN_BARU' ORDER BY date DESC, id DESC LIMIT 1
            ");
            $stmt_d->bind_param("i", $std_id);
            $stmt_d->execute();
            $last_mem = $stmt_d->get_result()->fetch_assoc();
            $stmt_d->close();
            $last_setor_date = $last_mem ? $last_mem['date'] : '-';

            $row['baseline_juz'] = $baseline_juz;
            $row['total_juz'] = $total_juz;
            $row['last_setor_date'] = $last_setor_date;

            $students[] = $row;
        }
        $stmt->close();
        
        // Recent Activities in this Halaqah (limit 15)
        $stmt = $this->mysqli->prepare("
            SELECT me.id, s.nama_siswa as student_name, me.date, me.entry_type, me.notes, me.created_at
            FROM memorization_entries me
            JOIN halaqah_members hm ON me.student_id = hm.student_id
            JOIN students s ON hm.student_id = s.id
            WHERE hm.group_id = ?
            ORDER BY me.created_at DESC, me.id DESC
            LIMIT 15
        ");
        $stmt->bind_param("i", $halaqah_id);
        $stmt->execute();
        $res_act = $stmt->get_result();
        $activities = [];
        while ($row = $res_act->fetch_assoc()) {
            $activities[] = $row;
        }
        $stmt->close();

        return [
            'info' => $info,
            'student_count' => count($students),
            'students' => $students,
            'recent_activities' => $activities
        ];
    }

    // API 8: Monitoring Pengampu
    public function getMonitoringPengampu($user_id, $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        
        // Get teachers who lead halaqahs matching our scope/filters
        $sql = "SELECT DISTINCT e.id, e.full_name, hg.id as group_id, hg.group_name
                FROM employees e
                JOIN halaqah_groups hg ON hg.teacher_id = e.id
                JOIN halaqah_members hm ON hm.group_id = hg.id
                JOIN students s ON hm.student_id = s.id
                WHERE s.status = 'Aktif'" . $where_clause . " 
                ORDER BY e.full_name ASC";
                
        $res = $this->queryWithParams($sql, $sf['params'], $sf['types']);
        $teachers = [];
        
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $teacher_id = $row['id'];
                
                // Teacher Attendance rate (last 30 days)
                $stmt = $this->mysqli->prepare("
                    SELECT 
                        SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as present,
                        COUNT(*) as total
                    FROM tahfidz_teacher_attendance
                    WHERE teacher_id = ? AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                ");
                $stmt->bind_param("i", $teacher_id);
                $stmt->execute();
                $att_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $present = $att_row['present'] ?? 0;
                $total_att = $att_row['total'] ?? 0;
                $attendance_rate = $total_att > 0 ? round(($present / $total_att) * 100.0, 2) : 100.0;
                
                // Total Setoran and Murojaah entered by this teacher (last 30 days)
                $stmt = $this->mysqli->prepare("
                    SELECT 
                        SUM(CASE WHEN entry_type = 'HAFALAN_BARU' THEN 1 ELSE 0 END) as setoran,
                        SUM(CASE WHEN entry_type = 'MUROJAAH' THEN 1 ELSE 0 END) as murojaah
                    FROM memorization_entries
                    WHERE teacher_id = ? AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                ");
                $stmt->bind_param("i", $teacher_id);
                $stmt->execute();
                $entries_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $teachers[] = [
                    'teacher_id' => $teacher_id,
                    'full_name' => $row['full_name'],
                    'halaqah_group' => $row['group_name'],
                    'attendance_rate_30_days' => $attendance_rate,
                    'setoran_recorded_30_days' => (int)($entries_row['setoran'] ?? 0),
                    'murojaah_recorded_30_days' => (int)($entries_row['murojaah'] ?? 0)
                ];
            }
        }
        return $teachers;
    }

    // API 9: Detail Pengampu
    public function getDetailPengampu($user_id, $teacher_id, $filters = []) {
        $teacher_id = (int)$teacher_id;
        
        $stmt = $this->mysqli->prepare("
            SELECT e.id, e.full_name, e.nip, e.phone_number,
                   u.name as unit_name, div.name as division_name
            FROM employees e
            LEFT JOIN units u ON e.unit_id = u.id
            LEFT JOIN divisions div ON e.division_id = div.id
            WHERE e.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$profile) {
            throw new Exception("Teacher not found.");
        }

        $profile['name'] = $profile['full_name'];
        
        // Teacher Attendance rate (last 30 days)
        $stmt_att = $this->mysqli->prepare("
            SELECT 
                SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as present,
                COUNT(*) as total
            FROM tahfidz_teacher_attendance
            WHERE teacher_id = ? AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
        ");
        $stmt_att->bind_param("i", $teacher_id);
        $stmt_att->execute();
        $att_row = $stmt_att->get_result()->fetch_assoc();
        $stmt_att->close();
        $present = $att_row['present'] ?? 0;
        $total_att = $att_row['total'] ?? 0;
        $attendance_rate = $total_att > 0 ? round(($present / $total_att) * 100.0, 2) : 100.0;
        
        $profile['attendance_rate'] = $attendance_rate;

        // weekly_input_count (last 7 days entries)
        $stmt_weekly = $this->mysqli->prepare("
            SELECT COUNT(*) FROM memorization_entries WHERE teacher_id = ? AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
        ");
        $stmt_weekly->bind_param("i", $teacher_id);
        $stmt_weekly->execute();
        $weekly_input_count = (int)$stmt_weekly->get_result()->fetch_row()[0];
        $stmt_weekly->close();
        
        $profile['weekly_input_count'] = $weekly_input_count;
        
        // Find halaqahs managed by this teacher
        $stmt = $this->mysqli->prepare("
            SELECT hg.id, hg.group_name 
            FROM halaqah_groups hg 
            WHERE hg.teacher_id = ?
        ");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $halaqahs = [];
        while ($row = $res->fetch_assoc()) {
            // Count members active in this group
            $stmt_m = $this->mysqli->prepare("SELECT COUNT(*) FROM halaqah_members hm JOIN students s ON hm.student_id = s.id WHERE hm.group_id = ? AND s.status = 'Aktif'");
            $stmt_m->bind_param("i", $row['id']);
            $stmt_m->execute();
            $row['member_count'] = (int)$stmt_m->get_result()->fetch_row()[0];
            $stmt_m->close();

            $halaqahs[] = $row;
        }
        $stmt->close();
        
        // Recent Activities logged by this teacher
        $stmt = $this->mysqli->prepare("
            SELECT me.id, s.nama_siswa as student_name, me.date, me.entry_type, me.notes, me.created_at,
                   me.surah_start as surah_name, me.start_ayah, me.end_ayah, me.entry_type as activity_name
            FROM memorization_entries me
            JOIN students s ON me.student_id = s.id
            WHERE me.teacher_id = ?
            ORDER BY me.created_at DESC, me.id DESC
            LIMIT 15
        ");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $res_act = $stmt->get_result();
        $activities = [];
        while ($row = $res_act->fetch_assoc()) {
            if ($row['activity_name'] == 'HAFALAN_BARU') {
                $row['activity_name'] = 'Ziyadah';
            } else if ($row['activity_name'] == 'MUROJAAH') {
                $row['activity_name'] = 'Murojaah';
            }
            $activities[] = $row;
        }
        $stmt->close();
        
        return [
            'profile' => $profile,
            'halaqahs' => $halaqahs,
            'recent_activities' => $activities
        ];
    }

    // API 10: Monitoring Santri
    public function getMonitoringSantri($user_id, $filters = [], $limit = 15, $page = 1) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where = $sf['where'];
        $params = $sf['params'];
        $types = $sf['types'];
        
        if (!empty($filters['search'])) {
            $where[] = "s.nama_siswa LIKE ?";
            $params[] = "%" . $filters['search'] . "%";
            $types .= "s";
        }
        
        $where_clause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
        $offset = ($page - 1) * $limit;
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;
        $start_date = $ay ? $ay['start_date'] : date('Y-01-01');

        $sql = "SELECT s.id, s.nama_siswa as full_name, COALESCE(gl.name, s.kelas) as kelas, s.tingkat,
                       hg.group_name as halaqah_name, e.full_name as teacher_name
                FROM students s
                LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                JOIN halaqah_members hm ON hm.student_id = s.id
                JOIN halaqah_groups hg ON hm.group_id = hg.id
                LEFT JOIN employees e ON hg.teacher_id = e.id
                " . $where_clause . "
                ORDER BY s.nama_siswa ASC
                LIMIT ? OFFSET ?";
                
        $params_query = array_merge([$ay_id], $params, [(int)$limit, (int)$offset]);
        $types_query = "i" . $types . "ii";
        
        $res = $this->queryWithParams($sql, $params_query, $types_query);
        $santri_list = [];
        
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $student_id = $row['id'];
                
                // Last memorization entry
                $stmt = $this->mysqli->prepare("
                    SELECT date, end_surah_id, end_ayah, juz, status, surah_end
                    FROM memorization_entries
                    WHERE student_id = ? AND entry_type = 'HAFALAN_BARU'
                    ORDER BY date DESC, id DESC LIMIT 1
                ");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $last_mem = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $last_setor_date = $last_mem ? $last_mem['date'] : null;
                $days_since_setor = null;
                if ($last_setor_date) {
                    $diff = time() - strtotime($last_setor_date);
                    $days_since_setor = max(0, (int)floor($diff / (60 * 60 * 24)));
                }
                
                // Calculate current total juz
                $stmt = $this->mysqli->prepare("
                    SELECT 
                        COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = ? AND academic_year_id = ? LIMIT 1), 0.00) + 
                        (COALESCE(SUM(line_count), 0) / 300.0) as total_juz
                    FROM memorization_entries
                    WHERE student_id = ? AND entry_type = 'HAFALAN_BARU' AND date >= ?
                ");
                $stmt->bind_param("iiis", $student_id, $ay_id, $student_id, $start_date);
                $stmt->execute();
                $total_juz_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $total_juz = $total_juz_row ? round((float)$total_juz_row['total_juz'], 2) : 0.00;
                
                $santri_list[] = [
                    'id' => $student_id,
                    'full_name' => $row['full_name'],
                    'kelas' => $row['kelas'],
                    'tingkat' => $row['tingkat'],
                    'halaqah_name' => $row['halaqah_name'] ?? 'Belum Ada',
                    'teacher_name' => $row['teacher_name'] ?? 'Belum Ada',
                    'last_setoran_date' => $last_setor_date,
                    'days_since_last_setor' => $days_since_setor,
                    'total_juz' => $total_juz
                ];
            }
        }
        
        return $santri_list;
    }

    // API 11: Detail Santri
    public function getDetailSantri($user_id, $student_id) {
        $student_id = (int)$student_id;
        
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;
        $start_date = $ay ? $ay['start_date'] : date('Y-01-01');

        $stmt = $this->mysqli->prepare("
            SELECT s.id, s.nama_siswa as full_name, COALESCE(gl.name, s.kelas) as kelas, s.tingkat, s.status,
                   hg.group_name as halaqah_name, e.full_name as teacher_name
            FROM students s
            LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
            LEFT JOIN grade_levels gl ON sch.class_id = gl.id
            LEFT JOIN halaqah_members hm ON hm.student_id = s.id
            LEFT JOIN halaqah_groups hg ON hm.group_id = hg.id
            LEFT JOIN employees e ON hg.teacher_id = e.id
            WHERE s.id = ? LIMIT 1
        ");
        $stmt->bind_param("ii", $ay_id, $student_id);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$profile) {
            throw new Exception("Student not found.");
        }
        
        // Calculated total juz and baseline
        $stmt = $this->mysqli->prepare("
            SELECT baseline_juz FROM memorization_baselines WHERE student_id = ? AND academic_year_id = ? LIMIT 1
        ");
        $stmt->bind_param("ii", $student_id, $ay_id);
        $stmt->execute();
        $baseline_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $baseline_juz = $baseline_row ? (float)$baseline_row['baseline_juz'] : 0.00;
        
        // Sum lines for new hafalan
        $stmt = $this->mysqli->prepare("
            SELECT SUM(line_count) FROM memorization_entries WHERE student_id = ? AND entry_type = 'HAFALAN_BARU' AND date >= ?
        ");
        $stmt->bind_param("is", $student_id, $start_date);
        $stmt->execute();
        $lines_row = $stmt->get_result()->fetch_row();
        $stmt->close();
        $new_lines = $lines_row ? (int)$lines_row[0] : 0;
        $total_juz = $baseline_juz + round($new_lines / 300.0, 2);
        
        // Memorization History (limit 20)
        $stmt = $this->mysqli->prepare("
            SELECT id, date, entry_type, surah_start, start_ayah, surah_end, end_ayah, line_count, notes, score, status
            FROM memorization_entries
            WHERE student_id = ?
            ORDER BY date DESC, id DESC
            LIMIT 20
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $history_res = $stmt->get_result();
        $history = [];
        while ($row = $history_res->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();
        
        // Attendance counts (last 30 days)
        $stmt = $this->mysqli->prepare("
            SELECT status, COUNT(*) as count 
            FROM tahfidz_attendance
            WHERE student_id = ? AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
            GROUP BY status
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $att_res = $stmt->get_result();
        $attendance = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alpha' => 0];
        while ($row = $att_res->fetch_assoc()) {
            $status = ucfirst(strtolower($row['status']));
            if ($status === 'Alpha') $status = 'Alpha'; // keep match
            if (isset($attendance[$status])) {
                $attendance[$status] = (int)$row['count'];
            }
        }
        $stmt->close();

        return [
            'profile' => $profile,
            'stats' => [
                'baseline_juz' => $baseline_juz,
                'total_juz' => $total_juz,
                'total_new_lines' => $new_lines,
                'memorized_juz_semester' => round($new_lines / 300.0, 2)
            ],
            'attendance_last_30_days' => $attendance,
            'history' => $history
        ];
    }

    // API 12: Santri Perlu Perhatian
    public function getSantriAttentionNeeded($user_id, $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;

        // Query active students under scope
        $sql = "SELECT s.id, s.nama_siswa as full_name, COALESCE(gl.name, s.kelas) as kelas, s.tingkat,
                       hg.group_name as halaqah_name, e.full_name as teacher_name
                FROM students s
                LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                JOIN halaqah_members hm ON hm.student_id = s.id
                JOIN halaqah_groups hg ON hm.group_id = hg.id
                LEFT JOIN employees e ON hg.teacher_id = e.id
                WHERE s.status = 'Aktif'" . $where_clause;
                
        $params_query = array_merge([$ay_id], $sf['params']);
        $types_query = "i" . $sf['types'];
        $res = $this->queryWithParams($sql, $params_query, $types_query);
        $attention_needed = [];
        
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $student_id = $row['id'];
                
                // Criteria 1: No setoran > 3 days
                $stmt = $this->mysqli->prepare("
                    SELECT date FROM memorization_entries 
                    WHERE student_id = ? AND entry_type = 'HAFALAN_BARU'
                    ORDER BY date DESC, id DESC LIMIT 1
                ");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $last_setor = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $days_since_setor = 999; // default high if never setoran
                if ($last_setor) {
                    $diff = time() - strtotime($last_setor['date']);
                    $days_since_setor = (int)floor($diff / (60 * 60 * 24));
                }
                
                // Criteria 2: Low attendance rate (Alpha counts in last 15 days)
                $stmt = $this->mysqli->prepare("
                    SELECT 
                        SUM(CASE WHEN status = 'Alpha' OR status = 'Alfa' THEN 1 ELSE 0 END) as alpha_count,
                        COUNT(*) as total
                    FROM tahfidz_attendance
                    WHERE student_id = ? AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 15 DAY)
                ");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $att_stats = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $alpha_count = $att_stats['alpha_count'] ?? 0;
                $total_att = $att_stats['total'] ?? 0;
                
                $should_add = false;
                $reasons = [];
                
                if ($days_since_setor > 3) {
                    $should_add = true;
                    $reasons[] = "Tidak menyetor hafalan selama $days_since_setor hari.";
                }
                if ($alpha_count >= 3) {
                    $should_add = true;
                    $reasons[] = "Absen Alfa sebanyak $alpha_count kali dalam 15 hari terakhir.";
                }
                
                if ($should_add) {
                    $attention_needed[] = [
                        'id' => $student_id,
                        'full_name' => $row['full_name'],
                        'kelas' => $row['kelas'],
                        'tingkat' => $row['tingkat'],
                        'halaqah_name' => $row['halaqah_name'] ?? 'Belum Ada',
                        'teacher_name' => $row['teacher_name'] ?? 'Belum Ada',
                        'reasons' => $reasons,
                        'days_since_last_setor' => $days_since_setor === 999 ? "Belum Pernah" : $days_since_setor
                    ];
                }
            }
        }
        
        return $attention_needed;
    }

    // API 13: Statistik Historis
    public function getHistoricalStats($user_id, $period = 'month', $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $intervals = 30; // default for month
        
        if ($period === 'week') {
            $intervals = 7;
        } else if ($period === 'semester') {
            $intervals = 180;
        }
        
        // Daily total line counts for the selected period
        $sql = "SELECT me.date, SUM(me.line_count) as total_lines
                FROM memorization_entries me
                JOIN students s ON me.student_id = s.id
                WHERE s.status = 'Aktif' AND me.entry_type = 'HAFALAN_BARU'
                  AND me.date >= DATE_SUB(CURRENT_DATE(), INTERVAL ? DAY)
                  " . $where_clause . "
                GROUP BY me.date
                ORDER BY me.date ASC";
                
        $params = array_merge([(int)$intervals], $sf['params']);
        $types = "i" . $sf['types'];
        
        $res = $this->queryWithParams($sql, $params, $types);
        $chart_data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $chart_data[] = [
                    'date' => $row['date'],
                    'juz_equivalent' => round($row['total_lines'] / 300.0, 2)
                ];
            }
        }
        return $chart_data;
    }

    // API 14: Executive Insight
    public function getExecutiveInsight($user_id, $filters = []) {
        $summary = $this->getExecutiveSummary($user_id, $filters);
        
        $attendance_rate = 100.0;
        $total_santri = $summary['total_santri'];
        if ($total_santri > 0) {
            $present = $summary['kehadiran_santri_detail']['Hadir'] ?? 0;
            $attendance_rate = round(($present / $total_santri) * 100.0, 2);
        }
        
        $insights = [];
        $insights[] = "Persentase kehadiran santri hari ini sebesar " . $attendance_rate . "%.";
        
        if ($summary['santri_belum_setor'] > 0) {
            $insights[] = "Sebanyak " . $summary['santri_belum_setor'] . " santri belum menyetorkan hafalan baru hari ini.";
        } else {
            $insights[] = "Seluruh santri aktif telah menyetorkan hafalan baru hari ini!";
        }
        
        if ($summary['halaqah_belum_aktif'] > 0) {
            $insights[] = "Terdapat " . $summary['halaqah_belum_aktif'] . " halaqoh yang belum mencatatkan aktivitas setor/absensi hari ini.";
        }

        return [
            'attendance_rate' => $attendance_rate,
            'insights' => $insights
        ];
    }

    // API 15: Perbandingan Unit
    public function getCompareUnits($user_id, $filters = []) {
        $scope = $this->resolveScope($user_id);
        
        // Exclude units not in scope
        $available_units = ['MTS', 'MA'];
        if (!empty($scope['units'])) {
            $available_units = array_intersect($available_units, array_map('strtoupper', $scope['units']));
        }
        
        $comparison = [];
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;
        
        foreach ($available_units as $unit) {
            // Count students
            $stmt = $this->mysqli->prepare("
                SELECT COUNT(*) 
                FROM students s
                JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                WHERE s.status = 'Aktif' AND s.tingkat = ?
            ");
            $stmt->bind_param("is", $ay_id, $unit);
            $stmt->execute();
            $student_count = $stmt->get_result()->fetch_row()[0];
            $stmt->close();
            
            // Count teachers
            $stmt = $this->mysqli->prepare("
                SELECT COUNT(DISTINCT hg.teacher_id) 
                FROM halaqah_groups hg
                JOIN halaqah_members hm ON hm.group_id = hg.id
                JOIN students s ON hm.student_id = s.id
                JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                WHERE s.status = 'Aktif' AND s.tingkat = ?
            ");
            $stmt->bind_param("is", $ay_id, $unit);
            $stmt->execute();
            $teacher_count = $stmt->get_result()->fetch_row()[0];
            $stmt->close();
            
            // Sum line counts in active year
            $start_date = $ay ? $ay['start_date'] : date('Y-01-01');
            $end_date = $ay ? $ay['end_date'] : date('Y-12-31');
            $stmt = $this->mysqli->prepare("
                SELECT SUM(line_count) FROM memorization_entries me
                JOIN students s ON me.student_id = s.id
                JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                WHERE s.status = 'Aktif' AND s.tingkat = ? AND me.entry_type = 'HAFALAN_BARU'
                  AND me.date BETWEEN ? AND ?
            ");
            $stmt->bind_param("isss", $ay_id, $unit, $start_date, $end_date);
            $stmt->execute();
            $total_lines = $stmt->get_result()->fetch_row()[0] ?? 0;
            $stmt->close();
            $progress_juz = round($total_lines / 300.0, 2);
            
            $comparison[] = [
                'unit' => $unit,
                'student_count' => (int)$student_count,
                'teacher_count' => (int)$teacher_count,
                'total_memorized_juz_semester' => $progress_juz
            ];
        }
        
        return $comparison;
    }

    // API 16: Ranking
    public function getRanking($user_id, $type = 'halaqah', $metric = 'progress', $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;
        
        $rankings = [];
        
        $start_date = $ay ? $ay['start_date'] : date('Y-01-01');
        $end_date = $ay ? $ay['end_date'] : date('Y-12-31');

        if ($type === 'halaqah') {
            // Rank halaqahs by lines/juz progress
            $sql = "SELECT hg.id, hg.group_name, e.full_name as teacher_name,
                           SUM(me.line_count) as total_lines
                    FROM halaqah_groups hg
                    JOIN halaqah_members hm ON hm.group_id = hg.id
                    JOIN students s ON hm.student_id = s.id
                    LEFT JOIN employees e ON hg.teacher_id = e.id
                    LEFT JOIN memorization_entries me ON me.student_id = s.id AND me.entry_type = 'HAFALAN_BARU' AND me.date BETWEEN ? AND ?
                    WHERE s.status = 'Aktif' " . $where_clause . "
                    GROUP BY hg.id
                    ORDER BY total_lines DESC
                    LIMIT 10";
                    
            $params = array_merge([$start_date, $end_date], $sf['params']);
            $types = "ss" . $sf['types'];
            $res = $this->queryWithParams($sql, $params, $types);
            $rank = 1;
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rankings[] = [
                        'rank' => $rank++,
                        'name' => $row['group_name'],
                        'subtitle' => $row['teacher_name'] ?? 'Belum Ditunjuk',
                        'value' => round($row['total_lines'] / 300.0, 2) . " Juz"
                    ];
                }
            }
        } else if ($type === 'pengampu') {
            // Rank teachers by number of entries processed (engagement)
            $sql = "SELECT e.id, e.full_name, COUNT(me.id) as record_count
                    FROM employees e
                    JOIN halaqah_groups hg ON hg.teacher_id = e.id
                    JOIN halaqah_members hm ON hm.group_id = hg.id
                    JOIN students s ON hm.student_id = s.id
                    LEFT JOIN memorization_entries me ON me.teacher_id = e.id AND me.date BETWEEN ? AND ?
                    WHERE s.status = 'Aktif' " . $where_clause . "
                    GROUP BY e.id
                    ORDER BY record_count DESC
                    LIMIT 10";
            
            $params = array_merge([$start_date, $end_date], $sf['params']);
            $types = "ss" . $sf['types'];
            $res = $this->queryWithParams($sql, $params, $types);
            $rank = 1;
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rankings[] = [
                        'rank' => $rank++,
                        'name' => $row['full_name'],
                        'subtitle' => 'Pengampu Tahfidz',
                        'value' => (int)$row['record_count'] . " Entri"
                    ];
                }
            }
        }
        
        return $rankings;
    }

    // API 17: Health Score
    public function getHealthScore($user_id, $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $ay = $this->getActiveAcademicYear();
        
        $start_date = $ay ? $ay['start_date'] : date('Y-01-01');

        // We evaluate attendance completeness and progress target to build a health score
        $sql = "SELECT s.id,
                       (COALESCE((SELECT baseline_juz FROM memorization_baselines WHERE student_id = s.id AND academic_year_id = ? LIMIT 1), 0.00) + 
                        COALESCE((SELECT SUM(line_count) FROM memorization_entries WHERE student_id = s.id AND entry_type = 'HAFALAN_BARU' AND date >= ?), 0) / 300.0) as total_juz
                FROM students s
                WHERE s.status = 'Aktif'" . $where_clause;
                
        $ay_id = $ay ? (int)$ay['id'] : 0;
        $params = array_merge([$ay_id, $start_date], $sf['params']);
        $types = "is" . $sf['types'];
        
        $res = $this->queryWithParams($sql, $params, $types);
        
        $total_students = 0;
        $juz_sum = 0.0;
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $total_students++;
                $juz_sum += (float)$row['total_juz'];
            }
        }
        
        // Base health calculations:
        $avg_juz = $total_students > 0 ? ($juz_sum / $total_students) : 0.0;
        $health_score = min(100.0, max(0.0, round(($avg_juz / 15.0) * 100.0, 2))); // 15 Juz is average benchmark

        return [
            'average_juz' => round($avg_juz, 2),
            'health_score' => $health_score,
            'rating' => $health_score >= 80 ? 'Sangat Sehat' : ($health_score >= 60 ? 'Sehat' : 'Perlu Perbaikan')
        ];
    }

    // API 18: Drill Down Navigation
    public function getDrillDown($user_id, $level = 'unit', $parent_id = null, $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;

        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $drill_data = [];
        
        if ($level === 'unit') {
            // Drill down to Units
            $available_units = ['MTS', 'MA', 'SDIT', 'TKIT', 'MAHAD ALY'];
            if (!empty($scope['units'])) {
                $available_units = array_intersect($available_units, array_map('strtoupper', $scope['units']));
            }
            foreach ($available_units as $unit) {
                $unit_cond = "1=1";
                if ($unit === 'MTS') {
                    $unit_cond = "(UPPER(TRIM(eu.name)) = 'MTS' OR gl.name LIKE '7%' OR gl.name LIKE '8%' OR gl.name LIKE '9%' OR gl.name LIKE 'VII%' OR gl.name LIKE 'VIII%' OR gl.name LIKE 'IX%') AND gl.name NOT LIKE '10%' AND gl.name NOT LIKE '11%' AND gl.name NOT LIKE '12%' AND gl.name NOT LIKE 'X%' AND gl.name NOT LIKE 'XI%' AND gl.name NOT LIKE 'XII%'";
                } else if ($unit === 'MA') {
                    $unit_cond = "(UPPER(TRIM(eu.name)) = 'MA' OR gl.name LIKE '10%' OR gl.name LIKE '11%' OR gl.name LIKE '12%' OR gl.name LIKE 'X%' OR gl.name LIKE 'XI%' OR gl.name LIKE 'XII%') AND gl.name NOT LIKE '7%' AND gl.name NOT LIKE '8%' AND gl.name NOT LIKE '9%' AND gl.name NOT LIKE 'VII%' AND gl.name NOT LIKE 'VIII%' AND gl.name NOT LIKE 'IX%'";
                } else {
                    $escaped_unit = $this->mysqli->real_escape_string($unit);
                    $unit_cond = "UPPER(TRIM(eu.name)) = '$escaped_unit'";
                }

                $stmt = $this->mysqli->prepare("
                    SELECT COUNT(DISTINCT s.id) 
                    FROM students s
                    JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                    JOIN grade_levels gl ON sch.class_id = gl.id
                    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
                    WHERE s.status = 'Aktif' AND $unit_cond
                ");
                $stmt->bind_param("i", $ay_id);
                $stmt->execute();
                $count = $stmt->get_result()->fetch_row()[0];
                $stmt->close();
                $drill_data[] = [
                    'id' => $unit,
                    'name' => $unit,
                    'type' => 'unit',
                    'student_count' => (int)$count
                ];
            }
        } else if ($level === 'class' && $parent_id) {
            // Drill down to Classes of selected Unit
            $unit = strtoupper(trim($parent_id));
            $unit_cond = "1=1";
            if ($unit === 'MTS') {
                $unit_cond = "(UPPER(TRIM(eu.name)) = 'MTS' OR gl.name LIKE '7%' OR gl.name LIKE '8%' OR gl.name LIKE '9%' OR gl.name LIKE 'VII%' OR gl.name LIKE 'VIII%' OR gl.name LIKE 'IX%') AND gl.name NOT LIKE '10%' AND gl.name NOT LIKE '11%' AND gl.name NOT LIKE '12%' AND gl.name NOT LIKE 'X%' AND gl.name NOT LIKE 'XI%' AND gl.name NOT LIKE 'XII%'";
            } else if ($unit === 'MA') {
                $unit_cond = "(UPPER(TRIM(eu.name)) = 'MA' OR gl.name LIKE '10%' OR gl.name LIKE '11%' OR gl.name LIKE '12%' OR gl.name LIKE 'X%' OR gl.name LIKE 'XI%' OR gl.name LIKE 'XII%') AND gl.name NOT LIKE '7%' AND gl.name NOT LIKE '8%' AND gl.name NOT LIKE '9%' AND gl.name NOT LIKE 'VII%' AND gl.name NOT LIKE 'VIII%' AND gl.name NOT LIKE 'IX%'";
            } else if ($unit === 'SDIT') {
                $unit_cond = "(UPPER(TRIM(eu.name)) = 'SDIT' OR gl.name LIKE '1%' OR gl.name LIKE '2%' OR gl.name LIKE '3%' OR gl.name LIKE '4%' OR gl.name LIKE '5%' OR gl.name LIKE '6%') AND gl.name NOT LIKE '7%' AND gl.name NOT LIKE '8%' AND gl.name NOT LIKE '9%' AND gl.name NOT LIKE '10%' AND gl.name NOT LIKE '11%' AND gl.name NOT LIKE '12%'";
            } else {
                $escaped_unit = $this->mysqli->real_escape_string($unit);
                $unit_cond = "UPPER(TRIM(eu.name)) = '$escaped_unit'";
            }

            $stmt = $this->mysqli->prepare("
                SELECT gl.name as kelas, COUNT(DISTINCT s.id) as count 
                FROM grade_levels gl
                LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
                LEFT JOIN student_class_history sch ON sch.class_id = gl.id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                LEFT JOIN students s ON s.id = sch.student_id AND s.status = 'Aktif'
                WHERE $unit_cond
                GROUP BY gl.id, gl.name 
                ORDER BY gl.name ASC
            ");
            $stmt->bind_param("i", $ay_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $drill_data[] = [
                    'id' => $row['kelas'],
                    'name' => "Kelas " . $row['kelas'],
                    'type' => 'class',
                    'student_count' => (int)$row['count']
                ];
            }
            $stmt->close();
        } else if ($level === 'halaqah' && $parent_id) {
            // Drill down to Halaqahs inside selected class/unit
            $class_name = trim($parent_id);
            $clean_class_name = $class_name;
            if (strpos(strtolower($clean_class_name), 'kelas ') === 0) {
                $clean_class_name = trim(substr($clean_class_name, 6));
            }
            
            $sql = "SELECT DISTINCT hg.id, hg.group_name, e.full_name as teacher_name
                    FROM halaqah_groups hg
                    JOIN halaqah_members hm ON hm.group_id = hg.id
                    JOIN students s ON hm.student_id = s.id
                    JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                    JOIN grade_levels gl ON sch.class_id = gl.id
                    LEFT JOIN employees e ON hg.teacher_id = e.id
                    WHERE s.status = 'Aktif' 
                      AND (gl.name = ? OR gl.name = ? OR gl.name = ?) " . $where_clause;
                    
            $full_class_name = "Kelas " . $clean_class_name;
            $params = array_merge([$ay_id, $class_name, $clean_class_name, $full_class_name], $sf['params']);
            $types = "isss" . $sf['types'];
            
            $res = $this->queryWithParams($sql, $params, $types);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $drill_data[] = [
                        'id' => $row['id'],
                        'name' => $row['group_name'],
                        'type' => 'halaqah',
                        'teacher_name' => $row['teacher_name'] ?? 'Belum Ditunjuk'
                    ];
                }
            }
        }
        
        return $drill_data;
    }

    // API 16: Daily submissions status of teachers (pengampu)
    public function getDailyPengampuSubmissions($user_id, $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $target_date = isset($filters['date']) ? $filters['date'] : date('Y-m-d');
        
        // Find halaqohs having members matching our scope/filters
        $sql = "SELECT DISTINCT hg.id, hg.group_name, e.full_name as teacher_name
                FROM halaqah_groups hg
                JOIN halaqah_members hm ON hm.group_id = hg.id
                JOIN students s ON hm.student_id = s.id
                LEFT JOIN employees e ON hg.teacher_id = e.id
                WHERE s.status = 'Aktif'" . $where_clause . " 
                ORDER BY LENGTH(hg.group_name) ASC, hg.group_name ASC";
                
        $res = $this->queryWithParams($sql, $sf['params'], $sf['types']);
        $submissions = [];
        
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $group_id = $row['id'];
                
                // Member count
                $stmt = $this->mysqli->prepare("
                    SELECT COUNT(*) 
                    FROM halaqah_members hm 
                    JOIN students s ON hm.student_id = s.id 
                    WHERE hm.group_id = ? AND s.status = 'Aktif'
                ");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $member_count = (int)$stmt->get_result()->fetch_row()[0];
                $stmt->close();
                
                // Count how many students have HAFALAN_BARU today
                $stmt = $this->mysqli->prepare("
                    SELECT COUNT(DISTINCT me.student_id) 
                    FROM memorization_entries me
                    JOIN halaqah_members hm ON me.student_id = hm.student_id
                    WHERE hm.group_id = ? AND me.date = ? AND me.entry_type = 'HAFALAN_BARU'
                ");
                $stmt->bind_param("is", $group_id, $target_date);
                $stmt->execute();
                $setoran_count = (int)$stmt->get_result()->fetch_row()[0];
                $stmt->close();
                
                // Count how many students have MUROJAAH today
                $stmt = $this->mysqli->prepare("
                    SELECT COUNT(DISTINCT me.student_id) 
                    FROM memorization_entries me
                    JOIN halaqah_members hm ON me.student_id = hm.student_id
                    WHERE hm.group_id = ? AND me.date = ? AND me.entry_type = 'MUROJAAH'
                ");
                $stmt->bind_param("is", $group_id, $target_date);
                $stmt->execute();
                $murojaah_count = (int)$stmt->get_result()->fetch_row()[0];
                $stmt->close();
                
                // Count how many students have attendance filled today
                $stmt = $this->mysqli->prepare("
                    SELECT COUNT(DISTINCT ta.student_id) 
                    FROM tahfidz_attendance ta
                    JOIN halaqah_members hm ON ta.student_id = hm.student_id
                    WHERE hm.group_id = ? AND ta.date = ?
                ");
                $stmt->bind_param("is", $group_id, $target_date);
                $stmt->execute();
                $attendance_count = (int)$stmt->get_result()->fetch_row()[0];
                $stmt->close();
                
                $submissions[] = [
                    'group_id' => $group_id,
                    'group_name' => $row['group_name'],
                    'teacher_name' => $row['teacher_name'] ?? 'Belum Ditunjuk',
                    'member_count' => $member_count,
                    'setoran_count' => $setoran_count,
                    'murojaah_count' => $murojaah_count,
                    'attendance_count' => $attendance_count
                ];
            }
        }
        
        return $submissions;
    }

    // API 17: Get daily logs of all memorization entries (setoran & murojaah) today
    public function getDailyMemorizationLog($user_id, $filters = []) {
        $scope = $this->resolveScope($user_id);
        $sf = $this->buildScopeAndFilters($scope, $filters);
        
        $where_clause = !empty($sf['where']) ? " AND " . implode(" AND ", $sf['where']) : "";
        $target_date = isset($filters['date']) ? $filters['date'] : date('Y-m-d');
        
        $ay = $this->getActiveAcademicYear();
        $ay_id = $ay ? (int)$ay['id'] : 0;
        
        $sql = "SELECT me.id, me.student_id, s.nama_siswa as student_name, 
                       COALESCE(gl.name, s.kelas) as kelas, s.tingkat,
                       hg.group_name as halaqah_name, e.full_name as teacher_name,
                       me.entry_type, me.surah_start, me.start_ayah, 
                       me.surah_end, me.end_ayah, me.line_count, me.status as quality, 
                       me.notes, me.created_at
                FROM memorization_entries me
                JOIN students s ON me.student_id = s.id
                LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = ? AND sch.status = 'ACTIVE'
                LEFT JOIN grade_levels gl ON sch.class_id = gl.id
                LEFT JOIN halaqah_members hm ON hm.student_id = s.id
                LEFT JOIN halaqah_groups hg ON hm.group_id = hg.id
                LEFT JOIN employees e ON me.teacher_id = e.id
                WHERE me.date = ?" . $where_clause . " 
                ORDER BY me.created_at DESC, me.id DESC";
                
        $params = array_merge([$ay_id, $target_date], $sf['params']);
        $types = "is" . $sf['types'];
        
        $res = $this->queryWithParams($sql, $params, $types);
        $log = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $log[] = $row;
            }
        }
        return $log;
    }
}
