<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Pengaturan Jam Pelajaran";

$db = new Database();
$conn = $db->getConnection();

// Fetch Education Units for grouping/filtering
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';

$where_clauses = ["1=1"];
$params = [];

if (!empty($unit_id)) {
    $where_clauses[] = "lp.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

$where_sql = implode(' AND ', $where_clauses);

$query = "SELECT lp.*, eu.name as unit_name 
          FROM lesson_periods lp 
          JOIN education_units eu ON lp.education_unit_id = eu.id 
          WHERE $where_sql 
          ORDER BY eu.name ASC, lp.period_number ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                        <path fill-rule="evenodd" d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z" clip-rule="evenodd" />
                    </svg>
                    Dashboard
                </a>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 text-slate-500 hover:text-slate-800">Jam Pelajaran</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-3xl font-bold leading-7 text-slate-900 sm:truncate sm:tracking-tight">Jam Pelajaran</h2>
            <p class="mt-2 text-sm text-slate-500">Atur waktu mulai dan selesai sesi pelajaran per jenjang pendidikan.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0 gap-3">
            <button type="button" id="bulkDeleteBtn" onclick="confirmBulkDelete()" class="hidden inline-flex items-center rounded-lg border border-red-600 bg-white px-4 py-2 text-sm font-semibold text-red-600 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
                Hapus Terpilih (<span id="selectedCount">0</span>)
            </button>
            <a href="<?php url('views/lesson_periods/form.php?' . http_build_query($_GET)); ?>" class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Jam
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6">
        <form id="filterForm" action="" method="GET" class="flex flex-col md:flex-row gap-4 items-center">
            <!-- Custom Education Unit Dropdown -->
            <div class="relative w-full md:w-64" id="container-unit_id">
                <input type="hidden" name="unit_id" id="input-unit_id" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleFormDropdown('unit_id')"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-4 py-2 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                    <span id="text-unit_id" class="block truncate">
                        <?php 
                        $unitTitle = "Pilih Semua";
                        foreach($units as $u) if((string)$u['id'] === (string)$unit_id) $unitTitle = $u['name'];
                        echo htmlspecialchars($unitTitle);
                        ?>
                    </span>
                    <svg id="arrow-unit_id" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="menu-unit_id" class="hidden absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5">
                        <input type="text" id="search-unit_id" onkeyup="filterDropdownSearch('unit_id')" placeholder="Cari jenjang..." class="block w-full rounded-md border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-unit_id">
                        <li onclick="selectFilterOption('unit_id', '', 'Pilih Semua')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Pilih Semua</li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit_id', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <?php if (!empty($unit_id)): ?>
                <a href="<?php url('views/lesson_periods/index.php'); ?>" class="text-sm text-cyan-600 hover:text-cyan-700 font-medium">Reset Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <script>
    function toggleFormDropdown(id) {
        const menu = document.getElementById('menu-' + id);
        const arrow = document.getElementById('arrow-' + id);
        const allMenus = document.querySelectorAll('[id^="menu-"]');
        const allArrows = document.querySelectorAll('[id^="arrow-"]');
        
        allMenus.forEach(m => { if(m !== menu) m.classList.add('hidden'); });
        allArrows.forEach(a => { if(a !== arrow) a.classList.remove('rotate-180'); });

        menu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
        
        if (!menu.classList.contains('hidden') && document.getElementById('search-' + id)) {
            document.getElementById('search-' + id).focus();
        }
    }

    function selectFilterOption(id, value, text) {
        document.getElementById('input-' + id).value = value;
        document.getElementById('text-' + id).innerText = text;
        document.getElementById('menu-' + id).classList.add('hidden');
        document.getElementById('arrow-' + id).classList.remove('rotate-180');
        
        document.getElementById('filterForm').submit();
    }

    function filterDropdownSearch(id) {
        const input = document.getElementById('search-' + id);
        const filter = input.value.toLowerCase();
        const list = document.getElementById('list-' + id);
        const li = list.getElementsByTagName('li');

        for (let i = 0; i < li.length; i++) {
            const txtValue = li[i].textContent || li[i].innerText;
            const matchesSearch = txtValue.toLowerCase().indexOf(filter) > -1;
            li[i].style.display = matchesSearch ? "" : "none";
        }
    }

    // Close on click outside
    window.addEventListener('click', function (e) {
        document.querySelectorAll('[id^="container-"]').forEach(container => {
            if (!container.contains(e.target)) {
                const id = container.id.replace('container-', '');
                const menu = document.getElementById('menu-' + id);
                if(menu) menu.classList.add('hidden');
                const arrow = document.getElementById('arrow-' + id);
                if(arrow) arrow.classList.remove('rotate-180');
            }
        });
    });
    </script>

    <!-- Table -->
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 uppercase tracking-widest text-[10px] font-bold text-slate-500">
                <tr>
                    <th class="py-3.5 pl-4 pr-3 text-left w-12 sm:pl-6 text-center">
                        <input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes(this)" class="custom-checkbox">
                    </th>
                    <th class="py-3.5 pl-4 pr-3 text-left w-12 text-center border-none">No.</th>
                    <th class="px-3 py-3.5 text-left min-w-[150px] border-none">Jenjang</th>
                    <th class="px-3 py-3.5 text-left min-w-[120px] border-none">Jam Ke</th>
                    <th class="px-3 py-3.5 text-left min-w-[120px] border-none">Waktu Mulai</th>
                    <th class="px-3 py-3.5 text-left min-w-[120px] border-none">Waktu Selesai</th>
                    <th class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right w-32 border-none">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php if (empty($periods)): ?>
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-sm text-slate-500 text-center italic">Tidak ada data jam pelajaran ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($periods as $index => $lp): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                                <input type="checkbox" value="<?php echo $lp['id']; ?>" class="row-checkbox custom-checkbox" onchange="updateBulkDeleteBtn()">
                            </td>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 text-center"><?php echo $index + 1; ?>.</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lp['unit_name']); ?></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600 font-bold">Jam ke-<?php echo $lp['period_number']; ?></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600"><?php echo date('H:i', strtotime($lp['start_time'])); ?></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600"><?php echo date('H:i', strtotime($lp['end_time'])); ?></td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex justify-end gap-2 text-gray-400">
                                    <?php 
                                    $editQuery = $_GET;
                                    $editQuery['id'] = $lp['id'];
                                    ?>
                                    <a href="<?php url('views/lesson_periods/form.php?' . http_build_query($editQuery)); ?>" class="hover:text-amber-600 transition-colors" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </a>
                                    <button onclick="openDeleteModal('<?php url('logic/lesson_periods/delete.php?id=' . $lp['id'] . '&' . http_build_query($_GET)); ?>')" class="hover:text-red-600 transition-colors" title="Hapus">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div id="bulkDeleteModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div id="bulkDeleteModalBackdrop" class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div id="bulkDeleteModalPanel" class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-bold leading-6 text-slate-900" id="modal-title">Hapus Jam Pelajaran Terpilih</h3>
                        <div class="mt-2 text-sm text-slate-500">
                            Apakah Anda yakin ingin menghapus <span id="bulkDeleteCount" class="font-bold text-slate-700"></span> jam pelajaran terpilih? Tindakan ini tidak dapat dibatalkan.
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                <button type="button" onclick="submitBulkDelete()" class="inline-flex w-full justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-all transform active:scale-95">
                    Hapus Terpilih
                </button>
                <button type="button" onclick="closeBulkDeleteModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<form id="hiddenBulkDeleteForm" method="POST" action="../../logic/lesson_periods/bulk_delete.php?<?php echo http_build_query($_GET); ?>" class="hidden">
</form>

<script>
    function toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = source.checked);
        updateBulkDeleteBtn();
    }

    function updateBulkDeleteBtn() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        const btn = document.getElementById('bulkDeleteBtn');
        const countSpan = document.getElementById('selectedCount');
        const selectAllCb = document.getElementById('selectAll');
        const allCheckboxes = document.querySelectorAll('.row-checkbox');
        
        if (checkboxes.length > 0) {
            btn.classList.remove('hidden');
            countSpan.textContent = checkboxes.length;
        } else {
            btn.classList.add('hidden');
        }

        if (allCheckboxes.length > 0) {
            selectAllCb.checked = (checkboxes.length === allCheckboxes.length);
        }
    }

    function openBulkDeleteModal(count) {
        document.getElementById('bulkDeleteCount').textContent = count;
        const modal = document.getElementById('bulkDeleteModal');
        const backdrop = document.getElementById('bulkDeleteModalBackdrop');
        const panel = document.getElementById('bulkDeleteModalPanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeBulkDeleteModal() {
        const modal = document.getElementById('bulkDeleteModal');
        const backdrop = document.getElementById('bulkDeleteModalBackdrop');
        const panel = document.getElementById('bulkDeleteModalPanel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function confirmBulkDelete() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) return;
        
        openBulkDeleteModal(checkboxes.length);
    }

    function submitBulkDelete() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) return;
        
        const form = document.getElementById('hiddenBulkDeleteForm');
        form.innerHTML = ''; // clear previous elements
        checkboxes.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            form.appendChild(input);
        });
        
        // Hide modal and submit
        closeBulkDeleteModal();
        form.submit();
    }
</script>

<?php include '../layouts/footer.php'; ?>
