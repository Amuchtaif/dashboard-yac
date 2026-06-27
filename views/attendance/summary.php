<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Rekap Absensi Pegawai";

$db = new Database();
$conn = $db->getConnection();

// --- Filter Logic ---
$today_day = (int)date('d');
if ($today_day >= 26) {
    $default_start = date('Y-m-26');
    $default_end = date('Y-m-25', strtotime('+1 month'));
} else {
    $default_start = date('Y-m-26', strtotime('-1 month'));
    $default_end = date('Y-m-25');
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $default_end;
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- Pagination Logic ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// Ambil List Bidang & Unit untuk Filter
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$units = $conn->query("SELECT id, name FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Build WHERE Clause (Base Employees)
$where = " WHERE e.id != 1 AND (e.status = 'active' OR e.status IS NULL) ";
$params = [':start_date' => $start_date, ':end_date' => $end_date];

if ($search) {
    $where .= " AND e.full_name LIKE :search ";
    $params[':search'] = "%$search%";
}
if ($division_id) {
    $where .= " AND e.division_id = :division_id ";
    $params[':division_id'] = $division_id;
}
if ($unit_id) {
    $where .= " AND e.unit_id = :unit_id ";
    $params[':unit_id'] = $unit_id;
}

// Count Total Rows for Pagination
$count_query = "SELECT COUNT(*) FROM employees e $where";
$total_stmt = $conn->prepare($count_query);
foreach ($params as $key => $val) {
    if ($key !== ':start_date' && $key !== ':end_date') {
        $total_stmt->bindValue($key, $val);
    }
}
$total_stmt->execute();
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Query Rekapitulasi with Pagination
$query = "
    SELECT 
        e.id, 
        e.nik,
        e.full_name, 
        e.email,
        u.name as unit_name, 
        d.name as division_name, 
        (SELECT COUNT(id) FROM attendances WHERE user_id = e.id AND date BETWEEN :start_date AND :end_date) as total_attendance
    FROM employees e
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    $where
    ORDER BY e.full_name ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($query);
$stmt->bindValue(':start_date', $start_date);
$stmt->bindValue(':end_date', $end_date);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $val) {
    if ($key !== ':start_date' && $key !== ':end_date') {
        $stmt->bindValue($key, $val);
    }
}
$stmt->execute();
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>
<style>
    /* Custom Date Picker Style */
    input[type="date"]::-webkit-calendar-picker-indicator {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%230891b2' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z' /%3E%3C/svg%3E");
        cursor: pointer;
        padding: 5px;
        border-radius: 4px;
        transition: all 0.2s;
    }

    input[type="date"]::-webkit-calendar-picker-indicator:hover {
        background-color: rgba(8, 145, 178, 0.05);
        transform: scale(1.1);
    }

    /* Select Customization */
    select option {
        padding: 10px;
        background: white;
        color: #1e293b;
    }

    /* Custom Scrollbar for Select (if supported) */
    select::-webkit-scrollbar {
        width: 8px;
    }

    select::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    select::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    select::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .group-focus-within .group-label {
        color: #0891b2;
    }
</style>
<div class="pb-10">
    <div class="sm:flex sm:items-center mb-8">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Rekapitulasi Absensi Pegawai</h1>
            <p class="mt-2 text-sm text-slate-500">Ringkasan total kehadiran karyawan per periode tanggal yang
                fleksibel.</p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 flex gap-3">
            <!-- Export Dropdown -->
            <div class="relative" id="export-container">
                <button type="button" onclick="toggleDropdown('export')"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    Export
                    <svg id="export-arrow" class="ml-2 h-4 w-4 text-slate-400 transition-transform duration-200"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="export-menu"
                    class="hidden absolute right-0 mt-2 w-48 origin-top-right rounded-xl bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 overflow-hidden">
                    <div class="py-1 text-slate-700">
                        <a href="export_summary.php?<?php echo http_build_query($_GET); ?>" target="_blank"
                            class="group flex items-center px-4 py-2.5 text-sm hover:bg-slate-50 hover:text-cyan-700 transition-colors">
                            <span class="mr-3 text-lg">📊</span>
                            Excel (.xlsx)
                        </a>
                        <a href="export_summary_pdf.php?<?php echo http_build_query($_GET); ?>" target="_blank"
                            class="group flex items-center px-4 py-2.5 text-sm hover:bg-slate-50 hover:text-cyan-700 transition-colors border-t border-slate-50">
                            <span class="mr-3 text-lg">📄</span>
                            PDF Document
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section Card -->
    <div class="mb-8 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm transition-all hover:shadow-md">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-6 items-end">
            <!-- Hidden Page Param -->
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="limit" value="<?php echo $limit; ?>">

            <!-- Search -->
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2 ml-1 tracking-wider">Nama Pegawai</label>
                <div class="relative group">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nama..."
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 px-4 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 focus:bg-white transition-all text-slate-700 font-medium outline-none">
                </div>
            </div>

            <!-- Start Date -->
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2 ml-1 tracking-wider">Mulai
                    Tanggal</label>
                <div class="relative group">
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-4 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 focus:bg-white transition-all text-slate-700 font-medium">
                </div>
            </div>

            <!-- End Date -->
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2 ml-1 tracking-wider">Sampai
                    Tanggal</label>
                <div class="relative group">
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-4 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 focus:bg-white transition-all text-slate-700 font-medium">
                </div>
            </div>

            <!-- Division Filter -->
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2 ml-1 tracking-wider">Filter
                    Bidang</label>
                <div class="relative group">
                    <select name="division_id"
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-4 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 focus:bg-white transition-all appearance-none cursor-pointer text-slate-700 font-medium">
                        <option value="">Semua Bidang</option>
                        <?php foreach ($divisions as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php echo $division_id == $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                            </path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Unit Filter -->
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2 ml-1 tracking-wider">Filter
                    Unit</label>
                <div class="relative group">
                    <select name="unit_id"
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-4 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 focus:bg-white transition-all appearance-none cursor-pointer text-slate-700 font-medium">
                        <option value="">Semua Unit</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $unit_id == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                            </path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex gap-2">
                <button type="submit"
                    class="flex-1 inline-flex items-center justify-center rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 hover:bg-cyan-700 hover:shadow-cyan-600/40 focus:ring-4 focus:ring-cyan-600/20 transition-all active:scale-[0.98]">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentcolor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Filter
                </button>
                <?php if ($search || $division_id || $unit_id || $start_date != $default_start || $end_date != $default_end): ?>
                    <a href="?" class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-orange-50 text-orange-600 hover:bg-orange-100 transition-all active:scale-95 border border-orange-100 shrink-0" title="Bersihkan Filter">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                            <path d="M3 3v5h5"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Summary Table Container -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden text-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-separate border-spacing-0">
                <thead class="bg-slate-50">
                    <tr class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-4 w-20 border-b border-slate-200 text-center">No.</th>
                        <th class="px-6 py-4 min-w-[140px] border-b border-slate-200">NIK</th>
                        <th class="px-6 py-4 min-w-[300px] border-b border-slate-200">Nama Lengkap Pegawai</th>
                        <th class="px-6 py-4 min-w-[180px] border-b border-slate-200 text-center">Bidang</th>
                        <th class="px-6 py-4 min-w-[180px] border-b border-slate-200 text-center">Unit Kerja</th>
                        <th class="px-6 py-4 text-center w-48 border-b border-slate-200">Total Kehadiran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($summary) > 0): ?>
                        <?php foreach ($summary as $index => $row): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors group">
                                <td
                                    class="px-6 py-5 text-slate-400 font-semibold text-center group-hover:text-cyan-600 transition-colors">
                                    <?php echo $offset + $index + 1; ?>.
                                </td>
                                <td class="px-6 py-5 font-mono text-xs font-semibold text-slate-600">
                                    <?php echo htmlspecialchars($row['nik'] ?? '-'); ?>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center">
                                        <div
                                            class="h-10 w-10 rounded-full bg-gradient-to-tr from-cyan-500 to-blue-500 flex items-center justify-center text-white font-bold text-sm shadow-sm ring-2 ring-white">
                                            <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                        </div>
                                        <div class="ml-4">
                                            <p
                                                class="font-bold text-slate-800 text-sm tracking-tight leading-tight group-hover:text-cyan-700 transition-colors">
                                                <?php echo htmlspecialchars($row['full_name']); ?>
                                            </p>
                                            <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                                                <?php echo htmlspecialchars($row['email']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span
                                        class="inline-flex items-center rounded-lg bg-slate-50 border border-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-600 tracking-tight uppercase">
                                        <?php echo htmlspecialchars($row['division_name'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span
                                        class="inline-flex items-center rounded-lg bg-slate-50 border border-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-600 tracking-tight uppercase">
                                        <?php echo htmlspecialchars($row['unit_name'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <div class="flex flex-col items-center">
                                        <span
                                            class="inline-flex items-center rounded-xl bg-cyan-50 px-3 py-1.5 text-xs font-black text-cyan-700 ring-1 ring-inset ring-cyan-500/20 group-hover:bg-cyan-100 transition-colors">
                                            <?php echo $row['total_attendance']; ?> HARI
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-32 text-center border-none">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="h-16 w-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                                        <svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                            </path>
                                        </svg>
                                    </div>
                                    <p class="text-slate-400 font-medium tracking-tight">Tidak ada data kehadiran ditemukan
                                        untuk periode ini.</p>
                                    <p class="text-xs text-slate-300 mt-1">Gunakan filter di atas untuk melihat periode
                                        lain.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Dynamic Pagination Helper -->
        <?php
        function buildSummaryUrl($p, $l, $s, $e, $d, $u)
        {
            return "?page=$p&limit=$l&start_date=$s&end_date=$e" .
                ($d ? "&division_id=$d" : "") .
                ($u ? "&unit_id=$u" : "");
        }
        ?>
        <div
            class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-white px-6 py-4 gap-4">
            <div class="flex items-center gap-4">
                <select
                    onchange="window.location.href='?page=1&limit=' + this.value + '&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&division_id=<?php echo $division_id; ?>&unit_id=<?php echo $unit_id; ?>'"
                    class="block rounded-lg border-slate-200 bg-slate-50 py-1.5 pl-3 pr-8 text-xs font-bold text-slate-700 focus:ring-4 focus:ring-cyan-500/10 transition-all cursor-pointer">
                    <?php foreach ([10, 20, 50, 100] as $val): ?>
                        <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>
                            Tampil <?php echo $val; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 font-medium">
                    Menampilkan <span
                        class="text-slate-900 font-bold"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> -
                    <span class="text-slate-900 font-bold"><?php echo min($offset + $limit, $total_rows); ?></span> dari
                    <span class="text-slate-900 font-bold"><?php echo $total_rows; ?></span>
                </p>
            </div>

            <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm border border-slate-200 overflow-hidden"
                aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo buildSummaryUrl($page - 1, $limit, $start_date, $end_date, $division_id, $unit_id); ?>"
                        class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 transition-colors">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                                clip-rule="evenodd" />
                        </svg>
                    </a>
                <?php endif; ?>

                <?php
                $range = 1;
                for ($i = 1; $i <= $total_pages; $i++) {
                    if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
                        ?>
                        <a href="<?php echo buildSummaryUrl($i, $limit, $start_date, $end_date, $division_id, $unit_id); ?>"
                            class="relative inline-flex items-center px-4 py-2 text-sm font-bold <?php echo ($i == $page) ? 'bg-cyan-600 text-white' : 'text-slate-700 hover:bg-slate-50'; ?> border-x border-slate-100 transition-colors">
                            <?php echo $i; ?>
                        </a>
                        <?php
                    } elseif ($i == 2 || $i == $total_pages - 1) {
                        ?>
                        <span
                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400">...</span>
                        <?php
                    }
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo buildSummaryUrl($page + 1, $limit, $start_date, $end_date, $division_id, $unit_id); ?>"
                        class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 transition-colors">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                clip-rule="evenodd" />
                        </svg>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>