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
            SELECT COUNT(DISTINCT sch.student_id) 
            FROM student_class_history sch
            JOIN grade_levels gl ON sch.class_id = gl.id
            WHERE gl.education_unit_id = u.id
            AND sch.academic_year_id = (SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1)
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
                    <i class="fa-solid fa-house w-3 h-3"></i>
                    Dashboard
                </a>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
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
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
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
                            <i class="fa-solid fa-graduation-cap w-6 h-6"></i>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <button
                            onclick="openDeleteModal('<?php url('logic/education_units/delete.php?id=' . $unit['id']); ?>')"
                            class="text-slate-400 hover:text-red-500 transition-colors" type="button">
                            <i class="fa-solid fa-trash w-5 h-5"></i>
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
                        <i class="fa-solid fa-users h-4 w-4 text-slate-400"></i>
                        <span class="font-medium">
                            <?php echo $unit['student_count']; ?>
                        </span> Siswa
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <i class="fa-solid fa-user h-4 w-4 text-slate-400"></i>
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