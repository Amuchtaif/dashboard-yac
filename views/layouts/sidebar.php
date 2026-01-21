<?php
$current_page = $_SERVER['REQUEST_URI'];
function isActive($path)
{
    global $current_page;
    // Fix: Check for specific view folder to avoid matching project name "dashboard-yac"
    return strpos($current_page, $path) !== false
        ? 'bg-cyan-50 text-cyan-700 font-semibold border-l-[6px] border-cyan-600 rounded-r-3xl'
        : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 border-l-[6px] border-transparent rounded-r-3xl';
}

function getIconClass($path)
{
    global $current_page;
    return strpos($current_page, $path) !== false ? 'text-cyan-600' : 'text-slate-400 group-hover:text-slate-600';
}
?>
<!-- Sidebar Container -->
<aside class="w-64 bg-white border-r border-slate-200 hidden md:flex flex-col shadow-sm z-20 h-full fixed left-0 top-0">

    <!-- Fixed Header inside Sidebar -->
    <div class="h-16 flex items-center px-6 border-b border-slate-100 flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="h-8 w-8 flex items-center justify-center">
                <img src="<?php echo url('public/images/logo.png'); ?>" alt="Logo" class="w-8 h-8">
            </div>
            <div>
                <h2 class="text-[15px] font-bold text-slate-800 leading-none">Dashboard YAC</h2>
                <span class="text-[12px] text-slate-400 font-medium tracking-wide">Admin Portal</span>
            </div>
        </div>
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
            <div class="pt-4 pb-2">
                <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Manajemen Pegawai</p>
            </div>

            <!-- Employees -->
            <a href="<?php url('views/employees/index.php'); ?>"
                class="<?php echo isActive('employees'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('employees'); ?> transition-colors"
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
                Divisi
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
                class="<?php echo isActive('schedules'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('schedules'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Jadwal Kerja
            </a>

            <!-- Attendance -->
            <a href="<?php url('views/attendance/index.php'); ?>"
                class="<?php echo isActive('attendance'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('attendance'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Absensi
            </a>

            <!-- Permits -->
            <a href="<?php url('views/permits/index.php'); ?>"
                class="<?php echo isActive('permits'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('permits'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 019 9v.375M10.125 2.25A3.375 3.375 0 0113.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 013.375 3.375M9 15l2.25 2.25L15 12" />
                </svg>
                Perizinan
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

            <!-- Office Settings -->
            <a href="<?php url('views/settings/office.php'); ?>"
                class="<?php echo isActive('office.php'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('office.php'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                </svg>
                Lokasi Kantor
            </a>

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

            <!-- Students -->
            <a href="<?php url('views/students/index.php'); ?>"
                class="<?php echo isActive('students'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('students'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 14l9-5-9-5-9 5 9 5z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                </svg>
                Data Siswa
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

            <!-- Class Placement -->
            <a href="<?php url('views/placements/index.php'); ?>"
                class="<?php echo isActive('placements'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('placements'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Penempatan Kelas
            </a>
        </nav>
    </div>

    <!-- Small Profile Section (Removed absolute, using flex-shrink-0) -->
    <div class="p-4 border-t border-slate-100 bg-white flex-shrink-0">
        <div class="flex items-center gap-3">
            <img class="h-9 w-9 rounded-full border border-slate-200"
                src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'Alex Morgan'); ?>&background=0F172A&color=fff"
                alt="">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-800 truncate">
                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Alex Morgan'); ?>
                </p>
                <p class="text-xs text-cyan-600 truncate">Super Admin</p>
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