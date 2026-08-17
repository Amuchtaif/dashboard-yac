<?php
// app/Services/Tahfidz/ProgressService.php

class ProgressService {
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

    public function getStudentProgress($student_id, $academic_year_id = null) {
        $student_id = (int)$student_id;
        
        // 1. Get Academic Year
        if ($academic_year_id === null) {
            $ay = $this->getActiveAcademicYear();
            $academic_year_id = $ay ? (int)$ay['id'] : 0;
        } else {
            $academic_year_id = (int)$academic_year_id;
            $stmt = $this->mysqli->prepare("SELECT * FROM academic_years WHERE id = ?");
            $stmt->bind_param("i", $academic_year_id);
            $stmt->execute();
            $ay = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$academic_year_id || !$ay) {
            return [
                'baseline_juz' => 0.0,
                'memorized_juz_semester' => 0.0,
                'total_juz' => 0.0,
                'murojaah_total_lines' => 0,
                'target_juz' => 0.0,
                'progress_percentage' => 0.0
            ];
        }

        // 2. Get Baseline
        $stmt = $this->mysqli->prepare("SELECT baseline_juz FROM memorization_baselines WHERE academic_year_id = ? AND student_id = ? LIMIT 1");
        $stmt->bind_param("ii", $academic_year_id, $student_id);
        $stmt->execute();
        $baseline_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $baseline_juz = $baseline_row ? (float)$baseline_row['baseline_juz'] : 0.00;

        // 3. Get Hafalan Baru (lines) in this academic year
        // We filter by date within the academic year start_date and end_date
        $start_date = $ay['start_date'];
        $end_date = $ay['end_date'];

        $stmt = $this->mysqli->prepare("SELECT 
            SUM(CASE WHEN entry_type = 'HAFALAN_BARU' THEN line_count ELSE 0 END) as new_lines,
            SUM(CASE WHEN entry_type = 'MUROJAAH' THEN line_count ELSE 0 END) as murojaah_lines
            FROM memorization_entries 
            WHERE student_id = ? AND date BETWEEN ? AND ?");
        $stmt->bind_param("iss", $student_id, $start_date, $end_date);
        $stmt->execute();
        $lines_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $new_lines = $lines_row ? (int)$lines_row['new_lines'] : 0;
        $murojaah_lines = $lines_row ? (int)$lines_row['murojaah_lines'] : 0;

        // Convert lines to Juz (300 lines = 1 Juz)
        $memorized_juz_semester = round($new_lines / 300.0, 2);
        $total_juz = $baseline_juz + $memorized_juz_semester;

        // 4. Get Target Hafalan for student's grade/unit
        $target_juz = 0.0;

        // Fetch class & unit info from student_class_history for the active academic year
        $class_id = null;
        $class_name = '';
        $sch_stmt = $this->mysqli->prepare("
            SELECT sch.class_id, gl.name as class_name, gl.category as unit_name
            FROM student_class_history sch
            JOIN grade_levels gl ON sch.class_id = gl.id
            WHERE sch.student_id = ? AND sch.academic_year_id = ?
            LIMIT 1
        ");
        $sch_stmt->bind_param("ii", $student_id, $academic_year_id);
        $sch_stmt->execute();
        $sch_row = $sch_stmt->get_result()->fetch_assoc();
        $sch_stmt->close();

        if ($sch_row) {
            $class_id = (int)$sch_row['class_id'];
            $class_name = $sch_row['class_name'] ?? '';
        } else {
            $st_stmt = $this->mysqli->prepare("SELECT kelas, tingkat FROM students WHERE id = ? LIMIT 1");
            $st_stmt->bind_param("i", $student_id);
            $st_stmt->execute();
            $st_row = $st_stmt->get_result()->fetch_assoc();
            $st_stmt->close();
            if ($st_row) {
                $class_name = $st_row['kelas'] ?? '';
            }
        }

        // Extract grade number from class_name (e.g., '8A' -> 8, 'Kelas 8' -> 8, '8' -> 8, 'VIII' -> 8)
        $grade_num = null;
        if (!empty($class_name)) {
            if (preg_match('/(\d+)/', $class_name, $m)) {
                $grade_num = (int)$m[1];
            } else if (stripos($class_name, 'VIII') !== false) {
                $grade_num = 8;
            } else if (stripos($class_name, 'VII') !== false) {
                $grade_num = 7;
            } else if (stripos($class_name, 'IX') !== false) {
                $grade_num = 9;
            } else if (stripos($class_name, 'XII') !== false) {
                $grade_num = 12;
            } else if (stripos($class_name, 'XI') !== false) {
                $grade_num = 11;
            } else if (stripos($class_name, 'X') !== false) {
                $grade_num = 10;
            }
        }

        // Tier 1: Try exact match by class_id or grade_num in target_hafalan for active academic year
        if ($class_id !== null || $grade_num !== null) {
            $t_where = [];
            if ($class_id !== null) $t_where[] = "kelas_id = $class_id";
            if ($grade_num !== null) $t_where[] = "kelas_id = $grade_num";
            
            $t_query = "SELECT target_juz FROM target_hafalan 
                        WHERE tahun_ajaran_id = ? 
                          AND (" . implode(" OR ", $t_where) . ")
                        LIMIT 1";
            $stmt = $this->mysqli->prepare($t_query);
            $stmt->bind_param("i", $academic_year_id);
            $stmt->execute();
            $t_row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($t_row && (float)$t_row['target_juz'] > 0) {
                $target_juz = (float)$t_row['target_juz'];
            }
        }

        // Tier 2: Try grade_levels name match for active academic year
        if ($target_juz == 0.0 && !empty($class_name)) {
            $t_query2 = "SELECT th.target_juz FROM target_hafalan th
                         LEFT JOIN grade_levels gl ON th.kelas_id = gl.id
                         WHERE th.tahun_ajaran_id = ?
                           AND (gl.name = ? OR LOWER(gl.name) LIKE LOWER(?))
                         LIMIT 1";
            $search_name = "%$class_name%";
            $stmt = $this->mysqli->prepare($t_query2);
            $stmt->bind_param("iss", $academic_year_id, $class_name, $search_name);
            $stmt->execute();
            $t_row2 = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($t_row2 && (float)$t_row2['target_juz'] > 0) {
                $target_juz = (float)$t_row2['target_juz'];
            }
        }

        // Tier 3: Try grade_num across target_hafalan for any academic year
        if ($target_juz == 0.0 && $grade_num !== null) {
            $t_query3 = "SELECT target_juz FROM target_hafalan WHERE kelas_id = ? ORDER BY id DESC LIMIT 1";
            $stmt = $this->mysqli->prepare($t_query3);
            $stmt->bind_param("i", $grade_num);
            $stmt->execute();
            $t_row3 = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($t_row3 && (float)$t_row3['target_juz'] > 0) {
                $target_juz = (float)$t_row3['target_juz'];
            }
        }

        // Tier 4: Fallback default target per grade level (e.g. 2.0 juz per semester for grade 8)
        if ($target_juz == 0.0 && $grade_num !== null) {
            $default_targets = [
                7 => 2.0,
                8 => 2.0,
                9 => 2.0,
                10 => 2.0,
                11 => 2.0,
                12 => 2.0,
            ];
            $target_juz = $default_targets[$grade_num] ?? 2.0;
        }

        // Calculate progress percentage
        $progress_percentage = 0.00;
        if ($target_juz > 0) {
            $progress_percentage = round(($memorized_juz_semester / $target_juz) * 100.0, 2);
        }

        return [
            'baseline_juz' => $baseline_juz,
            'memorized_juz_semester' => $memorized_juz_semester,
            'total_juz' => $total_juz,
            'murojaah_total_lines' => $murojaah_lines,
            'target_juz' => $target_juz,
            'progress_percentage' => $progress_percentage
        ];
    }
}
