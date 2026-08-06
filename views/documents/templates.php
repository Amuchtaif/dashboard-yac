<?php
// views/documents/templates.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('document.create');

$page_title = "Template Surat";

$db = new Database();
$conn = $db->getConnection();

// Fetch active templates
$templates = $conn->query("SELECT * FROM document_templates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="pb-10">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Katalog Template Surat</h1>
            <p class="mt-1 text-sm text-slate-500">Pilih template surat dinas resmi di bawah untuk mulai membuat dokumen baru.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <?php if (can('document.template.manage') || (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator')): ?>
                <a href="<?php url('views/documents/template_config.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-800 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-slate-700 transition-colors">
                    <i class="fa-solid fa-cogs mr-2 text-xs"></i>
                    Kelola Template (Admin)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- CARDS GRID -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
        <!-- Manual/Custom Custom Letter Card -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between hover:border-slate-300 transition-colors">
            <div>
                <div class="h-10 w-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600">
                    <i class="fa-solid fa-pen-nib text-lg"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800 mt-4">Surat Bebas / Manual</h3>
                <p class="text-xs text-slate-400 mt-1.5 leading-relaxed">Tulis surat dengan format kustom tanpa menggunakan template terstruktur. Editor teks manual lengkap.</p>
            </div>
            <div class="mt-6 border-t border-slate-100 pt-4">
                <a href="<?php url('views/documents/outgoing.php?action=new'); ?>" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                    Mulai Buat Surat
                </a>
            </div>
        </div>

        <!-- Registered Templates -->
        <?php if (count($templates) > 0): ?>
            <?php foreach ($templates as $tpl): 
                $stages = json_decode($tpl['workflow_stages'], true) ?: [];
                $stages_count = count($stages);
            ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between hover:border-indigo-300 transition-colors">
                    <div>
                        <div class="h-10 w-10 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600">
                            <i class="fa-solid fa-file-contract text-lg"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800 mt-4"><?php echo htmlspecialchars($tpl['name']); ?></h3>
                        <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-600 mt-1 uppercase"><?php echo htmlspecialchars($tpl['code']); ?></span>
                        
                        <div class="mt-4 space-y-2 text-[11px] text-slate-500">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-list-check text-slate-400 text-xs"></i>
                                <span>Workflow: <span class="font-bold text-slate-700"><?php echo $stages_count; ?> Tahap Approval</span></span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-solid fa-hashtag text-slate-400 text-xs"></i>
                                <span>Format: <span class="font-mono text-slate-600"><?php echo htmlspecialchars(substr($tpl['number_format'], 0, 24)); ?>...</span></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 border-t border-slate-100 pt-4">
                        <a href="<?php url('views/documents/outgoing.php?action=new&template_id=' . $tpl['id']); ?>" 
                           class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition-colors shadow-sm">
                            Pilih & Buat Surat
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
