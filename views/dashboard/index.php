<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Dashboard";

// Fetch Stats
$db = new Database();
$conn = $db->getConnection();

$emp_count = $conn->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$dept_count = $conn->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$unit_count = $conn->query("SELECT COUNT(*) FROM units")->fetchColumn();
$student_count = $conn->query("SELECT COUNT(*) FROM students")->fetchColumn();

// --- Attendance Stats (Today) ---
$today = date('Y-m-d');
// Fix: 'Telat' was missing. Added case-insensitive checks effectively.
$present_count = $conn->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = '$today' AND status IN ('Present', 'Late', 'Hadir', 'hadir', 'Telat')")->fetchColumn();
$late_count = $conn->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = '$today' AND status IN ('Late', 'Telat')")->fetchColumn();
$absent_count = $emp_count - $present_count;

// Attendance Rates
$present_rate = $emp_count > 0 ? round(($present_count / $emp_count) * 100) : 0;
$absent_rate = $emp_count > 0 ? round(($absent_count / $emp_count) * 100) : 0;

// --- Chart Data (Last 7 Days) ---
$chart_data = [];
$days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $day_label = date('D', strtotime($d));

    $daily_count = $conn->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = '$d' AND status IN ('Present', 'Late', 'Hadir', 'hadir', 'Telat')")->fetchColumn();

    // Calculate percentage height relative to total employees (max 100%)
    $height_percent = $emp_count > 0 ? round(($daily_count / $emp_count) * 100) : 0;

    $chart_data[] = [
        'day' => $day_label,
        'date' => $d,
        'count' => $daily_count,
        'height' => $height_percent
    ];
}

// --- Recent Activity ---
// Using time_in based on api/attendance.php (instead of clock_in)
$activity_query = "
    SELECT a.*, e.full_name 
    FROM attendance a 
    JOIN employees e ON a.user_id = e.id 
    WHERE a.date = '$today' 
    ORDER BY a.time_in DESC 
    LIMIT 5
";
$recent_activities = $conn->query($activity_query)->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="space-y-6">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">

        <!-- Total Employees Card -->
        <div
            class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 hover:shadow-md transition-shadow p-5 relative">
            <div class="flex justify-between items-start">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-blue-50 text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd"
                            d="M7.5 5.25a3 3 0 013-3h3a3 3 0 013 3v.25a3 3 0 013 3v1.5a3 3 0 01-3 3v.25h-9v-.25a3 3 0 01-3-3v-1.5a3 3 0 013-3V5.25zM3.75 21a.75.75 0 01.75-.75h15a.75.75 0 010 1.5H4.5a.75.75 0 01-.75-.75zm4.266-4.5H15.98a3 3 0 001.996.75 2.25 2.25 0 002.247-2.072l.027-.333a3.751 3.751 0 00-3.753-4.045H7.501A3.751 3.751 0 003.75 14.8l.026.333A2.25 2.25 0 006.023 17.25a3 3 0 001.993-.75z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">
                    Total
                </span>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-slate-500 truncate">Total Employees</dt>
                <dd class="mt-1 flex items-baseline">
                    <div class="text-2xl font-bold text-slate-900"><?php echo number_format($emp_count); ?></div>
                    <span class="ml-2 text-sm font-medium text-green-600">
                        <svg class="self-center flex-shrink-0 h-4 w-4 text-green-500 inline-block" fill="currentColor"
                            viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="inline-block align-middle">2.5%</span>
                    </span>
                </dd>
            </div>
        </div>

        <!-- Present Today Card -->
        <div
            class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 hover:shadow-md transition-shadow p-5 relative">
            <div class="flex justify-between items-start">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-green-50 text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd"
                            d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                    Today
                </span>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-slate-500 truncate">Present Today</dt>
                <dd class="mt-1 flex items-baseline">
                    <div class="text-2xl font-bold text-slate-900"><?php echo number_format($present_count); ?></div>
                    <span class="ml-2 text-sm font-medium text-slate-400"><?php echo $present_rate; ?>% Rate</span>
                </dd>
            </div>
        </div>

        <!-- Late Arrivals Card -->
        <div
            class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 hover:shadow-md transition-shadow p-5 relative">
            <div class="flex justify-between items-start">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-yellow-50 text-yellow-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd"
                            d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700">
                    Late
                </span>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-slate-500 truncate">Late Arrivals</dt>
                <dd class="mt-1 flex items-baseline">
                    <div class="text-2xl font-bold text-slate-900"><?php echo number_format($late_count); ?></div>
                </dd>
            </div>
        </div>

        <!-- Absent Today Card -->
        <div
            class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 hover:shadow-md transition-shadow p-5 relative">
            <div class="flex justify-between items-start">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-red-50 text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path
                            d="M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375z" />
                        <path fill-rule="evenodd"
                            d="M3.087 9l.54 9.176A3 3 0 006.62 21h10.757a3 3 0 002.995-2.824L20.913 9H3.087zm6.163 3.75A.75.75 0 0110 12h4a.75.75 0 010 1.5h-4a.75.75 0 01-.75-.75z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">
                    Absent
                </span>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-slate-500 truncate">Absent Today</dt>
                <dd class="mt-1 flex items-baseline">
                    <div class="text-2xl font-bold text-slate-900"><?php echo number_format($absent_count); ?></div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Est.</span>
                </dd>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Attendance Trends (Placeholder) -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Attendance Trends</h3>
                    <p class="text-sm text-slate-500">Weekly overview compared to last week</p>
                </div>
                <div class="flex gap-2">
                    <select
                        class="bg-slate-50 border-none text-xs rounded-lg px-3 py-1.5 font-medium text-slate-600 focus:ring-0 cursor-pointer">
                        <option>This Week</option>
                        <option>Last Week</option>
                    </select>
                    <button class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-50 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path
                                d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.965 3.129V2.75z" />
                            <path
                                d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
                        </svg>
                    </button>
                </div>
            </div>
            <!-- Dynamic Chart -->
            <div class="h-64 flex items-end justify-between px-4 pb-2 gap-2 border-b border-slate-100">
                <?php foreach ($chart_data as $data): ?>
                    <div class="flex flex-col items-center w-full group relative h-full justify-end">
                        <!-- Tooltip -->
                        <div class="absolute bottom-full mb-2 hidden group-hover:block z-10">
                            <div class="bg-gray-800 text-white text-xs rounded py-1 px-2 whitespace-nowrap shadow-lg">
                                <?php echo $data['count']; ?> Present<br>
                                <span class="text-[10px] text-gray-400"><?php echo $data['date']; ?></span>
                            </div>
                        </div>

                        <!-- Bar -->
                        <div class="w-full max-w-[32px] bg-cyan-500 rounded-t-md hover:bg-cyan-600 transition-all duration-300 relative"
                            style="height: <?php echo max((float) $data['height'], 4); ?>%;">
                        </div>

                        <!-- Label -->
                        <div class="mt-3 text-xs text-slate-400 font-medium"><?php echo $data['day']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Labels were moved inside the loop -->
        </div>

        <!-- Real-time Activity -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-base font-bold text-slate-800">Real-time Activity</h3>
                <a href="#" class="text-xs font-semibold text-cyan-600 hover:text-cyan-700">View All</a>
            </div>

            <div class="space-y-6">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="flex items-center gap-4">
                            <img class="h-10 w-10 rounded-full border border-slate-100"
                                src="https://ui-avatars.com/api/?name=<?php echo urlencode($activity['full_name']); ?>&background=random"
                                alt="">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">
                                    <?php echo htmlspecialchars($activity['full_name']); ?>
                                </p>
                                <p class="text-xs text-slate-500"><?php echo htmlspecialchars($activity['status']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold text-slate-800">
                                    <?php echo date('H:i A', strtotime($activity['time_in'])); ?>
                                </p>
                                <?php
                                $statusColor = 'bg-green-50 text-green-600';
                                if ($activity['status'] == 'Late')
                                    $statusColor = 'bg-yellow-50 text-yellow-600';
                                if ($activity['status'] == 'Absent')
                                    $statusColor = 'bg-red-50 text-red-600';
                                ?>
                                <span
                                    class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold <?php echo $statusColor; ?>">
                                    <?php echo strtoupper($activity['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-slate-500 text-center py-4">No activity yet today.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Quick Actions -->
    <div
        class="bg-slate-50 rounded-xl border border-slate-100 p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <h3 class="text-base font-bold text-slate-800">Quick Actions</h3>
            <p class="text-sm text-slate-500">Manage daily tasks efficiently</p>
        </div>
        <div class="flex gap-3">
            <button
                class="inline-flex items-center px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-2">
                    <path
                        d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                </svg>
                Add Employee
            </button>
            <button
                class="inline-flex items-center px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-lg transition-colors shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                    class="w-4 h-4 mr-2 text-slate-400">
                    <path fill-rule="evenodd"
                        d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-4.12-4.122A1.5 1.5 0 0011.378 2H4.5zm2.25 8.5a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5zm0 3a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5z"
                        clip-rule="evenodd" />
                </svg>
                Generate Report
            </button>
            <button
                class="inline-flex items-center px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-lg transition-colors shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                    class="w-4 h-4 mr-2 text-slate-400">
                    <path fill-rule="evenodd"
                        d="M3.5 17a3.5 3.5 0 013.5-3.5h9c1.933 0 3.5 1.567 3.5 3.5 0 .58-.42 1-1 1H4a1 1 0 01-.5-1zM5 4.75A3.75 3.75 0 1112.5 4.75 3.75 3.75 0 015 4.75z"
                        clip-rule="evenodd" />
                </svg>
                System Config
            </button>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>