<?php
// app/Services/Tahfidz/SemesterReportService.php

require_once __DIR__ . '/ProgressService.php';

class SemesterReportService {
    private $mysqli;
    private $progressService;

    public function __construct($mysqli = null) {
        if ($mysqli) {
            $this->mysqli = $mysqli;
        } else {
            global $mysqli;
            require_once __DIR__ . '/../../../config/db_mysqli.php';
            $this->mysqli = $mysqli;
        }
        $this->progressService = new ProgressService($this->mysqli);
    }

    public function getSemesterReport($student_id, $academic_year_id = null) {
        $student_id = (int)$student_id;
        
        // 1. Get active or specified academic year
        if ($academic_year_id === null) {
            $ay = $this->progressService->getActiveAcademicYear();
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
            throw new Exception("Active academic year not found.");
        }

        // 2. Fetch progress data (Baseline, Target, Hafalan Baru, Total, Murojaah, Percentage)
        $prog = $this->progressService->getStudentProgress($student_id, $academic_year_id);

        // 3. Count total setoran entries
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as total_setoran 
            FROM memorization_entries 
            WHERE student_id = ? AND date BETWEEN ? AND ?");
        $stmt->bind_param("iss", $student_id, $ay['start_date'], $ay['end_date']);
        $stmt->execute();
        $total_setoran = $stmt->get_result()->fetch_assoc()['total_setoran'];
        $stmt->close();

        // 4. Calculate Nilai Tasmi
        // We calculate average score of 'TASMI' entries in memorization_entries
        $stmt = $this->mysqli->prepare("SELECT AVG(score) as avg_score 
            FROM memorization_entries 
            WHERE student_id = ? AND entry_type = 'TASMI' AND date BETWEEN ? AND ?");
        $stmt->bind_param("iss", $student_id, $ay['start_date'], $ay['end_date']);
        $stmt->execute();
        $tasmi_avg = $stmt->get_result()->fetch_assoc()['avg_score'];
        $stmt->close();

        $tasmi_score = $tasmi_avg !== null ? round((float)$tasmi_avg, 2) : 0.00;

        // Fallback to tahfidz_assessments if tasmi_score is 0
        if ($tasmi_score == 0) {
            $stmt = $this->mysqli->prepare("SELECT AVG(total_score) as avg_score 
                FROM tahfidz_assessments 
                WHERE student_id = ? AND assessment_date BETWEEN ? AND ?");
            $stmt->bind_param("iss", $student_id, $ay['start_date'], $ay['end_date']);
            $stmt->execute();
            $assess_avg = $stmt->get_result()->fetch_assoc()['avg_score'];
            $stmt->close();
            if ($assess_avg !== null) {
                $tasmi_score = round((float)$assess_avg, 2);
            }
        }

        // 5. Get recent notes from entries or baselines
        $stmt = $this->mysqli->prepare("SELECT notes 
            FROM memorization_entries 
            WHERE student_id = ? AND notes IS NOT NULL AND notes != '' AND date BETWEEN ? AND ?
            ORDER BY date DESC LIMIT 1");
        $stmt->bind_param("iss", $student_id, $ay['start_date'], $ay['end_date']);
        $stmt->execute();
        $note_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $recent_note = $note_row ? $note_row['notes'] : "Progres setoran berjalan dengan baik.";

        return [
            'academic_year_name' => $ay['name'],
            'semester' => $ay['semester'],
            'baseline_awal' => $prog['baseline_juz'],
            'target_semester' => $prog['target_juz'],
            'hafalan_baru' => $prog['memorized_juz_semester'],
            'total_hafalan' => $prog['total_juz'],
            'persentase_target' => $prog['progress_percentage'],
            'total_murojaah' => $prog['murojaah_total_lines'],
            'total_setoran' => $total_setoran,
            'nilai_tasmi' => $tasmi_score,
            'catatan' => $recent_note
        ];
    }
}
