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
$start_day_of_week = date('N', $first_day_ts) - 1; // 0 (Mon) to 6 (Sun)

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

// --- Fetch Units for dropdown ---
$units_stmt = $conn->query("SELECT id, name FROM education_units ORDER BY name ASC");
$units = $units_stmt ? $units_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// --- Fetch Events for this month ---
$first_of_month = date('Y-m-d', $first_day_ts);
$last_of_month = date('Y-m-t', $first_day_ts);

$query = "SELECT a.*, u.name as unit_name FROM academic_calendar a 
          LEFT JOIN education_units u ON a.unit_id = u.id
          WHERE (a.start_date <= :last_day AND (a.end_date >= :first_day OR a.end_date IS NULL)) 
          ORDER BY a.start_date ASC";
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

$upcoming_query = "SELECT a.*, u.name as unit_name FROM academic_calendar a 
                   LEFT JOIN education_units u ON a.unit_id = u.id
                   WHERE (a.start_date <= :end AND (a.end_date >= :start OR a.end_date IS NULL))
                   ORDER BY a.start_date ASC";
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

    /* Source Type Left Border Indicators */
    .src-bidang { border-left: 3px solid #6366f1 !important; }
    .src-unit { border-left: 3px solid #10b981 !important; }
    .src-yayasan { border-left: 3px solid #14b8a6 !important; }
    .src-api { border-left: 3px solid #ef4444 !important; }

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

    .day-chip {
        user-select: none;
        transition: all 0.15s ease-in-out;
    }
    .day-chip:hover {
        transform: translateY(-1px);
    }

    /* Premium Datepicker Input Styling */
    input[type="date"] {
        cursor: pointer;
    }
    input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer;
        opacity: 0.5;
        padding: 4px;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    input[type="date"]::-webkit-calendar-picker-indicator:hover {
        opacity: 1;
        background-color: rgba(0, 0, 0, 0.05);
        transform: scale(1.1);
    }
</style>

<div class="pb-10 space-y-6">
    <!-- Header Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Kalender Akademik</h1>
            <p class="text-sm text-slate-500">Pusat pengelolaan agenda kegiatan dan kalender akademik YAC</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" onclick="openImportModal()" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-700 font-semibold text-xs hover:bg-slate-50 transition-all shadow-sm gap-2">
                <i class="fa-solid fa-file-import text-blue-600 text-sm shrink-0"></i>
                <span>Import Data</span>
            </button>
            <button type="button" onclick="openResetModal()" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-red-200 bg-red-50 text-red-700 font-semibold text-xs hover:bg-red-100 hover:border-red-300 transition-all shadow-sm gap-2">
                <i class="fa-solid fa-rotate-left text-red-600 text-sm shrink-0"></i>
                <span>Reset Data</span>
            </button>
            <a href="<?php url('agenda-pendidikan'); ?>" target="_blank" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs shadow-md transition-all gap-2">
                <span>Agenda Publik</span>
                <i class="fa-solid fa-arrow-up-right-from-square text-xs shrink-0"></i>
            </a>
        </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Main Calendar (Left) -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
                <!-- Calendar Header with Month & Year Dropdown Selector -->
                <div class="p-5 flex flex-wrap items-center justify-between gap-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold text-slate-800 shrink-0 hidden sm:block"><?php echo $month_name . " " . $year; ?></h2>
                        
                        <!-- Month & Year Select Dropdowns -->
                        <div class="flex items-center gap-2">
                            <select id="select_month" onchange="jumpToMonthYear()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-all outline-none cursor-pointer">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                        <?php echo $indo_months[$m]; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select id="select_year" onchange="jumpToMonthYear()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-all outline-none cursor-pointer">
                                <?php for ($y = date('Y') - 3; $y <= date('Y') + 3; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" title="Bulan Sebelumnya" class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 rounded-xl transition-all text-slate-600 border border-slate-200/80">
                            <i class="fa-solid fa-chevron-left text-xs"></i>
                        </a>
                        <a href="index.php" class="px-3.5 py-2 text-xs font-bold text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-xl transition-all flex items-center gap-1.5 border border-slate-200/80">
                            <i class="fa-solid fa-calendar-day text-blue-600 text-xs"></i>
                            <span>Bulan Ini</span>
                        </a>
                        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" title="Bulan Berikutnya" class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 rounded-xl transition-all text-slate-600 border border-slate-200/80">
                            <i class="fa-solid fa-chevron-right text-xs"></i>
                        </a>
                    </div>
                </div>

                <!-- Calendar Grid Wrapper for Responsiveness -->
                <div class="overflow-x-auto p-5">
                    <div class="calendar-grid min-w-[700px] md:min-w-full">
                        <?php 
                        $headers = ["SEN", "SEL", "RAB", "KAM", "JUM", "SAB", "AHAD"];
                        foreach ($headers as $h) echo "<div class='calendar-day-header'>$h</div>";

                        // Empty cells for padding
                        for ($i = 0; $i < $start_day_of_week; $i++) {
                            echo "<div class='calendar-cell bg-slate-50/30'></div>";
                        }

                        // Actual days
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $isToday = ($day == (int)date('d') && $month == (int)date('m') && $year == (int)date('Y'));
                            $isSunday = (($day + $start_day_of_week - 1) % 7 == 6);
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
                                
                                // Source type left border indicator
                                $srcClass = 'src-bidang';
                                $srcLabel = '📋 Bid. Pendidikan';
                                if (!empty($ev['is_api'])) {
                                    $srcClass = 'src-api';
                                    $srcLabel = '🔴 API';
                                } elseif (isset($ev['source_type']) && $ev['source_type'] == 'unit') {
                                    $srcClass = 'src-unit';
                                    $srcLabel = '🏫 ' . (!empty($ev['unit_name']) ? htmlspecialchars($ev['unit_name']) : 'Unit');
                                } elseif (isset($ev['source_type']) && $ev['source_type'] == 'yayasan') {
                                    $srcClass = 'src-yayasan';
                                    $srcLabel = '🏛️ Yayasan';
                                }
                                
                                $title = htmlspecialchars($ev['title']);
                                $unitBadge = !empty($ev['unit_name']) ? " (" . htmlspecialchars($ev['unit_name']) . ")" : "";
                                $locInfo = !empty($ev['location']) ? "📍 " . htmlspecialchars($ev['location']) : "";
                                
                                $isInternal = (!empty($ev['visibility']) && $ev['visibility'] === 'internal');
                                $isRec = (!empty($ev['is_recurring']) && $ev['is_recurring'] == 1);

                                $visIcon = $isInternal ? " <i class='fa-solid fa-lock text-[8px] text-amber-300' title='Internal Agenda Pendidikan'></i>" : "";
                                $recIcon = $isRec ? " <i class='fa-solid fa-arrows-rotate text-[8px] text-cyan-200' title='Agenda Berulang'></i>" : "";

                                $tooltipTxt = $title . $unitBadge;
                                $tooltipTxt .= "<br>" . $srcLabel;
                                if ($locInfo) $tooltipTxt .= "<br>" . $locInfo;
                                if ($isInternal) $tooltipTxt .= "<br><span style='color:#fcd34d;'>🔒 Khusus URL Agenda Pendidikan (Internal)</span>";
                                if ($isRec) {
                                    $recFreq = ucfirst($ev['recurrence_type'] ?? 'Berulang');
                                    $tooltipTxt .= "<br><span style='color:#67e8f9;'>🔄 Agenda Berulang ($recFreq)</span>";
                                }
                                
                                $jsonEv = json_encode($ev, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                echo "
                                <div class='tooltip-container'>
                                    <div onclick='editEvent($jsonEv)' class='event-pill $catClass $srcClass'>$title$unitBadge$visIcon$recIcon</div>
                                    <div class='custom-tooltip'>$tooltipTxt</div>
                                </div>";
                            }
                            
                            echo "</div>";
                        }

                        // Trailing empty cells to complete last row
                        $total_cells = $start_day_of_week + $days_in_month;
                        $trailing_empty = (7 - ($total_cells % 7)) % 7;
                        for ($i = 0; $i < $trailing_empty; $i++) {
                            echo "<div class='calendar-cell bg-slate-50/30'></div>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Desktop Legend: Categories -->
                <div class="p-5 bg-slate-50 border-t border-slate-100 flex flex-wrap gap-8 items-center justify-center sm:justify-start">
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

                <!-- Desktop Legend: Source Type -->
                <div class="px-5 pb-4 flex flex-wrap gap-x-6 gap-y-2 items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Sumber Agenda:</span>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm" style="background:#6366f1;"></span>
                        <span class="text-[10px] font-bold text-slate-600">Bidang Pendidikan</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm" style="background:#10b981;"></span>
                        <span class="text-[10px] font-bold text-slate-600">Unit Pendidikan</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm" style="background:#14b8a6;"></span>
                        <span class="text-[10px] font-bold text-slate-600">Yayasan</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm" style="background:#ef4444;"></span>
                        <span class="text-[10px] font-bold text-slate-600">Libur Nasional (API)</span>
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
                        <div class="h-12 w-12 rounded-2xl bg-cyan-50 flex items-center justify-center text-cyan-600 shadow-sm border border-cyan-100/50 shrink-0">
                            <i class="fa-solid fa-circle-plus text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-800 tracking-tight" id="sideHeaderTitle">Tambah Kegiatan</h3>
                            <p class="text-[11px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Agenda Baru Akademik</p>
                        </div>
                    </div>
                </div>

                <form id="sideForm" onsubmit="saveEventSide(event)" class="px-8 pb-8 space-y-6">
                    <input type="hidden" name="id" id="sideFormID">
                    <input type="hidden" name="repeat_group_id" id="sideFormRepeatGroupId">
                    <!-- Title Input -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-focus-within:text-cyan-600 transition-colors">Nama Kegiatan <span class="text-red-500">*</span></label>
                        <div class="relative transition-all">
                            <div class="absolute inset-y-0 left-0 w-11 flex items-center justify-center pointer-events-none">
                                <i class="fa-solid fa-pen-to-square text-sm text-slate-300 group-focus-within:text-cyan-400 transition-colors"></i>
                            </div>
                            <input type="text" name="title" id="sideFormTitle" required placeholder="Contoh: Rapat Kegiatan Wajib"
                                class="w-full pl-11 rounded-2xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 bg-slate-50/30 text-sm py-3 font-semibold text-slate-700 placeholder:text-slate-300 transition-all">
                        </div>
                    </div>

                    <!-- Target Tampilan / Visibilitas Agenda -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-focus-within:text-cyan-600 transition-colors">
                            Target Tampilan Agenda <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="relative flex flex-col p-3 rounded-2xl border-2 cursor-pointer transition-all border-cyan-500 bg-cyan-50/30 hover:border-cyan-500 shadow-sm" id="labelVisPublic">
                                <input type="radio" name="visibility" value="public" id="visPublic" checked onchange="updateVisibilityUI()" class="sr-only">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="w-7 h-7 rounded-lg bg-cyan-100 text-cyan-700 flex items-center justify-center shrink-0 text-xs">
                                        <i class="fa-solid fa-globe"></i>
                                    </div>
                                    <span class="text-xs font-bold text-slate-800">Semua User</span>
                                    <i class="fa-solid fa-circle-check text-cyan-600 ml-auto text-sm check-icon"></i>
                                </div>
                                <p class="text-[10px] text-slate-500 font-medium leading-tight">Tampil di Kalender & Agenda Pendidikan</p>
                            </label>

                            <label class="relative flex flex-col p-3 rounded-2xl border-2 cursor-pointer transition-all border-slate-200 bg-slate-50/40 hover:border-slate-300" id="labelVisInternal">
                                <input type="radio" name="visibility" value="internal" id="visInternal" onchange="updateVisibilityUI()" class="sr-only">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 text-xs">
                                        <i class="fa-solid fa-lock"></i>
                                    </div>
                                    <span class="text-xs font-bold text-slate-800">Internal Saja</span>
                                    <i class="fa-regular fa-circle text-slate-300 ml-auto text-sm check-icon"></i>
                                </div>
                                <p class="text-[10px] text-slate-500 font-medium leading-tight">Hanya di URL agenda-pendidikan</p>
                            </label>
                        </div>
                    </div>

                    <!-- Sumber Agenda & Unit Select -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="group">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-focus-within:text-cyan-600 transition-colors">Sumber Agenda</label>
                            <div class="relative transition-all">
                                <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center pointer-events-none">
                                    <i class="fa-solid fa-sitemap text-xs text-slate-300 group-focus-within:text-cyan-400 transition-colors"></i>
                                </div>
                                <select name="source_type" id="sideFormSourceType" onchange="toggleUnitSelect()"
                                    class="w-full pl-9 pr-7 rounded-2xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 bg-slate-50/30 text-xs py-3 font-bold text-slate-700 cursor-pointer transition-all appearance-none">
                                    <option value="bidang_pendidikan">Bid. Pendidikan</option>
                                    <option value="yayasan">Yayasan</option>
                                    <option value="unit">Unit Pendidikan</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 w-7 flex items-center justify-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </div>
                            </div>
                        </div>

                        <div class="group" id="unitSelectContainer">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-focus-within:text-cyan-600 transition-colors">Pilihan Unit</label>
                            <div class="relative transition-all">
                                <div class="absolute inset-y-0 left-0 w-10 flex items-center justify-center pointer-events-none">
                                    <i class="fa-solid fa-school text-xs text-slate-300 group-focus-within:text-cyan-400 transition-colors"></i>
                                </div>
                                <select name="unit_id" id="sideFormUnitId"
                                    class="w-full pl-9 pr-7 rounded-2xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 bg-slate-50/30 text-xs py-3 font-bold text-slate-700 cursor-pointer transition-all appearance-none">
                                    <option value="">-- Pilih Unit --</option>
                                    <?php foreach ($units as $u): ?>
                                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 w-7 flex items-center justify-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kategori Select (Custom UI) -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-focus-within:text-cyan-600 transition-colors">Kategori Kegiatan</label>
                        <div class="custom-select-container" id="categorySelectSidebar">
                            <input type="hidden" name="category" id="categoryInputSidebar" value="Libur Nasional">
                            <div class="custom-select-trigger transition-all">
                                <div class="absolute inset-y-0 left-0 w-11 flex items-center justify-center pointer-events-none">
                                    <i class="fa-solid fa-grip text-sm text-slate-300 group-focus-within:text-cyan-400 transition-colors"></i>
                                </div>
                                <span class="selected-text flex items-center gap-2">
                                    <span class="color-dot bg-red-500"></span>
                                    Libur Nasional
                                </span>
                                <div class="absolute inset-y-0 right-0 w-10 flex items-center justify-center pointer-events-none">
                                    <i class="fa-solid fa-chevron-down text-xs text-slate-400 transition-transform"></i>
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
                                <div class="custom-select-option" data-value="Kegiatan" data-label="Kegiatan Sekolah / Unit" data-color="bg-purple-500">
                                    <span class="color-dot bg-purple-500"></span> Kegiatan Sekolah / Unit
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date Selection -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="group">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-focus-within:text-cyan-600 transition-colors">Tanggal Mulai <span class="text-red-500">*</span></label>
                            <div class="relative cursor-pointer" onclick="try { document.getElementById('sideFormStartDate').showPicker(); } catch(e) {}">
                                <input type="date" name="start_date" id="sideFormStartDate" required onchange="syncStartDayToWeekly()" onclick="try { this.showPicker(); } catch(e) {}"
                                    class="w-full rounded-2xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 bg-slate-50/30 text-xs py-3 px-3 font-semibold text-slate-700 cursor-pointer transition-all">
                            </div>
                        </div>
                        <div class="group">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-focus-within:text-cyan-600 transition-colors">Tanggal Selesai</label>
                            <div class="relative cursor-pointer" onclick="try { document.getElementById('sideFormEndDate').showPicker(); } catch(e) {}">
                                <input type="date" name="end_date" id="sideFormEndDate" onclick="try { this.showPicker(); } catch(e) {}"
                                    class="w-full rounded-2xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 bg-slate-50/30 text-xs py-3 px-3 font-semibold text-slate-700 cursor-pointer transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- Agenda Berulang Toggle & Settings -->
                    <div id="recurrenceSection" class="pt-1 pb-1 border-y border-slate-100/80">
                        <div id="recurrenceToggleContainer" class="flex items-center justify-between cursor-pointer py-2 select-none" onclick="toggleRecurringOptions()">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center text-sm border border-cyan-100 shrink-0">
                                    <i class="fa-solid fa-arrows-rotate"></i>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-slate-800 cursor-pointer block">Agenda Berulang</label>
                                    <p class="text-[10px] text-slate-400 font-medium">Ulangi setiap hari, pekan, bulan, atau tahun</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer" onclick="event.stopPropagation()">
                                <input type="checkbox" name="is_recurring" id="sideFormIsRecurring" value="1" onchange="handleRecurringCheckboxChange()" class="sr-only peer">
                                <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-cyan-600"></div>
                            </label>
                        </div>

                        <!-- Collapsible Recurrence Settings Container -->
                        <div id="recurrenceSettingsContainer" class="hidden mt-3 space-y-4 pt-3 border-t border-slate-100 bg-slate-50/70 p-4 rounded-2xl border border-slate-100">
                            <!-- Recurrence Frequency Selector -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Frekuensi Pengulangan</label>
                                <div class="grid grid-cols-4 gap-1 p-1 bg-slate-200/70 rounded-xl text-center">
                                    <button type="button" onclick="setRecurrenceFreq('daily')" id="btnFreqDaily" class="py-1.5 text-[11px] font-bold rounded-lg transition-all text-slate-600 hover:text-slate-900">Harian</button>
                                    <button type="button" onclick="setRecurrenceFreq('weekly')" id="btnFreqWeekly" class="py-1.5 text-[11px] font-bold rounded-lg transition-all bg-white text-cyan-700 shadow-sm">Pekanan</button>
                                    <button type="button" onclick="setRecurrenceFreq('monthly')" id="btnFreqMonthly" class="py-1.5 text-[11px] font-bold rounded-lg transition-all text-slate-600 hover:text-slate-900">Bulanan</button>
                                    <button type="button" onclick="setRecurrenceFreq('yearly')" id="btnFreqYearly" class="py-1.5 text-[11px] font-bold rounded-lg transition-all text-slate-600 hover:text-slate-900">Tahunan</button>
                                </div>
                                <input type="hidden" name="recurrence_type" id="sideFormRecurrenceType" value="weekly">
                            </div>

                            <!-- SUB-OPTIONS FOR DAILY: Penjelasan -->
                            <div id="subOptionDaily" class="hidden text-[11px] text-slate-500 font-medium bg-white p-2.5 rounded-xl border border-slate-100">
                                <i class="fa-solid fa-circle-info text-cyan-600 mr-1"></i> Kegiatan akan berulang setiap hari sampai tanggal batas berakhir.
                            </div>

                            <!-- SUB-OPTIONS FOR WEEKLY: Pilih Hari Apa Saja -->
                            <div id="subOptionWeekly" class="space-y-2">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ulangi Setiap Hari Apa:</label>
                                <div class="grid grid-cols-7 gap-1">
                                    <?php 
                                    $dayList = [
                                        1 => 'Sen',
                                        2 => 'Sel',
                                        3 => 'Rab',
                                        4 => 'Kam',
                                        5 => 'Jum',
                                        6 => 'Sab',
                                        7 => 'Ahd'
                                    ];
                                    foreach ($dayList as $dNum => $dShort): ?>
                                        <label class="day-chip flex flex-col items-center justify-center py-2 rounded-xl border text-[11px] font-bold cursor-pointer transition-all border-slate-200 bg-white text-slate-600 hover:border-cyan-400 shadow-xs" id="chipDay<?php echo $dNum; ?>">
                                            <input type="checkbox" name="recurrence_days[]" value="<?php echo $dNum; ?>" onchange="updateDayChipUI(<?php echo $dNum; ?>)" class="sr-only" id="inputDay<?php echo $dNum; ?>">
                                            <span><?php echo $dShort; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- SUB-OPTIONS FOR MONTHLY: Pilih Pekan ke atau Tanggal -->
                            <div id="subOptionMonthly" class="hidden space-y-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Pola Bulanan:</label>
                                    <select name="monthly_mode" id="sideFormMonthlyMode" onchange="toggleMonthlyModeUI()" class="w-full rounded-xl border-slate-200 text-xs py-2 px-3 font-semibold text-slate-700 bg-white">
                                        <option value="date">Berdasarkan Tanggal yang Sama Setiap Bulan</option>
                                        <option value="nth_day">Berdasarkan Pekan ke- dan Hari Tertentu</option>
                                    </select>
                                </div>
                                <div id="monthlyNthDayContainer" class="hidden grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pekan ke-:</label>
                                        <select name="recurrence_week_num" id="sideFormMonthlyWeekNum" class="w-full rounded-xl border-slate-200 text-xs py-2 px-3 font-semibold text-slate-700 bg-white">
                                            <option value="1">Pekan ke-1 (Pertama)</option>
                                            <option value="2">Pekan ke-2 (Kedua)</option>
                                            <option value="3">Pekan ke-3 (Ketiga)</option>
                                            <option value="4">Pekan ke-4 (Keempat)</option>
                                            <option value="5">Pekan Terakhir</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Hari apa:</label>
                                        <select name="recurrence_day_of_week" id="sideFormMonthlyDayOfWeek" class="w-full rounded-xl border-slate-200 text-xs py-2 px-3 font-semibold text-slate-700 bg-white">
                                            <option value="1">Senin</option>
                                            <option value="2">Selasa</option>
                                            <option value="3">Rabu</option>
                                            <option value="4">Kamis</option>
                                            <option value="5">Jumat</option>
                                            <option value="6">Sabtu</option>
                                            <option value="7">Ahad</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- SUB-OPTIONS FOR YEARLY: Pilih Bulan ke & Tanggal / Pekan ke -->
                            <div id="subOptionYearly" class="hidden space-y-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Bulan ke- / Nama Bulan:</label>
                                    <select name="recurrence_month" id="sideFormYearlyMonth" class="w-full rounded-xl border-slate-200 text-xs py-2 px-3 font-semibold text-slate-700 bg-white">
                                        <?php for ($bm = 1; $bm <= 12; $bm++): ?>
                                            <option value="<?php echo $bm; ?>">Bulan ke-<?php echo $bm; ?> (<?php echo $indo_months[$bm]; ?>)</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pola Tahunan:</label>
                                    <select name="yearly_mode" id="sideFormYearlyMode" onchange="toggleYearlyModeUI()" class="w-full rounded-xl border-slate-200 text-xs py-2 px-3 font-semibold text-slate-700 bg-white">
                                        <option value="date">Berdasarkan Tanggal yang Sama</option>
                                        <option value="nth_day">Berdasarkan Pekan ke- dan Hari Tertentu</option>
                                    </select>
                                </div>
                                <div id="yearlyNthDayContainer" class="hidden grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pekan ke-:</label>
                                        <select name="yearly_week_num" id="sideFormYearlyWeekNum" class="w-full rounded-xl border-slate-200 text-xs py-2 px-3 font-semibold text-slate-700 bg-white">
                                            <option value="1">Pekan ke-1</option>
                                            <option value="2">Pekan ke-2</option>
                                            <option value="3">Pekan ke-3</option>
                                            <option value="4">Pekan ke-4</option>
                                            <option value="5">Pekan Terakhir</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Hari apa:</label>
                                        <select name="yearly_day_of_week" id="sideFormYearlyDayOfWeek" class="w-full rounded-xl border-slate-200 text-xs py-2 px-3 font-semibold text-slate-700 bg-white">
                                            <option value="1">Senin</option>
                                            <option value="2">Selasa</option>
                                            <option value="3">Rabu</option>
                                            <option value="4">Kamis</option>
                                            <option value="5">Jumat</option>
                                            <option value="6">Sabtu</option>
                                            <option value="7">Ahad</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- REPEAT UNTIL (Batas Berakhir Pengulangan) -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                                    Ulangi Sampai Tanggal: <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="repeat_until" id="sideFormRepeatUntil" onclick="try{this.showPicker()}catch(e){}" class="w-full rounded-xl border-slate-200 text-xs py-2 px-3 font-semibold text-slate-700 bg-white cursor-pointer">
                                <p class="text-[10px] text-slate-400 mt-1">Kegiatan akan dibuat otomatis pada kalender sampai batas ini.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Location Input -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-focus-within:text-cyan-600 transition-colors">Lokasi Kegiatan</label>
                        <div class="relative transition-all">
                            <div class="absolute inset-y-0 left-0 w-11 flex items-center justify-center pointer-events-none">
                                <i class="fa-solid fa-location-dot text-sm text-slate-300 group-focus-within:text-cyan-400 transition-colors"></i>
                            </div>
                            <input type="text" name="location" id="sideFormLocation" placeholder="Contoh: Aula Assunnah / Zoom"
                                class="w-full pl-11 rounded-2xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 bg-slate-50/30 text-xs py-3 font-semibold text-slate-700 placeholder:text-slate-300 transition-all">
                        </div>
                    </div>

                    <!-- Description Input -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 group-focus-within:text-cyan-600 transition-colors">Deskripsi / Detail</label>
                        <div class="relative transition-all">
                            <textarea name="description" id="sideFormDescription" rows="2" placeholder="Catatan tambahan mengenai kegiatan..."
                                class="w-full p-3.5 rounded-2xl border-slate-200 focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 bg-slate-50/30 text-xs font-medium text-slate-700 placeholder:text-slate-300 transition-all"></textarea>
                        </div>
                    </div>

                    <!-- Recurring Series Edit Notice (Visible only when editing a recurring event) -->
                    <div id="editRecurringSeriesContainer" class="hidden p-3.5 bg-amber-50 rounded-2xl border border-amber-200 text-xs text-amber-900 space-y-2">
                        <div class="flex items-center gap-2 font-bold text-amber-800">
                            <i class="fa-solid fa-arrows-rotate text-xs"></i>
                            <span>Kegiatan Berulang</span>
                        </div>
                        <p class="text-[11px] text-amber-700 leading-snug">Kegiatan ini merupakan bagian dari rangkaian agenda berulang. Tentukan perubahan yang ingin diterapkan:</p>
                        <div class="space-y-1.5 pt-1">
                            <label class="flex items-center gap-2 font-semibold text-xs text-amber-900 cursor-pointer">
                                <input type="radio" name="update_scope" value="single" checked class="text-amber-600 focus:ring-amber-500">
                                <span>Hanya perbarui kegiatan tanggal ini</span>
                            </label>
                            <label class="flex items-center gap-2 font-semibold text-xs text-amber-900 cursor-pointer">
                                <input type="radio" name="update_scope" value="series" class="text-amber-600 focus:ring-amber-500">
                                <span>Perbarui seluruh rangkaian kegiatan ini</span>
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2 flex flex-col gap-3">
                        <button type="submit" class="group/btn relative w-full inline-flex items-center justify-center px-8 py-4 font-bold text-white transition-all duration-200 bg-cyan-600 rounded-2xl hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-500/20 active:scale-[0.97] overflow-hidden">
                            <span class="absolute inset-0 w-full h-full bg-gradient-to-br from-white/10 to-transparent"></span>
                            <span class="relative flex items-center gap-2">
                                <i class="fa-solid fa-check text-sm group-hover/btn:translate-x-0.5 transition-transform"></i>
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
                                $upInternal = (!empty($upcoming['visibility']) && $upcoming['visibility'] === 'internal');
                                $upRec = (!empty($upcoming['is_recurring']) && $upcoming['is_recurring'] == 1);
                            ?>
                                <div class="p-6 hover:bg-slate-50 transition-all group">
                                    <div class="flex items-start gap-4">
                                        <div class="mt-1.5 h-2.5 w-2.5 rounded-full flex-shrink-0 <?php echo str_replace('text-', 'bg-', $upCatColor); ?>"></div>
                                        <div>
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <h4 class="text-sm font-bold text-slate-800 group-hover:text-[#0E83A3] transition-colors"><?php echo htmlspecialchars($upcoming['title']); ?></h4>
                                                <?php if ($upInternal): ?>
                                                    <span class="text-[9px] font-extrabold bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded-full inline-flex items-center gap-1"><i class="fa-solid fa-lock text-[7px]"></i> Internal</span>
                                                <?php endif; ?>
                                                <?php if ($upRec): ?>
                                                    <span class="text-[9px] font-bold bg-cyan-50 text-cyan-700 border border-cyan-200 px-1.5 py-0.5 rounded-full inline-flex items-center gap-1"><i class="fa-solid fa-arrows-rotate text-[7px]"></i> Berulang</span>
                                                <?php endif; ?>
                                            </div>
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
<script>
function jumpToMonthYear() {
    const month = document.getElementById('select_month').value;
    const year = document.getElementById('select_year').value;
    window.location.href = `?month=${month}&year=${year}`;
}

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

function toggleUnitSelect() {
    const sourceType = document.getElementById('sideFormSourceType').value;
    const unitContainer = document.getElementById('unitSelectContainer');
    if (sourceType === 'unit') {
        unitContainer.classList.remove('opacity-60');
    } else {
        unitContainer.classList.add('opacity-60');
    }
}

// --- Premium Toast Notification Handler (Fixed-position overlay) ---
function notifyToast(message, type = 'success') {
    // Always use a fixed-position floating container so toast is visible regardless of scroll
    let container = document.getElementById('calendarToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'calendarToastContainer';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:12px;max-width:420px;width:100%;pointer-events:none;';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    const isSuccess = type === 'success';
    const bgColor = isSuccess ? '#059669' : '#e11d48';
    const borderColor = isSuccess ? 'rgba(16,185,129,0.3)' : 'rgba(244,63,94,0.3)';
    const iconClass = isSuccess ? 'fa-circle-check' : 'fa-circle-xmark';
    const titleText = isSuccess ? 'Berhasil!' : 'Error!';
    
    toast.setAttribute('role', 'alert');
    toast.style.cssText = `
        pointer-events:auto;
        margin-bottom:8px;
        border-radius:16px;
        box-shadow:0 25px 50px -12px rgba(0,0,0,0.25),0 0 25px rgba(0,0,0,0.1);
        padding:16px 20px;
        border:1px solid ${borderColor};
        background:${bgColor};
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        opacity:0;
        transform:translateX(100%);
        transition:all 0.5s cubic-bezier(0.34,1.56,0.64,1);
    `;
    toast.innerHTML = `
        <div style="display:flex;align-items:center;gap:14px;">
            <div style="flex-shrink:0;background:rgba(255,255,255,0.2);padding:10px;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid ${iconClass}" style="font-size:18px;color:#fff;"></i>
            </div>
            <div>
                 <p style="font-size:10px;font-weight:900;color:rgba(255,255,255,0.8);text-transform:uppercase;letter-spacing:2px;line-height:1;margin:0 0 4px 0;">${titleText}</p>
                 <p style="font-size:14px;font-weight:700;color:#fff;margin:0;line-height:1.3;">${message}</p>
            </div>
        </div>
        <button type="button" onclick="this.closest('[role=alert]').style.opacity='0';this.closest('[role=alert]').style.transform='translateX(100%)';setTimeout(()=>this.closest('[role=alert]')&&this.closest('[role=alert]').remove(),500)" style="color:rgba(255,255,255,0.7);background:none;border:none;cursor:pointer;padding:8px;border-radius:8px;display:flex;align-items:center;transition:all 0.2s;">
            <i class="fa-solid fa-xmark" style="font-size:16px;"></i>
        </button>
    `;

    container.appendChild(toast);

    // Animate in (slide from right)
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        });
    });

    // Auto remove after 4 seconds
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) toast.remove();
        }, 500);
    }, 4000);
}

// --- Recurrence & Visibility UI Helpers ---
function updateVisibilityUI() {
    const isPublic = document.getElementById('visPublic') ? document.getElementById('visPublic').checked : true;
    const labelPub = document.getElementById('labelVisPublic');
    const labelInt = document.getElementById('labelVisInternal');
    if (!labelPub || !labelInt) return;

    const iconPub = labelPub.querySelector('.check-icon');
    const iconInt = labelInt.querySelector('.check-icon');

    if (isPublic) {
        labelPub.classList.add('border-cyan-500', 'bg-cyan-50/30');
        labelPub.classList.remove('border-slate-200', 'bg-slate-50/40');
        if (iconPub) iconPub.className = 'fa-solid fa-circle-check text-cyan-600 ml-auto text-sm check-icon';

        labelInt.classList.remove('border-amber-500', 'bg-amber-50/30');
        labelInt.classList.add('border-slate-200', 'bg-slate-50/40');
        if (iconInt) iconInt.className = 'fa-regular fa-circle text-slate-300 ml-auto text-sm check-icon';
    } else {
        labelInt.classList.add('border-amber-500', 'bg-amber-50/30');
        labelInt.classList.remove('border-slate-200', 'bg-slate-50/40');
        if (iconInt) iconInt.className = 'fa-solid fa-circle-check text-amber-600 ml-auto text-sm check-icon';

        labelPub.classList.remove('border-cyan-500', 'bg-cyan-50/30');
        labelPub.classList.add('border-slate-200', 'bg-slate-50/40');
        if (iconPub) iconPub.className = 'fa-regular fa-circle text-slate-300 ml-auto text-sm check-icon';
    }
}

function toggleRecurringOptions() {
    const cb = document.getElementById('sideFormIsRecurring');
    if (!cb) return;
    cb.checked = !cb.checked;
    handleRecurringCheckboxChange();
}

function handleRecurringCheckboxChange() {
    const cb = document.getElementById('sideFormIsRecurring');
    const container = document.getElementById('recurrenceSettingsContainer');
    if (!cb || !container) return;

    if (cb.checked) {
        container.classList.remove('hidden');
        const startVal = document.getElementById('sideFormStartDate').value;
        const repeatUntil = document.getElementById('sideFormRepeatUntil');
        if (repeatUntil && !repeatUntil.value) {
            const baseDate = startVal ? new Date(startVal) : new Date();
            baseDate.setMonth(baseDate.getMonth() + 6);
            repeatUntil.value = baseDate.toISOString().split('T')[0];
        }
        syncStartDayToWeekly();
    } else {
        container.classList.add('hidden');
    }
}

function syncStartDayToWeekly() {
    const startVal = document.getElementById('sideFormStartDate').value;
    if (!startVal) return;
    const d = new Date(startVal);
    let dayNum = d.getDay(); // 0 is Sunday
    if (dayNum === 0) dayNum = 7;
    
    const checkedDays = document.querySelectorAll('input[name="recurrence_days[]"]:checked');
    if (checkedDays.length === 0) {
        const targetInput = document.getElementById('inputDay' + dayNum);
        if (targetInput) {
            targetInput.checked = true;
            updateDayChipUI(dayNum);
        }
    }
}

function setRecurrenceFreq(freq) {
    const hiddenFreq = document.getElementById('sideFormRecurrenceType');
    if (hiddenFreq) hiddenFreq.value = freq;

    const freqs = ['daily', 'weekly', 'monthly', 'yearly'];
    freqs.forEach(f => {
        const btn = document.getElementById('btnFreq' + f.charAt(0).toUpperCase() + f.slice(1));
        const sub = document.getElementById('subOption' + f.charAt(0).toUpperCase() + f.slice(1));
        if (f === freq) {
            if (btn) btn.className = 'py-1.5 text-[11px] font-bold rounded-lg transition-all bg-white text-cyan-700 shadow-sm';
            if (sub) sub.classList.remove('hidden');
        } else {
            if (btn) btn.className = 'py-1.5 text-[11px] font-bold rounded-lg transition-all text-slate-600 hover:text-slate-900';
            if (sub) sub.classList.add('hidden');
        }
    });
}

function updateDayChipUI(num) {
    const chip = document.getElementById('chipDay' + num);
    const input = document.getElementById('inputDay' + num);
    if (!chip || !input) return;

    if (input.checked) {
        chip.classList.add('border-cyan-500', 'bg-cyan-600', 'text-white');
        chip.classList.remove('border-slate-200', 'bg-white', 'text-slate-600');
    } else {
        chip.classList.remove('border-cyan-500', 'bg-cyan-600', 'text-white');
        chip.classList.add('border-slate-200', 'bg-white', 'text-slate-600');
    }
}

function toggleMonthlyModeUI() {
    const modeEl = document.getElementById('sideFormMonthlyMode');
    if (!modeEl) return;
    const mode = modeEl.value;
    const container = document.getElementById('monthlyNthDayContainer');
    if (container) {
        if (mode === 'nth_day') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }
}

function toggleYearlyModeUI() {
    const modeEl = document.getElementById('sideFormYearlyMode');
    if (!modeEl) return;
    const mode = modeEl.value;
    const container = document.getElementById('yearlyNthDayContainer');
    if (container) {
        if (mode === 'nth_day') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }
}

function editEvent(event) {
    if (event.is_api) {
        notifyToast('Hari Libur Nasional (API) tidak dapat diubah.', 'error');
        return;
    }
    document.getElementById('sideFormID').value = event.id;
    document.getElementById('sideFormTitle').value = event.title;
    document.getElementById('sideFormStartDate').value = event.start_date;
    document.getElementById('sideFormEndDate').value = event.end_date || '';
    document.getElementById('sideFormSourceType').value = event.source_type || 'bidang_pendidikan';
    document.getElementById('sideFormUnitId').value = event.unit_id || '';
    document.getElementById('sideFormLocation').value = event.location || '';
    document.getElementById('sideFormDescription').value = event.description || '';
    document.getElementById('sideFormRepeatGroupId').value = event.repeat_group_id || '';

    // Visibilitas
    if (event.visibility === 'internal') {
        document.getElementById('visInternal').checked = true;
    } else {
        document.getElementById('visPublic').checked = true;
    }
    updateVisibilityUI();

    // Handle recurring series indicators when editing
    const recNotice = document.getElementById('editRecurringSeriesContainer');
    const recSection = document.getElementById('recurrenceSection');
    if (event.repeat_group_id) {
        if (recNotice) recNotice.classList.remove('hidden');
        if (recSection) recSection.classList.add('hidden');
    } else {
        if (recNotice) recNotice.classList.add('hidden');
        if (recSection) recSection.classList.remove('hidden');
        document.getElementById('sideFormIsRecurring').checked = false;
        handleRecurringCheckboxChange();
    }
    
    toggleUnitSelect();
    sidebarSelect.setValue(event.category || 'Kegiatan');
    
    document.getElementById('sideHeaderTitle').innerText = 'Edit Kegiatan';
    document.getElementById('sideBtnText').innerText = 'Update Kegiatan';
    document.getElementById('cancelEditBtn').classList.remove('hidden');
    document.getElementById('deleteBtn').classList.remove('hidden');
    
    document.getElementById('sideForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function resetSideForm() {
    document.getElementById('sideForm').reset();
    document.getElementById('sideFormID').value = '';
    document.getElementById('sideFormRepeatGroupId').value = '';
    document.getElementById('sideFormSourceType').value = 'bidang_pendidikan';
    document.getElementById('sideFormUnitId').value = '';
    document.getElementById('sideFormLocation').value = '';
    document.getElementById('sideFormDescription').value = '';

    // Reset visibility to public
    document.getElementById('visPublic').checked = true;
    updateVisibilityUI();

    // Reset recurring
    document.getElementById('sideFormIsRecurring').checked = false;
    handleRecurringCheckboxChange();
    setRecurrenceFreq('weekly');
    for (let i = 1; i <= 7; i++) {
        const inp = document.getElementById('inputDay' + i);
        if (inp) inp.checked = false;
        updateDayChipUI(i);
    }
    toggleMonthlyModeUI();
    toggleYearlyModeUI();

    const recNotice = document.getElementById('editRecurringSeriesContainer');
    if (recNotice) recNotice.classList.add('hidden');
    const recSection = document.getElementById('recurrenceSection');
    if (recSection) recSection.classList.remove('hidden');

    toggleUnitSelect();
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
    
    // Explicitly grab multiple recurrence days
    data.recurrence_days = formData.getAll('recurrence_days[]').map(Number);
    data.is_recurring = document.getElementById('sideFormIsRecurring').checked ? 1 : 0;
    
    // Auto holiday flag based on category for convenience
    data.is_holiday = (data.category === 'Libur Nasional' || data.category === 'Libur Sekolah') ? 1 : 0;

    // Validate recurring when adding a new event
    if (data.is_recurring && (!data.id || data.id === '')) {
        if (!data.repeat_until) {
            notifyToast('Silakan tentukan tanggal batas pengulangan.', 'error');
            return;
        }
        if (data.recurrence_type === 'weekly' && data.recurrence_days.length === 0) {
            notifyToast('Pilih minimal satu hari untuk pengulangan pekanan.', 'error');
            return;
        }
    }

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
        if (res.success) {
            const msg = res.message || 'Kegiatan berhasil disimpan!';
            sessionStorage.setItem('toast_message', msg);
            sessionStorage.setItem('toast_type', 'success');
            location.reload();
        } else {
            const errorMsg = res.message || 'Gagal menyimpan kegiatan.';
            notifyToast(errorMsg, 'error');
        }
    })
    .catch(err => {
        notifyToast('Terjadi kesalahan koneksi server', 'error');
    });
}

function deleteEventAjax() {
    openCalendarDeleteModal();
}

function openCalendarDeleteModal() {
    const modal = document.getElementById('calendarDeleteModal');
    const backdrop = document.getElementById('calDeleteBackdrop');
    const panel = document.getElementById('calDeletePanel');
    const repeatGroupId = document.getElementById('sideFormRepeatGroupId').value;

    const singleWrap = document.getElementById('singleDeleteBtnWrap');
    const seriesWrap = document.getElementById('seriesDeleteBtnWrap');
    const modalDesc = document.getElementById('calDeleteModalDesc');

    if (repeatGroupId) {
        if (singleWrap) singleWrap.classList.add('hidden');
        if (seriesWrap) seriesWrap.classList.remove('hidden');
        if (modalDesc) modalDesc.innerText = 'Kegiatan ini merupakan bagian dari kegiatan berulang. Anda dapat menghapus kegiatan pada tanggal ini saja atau menghapus seluruh rangkaiannya.';
    } else {
        if (singleWrap) singleWrap.classList.remove('hidden');
        if (seriesWrap) seriesWrap.classList.add('hidden');
        if (modalDesc) modalDesc.innerText = 'Apakah Anda yakin ingin menghapus kegiatan ini dari kalender? Tindakan ini tidak dapat dibatalkan.';
    }

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

function confirmDeleteEvent(scope = 'single') {
    const id = document.getElementById('sideFormID').value;
    if (!id) return;

    fetch('../../logic/calendar/delete_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, delete_scope: scope })
    })
    .then(r => r.json())
    .then(res => {
        closeCalendarDeleteModal();
        if (res.success) {
            const msg = res.message || 'Kegiatan berhasil dihapus!';
            sessionStorage.setItem('toast_message', msg);
            sessionStorage.setItem('toast_type', 'success');
            location.reload();
        } else {
            const errorMsg = res.message || 'Gagal menghapus kegiatan';
            notifyToast(errorMsg, 'error');
        }
    })
    .catch(err => {
        closeCalendarDeleteModal();
        notifyToast('Terjadi kesalahan koneksi server', 'error');
    });
}

function openImportModal() {
    document.getElementById('importFile').value = '';
    document.getElementById('importPreviewSection').classList.add('hidden');
    document.getElementById('btnConfirmImport').classList.add('hidden');

    const modal = document.getElementById('importModal');
    const backdrop = document.getElementById('importBackdrop');
    const panel = document.getElementById('importPanel');

    modal.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('opacity-0', 'scale-95');
    }, 10);
}

function closeImportModal() {
    const modal = document.getElementById('importModal');
    const backdrop = document.getElementById('importBackdrop');
    const panel = document.getElementById('importPanel');

    backdrop.classList.add('opacity-0');
    panel.classList.add('opacity-0', 'scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function openResetModal() {
    const modal = document.getElementById('resetModal');
    const backdrop = document.getElementById('resetBackdrop');
    const panel = document.getElementById('resetPanel');

    modal.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('opacity-0', 'scale-95');
    }, 10);
}

function closeResetModal() {
    const modal = document.getElementById('resetModal');
    const backdrop = document.getElementById('resetBackdrop');
    const panel = document.getElementById('resetPanel');

    backdrop.classList.add('opacity-0');
    panel.classList.add('opacity-0', 'scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function confirmResetCalendarEvents() {
    fetch('../../logic/calendar/reset_events.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(res => {
        closeResetModal();
        if (res.success) {
            const msg = res.message || 'Seluruh data agenda berhasil direset!';
            sessionStorage.setItem('toast_message', msg);
            sessionStorage.setItem('toast_type', 'success');
            location.reload();
        } else {
            const errorMsg = res.message || 'Gagal mereset data agenda';
            notifyToast(errorMsg, 'error');
        }
    })
    .catch(err => {
        closeResetModal();
        notifyToast('Terjadi kesalahan koneksi server', 'error');
    });
}

let parsedImportRows = [];

function uploadImportFile() {
    const fileInput = document.getElementById('importFile');
    if (!fileInput.files || fileInput.files.length === 0) {
        notifyToast('Silakan pilih file CSV/XLSX terlebih dahulu.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    fetch('<?php url("api/calendar/import.php?action=preview"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            notifyToast(res.message || 'Gagal memproses file', 'error');
            return;
        }

        parsedImportRows = res.rows || [];
        const summary = res.summary;

        document.getElementById('countTotal').innerText = summary.total;
        document.getElementById('countValid').innerText = summary.valid;
        document.getElementById('countDuplicate').innerText = summary.duplicate;
        document.getElementById('countError').innerText = summary.error;

        const tbody = document.getElementById('importPreviewTbody');
        tbody.innerHTML = '';

        parsedImportRows.forEach(r => {
            const tr = document.createElement('tr');
            tr.className = 'border-b border-slate-100 text-[11px]';
            
            let statusBadge = '';
            if (r.status === 'valid') {
                statusBadge = '<span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold">✓ Valid</span>';
            } else if (r.status === 'duplicate') {
                statusBadge = '<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold">⚠ Duplikat</span>';
            } else {
                statusBadge = '<span class="px-2 py-0.5 rounded-full bg-red-100 text-red-800 font-bold">⚠ Error</span>';
            }

            const errorText = r.errors && r.errors.length > 0 ? r.errors.join(', ') : '-';

            tr.innerHTML = `
                <td class="p-2 text-center font-bold text-slate-500">${r.row_num}</td>
                <td class="p-2 font-bold text-slate-800">${r.title}</td>
                <td class="p-2">${r.start_date} ${r.end_date && r.end_date !== r.start_date ? 's/d ' + r.end_date : ''}</td>
                <td class="p-2">${r.category}</td>
                <td class="p-2">${r.source_type}</td>
                <td class="p-2 text-center">${statusBadge}</td>
                <td class="p-2 text-slate-500">${errorText}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('importPreviewSection').classList.remove('hidden');
        if (summary.valid > 0) {
            document.getElementById('btnConfirmImport').classList.remove('hidden');
        } else {
            document.getElementById('btnConfirmImport').classList.add('hidden');
        }
    })
    .catch(err => notifyToast('Terjadi kesalahan koneksi server', 'error'));
}

function confirmImportData() {
    const validRows = parsedImportRows.filter(r => r.status === 'valid');
    if (validRows.length === 0) {
        notifyToast('Tidak ada data valid yang dapat diimport.', 'error');
        return;
    }

    fetch('<?php url("api/calendar/import.php?action=confirm"); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rows: validRows })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeImportModal();
            const msg = res.message || `${validRows.length} agenda berhasil diimport ke Kalender Akademik.`;
            sessionStorage.setItem('toast_message', msg);
            sessionStorage.setItem('toast_type', 'success');
            location.reload();
        } else {
            const errorMsg = res.message || 'Gagal mengimport data agenda.';
            notifyToast(errorMsg, 'error');
        }
    })
    .catch(err => {
        notifyToast('Terjadi kesalahan koneksi server.', 'error');
    });
}

// On Page Load: Check if there is a pending toast from previous page reload
window.addEventListener('load', () => {
    const toastMsg = sessionStorage.getItem('toast_message');
    const toastType = sessionStorage.getItem('toast_type') || 'success';
    if (toastMsg) {
        sessionStorage.removeItem('toast_message');
        sessionStorage.removeItem('toast_type');
        setTimeout(() => {
            notifyToast(toastMsg, toastType);
        }, 300);
    }
});
</script>

<!-- Custom Delete Modal for Calendar -->
<div id="calendarDeleteModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div id="calDeleteBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300 opacity-0"></div>
    <div class="flex min-h-full items-center justify-center p-4 text-center">
        <div id="calDeletePanel" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-md">
            <div class="bg-white p-6 sm:p-7">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-red-100/80 text-red-600 flex items-center justify-center shrink-0 border border-red-200/60 shadow-2xs">
                        <i class="fa-solid fa-trash-can text-xl leading-none"></i>
                    </div>
                    <div class="space-y-1.5 pt-0.5">
                        <h3 class="text-lg font-extrabold text-slate-900 leading-snug" id="calDeleteModalTitle">Hapus Kegiatan?</h3>
                        <p class="text-xs text-slate-500 leading-relaxed font-medium" id="calDeleteModalDesc">Apakah Anda yakin ingin menghapus kegiatan ini dari kalender? Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-6 py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 border-t border-slate-100">
                <button type="button" onclick="closeCalendarDeleteModal()" class="px-5 py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-all">
                    Batalkan
                </button>
                <div id="singleDeleteBtnWrap" class="inline-flex">
                    <button type="button" onclick="confirmDeleteEvent('single')" class="px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-xs font-bold text-white shadow-md shadow-red-500/20 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                        <span>Hapus Sekarang</span>
                    </button>
                </div>
                <div id="seriesDeleteBtnWrap" class="hidden flex flex-col sm:flex-row gap-2">
                    <button type="button" onclick="confirmDeleteEvent('single')" class="px-4 py-2.5 rounded-xl bg-white border border-red-200 hover:bg-red-50 text-xs font-bold text-red-600 transition-all">
                        Hanya Tanggal Ini
                    </button>
                    <button type="button" onclick="confirmDeleteEvent('series')" class="px-4 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-xs font-bold text-white shadow-md shadow-red-500/20 transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-arrows-rotate text-xs"></i>
                        <span>Hapus Seluruh Rangkaian</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Import Data -->
<div id="importModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div id="importBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300 opacity-0" onclick="closeImportModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div id="importPanel" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 max-w-3xl w-full">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-file-import text-blue-600 text-lg"></i>
                    <h3 class="text-base font-extrabold text-slate-800">Import Data Kalender Akademik</h3>
                </div>
                <button onclick="closeImportModal()" class="w-8 h-8 rounded-full hover:bg-slate-200 flex items-center justify-center text-slate-500 text-sm transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs">
                <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-between">
                    <div>
                        <h4 class="font-extrabold text-blue-900">Format File Import</h4>
                        <p class="text-blue-700 text-[11px]">Gunakan format CSV atau Excel (.xlsx). Unduh template acuan jika belum memilikinya.</p>
                    </div>
                    <a href="<?php url('api/calendar/template.php'); ?>" download class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-download"></i> Unduh Template
                    </a>
                </div>

                <div class="flex items-center gap-3">
                    <input type="file" id="importFile" accept=".csv, .xlsx, .xls" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                    <button onclick="uploadImportFile()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold flex items-center gap-1.5 shadow-sm shrink-0">
                        <i class="fa-solid fa-magnifying-glass"></i> Pratinjau
                    </button>
                </div>

                <!-- Preview Section -->
                <div id="importPreviewSection" class="space-y-3 hidden pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <h4 class="font-extrabold text-slate-800 uppercase tracking-wide">Pratinjau Data</h4>
                        <div class="flex items-center gap-2 font-bold text-[11px]">
                            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700">Total: <b id="countTotal">0</b></span>
                            <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800">Valid: <b id="countValid">0</b></span>
                            <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-800">Duplikat: <b id="countDuplicate">0</b></span>
                            <span class="px-2 py-0.5 rounded-md bg-red-100 text-red-800">Error: <b id="countError">0</b></span>
                        </div>
                    </div>

                    <div class="max-h-60 overflow-y-auto border border-slate-200 rounded-2xl">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50 sticky top-0 border-b border-slate-200 text-[10px] font-extrabold uppercase text-slate-600">
                                <tr>
                                    <th class="p-2 text-center w-10">No</th>
                                    <th class="p-2">Kegiatan</th>
                                    <th class="p-2">Mulai - Selesai</th>
                                    <th class="p-2">Kategori</th>
                                    <th class="p-2">Sumber</th>
                                    <th class="p-2 text-center">Status</th>
                                    <th class="p-2">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody id="importPreviewTbody"></tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeImportModal()" class="px-4 py-2 rounded-xl border border-slate-300 font-bold text-slate-600 hover:bg-slate-100">
                        Batal
                    </button>
                    <button id="btnConfirmImport" type="button" onclick="confirmImportData()" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-md hidden">
                        Import Sekarang
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reset Data Kalender -->
<div id="resetModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div id="resetBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300 opacity-0" onclick="closeResetModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4 text-center">
        <div id="resetPanel" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-md">
            <div class="bg-white p-6 sm:p-7">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-red-100/80 text-red-600 flex items-center justify-center shrink-0 border border-red-200/60 shadow-2xs">
                        <i class="fa-solid fa-triangle-exclamation text-xl leading-none"></i>
                    </div>
                    <div class="space-y-1.5 pt-0.5">
                        <h3 class="text-lg font-extrabold text-slate-900 leading-snug" id="modal-title">Reset Semua Data Agenda?</h3>
                        <p class="text-xs text-slate-500 leading-relaxed font-medium">Apakah Anda yakin ingin menghapus <b>SELURUH</b> data agenda kegiatan dari kalender akademik? Semua agenda lokal yang tersimpan akan terhapus secara permanen dan tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-6 py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 border-t border-slate-100">
                <button type="button" onclick="closeResetModal()" class="px-5 py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-all">
                    Batal
                </button>
                <button type="button" onclick="confirmResetCalendarEvents()" class="px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-xs font-bold text-white shadow-md shadow-red-500/20 transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-trash-can text-xs"></i>
                    <span>Ya, Reset Sekarang</span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
