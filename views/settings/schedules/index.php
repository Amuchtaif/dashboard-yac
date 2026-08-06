<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Jadwal Kerja";

$db = new Database();
$conn = $db->getConnection();

// Fetch All Schedules
$schedules = $conn->query("SELECT * FROM work_schedules ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../../layouts/header.php';
?>

<div class="pb-10">

    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="hover:text-slate-800 flex items-center gap-1">
                    <i class="fa-solid fa-house w-3 h-3"></i>
                    Beranda
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <a href="<?php url('views/settings/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-800">Pengaturan</a>
                </div>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 font-medium text-slate-800">Jadwal</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight">Jadwal
                Kerja</h2>
            <p class="mt-1 text-sm text-slate-500">Kelola jam kerja dan shift pegawai.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <a href="<?php url('views/settings/schedules/form.php'); ?>"
                class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Buat Jadwal Baru
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white shadow-sm ring-1 ring-gray-200 sm:rounded-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">No
                    </th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Nama Jadwal</th>
                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                    <th scope="col"
                        class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right text-sm font-semibold text-gray-900">Aksi
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php if (count($schedules) > 0): ?>
                    <?php $no = 1;
                    foreach ($schedules as $schedule):
                        // Count active days (Optional query optimization would be better but this is fine for small lists)
                        $active_days = $conn->query("SELECT COUNT(*) FROM work_schedule_details WHERE schedule_id = " . $schedule['id'] . " AND is_day_off = 0")->fetchColumn();
                        ?>
                        <tr>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                <?php echo $no++; ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 font-bold">
                                <?php echo htmlspecialchars($schedule['name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <span
                                    class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                    <?php echo $active_days ?: 0; ?> Hari Kerja
                                </span>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex items-center justify-end gap-3 text-gray-400">
                                    <a href="<?php url('views/settings/schedules/form.php?id=' . $schedule['id']); ?>"
                                        class="hover:text-cyan-600 transition-colors" title="Ubah">
                                        <i class="fa-solid fa-pen-to-square w-5 h-5"></i>
                                    </a>
                                    <button
                                        onclick="openDeleteModal('<?php url('views/settings/schedules/delete.php?id=' . $schedule['id']); ?>')"
                                        class="hover:text-red-600 transition-colors" title="Hapus">
                                        <i class="fa-solid fa-trash w-5 h-5"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="py-10 text-center text-sm text-gray-500">Tidak ada jadwal ditemukan. Buat
                            satu untuk memulai.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../layouts/footer.php'; ?>