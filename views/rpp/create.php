<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$page_title = "Buat RPP Baru";
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
                    <span class="font-medium text-slate-800">Tambah Baru</span>
                </li>
            </ol>
        </nav>
    </div>

    <form id="rppForm" class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar: Identitas -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 border-b pb-2">Identitas RPP</h3>
                
                <div class="space-y-4">
                    <!-- Hybrid Select: Nama Guru -->
                    <div class="relative" id="teacher-select-container">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Guru</label>
                        <input type="hidden" name="employee_id" id="teacher_select_input" required>
                        <button type="button" onclick="toggleTeacherDropdown()" id="teacher_select_btn"
                            class="inline-flex items-center justify-between w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 transition-all shadow-sm">
                            <span id="teacher_selected_label">-- Pilih Guru --</span>
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
                                    <option value="<?php echo $ay['id']; ?>"><?php echo htmlspecialchars($ay['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Semester</label>
                            <select name="semester" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:ring-2 focus:ring-cyan-500/20 outline-none transition-all appearance-none bg-white cursor-pointer hover:border-slate-300">
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-4 pt-2 border-t border-slate-50">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Jenjang Pendidikan</label>
                            <select name="education_unit_id" id="unit_select" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:ring-2 focus:ring-cyan-500/20 outline-none transition-all bg-slate-50 appearance-none cursor-pointer hover:border-slate-300">
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
                            <input type="text" name="session_no" placeholder="Contoh: 1" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 transition-all shadow-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Alokasi Waktu</label>
                            <input type="text" name="allocation" placeholder="Contoh: 2x45 Menit" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 transition-all shadow-sm">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-6">
                <p class="text-[11px] text-indigo-700 leading-relaxed font-medium">
                    <span class="font-bold">Info:</span> Dropdown jenjang, kelas, dan mapel diambil otomatis berdasarkan jadwal mengajar Anda di sistem.
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
                    <input type="text" name="title" required placeholder="Contoh: Penerapan Hukum Newton dalam Kehidupan" 
                        class="w-full text-xl font-black border-b-2 border-slate-100 focus:border-cyan-500 outline-none py-2 transition-all placeholder:text-slate-200 text-slate-800">
                </div>

                <!-- Structured Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 pt-4">
                    <div class="space-y-4">
                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">A</span> Capaian Pembelajaran (CP)
                            </label>
                            <textarea name="content_cp" rows="2" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"></textarea>
                        </section>

                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">B</span> Alur Tujuan Pembelajaran (ATP)
                            </label>
                            <textarea name="content_atp" rows="2" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"></textarea>
                        </section>

                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">C</span> Pertanyaan Pemantik
                            </label>
                            <textarea name="content_pertanyaan_pemantik" rows="2" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"></textarea>
                        </section>
                        
                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">D</span> Tujuan Pembelajaran (TP)
                            </label>
                            <textarea name="learning_goal" rows="4" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"></textarea>
                        </section>
                    </div>

                    <div class="space-y-4">
                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">E</span> Materi Ajar
                            </label>
                            <textarea name="teaching_material" rows="2" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"></textarea>
                        </section>

                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">F</span> Profil Pelajar Pancasila
                            </label>
                            <textarea name="teaching_profil_pancasila" rows="2" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none"></textarea>
                        </section>

                        <section>
                            <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">G</span> Kegiatan Pembelajaran
                            </label>
                            <textarea name="content_steps" rows="6" class="w-full rounded-2xl border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm focus:ring-4 focus:ring-cyan-500/5 focus:border-cyan-300 outline-none transition-all resize-none" placeholder="1. Pendahuluan...&#10;2. Inti...&#10;3. Penutup..."></textarea>
                        </section>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4 border-t border-slate-100 pt-6">
                    <section>
                        <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">H</span> Media & Sumber Belajar
                        </label>
                        <textarea name="content_summary" rows="3" class="w-full rounded-2xl border border-slate-100 bg-indigo-50/30 px-4 py-3 text-sm focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-300 outline-none transition-all resize-none"></textarea>
                    </section>
                    <section>
                        <label class="flex items-center text-[10px] font-black text-slate-400 uppercase mb-2">
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded mr-2">I</span> Asesmen
                        </label>
                        <textarea name="assessment" rows="3" class="w-full rounded-2xl border border-slate-100 bg-green-50/30 px-4 py-3 text-sm focus:ring-4 focus:ring-green-500/5 focus:border-green-300 outline-none transition-all resize-none" placeholder="Jenis tagihan, instrumen, dll..."></textarea>
                    </section>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-6">
                    <button type="button" onclick="openSmartPaste()" class="group relative inline-flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-indigo-200 bg-indigo-50/50 px-6 py-3 text-sm font-bold text-indigo-600 hover:border-indigo-400 hover:bg-indigo-50 transition-all active:scale-95">
                        <svg class="w-5 h-5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <span>Smart Paste dari Word</span>
                        <div class="absolute -top-2 -right-2 px-2 py-0.5 bg-indigo-600 text-[8px] text-white rounded-full font-black animate-bounce">BARU</div>
                    </button>
                    <div class="h-10 w-px bg-slate-100 mx-2"></div>
                    <button type="button" onclick="submitRPP(1)" class="px-6 py-3 rounded-2xl border border-slate-200 text-sm font-bold text-slate-500 hover:bg-slate-50 transition-all">
                        Simpan Draft
                    </button>
                    <button type="button" onclick="submitRPP(0)" class="px-10 py-3 rounded-2xl bg-cyan-600 text-sm font-bold text-white hover:bg-cyan-700 shadow-xl shadow-cyan-600/20 active:scale-95 transition-all">
                        Terbitkan RPP Sekarang
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Smart Paste Modal -->
<div id="smartPasteModal" class="fixed inset-0 z-[60] invisible transition-all duration-300 pointer-events-none">
    <div id="smartPasteOverlay" class="absolute inset-0 bg-slate-900/60 backdrop-blur-md opacity-0 transition-opacity duration-300" onclick="closeSmartPaste()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div id="smartPasteContent" class="relative bg-white rounded-[2.5rem] shadow-2xl transform opacity-0 scale-95 transition-all duration-300 max-w-2xl w-full overflow-hidden border border-white/20">
            <div class="p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-600/20">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-slate-800">Smart Paste Parser</h3>
                            <p class="text-xs text-slate-400 font-medium mt-1">Tempel seluruh teks dari Word, sistem akan membagi kolom otomatis.</p>
                        </div>
                    </div>
                    <button onclick="closeSmartPaste()" class="p-2 rounded-xl bg-slate-100 text-slate-400 hover:text-slate-600 transition-all">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="bg-amber-50 border border-amber-100 rounded-2xl p-4 flex gap-3">
                        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <p class="text-[11px] text-amber-700 leading-relaxed">
                            <span class="font-bold">Tips:</span> Pastikan teks di Word menggunakan poin penomoran <span class="font-bold">A, B, C, dst</span> agar sistem dapat mengenali pemisah antar bagian dengan akurat.
                        </p>
                    </div>
                    
                    <textarea id="smartPasteInput" rows="12" 
                        placeholder="Contoh:&#10;A. Capaian Pembelajaran...&#10;B. Alur Tujuan...&#10;C. Pertanyaan Pemantik..." 
                        class="w-full rounded-3xl border-2 border-slate-100 bg-slate-50/50 px-6 py-5 text-sm focus:ring-4 focus:ring-indigo-500/5 focus:border-indigo-500 outline-none transition-all resize-none font-mono placeholder:text-slate-300"></textarea>
                </div>

                <div class="mt-8 flex gap-3">
                    <button onclick="closeSmartPaste()" class="flex-1 px-6 py-4 rounded-2xl border border-slate-200 text-sm font-bold text-slate-500 hover:bg-slate-50 transition-all">
                        Batal
                    </button>
                    <button onclick="processSmartPaste()" class="flex-[2] px-6 py-4 rounded-2xl bg-indigo-600 text-sm font-bold text-white hover:bg-indigo-700 shadow-xl shadow-indigo-600/20 active:scale-95 transition-all flex items-center justify-center gap-2">
                        <span>Proses & Masukkan ke Form</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openSmartPaste() {
    const modal = document.getElementById('smartPasteModal');
    modal.classList.remove('invisible', 'pointer-events-none');
    setTimeout(() => {
        document.getElementById('smartPasteOverlay').classList.remove('opacity-0');
        document.getElementById('smartPasteContent').classList.remove('opacity-0', 'scale-95');
        document.getElementById('smartPasteInput').focus();
    }, 10);
}

function closeSmartPaste() {
    const modal = document.getElementById('smartPasteModal');
    document.getElementById('smartPasteOverlay').classList.add('opacity-0');
    document.getElementById('smartPasteContent').classList.add('opacity-0', 'scale-95');
    setTimeout(() => modal.classList.add('invisible', 'pointer-events-none'), 300);
}

function processSmartPaste() {
    const input = document.getElementById('smartPasteInput').value;
    if(!input.trim()) return alert('Silakan tempel teks RPP terlebih dahulu.');

    // Define markers and their corresponding field names
    const markers = [
        { key: 'A', name: 'content_cp' },
        { key: 'B', name: 'content_atp' },
        { key: 'C', name: 'content_pertanyaan_pemantik' },
        { key: 'D', name: 'learning_goal' },
        { key: 'E', name: 'teaching_material' },
        { key: 'F', name: 'teaching_profil_pancasila' },
        { key: 'G', name: 'content_steps' },
        { key: 'H', name: 'content_summary' },
        { key: 'I', name: 'assessment' }
    ];

    let results = {};
    
    // Improved Parsing Logic: Split by markers at the start of a line
    for (let i = 0; i < markers.length; i++) {
        const current = markers[i];
        const next = markers[i + 1];

        // Regex to find content between markers (e.g., between "A." and "B.")
        // Handles patterns like "A.", "A) ", "A. "
        const startPattern = `^[\\s]*${current.key}[\\.\\)]`;
        const endPattern = next ? `(?=[\\s]*${next.key}[\\.\\)])` : '$';
        
        const regex = new RegExp(`${startPattern}([\\s\\S]*?)${endPattern}`, 'm');
        const match = input.match(regex);

        if (match && match[1]) {
            // Clean up the text: remove redundant leading/trailing spaces and the first header line if it contains the title
            let cleanText = match[1].trim();
            
            // Remove the first line if it's just a repetition of the section title
            const lines = cleanText.split('\n');
            if (lines.length > 1 && lines[0].length < 50) {
                // If the first line is very short, it's likely just the header we already have
                // but we keep it just in case. Optional: logic to detect header titles.
            }
            
            results[current.name] = cleanText;
        }
    }

    // Populate the form fields
    let foundCount = 0;
    Object.keys(results).forEach(fieldName => {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (field) {
            field.value = results[fieldName];
            foundCount++;
        }
    });

    if (foundCount > 0) {
        closeSmartPaste();
        // Optional: notification
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-10 left-1/2 -translate-x-1/2 bg-slate-800 text-white px-6 py-3 rounded-2xl text-sm font-bold shadow-2xl z-[100] animate-bounce';
        toast.textContent = `⚡ Berhasil memetakan ${foundCount} bagian RPP!`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    } else {
        alert('Maaf, sistem tidak menemukan pola penomoran A, B, C... Pastikan teks menggunakan format poin penomoran tersebut.');
    }
}
let teacherSchedule = { units: [], classes: [], subjects: [] };

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Fetch active period defaults only
    const periodResp = await fetch(`../../api/rpp/get_active_period.php`);
    const periodData = await periodResp.json();
    if(periodData.success) {
        document.getElementById('academic_year_select').value = periodData.data.academic_year_id;
        document.querySelector('select[name="semester"]').value = periodData.data.semester;
    }
});

async function loadTeacherSchedules(employeeId) {
    if(!employeeId) {
        teacherSchedule = { units: [], classes: [], subjects: [] };
        resetSelections();
        return;
    }

    const unitSelect = document.getElementById('unit_select');
    unitSelect.innerHTML = '<option value="">-- Loading... --</option>';
    unitSelect.classList.add('bg-slate-50');

    try {
        const resp = await fetch(`../../api/rpp/get_teacher_data.php?employee_id=${employeeId}`);
        const result = await resp.json();
        
        if(result.status === 'success') {
            teacherSchedule = result.data;
            populateUnits();
        } else {
            console.warn('Teacher has no schedules');
            resetSelections();
            // Optional: toast or alert if teacher has 0 schedules
        }
    } catch(e) {
        console.error(e);
        resetSelections();
    }
}

// --- Hybrid Select Logic: Nama Guru ---
function toggleTeacherDropdown() {
    const menu = document.getElementById('teacher-dropdown-menu');
    const arrow = document.getElementById('teacher-arrow');
    const isHidden = menu.classList.contains('hidden');
    
    // Close other dropdowns if any
    document.querySelectorAll('[id$="-dropdown-menu"]').forEach(m => {
        if(m.id !== 'teacher-dropdown-menu') m.classList.add('hidden');
    });

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
    
    // Close dropdown
    toggleTeacherDropdown();
    
    // Trigger schedule load
    loadTeacherSchedules(id);
}

// Close dropdowns on outside click
document.addEventListener('click', (e) => {
    const teacherContainer = document.getElementById('teacher-select-container');
    if (teacherContainer && !teacherContainer.contains(e.target)) {
        document.getElementById('teacher-dropdown-menu').classList.add('hidden');
        document.getElementById('teacher-arrow').classList.remove('rotate-180');
    }
});

function resetSelections() {
    const unitSelect = document.getElementById('unit_select');
    const classSelect = document.getElementById('class_select');
    const subSelect = document.getElementById('subject_select');
    
    unitSelect.innerHTML = '<option value="">-- Pilih Unit --</option>';
    classSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>';
    subSelect.innerHTML = '<option value="">-- Pilih Mapel --</option>';
    unitSelect.classList.remove('bg-slate-50');
}

function populateUnits() {
    const unitSelect = document.getElementById('unit_select');
    unitSelect.innerHTML = '<option value="">-- Pilih Unit --</option>';
    teacherSchedule.units.forEach(u => {
        unitSelect.innerHTML += `<option value="${u.id}">${u.name}</option>`;
    });
    unitSelect.classList.remove('bg-slate-50');
}

// Chain selections
document.getElementById('unit_select').addEventListener('change', (e) => {
    const unitId = e.target.value;
    const classSelect = document.getElementById('class_select');
    classSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>';
    
    teacherSchedule.classes.filter(c => c.education_unit_id == unitId).forEach(c => {
        classSelect.innerHTML += `<option value="${c.id}">${c.name}</option>`;
    });
});

document.getElementById('class_select').addEventListener('change', (e) => {
    const classId = e.target.value;
    const subSelect = document.getElementById('subject_select');
    subSelect.innerHTML = '<option value="">-- Pilih Mapel --</option>';
    
    // Filter subjects by the classes that teacher teaches (from schedules)
    teacherSchedule.subjects.filter(s => s.grade_level_id == classId).forEach(s => {
        subSelect.innerHTML += `<option value="${s.id}">${s.name}</option>`;
    });
});


async function submitRPP(isDraft) {
    const form = document.getElementById('rppForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    data.is_draft = isDraft;

    if(!data.employee_id || !data.title || !data.grade_level_id || !data.subject_id) {
        alert('Mohon lengkapi guru, judul, kelas, dan mata pelajaran.');
        return;
    }

    try {
        const response = await fetch('../../api/rpp/create.php', {
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
