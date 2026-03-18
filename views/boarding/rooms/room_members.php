<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('can_access_kesantrian');

$room_id = $_GET['room_id'] ?? null;
if (!$room_id) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Fetch room info
$room_stmt = $conn->prepare("SELECT br.*, e.full_name as supervisor_name FROM boarding_rooms br JOIN employees e ON br.supervisor_id = e.id WHERE br.id = ?");
$room_stmt->execute([$room_id]);
$room = $room_stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    header('Location: index.php');
    exit;
}

$page_title = "Penempatan Santri - " . $room['room_name'];

// Fetch members
$members_query = "
    SELECT brm.*, s.nama_siswa, s.nomor_induk as student_nik, s.kelas as class_name
    FROM boarding_room_members brm
    JOIN students s ON brm.student_id = s.id
    WHERE brm.room_id = ?
    ORDER BY s.nama_siswa ASC
";
$members_stmt = $conn->prepare($members_query);
$members_stmt->execute([$room_id]);
$members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch students not in ANY room for adding
$available_students_query = "
    SELECT s.id, s.nama_siswa, s.nomor_induk, s.kelas
    FROM students s
    WHERE NOT EXISTS (SELECT 1 FROM boarding_room_members brm WHERE brm.student_id = s.id)
    ORDER BY s.nama_siswa ASC
";
$available_stmt = $conn->prepare($available_students_query);
$available_stmt->execute();
$available_students = $available_stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Breadcrumbs & Header -->
    <div class="flex flex-col gap-2">
        <nav class="flex text-sm text-slate-400 font-medium">
            <a href="index.php" class="hover:text-indigo-600 transition-colors">Data Asrama</a>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-600">Penempatan Santri</span>
        </nav>
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800"><?php echo htmlspecialchars($room['room_name']); ?></h1>
                <p class="text-slate-500 mt-1">Musyrif/Pembina: <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($room['supervisor_name']); ?></span></p>
            </div>
            <div class="mt-4 sm:mt-0">
                <button onclick="openModal('modal-add-member')" 
                    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    Tambah Santri
                </button>
            </div>
        </div>
    </div>

    <!-- Members Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">No.</th>
                        <th class="px-6 py-4">Nama Santri</th>
                        <th class="px-6 py-4">Nomor Induk</th>
                        <th class="px-6 py-4">Kelas</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($members) > 0): ?>
                        <?php foreach ($members as $index => $m): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 text-slate-400 font-medium"><?php echo $index + 1; ?>.</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-9 w-9 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold border border-white ring-2 ring-indigo-50">
                                            <?php echo substr($m['nama_siswa'], 0, 1); ?>
                                        </div>
                                        <div class="ml-3">
                                            <p class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($m['nama_siswa']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-500 font-mono text-xs italic"><?php echo htmlspecialchars($m['student_nik'] ?? '-'); ?></td>
                                <td class="px-6 py-4">
                                    <span class="text-xs font-medium text-slate-500"><?php echo htmlspecialchars($m['class_name'] ?? '-'); ?></span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button onclick="removeMember(<?php echo $m['id']; ?>)" class="text-slate-400 hover:text-red-600 transition-colors p-2" title="Keluarkan dari asrama">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-20 text-center text-slate-400 italic">Belum ada santri di asrama ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add Member -->
<div id="modal-add-member" class="fixed inset-0 z-50 hidden overflow-y-auto transition-opacity duration-300 opacity-0" aria-hidden="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm opacity-100"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-slate-200">
            <form action="../../../logic/boarding/manage_rooms.php" method="POST">
                <input type="hidden" name="action" value="add_member">
                <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                
                <div class="bg-white px-8 pt-8 pb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-slate-800">Tambah Santri ke Asrama</h3>
                        <button type="button" onclick="closeModal('modal-add-member')" class="text-slate-400 hover:text-slate-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative mb-6">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" id="member-search" onkeyup="filterStudents()" 
                            class="block w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl bg-slate-50 text-sm focus:ring-indigo-500 focus:border-indigo-500 transition-all" 
                            placeholder="Cari nama santri atau nomor induk...">
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between mb-2 px-1">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Daftar Santri Belum Ditempatkan</p>
                            <?php if (count($available_students) > 0): ?>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" id="select-all-available" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs font-bold text-slate-500 group-hover:text-indigo-600 transition-colors">Pilih Semua</span>
                            </label>
                            <?php endif; ?>
                        </div>
                        <div class="max-h-80 overflow-y-auto pr-2 custom-scrollbar" id="student-list-container">
                           <div class="grid grid-cols-1 gap-2">
                               <?php if (count($available_students) > 0): ?>
                                   <?php foreach ($available_students as $s): ?>
                                       <label class="student-item flex items-center p-3 rounded-xl border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 cursor-pointer transition-all group" 
                                              data-name="<?php echo strtolower(htmlspecialchars($s['nama_siswa'])); ?>"
                                              data-nik="<?php echo strtolower(htmlspecialchars($s['nomor_induk'] ?? '')); ?>">
                                           <input type="checkbox" name="student_ids[]" value="<?php echo $s['id']; ?>" class="student-checkbox h-4 w-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
                                           <div class="ml-4">
                                               <p class="text-sm font-bold text-slate-700 group-hover:text-indigo-700"><?php echo htmlspecialchars($s['nama_siswa']); ?></p>
                                               <p class="text-xs text-slate-400"><?php echo htmlspecialchars($s['nomor_induk'] ?? '-'); ?> • <?php echo htmlspecialchars($s['kelas'] ?? ''); ?></p>
                                           </div>
                                       </label>
                                   <?php endforeach; ?>
                               <?php else: ?>
                                   <div class="py-12 text-center">
                                       <p class="text-slate-400 text-sm italic">Semua santri sudah mendapatkan asrama.</p>
                                   </div>
                               <?php endif; ?>
                           </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-8 py-6 flex flex-row-reverse gap-3">
                    <?php if (count($available_students) > 0): ?>
                        <button type="submit" class="inline-flex justify-center rounded-xl bg-indigo-600 px-8 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 focus:outline-none transition-all">
                            Simpan Penempatan
                        </button>
                    <?php endif; ?>
                    <button type="button" onclick="closeModal('modal-add-member')" class="inline-flex justify-center rounded-xl bg-white border border-slate-200 px-6 py-2.5 text-sm font-bold text-slate-600 shadow-sm hover:bg-slate-50 focus:outline-none transition-all">
                        Tutup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('hidden');
        void modal.offsetWidth;
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
        document.getElementById('member-search').value = '';
        filterStudents();
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

    // Select All Logic
    const selectAllCheckbox = document.getElementById('select-all-available');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const visibleCheckboxes = document.querySelectorAll('.student-item:not([style*="display: none"]) .student-checkbox');
            visibleCheckboxes.forEach(cb => cb.checked = this.checked);
        });
    }

    function filterStudents() {
        const query = document.getElementById('member-search').value.toLowerCase();
        const items = document.querySelectorAll('.student-item');
        items.forEach(item => {
            const name = item.getAttribute('data-name');
            const nik = item.getAttribute('data-nik');
            item.style.display = (name.includes(query) || nik.includes(query)) ? 'flex' : 'none';
        });
    }

    function removeMember(memberId) {
        if (confirm('Keluarkan santri ini dari asrama?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../../../logic/boarding/manage_rooms.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden'; actionInput.name = 'action'; actionInput.value = 'remove_member';
            form.appendChild(actionInput);
            
            const memberInput = document.createElement('input');
            memberInput.type = 'hidden'; memberInput.name = 'member_id'; memberInput.value = memberId;
            form.appendChild(memberInput);

            const roomInput = document.createElement('input');
            roomInput.type = 'hidden'; roomInput.name = 'room_id'; roomInput.value = '<?php echo $room_id; ?>';
            form.appendChild(roomInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<?php include '../../layouts/footer.php'; ?>
