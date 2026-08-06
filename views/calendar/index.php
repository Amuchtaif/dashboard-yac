<?php
// views/calendar/index.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Kalender Akademik";

$db = new Database();
$conn = $db->getConnection();

// --- Filters & Navigation ---
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$first_day_ts = strtotime("$year-$month-01");
$days_in_month = date('t', $first_day_ts);
$start_day_of_week = date('w', $first_day_ts); // 0 (Sun) to 6 (Sat)

// Indonesian Month Names
$indo_months = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April", 5 => "Mei", 6 => "Juni",
    7 => "Juli", 8 => "Agustus", 9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
];
$month_name = $indo_months[$month];

// Previous and Next month links
$prev_month = $month == 1 ? 12 : $month - 1;
$prev_year = $month == 1 ? $year - 1 : $year;
$next_month = $month == 12 ? 1 : $month + 1;
$next_year = $month == 12 ? $year + 1 : $year;

// --- Fetch Public Holidays (libur.deno.dev API) ---
function get_public_holidays($year) {
    if (!$year) $year = date('Y');
    $cache_file = "../../tmp/holidays_$year.json";
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 86400) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if (is_array($cached_data) && isset($cached_data['data'])) {
            return $cached_data['data'];
        }
        return [];
    }
    
    $url = "https://api-hari-libur.vercel.app/api?year=$year";
    
    // Try file_get_contents first
    $json = @file_get_contents($url);
    
    // Fallback to CURL if file_get_contents is disabled
    if (!$json && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $json = curl_exec($ch);
    }

    if ($json) {
        if (!is_dir("../../tmp")) mkdir("../../tmp", 0777, true);
        file_put_contents($cache_file, $json);
        $decoded_data = json_decode($json, true);
        if (is_array($decoded_data) && isset($decoded_data['data'])) {
            return $decoded_data['data'];
        }
    }
    return [];
}

$api_holidays = get_public_holidays($year);

// --- Fetch Events for this month ---
$first_of_month = date('Y-m-d', $first_day_ts);
$last_of_month = date('Y-m-t', $first_day_ts);

$query = "SELECT * FROM academic_calendar 
          WHERE (start_date <= :last_day AND (end_date >= :first_day OR end_date IS NULL)) 
          ORDER BY start_date ASC";
$stmt = $conn->prepare($query);
$stmt->execute([':first_day' => $first_of_month, ':last_day' => $last_of_month]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map API holidays to events structure
$public_events = [];
for ($i = $year - 1; $i <= $year + 1; $i++) {
    $api_year_holidays = get_public_holidays($i);
    if (is_array($api_year_holidays)) {
        foreach ($api_year_holidays as $h) {
            if (isset($h['date']) && isset($h['description'])) {
                $public_events[] = [
                    'id' => 'api_' . md5($h['date'] . $h['description']),
                    'title' => $h['description'],
                    'start_date' => $h['date'],
                    'end_date' => $h['date'],
                    'category' => 'Libur Nasional',
                    'description' => 'Hari Libur Nasional (API)',
                    'is_api' => true
                ];
            }
        }
    }
}

// Map events to days for the grid
$days_events = [];
for ($i = 1; $i <= $days_in_month; $i++) {
    $current_date = sprintf('%04d-%02d-%02d', $year, $month, $i);
    
    // Combined local events and API holidays
    $local_day_events = array_filter($events, function($e) use ($current_date) {
        return $current_date >= $e['start_date'] && $current_date <= ($e['end_date'] ?: $e['start_date']);
    });
    
    $api_day_events = array_filter($public_events, function($e) use ($current_date) {
        return $current_date == $e['start_date'];
    });
    
    $days_events[$i] = array_merge($local_day_events, $api_day_events);
}

// Upcoming events for the ACTIVE month only
$current_month_start = sprintf('%04d-%02d-01', $year, $month);
$current_month_end = date('Y-m-t', strtotime($current_month_start));

$upcoming_query = "SELECT * FROM academic_calendar 
                   WHERE (start_date <= :end AND (end_date >= :start OR end_date IS NULL))
                   ORDER BY start_date ASC";
$upcoming_stmt = $conn->prepare($upcoming_query);
$upcoming_stmt->execute([':start' => $current_month_start, ':end' => $current_month_end]);
$upcoming_db_events = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

// Merge with upcoming API holidays for the active month
$upcoming_api = array_filter($public_events, function($e) use ($current_month_start, $current_month_end) {
    return $e['start_date'] >= $current_month_start && $e['start_date'] <= $current_month_end;
});

$upcoming_holidays = array_merge($upcoming_db_events, $upcoming_api);
usort($upcoming_holidays, function($a, $b) {
    return strcmp($a['start_date'], $b['start_date']);
});

// Remove duplicates (merged db and api might have same dates)
$temp_unique = [];
foreach($upcoming_holidays as $u) { $temp_unique[$u['start_date'].$u['title']] = $u; }
$upcoming_holidays = array_values($temp_unique);

include '../layouts/header.php';
?>

<style>
    /* Premium Calendar Styling */
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background-color: #fff;
        gap: 1px;
        background-color: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 0 0 1.5rem 1.5rem;
        overflow: hidden;
    }
    .calendar-day-header {
        padding: 1rem 0.5rem;
        text-align: center;
        background-color: #f8fafc;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.65rem;
        letter-spacing: 0.1em;
        color: #94a3b8;
        border-bottom: 1px solid #e2e8f0;
    }
    .calendar-cell {
        min-height: 120px;
        padding: 0.75rem;
        background-color: #fff;
        position: relative;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .calendar-cell:hover {
        background-color: #f8fafc;
        z-index: 10;
    }
    .calendar-cell.today {
        background-color: #f0f9ff;
    }
    .calendar-cell.today::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(to right, #06b6d4, #3b82f6);
    }
    .calendar-date-num {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1e293b;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
    }
    .calendar-cell.today .calendar-date-num {
        background: #0ea5e9;
        color: #fff;
        box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
    }
    .calendar-cell.sunday .calendar-date-num {
        color: #ef4444;
    }
    .calendar-cell.sunday.today .calendar-date-num {
        color: #fff;
    }
    
    .event-pill {
        font-size: 10px;
        padding: 4px 8px;
        border-radius: 8px;
        font-weight: 700;
        line-height: 1.2;
        transition: all 0.2s;
        cursor: pointer;
        border: 1px solid transparent;
        white-space: normal;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .event-pill:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        filter: brightness(0.95);
    }
    
    /* Elegant Category Colors */
    .bg-holiday { background-color: #ffefef; color: #dc2626; border-color: #fecaca; }
    .bg-school-holiday { background-color: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
    .bg-collective { background-color: #fffbeb; color: #d97706; border-color: #fef3c7; }
    .bg-academic { background-color: #f0f9ff; color: #0284c7; border-color: #bae6fd; }
    .bg-meeting { background-color: #f5f3ff; color: #7c3aed; border-color: #ddd6fe; }
    .bg-yayasan { background-color: #f0fdfa; color: #0d9488; border-color: #ccfbf1; }
    .bg-other { background-color: #f8fafc; color: #475569; border-color: #e2e8f0; }

    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 1);
    }

    /* Custom Select Component */
    .custom-select-container {
        position: relative;
        width: 100%;
    }
    .custom-select-trigger {
        cursor: pointer;
        display: flex;
        align-items: center;
        width: 100%;
        padding: 0.875rem 1rem 0.875rem 2.75rem;
        border-radius: 1rem;
        background-color: rgba(248, 250, 252, 0.3);
        border: 1px solid #e2e8f0;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        font-weight: 700;
        font-size: 0.875rem;
        color: #334155;
    }
    .custom-select-container.active .custom-select-trigger {
        border-color: #06b6d4;
        box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
        background-color: #fff;
    }
    .custom-select-options {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        background: #fff;
        border-radius: 1.25rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        padding: 0.5rem;
        z-index: 50;
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .custom-select-container.active .custom-select-options {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    .custom-select-option {
        padding: 0.75rem 1rem;
        border-radius: 0.875rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #475569;
        transition: all 0.15s;
    }
    .custom-select-option:hover {
        background-color: #f1f5f9;
        color: #0ea5e9;
    }
    .custom-select-option.selected {
        background-color: #f0f9ff;
        color: #0369a1;
    }
    .color-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    /* Tooltip Styling */
    .tooltip-container {
        position: relative;
    }
    .custom-tooltip {
        position: absolute;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%) translateY(10px);
        background: #1e293b;
        color: #fff;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 600;
        white-space: normal;
        min-width: 150px;
        max-width: 250px;
        z-index: 100;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: none;
        text-align: center;
    }
    .tooltip-container:hover .custom-tooltip {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(0);
    }
    .custom-tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #1e293b transparent transparent transparent;
    }
</style>

<div class="pb-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Main Calendar (Left) -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
                <!-- Calendar Header -->
                <div class="p-6 flex items-center justify-between border-b border-slate-100">
                    <h2 class="text-xl font-bold text-slate-800"><?php echo $month_name . " " . $year; ?></h2>
                    <div class="flex items-center gap-2">
                        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="p-2 hover:bg-slate-100 rounded-lg transition-all">
                            <i class="fa-solid fa-chevron-left h-5 w-5 text-slate-500"></i>
                        </a>
                        <a href="index.php" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg transition-all">Bulan Ini</a>
                        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="p-2 hover:bg-slate-100 rounded-lg transition-all">
                            <i class="fa-solid fa-chevron-right h-5 w-5 text-slate-500"></i>
                        </a>
                    </div>
                </div>

                <!-- Calendar Grid Wrapper for Responsiveness -->
                <div class="overflow-x-auto">
                    <div class="calendar-grid min-w-[700px] md:min-w-full">
                        <?php 
                        $headers = ["AHAD", "SEN", "SEL", "RAB", "KAM", "JUM", "SAB"];
                        foreach ($headers as $h) echo "<div class='calendar-day-header'>$h</div>";

                        // Empty cells for padding
                        for ($i = 0; $i < $start_day_of_week; $i++) {
                            echo "<div class='calendar-cell bg-slate-50/30'></div>";
                        }

                        // Actual days
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $isToday = ($day == (int)date('d') && $month == (int)date('m') && $year == (int)date('Y'));
                            $isSunday = (($day + $start_day_of_week - 1) % 7 == 0);
                            $class = "calendar-cell" . ($isToday ? " today" : "") . ($isSunday ? " sunday" : "");
                            
                            echo "<div class='$class'>";
                            echo "<div class='calendar-date-num'>$day</div>";
                            
                            // Render simple markers for events with Tooltip
                            foreach ($days_events[$day] as $ev) {
                                $catClass = "bg-other";
                                if ($ev['category'] == 'Libur Nasional') $catClass = "bg-holiday";
                                if ($ev['category'] == 'Libur Sekolah') $catClass = "bg-school-holiday";
                                if ($ev['category'] == 'Cuti Bersama') $catClass = "bg-collective";
                                if ($ev['category'] == 'Akademik') $catClass = "bg-academic";
                                if ($ev['category'] == 'Rapat') $catClass = "bg-meeting";
                                if ($ev['category'] == 'Kegiatan Yayasan') $catClass = "bg-yayasan";
                                
                                $title = htmlspecialchars($ev['title']);
                                echo "
                                <div class='tooltip-container'>
                                    <div onclick='editEvent(".json_encode($ev).")' class='event-pill $catClass'>$title</div>
                                    <div class='custom-tooltip'>$title</div>
                                </div>";
                            }
                            
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Desktop Legend -->
                <div class="p-6 bg-slate-50 border-t border-slate-100 flex flex-wrap gap-8 items-center justify-center sm:justify-start">
                    <div class="flex items-center gap-3">
                        <span class="w-3.5 h-3.5 rounded-full bg-red-500 shadow-sm"></span>
                        <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-widest">LIBUR NASIONAL</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-3.5 h-3.5 rounded-full bg-green-500 shadow-sm"></span>
                        <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-widest">LIBUR SEKOLAH</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-3.5 h-3.5 rounded-full bg-yellow-500 shadow-sm"></span>
                        <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-widest">CUTI BERSAMA</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-3.5 h-3.5 rounded-full bg-indigo-500 shadow-sm"></span>
                        <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-widest">RAPAT</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-3.5 h-3.5 rounded-full bg-teal-500 shadow-sm"></span>
                        <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-widest">KEGIATAN YAYASAN</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar (Right) -->
        <div class="lg:col-span-4 space-y-6">
            
            <!-- Add New Form Card (Enhanced UI/UX) -->
            <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 transition-all hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)]">
                <div class="px-8 pt-8 pb-5 border-b border-slate-50 bg-gradient-to-r from-slate-50/50 to-white">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-cyan-600 shadow-sm border border-cyan-100/50">
                            <i class="fa-solid fa-circle-plus h-6 w-6"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-800 tracking-tight" id="sideHeaderTitle">Tambah Kegiatan</h3>
                            <p class="text-[11px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Agenda Baru Akademik</p>
                        </div>
                    </div>
                </div>

                <form id="sideForm" onsubmit="saveEventSide(event)" class="px-8 pb-8 space-y-7">
                    <input type="hidden" name="id" id="sideFormID">
                    <!-- Title Input -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2.5 group-focus-within:text-cyan-600 transition-colors">Nama Kegiatan</label>
                        <div class="relative transition-all">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fa-solid fa-pen-to-square h-5 w-5 text-slate-300 group-focus-within:text-cyan-400 transition-colors"></i>
                            </div>
                            <input type="text" name="title" id="sideFormTitle" required placeholder="Contoh: Rapat Kerja Kurikulum"
                                class="w-full pl-11 rounded-2xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 bg-slate-50/30 text-sm py-3.5 font-semibold text-slate-700 placeholder:text-slate-300 transition-all">
                        </div>
                    </div>

                    <!-- Date Selection -->
                    <div class="grid grid-cols-2 gap-5">
                        <div class="group">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2.5 group-focus-within:text-cyan-600 transition-colors">Tanggal Mulai</label>
                            <input type="date" name="start_date" id="sideFormStartDate" required
                                class="w-full rounded-2xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 bg-slate-50/30 text-sm py-3.5 font-semibold text-slate-700 transition-all">
                        </div>
                        <div class="group">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2.5 group-focus-within:text-cyan-600 transition-colors">Tanggal Selesai</label>
                            <input type="date" name="end_date" id="sideFormEndDate"
                                class="w-full rounded-2xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 bg-slate-50/30 text-sm py-3.5 font-semibold text-slate-700 transition-all">
                        </div>
                    </div>

                    <!-- Kategori Select (Custom UI) -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2.5 group-focus-within:text-cyan-600 transition-colors">Kategori Kegiatan</label>
                        <div class="custom-select-container" id="categorySelectSidebar">
                            <input type="hidden" name="category" id="categoryInputSidebar" value="Libur Nasional">
                            <div class="custom-select-trigger transition-all">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-grip h-5 w-5 text-slate-300 group-focus-within:text-cyan-400 transition-colors"></i>
                                </div>
                                <span class="selected-text flex items-center gap-2">
                                    <span class="color-dot bg-red-500"></span>
                                    Libur Nasional
                                </span>
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform"></i>
                                </div>
                            </div>
                            <div class="custom-select-options">
                                <div class="custom-select-option selected" data-value="Libur Nasional" data-label="Libur Nasional" data-color="bg-red-500">
                                    <span class="color-dot bg-red-500"></span> Libur Nasional
                                </div>
                                <div class="custom-select-option" data-value="Libur Sekolah" data-label="Libur Sekolah" data-color="bg-green-500">
                                    <span class="color-dot bg-green-500"></span> Libur Sekolah
                                </div>
                                <div class="custom-select-option" data-value="Cuti Bersama" data-label="Cuti Bersama" data-color="bg-yellow-500">
                                    <span class="color-dot bg-yellow-500"></span> Cuti Bersama
                                </div>
                                <div class="custom-select-option" data-value="Akademik" data-label="Akademik" data-color="bg-blue-500">
                                    <span class="color-dot bg-blue-500"></span> Akademik
                                </div>
                                <div class="custom-select-option" data-value="Rapat" data-label="Rapat" data-color="bg-indigo-500">
                                    <span class="color-dot bg-indigo-500"></span> Rapat
                                </div>
                                <div class="custom-select-option" data-value="Kegiatan Yayasan" data-label="Kegiatan Yayasan" data-color="bg-teal-500">
                                    <span class="color-dot bg-teal-500"></span> Kegiatan Yayasan
                                </div>
                                <div class="custom-select-option" data-value="Kegiatan" data-label="Kegiatan Sekolah" data-color="bg-purple-500">
                                    <span class="color-dot bg-purple-500"></span> Kegiatan Sekolah
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2 flex flex-col gap-3">
                        <button type="submit" class="group/btn relative w-full inline-flex items-center justify-center px-8 py-4 font-bold text-white transition-all duration-200 bg-cyan-600 rounded-2xl hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-500/20 active:scale-[0.97] overflow-hidden">
                            <span class="absolute inset-0 w-full h-full bg-gradient-to-br from-white/10 to-transparent"></span>
                            <span class="relative flex items-center gap-2">
                                <i class="fa-solid fa-check h-5 w-5 group-hover/btn:translate-x-1 transition-transform"></i>
                                <span id="sideBtnText">Simpan Kegiatan</span>
                            </span>
                        </button>
                        
                        <div class="flex gap-2">
                             <button type="button" id="deleteBtn" onclick="deleteEventAjax()" class="hidden flex-1 py-3 text-sm font-bold text-red-500 hover:bg-red-50 rounded-xl transition-colors border border-red-100">
                                Hapus
                            </button>
                            <button type="button" id="cancelEditBtn" onclick="resetSideForm()" class="hidden flex-1 py-3 text-sm font-bold text-slate-400 hover:bg-slate-50 rounded-xl transition-colors border border-slate-100">
                                Batal
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Upcoming Holidays List -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800">Kegiatan Terdekat</h3>
                    <span class="text-[10px] font-bold bg-slate-100 text-slate-500 px-2 py-1 rounded uppercase tracking-wider"><?php echo strtoupper($month_name . " " . $year); ?></span>
                </div>
                <div class="p-0">
                    <?php if (count($upcoming_holidays) > 0): ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($upcoming_holidays as $upcoming): 
                                $upCatColor = "text-slate-400";
                                if ($upcoming['category'] == 'Libur Nasional') $upCatColor = "text-red-500";
                                if ($upcoming['category'] == 'Libur Sekolah') $upCatColor = "text-green-500";
                                if ($upcoming['category'] == 'Cuti Bersama') $upCatColor = "text-yellow-500";
                            ?>
                                <div class="p-6 hover:bg-slate-50 transition-all group">
                                    <div class="flex items-start gap-4">
                                        <div class="mt-1.5 h-2.5 w-2.5 rounded-full flex-shrink-0 <?php echo str_replace('text-', 'bg-', $upCatColor); ?>"></div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-800 group-hover:text-[#0E83A3] transition-colors"><?php echo htmlspecialchars($upcoming['title']); ?></h4>
                                            <p class="text-xs text-slate-400 flex items-center gap-1.5 mt-1 font-medium">
                                                <i class="fa-solid fa-calendar-days h-3.5 w-3.5"></i>
                                                <?php echo date('d', strtotime($upcoming['start_date'])) . " " . $indo_months[(int)date('m', strtotime($upcoming['start_date']))] . " " . date('Y', strtotime($upcoming['start_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-4 bg-slate-50/50 border-t border-slate-100 text-center">
                            <a href="#" class="text-xs font-bold text-[#0E83A3] hover:underline flex items-center justify-center gap-1">
                                Lihat Semua Kegiatan 
                                <i class="fa-solid fa-chevron-right h-3 w-3"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center bg-slate-50 text-slate-400 text-xs font-medium">Tidak ada kegiatan mendatang.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

</div>

<script>
// Custom Select Logic
function initCustomSelect(containerId, inputId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const trigger = container.querySelector('.custom-select-trigger');
    const options = container.querySelectorAll('.custom-select-option');
    const input = document.getElementById(inputId);
    const selectedText = trigger.querySelector('.selected-text');

    function updateUI(opt) {
        if (!opt) return;
        const val = opt.getAttribute('data-value');
        const color = opt.getAttribute('data-color');
        const text = opt.getAttribute('data-label') || opt.innerText.trim();

        input.value = val;
        selectedText.innerHTML = `<span class="color-dot ${color}"></span> ${text}`;
        
        options.forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        
        container.classList.remove('active');
        const arrow = trigger.querySelector('svg:last-child');
        if (arrow) arrow.style.transform = '';
    }

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        container.classList.toggle('active');
        const arrow = trigger.querySelector('svg:last-child');
        if (arrow) arrow.style.transform = container.classList.contains('active') ? 'rotate(180deg)' : '';
    });

    options.forEach(opt => {
        opt.addEventListener('click', (e) => {
            e.stopPropagation();
            updateUI(opt);
        });
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target)) {
            container.classList.remove('active');
            const arrow = trigger.querySelector('svg:last-child');
            if (arrow) arrow.style.transform = '';
        }
    });

    // Helper to programmatically set value
    return {
        setValue: (val) => {
            const targetVal = (val || '').trim().toLowerCase();
            if (!targetVal) return;
            
            // 1. Try match data-value
            let opt = [...options].find(o => (o.getAttribute('data-value') || '').trim().toLowerCase() === targetVal);
            
            // 2. Try match data-label
            if (!opt) {
                opt = [...options].find(o => (o.getAttribute('data-label') || '').trim().toLowerCase() === targetVal);
            }
            
            // 3. Try match visible text
            if (!opt) {
                opt = [...options].find(o => o.innerText.trim().toLowerCase() === targetVal);
            }

            if (opt) {
                updateUI(opt);
            } else {
                // Default fallback
                const fallback = [...options].find(o => o.getAttribute('data-value').toLowerCase() === 'kegiatan') || options[0];
                if (fallback) updateUI(fallback);
            }
        }
    };
}

const sidebarSelect = initCustomSelect('categorySelectSidebar', 'categoryInputSidebar');

function editEvent(event) {
    if (event.is_api) {
        showToast('Hari Libur Nasional (API) tidak dapat diubah.', 'error');
        return;
    }
    document.getElementById('sideFormID').value = event.id;
    document.getElementById('sideFormTitle').value = event.title;
    document.getElementById('sideFormStartDate').value = event.start_date;
    document.getElementById('sideFormEndDate').value = event.end_date || '';
    
    // Set custom select value
    sidebarSelect.setValue(event.category || 'Kegiatan');
    
    document.getElementById('sideHeaderTitle').innerText = 'Edit Kegiatan';
    document.getElementById('sideBtnText').innerText = 'Update Kegiatan';
    document.getElementById('cancelEditBtn').classList.remove('hidden');
    document.getElementById('deleteBtn').classList.remove('hidden');
    
    // Smooth scroll to sidebar
    document.getElementById('sideForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function resetSideForm() {
    document.getElementById('sideForm').reset();
    document.getElementById('sideFormID').value = '';
    document.getElementById('sideHeaderTitle').innerText = 'Tambah Kegiatan';
    document.getElementById('sideBtnText').innerText = 'Simpan Kegiatan';
    document.getElementById('cancelEditBtn').classList.add('hidden');
    document.getElementById('deleteBtn').classList.add('hidden');
    sidebarSelect.setValue('Libur Nasional');
}

function saveEventSide(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    const isEdit = data.id && data.id !== '';
    
    // Auto holiday flag based on category for convenience
    data.is_holiday = (data.category === 'Libur Nasional' || data.category === 'Libur Sekolah') ? 1 : 0;

    submitEvent(data);
}

function submitEvent(data) {
    fetch('../../logic/calendar/save_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) location.reload();
        else alert('Error: ' + res.message);
    });
}

function deleteEventAjax() {
    openCalendarDeleteModal();
}

function openCalendarDeleteModal() {
    const modal = document.getElementById('calendarDeleteModal');
    const backdrop = document.getElementById('calDeleteBackdrop');
    const panel = document.getElementById('calDeletePanel');

    modal.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('opacity-0', 'scale-95');
    }, 10);
}

function closeCalendarDeleteModal() {
    const modal = document.getElementById('calendarDeleteModal');
    const backdrop = document.getElementById('calDeleteBackdrop');
    const panel = document.getElementById('calDeletePanel');

    backdrop.classList.add('opacity-0');
    panel.classList.add('opacity-0', 'scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function confirmDeleteEvent() {
    const id = document.getElementById('sideFormID').value;
    if (!id) return;

    fetch('../../logic/calendar/delete_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) location.reload();
        else alert('Error: ' + res.message);
    });
}
</script>

<!-- Custom Delete Modal for Calendar -->
<div id="calendarDeleteModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div id="calDeleteBackdrop" class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div id="calDeletePanel" class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-md">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-8 sm:pb-6">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 sm:mx-0 sm:h-12 sm:w-12">
                        <i class="fa-solid fa-trash h-6 w-6 text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:ml-6 sm:mt-0 sm:text-left">
                        <h3 class="text-xl font-bold leading-6 text-slate-900" id="modal-title">Hapus Kegiatan?</h3>
                        <div class="mt-3">
                            <p class="text-sm text-slate-500 leading-relaxed font-medium">Apakah Anda yakin ingin menghapus kegiatan ini dari kalender? Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-6 py-4 sm:flex sm:flex-row-reverse gap-3">
                <button type="button" onclick="confirmDeleteEvent()" class="inline-flex w-full justify-center rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-all transform active:scale-95">
                    Hapus Sekarang
                </button>
                <button type="button" onclick="closeCalendarDeleteModal()" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-600 shadow-sm ring-1 ring-inset ring-slate-200 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                    Batalkan
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
