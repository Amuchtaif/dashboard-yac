<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id) {
    header("Location: index.php?error=ID Siswa tidak valid");
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Fetch Student Data
$query = "SELECT * FROM students WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->execute([':id' => $id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: index.php?error=Siswa tidak ditemukan");
    exit();
}

// Fetch Active Class History for this student (to pre-select class)
// We need to know which academic year is active or just show the latest one?
// Usually edit shows the *current* state.
// Fetch Active Academic Year first
$active_year_query = "SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1";
$active_year_stmt = $conn->query($active_year_query);
$active_year = $active_year_stmt->fetch(PDO::FETCH_ASSOC);
$active_year_id = $active_year ? $active_year['id'] : null;

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

if (!$is_admin && $user_level > 2 && !empty($mapped_education_unit_ids)) {
    // Check student's unit/class
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

// Fetch Grade Levels for Dropdown (only active classes or student's current class)
$query_grades = "SELECT id, name, education_unit_id FROM grade_levels WHERE (is_active = 1 OR id = :curr_cid)";
if (!$is_admin && $user_level > 2 && !empty($mapped_education_unit_ids)) {
    $query_grades .= " AND education_unit_id IN (" . implode(',', $mapped_education_unit_ids) . ")";
}
$query_grades .= " ORDER BY name ASC";
$stmt_grades = $conn->prepare($query_grades);
$stmt_grades->execute([':curr_cid' => $current_class_id_val]);
$grade_levels = $stmt_grades->fetchAll(PDO::FETCH_ASSOC);

// Fetch Education Units for Dropdown
$query_units = "SELECT id, name FROM education_units";
if (!$is_admin && $user_level > 2 && !empty($mapped_education_unit_ids)) {
    $query_units .= " WHERE id IN (" . implode(',', $mapped_education_unit_ids) . ")";
}
$query_units .= " ORDER BY name ASC";
$education_units = $conn->query($query_units)->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Edit Data Siswa";
include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="inline-flex items-center text-slate-500 hover:text-slate-700">
                    Beranda
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <a href="<?php url('views/students/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-700">Data Siswa</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 font-medium text-slate-800">Edit Siswa</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Edit Data Siswa</h1>
        <p class="mt-2 text-sm text-slate-600">Perbarui informasi siswa.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="h-1 bg-cyan-500 w-full"></div>

        <form action="<?php url('logic/students/update.php'); ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($student['id']); ?>">
            <input type="hidden" name="active_year_id" value="<?php echo $active_year_id; ?>">

            <!-- Section 1: Identitas Siswa -->
            <div class="p-8 border-b border-slate-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                        <i class="fa-solid fa-circle-user w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 uppercase">Identitas Siswa</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="nama_siswa" class="block text-sm font-semibold text-slate-700 mb-1">Nama Lengkap
                            Siswa</label>
                        <input type="text" name="nama_siswa" id="nama_siswa" required
                            value="<?php echo htmlspecialchars($student['nama_siswa']); ?>"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400 capitalize"
                            oninput="this.value = this.value.replace(/\b\w/g, c => c.toUpperCase())">
                    </div>
                    <div>
                        <label for="nomor_induk" class="block text-sm font-semibold text-slate-700 mb-1">Nomor Induk
                            (NISN)</label>
                        <input type="text" name="nomor_induk" id="nomor_induk" required
                            value="<?php echo htmlspecialchars($student['nomor_induk']); ?>"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400">
                    </div>

                    <div>
                        <label for="tempat_lahir" class="block text-sm font-semibold text-slate-700 mb-1">Tempat
                            Lahir</label>
                        <input type="text" name="tempat_lahir" id="tempat_lahir"
                            value="<?php echo htmlspecialchars($student['tempat_lahir'] ?? ''); ?>"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400">
                    </div>
                    <div>
                        <label for="tanggal_lahir" class="block text-sm font-semibold text-slate-700 mb-1">Tanggal
                            Lahir</label>
                        <input type="date" name="tanggal_lahir" id="tanggal_lahir"
                            value="<?php echo htmlspecialchars($student['tanggal_lahir'] ?? ''); ?>"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-slate-600">
                    </div>

                    <div class="md:col-span-2">
                        <label for="alamat" class="block text-sm font-semibold text-slate-700 mb-1">Alamat
                            Lengkap</label>
                        <textarea name="alamat" id="alamat" rows="2"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"><?php echo htmlspecialchars($student['alamat'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: Informasi Akademik -->
            <div class="p-8 border-b border-slate-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                        <i class="fa-solid fa-graduation-cap w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 uppercase">Informasi Akademik</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                         <?php 
                            // Display Academic Year (Not editable here, usually edited in history or promotion, 
                            // but we can show the string from students table or just the current year context)
                            // Let's show the column value from students table for reference
                         ?>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Tahun Ajaran (Terakhir Update)</label>
                        <input type="text" disabled value="<?php echo htmlspecialchars($student['tahun_ajaran'] ?? '-'); ?>"
                             class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-500 text-sm">
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                        <select name="status" id="status"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all bg-white text-slate-700">
                            <?php foreach(['Aktif', 'Non_aktif', 'Lulus', 'Pindah', 'Dikeluarkan'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo ($student['status'] == $st) ? 'selected' : ''; ?>>
                                    <?php echo str_replace('_', ' ', $st); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="unit_id" class="block text-sm font-semibold text-slate-700 mb-1">Unit Pendidikan</label>
                        <select name="unit_id" id="unit_id"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all bg-white text-slate-700">
                            <option value="">Semua Unit</option>
                            <?php foreach ($education_units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>">
                                    <?php echo htmlspecialchars($unit['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="class_id" class="block text-sm font-semibold text-slate-700 mb-1">Kelas (Tahun Ajaran Aktif)</label>
                        <?php if($active_year_id): ?>
                            <select name="class_id" id="class_id"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all bg-white text-slate-700">
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($grade_levels as $grade): ?>
                                    <option value="<?php echo $grade['id']; ?>" data-unit-id="<?php echo $grade['education_unit_id']; ?>" <?php echo ($grade['id'] == $current_class_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($grade['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-[10px] text-slate-500 mt-1">Memperbarui penempatan kelas siswa di tahun ajaran aktif.</p>
                        <?php else: ?>
                            <p class="text-red-500 text-sm">Tahun ajaran tidak aktif. Silakan aktifkan tahun ajaran terlebih dahulu.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section 3: Data Keuangan & Foto -->
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Left: Data Keuangan -->
                    <div>
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                                <i class="fa-solid fa-credit-card w-5 h-5"></i>
                            </div>
                            <h3 class="text-base font-bold text-slate-800 uppercase">Data Keuangan</h3>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label for="spp" class="block text-sm font-semibold text-slate-700 mb-1">Besar SPP
                                    Bulanan</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-slate-500 text-sm">Rp</span>
                                    </div>
                                    <input type="number" name="spp" id="spp"
                                        value="<?php echo htmlspecialchars($student['spp'] ?? 0); ?>"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400">
                                </div>
                            </div>
                            <div>
                                <label for="daftar_ulang" class="block text-sm font-semibold text-slate-700 mb-1">Biaya
                                    Daftar Ulang</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-slate-500 text-sm">Rp</span>
                                    </div>
                                    <input type="number" name="daftar_ulang" id="daftar_ulang"
                                        value="<?php echo htmlspecialchars($student['daftar_ulang'] ?? 0); ?>"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Unggah Foto -->
                    <div>
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                                <i class="fa-solid fa-user-gear w-5 h-5"></i>
                            </div>
                            <h3 class="text-base font-bold text-slate-800 uppercase">Foto Profil</h3>
                        </div>

                        <?php if(!empty($student['foto']) && file_exists("../../uploads/students/" . $student['foto'])): ?>
                            <div class="mb-4 text-center">
                                <img src="../../uploads/students/<?php echo htmlspecialchars($student['foto']); ?>" 
                                     alt="Current Photo" class="h-32 w-32 object-cover rounded-full mx-auto border-2 border-slate-200">
                                <p class="text-xs text-slate-500 mt-2">Foto saat ini</p>
                            </div>
                        <?php endif; ?>

                        <label for="foto"
                            class="block w-full h-[140px] border-2 border-dashed border-slate-300 rounded-lg bg-slate-50 hover:bg-slate-100 hover:border-cyan-500 transition-all cursor-pointer flex flex-col items-center justify-center text-center p-6 group">
                            <div
                                class="p-3 bg-white rounded-full shadow-sm mb-3 group-hover:scale-110 transition-transform">
                                <i class="fa-solid fa-download w-6 h-6 text-cyan-600"></i>
                            </div>
                            <p class="text-sm font-medium text-slate-700">Klik untuk ganti foto</p>
                            <p class="text-xs text-slate-400 mt-1">Biarkan kosong jika tidak ingin mengubah</p>
                            <input type="file" name="foto" id="foto" class="hidden">
                        </label>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-x-6 border-t border-slate-100 px-8 py-6">
                <a href="<?php url('views/students/index.php'); ?>"
                    class="text-sm font-semibold leading-6 text-gray-700 hover:text-gray-900">Batalkan</a>
                <button type="submit"
                    class="rounded-lg bg-cyan-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                    Simpan Perubahan
                </button>
            </div>

        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const unitSelect = document.getElementById('unit_id');
    const classSelect = document.getElementById('class_id');
    if (unitSelect && classSelect) {
        const classOptions = Array.from(classSelect.querySelectorAll('option'));

        // Pre-select Unit if a class is already selected
        const selectedClassOption = classSelect.querySelector('option[selected]');
        if (selectedClassOption && selectedClassOption.dataset.unitId) {
            unitSelect.value = selectedClassOption.dataset.unitId;
            // Filter class options immediately
            filterClasses(selectedClassOption.dataset.unitId);
        }

        unitSelect.addEventListener('change', function() {
            filterClasses(this.value);
        });

        function filterClasses(selectedUnitId) {
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
        }
    }
});
</script>

<?php include '../layouts/footer.php'; ?>
