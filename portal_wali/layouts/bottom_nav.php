<?php
// portal_wali/layouts/bottom_nav.php
if (empty($_SESSION['guardian_id'])) {
    return;
}

$page = $current_page ?? 'index';
?>
<!-- Bottom Navigation Bar (Mobile Native Style) -->
<nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-white/95 backdrop-blur-md border-t border-slate-200 z-40 safe-bottom">
    <div class="grid grid-cols-4 h-16">
        <!-- 1. Beranda -->
        <a href="index.php" class="inline-flex flex-col items-center justify-center text-center px-1 transition-colors <?php echo $page === 'index' ? 'text-[#448AFF] font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'; ?>">
            <i class="fa-solid fa-house text-lg mb-1 <?php echo $page === 'index' ? 'text-[#448AFF]' : 'text-slate-400'; ?>"></i>
            <span class="text-[10px]">Beranda</span>
        </a>

        <!-- 2. Tahfidz -->
        <a href="tahfidz.php" class="inline-flex flex-col items-center justify-center text-center px-1 transition-colors <?php echo $page === 'tahfidz' ? 'text-[#448AFF] font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'; ?>">
            <i class="fa-solid fa-book-quran text-lg mb-1 <?php echo $page === 'tahfidz' ? 'text-[#448AFF]' : 'text-slate-400'; ?>"></i>
            <span class="text-[10px]">Tahfidz</span>
        </a>

        <!-- 3. Presensi & Asrama -->
        <a href="presensi.php" class="inline-flex flex-col items-center justify-center text-center px-1 transition-colors <?php echo $page === 'presensi' ? 'text-[#448AFF] font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'; ?>">
            <i class="fa-solid fa-clipboard-check text-lg mb-1 <?php echo $page === 'presensi' ? 'text-[#448AFF]' : 'text-slate-400'; ?>"></i>
            <span class="text-[10px]">Kehadiran</span>
        </a>

        <!-- 4. Profil -->
        <a href="profil.php" class="inline-flex flex-col items-center justify-center text-center px-1 transition-colors <?php echo $page === 'profil' ? 'text-[#448AFF] font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'; ?>">
            <i class="fa-solid fa-user-circle text-lg mb-1 <?php echo $page === 'profil' ? 'text-[#448AFF]' : 'text-slate-400'; ?>"></i>
            <span class="text-[10px]">Akun</span>
        </a>
    </div>
</nav>

<!-- Family / Child Switcher Modal (Bottom Sheet) -->
<div id="childModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-end justify-center hidden opacity-0 transition-opacity duration-300" onclick="closeChildModal(event)">
    <div class="bg-white w-full max-w-md rounded-t-3xl p-5 shadow-2xl transform translate-y-full transition-transform duration-300" onclick="event.stopPropagation()">
        <!-- Handle Bar -->
        <div class="w-12 h-1 bg-slate-300 rounded-full mx-auto mb-4"></div>

        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-800">Pilih Data Santri</h3>
                <p class="text-xs text-slate-500">Beralih informasi antar anak dalam akun Anda</p>
            </div>
            <button type="button" onclick="closeChildModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center hover:bg-slate-200">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- List of Children -->
        <div class="space-y-2.5 max-h-72 overflow-y-auto mb-4 pr-1">
            <?php foreach ($all_children as $child): 
                $isSelected = ((int)$child['student_id'] === (int)($active_child['student_id'] ?? 0));
                $avatarSrc = !empty($child['foto']) && file_exists(__DIR__ . '/../../uploads/students/' . $child['foto']) 
                    ? '../uploads/students/' . $child['foto'] 
                    : null;
            ?>
                <a href="switch_student.php?student_id=<?php echo $child['student_id']; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                   class="flex items-center justify-between p-3.5 rounded-2xl border transition-all <?php echo $isSelected ? 'border-[#448AFF] bg-brand-50/70 shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white'; ?>">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-full border border-slate-200 overflow-hidden bg-slate-100 flex items-center justify-center shrink-0">
                            <?php if ($avatarSrc): ?>
                                <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Foto" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fa-solid fa-user-graduate text-slate-400 text-lg"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($child['nama_siswa']); ?></h4>
                            <p class="text-[11px] text-slate-500">
                                <?php echo htmlspecialchars($child['unit_name'] ?? $child['tingkat'] ?? 'Yayasan Assunnah'); ?> 
                                &bull; Kelas <?php echo htmlspecialchars($child['grade_name'] ?? $child['kelas'] ?? '-'); ?>
                            </p>
                        </div>
                    </div>
                    <?php if ($isSelected): ?>
                        <span class="w-6 h-6 rounded-full bg-[#448AFF] text-white flex items-center justify-center text-xs shadow-xs">
                            <i class="fa-solid fa-check"></i>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <a href="tambah_anak.php" class="w-full py-2.5 rounded-xl border border-dashed border-brand-300 bg-brand-50/50 hover:bg-brand-50 text-brand-700 text-xs font-semibold flex items-center justify-center gap-2 transition-colors">
            <i class="fa-solid fa-user-plus"></i>
            <span>Hubungkan Anak Lain (Klaim NISN)</span>
        </a>
    </div>
</div>

