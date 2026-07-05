<?php
// api/tahfidz/setup_refactor_db.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

$messages = [];

try {
    // 1. Create memorization_baselines
    $q1 = "CREATE TABLE IF NOT EXISTS `memorization_baselines` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `academic_year_id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `baseline_juz` decimal(5,2) NOT NULL,
        `notes` text DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_acad_student` (`academic_year_id`, `student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($mysqli->query($q1)) {
        $messages[] = "Table 'memorization_baselines' checked/created.";
    } else {
        throw new Exception("Error creating memorization_baselines: " . $mysqli->error);
    }

    // 2. Create semester_snapshots
    $q2 = "CREATE TABLE IF NOT EXISTS `semester_snapshots` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `academic_year_id` int(11) NOT NULL,
        `semester` enum('Ganjil','Genap') NOT NULL,
        `student_id` int(11) NOT NULL,
        `baseline_juz` decimal(5,2) NOT NULL,
        `target_juz` decimal(5,2) NOT NULL,
        `memorized_juz` decimal(5,2) NOT NULL,
        `total_juz` decimal(5,2) NOT NULL,
        `murojaah_total` int(11) DEFAULT 0,
        `tasmi_score` decimal(5,2) DEFAULT 0.00,
        `progress_percentage` decimal(5,2) DEFAULT 0.00,
        `notes` text DEFAULT NULL,
        `generated_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_snapshot` (`academic_year_id`, `semester`, `student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($mysqli->query($q2)) {
        $messages[] = "Table 'semester_snapshots' checked/created.";
    } else {
        throw new Exception("Error creating semester_snapshots: " . $mysqli->error);
    }

    // 3. Create memorization_entries
    $q3 = "CREATE TABLE IF NOT EXISTS `memorization_entries` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `date` date NOT NULL,
        `entry_type` enum('HAFALAN_BARU','MUROJAAH','TASMI','UJIAN') NOT NULL,
        `start_surah_id` int(11) DEFAULT NULL,
        `start_ayah` int(11) DEFAULT NULL,
        `end_surah_id` int(11) DEFAULT NULL,
        `end_ayah` int(11) DEFAULT NULL,
        `line_count` int(11) DEFAULT 0,
        `score` decimal(5,2) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `teacher_id` int(11) DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        -- Compatibility Columns
        `surah_id` int(11) DEFAULT NULL,
        `surah_start` varchar(100) DEFAULT NULL,
        `surah_end` varchar(100) DEFAULT NULL,
        `total_baris` int(11) DEFAULT 0,
        `juz` int(11) DEFAULT NULL,
        `status` varchar(50) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($mysqli->query($q3)) {
        $messages[] = "Table 'memorization_entries' checked/created.";
    } else {
        throw new Exception("Error creating memorization_entries: " . $mysqli->error);
    }

    // 4. Perform Data Migration from tahfidz_memorization if memorization_entries is empty
    $check_entries = $mysqli->query("SELECT COUNT(*) FROM memorization_entries");
    $count = $check_entries->fetch_row()[0];
    
    if ($count == 0) {
        // Load Surah mapping
        $surat_json_path = __DIR__ . '/../quran/surat.json';
        $surah_map = [];
        if (file_exists($surat_json_path)) {
            $surat_data = json_decode(file_get_contents($surat_json_path), true);
            if (isset($surat_data['data'])) {
                foreach ($surat_data['data'] as $surah) {
                    // Clean and standardize Latin names for matching
                    $key = strtolower(trim(str_replace(["'", "-", " "], "", $surah['namaLatin'])));
                    $surah_map[$key] = $surah['nomor'];
                }
            }
        }
        
        // Helper function to map surah name string to ID
        $get_surah_id = function($name) use ($surah_map) {
            if (empty($name)) return null;
            if (is_numeric($name)) return (int)$name;
            
            $clean_name = strtolower(trim(str_replace(["'", "-", " "], "", $name)));
            
            // Try direct mapping
            if (isset($surah_map[$clean_name])) {
                return $surah_map[$clean_name];
            }
            
            // Try fuzzy matching (partial match)
            foreach ($surah_map as $latin => $num) {
                if (strpos($latin, $clean_name) !== false || strpos($clean_name, $latin) !== false) {
                    return $num;
                }
            }
            
            return null; // Fallback
        };

        $res = $mysqli->query("SELECT * FROM tahfidz_memorization");
        $migrated = 0;
        
        if ($res) {
            $insert_stmt = $mysqli->prepare("INSERT INTO memorization_entries 
                (student_id, date, entry_type, start_surah_id, start_ayah, end_surah_id, end_ayah, line_count, notes, teacher_id, created_at, surah_id, surah_start, surah_end, total_baris, juz, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
            while ($row = $res->fetch_assoc()) {
                $student_id = $row['student_id'];
                $date = $row['date'];
                
                // Status maps
                $old_status = $row['status'];
                $entry_type = (strcasecmp($old_status, 'Murajaah') === 0) ? 'MUROJAAH' : 'HAFALAN_BARU';
                
                $start_surah_id = $get_surah_id($row['surah_start']);
                $end_surah_id = $get_surah_id($row['surah_end']);
                if ($end_surah_id === null) $end_surah_id = $start_surah_id;
                
                $start_ayah = $row['ayat_start'];
                $end_ayah = $row['ayat_end'];
                
                $line_count = $row['total_baris'];
                $notes = $row['notes'];
                $teacher_id = $row['teacher_id'];
                $created_at = $row['created_at'];
                
                // Compatibility fields
                $surah_id = $start_surah_id;
                $surah_start = $row['surah_start'];
                $surah_end = $row['surah_end'];
                $total_baris = $row['total_baris'];
                $juz = $row['juz'];
                $status = $row['status'];
                
                $insert_stmt->bind_param("issiiiiisissisiis",
                    $student_id,
                    $date,
                    $entry_type,
                    $start_surah_id,
                    $start_ayah,
                    $end_surah_id,
                    $end_ayah,
                    $line_count,
                    $notes,
                    $teacher_id,
                    $created_at,
                    $surah_id,
                    $surah_start,
                    $surah_end,
                    $total_baris,
                    $juz,
                    $status
                );
                
                if ($insert_stmt->execute()) {
                    $migrated++;
                }
            }
            $insert_stmt->close();
            $messages[] = "Successfully migrated $migrated records from 'tahfidz_memorization' to 'memorization_entries'.";
        }
    } else {
        $messages[] = "Table 'memorization_entries' already has data. Migration skipped.";
    }

    echo json_encode([
        "success" => true,
        "messages" => $messages,
        "detail" => "Tahfidz refactor tables setup and migration completed successfully."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
