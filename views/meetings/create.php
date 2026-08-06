<?php
require_once '../../config/app.php';
require_once '../../config/db_mysqli.php';

check_login();

$page_title = "Buat Rapat Baru";

// Fetch divisions for dropdown
$resDiv = $mysqli->query("SELECT * FROM divisions ORDER BY name ASC");
$divisions = [];
while ($row = $resDiv->fetch_assoc())
    $divisions[] = $row;

// Fetch active employees with Division and Unit info
$queryEmp = "
    SELECT e.id, e.full_name, d.name as division_name, u.name as unit_name, p.level as position_level 
    FROM employees e 
    LEFT JOIN divisions d ON e.division_id = d.id 
    LEFT JOIN units u ON e.unit_id = u.id 
    JOIN positions p ON e.position_id = p.id
    WHERE e.status = 'active' 
    ORDER BY d.name, u.name, e.full_name ASC
";
$resEmp = $mysqli->query($queryEmp);
$employees = [];
$pengurusInti = [];
while ($row = $resEmp->fetch_assoc()) {
    // Collect Pengurus Inti (Level 1 & 2)
    if ($row['position_level'] == 1 || $row['position_level'] == 2) {
        $pengurusInti[] = $row;
    }

    // Create a group key for display
    $div = $row['division_name'] ?: 'Tanpa Divisi';
    $unit = $row['unit_name'] ?: 'Umum';
    $groupKey = "$div - $unit";
    
    $employees[$groupKey][] = $row;
}

// Prepend Pengurus Inti if not empty
if (!empty($pengurusInti)) {
    $employees = ['Pengurus Inti' => $pengurusInti] + $employees;
}

include '../layouts/header.php';
?>

<!-- Tom Select CSS (if not in header) -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.2.2/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<!-- Tom Select JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.2.2/js/tom-select.complete.min.js"></script>

<div class="min-h-screen pb-10">
    <!-- Top Header -->
    <div class="flex justify-between items-start mb-8 pt-6">
        <div>
           <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <a href="index.php" class="hover:text-indigo-600">Manajemen Rapat</a>
                <i class="fa-solid fa-chevron-right h-3 w-3"></i>
                <span class="font-medium text-indigo-600">Buat Baru</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Buat Rapat Baru</h1>
            <p class="mt-1 text-slate-500">Isi formulir di bawah untuk menjadwalkan rapat baru.</p>
        </div>
    </div>

    <div class="w-full bg-white rounded-xl shadow-sm border border-slate-200">
        <form id="createForm" class="p-8 space-y-6">
            <!-- Hidden inputs -->
            <input type="hidden" name="created_by" value="<?php echo $_SESSION['user_id'] ?? 1; ?>">

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium text-slate-700">Judul Rapat</label>
                <input type="text" name="title" required placeholder="Contoh: Rapat Koordinasi Q3"
                    class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-slate-700">Deskripsi/Agenda</label>
                <textarea name="description" rows="3" placeholder="Jelaskan agenda rapat..."
                    class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
            </div>

            <!-- Date & Division -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Tanggal</label>
                    <input type="date" name="date" required
                        class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Bidang Penyelenggara</label>
                    <select name="division_id" required
                        class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Pilih Bidang</option>
                        <?php foreach ($divisions as $div): ?>
                            <option value="<?= $div['id'] ?>">
                                <?= htmlspecialchars($div['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Time -->
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Waktu Mulai</label>
                    <input type="time" name="start_time" required
                        class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Waktu Selesai</label>
                    <input type="time" name="end_time" required
                        class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
            </div>

            <!-- Type & Location -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Tipe Rapat</label>
                    <select name="type" required
                        class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="offline">Offline (Tatap Muka)</option>
                        <option value="online">Online (Daring)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Lokasi / Link</label>
                    <input type="text" name="location" placeholder="Contoh: Ruang Rapat 1 atau Link Zoom"
                        class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
            </div>

            <!-- Participants -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Peserta Undangan</label>
                
                <!-- Quick Filter Buttons (Optional) -->
                <!-- 
                <div class="flex gap-2 mb-2">
                    <button type="button" onclick="selectAll()" class="text-xs bg-slate-100 px-2 py-1 rounded hover:bg-slate-200">Pilih Semua</button>
                    <button type="button" onclick="clearAll()" class="text-xs bg-slate-100 px-2 py-1 rounded hover:bg-slate-200">Reset</button>
                </div> 
                -->

                <select id="participant-select" name="participant_ids[]" multiple
                    placeholder="Cari nama pegawai, divisi, atau unit..." autocomplete="off">
                    <?php foreach ($employees as $groupName => $members): ?>
                        <optgroup label="<?= htmlspecialchars($groupName) ?>">
                            <?php foreach ($members as $emp): ?>
                                <option value="<?= $emp['id'] ?>" data-div="<?= htmlspecialchars($emp['division_name']) ?>" data-unit="<?= htmlspecialchars($emp['unit_name']) ?>">
                                    <?= htmlspecialchars($emp['full_name']) ?>
                                    (<?= htmlspecialchars($emp['unit_name'] ?: '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <div class="mt-2 text-xs text-slate-400 flex items-center justify-between">
                    <span>Ketik nama, divisi, atau unit untuk mencari.</span>
                    <span class="text-slate-300">Tips: Gunakan CTRL+Click untuk memilih banyak (desktop)</span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-slate-100">
                <a href="index.php"
                    class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    Batal
                </a>
                <button type="submit"
                    class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors shadow-sm">
                    Buat Jadwal Rapat
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize Tom Select
    var tomSelectInstance = new TomSelect("#participant-select", {
        plugins: ['remove_button', 'clear_button'],
        create: false,
        sortField: {
            field: "text",
            direction: "asc"
        },
        searchField: ['text', 'optgroup'], // Search in text and group label
        placeholder: "Pilih Peserta..."
    });

    // Handle Form Submit
    document.getElementById('createForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('../../api/create_meeting.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Rapat berhasil dijadwalkan.',
                        icon: 'success',
                        confirmButtonText: 'Kembali ke Dashboard',
                        confirmButtonColor: '#4f46e5'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'index.php';
                        }
                    });
                } else {
                    Swal.fire('Gagal', data.message || 'Terjadi kesalahan saat membuat rapat', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Terjadi kesalahan koneksi', 'error');
            });
    });
</script>

<?php include '../layouts/footer.php'; ?>