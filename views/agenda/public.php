<?php
// views/agenda/public.php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// Rate Limiting: 60 requests per minute per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$rateLimitFile = __DIR__ . '/../../tmp/rate_limit_' . md5($ip) . '.json';
$now = time();
$window = 60;
$maxRequests = 60;

if (file_exists($rateLimitFile)) {
    $rateData = json_decode(file_get_contents($rateLimitFile), true);
    if ($rateData && ($now - $rateData['start_time']) < $window) {
        if ($rateData['count'] >= $maxRequests) {
            http_response_code(429);
            echo "<!DOCTYPE html><html lang='id'><head><title>429 Too Many Requests</title></head><body style='font-family:sans-serif;text-align:center;padding:50px;'><h1>429 Too Many Requests</h1><p>Terlalu banyak permintaan. Silakan coba beberapa saat lagi.</p></body></html>";
            exit;
        }
        $rateData['count']++;
    } else {
        $rateData = ['start_time' => $now, 'count' => 1];
    }
} else {
    $rateData = ['start_time' => $now, 'count' => 1];
}
if (!is_dir(__DIR__ . '/../../tmp')) mkdir(__DIR__ . '/../../tmp', 0777, true);
file_put_contents($rateLimitFile, json_encode($rateData));

$db = new Database();
$conn = $db->getConnection();

// Fetch units & academic years for public dropdowns
$unitsStmt = $conn->query("SELECT id, name FROM education_units ORDER BY name ASC");
$units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);

$ayStmt = $conn->query("SELECT id, name, is_active FROM academic_years ORDER BY start_date DESC");
$academic_years = $ayStmt->fetchAll(PDO::FETCH_ASSOC);
$active_ay = array_filter($academic_years, function($ay) { return $ay['is_active'] == 1; });
$active_ay_id = !empty($active_ay) ? reset($active_ay)['id'] : (!empty($academic_years) ? $academic_years[0]['id'] : null);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Agenda Bid. Pendidikan - Yayasan Assunnah Cirebon</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php url('assets/images/favicon.png'); ?>">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background-color: #fff;
            gap: 1px;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
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

        /* Category Colors */
        .bg-holiday { background-color: #ffefef; color: #dc2626; border-color: #fecaca; }
        .bg-school-holiday { background-color: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
        .bg-collective { background-color: #fffbeb; color: #d97706; border-color: #fef3c7; }
        .bg-academic { background-color: #f0f9ff; color: #0284c7; border-color: #bae6fd; }
        .bg-meeting { background-color: #f5f3ff; color: #7c3aed; border-color: #ddd6fe; }
        .bg-yayasan { background-color: #f0fdfa; color: #0d9488; border-color: #ccfbf1; }
        .bg-other { background-color: #f8fafc; color: #475569; border-color: #e2e8f0; }

        .timeline-container {
            display: flex;
            overflow-x: auto;
            padding-bottom: 1rem;
            gap: 1.5rem;
            scrollbar-width: thin;
        }
        .timeline-card {
            min-width: 240px;
            max-width: 280px;
            flex-shrink: 0;
        }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex flex-col justify-between">

<div>
    <!-- Public Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="w-full px-4 sm:px-6 lg:px-10 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3.5">
                <div class="h-12 w-12 flex items-center justify-center rounded-xl bg-slate-50 p-1 shadow-sm border border-slate-200">
                    <img src="<?php echo url('public/images/logo.png'); ?>" alt="Logo YAC" class="w-full h-full object-contain">
                </div>
                <div>
                    <h1 class="text-lg font-black text-slate-900 tracking-tight">Agenda Bidang Pendidikan</h1>
                    <p class="text-xs text-slate-500 font-semibold">Yayasan Assunnah Cirebon</p>
                </div>
            </div>
            <div class="hidden sm:block text-right text-xs text-slate-800 font-medium">
                Pusat Informasi Kegiatan Bidang Pendidikan & Unit YAC
            </div>
        </div>
    </header>

    <main class="w-full max-w-full px-4 sm:px-6 lg:px-10 xl:px-14 py-8 space-y-6">

        <!-- Three Column Grid Layout: Filter (Col 3), Calendar (Col 6), Agenda Cards 1 & 2 (Col 3) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Left Column: Card Filter Agenda & Coming Soon Timeline (lg:col-span-3) -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Card Filter Agenda -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-1">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-filter text-blue-600"></i>
                            Filter Agenda
                        </h3>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Tahun Akademik</label>
                            <select id="filter_academic_year_id" onchange="loadPublicAgenda()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-all outline-none cursor-pointer">
                                <option value="">Semua Tahun</option>
                                <?php foreach ($academic_years as $ay): ?>
                                    <option value="<?php echo $ay['id']; ?>" <?php echo $ay['id'] == $active_ay_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ay['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Semester</label>
                            <select id="filter_semester" onchange="loadPublicAgenda()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-all outline-none cursor-pointer">
                                <option value="Semua">Semua Semester</option>
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Sumber Agenda</label>
                            <select id="filter_source_type" onchange="loadPublicAgenda()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-all outline-none cursor-pointer">
                                <option value="Semua">Semua Sumber</option>
                                <option value="yayasan">Yayasan</option>
                                <option value="bidang_pendidikan">Bidang Pendidikan</option>
                                <option value="unit">Unit Pendidikan</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Kategori</label>
                            <select id="filter_category" onchange="loadPublicAgenda()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-all outline-none cursor-pointer">
                                <option value="Semua">Semua Kategori</option>
                                <option value="Libur Nasional">Libur Nasional</option>
                                <option value="Libur Sekolah">Libur Sekolah</option>
                                <option value="Cuti Bersama">Cuti Bersama</option>
                                <option value="Rapat">Rapat</option>
                                <option value="Kegiatan Yayasan">Kegiatan Yayasan</option>
                                <option value="Kegiatan Bidang Pendidikan">Kegiatan Bidang Pendidikan</option>
                                <option value="Kegiatan Unit">Kegiatan Unit</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Card Coming Soon Timeline (Dibawah Card Filter) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-clock-rotate-left text-amber-500"></i>
                                Coming Soon Timeline
                            </h3>
                        </div>

                        <div id="coming_soon_timeline" class="space-y-3 max-h-[300px] overflow-y-auto pr-1"></div>
                    </div>
                </div>
            </div>

            <!-- Center Column: Calendar Month Card (lg:col-span-6) -->
            <div class="lg:col-span-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
                    <!-- Calendar Header with Month & Year Dropdown Selector -->
                    <div class="p-5 flex flex-wrap items-center justify-between gap-4 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <h2 id="current_month_title" class="text-xl font-bold text-slate-800 shrink-0 hidden sm:block">Agustus 2026</h2>
                            
                            <!-- Month & Year Select Dropdowns -->
                            <div class="flex items-center gap-2">
                                <select id="select_month" onchange="jumpPublicMonthYear()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-all outline-none cursor-pointer">
                                    <option value="1">Januari</option>
                                    <option value="2">Februari</option>
                                    <option value="3">Maret</option>
                                    <option value="4">April</option>
                                    <option value="5">Mei</option>
                                    <option value="6">Juni</option>
                                    <option value="7">Juli</option>
                                    <option value="8">Agustus</option>
                                    <option value="9">September</option>
                                    <option value="10">Oktober</option>
                                    <option value="11">November</option>
                                    <option value="12">Desember</option>
                                </select>
                                <select id="select_year" onchange="jumpPublicMonthYear()" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-all outline-none cursor-pointer">
                                    <?php for ($y = date('Y') - 3; $y <= date('Y') + 3; $y++): ?>
                                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" onclick="changeMonth(-1)" title="Bulan Sebelumnya" class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 rounded-xl transition-all text-slate-600 border border-slate-200/80">
                                <i class="fa-solid fa-chevron-left text-xs"></i>
                            </button>
                            <button type="button" onclick="goToday()" class="px-3.5 py-2 text-xs font-bold text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-xl transition-all flex items-center gap-1.5 border border-slate-200/80">
                                <i class="fa-solid fa-calendar-day text-blue-600 text-xs"></i>
                                <span>Bulan Ini</span>
                            </button>
                            <button type="button" onclick="changeMonth(1)" title="Bulan Berikutnya" class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 rounded-xl transition-all text-slate-600 border border-slate-200/80">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Calendar Grid Wrapper for Responsiveness -->
                    <div class="overflow-x-auto p-5">
                        <div class="calendar-grid min-w-[650px] md:min-w-full" id="calendar_grid"></div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Agenda Cards 1 & 2 (lg:col-span-3) -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Card 1: Agenda Bidang Pendidikan -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-bullhorn text-indigo-600"></i>
                                Agenda Bidang Pendidikan
                            </h3>
                        </div>

                        <div id="bidang_agenda_list" class="space-y-3 max-h-[300px] overflow-y-auto pr-1"></div>
                    </div>
                </div>

                <!-- Card 2: Agenda Unit Pendidikan -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-school text-emerald-600"></i>
                                Agenda Unit Pendidikan
                            </h3>
                        </div>

                        <div id="unit_agenda_list" class="space-y-3 max-h-[300px] overflow-y-auto pr-1"></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Section: Kegiatan Unit Pendidikan -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2">
                        <i class="fa-solid fa-school text-emerald-600"></i>
                        Kegiatan Unit Pendidikan
                    </h3>
                </div>

                <!-- Unit Filter Tabs -->
                <div id="unit_filter_tabs" class="flex items-center gap-1 overflow-x-auto pb-1 sm:pb-0">
                    <button onclick="filterUnitTab('Semua')" class="unit-tab active px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-600 text-white shadow-sm transition-all">
                        Semua Unit
                    </button>
                    <?php foreach ($units as $u): ?>
                        <button onclick="filterUnitTab('<?php echo $u['id']; ?>')" class="unit-tab px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-600 transition-all">
                            <?php echo htmlspecialchars($u['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="unit_cards_container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4"></div>
        </div>



    </main>
</div>


<!-- Modal Detail Day Events -->
<div id="dayDetailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <h3 id="dayDetailTitle" class="text-base font-extrabold text-slate-800">Agenda Tanggal</h3>
            <button onclick="closeModal('dayDetailModal')" class="w-8 h-8 rounded-full hover:bg-slate-200 flex items-center justify-center text-slate-500 text-sm">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="dayDetailList" class="p-6 space-y-3 max-h-[450px] overflow-y-auto text-xs"></div>
    </div>
</div>

<script>
    let currentMonth = <?php echo (int)date('m'); ?>;
    let currentYear = <?php echo (int)date('Y'); ?>;
    let agendaData = [];
    let activeUnitTab = 'Semua';

    const indoMonths = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];

    document.addEventListener('DOMContentLoaded', () => {
        loadPublicAgenda();
    });

    function jumpPublicMonthYear() {
        currentMonth = parseInt(document.getElementById('select_month').value);
        currentYear = parseInt(document.getElementById('select_year').value);
        loadPublicAgenda();
    }

    function changeMonth(offset) {
        currentMonth += offset;
        if (currentMonth > 12) { currentMonth = 1; currentYear++; }
        else if (currentMonth < 1) { currentMonth = 12; currentYear--; }
        loadPublicAgenda();
    }

    function goToday() {
        const today = new Date();
        currentMonth = today.getMonth() + 1;
        currentYear = today.getFullYear();
        loadPublicAgenda();
    }

    function loadPublicAgenda() {
        document.getElementById('current_month_title').innerText = `${indoMonths[currentMonth]} ${currentYear}`;
        if (document.getElementById('select_month')) document.getElementById('select_month').value = currentMonth;
        if (document.getElementById('select_year')) document.getElementById('select_year').value = currentYear;

        const ay = document.getElementById('filter_academic_year_id').value;
        const sem = document.getElementById('filter_semester').value;
        const src = document.getElementById('filter_source_type').value;
        const cat = document.getElementById('filter_category').value;

        const params = new URLSearchParams({
            month: currentMonth,
            year: currentYear
        });
        if (ay) params.append('academic_year_id', ay);
        if (sem !== 'Semua') params.append('semester', sem);
        if (src !== 'Semua') params.append('source_type', src);
        if (cat !== 'Semua') params.append('category', cat);

        fetch(`<?php url('api/agenda/public.php'); ?>?${params.toString()}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    agendaData = res.data || [];
                    renderCalendarGrid();
                    renderBidangAgenda();
                    renderUnitAgendaList();
                    renderUnitCards();
                    renderComingSoonTimeline();
                }
            });
    }

    function renderCalendarGrid() {
        const grid = document.getElementById('calendar_grid');
        grid.innerHTML = '';

        const headers = ["SEN", "SEL", "RAB", "KAM", "JUM", "SAB", "AHAD"];
        headers.forEach(h => {
            const div = document.createElement('div');
            div.className = 'calendar-day-header';
            div.innerText = h;
            grid.appendChild(div);
        });

        const firstDayTS = new Date(currentYear, currentMonth - 1, 1);
        const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
        const startDayOfWeek = (firstDayTS.getDay() + 6) % 7; // 0 for Mon, 6 for Sun

        const todayObj = new Date();
        const todayStr = `${todayObj.getFullYear()}-${String(todayObj.getMonth() + 1).padStart(2, '0')}-${String(todayObj.getDate()).padStart(2, '0')}`;

        for (let i = 0; i < startDayOfWeek; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'calendar-cell bg-slate-50/50 opacity-40 cursor-default';
            grid.appendChild(emptyCell);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const cell = document.createElement('div');
            
            const isSunday = (startDayOfWeek + day - 1) % 7 === 6;
            const isToday = dateStr === todayStr;

            cell.className = `calendar-cell ${isSunday ? 'sunday' : ''} ${isToday ? 'today' : ''}`;
            cell.onclick = () => openDayDetail(dateStr);

            const dateNum = document.createElement('span');
            dateNum.className = 'calendar-date-num';
            dateNum.innerText = day;
            cell.appendChild(dateNum);

            const dayEvents = agendaData.filter(e => dateStr >= e.start_date && dateStr <= (e.end_date || e.start_date));
            
            dayEvents.slice(0, 3).forEach(e => {
                let catClass = "bg-other";
                if (e.category === 'Libur Nasional' || e.is_holiday == 1) catClass = "bg-holiday";
                else if (e.category === 'Libur Sekolah') catClass = "bg-school-holiday";
                else if (e.category === 'Cuti Bersama') catClass = "bg-collective";
                else if (e.category === 'Akademik') catClass = "bg-academic";
                else if (e.category === 'Rapat') catClass = "bg-meeting";
                else if (e.category === 'Kegiatan Yayasan') catClass = "bg-yayasan";

                const ttContainer = document.createElement('div');
                ttContainer.className = 'tooltip-container';

                const pill = document.createElement('div');
                pill.className = `event-pill ${catClass}`;
                pill.innerText = (e.start_time ? e.start_time.substring(0,5) + ' ' : '') + e.title;

                const tt = document.createElement('div');
                tt.className = 'custom-tooltip';
                tt.innerText = e.title;

                ttContainer.appendChild(pill);
                ttContainer.appendChild(tt);
                cell.appendChild(ttContainer);
            });

            if (dayEvents.length > 3) {
                const more = document.createElement('span');
                more.className = 'text-[9px] font-extrabold text-blue-600 px-1';
                more.innerText = `+${dayEvents.length - 3} agenda lagi`;
                cell.appendChild(more);
            }

            grid.appendChild(cell);
        }

        const totalCells = startDayOfWeek + daysInMonth;
        const trailingEmpty = (7 - (totalCells % 7)) % 7;
        for (let i = 0; i < trailingEmpty; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'calendar-cell bg-slate-50/50 opacity-40 cursor-default';
            grid.appendChild(emptyCell);
        }

        if (document.getElementById('calendar_event_count')) {
            document.getElementById('calendar_event_count').innerText = `${agendaData.length} Agenda`;
        }
    }

    let bidangScrollInterval = null;

    function startBidangAutoScroll() {
        const container = document.getElementById('bidang_agenda_list');
        if (!container) return;

        if (bidangScrollInterval) {
            clearInterval(bidangScrollInterval);
            bidangScrollInterval = null;
        }

        // Check if content overflows container
        if (container.scrollHeight <= container.clientHeight) {
            return;
        }

        // Duplicate elements for seamless infinite looping
        const originalCards = Array.from(container.children);
        originalCards.forEach(card => {
            const clone = card.cloneNode(true);
            container.appendChild(clone);
        });

        let isHovered = false;
        container.onmouseenter = () => { isHovered = true; };
        container.onmouseleave = () => { isHovered = false; };

        const halfHeight = container.scrollHeight / 2;
        let scrollAcc = container.scrollTop || 0;

        bidangScrollInterval = setInterval(() => {
            if (isHovered) return;
            scrollAcc += 0.5;
            container.scrollTop = scrollAcc;
            if (container.scrollTop >= halfHeight || scrollAcc >= halfHeight) {
                scrollAcc = 0;
                container.scrollTop = 0;
            }
        }, 30);
    }

    function renderBidangAgenda() {
        const container = document.getElementById('bidang_agenda_list');
        if (bidangScrollInterval) {
            clearInterval(bidangScrollInterval);
            bidangScrollInterval = null;
        }
        container.innerHTML = '';

        // Agenda Bidang Pendidikan / Yayasan (Events without specific unit_id)
        const bidangEvents = agendaData.filter(e => !e.unit_id && (e.source_type === 'bidang_pendidikan' || e.source_type === 'yayasan'));

        if (bidangEvents.length === 0) {
            container.innerHTML = `<div class="text-center py-8 text-slate-400 font-medium text-xs">Belum ada agenda Bidang Pendidikan di bulan ini</div>`;
            return;
        }

        bidangEvents.forEach(e => {
            const card = document.createElement('div');
            card.className = 'p-3.5 rounded-2xl border border-slate-100 bg-slate-50/70 space-y-1';
            card.innerHTML = `
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold text-slate-500" style="color:${e.color || '#3b82f6'}">${e.category}</span>
                </div>
                <h4 class="font-extrabold text-slate-800 text-xs">${e.title}</h4>
                <div class="text-[11px] text-slate-500 font-medium flex items-center gap-3">
                    <span><i class="fa-regular fa-calendar mr-1 text-slate-400"></i>${formatDateIndo(e.start_date)}</span>
                    ${e.start_time ? `<span><i class="fa-regular fa-clock mr-1 text-slate-400"></i>${e.start_time.substring(0,5)}</span>` : ''}
                </div>
                ${e.location ? `<div class="text-[11px] text-slate-500 font-medium"><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i>${e.location}</div>` : ''}
            `;
            container.appendChild(card);
        });

        setTimeout(() => {
            startBidangAutoScroll();
        }, 150);
    }

    let unitScrollInterval = null;

    function startUnitAutoScroll() {
        const container = document.getElementById('unit_agenda_list');
        if (!container) return;

        if (unitScrollInterval) {
            clearInterval(unitScrollInterval);
            unitScrollInterval = null;
        }

        // Check if content overflows container
        if (container.scrollHeight <= container.clientHeight) {
            return;
        }

        // Duplicate elements for seamless infinite looping
        const originalCards = Array.from(container.children);
        originalCards.forEach(card => {
            const clone = card.cloneNode(true);
            container.appendChild(clone);
        });

        let isHovered = false;
        container.onmouseenter = () => { isHovered = true; };
        container.onmouseleave = () => { isHovered = false; };

        const halfHeight = container.scrollHeight / 2;
        let scrollAcc = container.scrollTop || 0;

        unitScrollInterval = setInterval(() => {
            if (isHovered) return;
            scrollAcc += 0.5;
            container.scrollTop = scrollAcc;
            if (container.scrollTop >= halfHeight || scrollAcc >= halfHeight) {
                scrollAcc = 0;
                container.scrollTop = 0;
            }
        }, 30);
    }

    function getUnitBadgeStyle(unitName, unitId) {
        const name = (unitName || '').toUpperCase();
        if (name.includes('TK') || name.includes('RA') || name.includes('PAUD')) {
            return 'bg-pink-50 text-pink-700 border-pink-200/80';
        } else if (name.includes('SD')) {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200/80';
        } else if (name.includes('SMP')) {
            return 'bg-sky-50 text-sky-700 border-sky-200/80';
        } else if (name.includes('SMA') || name.includes('MA') || name.includes('SMK')) {
            return 'bg-amber-50 text-amber-800 border-amber-200/80';
        } else if (name.includes('STDI') || name.includes('KULIAH') || name.includes('MAHAD')) {
            return 'bg-purple-50 text-purple-700 border-purple-200/80';
        }
        
        const styles = [
            'bg-indigo-50 text-indigo-700 border-indigo-200/80',
            'bg-teal-50 text-teal-700 border-teal-200/80',
            'bg-orange-50 text-orange-700 border-orange-200/80',
            'bg-rose-50 text-rose-700 border-rose-200/80',
            'bg-blue-50 text-blue-700 border-blue-200/80'
        ];
        const index = (unitId ? parseInt(unitId) : (unitName ? unitName.length : 0)) % styles.length;
        return styles[index];
    }

    function renderUnitAgendaList() {
        const container = document.getElementById('unit_agenda_list');
        if (!container) return;

        if (unitScrollInterval) {
            clearInterval(unitScrollInterval);
            unitScrollInterval = null;
        }

        container.innerHTML = '';

        // Agenda Unit Pendidikan (Events with specific unit_id or source_type unit)
        const unitEvents = agendaData.filter(e => Boolean(e.unit_id) || e.source_type === 'unit');

        if (unitEvents.length === 0) {
            container.innerHTML = `<div class="text-center py-8 text-slate-400 font-medium text-xs">Belum ada agenda Unit Pendidikan di bulan ini</div>`;
            return;
        }

        unitEvents.forEach(e => {
            const card = document.createElement('div');
            card.className = 'p-3.5 rounded-2xl border border-slate-100 bg-slate-50/70 space-y-1';
            card.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border ${getUnitBadgeStyle(e.unit_name, e.unit_id)}">${e.unit_name || 'Unit'}</span>
                    <span class="text-[10px] font-bold text-slate-500" style="color:${e.color || '#3b82f6'}">${e.category}</span>
                </div>
                <h4 class="font-extrabold text-slate-800 text-xs">${e.title}</h4>
                <div class="text-[11px] text-slate-500 font-medium flex items-center gap-3">
                    <span><i class="fa-regular fa-calendar mr-1 text-slate-400"></i>${formatDateIndo(e.start_date)}</span>
                    ${e.start_time ? `<span><i class="fa-regular fa-clock mr-1 text-slate-400"></i>${e.start_time.substring(0,5)}</span>` : ''}
                </div>
                ${e.location ? `<div class="text-[11px] text-slate-500 font-medium"><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i>${e.location}</div>` : ''}
            `;
            container.appendChild(card);
        });

        setTimeout(() => {
            startUnitAutoScroll();
        }, 150);
    }

    function filterUnitTab(unitId) {
        activeUnitTab = unitId;
        document.querySelectorAll('.unit-tab').forEach(tab => {
            tab.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
            tab.classList.add('bg-slate-100', 'text-slate-600');
        });
        event.target.classList.remove('bg-slate-100', 'text-slate-600');
        event.target.classList.add('bg-blue-600', 'text-white', 'shadow-sm');

        renderUnitCards();
    }

    function renderUnitCards() {
        const container = document.getElementById('unit_cards_container');
        container.innerHTML = '';

        const unitsMeta = <?php echo json_encode($units); ?>;
        let displayUnits = unitsMeta;

        if (activeUnitTab !== 'Semua') {
            displayUnits = unitsMeta.filter(u => u.id == activeUnitTab);
        }

        displayUnits.forEach(u => {
            const unitEvents = agendaData.filter(e => e.unit_id == u.id);
            const card = document.createElement('div');
            card.className = 'p-4 rounded-2xl border border-slate-200 bg-slate-50/50 flex flex-col justify-between space-y-3';

            let eventsListHtml = '';
            if (unitEvents.length === 0) {
                eventsListHtml = `<p class="text-[11px] text-slate-400 italic">Belum ada agenda bulan ini</p>`;
            } else {
                unitEvents.slice(0, 3).forEach(ev => {
                    eventsListHtml += `
                        <div class="text-xs space-y-0.5">
                            <span class="font-bold text-slate-800 block truncate">• ${ev.title}</span>
                            <span class="text-[10px] text-slate-500 font-medium block">${formatDateIndo(ev.start_date)}</span>
                        </div>
                    `;
                });
            }

            card.innerHTML = `
                <div class="space-y-2">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                        <span class="font-extrabold text-slate-800 text-xs uppercase">${u.name}</span>
                        <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 font-bold text-[10px]">${unitEvents.length} Agenda</span>
                    </div>
                    <div class="space-y-2">
                        ${eventsListHtml}
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    }

    let comingSoonScrollInterval = null;

    function startComingSoonAutoScroll() {
        const container = document.getElementById('coming_soon_timeline');
        if (!container) return;

        if (comingSoonScrollInterval) {
            clearInterval(comingSoonScrollInterval);
            comingSoonScrollInterval = null;
        }

        // Check if content overflows container
        if (container.scrollHeight <= container.clientHeight) {
            return;
        }

        // Duplicate elements for seamless infinite looping
        const originalCards = Array.from(container.children);
        originalCards.forEach(card => {
            const clone = card.cloneNode(true);
            container.appendChild(clone);
        });

        let isHovered = false;
        container.onmouseenter = () => { isHovered = true; };
        container.onmouseleave = () => { isHovered = false; };

        const halfHeight = container.scrollHeight / 2;
        let scrollAcc = container.scrollTop || 0;

        comingSoonScrollInterval = setInterval(() => {
            if (isHovered) return;
            scrollAcc += 0.5;
            container.scrollTop = scrollAcc;
            if (container.scrollTop >= halfHeight || scrollAcc >= halfHeight) {
                scrollAcc = 0;
                container.scrollTop = 0;
            }
        }, 30);
    }

    function renderComingSoonTimeline() {
        const container = document.getElementById('coming_soon_timeline');
        if (!container) return;

        if (comingSoonScrollInterval) {
            clearInterval(comingSoonScrollInterval);
            comingSoonScrollInterval = null;
        }

        container.innerHTML = '';

        const sortedEvents = [...agendaData].sort((a, b) => a.start_date.localeCompare(b.start_date));

        if (sortedEvents.length === 0) {
            container.innerHTML = `<div class="text-center py-8 text-slate-400 font-medium text-xs">Belum ada agenda mendatang</div>`;
            return;
        }

        sortedEvents.forEach(e => {
            const card = document.createElement('div');
            card.className = 'p-3.5 rounded-2xl border border-slate-100 bg-slate-50/70 space-y-1';
            
            const dateObj = new Date(e.start_date);
            const dayNum = dateObj.getDate();
            const monthShort = indoMonths[dateObj.getMonth() + 1].substring(0, 3).toUpperCase();
            const badgeLabel = e.unit_name ? e.unit_name : (e.source_type ? e.source_type.replace('_', ' ').toUpperCase() : 'YAC');

            card.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black px-2 py-0.5 rounded-lg bg-blue-600 text-white shadow-sm flex items-center gap-1">
                        <i class="fa-regular fa-clock text-[9px]"></i>
                        ${dayNum} ${monthShort}
                    </span>
                    <span class="text-[10px] font-bold text-slate-500" style="color:${e.color || '#3b82f6'}">${e.category}</span>
                </div>
                <h4 class="font-extrabold text-slate-800 text-xs">${e.title}</h4>
                <div class="text-[11px] text-slate-500 font-medium flex items-center justify-between pt-1">
                    <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border ${getUnitBadgeStyle(e.unit_name, e.unit_id)}">${badgeLabel}</span>
                    ${e.start_time ? `<span><i class="fa-regular fa-clock mr-1 text-slate-400"></i>${e.start_time.substring(0,5)}</span>` : ''}
                </div>
                ${e.location ? `<div class="text-[11px] text-slate-500 font-medium"><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i>${e.location}</div>` : ''}
            `;
            container.appendChild(card);
        });

        setTimeout(() => {
            startComingSoonAutoScroll();
        }, 150);
    }

    function openDayDetail(dateStr) {
        const modal = document.getElementById('dayDetailModal');
        const title = document.getElementById('dayDetailTitle');
        const list = document.getElementById('dayDetailList');

        title.innerText = `Agenda Tanggal: ${formatDateIndo(dateStr)}`;
        list.innerHTML = '';

        const dayEvents = agendaData.filter(e => dateStr >= e.start_date && dateStr <= (e.end_date || e.start_date));

        if (dayEvents.length === 0) {
            list.innerHTML = `<div class="text-center py-6 text-slate-400 font-medium">Belum ada agenda di tanggal ini</div>`;
        } else {
            dayEvents.forEach(e => {
                const item = document.createElement('div');
                item.className = 'p-3.5 rounded-2xl border border-slate-200 bg-slate-50 space-y-1.5';
                item.innerHTML = `
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold text-white inline-block mb-1" style="background-color:${e.color || '#3b82f6'}">${e.category}</span>
                    <h4 class="font-extrabold text-slate-800 text-xs">${e.title}</h4>
                    <p class="text-[11px] text-slate-500 font-medium">${e.description || 'Tanpa deskripsi'}</p>
                    <div class="text-[10px] text-slate-400 flex items-center gap-3 pt-1">
                        <span><i class="fa-regular fa-clock mr-1"></i>${e.start_time || 'Seharian'}</span>
                        <span><i class="fa-solid fa-location-dot mr-1"></i>${e.location || '-'}</span>
                    </div>
                `;
                list.appendChild(item);
            });
        }

        modal.classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function formatDateIndo(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length < 3) return dateStr;
        return `${parseInt(parts[2])} ${indoMonths[parseInt(parts[1])]} ${parts[0]}`;
    }
</script>
</body>
</html>
