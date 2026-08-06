<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';
require_once '../../../config/permission.php';

check_permission('can_access_kesantrian');

$id = $_GET['id'] ?? '';
if (!$id) {
    redirect('views/boarding/violations/index.php');
}

$page_title = "Detail Pelanggaran Santri";
require_once '../../layouts/header.php';
?>

<div class="space-y-8 pb-12">
    <!-- Header with Back Button -->
    <div class="flex items-center gap-4">
        <a href="index.php" class="p-2.5 bg-white border border-slate-200 rounded-xl text-slate-400 hover:text-slate-600 hover:border-slate-300 transition-all shadow-sm">
            <i class="fa-solid fa-arrow-left w-6 h-6"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Pantau dan berikan tindak lanjut pada pelanggaran ini.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <!-- Left: Violation Summary Card -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-8 space-y-6">
                    <!-- Status Badge -->
                    <div id="detail-status" class="flex justify-center">
                        <span class="px-6 py-1.5 rounded-full text-[11px] font-black uppercase tracking-widest bg-slate-100 text-slate-400">Loading...</span>
                    </div>

                    <div class="text-center space-y-1">
                        <h2 class="text-2xl font-black text-slate-800" id="detail-santri">-</h2>
                        <p class="text-sm font-bold text-slate-400 uppercase tracking-widest" id="detail-kategori">-</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 py-6 border-y border-slate-50 italic">
                        <div class="text-center">
                            <p class="text-[10px] text-slate-400 uppercase font-black mb-1">Tanggal</p>
                            <p class="text-sm font-bold text-slate-700" id="detail-tanggal">-</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[10px] text-slate-400 uppercase font-black mb-1">Poin</p>
                            <p class="text-sm font-bold text-rose-600" id="detail-poin">0</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-start gap-4">
                            <div class="p-2 bg-indigo-50 rounded-xl text-indigo-600 flex-shrink-0">
                                <i class="fa-solid fa-location-dot w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase font-black mb-0.5">Lokasi Kejadian</p>
                                <p class="text-sm font-semibold text-slate-700" id="detail-lokasi">-</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="p-2 bg-emerald-50 rounded-xl text-emerald-600 flex-shrink-0">
                                <i class="fa-solid fa-user w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase font-black mb-0.5">Pelapor</p>
                                <p class="text-sm font-semibold text-slate-700" id="detail-pelapor">-</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                        <p class="text-[10px] text-slate-400 uppercase font-black mb-2">Deskripsi Kronologi</p>
                        <p class="text-sm text-slate-600 leading-relaxed italic" id="detail-deskripsi">-</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Follow-up Timeline & Form -->
        <div class="lg:col-span-2 space-y-8">
            
            <!-- Follow-up Form Card -->
            <div id="form-followup-card" class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden hidden">
                <div class="p-8">
                    <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                        <i class="fa-solid fa-pen-to-square w-6 h-6 text-indigo-500"></i>
                        Tambahkan Tindak Lanjut
                    </h3>
                    <form id="followup-form" class="space-y-5">
                        <input type="hidden" name="pelanggaran_id" value="<?php echo $id; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-2">
                                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Tindakan Diambil</label>
                                <input type="text" name="tindakan" required 
                                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all" 
                                    placeholder="Contoh: Pemberian nasehat, denda, dll...">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Tanggal Tindakan</label>
                                <input type="date" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required 
                                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Catatan Tambahan (Opsional)</label>
                            <textarea name="catatan" rows="2" 
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all resize-none" 
                                placeholder="Detail tambahan atau respon santri..."></textarea>
                        </div>

                        <div class="flex items-center justify-between pt-4">
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" name="status" value="diproses" checked class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                                    <span class="text-sm font-semibold text-slate-600 group-hover:text-slate-900 transition-colors">Masih Diproses</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" name="status" value="selesai" class="w-4 h-4 text-emerald-600 border-slate-300 focus:ring-emerald-500">
                                    <span class="text-sm font-semibold text-slate-600 group-hover:text-slate-900 transition-colors text-emerald-600">Kasus Selesai</span>
                                </label>
                            </div>
                            <button type="submit" class="px-10 py-3 bg-indigo-600 text-white text-sm font-bold rounded-2xl shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all transform active:scale-95">Simpan Tindakan</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Timeline Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/30">
                    <h3 class="text-lg font-bold text-slate-800">Riwayat Tindak Lanjut</h3>
                </div>
                <div class="p-8">
                    <div id="timeline-container" class="relative space-y-8 before:absolute before:inset-0 before:ml-5 before:-translate-x-px before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-200 before:to-transparent">
                        <!-- Timeline items will be rendered here -->
                        <div class="text-center py-10 text-slate-400 italic">Memuat riwayat...</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    const violationId = <?php echo json_encode($id); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        loadDetail();
    });

    async function loadDetail() {
        try {
            const res = await fetch(`<?php echo url('api/student_violations/get_detail.php'); ?>?id=${violationId}`);
            const result = await res.json();
            
            if (result.success) {
                renderDetail(result.data.violation);
                renderTimeline(result.data.followups);
                
                // Show form if is an officer AND not finished
                if (result.data.is_officer && result.data.violation.status !== 'selesai') {
                    document.getElementById('form-followup-card').classList.remove('hidden');
                } else {
                    document.getElementById('form-followup-card').classList.add('hidden');
                }
            } else {
                showToast(result.message, 'error');
            }
        } catch (e) {
            showToast('Gagal memuat detail', 'error');
        }
    }

    function renderDetail(v) {
        document.getElementById('detail-santri').textContent = v.nama_siswa;
        document.getElementById('detail-kategori').textContent = v.nama_kategori;
        document.getElementById('detail-tanggal').textContent = v.tanggal_pelanggaran;
        document.getElementById('detail-poin').textContent = `+${v.poin}`;
        document.getElementById('detail-lokasi').textContent = v.lokasi || '-';
        document.getElementById('detail-pelapor').textContent = v.pelapor_name;
        document.getElementById('detail-deskripsi').textContent = v.deskripsi;
        
        const statusEl = document.getElementById('detail-status');
        let statusClass = getStatusClass(v.status);
        statusEl.innerHTML = `<span class="px-6 py-1.5 rounded-full text-[11px] font-black uppercase tracking-widest ${statusClass}">${v.status}</span>`;
    }

    function renderTimeline(followups) {
        const container = document.getElementById('timeline-container');
        if (followups.length === 0) {
            container.innerHTML = '<div class="text-center py-10 text-slate-400 italic">Belum ada tindakan yang dicatat.</div>';
            return;
        }

        container.innerHTML = followups.map(f => `
            <div class="relative flex items-start gap-8 group">
                <!-- Dot -->
                <div class="absolute left-5 -translate-x-1/2 mt-1.5 h-3 w-3 rounded-full border-2 border-white bg-indigo-500 shadow-sm ring-4 ring-indigo-50 transition-all group-hover:scale-125 group-hover:ring-8"></div>
                
                <div class="flex-1 ml-10">
                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-2">
                        <h4 class="text-base font-bold text-slate-800">${f.tindakan}</h4>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">${f.tanggal_tindakan}</span>
                        </div>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <p class="text-sm text-slate-600 italic">"${f.catatan || 'Tanpa catatan tambahan'}"</p>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Penindak:</span>
                        <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest">${f.penindak_name}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function getStatusClass(status) {
        switch(status) {
            case 'draft': return 'bg-slate-100 text-slate-500';
            case 'dilaporkan': return 'bg-amber-100 text-amber-600';
            case 'diproses': return 'bg-indigo-100 text-indigo-600 shadow-lg shadow-indigo-100';
            case 'selesai': return 'bg-emerald-600 text-white shadow-lg shadow-emerald-200';
            default: return 'bg-slate-100 text-slate-500';
        }
    }

    document.getElementById('followup-form').onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('<?php echo url('api/student_violations/add_followup.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                showToast(result.message);
                e.target.reset();
                loadDetail();
            } else {
                showToast(result.message, 'error');
            }
        } catch (e) {
            showToast('Gagal menyimpan tindakan', 'error');
        }
    };
</script>

<?php require_once '../../layouts/footer.php'; ?>
