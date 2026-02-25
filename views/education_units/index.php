<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Education Units";

$db = new Database();
$conn = $db->getConnection();

// Fetch Units with Dynamic Counts
$query = "
    SELECT 
        u.*,
        (
            SELECT COUNT(*) 
            FROM students s 
            WHERE FIND_IN_SET(s.tingkat, u.grade_levels) > 0
        ) as student_count,
        (
            SELECT COUNT(*) 
            FROM employees e 
            WHERE e.unit_id = u.operational_unit_id
        ) as teacher_count
    FROM education_units u 
    ORDER BY u.name ASC
";
$stmt = $conn->prepare($query);
$stmt->execute();
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">

    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="hover:text-slate-800 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                        <path fill-rule="evenodd"
                            d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z"
                            clip-rule="evenodd" />
                    </svg>
                    Dashboard
                </a>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 text-slate-500 hover:text-slate-800">Education Units</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-3xl font-bold leading-7 text-slate-900 sm:truncate sm:tracking-tight">Unit Pendidikan</h2>
            <p class="mt-2 text-sm text-slate-500 max-w-2xl">Kelola jenjang pendidikan dan satuan unit di bawah naungan
                yayasan/lembaga.</p>
        </div>
        <div class="mt-4 flex gap-3 md:ml-4 md:mt-0">
            <a href="<?php url('views/education_units/create.php'); ?>"
                class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                <svg class="-ml-1 mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                </svg>
                Tambah Unit Baru
            </a>
        </div>
    </div>

    <!-- Grid Layout -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($units as $unit): ?>
            <div
                class="relative flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white p-6 shadow-sm hover:shadow-md transition-shadow">

                <!-- Simple Header/Icon -->
                <div class="flex items-start justify-between">
                    <div
                        class="h-12 w-12 flex-shrink-0 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600">
                        <?php if ($unit['icon'] && file_exists('../../uploads/education_units/' . $unit['icon'])): ?>
                            <img src="<?php echo url('uploads/education_units/' . $unit['icon']); ?>" alt=""
                                class="h-8 w-8 object-contain">
                        <?php else: ?>
                            <!-- Fallback Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.499 5.294 50.536 50.536 0 00-2.658.813m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                            </svg>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <button
                            onclick="openDeleteModal('<?php url('logic/education_units/delete.php?id=' . $unit['id']); ?>')"
                            class="text-slate-400 hover:text-red-500 transition-colors" type="button">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="mt-4">
                    <h3 class="text-lg font-bold text-slate-900">
                        <?php echo htmlspecialchars($unit['name']); ?>
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 line-clamp-2 min-h-[40px]">
                        <?php echo htmlspecialchars($unit['description']); ?>
                    </p>
                </div>

                <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        <span class="font-medium">
                            <?php echo $unit['student_count']; ?>
                        </span> Siswa
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        <span class="font-medium">
                            <?php echo $unit['teacher_count']; ?>
                        </span> Guru
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>