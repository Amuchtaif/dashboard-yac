<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Tahun Ajaran";

$db = new Database();
$conn = $db->getConnection();

// Fetch all Academic Years
$years = $conn->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

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
                    <span class="ml-1 font-medium text-slate-800">Tahun Ajaran</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-3xl font-bold leading-7 text-slate-900 sm:truncate sm:tracking-tight">Tahun Ajaran</h2>
            <p class="mt-2 text-sm text-slate-500">Kelola data tahun ajaran sekolah.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <button onclick="openModal('addModal')"
                class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-5 h-5 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Tahun Ajaran
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 border-b border-slate-100">
                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left w-16 sm:pl-6 text-center">No.</th>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left min-w-[200px]">Nama Tahun</th>
                    <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">Periode</th>
                    <th scope="col" class="px-3 py-3.5 text-left min-w-[120px] text-center">Status</th>
                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right w-32 border-none">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php if (empty($years)): ?>
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-sm text-slate-500 text-center italic">
                            Belum ada data tahun ajaran.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($years as $index => $year): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                <?php echo $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($year['name']); ?> -
                                <?php echo htmlspecialchars($year['semester']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <?php
                                $start = $year['start_date'] ? date('d M Y', strtotime($year['start_date'])) : '-';
                                $end = $year['end_date'] ? date('d M Y', strtotime($year['end_date'])) : '-';
                                echo "$start s/d $end";
                                ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <?php if ($year['is_active'] == 1): ?>
                                    <span
                                        class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Aktif</span>
                                <?php else: ?>
                                    <button
                                        onclick="confirmActivate('<?php url('logic/academic_years/set_active.php?id=' . $year['id']); ?>')"
                                        class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 hover:bg-blue-100 transition-colors">Aktifkan</button>
                                <?php endif; ?>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex justify-end gap-2">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($year)); ?>)"
                                        class="text-indigo-600 hover:text-indigo-900 p-1 hover:bg-indigo-50 rounded transition-colors"
                                        title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </button>
                                    <a href="javascript:void(0)"
                                        onclick="openDeleteModal('<?php url('logic/academic_years/delete.php?id=' . $year['id']); ?>')"
                                        class="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded transition-colors"
                                        title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog"
    aria-modal="true">
    <!-- Backdrop -->
    <div id="addModalBackdrop"
        class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Modal Panel -->
        <div id="addModalPanel"
            class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-md">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <h3 class="text-xl font-bold leading-6 text-slate-900 border-b border-slate-100 pb-4 mb-6"
                    id="modal-title">
                    Tambah Tahun Ajaran
                </h3>
                <form action="<?php url('logic/academic_years/store.php'); ?>" method="POST" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Nama <span
                                class="text-xs font-normal text-slate-400">(Contoh: 2024/2025)</span></label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                            </div>
                            <input type="text" name="name" required
                                class="block w-full rounded-lg border-slate-200 bg-slate-50 pl-10 pr-4 py-2.5 text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 sm:text-sm transition-all shadow-sm"
                                placeholder="2024/2025">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Semester</label>
                        <div class="relative" id="add-semester-container">
                            <input type="hidden" name="semester" id="add-semester-input" value="Ganjil">
                            <button type="button" onclick="toggleDropdown('add-semester')"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-sm text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 transition-all shadow-sm">
                                <span id="add-semester-text">Ganjil</span>
                                <svg class="h-5 w-5 text-slate-400 transition-transform duration-200"
                                    id="add-semester-arrow" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div id="add-semester-menu"
                                class="hidden absolute z-10 mt-1 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                                <ul class="py-1">
                                    <li onclick="selectOption('add-semester', 'Ganjil', 'Ganjil')"
                                        class="cursor-pointer select-none py-2.5 px-4 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        Ganjil
                                    </li>
                                    <li onclick="selectOption('add-semester', 'Genap', 'Genap')"
                                        class="cursor-pointer select-none py-2.5 px-4 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        Genap
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Mulai</label>
                            <input type="date" name="start_date" required
                                class="block w-full rounded-lg border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 sm:text-sm transition-all shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Selesai</label>
                            <input type="date" name="end_date" required
                                class="block w-full rounded-lg border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 sm:text-sm transition-all shadow-sm">
                        </div>
                    </div>
                    <div
                        class="mt-8 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" onclick="closeModal('addModal')"
                            class="inline-flex justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition-all">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex justify-center rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-all">
                            Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="modal-title" role="dialog"
    aria-modal="true">
    <!-- Backdrop -->
    <div id="editModalBackdrop"
        class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Modal Panel -->
        <div id="editModalPanel"
            class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-md">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <h3 class="text-xl font-bold leading-6 text-slate-900 border-b border-slate-100 pb-4 mb-6"
                    id="modal-title">
                    Edit Tahun Ajaran
                </h3>
                <form action="<?php url('logic/academic_years/update.php'); ?>" method="POST" class="space-y-5">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="status" id="edit_is_active">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Nama <span
                                class="text-xs font-normal text-slate-400">(Contoh: 2024/2025)</span></label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                            </div>
                            <input type="text" name="name" id="edit_name" required
                                class="block w-full rounded-lg border-slate-200 bg-slate-50 pl-10 pr-4 py-2.5 text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 sm:text-sm transition-all shadow-sm"
                                placeholder="2024/2025">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Semester</label>
                        <div class="relative" id="edit-semester-container">
                            <input type="hidden" name="semester" id="edit_semester" value="Ganjil">
                            <button type="button" onclick="toggleDropdown('edit-semester')"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-left text-sm text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 transition-all shadow-sm">
                                <span id="edit-semester-text">Ganjil</span>
                                <svg class="h-5 w-5 text-slate-400 transition-transform duration-200"
                                    id="edit-semester-arrow" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div id="edit-semester-menu"
                                class="hidden absolute z-10 mt-1 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                                <ul class="py-1">
                                    <li onclick="selectOption('edit-semester', 'Ganjil', 'Ganjil')"
                                        class="cursor-pointer select-none py-2.5 px-4 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        Ganjil
                                    </li>
                                    <li onclick="selectOption('edit-semester', 'Genap', 'Genap')"
                                        class="cursor-pointer select-none py-2.5 px-4 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        Genap
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Mulai</label>
                            <input type="date" name="start_date" id="edit_start_date" required
                                class="block w-full rounded-lg border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 sm:text-sm transition-all shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Selesai</label>
                            <input type="date" name="end_date" id="edit_end_date" required
                                class="block w-full rounded-lg border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 sm:text-sm transition-all shadow-sm">
                        </div>
                    </div>
                    <div
                        class="mt-8 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" onclick="closeModal('editModal')"
                            class="inline-flex justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition-all">
                            Batal
                        </button>
                        <button type="submit"
                            class="inline-flex justify-center rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-all">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Activate Confirmation Modal -->
<div id="activateModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog"
    aria-modal="true">
    <!-- Backdrop -->
    <div id="activateModalBackdrop"
        class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Modal Panel -->
        <div id="activateModalPanel"
            class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div
                        class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-bold leading-6 text-slate-900" id="modal-title">Aktifkan Semester</h3>
                        <div class="mt-2 text-sm text-slate-500">
                            Anda yakin ingin mengaktifkan Semester ini? Semester yang sedang aktif akan otomatis
                            dinonaktifkan.
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                <a id="confirmActivateBtn" href="#"
                    class="inline-flex w-full justify-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 sm:w-auto transition-all transform active:scale-95">
                    Aktifkan
                </a>
                <button type="button" onclick="closeModal('activateModal')"
                    class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        const modal = document.getElementById(id);
        const backdrop = document.getElementById(id + 'Backdrop');
        const panel = document.getElementById(id + 'Panel');

        modal.classList.remove('hidden');

        // Small delay to trigger transitions
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        const backdrop = document.getElementById(id + 'Backdrop');
        const panel = document.getElementById(id + 'Panel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');

        // Delay hiding the modal until animations are done
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_name').value = data.name;
        document.getElementById('edit_semester').value = data.semester;
        document.getElementById('edit-semester-text').innerText = data.semester;
        document.getElementById('edit_start_date').value = data.start_date;
        document.getElementById('edit_end_date').value = data.end_date;
        document.getElementById('edit_is_active').value = data.is_active;
        openModal('editModal');
    }

    function confirmActivate(url) {
        document.getElementById('confirmActivateBtn').href = url;
        openModal('activateModal');
    }

</script>

<?php include '../layouts/footer.php'; ?>