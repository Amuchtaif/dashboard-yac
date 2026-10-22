<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: index.php?error=ID Siswa tidak valid");
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// 1. Fetch Student Data
$query = "SELECT * FROM students WHERE id = :id LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: index.php?error=Siswa tidak ditemukan");
    exit();
}

// 2. Fetch Active Academic Year
$active_year_query = "SELECT id, name FROM academic_years WHERE is_active = 1 LIMIT 1";
$active_year_stmt = $conn->query($active_year_query);
$active_year = $active_year_stmt->fetch(PDO::FETCH_ASSOC);
$active_year_id = $active_year ? $active_year['id'] : null;

// 3. Fetch Active Class History for this student
$current_class_id = '';
if ($active_year_id) {
    $hist_query = "SELECT class_id FROM student_class_history WHERE student_id = :sid AND academic_year_id = :ayid LIMIT 1";
    $hist_stmt = $conn->prepare($hist_query);
    $hist_stmt->execute([':sid' => $id, ':ayid' => $active_year_id]);
    $history = $hist_stmt->fetch(PDO::FETCH_ASSOC);
    if ($history) {
        $current_class_id = $history['class_id'];
    }
}
$current_class_id_val = $current_class_id ?? 0;

// 4. Role-based Scoping Check
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

if (!$is_admin && $user_level > 2 && !empty($mapped_education_unit_ids)) {
    $student_unit_check = $conn->prepare("
        SELECT gl.education_unit_id, s.tingkat 
        FROM students s
        LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id AND sch.status = 'ACTIVE'
        LEFT JOIN grade_levels gl ON sch.class_id = gl.id
        WHERE s.id = :id LIMIT 1
    ");
    $student_unit_check->execute([':id' => $id, ':active_year_id' => $active_year_id]);
    $student_unit_data = $student_unit_check->fetch(PDO::FETCH_ASSOC);
    
    $student_edu_unit_id = $student_unit_data ? $student_unit_data['education_unit_id'] : null;
    $student_tingkat = $student_unit_data ? $student_unit_data['tingkat'] : '';
    
    $edu_names_stmt = $conn->prepare("SELECT name FROM education_units WHERE id IN (" . implode(',', $mapped_education_unit_ids) . ")");
    $edu_names_stmt->execute();
    $edu_names = $edu_names_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $allowed = false;
    if ($student_edu_unit_id && in_array((int)$student_edu_unit_id, $mapped_education_unit_ids)) {
        $allowed = true;
    } else if ($student_tingkat && in_array($student_tingkat, $edu_names)) {
        $allowed = true;
    }
    
    if (!$allowed) {
        header("Location: index.php?error=Akses+ditolak+Siswa+di+luar+unit+kerja+Anda");
        exit();
    }
}

// 5. Fetch Grade Levels
$query_grades = "SELECT id, name, education_unit_id FROM grade_levels WHERE (is_active = 1 OR id = :curr_cid)";
if (!$is_admin && $user_level > 2 && !empty($mapped_education_unit_ids)) {
    $query_grades .= " AND education_unit_id IN (" . implode(',', $mapped_education_unit_ids) . ")";
}
$query_grades .= " ORDER BY name ASC";
$stmt_grades = $conn->prepare($query_grades);
$stmt_grades->execute([':curr_cid' => $current_class_id_val]);
$grade_levels = $stmt_grades->fetchAll(PDO::FETCH_ASSOC);

// 6. Fetch Education Units
$query_units = "SELECT id, name FROM education_units";
if (!$is_admin && $user_level > 2 && !empty($mapped_education_unit_ids)) {
    $query_units .= " WHERE id IN (" . implode(',', $mapped_education_unit_ids) . ")";
}
$query_units .= " ORDER BY name ASC";
$education_units = $conn->query($query_units)->fetchAll(PDO::FETCH_ASSOC);

// Determine current unit_id based on current_class_id
$current_unit_id = '';
if ($current_class_id) {
    foreach ($grade_levels as $gl) {
        if ($gl['id'] == $current_class_id) {
            $current_unit_id = $gl['education_unit_id'];
            break;
        }
    }
}

// Existing Photo Check
$existingAvatar = null;
if (!empty($student['foto']) && file_exists("../../uploads/students/" . $student['foto'])) {
    $existingAvatar = "../../uploads/students/" . $student['foto'];
}

$page_title = "Edit Data Siswa";
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
                    <a href="<?php url('views/students/index.php'); ?>" class="hover:text-slate-800 transition-colors">Data Siswa</a>
                </li>
                <li aria-current="page">
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-800 font-medium">Edit Siswa</span>
                </li>
            </ol>
        </nav>

        <!-- Judul & Deskripsi -->
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Edit Data Siswa</h1>
        <p class="text-slate-500 text-sm mt-1">Perbarui profil siswa, penempatan kelas tahun ajaran aktif, dan administrasi keuangan.</p>
    </div>

    <!-- Alert Notifikasi Error Server -->
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-red-500 flex-shrink-0"></i>
            <span><?php echo htmlspecialchars($_GET['error']); ?></span>
        </div>
    <?php endif; ?>

    <form action="<?php url('logic/students/update.php'); ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($student['id']); ?>">
        <input type="hidden" name="active_year_id" value="<?php echo $active_year_id; ?>">

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
                        <img id="profile_preview" class="w-full h-full object-cover <?php echo $existingAvatar ? '' : 'hidden'; ?>" 
                             src="<?php echo $existingAvatar ? htmlspecialchars($existingAvatar) : ''; ?>" alt="Foto Profil">
                        <div id="photo_placeholder" class="flex items-center justify-center w-full h-full text-slate-400 <?php echo $existingAvatar ? 'hidden' : ''; ?>">
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
                                <span><?php echo $existingAvatar ? 'Ganti Foto' : 'Pilih Foto'; ?></span>
                            </button>
                            <button type="button" id="remove_photo_btn" onclick="removeImage()"
                                class="<?php echo $existingAvatar ? '' : 'hidden'; ?> inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold transition-colors cursor-pointer">
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
                            value="<?php echo htmlspecialchars($student['nama_siswa'] ?? ''); ?>"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400 capitalize"
                            placeholder="masukkan nama lengkap">
                    </div>

                    <div>
                        <label for="nomor_induk" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nomor Induk (NISN) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nomor_induk" id="nomor_induk" required
                            value="<?php echo htmlspecialchars($student['nomor_induk'] ?? ''); ?>"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
                            placeholder="Masukkan 10 digit NISN">
                    </div>

                    <div>
                        <label for="tempat_lahir" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tempat Lahir
                        </label>
                        <input type="text" name="tempat_lahir" id="tempat_lahir"
                            value="<?php echo htmlspecialchars($student['tempat_lahir'] ?? ''); ?>"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
                            placeholder="Kota Kelahiran">
                    </div>

                    <div>
                        <label for="tanggal_lahir" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tanggal Lahir
                        </label>
                        <input type="date" name="tanggal_lahir" id="tanggal_lahir"
                            value="<?php echo htmlspecialchars($student['tanggal_lahir'] ?? ''); ?>"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm text-slate-600">
                    </div>

                    <div class="md:col-span-2">
                        <label for="alamat" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Alamat Lengkap
                        </label>
                        <textarea name="alamat" id="alamat" rows="3"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm placeholder:text-slate-400"
                            placeholder="Alamat lengkap tempat tinggal..."><?php echo htmlspecialchars($student['alamat'] ?? ''); ?></textarea>
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
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tahun Ajaran Aktif
                        </label>
                        <input type="text" disabled 
                            value="<?php echo htmlspecialchars($active_year['name'] ?? $student['tahun_ajaran'] ?? '-'); ?>"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-600 text-sm shadow-sm cursor-not-allowed">
                        <p class="text-[11px] text-slate-400 mt-1">Penempatan kelas disinkronkan dengan tahun ajaran aktif.</p>
                    </div>

                    <div>
                        <label for="unit_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Unit Pendidikan
                        </label>
                        <select name="unit_id" id="unit_id"
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <option value="">Semua Unit Pendidikan</option>
                            <?php foreach ($education_units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>" <?php echo ($unit['id'] == $current_unit_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($unit['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="class_id" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Kelas (Tahun Ajaran Aktif)
                        </label>
                        <?php if ($active_year_id): ?>
                            <select name="class_id" id="class_id"
                                class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($grade_levels as $grade): ?>
                                    <option value="<?php echo $grade['id']; ?>" data-unit-id="<?php echo $grade['education_unit_id']; ?>" <?php echo ($grade['id'] == $current_class_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($grade['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1">Memperbarui penempatan kelas siswa di tahun ajaran aktif.</p>
                        <?php else: ?>
                            <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-xs">
                                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
                                Tidak ada tahun ajaran aktif. Aktifkan tahun ajaran terlebih dahulu.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="status" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Status Siswa <span class="text-red-500">*</span>
                        </label>
                        <select name="status" id="status" required data-searchable="false"
                            class="hybrid-select w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm">
                            <?php 
                            $curr_status = $student['status'] ?? 'Aktif';
                            $statuses = ['Aktif', 'Non_aktif', 'Lulus', 'Pindah', 'Dikeluarkan'];
                            foreach ($statuses as $st): 
                            ?>
                                <option value="<?php echo $st; ?>" <?php echo ($curr_status === $st) ? 'selected' : ''; ?>>
                                    <?php echo str_replace('_', ' ', $st); ?>
                                </option>
                            <?php endforeach; ?>
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
                                value="<?php echo htmlspecialchars($student['spp'] ?? 0); ?>"
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
                                value="<?php echo htmlspecialchars($student['daftar_ulang'] ?? 0); ?>"
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
                    Simpan Perubahan
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
            
            // Clear existing options except placeholder
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
