<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch RPP Detail
$stmt = $conn->prepare("SELECT * FROM rpp WHERE id = ?");
$stmt->execute([$id]);
$rpp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rpp) {
    header("Location: index.php");
    exit;
}

$page_title = "Edit RPP";

// Fetch all employees for manual selection
$employees = $conn->query("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get baseline academic years
$academic_years = $conn->query("SELECT id, name, semester FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Breadcrumb & Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
                <li><a href="index.php" class="hover:text-cyan-600 transition-colors">Daftar RPP</a></li>
                <li class="flex items-center">
                    <svg class="w-3 h-3 mx-1 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"></path></svg>
                    <span class="font-medium text-slate-800">Edit RPP</span>
                </li>
            </ol>
        </nav>
    </div>

    <form id="rppForm" class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <input type="hidden" name="id" value="<?php echo $rpp['id']; ?>">
        
        <!-- Sidebar: Identitas -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 border-b pb-2">Identitas RPP</h3>
                
                <div class="space-y-4">
                    <!-- Hybrid Select: Nama Guru -->
                    <div class="relative" id="teacher-select-container">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Guru</label>
                        <input type="hidden" name="employee_id" id="teacher_select_input" value="<?php echo $rpp['employee_id']; ?>" required>
                        <button type="button" onclick="toggleTeacherDropdown()" id="teacher_select_btn"
                            class="inline-flex items-center justify-between w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 transition-all shadow-sm">
                            <?php 
                                $teacher_name = "-- Pilih Guru --";
                                foreach($employees as $e) {
                                    if($e['id'] == $rpp['employee_id']) {
                                        $teacher_name = $e['full_name'];
                                        break;
                                    }
                                }
                            ?>
                            <span id="teacher_selected_label"><?php echo htmlspecialchars($teacher_name); ?></span>
                            <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="teacher-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        
                        <div id="teacher-dropdown-menu" class="hidden absolute top-full left-0 mt-1 w-full z-50 rounded-xl bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none overflow-hidden border border-slate-100">
                            <div class="p-2 border-b border-slate-50">
                                <input type="text" id="teacher-search" onkeyup="filterTeachers()" placeholder="Cari nama guru..." 
                                    class="block w-full rounded-lg border-0 bg-slate-100 px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-cyan-500 outline-none">
                            </div>
                            <ul class="py-1 max-h-60 overflow-y-auto" id="teacher-list">
                                <li onclick="selectTeacher('', '-- Pilih Guru --')" class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50">
                                    -- Pilih Guru --
                                </li>
                                <?php foreach($employees as $e): ?>
                                    <li onclick="selectTeacher('<?php echo $e['id']; ?>', '<?php echo htmlspecialchars(addslashes($e['full_name']), ENT_QUOTES); ?>')" 
                                        class="teacher-item cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        <?php echo htmlspecialchars($e['full_name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tahun Ajaran</label>
                            <select name="academic_year_id" id="academic_year_select" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:ring-2 focus:ring-cyan-500/20 outline-none transition-all appearance-none bg-white cursor-pointer hover:border-slate-300">
                                <?php foreach($academic_years as $ay): ?>
                                    <option value="<?php echo $ay['id']; ?>" <?php echo $ay['id'] == $rpp['academic_year_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ay['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Semester</label>
                            <select name="semester" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:ring-2 focus:ring-cyan-500/20 outline-none transition-all appearance-none bg-white cursor-pointer hover:border-slate-300">
                                <option value="Ganjil" <?php echo $rpp['semester'] == 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                                <option value="Genap" <?php echo $rpp['semester'] == 'Genap' ? 'selected' : ''; ?>>Genap</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-4 pt-2 border-t border-slate-50">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Jenjang Pendidikan</label>
                            <select name="education_unit_id" id="unit_select" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:ring-2 focus:ring-cyan-500/20 outline-none transition-all bg-white appearance-none cursor-pointer hover:border-slate-300">
                                <option value="">-- Loading... --</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kelas</label>
                            <select name="grade_level_id" id="class_select" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:ring-2 focus:ring-cyan-500/20 outline-none transition-all appearance-none cursor-pointer hover:border-slate-300">
                                <option value="">-- Pilih Kelas --</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Mata Pelajaran</label>
                            <select name="subject_id" id="subject_select" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:ring-2 focus:ring-cyan-500/20 outline-none transition-all appearance-none cursor-pointer hover:border-slate-300">
                                <option value="">-- Pilih Mapel --</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-50">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Pertemuan Ke</label>
                            <input type="text" name="session_no" value="<?php echo htmlspecialchars($rpp['session_no']); ?>" placeholder="Contoh: 1" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 transition-all shadow-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Alokasi Waktu</label>
                            <input type="text" name="allocation" value="<?php echo htmlspecialchars($rpp['allocation']); ?>" placeholder="Contoh: 2x45 Menit" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 transition-all shadow-sm">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-6">
                <p class="text-[11px] text-indigo-700 leading-relaxed font-medium">
                    <span class="font-bold">Info:</span> Anda sedang mengubah dokumen RPP yang sudah ada. Perubahan akan disimpan sebagai versi terbaru.
                </p>
            </div>
        </div>

        <!-- Main Content: Detailed Editor -->
        <div class="lg:col-span-3 space-y-6">
            <div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm space-y-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-5 pointer-events-none">
                    <svg class="h-40 w-40" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 4h7v5h5v11H6V4zm2 8v2h8v-2H8zm0 4v2h5v-2H8z"/></svg>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Judul RPP / Pokok Bahasan</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($rpp['title']); ?>" required placeholder="Contoh: Penerapan Hukum Newton dalam Kehidupan" 
                        class="w-full text-xl font-black border-b-2 border-slate-100 focus:border-cyan-500 outline-none py-2 transition-all placeholder:text-slate-200 text-slate-800">
                </div>

                <!-- Structured Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 pt-4">
                    <div class="space-y-4">
                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">A</span> Standar Kompetensi
                            </label>
                            <textarea name="content_sk" rows="2" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"><?php echo htmlspecialchars($rpp['content_sk']); ?></textarea>
                        </section>

                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">B</span> Kompetensi Dasar
                            </label>
                            <textarea name="content_kd" rows="2" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"><?php echo htmlspecialchars($rpp['content_kd']); ?></textarea>
                        </section>

                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">C</span> Indikator
                            </label>
                            <textarea name="content_indicator" rows="2" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"><?php echo htmlspecialchars($rpp['content_indicator']); ?></textarea>
                        </section>
                        
                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">D</span> Tujuan Pembelajaran
                            </label>
                            <textarea name="learning_goal" rows="4" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"><?php echo htmlspecialchars($rpp['learning_goal']); ?></textarea>
                        </section>
                    </div>

                    <div class="space-y-4">
                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">E</span> Materi Ajar
                            </label>
                            <textarea name="teaching_material" rows="2" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"><?php echo htmlspecialchars($rpp['teaching_material']); ?></textarea>
                        </section>

                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">F</span> Metode Pembelajaran
                            </label>
                            <textarea name="teaching_method" rows="2" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"><?php echo htmlspecialchars($rpp['teaching_method']); ?></textarea>
                        </section>

                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">G</span> Langkah Pembelajaran
                            </label>
                            <textarea name="content_steps" rows="6" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"><?php echo htmlspecialchars($rpp['content_steps']); ?></textarea>
                        </section>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4 border-t border-slate-100 pt-6">
                    <section>
                        <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">H</span> Alat & Sumber Belajar
                        </label>
                        <textarea name="content_summary" rows="3" class="w-full rounded-2xl border border-slate-100 bg-indigo-50/30 px-4 py-3 text-sm focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-300 outline-none transition-all resize-none"><?php echo htmlspecialchars($rpp['content_summary']); ?></textarea>
                    </section>
                    <section>
                        <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">I</span> Penilaian
                        </label>
                        <textarea name="assessment" rows="3" class="w-full rounded-2xl border border-slate-100 bg-green-50/30 px-4 py-3 text-sm focus:ring-4 focus:ring-green-500/5 focus:border-green-300 outline-none transition-all resize-none"><?php echo htmlspecialchars($rpp['assessment']); ?></textarea>
                    </section>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-6">
                    <a href="index.php" class="px-6 py-3 rounded-2xl border border-slate-200 text-sm font-bold text-slate-500 hover:bg-slate-50 transition-all">
                        Batal
                    </a>
                    <a href="<?php url('views/rpp/print.php?id=' . $rpp['id']); ?>" target="_blank" class="px-6 py-3 rounded-2xl border border-indigo-200 text-sm font-bold text-indigo-600 hover:bg-indigo-50 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Cetak RPP
                    </a>
                    <button type="button" onclick="submitRPP(1)" class="px-6 py-3 rounded-2xl border border-amber-200 text-sm font-bold text-amber-600 hover:bg-amber-50 transition-all">
                        Kembalikan ke Draft
                    </button>
                    <button type="button" onclick="submitRPP(0)" class="px-10 py-3 rounded-2xl bg-cyan-600 text-sm font-bold text-white hover:bg-cyan-700 shadow-xl shadow-cyan-600/20 active:scale-95 transition-all">
                        Update & Terbitkan
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let teacherSchedule = { units: [], classes: [], subjects: [] };
const initialData = {
    unit_id: '<?php echo $rpp['education_unit_id']; ?>',
    class_id: '<?php echo $rpp['grade_level_id']; ?>',
    subject_id: '<?php echo $rpp['subject_id']; ?>'
};

document.addEventListener('DOMContentLoaded', async () => {
    // Initial load for the selected teacher
    await loadTeacherSchedules(<?php echo $rpp['employee_id']; ?>, true);
});

async function loadTeacherSchedules(employeeId, isInitial = false) {
    if(!employeeId) {
        teacherSchedule = { units: [], classes: [], subjects: [] };
        resetSelections();
        return;
    }

    const unitSelect = document.getElementById('unit_select');
    if(!isInitial) {
        unitSelect.innerHTML = '<option value="">-- Loading... --</option>';
        unitSelect.classList.add('bg-slate-50');
    }

    try {
        const resp = await fetch(`../../api/rpp/get_teacher_data.php?employee_id=${employeeId}`);
        const result = await resp.json();
        
        if(result.status === 'success') {
            teacherSchedule = result.data;
            populateUnits(isInitial);
        } else {
            resetSelections();
        }
    } catch(e) {
        console.error(e);
        resetSelections();
    }
}

function toggleTeacherDropdown() {
    const menu = document.getElementById('teacher-dropdown-menu');
    const arrow = document.getElementById('teacher-arrow');
    const isHidden = menu.classList.contains('hidden');
    
    if(isHidden) {
        menu.classList.remove('hidden');
        arrow.classList.add('rotate-180');
        document.getElementById('teacher-search').focus();
    } else {
        menu.classList.add('hidden');
        arrow.classList.remove('rotate-180');
    }
}

function filterTeachers() {
    const search = document.getElementById('teacher-search').value.toLowerCase();
    const items = document.querySelectorAll('.teacher-item');
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(search) ? 'block' : 'none';
    });
}

function selectTeacher(id, name) {
    document.getElementById('teacher_select_input').value = id;
    document.getElementById('teacher_selected_label').textContent = name;
    toggleTeacherDropdown();
    loadTeacherSchedules(id);
}

function resetSelections() {
    document.getElementById('unit_select').innerHTML = '<option value="">-- Pilih Unit --</option>';
    document.getElementById('class_select').innerHTML = '<option value="">-- Pilih Kelas --</option>';
    document.getElementById('subject_select').innerHTML = '<option value="">-- Pilih Mapel --</option>';
}

function populateUnits(isInitial = false) {
    const unitSelect = document.getElementById('unit_select');
    unitSelect.innerHTML = '<option value="">-- Pilih Unit --</option>';
    teacherSchedule.units.forEach(u => {
        const selected = (isInitial && u.id == initialData.unit_id) ? 'selected' : '';
        unitSelect.innerHTML += `<option value="${u.id}" ${selected}>${u.name}</option>`;
    });
    unitSelect.classList.remove('bg-slate-50');
    
    if(isInitial) {
        populateClasses(initialData.unit_id, true);
    }
}

function populateClasses(unitId, isInitial = false) {
    const classSelect = document.getElementById('class_select');
    classSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>';
    teacherSchedule.classes.filter(c => c.education_unit_id == unitId).forEach(c => {
        const selected = (isInitial && c.id == initialData.class_id) ? 'selected' : '';
        classSelect.innerHTML += `<option value="${c.id}" ${selected}>${c.name}</option>`;
    });

    if(isInitial) {
        populateSubjects(initialData.class_id, true);
    }
}

function populateSubjects(classId, isInitial = false) {
    const subSelect = document.getElementById('subject_select');
    subSelect.innerHTML = '<option value="">-- Pilih Mapel --</option>';
    teacherSchedule.subjects.filter(s => s.grade_level_id == classId).forEach(s => {
        const selected = (isInitial && s.id == initialData.subject_id) ? 'selected' : '';
        subSelect.innerHTML += `<option value="${s.id}" ${selected}>${s.name}</option>`;
    });
}

document.getElementById('unit_select').addEventListener('change', (e) => populateClasses(e.target.value));
document.getElementById('class_select').addEventListener('change', (e) => populateSubjects(e.target.value));

async function submitRPP(isDraft) {
    const form = document.getElementById('rppForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    data.is_draft = isDraft;

    try {
        const response = await fetch('../../api/rpp/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        if(result.success) {
            window.location.href = 'index.php?success=' + encodeURIComponent(result.message);
        } else {
            alert('Gagal: ' + result.message);
        }
    } catch (e) {
        alert('Terjadi kesalahan sistem.');
    }
}
</script>

<?php include '../layouts/footer.php'; ?>
