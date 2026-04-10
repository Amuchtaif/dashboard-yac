<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

$page_title = "Dashboard Tahfidz";

// Initialize Database
$db = new Database();
$conn = $db->getConnection();

// --- Stats Logic ---
$today = date('Y-m-d');

// Total Setoran Hari Ini
$todayItemsQuery = "SELECT COUNT(*) FROM tahfidz_memorization WHERE date = '$today'";
$todayCount = $conn->query($todayItemsQuery)->fetchColumn();

// Total Santri Setor Hari Ini (Distinct)
$todayStudentsQuery = "SELECT COUNT(DISTINCT student_id) FROM tahfidz_memorization WHERE date = '$today'";
$todayStudentCount = $conn->query($todayStudentsQuery)->fetchColumn();

// Fetch Recent Setoran Data (Today)
// Assuming students table has 'nama_siswa', 'kelas', 'tingkat'. Assuming 'employees' table for teacher if needed.
// Teacher ID is in tahfidz_memorization.teacher_id -> employees.id? Or separate users table?
// Usually teacher_id refers to user_id or employee_id. Let's assume employees.id and name is full_name based on dashboard/index.php.
// But in submit_memorization.php, teacher_id likely comes from user logic.
// We'll LEFT JOIN employees ON t.teacher_id = employees.id (or user_id).
// Let's assume employees table has 'id', 'full_name'.
// We also need student name. students table has 'nama_siswa' based on get_students.php fix earlier.

$query = "
    SELECT 
        tm.id, tm.student_id, tm.teacher_id, tm.date, tm.surah_start, tm.ayat_start, tm.surah_end, tm.ayat_end, tm.juz, tm.status, tm.notes, tm.created_at,
        s.nama_siswa AS student_name,
        s.kelas AS student_class,
        s.tingkat AS student_level,
        e.full_name AS teacher_name
    FROM tahfidz_memorization tm
    LEFT JOIN students s ON tm.student_id = s.id
    LEFT JOIN employees e ON tm.teacher_id = e.id
    WHERE tm.date = '$today'
    ORDER BY tm.id DESC
    LIMIT 50
";

$stm = $conn->prepare($query);
$stm->execute();
$activities = $stm->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Dashboard Tahfidz</h1>
            <p class="text-slate-500 mt-1">Monitoring real-time aktivitas setoran hafalan santri hari ini.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <span class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-50 text-blue-700 text-sm font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                </svg>
                <?php echo date('d F Y'); ?>
            </span>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Total Setoran Card -->
        <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow p-6 border border-slate-100 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Total Setoran Hari Ini</p>
                <div class="mt-2 flex items-baseline">
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo number_format($todayCount); ?></h3>
                    <span class="ml-2 text-sm text-green-600 font-medium">record</span>
                </div>
            </div>
        </div>

        <!-- Total Santri Card -->
        <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow p-6 border border-slate-100 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Santri Setor Hari Ini</p>
                <div class="mt-2 flex items-baseline">
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo number_format($todayStudentCount); ?></h3>
                    <span class="ml-2 text-sm text-cyan-600 font-medium">santri</span>
                </div>
            </div>
        </div>

        <!-- Placeholders for other stats if needed -->
        <!-- Just filling column 3 and 4 with placeholders to match 4-col layout request kind of but simpler -->
    </div>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-lg font-bold text-slate-800">Aktivitas Terbaru</h3>
            <div class="flex space-x-2">
                 <!-- Actions -->
                 <button class="text-slate-400 hover:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                 </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Waktu</th>
                        <th class="px-6 py-3">Santri</th>
                        <th class="px-6 py-3">Setoran / Hafalan</th>
                        <th class="px-6 py-3">Catatan</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Pengampu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($activities) > 0): ?>
                        <?php foreach ($activities as $row): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                <td class="px-6 py-4 text-slate-500 whitespace-nowrap">
                                    <?php 
                                        // If 'created_at' exists use it, else just date 
                                        // db_schema suggests created_at typically exists, assuming timestamp
                                        // If query has no time column, we might just show Date.
                                        // But this is "Today", so time is valuable. 
                                        // Usually tables have created_at. Let's check schema visually if possible, but safe fallback.
                                        echo isset($row['created_at']) ? date('H:i', strtotime($row['created_at'])) : '-'; 
                                    ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-cyan-100 flex items-center justify-center text-cyan-600 font-bold text-xs ring-2 ring-white">
                                            <?php echo substr($row['student_name'], 0, 1); ?>
                                        </div>
                                        <div class="ml-3">
                                            <p class="font-medium text-slate-800"><?php echo htmlspecialchars($row['student_name']); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($row['student_class'] ?? '-'); ?> • <?php echo htmlspecialchars($row['student_level'] ?? ''); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-slate-700 font-medium">
                                        <?php echo htmlspecialchars($row['surah_start']); ?> : <?php echo $row['ayat_start']; ?>
                                        <span class="mx-1 text-slate-300">s.d.</span>
                                        <?php echo htmlspecialchars($row['surah_end']); ?> : <?php echo $row['ayat_end']; ?>
                                    </div>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 text-slate-500 font-bold text-[10px] uppercase tracking-tight">
                                            Juz <?php echo htmlspecialchars($row['juz'] ?? '-'); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-500 max-w-[150px] truncate">
                                    <?php echo htmlspecialchars($row['notes'] ?: '-'); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                        $statusColor = 'bg-slate-100 text-slate-600';
                                        $s = strtolower($row['status']);
                                        if (strpos($s, 'lancar') !== false) $statusColor = 'bg-green-100 text-green-700';
                                        elseif (strpos($s, 'ulang') !== false) $statusColor = 'bg-red-100 text-red-700';
                                        elseif (strpos($s, 'kurang') !== false) $statusColor = 'bg-yellow-100 text-yellow-700';
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?php echo htmlspecialchars($row['teacher_name'] ?? '-'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    <p class="text-base font-medium">Belum ada data setoran hari ini</p>
                                    <p class="text-sm text-slate-400 mt-1">Data akan muncul setelah ada input dari aplikasi mobile</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
