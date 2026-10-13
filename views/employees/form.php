<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = !empty($id);
$page_title = $is_edit ? "Edit Pegawai" : "Tambah Pegawai Baru";

$employee = [
    'full_name' => '',
    'email' => '',
    'phone_number' => '',
    'address' => '',
    'division_id' => '',
    'unit_id' => '',
    'position_id' => '',
    'schedule_id' => '',
    'id' => ''
];

if ($is_edit) {
    try {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $fetched = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fetched) {
            $employee = $fetched;
        } else {
            header("Location: index.php?error=Pegawai tidak ditemukan");
            exit;
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Fetch all necessary data
$divisions = $conn->query("SELECT * FROM divisions ORDER BY id ASC")->fetchAll();
$units = $conn->query("SELECT * FROM units ORDER BY name ASC")->fetchAll();
$positions = $conn->query("SELECT * FROM positions ORDER BY level ASC")->fetchAll();
$schedules = $conn->query("SELECT * FROM work_schedules ORDER BY name ASC")->fetchAll();

include '../layouts/header.php';

// Capture return filters
$return_filters = $_GET;
unset($return_filters['id'], $return_filters['error'], $return_filters['success']);
$return_filters_qs = http_build_query($return_filters);
?>

<div class="w-full pb-12">
    <div class="mb-6">
        <nav class="flex mb-3" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-2 text-xs text-slate-500">
                <li>
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 transition-colors">Beranda</a>
                </li>
                <li>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <a href="<?php url('views/employees/index.php'); ?>" class="hover:text-slate-800 transition-colors">Pegawai</a>
                </li>
                <li aria-current="page">
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-800 font-medium"><?php echo $is_edit ? "Edit Pegawai" : "Tambah Baru"; ?></span>
                </li>
            </ol>
        </nav>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight"><?php echo $is_edit ? "Edit Pegawai" : "Tambah Pegawai Baru"; ?></h1>
        <p class="text-slate-500 text-sm mt-1">
            <?php echo $is_edit ? "Perbarui informasi dan data kepegawaian untuk " . htmlspecialchars($employee['full_name']) : "Lengkapi formulir di bawah ini untuk mendaftarkan pegawai baru ke dalam sistem."; ?>
        </p>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-red-500 flex-shrink-0"></i>
            <span><?php echo htmlspecialchars($_GET['error']); ?></span>
        </div>
    <?php endif; ?>

    <form action="<?php echo $is_edit ? url('logic/employees/update.php') : url('logic/employees/store.php'); ?>"
        method="POST" enctype="multipart/form-data" class="space-y-6">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
        <?php endif; ?>
        <input type="hidden" name="return_filters" value="<?php echo htmlspecialchars($return_filters_qs); ?>">

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 divide-y divide-slate-200">

            <!-- Personal Information Section -->
            <div class="p-6 sm:p-8">
                <div class="mb-6">
                    <h2 class="text-base font-semibold text-slate-900">Informasi Pribadi</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Identitas dasar dan kontak pegawai.</p>
                </div>

                <!-- Profile Photo -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-5 p-4 rounded-xl bg-slate-50 border border-slate-200/80 mb-6">
                    <div class="relative w-20 h-20 rounded-full border border-slate-200 shadow-sm overflow-hidden bg-slate-100 flex-shrink-0 flex items-center justify-center">
                        <?php
                        $photo_url = null;
                        if (!empty($employee['profile_photo'])) {
                            if (file_exists(BASE_PATH . '/uploads/profile_photos/' . $employee['profile_photo'])) {
                                $photo_url = BASE_URL . '/uploads/profile_photos/' . $employee['profile_photo'];
                            } elseif (file_exists(BASE_PATH . '/public/uploads/employees/' . $employee['profile_photo'])) {
                                $photo_url = BASE_URL . '/public/uploads/employees/' . $employee['profile_photo'];
                            }
                        }
                        ?>
                        <img id="profile_preview"
                            class="w-full h-full object-cover <?php echo $photo_url ? '' : 'hidden'; ?>"
                            src="<?php echo $photo_url ?: ''; ?>" alt="Foto Profil">

                        <div id="photo_placeholder"
                            class="<?php echo $photo_url ? 'hidden' : 'flex'; ?> items-center justify-center w-full h-full text-slate-400">
                            <i class="fa-solid fa-user text-2xl"></i>
                        </div>
                    </div>

                    <div class="flex-1">
                        <div class="text-sm font-semibold text-slate-800 mb-0.5">Foto Profil</div>
                        <p class="text-xs text-slate-500 mb-3">Format JPG, JPEG, PNG, atau WebP. Maksimal 2MB.</p>
                        <div class="flex items-center gap-2.5">
                            <input type="file" name="profile_photo" id="profile_photo_input" class="hidden"
                                accept="image/*" onchange="previewImage(this)">
                            <input type="hidden" name="remove_photo" id="remove_photo_input" value="0">

                            <button type="button" onclick="document.getElementById('profile_photo_input').click()"
                                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                                <i class="fa-solid fa-arrow-up-from-bracket text-xs text-slate-500"></i>
                                <span>Pilih Foto</span>
                            </button>
                            <button type="button" id="remove_photo_btn" onclick="removeImage()"
                                class="<?php echo $photo_url ? '' : 'hidden'; ?> inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold transition-colors cursor-pointer">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                                <span>Hapus</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Personal Fields Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                    <div>
                        <label for="nik" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            NIK (Nomor Induk Karyawan) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nik" id="nik" required
                            value="<?php echo htmlspecialchars($employee['nik'] ?? ''); ?>"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm"
                            placeholder="Masukkan NIK">
                    </div>

                    <div>
                        <label for="full_name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nama Lengkap <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="full_name" id="full_name" required
                            value="<?php echo htmlspecialchars($employee['full_name']); ?>"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm"
                            placeholder="Masukkan nama lengkap">
                    </div>

                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Alamat Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" id="email" required
                            value="<?php echo htmlspecialchars($employee['email']); ?>"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm"
                            placeholder="nama@email.com">
                    </div>

                    <div>
                        <label for="phone_number" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nomor Telepon <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="phone_number" id="phone_number" required
                            value="<?php echo htmlspecialchars($employee['phone_number']); ?>"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm"
                            placeholder="0812xxxxxxxx">
                    </div>

                    <div>
                        <label for="gender" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Jenis Kelamin <span class="text-red-500">*</span>
                        </label>
                        <select name="gender" id="gender" required data-searchable="false"
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <option value="">Pilih Jenis Kelamin</option>
                            <option value="Male" <?php echo (($employee['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="Female" <?php echo (($employee['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            <?php echo $is_edit ? "Kata Sandi Baru" : "Kata Sandi Default"; ?>
                            <span class="<?php echo $is_edit ? "text-slate-400 text-xs font-normal normal-case" : "text-red-500"; ?>">
                                <?php echo $is_edit ? "(Kosongkan jika tidak ingin diubah)" : "*"; ?>
                            </span>
                        </label>
                        <input type="password" name="password" id="password" <?php echo $is_edit ? '' : 'required'; ?>
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm"
                            placeholder="••••••••">
                    </div>

                    <div class="md:col-span-2">
                        <label for="address" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Alamat Lengkap <span class="text-red-500">*</span>
                        </label>
                        <textarea name="address" id="address" rows="3" required
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm"
                            placeholder="Alamat lengkap tempat tinggal..."><?php echo htmlspecialchars($employee['address']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Employment Details Section -->
            <div class="p-6 sm:p-8">
                <div class="mb-6">
                    <h2 class="text-base font-semibold text-slate-900">Rincian Penempatan & Jadwal Kerja</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Penetapan bidang organisasi, unit kerja, jabatan, dan jadwal.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                    <!-- Division -->
                    <div>
                        <label for="division_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Bidang Organisasi <span class="text-red-500">*</span>
                        </label>
                        <select name="division_id" id="division_id" required
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <option value="">Pilih Bidang</option>
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?php echo $div['id']; ?>" <?php echo ($div['id'] == ($employee['division_id'] ?? '')) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($div['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Unit -->
                    <div>
                        <label for="unit_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Unit Kerja
                        </label>
                        <select name="unit_id" id="unit_id"
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <option value="">Langsung di bawah Bidang (Tanpa Unit)</option>
                        </select>
                    </div>

                    <!-- Position -->
                    <div>
                        <label for="position_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Jabatan <span class="text-red-500">*</span>
                        </label>
                        <select name="position_id" id="position_id" required
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <option value="">Pilih Jabatan</option>
                            <?php foreach ($positions as $pos):
                                if ($pos['name'] === 'Administrator') continue;
                            ?>
                                <option value="<?php echo $pos['id']; ?>" <?php echo ($pos['id'] == ($employee['position_id'] ?? '')) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pos['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Work Schedule -->
                    <div>
                        <label for="schedule_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Jadwal Kerja Pegawai
                        </label>
                        <select name="schedule_id" id="schedule_id"
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <option value="">Ikuti Jadwal Default Bidang/Unit</option>
                            <?php foreach ($schedules as $sched): ?>
                                <option value="<?php echo $sched['id']; ?>" <?php echo ($sched['id'] == ($employee['schedule_id'] ?? '')) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sched['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">Kosongkan jika ingin mengikuti pengaturan jadwal default.</p>
                    </div>
                </div>
            </div>

            <!-- Card Footer / Actions -->
            <div class="px-6 sm:px-8 py-4 bg-slate-50 flex items-center justify-end gap-3 rounded-b-xl">
                <a href="<?php url('views/employees/index.php?' . $return_filters_qs); ?>"
                    class="px-5 py-2.5 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-sm cursor-pointer">
                    Batal
                </a>
                <button type="submit"
                    class="px-6 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-600 cursor-pointer">
                    <?php echo $is_edit ? "Simpan Perubahan" : "Simpan Pegawai"; ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
    // --- Profile Photo Handling ---
    function previewImage(input) {
        const preview = document.getElementById('profile_preview');
        const placeholder = document.getElementById('photo_placeholder');
        const removeBtn = document.getElementById('remove_photo_btn');
        const removeInput = document.getElementById('remove_photo_input');

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
                removeBtn.classList.remove('hidden');
                removeInput.value = "0";
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeImage() {
        const input = document.getElementById('profile_photo_input');
        const preview = document.getElementById('profile_preview');
        const placeholder = document.getElementById('photo_placeholder');
        const removeBtn = document.getElementById('remove_photo_btn');
        const removeInput = document.getElementById('remove_photo_input');

        input.value = "";
        preview.src = "";
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
        removeBtn.classList.add('hidden');
        removeInput.value = "1";
    }

    // --- Data from PHP ---
    const allUnits = <?php echo json_encode($units); ?>;
    const currentUnitId = "<?php echo (string)($employee['unit_id'] ?? ''); ?>";
    const divisionSelect = document.getElementById('division_id');
    const unitSelect = document.getElementById('unit_id');

    function updateUnitOptions(divisionId, selectedUnitId = null) {
        if (!unitSelect) return;
        const targetUnit = selectedUnitId !== null ? selectedUnitId : unitSelect.value;
        unitSelect.innerHTML = '<option value="">Langsung di bawah Bidang (Tanpa Unit)</option>';

        const filtered = divisionId ? allUnits.filter(u => String(u.division_id) === String(divisionId)) : allUnits;
        filtered.forEach(unit => {
            const opt = document.createElement('option');
            opt.value = unit.id;
            opt.textContent = unit.name;
            if (targetUnit && String(unit.id) === String(targetUnit)) {
                opt.selected = true;
            }
            unitSelect.appendChild(opt);
        });
    }

    if (divisionSelect) {
        divisionSelect.addEventListener('change', function () {
            updateUnitOptions(this.value, '');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const initialDivId = divisionSelect ? divisionSelect.value : '';
        updateUnitOptions(initialDivId, currentUnitId);
    });
</script>

<?php include '../layouts/footer.php'; ?>