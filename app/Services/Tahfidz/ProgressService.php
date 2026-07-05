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
        // We need student's unit_id and kelas_id
        $stmt = $this->mysqli->prepare("SELECT tahun_ajaran, tingkat, kelas FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $stud_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $target_juz = 0.0;
        if ($stud_info) {
            // Map student grade to unit_id and kelas_id if needed, or query target_hafalan
            // Let's check target_hafalan schema again: it matches tahun_ajaran_id (which is academic_year_id), unit_id, kelas_id.
            // Let's write a query to fetch target based on academic_year_id and student's class
            // To do this accurately, we find target_hafalan records.
            // Let's get the class name and match target_hafalan.
            // Let's see: target_hafalan matches unit_id, kelas_id (which are from education_units and grade_levels, or simple integers).
            // Let's query target_hafalan with a join on students/grade levels.
            // Since student has 'tingkat' and 'kelas', let's search if there's target_hafalan matches.
            // Let's write a lookup query:
            $target_query = "SELECT t.target_juz 
                             FROM target_hafalan t
                             JOIN students s ON s.id = ?
                             WHERE t.tahun_ajaran_id = ? 
                               AND (t.kelas_id = s.kelas OR t.kelas_id = (SELECT id FROM grade_levels WHERE name = s.kelas LIMIT 1) OR t.kelas_id = CAST(s.kelas AS UNSIGNED))
                             LIMIT 1";
            $stmt = $this->mysqli->prepare($target_query);
            $stmt->bind_param("ii", $student_id, $academic_year_id);
            $stmt->execute();
            $target_row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($target_row) {
                $target_juz = (float)$target_row['target_juz'];
            }
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
