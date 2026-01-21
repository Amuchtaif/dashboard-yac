<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Tahun Ajaran";

$db = new Database();
$conn = $db->getConnection();

// Fetch all Academic Years
$years = $conn->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 pb-10">
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

    <!-- Alert Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="mb-6 rounded-md bg-green-50 p-4 border border-green-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="mb-6 rounded-md bg-red-50 p-4 border border-red-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col"
                        class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-16">
                        No.</th>
                    <th scope="col"
                        class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Nama Tahun</th>
                    <th scope="col"
                        class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Periode</th>
                    <th scope="col"
                        class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status
                    </th>
                    <th scope="col"
                        class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right font-semibold text-xs uppercase tracking-wide text-gray-500">
                        Aksi</th>
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
                                <?php echo htmlspecialchars($year['name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <?php
                                $start = $year['start_date'] ? date('d M Y', strtotime($year['start_date'])) : '-';
                                $end = $year['end_date'] ? date('d M Y', strtotime($year['end_date'])) : '-';
                                echo "$start s/d $end";
                                ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <?php if ($year['status'] == 'Active'): ?>
                                    <span
                                        class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Aktif</span>
                                <?php else: ?>
                                    <span
                                        class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">Tidak
                                        Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex justify-end gap-2">
                                    <?php if ($year['status'] != 'Active'): ?>
                                        <a href="<?php url('logic/academic_years/set_active.php?id=' . $year['id']); ?>"
                                            class="text-green-600 hover:text-green-900 p-1 hover:bg-green-50 rounded transition-colors"
                                            title="Set Aktif"
                                            onclick="return confirm('Set tahun ini sebagai aktif? Tahun lain akan dinonaktifkan.')">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($year)); ?>)"
                                        class="text-indigo-600 hover:text-indigo-900 p-1 hover:bg-indigo-50 rounded transition-colors"
                                        title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </button>
                                    <a href="<?php url('logic/academic_years/delete.php?id=' . $year['id']); ?>"
                                        class="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded transition-colors"
                                        title="Delete" onclick="return confirm('Hapus tahun ajaran ini?')">
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
<div id="addModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 border-b pb-2 mb-4">Tambah Tahun Ajaran</h3>
            <form action="<?php url('logic/academic_years/store.php'); ?>" method="POST">
                <div class="mb-4 text-left">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Nama (Contoh: 2024/2025)</label>
                    <input type="text" name="name" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4 text-left">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai</label>
                    <input type="date" name="start_date"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4 text-left">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Selesai</label>
                    <input type="date" name="end_date"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="flex items-center justify-end mt-6">
                    <button type="button" onclick="closeModal('addModal')"
                        class="bg-gray-200 text-gray-800 px-4 py-2 rounded mr-2 hover:bg-gray-300">Batal</button>
                    <button type="submit"
                        class="bg-cyan-600 text-white px-4 py-2 rounded hover:bg-cyan-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg leading-6 font-medium text-gray-900 border-b pb-2 mb-4">Edit Tahun Ajaran</h3>
            <form action="<?php url('logic/academic_years/update.php'); ?>" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="status" id="edit_status">
                <div class="mb-4 text-left">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Nama</label>
                    <input type="text" name="name" id="edit_name" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4 text-left">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai</label>
                    <input type="date" name="start_date" id="edit_start_date"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4 text-left">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Selesai</label>
                    <input type="date" name="end_date" id="edit_end_date"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="flex items-center justify-end mt-6">
                    <button type="button" onclick="closeModal('editModal')"
                        class="bg-gray-200 text-gray-800 px-4 py-2 rounded mr-2 hover:bg-gray-300">Batal</button>
                    <button type="submit"
                        class="bg-cyan-600 text-white px-4 py-2 rounded hover:bg-cyan-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_name').value = data.name;
        document.getElementById('edit_start_date').value = data.start_date;
        document.getElementById('edit_end_date').value = data.end_date;
        document.getElementById('edit_status').value = data.status;
        openModal('editModal');
    }
</script>

<?php include '../layouts/footer.php'; ?>