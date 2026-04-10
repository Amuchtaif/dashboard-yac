<?php
$current_page = $_SERVER['REQUEST_URI'];
if (!function_exists('isUrlActive')) {
    function isUrlActive($path)
    {
        global $current_page;
        // Special case for boarding attendance subpages
        $is_boarding_attendance = (strpos($current_page, 'boarding/attendance') !== false && $path === 'boarding/attendance');
        if ($is_boarding_attendance) return true;

        // Ensure we match as a path segment to avoid substring issues
        // If path doesn't start with '/', add one for comparison unless it's a full URL
        $search_path = $path;
        if (strpos($path, '/') !== 0 && strpos($path, 'http') !== 0) {
            $search_path = '/' . $path;
        }

        return (strpos($current_page, $search_path) !== false);
    }
}

if (!function_exists('isActive')) {
    function isActive($path)
    {
        return isUrlActive($path)
            ? 'bg-cyan-50 text-cyan-700 font-semibold border-l-[6px] border-cyan-600 rounded-r-3xl'
            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 border-l-[6px] border-transparent rounded-r-3xl';
    }
}

if (!function_exists('getIconClass')) {
    function getIconClass($path)
    {
        return isUrlActive($path) ? 'text-cyan-600' : 'text-slate-400 group-hover:text-slate-600';
    }
}

// Fetch current user's permissions
require_once __DIR__ . '/../../config/permission.php';
$user_id = $_SESSION['user_id'] ?? 0;

$can_manage_employees = hasPermission($user_id, 'manage_employees');
$can_manage_academic = hasPermission($user_id, 'manage_academic');
$can_manage_tahfidz = hasPermission($user_id, 'manage_tahfidz');
$can_manage_news = hasPermission($user_id, 'manage_news');
$can_access_kabid = hasPermission($user_id, 'can_access_kabid');
$can_manage_boarding = hasPermission($user_id, 'manage_boarding');
$can_manage_inventory = hasPermission($user_id, 'manage_inventory');

// Check if user is Administrator (Position)
$is_admin = false;
if (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator') {
    $can_manage_employees = true;
    $can_manage_academic = true;
    $can_manage_tahfidz = true;
    $can_manage_news = true;
    $can_access_kabid = true;
    $can_manage_boarding = true;
    $can_manage_inventory = true;
    $is_admin = true;
}
?>
<!-- Sidebar Container -->
<aside id="main-sidebar"
    class="w-64 bg-white border-r border-slate-200 flex flex-col shadow-xl md:shadow-sm z-50 md:z-20 h-full fixed left-0 top-0 transition-transform duration-300 transform md:translate-x-0 -translate-x-full">

    <!-- Fixed Header inside Sidebar -->
    <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="h-8 w-8 flex items-center justify-center">
                <img src="<?php echo url('public/images/logo.png'); ?>" alt="Logo" class="w-8 h-8">
            </div>
            <div>
                <h2 class="text-[15px] font-bold text-slate-800 leading-none">Dashboard YAC</h2>
                <span class="text-[12px] text-slate-400 font-medium tracking-wide">Admin Portal</span>
            </div>
        </div>

        <!-- Mobile Close Button -->
        <button type="button" onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-slate-600 p-1">
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Scrollable Navigation (Removed pt-20, using flex-1 for auto height) -->
    <div
        class="flex-1 overflow-y-auto py-6 px-3 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:'none'] [scrollbar-width:'none']">
        <nav class="space-y-1">
            <a href="<?php url('views/dashboard/index.php'); ?>"
                class="<?php echo isActive('/views/dashboard/'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('/views/dashboard/'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                Beranda
            </a>

            <!-- Manage Employee Category -->
            <?php if ($can_manage_employees): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Manajemen Pegawai</p>
                </div>

                <!-- Employees -->
                <a href="<?php url('views/employees/index.php'); ?>"
                    class="<?php echo (strpos($current_page, 'employees') !== false && strpos($current_page, 'reset_password') === false) ? 'bg-cyan-50 text-cyan-700 font-semibold border-l-[6px] border-cyan-600 rounded-r-3xl' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 border-l-[6px] border-transparent rounded-r-3xl'; ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo (strpos($current_page, 'employees') !== false && strpos($current_page, 'reset_password') === false) ? 'text-cyan-600' : 'text-slate-400 group-hover:text-slate-600'; ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Data Pegawai
                </a>

                <!-- Functions/Departments (Renamed to Divisions to match UI) -->
                <a href="<?php url('views/departments/index.php'); ?>"
                    class="<?php echo isActive('departments'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('departments'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    Bidang Organisasi
                </a>

                <!-- Units -->
                <a href="<?php url('views/units/index.php'); ?>"
                    class="<?php echo isActive('/views/units/'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('/views/units/'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    Unit Organisasi
                </a>

                <!-- Positions -->
                <a href="<?php url('views/positions/index.php'); ?>"
                    class="<?php echo isActive('positions'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('positions'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
                    </svg>
                    Jabatan
                </a>


                <!-- Work Schedules -->
                <a href="<?php url('views/settings/schedules/index.php'); ?>"
                    class="<?php echo isActive('settings/schedules'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('settings/schedules'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Jadwal Kerja
                </a>

                <!-- Ramadan Settings -->
                <a href="<?php url('views/settings/ramadan.php'); ?>"
                    class="<?php echo isActive('settings/ramadan.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('settings/ramadan.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z"
                            clip-rule="evenodd" />
                    </svg>
                    Pengaturan Ramadan
                </a>

                <!-- Shift Exchange -->
                <a href="<?php url('views/settings/shifts/index.php'); ?>"
                    class="<?php echo isActive('settings/shifts'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('settings/shifts'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Tukar Shift
                </a>

                <!-- Attendance -->
                <a href="<?php url('views/attendance/index.php'); ?>"
                    class="<?php echo isActive('views/attendance/index.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('views/attendance/index.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Absensi Pegawai
                </a>

                <!-- Attendance Summary -->
                <a href="<?php url('views/attendance/summary.php'); ?>"
                    class="<?php echo isActive('attendance/summary.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('attendance/summary.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" 
                            d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                    </svg>
                    Rekap Absensi Pegawai
                </a>

                <!-- Kinerja Pegawai -->
                <a href="<?php url('views/performance/index.php'); ?>"
                    class="<?php echo isActive('performance'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('performance'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 01-2.77.704 6.023 6.023 0 01-2.77-.704" />
                    </svg>
                    Kinerja Pegawai
                </a>

                <!-- Permits -->
                <a href="<?php url('views/permits/index.php'); ?>"
                    class="<?php echo isActive('/views/permits/'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('/views/permits/'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 019 9v.375M10.125 2.25A3.375 3.375 0 0113.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 013.375 3.375M9 15l2.25 2.25L15 12" />
                    </svg>
                    Perizinan
                </a>

                <!-- Meetings -->
                <a href="<?php url('views/meetings/index.php'); ?>"
                    class="<?php echo isActive('meetings'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('meetings'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                    </svg>
                    Manajemen Rapat
                </a>

                <!-- Organization Chart -->
                <a href="<?php url('views/organization/chart.php'); ?>"
                    class="<?php echo isActive('organization'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('organization'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    Struktur Organisasi
                </a>

                <!-- Locations Management -->
                <a href="<?php url('views/settings/locations.php'); ?>"
                    class="<?php echo isActive('settings/locations.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('settings/locations.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                    Manajemen Lokasi
                </a>

                <!-- Task Assignments -->
                <a href="<?php url('views/task_assignments/index.php'); ?>"
                    class="<?php echo isActive('views/task_assignments/'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('views/task_assignments/'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 011.65 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75" />
                    </svg>
                    Manajemen Penugasan
                </a>
                <?php
            endif; ?>

            <!-- Application Management Category -->
            <?php if ($is_admin): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Manajemen Aplikasi</p>
                </div>

                <!-- Access Control (Permissions) -->
                <a href="<?php url('views/permissions/index.php'); ?>"
                    class="<?php echo isActive('permissions/index.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('permissions/index.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                    </svg>
                    Hak Akses Aplikasi
                </a>

                <!-- Web Access Control (Permissions) -->
                <a href="<?php url('views/permissions/web_permissions.php'); ?>"
                    class="<?php echo isActive('permissions/web_permissions.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('permissions/web_permissions.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9s2.015-9 4.5-9m0 0a9.004 9.004 0 018.716 6.747M12 3a9.004 9.004 0 00-8.716 6.747" />
                    </svg>
                    Hak Akses Web
                </a>

                <!-- Reset Password -->
                <a href="<?php url('views/employees/reset_password.php'); ?>"
                    class="<?php echo isActive('reset_password'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('reset_password'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                    </svg>
                    Reset Password
                </a>

                <!-- News Management -->
                <?php if ($can_manage_news): ?>
                    <a href="<?php url('views/news/index.php'); ?>"
                        class="<?php echo isActive('news'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                        <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('news'); ?> transition-colors"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z" />
                        </svg>
                        Manajemen Berita
                    </a>
                    <?php
                endif; ?>
                <?php
            endif; ?>

            <?php if ($can_manage_academic): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Manajemen Akademik</p>
                </div>

                <!-- Academic Years -->
                <a href="<?php url('views/academic_years/index.php'); ?>"
                    class="<?php echo isActive('academic_years'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('academic_years'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    Tahun Ajaran
                </a>

                <!-- Academic Calendar -->
                <a href="<?php url('views/calendar/index.php'); ?>"
                    class="<?php echo isActive('calendar'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('calendar'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                    </svg>
                    Kalender Akademik
                </a>

                <!-- Teachers -->
                <a href="<?php url('views/class_schedules/teachers.php'); ?>"
                    class="<?php echo isActive('class_schedules/teachers.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('class_schedules/teachers.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    Data Guru
                </a>

                <!-- Students -->
                <a href="<?php url('views/students/index.php'); ?>"
                    class="<?php echo (strpos($current_page, 'students/index.php') !== false) ? 'bg-cyan-50 text-cyan-700 font-semibold border-l-[6px] border-cyan-600 rounded-r-3xl' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 border-l-[6px] border-transparent rounded-r-3xl'; ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo (strpos($current_page, 'students/index.php') !== false) ? 'text-cyan-600' : 'text-slate-400 group-hover:text-slate-600'; ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 14l9-5-9-5-9 5 9 5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                    </svg>
                    Data Siswa
                </a>

                <!-- Data Siswa (Non-Aktif) -->
                <a href="<?php url('views/students/inactive.php'); ?>"
                    class="<?php echo (strpos($current_page, 'students/inactive.php') !== false) ? 'bg-cyan-50 text-cyan-700 font-semibold border-l-[6px] border-cyan-600 rounded-r-3xl' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 border-l-[6px] border-transparent rounded-r-3xl'; ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo (strpos($current_page, 'students/inactive.php') !== false) ? 'text-cyan-600' : 'text-slate-400 group-hover:text-slate-600'; ?> transition-colors"
                        xmlns="http://www.w3.org/2000/xl" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    Data Siswa (Non-Aktif)
                </a>

                <!-- Batch Promotion -->
                <a href="<?php url('views/students/promotion.php'); ?>"
                    class="<?php echo isActive('promotion.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('promotion.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    Kenaikan Kelas
                </a>

                <!-- Education Units -->
                <a href="<?php url('views/education_units/index.php'); ?>"
                    class="<?php echo isActive('education_units'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('education_units'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.499 5.294 50.536 50.536 0 00-2.658.813m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                    </svg>
                    Unit Pendidikan
                </a>

                <!-- Grade Levels -->
                <a href="<?php url('views/grade_levels/index.php'); ?>"
                    class="<?php echo isActive('grade_levels'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('grade_levels'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                    Data Kelas
                </a>

                <!-- Lesson Periods -->
                <a href="<?php url('views/lesson_periods/index.php'); ?>"
                    class="<?php echo isActive('lesson_periods'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('lesson_periods'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Jam Pelajaran
                </a>

                <!-- Subjects -->
                <a href="<?php url('views/subjects/index.php'); ?>"
                    class="<?php echo isActive('subjects'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('subjects'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" />
                    </svg>
                    Mata Pelajaran
                </a>

                <!-- Class Schedules -->
                <a href="<?php url('views/class_schedules/index.php'); ?>"
                    class="<?php echo (strpos($current_page, 'tahfidz/halaqah') !== false) ? 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 border-l-[6px] border-transparent rounded-r-3xl' : (isUrlActive('class_schedules') && strpos($current_page, 'teachers.php') === false ? 'bg-cyan-50 text-cyan-700 font-semibold border-l-[6px] border-cyan-600 rounded-r-3xl' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 border-l-[6px] border-transparent rounded-r-3xl'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo (strpos($current_page, 'tahfidz/halaqah') !== false) ? 'text-slate-400 group-hover:text-slate-600' : (isUrlActive('class_schedules') && strpos($current_page, 'teachers.php') === false ? 'text-cyan-600' : 'text-slate-400 group-hover:text-slate-600'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                    </svg>
                    Jadwal Pelajaran
                </a>

                <!-- Student Attendance -->
                <a href="<?php url('views/student_attendance/index.php'); ?>"
                    class="<?php echo isActive('views/student_attendance/'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('views/student_attendance/'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Absensi Siswa
                </a>

                <!-- Class Journals -->
                <a href="<?php url('views/class_journals/index.php'); ?>"
                    class="<?php echo isActive('class_journals'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('class_journals'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" />
                    </svg>
                    Jurnal Kelas
                </a>

                <!-- RPP -->
                <a href="<?php url('views/rpp/index.php'); ?>"
                    class="<?php echo isActive('rpp'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('rpp'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    Rencana Mengajar (RPP)
                </a>

                <!-- Assessment Types -->
                <a href="<?php url('views/assessment_types/index.php'); ?>"
                    class="<?php echo isActive('/views/assessment_types/'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('/views/assessment_types/'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 011.65 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75" />
                    </svg>
                    Jenis Penilaian
                </a>

                <!-- Student Assessments -->
                <a href="<?php url('views/student_assessments/index.php'); ?>"
                    class="<?php echo isActive('student_assessments'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('student_assessments'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/xl" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.423 48.423 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75" />
                    </svg>
                    Riwayat Penilaian
                </a>

                <!-- Class Placement -->
                <?php
            endif; ?>

            <!-- Tahfidz Management -->
            <?php if ($can_manage_tahfidz): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Manajemen Tahfidz</p>
                </div>

                <a href="<?php url('views/tahfidz/dashboard.php'); ?>"
                    class="<?php echo isActive('tahfidz/dashboard'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('tahfidz/dashboard'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" />
                    </svg>
                    Dashboard Tahfidz
                </a>

                <!-- Double Assignment -->
                <a href="<?php url('views/assignments/index.php'); ?>"
                    class="<?php echo isActive('/views/assignments/'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('/views/assignments/'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    Koordinator Tahfidz
                </a>

                <!-- Tahfidz Assessment Types -->
                <a href="<?php url('views/tahfidz/assessment_types.php'); ?>"
                    class="<?php echo isActive('/tahfidz/assessment_types.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('/tahfidz/assessment_types.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 011.65 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75" />
                    </svg>
                    Kelola Penilaian Tahfidz
                </a>

                <!-- Tahfidz Assessment History -->
                <a href="<?php url('views/tahfidz/assessments.php'); ?>"
                    class="<?php echo isActive('/tahfidz/assessments.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('/tahfidz/assessments.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/xl" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.423 48.423 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75" />
                    </svg>
                    Data Penilaian Tahfidz
                </a>

                <a href="<?php url('views/tahfidz/halaqah.php'); ?>"
                    class="<?php echo isActive('tahfidz/halaqah'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('tahfidz/halaqah'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                    Data Halaqah
                </a>

                <a href="<?php url('views/tahfidz/teacher_attendance.php'); ?>"
                    class="<?php echo isActive('tahfidz/teacher_attendance'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('tahfidz/teacher_attendance'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    Absensi Pengampu
                </a>

                <a href="<?php url('views/tahfidz/student_attendance.php'); ?>"
                    class="<?php echo isActive('tahfidz/student_attendance'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('tahfidz/student_attendance'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                    </svg>
                    Absensi Santri
                </a>



                <a href="<?php url('views/tahfidz/report.php'); ?>"
                    class="<?php echo isActive('tahfidz/report'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('tahfidz/report'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    Laporan Hafalan
                </a>
                <?php
            endif; ?>

            <!-- Boarding Management (Kepengasuhan) -->
            <?php if ($can_manage_boarding): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Kepengasuhan</p>
                </div>

                <!-- Data Asrama -->
                <a href="<?php url('views/boarding/rooms/index.php'); ?>"
                    class="<?php echo isActive('boarding/rooms'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('boarding/rooms'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-3h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                    </svg>
                    Data Asrama
                </a>

                <!-- Absensi Asrama -->
                <a href="<?php url('views/boarding/attendance/index.php'); ?>"
                    class="<?php echo isActive('boarding/attendance'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('boarding/attendance'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.423 48.423 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75" />
                    </svg>
                    Absensi Asrama
                </a>

                <!-- Kelola Pelanggaran -->
                <a href="<?php url('views/boarding/violations/index.php'); ?>"
                    class="<?php echo isActive('boarding/violations/index.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('boarding/violations/index.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    Kelola Pelanggaran
                </a>

                <!-- Jenis Pelanggaran -->
                <a href="<?php url('views/boarding/violation_types/index.php'); ?>"
                    class="<?php echo isActive('boarding/violation_types/index.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('boarding/violation_types/index.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-2.25 5.25h.008v.008H1.875V17.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                    Jenis Pelanggaran
                </a>

                <!-- Monitoring Perpulangan -->
                <a href="<?php url('views/boarding/perpulangan/index.php'); ?>"
                    class="<?php echo isActive('boarding/perpulangan/index.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('boarding/perpulangan/index.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                    </svg>
                    Monitoring Perpulangan
                </a>

                <!-- Kelola Liburan -->
                <a href="<?php url('views/boarding/holidays/index.php'); ?>"
                    class="<?php echo isActive('boarding/holidays'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('boarding/holidays'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                    </svg>
                    Kelola Liburan
                </a>

                <!-- Kelola Kepulangan Santri -->
                <a href="<?php url('views/boarding/returns/index.php'); ?>"
                    class="<?php echo isActive('boarding/returns/index.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('boarding/returns/index.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                    Kelola Kepulangan Santri
                </a>

                <!-- Kelola Izin Santri -->
                <a href="<?php url('views/boarding/permits/index.php'); ?>"
                    class="<?php echo isActive('boarding/permits/index.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('boarding/permits/index.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 011.65 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75" />
                    </svg>
                    Kelola Izin Santri
                </a>

                <!-- Monitoring Absensi Makan -->
                <a href="<?php url('views/meal_attendance/monitor.php'); ?>"
                    class="<?php echo isActive('meal_attendance/monitor.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('meal_attendance/monitor.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75l-1.5.75a3.354 3.354 0 01-3 0 3.354 3.354 0 00-3 0 3.354 3.354 0 01-3 0 3.354 3.354 0 00-3 0 3.354 3.354 0 01-3 0L3 16.5m15-3.375a33.75 33.75 0 00-12 0m12 0v1.5m0-1.5a3.354 3.354 0 013 0 3.354 3.354 0 003 0m-18 0a3.354 3.354 0 00-3 0 3.354 3.354 0 01-3 0m3 0v1.5m0-1.5a3.354 3.354 0 013 0 3.354 3.354 0 003 0m0 0a3.354 3.354 0 013 0 3.354 3.354 0 003 0z" />
                    </svg>
                    Monitoring Makan
                </a>
                <?php
            endif; ?>

            <!-- Manajemen Inventaris -->
            <?php if ($can_manage_inventory): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Manajemen Inventaris</p>
                </div>

                <!-- Struktur Lokasi -->
                <a href="<?php url('views/inventory/locations_tree.php'); ?>"
                    class="<?php echo isActive('inventory/locations_tree.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('inventory/locations_tree.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                    </svg>
                    Kelola Lokasi
                </a>

                <!-- Kelola Inventaris -->
                <a href="<?php url('views/inventory/items.php'); ?>"
                    class="<?php echo isActive('inventory/items.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                    <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('inventory/items.php'); ?> transition-colors"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    Kelola Inventaris
                </a>
            <?php endif; ?>

        </nav>
    </div>

    <!-- Small Profile Section (Removed absolute, using flex-shrink-0) -->
    <div class="p-4 border-t border-slate-100 bg-white flex-shrink-0">
        <div class="flex items-center gap-3">
            <?php
            $profile_name = $_SESSION['user_name'] ?? 'User';
            $profile_photo = $_SESSION['user_photo'] ?? '';
            $avatar_url = !empty($profile_photo)
                ? BASE_URL . '/public/uploads/employees/' . $profile_photo
                : "https://ui-avatars.com/api/?name=" . urlencode($profile_name) . "&background=random";
            ?>
            <img class="h-8 w-8 rounded-full border border-slate-200 object-cover" src="<?php echo $avatar_url; ?>"
                alt="User Profile">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-800 truncate">
                    <?php echo htmlspecialchars($profile_name); ?>
                </p>
                <p class="text-[11px] text-slate-500 truncate">
                    <?php echo htmlspecialchars($_SESSION['position_name'] ?? 'Pegawai'); ?>
                </p>
            </div>
            <a href="<?php url('logic/auth/logout.php'); ?>" class="text-slate-400 hover:text-red-500 transition-colors"
                title="Sign Out">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                </svg>
            </a>
        </div>
    </div>
</aside>

<!-- Auto-scroll sidebar to active menu item -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const activeSidebarItem = document.querySelector('aside nav a.bg-cyan-50');
        if (activeSidebarItem) {
            // Scroll slightly above center to ensure visibility
            activeSidebarItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>