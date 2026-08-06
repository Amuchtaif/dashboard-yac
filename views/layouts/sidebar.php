<?php
$current_page = $_SERVER['REQUEST_URI'];
if (!function_exists('isUrlActive')) {
    function isUrlActive($path)
    {
        global $current_page;

        // Bersihkan ekstensi .php dari URL saat ini dan path target agar perbandingan akurat
        $clean_page = str_replace('.php', '', $current_page);
        $clean_path = str_replace('.php', '', $path);

        // Special case for boarding attendance subpages
        $is_boarding_attendance = (strpos($clean_page, 'boarding/attendance') !== false && $clean_path === 'boarding/attendance');
        if ($is_boarding_attendance)
            return true;

        // Pastikan kita mencocokkan sebagai segmen path
        $search_path = $clean_path;
        if (strpos($clean_path, '/') !== 0 && strpos($clean_path, 'http') !== 0) {
            $search_path = '/' . $clean_path;
        }

        return (strpos($clean_page, $search_path) !== false);
    }
}

if (!function_exists('isActive')) {
    function isActive($path)
    {
        return isUrlActive($path)
            ? 'bg-white text-[#2B3990] font-semibold shadow-sm'
            : 'text-white/80 hover:bg-white/10 hover:text-white';
    }
}

if (!function_exists('getIconClass')) {
    function getIconClass($path)
    {
        return isUrlActive($path) ? 'text-[#2B3990]' : 'text-white/70 group-hover:text-white';
    }
}

if (!function_exists('renderIcon')) {
    function renderIcon($iconClass, $colorClass) {
        echo "<i class=\"fa-solid {$iconClass} mr-3 flex-shrink-0 w-5 text-center text-base {$colorClass} transition-colors\"></i>";
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
$can_manage_documents = hasPermission($user_id, 'manage_documents');

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
    $can_manage_documents = true;
    $is_admin = true;
}

// Check if user is Wali Kelas
$is_wali_kelas = false;
try {
    $db_sidebar = new Database();
    $conn_sidebar = $db_sidebar->getConnection();
    if ($conn_sidebar) {
        $stmt_wk = $conn_sidebar->prepare("SELECT id FROM grade_levels WHERE teacher_id = :uid LIMIT 1");
        $stmt_wk->execute([':uid' => $user_id]);
        if ($stmt_wk->fetchColumn()) {
            $is_wali_kelas = true;
        }
    }
} catch (Exception $e) {
    // Database connection down, fallback gracefully
    error_log("Sidebar database connection failed: " . $e->getMessage());
}
if ($is_admin) {
    $is_wali_kelas = true;
}
?>
<!-- Sidebar Container -->
<aside id="main-sidebar"
    class="w-64 bg-[#2B3990] text-white flex flex-col shadow-xl md:shadow-sm z-50 md:z-20 h-full fixed left-0 top-0 transition-transform duration-300 transform md:translate-x-0 -translate-x-full border-r border-white/10">

    <!-- Fixed Header inside Sidebar -->
    <div class="h-20 flex items-center justify-between px-6 flex-shrink-0">
        <div class="flex items-center gap-3.5">
            <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-white/10 p-1.5 shadow-inner backdrop-blur-md transition-all duration-300 hover:scale-105">
                <img src="<?php echo url('public/images/logo.png'); ?>" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div class="flex flex-col gap-0.5">
                <h2 class="text-[15px] font-extrabold text-white tracking-wide leading-tight">Dashboard YAC</h2>
                <span class="text-[10px] text-white/50 font-bold uppercase tracking-wider leading-none">Admin Portal</span>
            </div>
        </div>

        <!-- Mobile Close Button -->
        <button type="button" onclick="toggleSidebar()" class="md:hidden text-white/60 hover:text-white p-1.5 rounded-xl hover:bg-white/10 transition-all duration-200">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>

    <!-- Scrollable Navigation (Removed pt-20, using flex-1 for auto height) -->
    <div
        class="flex-1 overflow-y-auto py-6 px-2 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:'none'] [scrollbar-width:'none']">
        <nav class="space-y-1.5">
            <a href="<?php url('views/dashboard/index.php'); ?>"
                class="<?php echo isActive('/views/dashboard/'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                <?php renderIcon('fa-house', getIconClass('/views/dashboard/')); ?>
                Beranda
            </a>

            <!-- Manage Employee Category -->
            <?php if ($can_manage_employees): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Manajemen Pegawai</p>
                </div>

                <!-- Employees -->
                <a href="<?php url('views/employees/index.php'); ?>"
                    class="<?php echo (isUrlActive('employees') && !isUrlActive('reset_password')) ? 'bg-white text-[#2B3990] font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white'; ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-users', (isUrlActive('employees') && !isUrlActive('reset_password')) ? 'text-[#2B3990]' : 'text-white/70 group-hover:text-white'); ?>
                    Data Pegawai
                </a>

                <!-- Bidang Organisasi (Renamed to Bidang to match UI) -->
                <a href="<?php url('views/departments/index.php'); ?>"
                    class="<?php echo isActive('departments'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-sitemap', getIconClass('departments')); ?>
                    Bidang Organisasi
                </a>

                <!-- Units -->
                <a href="<?php url('views/units/index.php'); ?>"
                    class="<?php echo isActive('/views/units/'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-building', getIconClass('/views/units/')); ?>
                    Unit Organisasi
                </a>

                <!-- Positions -->
                <a href="<?php url('views/positions/index.php'); ?>"
                    class="<?php echo isActive('positions'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-briefcase', getIconClass('positions')); ?>
                    Jabatan
                </a>


                <!-- Work Schedules -->
                <a href="<?php url('views/settings/schedules/index.php'); ?>"
                    class="<?php echo isActive('settings/schedules'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-calendar-days', getIconClass('settings/schedules')); ?>
                    Jadwal Kerja
                </a>

                <!-- Ramadan Settings -->
                <a href="<?php url('views/settings/ramadan.php'); ?>"
                    class="<?php echo isActive('settings/ramadan.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-moon', getIconClass('settings/ramadan.php')); ?>
                    Pengaturan Ramadan
                </a>

                <!-- Shift Exchange -->
                <a href="<?php url('views/settings/shifts/index.php'); ?>"
                    class="<?php echo isActive('settings/shifts'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-right-left', getIconClass('settings/shifts')); ?>
                    Tukar Shift
                </a>

                <!-- Attendance -->
                <a href="<?php url('views/attendance/index.php'); ?>"
                    class="<?php echo isActive('views/attendance/index.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-clock', getIconClass('views/attendance/index.php')); ?>
                    Absensi Pegawai
                </a>

                <!-- Attendance Summary -->
                <a href="<?php url('views/attendance/summary.php'); ?>"
                    class="<?php echo isActive('attendance/summary.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-file-invoice', getIconClass('attendance/summary.php')); ?>
                    Rekap Absensi Pegawai
                </a>

                <!-- Daily Recap -->
                <a href="<?php url('views/attendance/daily_recap.php'); ?>"
                    class="<?php echo isActive('attendance/daily_recap.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-calendar-check', getIconClass('attendance/daily_recap.php')); ?>
                    Rekap Harian Pegawai
                </a>

                <!-- Kinerja Pegawai -->
                <a href="<?php url('views/performance/index.php'); ?>"
                    class="<?php echo isActive('performance'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-trophy', getIconClass('performance')); ?>
                    Kinerja Pegawai
                </a>

                <!-- Permits -->
                <a href="<?php url('views/permits/index.php'); ?>"
                    class="<?php echo isActive('/views/permits/'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-envelope-open-text', getIconClass('/views/permits/')); ?>
                    Perizinan
                </a>

                <!-- Meetings -->
                <a href="<?php url('views/meetings/index.php'); ?>"
                    class="<?php echo isActive('meetings'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-comments', getIconClass('meetings')); ?>
                    Manajemen Rapat
                </a>

                <!-- Organization Chart -->
                <a href="<?php url('views/organization/chart.php'); ?>"
                    class="<?php echo isActive('organization'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-network-wired', getIconClass('organization')); ?>
                    Struktur Organisasi
                </a>

                <!-- Locations Management -->
                <a href="<?php url('views/settings/locations.php'); ?>"
                    class="<?php echo isActive('settings/locations.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-location-dot', getIconClass('settings/locations.php')); ?>
                    Manajemen Lokasi
                </a>

                <!-- Employee Groups -->
                <a href="<?php url('views/employee_groups/index.php'); ?>"
                    class="<?php echo isActive('employee_groups'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-people-group', getIconClass('employee_groups')); ?>
                    Pengelompokan Karyawan
                </a>


                <!-- Task Assignments -->
                <a href="<?php url('views/task_assignments/index.php'); ?>"
                    class="<?php echo isActive('views/task_assignments/'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-list-check', getIconClass('views/task_assignments/')); ?>
                    Manajemen Penugasan
                </a>

                <!-- Work Reports -->
                <a href="<?php url('views/work_reports/index.php'); ?>"
                    class="<?php echo (isUrlActive('work_reports') && !isUrlActive('categories')) ? 'bg-white text-[#2B3990] font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white'; ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-file-signature', (isUrlActive('work_reports') && !isUrlActive('categories')) ? 'text-[#2B3990]' : 'text-white/70 group-hover:text-white'); ?>
                    Laporan Kerja
                </a>

                <!-- Work Report Categories -->
                <a href="<?php url('views/work_reports/categories.php'); ?>"
                    class="<?php echo isActive('work_reports/categories'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-folder-open', getIconClass('work_reports/categories')); ?>
                    Kategori Laporan
                </a>
                <?php
            endif; ?>

            <!-- Application Management Category -->
            <?php if ($is_admin): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Manajemen Aplikasi</p>
                </div>

                <!-- Access Control (Permissions) -->
                <a href="<?php url('views/permissions/index.php'); ?>"
                    class="<?php echo isActive('permissions/index.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-key', getIconClass('permissions/index.php')); ?>
                    Hak Akses Aplikasi
                </a>

                <!-- Web Access Control (Permissions) -->
                <a href="<?php url('views/permissions/web_permissions.php'); ?>"
                    class="<?php echo isActive('permissions/web_permissions.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-globe', getIconClass('permissions/web_permissions.php')); ?>
                    Hak Akses Web
                </a>

                <!-- Reset Password -->
                <a href="<?php url('views/employees/reset_password.php'); ?>"
                    class="<?php echo isActive('reset_password'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-lock-open', getIconClass('reset_password')); ?>
                    Reset Password
                </a>

                <!-- Status Maintenance -->
                <a href="<?php url('views/settings/maintenance.php'); ?>"
                    class="<?php echo isActive('settings/maintenance.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-gears', getIconClass('settings/maintenance.php')); ?>
                    Status Maintenance
                </a>

                <!-- News Management -->
                <?php if ($can_manage_news): ?>
                    <a href="<?php url('views/news/index.php'); ?>"
                        class="<?php echo isActive('news'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                        <?php renderIcon('fa-newspaper', getIconClass('news')); ?>
                        Manajemen Berita
                    </a>
                    <?php
                endif; ?>
                <?php
            endif; ?>

            <!-- System Logging Category -->
            <?php 
            $position = $_SESSION['position_name'] ?? '';
            $can_view_activity_logs = in_array($position, ['Administrator', 'Manager', 'Developer', 'Super Admin']);
            $can_view_system_logs = in_array($position, ['Administrator', 'Developer', 'Super Admin']);
            if ($can_view_activity_logs || $can_view_system_logs): 
            ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Sistem & Log</p>
                </div>

                <?php if ($can_view_activity_logs): ?>
                    <!-- Activity Log -->
                    <a href="<?php url('views/logs/activity.php'); ?>"
                        class="<?php echo isActive('views/logs/activity.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                        <?php renderIcon('fa-clock-rotate-left', getIconClass('views/logs/activity.php')); ?>
                        Log Aktivitas
                    </a>
                <?php endif; ?>

                <?php if ($can_view_system_logs): ?>
                    <!-- System Logs -->
                    <a href="<?php url('views/logs/system.php'); ?>"
                        class="<?php echo isActive('views/logs/system.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                        <?php renderIcon('fa-terminal', getIconClass('views/logs/system.php')); ?>
                        Log Sistem (File)
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($can_manage_academic): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Manajemen Akademik</p>
                </div>

                <!-- Academic Years -->
                <a href="<?php url('views/academic_years/index.php'); ?>"
                    class="<?php echo isActive('academic_years'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-graduation-cap', getIconClass('academic_years')); ?>
                    Tahun Ajaran
                </a>

                <!-- Academic Calendar -->
                <a href="<?php url('views/calendar/index.php'); ?>"
                    class="<?php echo isActive('calendar'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-calendar', getIconClass('calendar')); ?>
                    Kalender Akademik
                </a>

                <!-- Teachers -->
                <a href="<?php url('views/class_schedules/teachers.php'); ?>"
                    class="<?php echo isActive('class_schedules/teachers.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-chalkboard-user', getIconClass('class_schedules/teachers.php')); ?>
                    Data Guru
                </a>

                <!-- Students -->
                <a href="<?php url('views/students/index.php'); ?>"
                    class="<?php echo isActive('students/index'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-user-graduate', getIconClass('students/index')); ?>
                    Data Siswa
                </a>

                <!-- Data Siswa (Non-Aktif) -->
                <a href="<?php url('views/students/inactive.php'); ?>"
                    class="<?php echo isActive('students/inactive'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-user-slash', getIconClass('students/inactive')); ?>
                    Data Siswa (Non-Aktif)
                </a>

                <!-- Batch Promotion -->
                <a href="<?php url('views/students/promotion.php'); ?>"
                    class="<?php echo isActive('promotion.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-arrow-up-right-dots', getIconClass('promotion.php')); ?>
                    Kenaikan Kelas
                </a>

                <!-- Education Units -->
                <a href="<?php url('views/education_units/index.php'); ?>"
                    class="<?php echo isActive('education_units'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-school', getIconClass('education_units')); ?>
                    Unit Pendidikan
                </a>

                <!-- Grade Levels -->
                <a href="<?php url('views/grade_levels/index.php'); ?>"
                    class="<?php echo isActive('grade_levels'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-chalkboard', getIconClass('grade_levels')); ?>
                    Data Kelas
                </a>

                <!-- Lesson Periods -->
                <a href="<?php url('views/lesson_periods/index.php'); ?>"
                    class="<?php echo isActive('lesson_periods'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-clock', getIconClass('lesson_periods')); ?>
                    Jam Pelajaran
                </a>

                <!-- Subjects -->
                <a href="<?php url('views/subjects/index.php'); ?>"
                    class="<?php echo isActive('subjects'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-book', getIconClass('subjects')); ?>
                    Mata Pelajaran
                </a>

                <!-- Class Schedules -->
                <a href="<?php url('views/class_schedules/index.php'); ?>"
                    class="<?php echo (isUrlActive('tahfidz/halaqah')) ? 'text-white/80 hover:bg-white/10 hover:text-white' : (isUrlActive('class_schedules') && !isUrlActive('teachers') ? 'bg-white text-[#2B3990] font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-calendar-day', (isUrlActive('tahfidz/halaqah')) ? 'text-white/70 group-hover:text-white' : (isUrlActive('class_schedules') && !isUrlActive('teachers') ? 'text-[#2B3990]' : 'text-white/70 group-hover:text-white')); ?>
                    Jadwal Pelajaran
                </a>

                <!-- Class Attendance -->
                <a href="<?php url('views/class_attendance/index.php'); ?>"
                    class="<?php echo isActive('views/class_attendance/'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-check-double', getIconClass('views/class_attendance/')); ?>
                    Absensi Kelas
                </a>

                <!-- Student Attendance -->
                <a href="<?php url('views/student_attendance/index.php'); ?>"
                    class="<?php echo isActive('views/student_attendance/index.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-clipboard-user', getIconClass('views/student_attendance/index.php')); ?>
                    Absensi Siswa
                </a>

                <!-- Class Journals -->
                <a href="<?php url('views/class_journals/index.php'); ?>"
                    class="<?php echo isActive('class_journals'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-book-open-reader', getIconClass('class_journals')); ?>
                    Jurnal Kelas
                </a>

                <!-- RPP -->
                <a href="<?php url('views/rpp/index.php'); ?>"
                    class="<?php echo isActive('rpp'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-file-lines', getIconClass('rpp')); ?>
                    Rencana Mengajar (RPP)
                </a>

                <!-- Assessment Types -->
                <a href="<?php url('views/assessment_types/index.php'); ?>"
                    class="<?php echo isActive('/views/assessment_types/'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-sliders', getIconClass('/views/assessment_types/')); ?>
                    Jenis Penilaian
                </a>

                <!-- Student Assessments -->
                <a href="<?php url('views/student_assessments/index.php'); ?>"
                    class="<?php echo isActive('student_assessments'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-ranking-star', getIconClass('student_assessments')); ?>
                    Riwayat Penilaian
                </a>

                <!-- Class Placement -->
                <?php
            endif; ?>

            <!-- Wali Kelas Menu Category -->
            <?php if ($is_wali_kelas): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Menu Wali Kelas</p>
                </div>

                <!-- Daily Student Attendance (Homeroom) -->
                <a href="<?php url('views/homeroom/attendance.php'); ?>"
                    class="<?php echo isActive('homeroom/attendance'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-clipboard-user', getIconClass('homeroom/attendance')); ?>
                    Absensi Harian Siswa
                </a>

                <!-- Absensi Per Mapel (Homeroom View) -->
                <a href="<?php url('views/homeroom/subject_attendance.php'); ?>"
                    class="<?php echo isActive('homeroom/subject_attendance'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-check-double', getIconClass('homeroom/subject_attendance')); ?>
                    Absensi Per Mapel
                </a>

                <!-- Attendance Recap (Homeroom View) -->
                <a href="<?php url('views/homeroom/recap.php'); ?>"
                    class="<?php echo isActive('homeroom/recap'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-file-invoice', getIconClass('homeroom/recap')); ?>
                    Rekap Absensi Siswa
                </a>

                <!-- Class Journals (Homeroom View) -->
                <a href="<?php url('views/homeroom/journals.php'); ?>"
                    class="<?php echo isActive('homeroom/journals'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-book-open-reader', getIconClass('homeroom/journals')); ?>
                    Jurnal Kelas
                </a>

                <!-- Journal Recap (Homeroom View) -->
                <a href="<?php url('views/homeroom/journal_recap.php'); ?>"
                    class="<?php echo isActive('homeroom/journal_recap'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-rectangle-list', getIconClass('homeroom/journal_recap')); ?>
                    Rekap Jurnal Kelas
                </a>

                <!-- Class Schedule (Homeroom View) -->
                <a href="<?php url('views/homeroom/schedule.php'); ?>"
                    class="<?php echo isActive('homeroom/schedule'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-calendar-day', getIconClass('homeroom/schedule')); ?>
                    Jadwal Pelajaran
                </a>

                <!-- Student Grades (Homeroom View) -->
                <a href="<?php url('views/homeroom/grades.php'); ?>"
                    class="<?php echo isActive('homeroom/grades'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-award', getIconClass('homeroom/grades')); ?>
                    Data Nilai Siswa
                </a>
            <?php endif; ?>

            <!-- Tahfidz Management -->
            <?php if ($can_manage_tahfidz): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Manajemen Tahfidz</p>
                </div>

                <a href="<?php url('views/tahfidz/dashboard.php'); ?>"
                    class="<?php echo isActive('tahfidz/dashboard'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-quran', getIconClass('tahfidz/dashboard')); ?>
                    Dashboard Tahfidz
                </a>

                <!-- Double Assignment -->
                <a href="<?php url('views/assignments/index.php'); ?>"
                    class="<?php echo isActive('/views/assignments/'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-user-gear', getIconClass('/views/assignments/')); ?>
                    Koordinator Tahfidz
                </a>

                <!-- Tahfidz Assessment Types -->
                <a href="<?php url('views/tahfidz/assessment_types.php'); ?>"
                    class="<?php echo isActive('/tahfidz/assessment_types.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-sliders', getIconClass('/tahfidz/assessment_types.php')); ?>
                    Kelola Penilaian Tahfidz
                </a>

                <a href="<?php url('views/tahfidz/target_hafalan.php'); ?>"
                    class="<?php echo isActive('tahfidz/target_hafalan'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-bullseye', getIconClass('tahfidz/target_hafalan')); ?>
                    Kelola Target Hafalan
                </a>

                <a href="<?php url('views/tahfidz/baselines.php'); ?>"
                    class="<?php echo isActive('tahfidz/baselines'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-flag', getIconClass('tahfidz/baselines')); ?>
                    Baseline Hafalan
                </a>

                <!-- Tahfidz Assessment History -->
                <a href="<?php url('views/tahfidz/assessments.php'); ?>"
                    class="<?php echo isActive('/tahfidz/assessments.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-ranking-star', getIconClass('/tahfidz/assessments.php')); ?>
                    Data Penilaian Tahfidz
                </a>

                <a href="<?php url('views/tahfidz/halaqah.php'); ?>"
                    class="<?php echo isActive('tahfidz/halaqah'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-people-roof', getIconClass('tahfidz/halaqah')); ?>
                    Data Halaqah
                </a>

                <a href="<?php url('views/tahfidz/teacher_attendance.php'); ?>"
                    class="<?php echo isActive('tahfidz/teacher_attendance'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-user-check', getIconClass('tahfidz/teacher_attendance')); ?>
                    Absensi Pengampu
                </a>

                <a href="<?php url('views/tahfidz/student_attendance.php'); ?>"
                    class="<?php echo isActive('tahfidz/student_attendance'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-users-rectangle', getIconClass('tahfidz/student_attendance')); ?>
                    Absensi Santri
                </a>



                <a href="<?php url('views/tahfidz/report.php'); ?>"
                    class="<?php echo isActive('tahfidz/report'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-chart-simple', getIconClass('tahfidz/report')); ?>
                    Laporan Hafalan
                </a>

                <a href="<?php url('views/tahfidz/semester_recap.php'); ?>"
                    class="<?php echo isActive('tahfidz/semester_recap'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-file-lines', getIconClass('tahfidz/semester_recap')); ?>
                    Rekap Semester
                </a>


                <?php
            endif; ?>

            <!-- Boarding Management (Kepengasuhan) -->
            <?php if ($can_manage_boarding): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Kepengasuhan</p>
                </div>

                <!-- Data Asrama -->
                <a href="<?php url('views/boarding/rooms/index.php'); ?>"
                    class="<?php echo isActive('boarding/rooms'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-bed', getIconClass('boarding/rooms')); ?>
                    Data Asrama
                </a>

                <!-- Absensi Asrama -->
                <a href="<?php url('views/boarding/attendance/index.php'); ?>"
                    class="<?php echo isActive('boarding/attendance'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-clipboard-user', getIconClass('boarding/attendance')); ?>
                    Absensi Asrama
                </a>

                <!-- Kelola Pelanggaran -->
                <a href="<?php url('views/boarding/violations/index.php'); ?>"
                    class="<?php echo isActive('boarding/violations/index.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-triangle-exclamation', getIconClass('boarding/violations/index.php')); ?>
                    Kelola Pelanggaran
                </a>

                <!-- Jenis Pelanggaran -->
                <a href="<?php url('views/boarding/violation_types/index.php'); ?>"
                    class="<?php echo isActive('boarding/violation_types/index.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-list-ul', getIconClass('boarding/violation_types/index.php')); ?>
                    Jenis Pelanggaran
                </a>

                <!-- Monitoring Perpulangan -->
                <a href="<?php url('views/boarding/perpulangan/index.php'); ?>"
                    class="<?php echo isActive('boarding/perpulangan/index.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-plane-departure', getIconClass('boarding/perpulangan/index.php')); ?>
                    Monitoring Perpulangan
                </a>

                <!-- Kelola Liburan -->
                <a href="<?php url('views/boarding/holidays/index.php'); ?>"
                    class="<?php echo isActive('boarding/holidays'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-umbrella-beach', getIconClass('boarding/holidays')); ?>
                    Kelola Liburan
                </a>

                <!-- Kelola Kepulangan Santri -->
                <a href="<?php url('views/boarding/returns/index.php'); ?>"
                    class="<?php echo isActive('boarding/returns/index.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-right-from-bracket', getIconClass('boarding/returns/index.php')); ?>
                    Kelola Kepulangan Santri
                </a>

                <!-- Kelola Izin Santri -->
                <a href="<?php url('views/boarding/permits/index.php'); ?>"
                    class="<?php echo isActive('boarding/permits/index.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-id-card', getIconClass('boarding/permits/index.php')); ?>
                    Kelola Izin Santri
                </a>

                <!-- Monitoring Absensi Makan -->
                <a href="<?php url('views/meal_attendance/monitor.php'); ?>"
                    class="<?php echo isActive('meal_attendance/monitor.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-utensils', getIconClass('meal_attendance/monitor.php')); ?>
                    Monitoring Makan
                </a>
                <?php
            endif; ?>

            <!-- Amaliyah Santri (Aktivitas Santri) -->
            <?php 
            $can_manage_amaliyah = hasPermission($user_id, 'manage_activities') || (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');
            if ($can_manage_amaliyah): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Amaliyah Santri</p>
                </div>

                <!-- Dashboard Statistik -->
                <a href="<?php url('views/amaliyah/dashboard.php'); ?>"
                    class="<?php echo isActive('views/amaliyah/dashboard'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-chart-line', getIconClass('views/amaliyah/dashboard')); ?>
                    Statistik Aktivitas
                </a>

                <!-- Master Jenis Aktivitas -->
                <a href="<?php url('views/amaliyah/activity_types.php'); ?>"
                    class="<?php echo isActive('views/amaliyah/activity_types'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-list-check', getIconClass('views/amaliyah/activity_types')); ?>
                    Jenis Aktivitas
                </a>

                <!-- Monitoring Aktivitas -->
                <a href="<?php url('views/amaliyah/monitoring.php'); ?>"
                    class="<?php echo isActive('views/amaliyah/monitoring'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-eye', getIconClass('views/amaliyah/monitoring')); ?>
                    Monitoring Aktivitas
                </a>
            <?php endif; ?>

            <!-- Manajemen Inventaris -->
            <?php if ($can_manage_inventory): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Manajemen Inventaris</p>
                </div>

                <!-- Struktur Lokasi -->
                <a href="<?php url('views/inventory/locations_tree.php'); ?>"
                    class="<?php echo isActive('inventory/locations_tree.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-location-dot', getIconClass('inventory/locations_tree.php')); ?>
                    Kelola Lokasi
                </a>

                <!-- Kelola Inventaris -->
                <a href="<?php url('views/inventory/items.php'); ?>"
                    class="<?php echo isActive('inventory/items.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-boxes-stacked', getIconClass('inventory/items.php')); ?>
                    Kelola Inventaris
                </a>

                <!-- Monitoring Lokasi -->
                <a href="<?php url('views/inventory/monitoring.php'); ?>"
                    class="<?php echo isActive('inventory/monitoring.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-desktop', getIconClass('inventory/monitoring.php')); ?>
                    Monitoring Inventaris
                </a>
            <?php endif; ?>

            <!-- Surat & Dokumen Digital -->
            <?php if ($can_manage_documents): ?>
                <div class="pt-4 pb-2">
                    <p class="px-3 text-xs font-semibold text-white/40 uppercase tracking-wider">Persuratan Digital</p>
                </div>

                <!-- Dashboard -->
                <a href="<?php url('views/documents/index.php'); ?>"
                    class="<?php echo isActive('documents/index.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-table-columns', getIconClass('documents/index.php')); ?>
                    Dashboard Dokumen
                </a>

                <!-- Surat Keluar -->
                <a href="<?php url('views/documents/outgoing.php'); ?>"
                    class="<?php echo isActive('documents/outgoing.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-paper-plane', getIconClass('documents/outgoing.php')); ?>
                    Surat Keluar
                </a>

                <!-- Surat Masuk -->
                <a href="<?php url('views/documents/incoming.php'); ?>"
                    class="<?php echo isActive('documents/incoming.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-file-import', getIconClass('documents/incoming.php')); ?>
                    Surat Masuk
                </a>

                <!-- Approval -->
                <a href="<?php url('views/documents/approval.php'); ?>"
                    class="<?php echo isActive('documents/approval.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-signature', getIconClass('documents/approval.php')); ?>
                    Approval Surat
                </a>

                <!-- Disposisi -->
                <a href="<?php url('views/documents/disposition.php'); ?>"
                    class="<?php echo isActive('documents/disposition.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-share-nodes', getIconClass('documents/disposition.php')); ?>
                    Disposisi Surat
                </a>

                <!-- Template Surat (Katalog) -->
                <a href="<?php url('views/documents/templates.php'); ?>"
                    class="<?php echo isActive('documents/templates.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-file-invoice', getIconClass('documents/templates.php')); ?>
                    Template Surat
                </a>

                <!-- Arsip Digital -->
                <a href="<?php url('views/documents/archive.php'); ?>"
                    class="<?php echo isActive('documents/archive.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-box-archive', getIconClass('documents/archive.php')); ?>
                    Arsip Digital
                </a>

                <!-- Verifikasi Dokumen -->
                <a href="<?php url('views/documents/verify.php'); ?>"
                    class="<?php echo isActive('documents/verify.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-stamp', getIconClass('documents/verify.php')); ?>
                    Verifikasi Dokumen
                </a>

                <!-- Pengaturan Penerima Surat (Admin Only) -->
                <?php if ($is_admin): ?>
                    <a href="<?php url('views/documents/routing_config.php'); ?>"
                        class="<?php echo isActive('documents/routing_config.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                        <?php renderIcon('fa-sliders', getIconClass('documents/routing_config.php')); ?>
                        Pengaturan Penerima
                    </a>
                <?php endif; ?>

                <!-- Laporan -->
                <a href="<?php url('views/documents/reports.php'); ?>"
                    class="<?php echo isActive('documents/reports.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-chart-simple', getIconClass('documents/reports.php')); ?>
                    Laporan Persuratan
                </a>

                <!-- Pengaturan Template -->
                <?php if (hasPermission($user_id, 'document.template.manage') || $is_admin): ?>
                    <a href="<?php url('views/documents/template_config.php'); ?>"
                        class="<?php echo isActive('documents/template_config.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                        <?php renderIcon('fa-gear', getIconClass('documents/template_config.php')); ?>
                        Pengaturan Template
                    </a>
                <?php endif; ?>

                <!-- Tanda Tangan Saya -->
                <a href="<?php url('views/documents/signature.php'); ?>"
                    class="<?php echo isActive('documents/signature.php'); ?> group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200">
                    <?php renderIcon('fa-file-signature', getIconClass('documents/signature.php')); ?>
                    Tanda Tangan Saya
                </a>
            <?php endif; ?>

        </nav>
    </div>

    <!-- Small Profile Section (Removed absolute, using flex-shrink-0) -->
    <div class="px-6 py-5 flex-shrink-0">
        <div class="flex items-center gap-4">
            <?php
            $profile_name = $_SESSION['user_name'] ?? 'User';
            $profile_photo = $_SESSION['user_photo'] ?? '';
            $avatar_url = (!empty($profile_photo) && file_exists(BASE_PATH . '/uploads/profile_photos/' . $profile_photo))
                ? BASE_URL . '/uploads/profile_photos/' . $profile_photo
                : "https://ui-avatars.com/api/?name=" . urlencode($profile_name) . "&background=random";
            ?>
            <img class="h-8 w-8 rounded-full border border-white/10 object-cover" src="<?php echo $avatar_url; ?>"
                alt="User Profile" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($profile_name); ?>&background=random';">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate">
                    <?php echo htmlspecialchars($profile_name); ?>
                </p>
                <p class="text-[11px] text-white/60 truncate">
                    <?php echo htmlspecialchars($_SESSION['position_name'] ?? 'Pegawai'); ?>
                </p>
            </div>
            <a href="<?php url('logic/auth/logout.php'); ?>" class="text-white/60 hover:text-red-400 transition-colors flex items-center justify-center w-5 h-5"
                title="Sign Out">
                <i class="fa-solid fa-right-from-bracket text-base"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Auto-scroll sidebar to active menu item -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const activeSidebarItem = document.querySelector('aside nav a.bg-white');
        if (activeSidebarItem) {
            // Scroll slightly above center to ensure visibility
            activeSidebarItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>