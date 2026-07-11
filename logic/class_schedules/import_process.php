<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

function cleanNameForMatching($name) {
    $name = strtolower($name);
    // Replace punctuation with spaces
    $name = preg_replace('/[^\w\s]/u', ' ', $name);
    
    // Degrees to exclude
    $degrees = [
        's', 'pd', 'i', 'kom', 'ag', 'sy', 'se', 'h', 'farm', 'md', 'ma', 'lc', 'st', 'ba', 'dr', 'apt', 'spd', 'mth', 'ss'
    ];
    
    $words = explode(' ', $name);
    $cleaned_words = [];
    foreach ($words as $word) {
        $word = trim($word);
        if (empty($word)) continue;
        if (in_array($word, $degrees)) continue;
        $cleaned_words[] = $word;
    }
    return implode(' ', $cleaned_words);
}

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/views/class_schedules/import.php");
    exit;
}

require_once '../../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/views/class_schedules/import.php");
    exit;
}

$return_filters = isset($_POST['return_filters']) ? $_POST['return_filters'] : '';
$redirect_qs_amp = $return_filters ? "&" . $return_filters : "";

$file_key = isset($_FILES['import_file']) ? 'import_file' : 'csv_file';
if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
    header("Location: " . BASE_URL . "/views/class_schedules/import.php?error=" . urlencode("Upload failed or no file selected") . $redirect_qs_amp);
    exit;
}

$file = $_FILES[$file_key]['tmp_name'];

$db = new Database();
$conn = $db->getConnection();

$successCount = 0;
$errorCount = 0;
$errors = [];
$rowNumber = 0;

try {
    $conn->beginTransaction();

    // Load file using PhpSpreadsheet
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    // Loop starts at 1 to skip header row
    for ($i = 1; $i < count($rows); $i++) {
        $rowNumber = $i + 1;
        $data = $rows[$i];

        // Skip completely empty rows
        if (empty($data) || (!isset($data[0]) && !isset($data[1]) && !isset($data[2]))) {
            continue;
        }

        // If row is partially filled but missing main columns
        if (empty($data[0]) && empty($data[1]) && empty($data[2])) {
            continue;
        }

        if (count($data) < 7) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Kolom tidak lengkap (Hanya " . count($data) . " kolom). Pastikan format file sesuai template.";
            continue;
        }

        $day = trim($data[0] ?? '');
        $unit_name = trim($data[1] ?? '', " \t\n\r\0\x0B\"");
        $grade_name = trim($data[2] ?? '', " \t\n\r\0\x0B\"");
        $subject_name = trim($data[3] ?? '', " \t\n\r\0\x0B\"");
        $teacher_name = trim($data[4] ?? '', " \t\n\r\0\x0B\"");
        $start_period = trim($data[5] ?? '', " \t\n\r\0\x0B\"");
        $end_period = trim($data[6] ?? $data[5] ?? '', " \t\n\r\0\x0B\"");
        $ay_name = trim($data[7] ?? '', " \t\n\r\0\x0B\"");

        // 1. Resolve Academic Year
        $ay_id = null;
        if ($ay_name) {
            $stmt = $conn->prepare("SELECT id FROM academic_years WHERE name = ? LIMIT 1");
            $stmt->execute([$ay_name]);
            $ay_id = $stmt->fetchColumn();
        }
        if (!$ay_id) {
            // Pick active year as fallback
            $ay_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
        }
        if (!$ay_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Tahun Akademik '$ay_name' tidak ditemukan/aktif.";
            continue;
        }

        // 2. Resolve Unit
        $stmt = $conn->prepare("SELECT id FROM education_units WHERE name LIKE ? LIMIT 1");
        $stmt->execute(['%' . $unit_name . '%']);
        $unit_id = $stmt->fetchColumn();
        if (!$unit_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Unit '$unit_name' tidak ditemukan.";
            continue;
        }

        // 3. Resolve Grade Level
        $stmt = $conn->prepare("SELECT id FROM grade_levels WHERE name LIKE ? AND education_unit_id = ? LIMIT 1");
        $stmt->execute(['%' . $grade_name . '%', $unit_id]);
        $grade_id = $stmt->fetchColumn();
        if (!$grade_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Kelas '$grade_name' tidak ditemukan di unit '$unit_name'.";
            continue;
        }

        // 4. Resolve Subject
        $stmt = $conn->prepare("SELECT id FROM subjects WHERE name LIKE ? LIMIT 1");
        $stmt->execute(['%' . $subject_name . '%']);
        $subject_id = $stmt->fetchColumn();
        
        if (!$subject_id) {
            // Fuzzy word-based match fallback
            $search_words = array_filter(explode(' ', strtolower($subject_name)), function($w) {
                return strlen(trim($w)) > 2;
            });
            if (!empty($search_words)) {
                $all_subjects = $conn->query("SELECT id, name FROM subjects")->fetchAll();
                $best_subj_match = null;
                $best_subj_score = 0;
                foreach ($all_subjects as $subj) {
                    $subj_name_lower = strtolower($subj['name']);
                    $matched_words = 0;
                    foreach ($search_words as $word) {
                        if (strpos($subj_name_lower, $word) !== false) {
                            $matched_words++;
                        }
                    }
                    if ($matched_words > 0) {
                        $score = $matched_words / count($search_words);
                        if ($score > $best_subj_score) {
                            $best_subj_score = $score;
                            $best_subj_match = $subj['id'];
                        }
                    }
                }
                $threshold = count($search_words) <= 2 ? 1.0 : 0.75;
                if ($best_subj_score >= $threshold) {
                    $subject_id = $best_subj_match;
                }
            }
        }

        if (!$subject_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Mata Pelajaran '$subject_name' tidak ditemukan.";
            continue;
        }

        // 5. Resolve Teacher (Fuzzy Match)
        $teacher_id = null;
        $search_name = trim($teacher_name);

        if ($search_name) {
            // First attempt: Exact match or simple LIKE
            $stmt = $conn->prepare("SELECT id FROM employees WHERE (full_name = ? OR full_name LIKE ?) AND status = 'active' LIMIT 1");
            $stmt->execute([$search_name, $search_name]);
            $teacher_id = $stmt->fetchColumn();

            if (!$teacher_id) {
                // Second attempt: Replace spaces and punctuation with wildcards (Muadin Lc -> %Muadin%Lc%)
                // Prioritize names that START with the search term (Dian -> Dian Sari instead of Rusdiana)
                $fuzzy_wildcard = preg_replace('/[^\w]/u', '%', $search_name);
                $stmt = $conn->prepare("
                    SELECT id FROM employees 
                    WHERE full_name LIKE :contains 
                      AND status = 'active' 
                    ORDER BY (full_name LIKE :starts) DESC, LENGTH(full_name) ASC 
                    LIMIT 1
                ");
                $stmt->execute([
                    ':contains' => '%' . $fuzzy_wildcard . '%',
                    ':starts' => $fuzzy_wildcard . '%'
                ]);
                $teacher_id = $stmt->fetchColumn();
            }

            if (!$teacher_id) {
                // Third attempt: Try matching the first part of the name (assuming titles/degrees are at the end)
                $name_parts = explode(' ', $search_name);
                if (count($name_parts) > 1) {
                    $first_part = $name_parts[0];
                    if (strlen($first_part) > 3) {
                        $stmt = $conn->prepare("
                            SELECT id FROM employees 
                            WHERE full_name LIKE :contains 
                              AND status = 'active' 
                            ORDER BY (full_name LIKE :starts) DESC, LENGTH(full_name) ASC 
                            LIMIT 1
                        ");
                        $stmt->execute([
                            ':contains' => '%' . $first_part . '%',
                            ':starts' => $first_part . '%'
                        ]);
                        $teacher_id = $stmt->fetchColumn();
                    }
                }
            }

            if (!$teacher_id) {
                // Fourth attempt: Levenshtein distance matching (only active employees)
                $all_teachers = $conn->query("SELECT id, full_name FROM employees WHERE status = 'active'")->fetchAll();
                $clean_search = cleanNameForMatching($search_name);
                
                $best_teacher_match = null;
                $min_distance = 999;
                
                foreach ($all_teachers as $teacher) {
                    $clean_teacher = cleanNameForMatching($teacher['full_name']);
                    $dist = levenshtein($clean_search, $clean_teacher);
                    if ($dist < $min_distance) {
                        $min_distance = $dist;
                        $best_teacher_match = $teacher['id'];
                    }
                }
                
                // Allow distance of <= 2 for cleaned search terms with length > 3
                if ($best_teacher_match && $min_distance <= 2 && strlen($clean_search) > 3) {
                    $teacher_id = $best_teacher_match;
                }
            }
        }

        if (!$teacher_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Guru '$teacher_name' tidak ditemukan.";
            continue;
        }

        // 6. Resolve Periods
        $stmt = $conn->prepare("SELECT id FROM lesson_periods WHERE education_unit_id = ? AND period_number = ? LIMIT 1");
        $stmt->execute([$unit_id, $start_period]);
        $lp_id = $stmt->fetchColumn();

        $stmt->execute([$unit_id, $end_period]);
        $lp_end_id = $stmt->fetchColumn();

        if (!$lp_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Jam pelajaran ke-$start_period tidak ditemukan untuk unit '$unit_name'.";
            continue;
        }

        // --- NEW: Calculate day_of_week ---
        $day_map = [
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
            'Sunday' => 7
        ];
        $day_of_week = $day_map[$day] ?? 0;

        // 7. Duplicate & Conflict Check
        // a. Check if Class/Grade already has a schedule at this time
        $stmtCheck = $conn->prepare("
            SELECT cs.id FROM class_schedules cs
            JOIN lesson_periods lp_start ON cs.lesson_period_id = lp_start.id
            LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
            WHERE cs.academic_year_id = :ay 
              AND cs.grade_level_id = :grade 
              AND cs.day_of_week = :dow 
              AND lp_start.education_unit_id = :unit_id
              AND (
                  (:start_p <= COALESCE(lp_end.period_number, lp_start.period_number) AND :end_p >= lp_start.period_number)
              )
            LIMIT 1
        ");
        $stmtCheck->execute([
            ':ay' => $ay_id,
            ':grade' => $grade_id,
            ':dow' => $day_of_week,
            ':unit_id' => $unit_id,
            ':start_p' => $start_period,
            ':end_p' => $end_period
        ]);

        if ($stmtCheck->fetch()) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Kelas '$grade_name' sudah memiliki jadwal di hari $day jam ke-$start_period s/d $end_period. Baris ini dilewati.";
            continue;
        }

        // b. Check if Teacher (Employee) already has a schedule at this time (prevent uq_teacher_schedule error)
        $stmtTeacherCheck = $conn->prepare("
            SELECT cs.id FROM class_schedules cs
            JOIN lesson_periods lp_start ON cs.lesson_period_id = lp_start.id
            LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
            WHERE cs.academic_year_id = :ay 
              AND cs.employee_id = :emp 
              AND cs.day_of_week = :dow 
              AND lp_start.education_unit_id = :unit_id
              AND (
                  (:start_p <= COALESCE(lp_end.period_number, lp_start.period_number) AND :end_p >= lp_start.period_number)
              )
            LIMIT 1
        ");
        $stmtTeacherCheck->execute([
            ':ay' => $ay_id,
            ':emp' => $teacher_id,
            ':dow' => $day_of_week,
            ':unit_id' => $unit_id,
            ':start_p' => $start_period,
            ':end_p' => $end_period
        ]);

        if ($stmtTeacherCheck->fetch()) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Guru '$teacher_name' sudah memiliki jadwal mengajar lain di hari $day jam ke-$start_period s/d $end_period. Baris ini dilewati.";
            continue;
        }

        // 8. Insert
        $stmt = $conn->prepare("
            INSERT INTO class_schedules (
                academic_year_id, employee_id, subject_id, grade_level_id, 
                lesson_period_id, end_lesson_period_id, day, day_of_week
            ) VALUES (
                :ay, :emp, :sub, :grade, :lp, :lp_end, :day, :dow
            )
        ");
        $stmt->execute([
            ':ay' => $ay_id,
            ':emp' => $teacher_id,
            ':sub' => $subject_id,
            ':grade' => $grade_id,
            ':lp' => $lp_id,
            ':lp_end' => $lp_end_id ?: $lp_id,
            ':day' => $day,
            ':dow' => $day_of_week
        ]);

        $successCount++;
    }

    $conn->commit();
    // fclose($handle); // Removed as we use string processing now


    $msg = "Import Berhasil! $successCount jadwal ditambahkan.";
    if ($errorCount > 0) {
        $msg .= " Terdapat $errorCount baris bermasalah.";
        // Store errors in session to show them?
        $_SESSION['import_errors'] = $errors;
    }

    header("Location: " . BASE_URL . "/views/class_schedules/index.php?success=" . urlencode($msg) . $redirect_qs_amp);
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: " . BASE_URL . "/views/class_schedules/import.php?error=" . urlencode('Error Sistem: ' . $e->getMessage()) . $redirect_qs_amp);
    exit;
}
