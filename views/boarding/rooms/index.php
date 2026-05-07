<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('can_access_kesantrian');

$page_title = "Data Asrama";

$db = new Database();
$conn = $db->getConnection();

// --- Fetch Data ---
// 1. Fetch Boarding Rooms with multiple Supervisors
$rooms_query = "
    SELECT br.*, 
    (SELECT GROUP_CONCAT(e.full_name SEPARATOR ', ') FROM boarding_room_supervisors brs JOIN employees e ON brs.supervisor_id = e.id WHERE brs.room_id = br.id) as supervisor_name,
    (SELECT GROUP_CONCAT(e.id) FROM boarding_room_supervisors brs JOIN employees e ON brs.supervisor_id = e.id WHERE brs.room_id = br.id) as supervisor_ids,
    (SELECT COUNT(*) FROM boarding_room_members WHERE room_id = br.id) as member_count
    FROM boarding_rooms br
    ORDER BY CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(br.room_name, ' (', 1), ' ', -1) AS UNSIGNED) ASC, br.room_name ASC
";
$rooms = $conn->query($rooms_query)->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch All Supervisors (for room creation)
$supervisors_query = "SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name ASC";
$supervisors = $conn->query($supervisors_query)->fetchAll(PDO::FETCH_ASSOC);

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Data Asrama</h1>
            <p class="text-slate-500 mt-1">Kelola pembagian asrama dan penempatan santri.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openModal('modal-add-room')" 
                class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Asrama
            </button>
        </div>
    </div>

    <!-- Rooms Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (count($rooms) > 0): ?>
            <?php foreach ($rooms as $room): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-all group">
                    <div class="p-6">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-3">
                                <div class="h-12 w-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($room['room_name']); ?></h3>
                                    <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Kamar/Gedung Asrama</p>
                                </div>
                            </div>
                            <div class="flex gap-1 transition-opacity">
                                <button onclick="editRoom(<?php echo htmlspecialchars(json_encode($room)); ?>)" class="p-2 text-slate-400 hover:text-cyan-600 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button onclick="deleteRoom(<?php echo $room['id']; ?>)" class="p-2 text-slate-400 hover:text-red-600 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Musyrif/Pembina</span>
                                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($room['supervisor_name']); ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Jumlah Santri</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700">
                                    <?php echo $room['member_count']; ?> Santri
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-between items-center">
                        <a href="room_members.php?room_id=<?php echo $room['id']; ?>" 
                           class="text-sm font-bold text-indigo-600 hover:text-indigo-700 transition-colors inline-flex items-center">
                            Kelola Santri
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full py-20 bg-white rounded-2xl border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 text-center px-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <p class="text-lg font-bold text-slate-600">Belum ada data asrama</p>
                <p class="text-sm mt-1 max-w-xs">Mulai dengan menambahkan asrama baru dan tugaskan musyrif.</p>
                <button onclick="openModal('modal-add-room')" class="mt-6 text-indigo-600 font-bold hover:underline">Tambah Asrama Sekarang</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Add Room -->
<div id="modal-add-room" class="fixed inset-0 z-50 hidden overflow-y-auto transition-opacity duration-300 opacity-0" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm opacity-100"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200">
            <form action="../../../logic/boarding/manage_rooms.php" method="POST">
                <input type="hidden" name="action" value="create_room" id="room-form-action">
                <input type="hidden" name="room_id" id="room-form-id">
                <div class="bg-white px-8 pt-8 pb-6 rounded-t-2xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-slate-800" id="room-modal-title">Tambah Asrama Baru</h3>
                        <button type="button" onclick="closeModal('modal-add-room')" class="text-slate-400 hover:text-slate-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Asrama / Kamar</label>
                            <input type="text" name="room_name" id="room-name-input" required placeholder="Contoh: Asrama Abu Bakar - Kamar 01"
                                class="block w-full rounded-xl border-slate-200 bg-slate-50 border px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Pilih Musyrif / Pembina (Bisa pilih lebih dari satu)</label>
                            <div id="supervisor-ids-container"></div>
                            <div class="relative" id="supervisor-dropdown">
                                <!-- Trigger Button -->
                                <button type="button" id="supervisor-dropdown-btn"
                                    onclick="toggleSupervisorDropdown()"
                                    class="flex items-center justify-between w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-left focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition-all">
                                    <span id="supervisor-dropdown-text" class="text-slate-400 truncate">Pilih Musyrif...</span>
                                    <svg class="h-4 w-4 text-slate-400 flex-shrink-0 ml-2 transition-transform duration-200" id="supervisor-dropdown-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <!-- Dropdown Panel -->
                                <div id="supervisor-dropdown-menu"
                                    class="hidden absolute left-0 right-0 mt-2 bg-white rounded-xl border border-slate-200 shadow-lg z-[60] overflow-hidden transition-all duration-200 origin-top scale-y-0 opacity-0"
                                    style="max-height: 280px;">
                                    <!-- Search Input -->
                                    <div class="p-2.5 border-b border-slate-100 sticky top-0 bg-white z-10">
                                        <div class="relative">
                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                                </svg>
                                            </div>
                                            <input type="text" id="supervisor-search-input"
                                                placeholder="Ketik nama musyrif..."
                                                autocomplete="off"
                                                class="block w-full rounded-lg border-slate-200 bg-slate-50 border pl-9 pr-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 transition-all placeholder:text-slate-400">
                                        </div>
                                    </div>
                                    <!-- Options List -->
                                    <ul id="supervisor-options-list" class="overflow-y-auto" style="max-height: 210px;">
                                        <?php foreach ($supervisors as $s): ?>
                                            <li class="supervisor-option cursor-pointer flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                                                data-value="<?php echo $s['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($s['full_name']); ?>"
                                                onclick="selectSupervisor(this)">
                                                <span class="flex-shrink-0 h-7 w-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-xs font-bold">
                                                    <?php echo strtoupper(substr($s['full_name'], 0, 1)); ?>
                                                </span>
                                                <span class="truncate"><?php echo htmlspecialchars($s['full_name']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-8 py-6 flex flex-row-reverse gap-3 rounded-b-2xl">
                    <button type="submit" class="inline-flex justify-center rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 focus:outline-none transition-all">
                        Simpan Asrama
                    </button>
                    <button type="button" onclick="closeModal('modal-add-room')" class="inline-flex justify-center rounded-xl bg-white border border-slate-200 px-6 py-2.5 text-sm font-bold text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none transition-all">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Delete Room -->
<div id="modal-delete-room" class="fixed inset-0 z-50 hidden overflow-y-auto transition-opacity duration-300 opacity-0" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm opacity-100" onclick="closeModal('modal-delete-room')"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-200">
            <form id="delete-room-form" action="../../../logic/boarding/manage_rooms.php" method="POST">
                <input type="hidden" name="action" value="delete_room">
                <input type="hidden" name="room_id" id="delete_room_id">
                
                <div class="bg-white px-8 pt-8 pb-6 rounded-t-2xl">
                    <div class="flex flex-col items-center text-center">
                        <div class="h-14 w-14 rounded-full bg-red-50 flex items-center justify-center text-red-500 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">Hapus Asrama?</h3>
                        <p class="text-slate-500 text-sm">
                            Apakah Anda yakin ingin menghapus data asrama ini?
                            <br>
                            <span class="text-red-500 font-medium text-xs mt-1 block">Semua penempatan santri di dalamnya juga akan terpengaruh.</span>
                        </p>
                    </div>
                </div>
                
                <div class="bg-slate-50 px-8 py-6 flex flex-row gap-3 justify-center rounded-b-2xl">
                    <button type="button" onclick="closeModal('modal-delete-room')" 
                        class="w-full inline-flex justify-center rounded-xl bg-white border border-slate-200 px-6 py-2.5 text-sm font-bold text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none transition-all">
                        Batal
                    </button>
                    <button type="submit" 
                        class="w-full inline-flex justify-center rounded-xl bg-red-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-red-700 focus:outline-none transition-all">
                        Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let selectedSupervisors = new Map();

    function openModal(id) {
        if (id === 'modal-add-room') {
            document.getElementById('room-form-action').value = 'create_room';
            document.getElementById('room-form-id').value = '';
            document.getElementById('room-name-input').value = '';
            document.getElementById('room-modal-title').innerText = 'Tambah Asrama Baru';
            
            selectedSupervisors.clear();
            updateSupervisorUI();
            document.querySelectorAll('.supervisor-option').forEach(opt => {
                opt.classList.remove('bg-indigo-50', 'text-indigo-700', 'font-semibold');
            });
        }

        const modal = document.getElementById(id);
        modal.classList.remove('hidden');
        void modal.offsetWidth; 
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
    }

    function editRoom(room) {
        openModal('modal-add-room');
        document.getElementById('room-form-action').value = 'update_room';
        document.getElementById('room-form-id').value = room.id;
        document.getElementById('room-name-input').value = room.room_name;
        document.getElementById('room-modal-title').innerText = 'Ubah Asrama';

        selectedSupervisors.clear();
        const ids = room.supervisor_ids ? room.supervisor_ids.split(',') : [];
        const names = room.supervisor_name ? room.supervisor_name.split(', ') : [];
        
        ids.forEach((id, index) => {
            selectedSupervisors.set(id, names[index]);
            const opt = document.querySelector(`.supervisor-option[data-value="${id}"]`);
            if (opt) opt.classList.add('bg-indigo-50', 'text-indigo-700', 'font-semibold');
        });
        
        updateSupervisorUI();
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }, 300);
    }

    function deleteRoom(id) {
        document.getElementById('delete_room_id').value = id;
        openModal('modal-delete-room');
    }

    // --- Custom Searchable Dropdown for Supervisor ---
    let supervisorDropdownOpen = false;

    function toggleSupervisorDropdown() {
        supervisorDropdownOpen ? closeSupervisorDropdown() : openSupervisorDropdown();
    }

    function openSupervisorDropdown() {
        const menu = document.getElementById('supervisor-dropdown-menu');
        const arrow = document.getElementById('supervisor-dropdown-arrow');
        const searchInput = document.getElementById('supervisor-search-input');
        
        menu.classList.remove('hidden');
        void menu.offsetWidth;
        menu.classList.remove('scale-y-0', 'opacity-0');
        menu.classList.add('scale-y-100', 'opacity-100');
        arrow.classList.add('rotate-180');
        supervisorDropdownOpen = true;
        setTimeout(() => searchInput.focus(), 100);
    }

    function closeSupervisorDropdown() {
        const menu = document.getElementById('supervisor-dropdown-menu');
        const arrow = document.getElementById('supervisor-dropdown-arrow');
        menu.classList.remove('scale-y-100', 'opacity-100');
        menu.classList.add('scale-y-0', 'opacity-0');
        arrow.classList.remove('rotate-180');
        supervisorDropdownOpen = false;
        setTimeout(() => menu.classList.add('hidden'), 200);
    }

    function selectSupervisor(el) {
        const id = el.getAttribute('data-value');
        const name = el.getAttribute('data-name');
        
        if (selectedSupervisors.has(id)) {
            selectedSupervisors.delete(id);
            el.classList.remove('bg-indigo-50', 'text-indigo-700', 'font-semibold');
        } else {
            selectedSupervisors.set(id, name);
            el.classList.add('bg-indigo-50', 'text-indigo-700', 'font-semibold');
        }
        
        updateSupervisorUI();
    }

    function updateSupervisorUI() {
        const container = document.getElementById('supervisor-ids-container');
        const textEl = document.getElementById('supervisor-dropdown-text');
        
        container.innerHTML = '';
        let names = [];
        
        selectedSupervisors.forEach((name, id) => {
            names.push(name);
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'supervisor_ids[]';
            input.value = id;
            container.appendChild(input);
        });
        
        if (names.length > 0) {
            textEl.textContent = names.join(', ');
            textEl.classList.remove('text-slate-400');
            textEl.classList.add('text-slate-800', 'font-medium');
        } else {
            textEl.textContent = 'Pilih Musyrif...';
            textEl.classList.remove('text-slate-800', 'font-medium');
            textEl.classList.add('text-slate-400');
        }
    }

    document.getElementById('supervisor-search-input').addEventListener('input', function() {
        const keyword = this.value.toLowerCase().trim();
        const options = document.querySelectorAll('.supervisor-option');
        options.forEach(opt => {
            const name = opt.getAttribute('data-name').toLowerCase();
            opt.classList.toggle('hidden', !name.includes(keyword));
        });
    });

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('supervisor-dropdown');
        if (dropdown && !dropdown.contains(e.target) && supervisorDropdownOpen) {
            closeSupervisorDropdown();
        }
    });
</script>

<?php include '../../layouts/footer.php'; ?>
