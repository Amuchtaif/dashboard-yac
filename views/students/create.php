<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Tambah Siswa Baru";

$db = new Database();
$conn = $db->getConnection();

// --- Fetch Logged-in User Info for Role-based Scoping ---
$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');
$user_stmt = $conn->prepare("
    SELECT e.unit_id, p.level, u.name as unit_name
    FROM employees e 
    LEFT JOIN positions p ON e.position_id = p.id 
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE e.id = :user_id LIMIT 1
");
$user_stmt->execute([':user_id' => $_SESSION['user_id']]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
$user_level = $user_data ? (int)$user_data['level'] : 5;
$user_unit_name = $user_data ? $user_data['unit_name'] : '';

$mapped_education_unit_ids = [];
if (!empty($user_unit_name)) {
    $clean_unit_name = str_replace(["'", " "], ["", ""], strtolower($user_unit_name));
    $edu_stmt = $conn->query("SELECT id, name FROM education_units");
    while ($edu_row = $edu_stmt->fetch(PDO::FETCH_ASSOC)) {
        $clean_edu_name = str_replace(["'", " "], ["", ""], strtolower($edu_row['name']));
        if (strpos($clean_unit_name, $clean_edu_name) !== false || strpos($clean_edu_name, $clean_unit_name) !== false) {
            $mapped_education_unit_ids[] = (int)$edu_row['id'];
        }
    }
}

// Fetch Grade Levels for Dropdown
$query_grades = "SELECT id, name, education_unit_id FROM grade_levels WHERE is_active = 1";
if (!$is_admin && $user_level > 2 && !empty($mapped_education_unit_ids)) {
    $query_grades .= " AND education_unit_id IN (" . implode(',', $mapped_education_unit_ids) . ")";
}
$query_grades .= " ORDER BY name ASC";
$stmt_grades = $conn->prepare($query_grades);
$stmt_grades->execute();
$grade_levels = $stmt_grades->fetchAll(PDO::FETCH_ASSOC);

// Fetch Education Units for Dropdown
$query_units = "SELECT id, name FROM education_units";
if (!$is_admin && $user_level > 2 && !empty($mapped_education_unit_ids)) {
    $query_units .= " WHERE id IN (" . implode(',', $mapped_education_unit_ids) . ")";
}
$query_units .= " ORDER BY name ASC";
$education_units = $conn->query($query_units)->fetchAll(PDO::FETCH_ASSOC);

// Fetch Academic Years for Dropdown
$academic_years = $conn->query("SELECT id, name, semester FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Academic Year
$active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();

include '../layouts/header.php';
?>

<div class="w-full pb-12">
    <!-- Header Block -->
    <div class="mb-6">
        <!-- Breadcrumb -->
        <nav class="flex mb-3" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-2 text-xs text-slate-500">
                <li>
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 transition-colors">Beranda</a>
                </li>
                <li>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <a href="<?php url('views/students/index.php'); ?>" class="hover:text-slate-800 transition-colors">Siswa</a>
                </li>
                <li aria-current="page">
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-800 font-medium">Tambah Baru</span>
                </li>
            </ol>
        </nav>

        <!-- Judul & Deskripsi -->
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Tambah Siswa Baru</h1>
        <p class="text-slate-500 text-sm mt-1">Lengkapi formulir di bawah ini untuk mendaftarkan siswa baru ke dalam sistem.</p>
    </div>

    <!-- Alert Notifikasi Error Server -->
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-red-500 flex-shrink-0"></i>
            <span><?php echo htmlspecialchars($_GET['error']); ?></span>
        </div>
    <?php endif; ?>

    <form action="<?php url('logic/students/store.php'); ?>" method="POST" enctype="multipart/form-data" class="space-y-6">

        <!-- Master Card Container -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 divide-y divide-slate-200">

            <!-- Section 1: Informasi Pribadi -->
            <div class="p-6 sm:p-8">
                <div class="mb-6">
                    <h2 class="text-base font-semibold text-slate-900">Informasi Pribadi</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Identitas dasar dan kontak siswa.</p>
                </div>

                <!-- Foto Profil Siswa -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-5 p-4 rounded-xl bg-slate-50 border border-slate-200/80 mb-6">
                    <div class="relative w-20 h-20 rounded-full border border-slate-200 shadow-sm overflow-hidden bg-slate-100 flex-shrink-0 flex items-center justify-center">
                        <img id="profile_preview" class="w-full h-full object-cover hidden" src="" alt="Foto Profil">
                        <div id="photo_placeholder" class="flex items-center justify-center w-full h-full text-slate-400">
                            <i class="fa-solid fa-user text-2xl"></i>
                        </div>
                    </div>

                    <div class="flex-1">
                        <div class="text-sm font-semibold text-slate-800 mb-0.5">Foto Profil Siswa</div>
                        <p class="text-xs text-slate-500 mb-3">Format JPG, JPEG, PNG, atau WebP. Maksimal 2MB.</p>
                        <div class="flex items-center gap-2.5">
                            <input type="file" name="foto" id="profile_photo_input" class="hidden"
                                accept="image/*" onchange="previewImage(this)">
                            <input type="hidden" name="remove_photo" id="remove_photo_input" value="0">

                            <button type="button" onclick="document.getElementById('profile_photo_input').click()"
                                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                                <i class="fa-solid fa-arrow-up-from-bracket text-xs text-slate-500"></i>
                                <span>Pilih Foto</span>
                            </button>
                            <button type="button" id="remove_photo_btn" onclick="removeImage()"
                                class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold transition-colors cursor-pointer">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                                <span>Hapus</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Personal Fields Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                    <div>
                        <label for="nama_siswa" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nama Lengkap Siswa <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nama_siswa" id="nama_siswa" required
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
                            placeholder="masukkan nama lengkap">
                    </div>

                    <div>
                        <label for="nomor_induk" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nomor Induk (NISN) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nomor_induk" id="nomor_induk" required
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
                            placeholder="Masukkan 10 digit NISN">
                    </div>

                    <div>
                        <label for="tempat_lahir" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tempat Lahir
                        </label>
                        <input type="text" name="tempat_lahir" id="tempat_lahir"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
                            placeholder="Kota Kelahiran">
                    </div>

                    <div>
                        <label for="tanggal_lahir" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tanggal Lahir
                        </label>
                        <input type="date" name="tanggal_lahir" id="tanggal_lahir"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm text-slate-600">
                    </div>

                    <div class="md:col-span-2">
                        <label for="alamat" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Alamat Lengkap
                        </label>
                        <textarea name="alamat" id="alamat" rows="3"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
                            placeholder="Alamat lengkap tempat tinggal..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: Informasi Akademik -->
            <div class="p-6 sm:p-8">
                <div class="mb-6">
                    <h2 class="text-base font-semibold text-slate-900">Informasi Akademik</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Penetapan tahun ajaran, unit pendidikan, kelas, dan status siswa.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                    <div>
                        <label for="academic_year_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tahun Ajaran <span class="text-red-500">*</span>
                        </label>
                        <select name="academic_year_id" id="academic_year_id" required
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <option value="">Pilih Tahun Ajaran</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?php echo $year['id']; ?>" <?php echo ($year['id'] == $active_year_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="unit_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Unit Pendidikan
                        </label>
                        <select name="unit_id" id="unit_id"
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <option value="">Semua Unit Pendidikan</option>
                            <?php foreach ($education_units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>">
                                    <?php echo htmlspecialchars($unit['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="class_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Kelas <span class="text-red-500">*</span>
                        </label>
                        <select name="class_id" id="class_id" required
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <option value="">Pilih Kelas</option>
                            <?php foreach ($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" data-unit-id="<?php echo $grade['education_unit_id']; ?>">
                                    <?php echo htmlspecialchars($grade['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">Siswa otomatis dimasukkan ke riwayat kelas yang dipilih.</p>
                    </div>

                    <div>
                        <label for="status" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Status Siswa <span class="text-red-500">*</span>
                        </label>
                        <select name="status" id="status" required data-searchable="false"
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <option value="Aktif" selected>Aktif</option>
                            <option value="Non_aktif">Non-aktif</option>
                            <option value="Lulus">Lulus</option>
                            <option value="Pindah">Pindah</option>
                            <option value="Dikeluarkan">Dikeluarkan</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 3: Data Keuangan -->
            <div class="p-6 sm:p-8">
                <div class="mb-6">
                    <h2 class="text-base font-semibold text-slate-900">Data Keuangan</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Informasi besaran SPP dan biaya administrasi daftar ulang.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6">
                    <div>
                        <label for="spp" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Besar SPP Bulanan
                        </label>
                        <div class="flex">
                            <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 text-slate-500 text-sm font-medium">Rp</span>
                            <input type="number" name="spp" id="spp" min="0" step="1000"
                                class="rounded-r-lg border border-slate-300 bg-white text-slate-800 focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none block w-full min-w-0 flex-1 text-sm px-3.5 py-2.5 shadow-sm placeholder:text-slate-400"
                                placeholder="0">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Nominal iuran SPP bulanan siswa.</p>
                    </div>

                    <div>
                        <label for="daftar_ulang" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Biaya Daftar Ulang
                        </label>
                        <div class="flex">
                            <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 text-slate-500 text-sm font-medium">Rp</span>
                            <input type="number" name="daftar_ulang" id="daftar_ulang" min="0" step="1000"
                                class="rounded-r-lg border border-slate-300 bg-white text-slate-800 focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none block w-full min-w-0 flex-1 text-sm px-3.5 py-2.5 shadow-sm placeholder:text-slate-400"
                                placeholder="0">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Biaya daftar ulang / administrasi awal masuk.</p>
                    </div>
                </div>
            </div>

            <!-- Card Footer / Actions -->
            <div class="px-6 sm:px-8 py-4 bg-slate-50 flex items-center justify-end gap-3 rounded-b-xl">
                <a href="<?php url('views/students/index.php'); ?>"
                    class="px-5 py-2.5 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-sm cursor-pointer">
                    Batal
                </a>
                <button type="submit"
                    class="px-6 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-600 cursor-pointer">
                    Simpan Siswa
                </button>
            </div>
        </div>
    </form>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
// --- Profile Photo Handling (Matching views/employees/form.php) ---
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
        };
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

// --- Unit & Grade Dropdown Dynamic Filter ---
document.addEventListener('DOMContentLoaded', function() {
    const unitSelect = document.getElementById('unit_id');
    const classSelect = document.getElementById('class_id');
    if (unitSelect && classSelect) {
        const classOptions = Array.from(classSelect.querySelectorAll('option'));

        unitSelect.addEventListener('change', function() {
            const selectedUnitId = this.value;
            const currentSelectedValue = classSelect.value;
            
            // Clear existing options except the placeholder
            classSelect.innerHTML = '';
            
            // Re-append matching options
            classOptions.forEach(opt => {
                if (opt.value === '') {
                    classSelect.appendChild(opt);
                } else if (!selectedUnitId || opt.dataset.unitId == selectedUnitId) {
                    classSelect.appendChild(opt);
                }
            });
            
            // Restore selection if it's still available in the filtered list
            if (Array.from(classSelect.options).some(opt => opt.value === currentSelectedValue)) {
                classSelect.value = currentSelectedValue;
            } else {
                classSelect.value = '';
            }

            // Dispatch change event so MutationObserver in hybrid-select refreshes
            classSelect.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
});
</script>

<?php include '../layouts/footer.php'; ?>