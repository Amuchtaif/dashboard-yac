<?php
// portal_wali/profil.php
// Halaman Profil & Pengaturan Akun Wali Santri
require_once __DIR__ . '/init.php';
check_guardian_auth();

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM guardians WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $_SESSION['guardian_id']]);
$guardian = $stmt->fetch(PDO::FETCH_ASSOC);

$children = get_guardian_children($_SESSION['guardian_id']);
$active_child = get_active_child();

$page_title = "Profil Akun";
require_once __DIR__ . '/layouts/header.php';
?>

<div class="px-4 pt-4 space-y-4">
    <!-- Header Page Info -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-bold text-slate-900">Akun Wali Santri</h1>
            <p class="text-xs text-slate-500">Kelola profil orang tua & santri terhubung</p>
        </div>
        <div class="w-10 h-10 rounded-2xl bg-brand-50 text-[#448AFF] flex items-center justify-center text-lg shadow-xs">
            <i class="fa-solid fa-user-gear"></i>
        </div>
    </div>

    <!-- Guardian Info Card -->
    <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3.5 pb-4 border-b border-slate-100">
            <div class="w-12 h-12 rounded-2xl bg-[#448AFF] text-white flex items-center justify-center text-xl font-bold shadow-xs">
                <?php echo strtoupper(substr($guardian['full_name'] ?? 'W', 0, 1)); ?>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($guardian['full_name'] ?? 'Wali Santri'); ?></h3>
                <p class="text-xs text-slate-500 flex items-center gap-1.5 mt-0.5">
                    <i class="fa-brands fa-whatsapp text-[#448AFF]"></i>
                    <span><?php echo htmlspecialchars($guardian['phone_number'] ?? ''); ?></span>
                    &bull;
                    <span class="font-semibold text-[#448AFF]"><?php echo htmlspecialchars($guardian['relationship'] ?? 'Wali'); ?></span>
                </p>
            </div>
        </div>

        <div class="pt-3 text-[11px] text-slate-400 flex items-center justify-between">
            <span>Terdaftar Sejak:</span>
            <span class="font-medium text-slate-600"><?php echo date('d M Y', strtotime($guardian['created_at'])); ?></span>
        </div>
    </div>

    <!-- Connected Children Section -->
    <div class="space-y-2.5">
        <div class="flex items-center justify-between px-1">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Santri Terhubung</h3>
            <span class="text-xs font-bold text-brand-700 bg-brand-50 px-2 py-0.5 rounded-full border border-brand-200">
                <?php echo count($children); ?> Santri
            </span>
        </div>

        <div class="space-y-2">
            <?php foreach ($children as $c): 
                $isActive = ((int)$c['student_id'] === (int)($active_child['student_id'] ?? 0));
                $avatarSrc = !empty($c['foto']) && file_exists(__DIR__ . '/../uploads/students/' . $c['foto']) 
                    ? '../uploads/students/' . $c['foto'] 
                    : null;
            ?>
                <div class="bg-white p-3.5 rounded-2xl border <?php echo $isActive ? 'border-[#448AFF] bg-brand-50/30' : 'border-slate-200/80'; ?> shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full border border-slate-200 overflow-hidden bg-slate-100 flex items-center justify-center shrink-0">
                            <?php if ($avatarSrc): ?>
                                <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Foto" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fa-solid fa-user-graduate text-slate-400 text-sm"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($c['nama_siswa']); ?></h4>
                            <p class="text-[10px] text-slate-400">
                                NISN: <?php echo htmlspecialchars($c['nomor_induk'] ?? '-'); ?> &bull; 
                                <?php echo htmlspecialchars($c['unit_name'] ?? $c['tingkat'] ?? ''); ?>
                            </p>
                        </div>
                    </div>

                    <?php if ($isActive): ?>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-[#448AFF] text-white shadow-xs">
                            Aktif
                        </span>
                    <?php else: ?>
                        <a href="switch_student.php?student_id=<?php echo $c['student_id']; ?>&redirect=profil.php"
                           class="px-2.5 py-1 rounded-full text-[10px] font-semibold border border-slate-300 text-slate-600 hover:bg-slate-50">
                            Pilih
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <a href="tambah_anak.php" 
           class="w-full py-2.5 rounded-2xl border border-dashed border-brand-300 bg-brand-50/50 hover:bg-brand-50 text-brand-700 text-xs font-semibold flex items-center justify-center gap-2 transition-colors">
            <i class="fa-solid fa-plus text-xs"></i>
            <span>Hubungkan Santri Baru (Klaim NISN)</span>
        </a>
    </div>

    <!-- Help & Contacts -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs space-y-2.5">
        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Layanan Bantuan Sekolah</h4>
        <div class="text-[11px] text-slate-500 leading-relaxed">
            Jika terdapat ketidaksesuaian data NISN atau ingin berkonsultasi administrasi, silakan hubungi bagian Tata Usaha Yayasan Assunnah Cirebon.
        </div>
        <div class="pt-1">
            <a href="https://wa.me/6281234567890?text=Assalamu'alaikum%20Admin%20Yayasan%20Assunnah" 
               target="_blank"
               class="inline-flex items-center gap-2 text-xs font-bold text-[#448AFF] hover:text-brand-700">
                <i class="fa-brands fa-whatsapp text-sm"></i>
                <span>Hubungi Layanan Pengaduan WhatsApp</span>
            </a>
        </div>
    </div>

    <!-- Logout Button -->
    <div class="pt-2">
        <a href="logout.php" 
           onclick="return confirm('Apakah Anda yakin ingin keluar dari akun portal wali santri?')"
           class="w-full py-3 bg-white border border-rose-200 hover:bg-rose-50 text-rose-600 rounded-2xl font-bold text-xs shadow-xs transition-colors flex items-center justify-center gap-2">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Keluar dari Akun</span>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

