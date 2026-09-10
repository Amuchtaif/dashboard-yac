<?php
// logic/calendar/save_event.php
require_once '../../config/app.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

check_login();

$user_id = $_SESSION['user_id'] ?? null;
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (empty($data['title']) || empty($data['start_date'])) {
    echo json_encode(['success' => false, 'message' => 'Judul dan Tanggal Mulai wajib diisi']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Fetch active academic year if not provided
$ay_id = !empty($data['academic_year_id']) ? (int)$data['academic_year_id'] : null;
if (!$ay_id) {
    $ayStmt = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
    if ($ayStmt) {
        $ay_id = $ayStmt->fetchColumn() ?: null;
    }
}

$title = trim($data['title']);
$description = isset($data['description']) ? trim($data['description']) : '';
$start_date = trim($data['start_date']);
$end_date = !empty($data['end_date']) ? trim($data['end_date']) : $start_date;
$start_time = !empty($data['start_time']) ? trim($data['start_time']) : null;
$end_time = !empty($data['end_time']) ? trim($data['end_time']) : null;
$location = isset($data['location']) ? trim($data['location']) : '';
$category = !empty($data['category']) ? trim($data['category']) : 'Kegiatan';
$unit_id = !empty($data['unit_id']) ? (int)$data['unit_id'] : null;
$source_type = !empty($data['source_type']) ? trim($data['source_type']) : 'bidang_pendidikan';
if ($unit_id) {
    $source_type = 'unit';
}
$is_holiday = isset($data['is_holiday']) ? (int)$data['is_holiday'] : (in_array($category, ['Libur Nasional', 'Libur Sekolah', 'Cuti Bersama']) ? 1 : 0);

// Visibility: 'public' (Semua User) or 'internal' (Hanya URL agenda-pendidikan)
$visibility = (!empty($data['visibility']) && in_array($data['visibility'], ['public', 'internal'])) ? $data['visibility'] : 'public';

// Recurrence options
$is_recurring = !empty($data['is_recurring']) ? 1 : 0;
$recurrence_type = ($is_recurring && !empty($data['recurrence_type']) && in_array($data['recurrence_type'], ['daily', 'weekly', 'monthly', 'yearly'])) 
    ? $data['recurrence_type'] : 'none';

$recurrence_rule_array = [];
if ($is_recurring && $recurrence_type !== 'none') {
    $recurrence_rule_array['type'] = $recurrence_type;
    $recurrence_rule_array['repeat_until'] = !empty($data['repeat_until']) ? trim($data['repeat_until']) : date('Y-m-d', strtotime("$start_date + 6 months"));
    
    if ($recurrence_type === 'weekly') {
        $days = [];
        if (!empty($data['recurrence_days']) && is_array($data['recurrence_days'])) {
            foreach ($data['recurrence_days'] as $d) {
                $dn = (int)$d;
                if ($dn >= 1 && $dn <= 7) $days[] = $dn;
            }
        }
        if (empty($days)) {
            $days = [(int)date('N', strtotime($start_date))];
        }
        sort($days);
        $recurrence_rule_array['days'] = $days;
    } elseif ($recurrence_type === 'monthly') {
        $monthly_mode = (!empty($data['monthly_mode']) && $data['monthly_mode'] === 'nth_day') ? 'nth_day' : 'date';
        $recurrence_rule_array['monthly_mode'] = $monthly_mode;
        if ($monthly_mode === 'nth_day') {
            $recurrence_rule_array['week_num'] = !empty($data['recurrence_week_num']) ? (int)$data['recurrence_week_num'] : 1; // 1-4, 5=last
            $recurrence_rule_array['day_of_week'] = !empty($data['recurrence_day_of_week']) ? (int)$data['recurrence_day_of_week'] : (int)date('N', strtotime($start_date));
        } else {
            $recurrence_rule_array['day_of_month'] = (int)date('d', strtotime($start_date));
        }
    } elseif ($recurrence_type === 'yearly') {
        $recurrence_rule_array['month'] = !empty($data['recurrence_month']) ? (int)$data['recurrence_month'] : (int)date('m', strtotime($start_date));
        $yearly_mode = (!empty($data['yearly_mode']) && $data['yearly_mode'] === 'nth_day') ? 'nth_day' : 'date';
        $recurrence_rule_array['yearly_mode'] = $yearly_mode;
        if ($yearly_mode === 'nth_day') {
            $recurrence_rule_array['week_num'] = !empty($data['recurrence_week_num']) ? (int)$data['recurrence_week_num'] : 1;
            $recurrence_rule_array['day_of_week'] = !empty($data['recurrence_day_of_week']) ? (int)$data['recurrence_day_of_week'] : (int)date('N', strtotime($start_date));
        } else {
            $recurrence_rule_array['day_of_month'] = (int)date('d', strtotime($start_date));
        }
    }
}

$recurrence_rule_json = !empty($recurrence_rule_array) ? json_encode($recurrence_rule_array) : null;

// Helper: Find Nth weekday of a month
function find_nth_weekday_of_month($year, $month, $week_num, $day_of_week) {
    $first_day_ts = strtotime(sprintf('%04d-%02d-01', $year, $month));
    $first_day_weekday = (int)date('N', $first_day_ts); // 1 (Mon) - 7 (Sun)
    
    // Days until first target weekday
    $offset = ($day_of_week - $first_day_weekday + 7) % 7;
    $first_target_day = 1 + $offset;
    
    if ($week_num <= 4) {
        $target_day = $first_target_day + ($week_num - 1) * 7;
        $days_in_month = (int)date('t', $first_day_ts);
        if ($target_day <= $days_in_month) {
            return sprintf('%04d-%02d-%02d', $year, $month, $target_day);
        }
        return null;
    } else {
        // Week num 5 means 'Terakhir' (last occurrence of that weekday in the month)
        $days_in_month = (int)date('t', $first_day_ts);
        for ($d = $days_in_month; $d >= 1; $d--) {
            if ((int)date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d))) === $day_of_week) {
                return sprintf('%04d-%02d-%02d', $year, $month, $d);
            }
        }
        return null;
    }
}

// Helper: Generate list of start dates for recurrence
function generate_recurrence_dates($start_date, $rule_array, $max_occurrences = 100) {
    $dates = [];
    $type = $rule_array['type'] ?? 'daily';
    $repeat_until = $rule_array['repeat_until'] ?? date('Y-m-d', strtotime("$start_date + 6 months"));
    
    $cur_ts = strtotime($start_date);
    $end_ts = strtotime($repeat_until);
    if ($end_ts < $cur_ts) {
        return [$start_date];
    }
    
    // Always include the initial date
    $dates[] = $start_date;
    
    if ($type === 'daily') {
        $cur_ts = strtotime("+1 day", $cur_ts);
        while ($cur_ts <= $end_ts && count($dates) < $max_occurrences) {
            $dates[] = date('Y-m-d', $cur_ts);
            $cur_ts = strtotime("+1 day", $cur_ts);
        }
    } elseif ($type === 'weekly') {
        $target_days = $rule_array['days'] ?? [(int)date('N', $cur_ts)];
        $cur_ts = strtotime("+1 day", $cur_ts);
        while ($cur_ts <= $end_ts && count($dates) < $max_occurrences) {
            $w = (int)date('N', $cur_ts);
            if (in_array($w, $target_days)) {
                $dates[] = date('Y-m-d', $cur_ts);
            }
            $cur_ts = strtotime("+1 day", $cur_ts);
        }
    } elseif ($type === 'monthly') {
        $monthly_mode = $rule_array['monthly_mode'] ?? 'date';
        $start_year = (int)date('Y', $cur_ts);
        $start_month = (int)date('m', $cur_ts);
        $end_year = (int)date('Y', $end_ts);
        $end_month = (int)date('m', $end_ts);
        
        $y = $start_year;
        $m = $start_month;
        
        while (($y < $end_year || ($y == $end_year && $m <= $end_month)) && count($dates) < $max_occurrences) {
            // Next month
            $m++;
            if ($m > 12) {
                $m = 1;
                $y++;
            }
            if ($y > $end_year || ($y == $end_year && $m > $end_month)) break;
            
            if ($monthly_mode === 'date') {
                $day_of_month = $rule_array['day_of_month'] ?? (int)date('d', $cur_ts);
                $days_in_month = (int)date('t', strtotime(sprintf('%04d-%02d-01', $y, $m)));
                $actual_day = min($day_of_month, $days_in_month);
                $target_date = sprintf('%04d-%02d-%02d', $y, $m, $actual_day);
            } else {
                $week_num = $rule_array['week_num'] ?? 1;
                $day_of_week = $rule_array['day_of_week'] ?? 1;
                $target_date = find_nth_weekday_of_month($y, $m, $week_num, $day_of_week);
            }
            
            if ($target_date && $target_date > $start_date && $target_date <= $repeat_until) {
                $dates[] = $target_date;
            }
        }
    } elseif ($type === 'yearly') {
        $target_month = $rule_array['month'] ?? (int)date('m', $cur_ts);
        $yearly_mode = $rule_array['yearly_mode'] ?? 'date';
        $start_year = (int)date('Y', $cur_ts);
        $end_year = (int)date('Y', $end_ts);
        
        for ($y = $start_year; $y <= $end_year; $y++) {
            if ($yearly_mode === 'date') {
                $day_of_month = $rule_array['day_of_month'] ?? (int)date('d', $cur_ts);
                $days_in_month = (int)date('t', strtotime(sprintf('%04d-%02d-01', $y, $target_month)));
                $actual_day = min($day_of_month, $days_in_month);
                $target_date = sprintf('%04d-%02d-%02d', $y, $target_month, $actual_day);
            } else {
                $week_num = $rule_array['week_num'] ?? 1;
                $day_of_week = $rule_array['day_of_week'] ?? 1;
                $target_date = find_nth_weekday_of_month($y, $target_month, $week_num, $day_of_week);
            }
            
            if ($target_date && $target_date > $start_date && $target_date <= $repeat_until) {
                $dates[] = $target_date;
            }
        }
    }
    
    return array_unique($dates);
}

try {
    $duration_days = max(0, (int)round((strtotime($end_date) - strtotime($start_date)) / 86400));
    $update_scope = !empty($data['update_scope']) ? $data['update_scope'] : 'single';

    if (!empty($data['id'])) {
        // --- UPDATE ---
        $stmtCheck = $conn->prepare("SELECT id, repeat_group_id, is_recurring FROM academic_calendar WHERE id = ?");
        $stmtCheck->execute([$data['id']]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Kegiatan tidak ditemukan']);
            exit;
        }

        if ($update_scope === 'series' && !empty($existing['repeat_group_id'])) {
            // Update common metadata across entire recurrence series
            $sql = "UPDATE academic_calendar SET 
                    title = :title, 
                    description = :description, 
                    start_time = :start_time,
                    end_time = :end_time,
                    location = :location,
                    category = :category, 
                    source_type = :source_type,
                    unit_id = :unit_id,
                    visibility = :visibility,
                    is_holiday = :is_holiday,
                    updated_by = :updated_by
                    WHERE repeat_group_id = :repeat_group_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':repeat_group_id', $existing['repeat_group_id']);
            $stmt->bindParam(':updated_by', $user_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':source_type', $source_type);
            $stmt->bindParam(':unit_id', $unit_id);
            $stmt->bindParam(':visibility', $visibility);
            $stmt->bindParam(':is_holiday', $is_holiday);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Seluruh rangkaian kegiatan berulang berhasil diperbarui']);
            exit;
        } else {
            // Update single event
            $sql = "UPDATE academic_calendar SET 
                    title = :title, 
                    description = :description, 
                    start_date = :start_date, 
                    end_date = :end_date, 
                    start_time = :start_time,
                    end_time = :end_time,
                    location = :location,
                    category = :category, 
                    source_type = :source_type,
                    unit_id = :unit_id,
                    visibility = :visibility,
                    is_holiday = :is_holiday,
                    updated_by = :updated_by
                    WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':updated_by', $user_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':source_type', $source_type);
            $stmt->bindParam(':unit_id', $unit_id);
            $stmt->bindParam(':visibility', $visibility);
            $stmt->bindParam(':is_holiday', $is_holiday);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Kegiatan berhasil diperbarui']);
            exit;
        }
    } else {
        // --- INSERT ---
        if ($is_recurring && $recurrence_type !== 'none' && !empty($recurrence_rule_array)) {
            // Recurring Event: generate dates and insert batch
            $repeat_group_id = 'grp_' . bin2hex(random_bytes(10));
            $dates = generate_recurrence_dates($start_date, $recurrence_rule_array);

            $sql = "INSERT INTO academic_calendar 
                    (title, description, start_date, end_date, start_time, end_time, location, category, source_type, unit_id, academic_year_id, visibility, status, is_holiday, is_recurring, recurrence_type, recurrence_rule, repeat_group_id, created_by, updated_by) 
                    VALUES 
                    (:title, :description, :start_date, :end_date, :start_time, :end_time, :location, :category, :source_type, :unit_id, :academic_year_id, :visibility, 'Published', :is_holiday, 1, :recurrence_type, :recurrence_rule, :repeat_group_id, :created_by, :updated_by)";
            $stmt = $conn->prepare($sql);

            $conn->beginTransaction();
            foreach ($dates as $occ_start) {
                $occ_end = date('Y-m-d', strtotime("$occ_start + $duration_days days"));
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':start_date' => $occ_start,
                    ':end_date' => $occ_end,
                    ':start_time' => $start_time,
                    ':end_time' => $end_time,
                    ':location' => $location,
                    ':category' => $category,
                    ':source_type' => $source_type,
                    ':unit_id' => $unit_id,
                    ':academic_year_id' => $ay_id,
                    ':visibility' => $visibility,
                    ':is_holiday' => $is_holiday,
                    ':recurrence_type' => $recurrence_type,
                    ':recurrence_rule' => $recurrence_rule_json,
                    ':repeat_group_id' => $repeat_group_id,
                    ':created_by' => $user_id,
                    ':updated_by' => $user_id
                ]);
            }
            $conn->commit();

            $total_created = count($dates);
            echo json_encode([
                'success' => true, 
                'message' => "Agenda berulang berhasil disimpan ({$total_created} kegiatan dibuat)"
            ]);
            exit;
        } else {
            // Single Non-recurring Event
            $sql = "INSERT INTO academic_calendar 
                    (title, description, start_date, end_date, start_time, end_time, location, category, source_type, unit_id, academic_year_id, visibility, status, is_holiday, is_recurring, recurrence_type, created_by, updated_by) 
                    VALUES 
                    (:title, :description, :start_date, :end_date, :start_time, :end_time, :location, :category, :source_type, :unit_id, :academic_year_id, :visibility, 'Published', :is_holiday, 0, 'none', :created_by, :updated_by)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':academic_year_id', $ay_id);
            $stmt->bindParam(':created_by', $user_id);
            $stmt->bindParam(':updated_by', $user_id);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':source_type', $source_type);
            $stmt->bindParam(':unit_id', $unit_id);
            $stmt->bindParam(':visibility', $visibility);
            $stmt->bindParam(':is_holiday', $is_holiday);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Kegiatan berhasil ditambahkan']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database']);
            }
            exit;
        }
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
