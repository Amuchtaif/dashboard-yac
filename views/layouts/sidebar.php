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
    <div class="h-16 flex items-center px-6 border-b border-slate-100 absolute top-0 left-0 w-full z-30">
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

    <!-- Scrollable Navigation (Added pt-16 to account for fixed header) -->
    <div class="flex-1 overflow-y-auto py-6 px-3 pt-20"> <!-- pt-20 = 5rem (header 4rem + 1rem padding) -->
        <nav class="space-y-1">

            <!-- Dashboard (Specific Path Check) -->
            <a href="<?php url('views/dashboard/index.php'); ?>"
                class="<?php echo isActive('/views/dashboard/'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('/views/dashboard/'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                Dashboard
            </a>

            <div class="pt-4 pb-2">
                <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Management</p>
            </div>

            <!-- Employees -->
            <a href="<?php url('views/employees/index.php'); ?>"
                class="<?php echo isActive('employees'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('employees'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Employees
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
                Students
            </a>

            <!-- Departments -->
            <a href="<?php url('views/departments/index.php'); ?>"
                class="<?php echo isActive('departments'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('departments'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                Departments
            </a>

            <!-- Units -->
            <a href="<?php url('views/units/index.php'); ?>"
                class="<?php echo isActive('units'); ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
                <svg class="mr-3 flex-shrink-0 h-5 w-5 <?php echo getIconClass('units'); ?> transition-colors"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Units
            </a>


            <div class="pt-4 pb-2">
                <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Configuration</p>
            </div>

            <!-- Settings -->
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
                Office Location
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
                Work Schedules
            </a>
        </nav>
    </div>

    <!-- Small Profile Section at bottom -->
    <div class="p-4 border-t border-slate-100 absolute bottom-0 left-0 w-full bg-white z-20">
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