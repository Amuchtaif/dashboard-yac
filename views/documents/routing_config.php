<?php
// views/documents/routing_config.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$is_admin = isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator';
if (!$is_admin && !hasPermission($_SESSION['user_id'], 'manage_documents')) {
    redirect("views/documents/index.php?error=Akses+ditolak");
}

$page_title = "Pengaturan Penerima & Alur Surat";

$db = new Database();
$conn = $db->getConnection();

// Fetch Divisions & Units for dropdown
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$units = $conn->query("SELECT id, division_id, name FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active employees
$employees = $conn->query("
    SELECT e.id, e.full_name, e.phone_number, p.name as position_name, divs.name as division_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN divisions divs ON e.division_id = divs.id
    WHERE e.status = 'active'
    ORDER BY e.full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing routing rules
$stmtRules = $conn->query("
    SELECT r.id, r.division_id, r.unit_id, r.employee_id, r.role_type, r.created_at,
           divs.name as division_name, u.name as unit_name,
           e.full_name as employee_name, e.phone_number, p.name as position_name
    FROM document_routing_rules r
    JOIN divisions divs ON r.division_id = divs.id
    LEFT JOIN units u ON r.unit_id = u.id
    JOIN employees e ON r.employee_id = e.id
    LEFT JOIN positions p ON e.position_id = p.id
    ORDER BY divs.name ASC, u.name ASC, r.role_type DESC, e.full_name ASC
");
$rules = $stmtRules->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Tom Select CSS & JS for Searchable Dropdowns -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.2.2/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.2.2/js/tom-select.complete.min.js"></script>

<div class="pb-10">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Pengaturan Penerima & Alur Surat Per Bidang</h1>
            <p class="mt-1 text-sm text-slate-500">Tentukan pegawai yang bertanggung jawab menerima, menindaklanjuti, dan menyetujui surat masuk di setiap Bidang & Unit.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="<?php url('views/documents/index.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                Kembali ke Dashboard
            </a>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Form Tambah Penerima Surat -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm h-fit">
            <h2 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center">
                <i class="fa-solid fa-user-plus text-indigo-600 mr-2.5"></i>
                Tambah Penerima Surat
            </h2>

            <form action="<?php url('logic/documents/save_routing_rules.php'); ?>" method="POST" class="mt-5 space-y-4">
                <input type="hidden" name="action" value="add">

                <!-- Select Bidang -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Bidang Tujuan <span class="text-rose-500">*</span></label>
                    <select name="division_id" id="division_id" required
                            class="select-custom mt-1.5 block w-full rounded-xl border-slate-200 text-xs bg-slate-50 border p-3 text-slate-700">
                        <option value="">-- Pilih / Cari Bidang --</option>
                        <?php foreach ($divisions as $div): ?>
                            <option value="<?php echo $div['id']; ?>"><?php echo htmlspecialchars($div['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Select Unit -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Unit Spesifik <span class="text-slate-400 font-normal">(Opsional)</span></label>
                    <select name="unit_id" id="unit_id"
                            class="select-custom mt-1.5 block w-full rounded-xl border-slate-200 text-xs bg-slate-50 border p-3 text-slate-700">
                        <option value="">-- Seluruh Unit di Bidang Ini --</option>
                    </select>
                </div>

                <!-- Select Employee -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Pegawai Penanggung Jawab <span class="text-rose-500">*</span></label>
                    <select name="employee_id" id="employee_id" required
                            class="select-custom mt-1.5 block w-full rounded-xl border-slate-200 text-xs bg-slate-50 border p-3 text-slate-700">
                        <option value="">-- Pilih / Cari Pegawai --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>">
                                <?php echo htmlspecialchars($emp['full_name']); ?> 
                                (<?php echo htmlspecialchars($emp['position_name'] ?? 'Pegawai'); ?> - <?php echo htmlspecialchars($emp['division_name'] ?? 'Yayasan'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Role Type -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Peran Penanganan <span class="text-rose-500">*</span></label>
                    <select name="role_type" required
                            class="select-custom mt-1.5 block w-full rounded-xl border-slate-200 text-xs bg-slate-50 border p-3 text-slate-700 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="handler">Penerima & Notifikasi Surat (Handler)</option>
                        <option value="approver">Penyetuju Utama (Approver)</option>
                    </select>
                </div>

                <div class="pt-3">
                    <button type="submit" class="w-full inline-flex justify-center items-center rounded-xl bg-indigo-600 px-4 py-3 text-xs font-bold text-white shadow-md shadow-indigo-600/20 hover:bg-indigo-700 transition-colors">
                        <i class="fa-solid fa-plus mr-2"></i>
                        Simpan Penugasan
                    </button>
                </div>
            </form>
        </div>

        <!-- Matrix List -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
            <h2 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center justify-between">
                <span><i class="fa-solid fa-network-wired text-indigo-600 mr-2.5"></i> Daftar Matriks Penanggung Jawab Surat</span>
                <span class="text-xs font-bold bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full border border-indigo-100"><?php echo count($rules); ?> Penugasan</span>
            </h2>

            <div class="mt-5 overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left">Bidang / Unit Tujuan</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Pegawai Penanggung Jawab</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Jabatan & No HP</th>
                            <th scope="col" class="px-3 py-3.5 text-center">Peran</th>
                            <th scope="col" class="relative py-3.5 pl-3 pr-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (count($rules) > 0): ?>
                            <?php foreach ($rules as $r): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="whitespace-nowrap py-3.5 pl-4 pr-3 text-xs font-bold text-slate-800">
                                        <span class="inline-flex items-center text-indigo-700 font-bold">
                                            <i class="fa-solid fa-sitemap mr-1.5 text-indigo-500"></i>
                                            <?php echo htmlspecialchars($r['division_name']); ?>
                                        </span>
                                        <?php if (!empty($r['unit_name'])): ?>
                                            <span class="block text-[10px] text-slate-500 font-normal mt-0.5">&bull; Unit: <?php echo htmlspecialchars($r['unit_name']); ?></span>
                                        <?php else: ?>
                                            <span class="block text-[10px] text-slate-400 font-normal mt-0.5">(Seluruh Unit)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-700 font-medium">
                                        <?php echo htmlspecialchars($r['employee_name']); ?>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-500">
                                        <div><?php echo htmlspecialchars($r['position_name'] ?? '-'); ?></div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            <i class="fa-brands fa-whatsapp text-emerald-600 mr-1"></i>
                                            <?php echo htmlspecialchars($r['phone_number'] ?: 'Belum ada No HP'); ?>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-center">
                                        <?php if ($r['role_type'] === 'approver'): ?>
                                            <span class="inline-flex items-center rounded-full bg-purple-50 px-2.5 py-0.5 text-[9px] font-bold text-purple-700 border border-purple-200">Penyetuju (Approver)</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[9px] font-bold text-emerald-700 border border-emerald-200">Penerima (Handler)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-center">
                                        <form action="<?php url('logic/documents/save_routing_rules.php'); ?>" method="POST" onsubmit="return confirm('Hapus penugasan penerima surat ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                            <button type="submit" class="inline-flex items-center rounded-lg bg-rose-50 p-1.5 text-rose-600 hover:bg-rose-100 transition-colors" title="Hapus Penugasan">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-8 text-center text-xs text-slate-400 italic">
                                    Belum ada aturan penanggung jawab surat. Silakan tambahkan pada form di samping.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const allUnits = <?php echo json_encode($units); ?>;

let divTomSelect = null;
let unitTomSelect = null;
let empTomSelect = null;

document.addEventListener("DOMContentLoaded", function() {
    // Initialize TomSelect for Bidang Tujuan
    if (document.getElementById('division_id')) {
        divTomSelect = new TomSelect("#division_id", {
            create: false,
            placeholder: "-- Pilih / Cari Bidang --",
            onChange: function(value) {
                filterUnits(value);
            }
        });
    }

    // Initialize TomSelect for Unit Spesifik
    if (document.getElementById('unit_id')) {
        unitTomSelect = new TomSelect("#unit_id", {
            create: false,
            placeholder: "-- Seluruh Unit di Bidang Ini --"
        });
    }

    // Initialize TomSelect for Pegawai Penanggung Jawab
    if (document.getElementById('employee_id')) {
        empTomSelect = new TomSelect("#employee_id", {
            create: false,
            placeholder: "-- Pilih / Cari Pegawai --"
        });
    }
});

function filterUnits(divId) {
    if (!unitTomSelect) return;

    unitTomSelect.clear();
    unitTomSelect.clearOptions();
    unitTomSelect.addOption({ value: '', text: '-- Seluruh Unit di Bidang Ini --' });

    if (divId) {
        const filtered = allUnits.filter(u => u.division_id == divId);
        filtered.forEach(u => {
            unitTomSelect.addOption({ value: u.id, text: u.name });
        });
    }
    unitTomSelect.setValue('');
}
</script>

<?php include '../layouts/footer.php'; ?>
