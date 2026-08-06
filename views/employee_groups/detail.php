<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . url('views/employee_groups/index.php', false));
    exit;
}

$page_title = "Detail Grup Karyawan";
include '../layouts/header.php';
?>

<div class="pb-10 max-w-7xl mx-auto" id="appContainer" data-id="<?php echo $id; ?>" data-mode="detail">
    <!-- Breadcrumbs -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 flex items-center gap-1">Beranda</a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <a href="<?php url('views/employee_groups/index.php'); ?>" class="ml-1 hover:text-slate-800">Pengelompokan Karyawan</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 text-slate-800 font-medium">Detail</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight" id="detailGroupName">
                Memuat...
            </h2>
            <div class="mt-1 flex flex-col sm:mt-0 sm:flex-row sm:flex-wrap sm:space-x-6">
                <div class="mt-2 flex items-center text-sm text-slate-500" id="detailGroupType">
                    <!-- Badge Type -->
                </div>
                <div class="mt-2 flex items-center text-sm text-slate-500" id="detailGroupStatus">
                    <!-- Badge Status -->
                </div>
            </div>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0 gap-3">
            <a href="<?php url('views/employee_groups/form.php?id=' . $id); ?>" class="inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                <i class="fa-solid fa-pen-to-square -ml-1 mr-2 h-4 w-4 text-slate-400"></i>
                Ubah Grup
            </a>
        </div>
    </div>

    <!-- Group Description -->
    <div class="bg-white shadow-sm ring-1 ring-slate-200 rounded-xl p-6 mb-6">
        <h3 class="text-sm font-semibold text-slate-900 border-b pb-2 mb-3">Deskripsi</h3>
        <p class="text-sm text-slate-600" id="detailGroupDescription">-</p>
    </div>

    <!-- Rules Section (Dynamic Only) -->
    <div id="detailRulesSection" class="bg-white shadow-sm ring-1 ring-slate-200 rounded-xl p-6 mb-6 hidden">
        <h3 class="text-sm font-semibold text-slate-900 border-b pb-2 mb-4">Rules (Dynamic Group)</h3>
        <ul id="detailRulesList" class="space-y-2 text-sm text-slate-600 list-disc pl-5">
            <!-- Rules list injected here -->
        </ul>
    </div>

    <!-- Members Section -->
    <div class="bg-white shadow-sm ring-1 ring-slate-200 rounded-xl overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-200 flex justify-between items-center">
            <div>
                <h3 class="text-base font-semibold leading-6 text-slate-900">Daftar Anggota</h3>
                <p class="mt-1 text-sm text-slate-500" id="membersCountDesc">Memuat anggota...</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-slate-900">Nama</th>
                        <th class="px-6 py-3 text-left font-semibold text-slate-900">NIK</th>
                        <th class="px-6 py-3 text-left font-semibold text-slate-900">Unit ID</th>
                        <th class="px-6 py-3 text-left font-semibold text-slate-900">Divisi ID</th>
                        <th class="px-6 py-3 text-left font-semibold text-slate-900">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white" id="detailMembersTable">
                    <!-- Members injected here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const APP_URL = "<?php echo BASE_URL; ?>";
</script>
<script src="<?php echo url('assets/js/employee_groups.js') . '?v=' . time(); ?>"></script>

<?php include '../layouts/footer.php'; ?>
