<?php
// app/Services/Tahfidz/SemesterClosingService.php

require_once __DIR__ . '/SemesterReportService.php';

class SemesterClosingService {
    private $mysqli;
    private $reportService;

    public function __construct($mysqli = null) {
        if ($mysqli) {
            $this->mysqli = $mysqli;
        } else {
            global $mysqli;
            require_once __DIR__ . '/../../../config/db_mysqli.php';
            $this->mysqli = $mysqli;
        }
        $this->reportService = new SemesterReportService($this->mysqli);
    }

    public function closeSemester($academic_year_id, $semester) {
        $academic_year_id = (int)$academic_year_id;

        // Verify role permission (must be handled at endpoint layer, but let's check role if employee_id passed)
        // Check if academic year exists
        $stmt = $this->mysqli->prepare("SELECT * FROM academic_years WHERE id = ?");
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $ay = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ay) {
            throw new Exception("Academic year not found.");
        }

        // Check if snapshot already exists for this semester
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM semester_snapshots WHERE academic_year_id = ? AND semester = ?");
        $stmt->bind_param("is", $academic_year_id, $semester);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        if ($count > 0) {
            throw new Exception("Semester has already been closed. Snapshots are locked and cannot be re-generated.");
        }

        // Fetch all active students
        $res = $this->mysqli->query("SELECT id FROM students WHERE status = 'Aktif'");
        $students = [];
        while ($row = $res->fetch_assoc()) {
            $students[] = (int)$row['id'];
        }

        $this->mysqli->begin_transaction();
        try {
            $generated_count = 0;
            $stmt = $this->mysqli->prepare("INSERT INTO semester_snapshots 
                (academic_year_id, semester, student_id, baseline_juz, target_juz, memorized_juz, total_juz, murojaah_total, tasmi_score, progress_percentage, notes, generated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            foreach ($students as $student_id) {
                // Generate report data for this student
                try {
                    $rep = $this->reportService->getSemesterReport($student_id, $academic_year_id);
                } catch (Exception $e) {
                    // Skip if student doesn't have active data or target
                    continue;
                }

                $baseline_juz = $rep['baseline_awal'];
                $target_juz = $rep['target_semester'];
                $memorized_juz = $rep['hafalan_baru'];
                $total_juz = $rep['total_hafalan'];
                $murojaah_total = $rep['total_murojaah'];
                $tasmi_score = $rep['nilai_tasmi'];
                $progress_percentage = $rep['persentase_target'];
                $notes = $rep['catatan'];

                $stmt->bind_param("isiddddidss",
                    $academic_year_id,
                    $semester,
                    $student_id,
                    $baseline_juz,
                    $target_juz,
                    $memorized_juz,
                    $total_juz,
                    $murojaah_total,
                    $tasmi_score,
                    $progress_percentage,
                    $notes
                );
                
                if ($stmt->execute()) {
                    $generated_count++;
                }
            }
            
            $stmt->close();
            $this->mysqli->commit();
            
            $this->logActivity("Semester closed for Academic Year ID: $academic_year_id, Semester: $semester. Generated $generated_count snapshots.");
            
            return $generated_count;
        } catch (Exception $e) {
            $this->mysqli->rollback();
            throw $e;
        }
    }

    private function logActivity($message) {
        $log_file = __DIR__ . '/../../../tmp/tahfidz_activity.log';
        if (!file_exists(dirname($log_file))) {
            mkdir(dirname($log_file), 0777, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [SYSTEM] [CLOSING] $message" . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}
