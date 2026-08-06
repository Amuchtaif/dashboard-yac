<?php
// views/documents/template_config.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('document.template.manage');

$page_title = "Pengaturan Template Surat";

$db = new Database();
$conn = $db->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$tpl_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$template = null;
if (($action === 'edit') && $tpl_id > 0) {
    $stmtTpl = $conn->prepare("SELECT * FROM document_templates WHERE id = ?");
    $stmtTpl->execute([$tpl_id]);
    $template = $stmtTpl->fetch(PDO::FETCH_ASSOC);
    if (!$template) {
        redirect("views/documents/template_config.php?error=Template+tidak+ditemukan");
    }
}

// Fetch all available positions for configuring workflow stages
$positions = $conn->query("SELECT id, name FROM positions ORDER BY level ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all templates
$templates = $conn->query("SELECT * FROM document_templates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Template Builder Custom Styling */
.builder-canvas-wrapper {
    background: #e2e8f0;
    padding: 1.5rem;
    border-radius: 1rem;
    max-height: 82vh;
    overflow-y: auto;
    box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
}
.builder-canvas-paper {
    background: #ffffff;
    width: 100%;
    min-height: 297mm;
    padding: 20mm 15mm 20mm 15mm;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    font-family: 'Times New Roman', Times, serif;
    font-size: 12pt;
    line-height: 1.5;
    color: #1e293b;
    border: 1px solid #cbd5e1;
    border-radius: 2px;
    box-sizing: border-box;
    transition: all 0.2s ease-in-out;
}
.editor-editable {
    min-height: 320px;
    outline: none;
    font-family: 'Times New Roman', Times, serif;
    font-size: 11pt;
    line-height: 1.6;
    padding: 1rem;
    background-color: #ffffff;
    border-radius: 0.75rem;
    border: 1px solid #cbd5e1;
}
.editor-editable:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.tag-badge {
    display: inline-flex;
    align-items: center;
    background-color: #e0e7ff;
    color: #3730a3;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 0.15rem 0.5rem;
    border-radius: 0.375rem;
    border: 1px solid #c7d2fe;
    margin: 0 2px;
}
.editor-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.15s ease;
}
.editor-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.editor-btn.active {
    background: #6366f1;
    color: #ffffff;
    border-color: #6366f1;
}
</style>

<div class="pb-10">
    <!-- Header Page -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-indigo-600"></i>
                Pengaturan Template & Template Builder
            </h1>
            <p class="mt-1 text-sm text-slate-500">Desain kerangka surat dinas secara visual dengan live preview real-time serta alur persetujuan tanda tangan digital.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-2">
            <?php if ($action !== 'list'): ?>
                <a href="<?php url('views/documents/template_config.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    <i class="fa-solid fa-arrow-left mr-2 text-xs"></i>
                    Kembali ke Daftar
                </a>
            <?php else: ?>
                <a href="?action=new" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                    <i class="fa-solid fa-plus mr-2 text-xs"></i>
                    Tambah Template Baru
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FORM CREATE / EDIT (TEMPLATE BUILDER MODE) -->
    <?php if ($action === 'new' || $action === 'edit'): 
        $is_edit = ($action === 'edit');
        $default_content = '<!-- METADATA ANATOMI SURAT -->
<table style="width: 100%; border: none; margin-bottom: 20px; font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.4;">
    <tr>
        <td style="width: 80px; font-weight: bold; vertical-align: top;">Nomor</td>
        <td style="width: 15px; vertical-align: top;">:</td>
        <td style="vertical-align: top;">{{nomor_surat}}</td>
    </tr>
    <tr>
        <td style="font-weight: bold; vertical-align: top;">Hal</td>
        <td style="vertical-align: top;">:</td>
        <td style="font-weight: bold; vertical-align: top;">{{perihal}}</td>
    </tr>
    <tr>
        <td style="font-weight: bold; vertical-align: top;">Kepada</td>
        <td style="vertical-align: top;">:</td>
        <td style="vertical-align: top;">Yth. {{tujuan}}</td>
    </tr>
    <tr>
        <td></td>
        <td></td>
        <td style="font-weight: bold;">Di Tempat</td>
    </tr>
</table>

<!-- SALAM PEMBUKA -->
<p style="font-style: italic; font-family: \'Times New Roman\', serif; font-size: 12pt; margin-bottom: 15px;">Assalamu\'alaikum Warahmatullahi Wabarakatuh</p>

<!-- ISI SURAT / PARAGRAF -->
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.6; text-align: justify;">
    <p style="text-indent: 30px; margin-bottom: 15px;">Segala puji bagi Allah <i>Subhanahu wa Ta\'ala</i> yang telah melimpahkan rahmat-Nya kepada kita. Shalawat serta salam semoga senantiasa tercurah kepada Nabi Muhammad <i>Shallallahu \'alaihi wa sallam</i>.</p>
    
    <p style="text-indent: 30px; margin-bottom: 15px;">Dalam rangka meningkatkan mutu layanan pendidikan serta mewujudkan visi besar Yayasan Assunnah Cirebon, disampaikan instruksi resmi sebagai berikut:</p>
    
    <ol style="margin-left: 20px; font-weight: bold; margin-bottom: 15px;">
        <li>Pelaksanaan program kerja terpadu.</li>
        <li>Sosialisasi kepada seluruh unit terkait.</li>
        <li>Pelaporan progres secara berkala.</li>
    </ol>
    
    <p style="text-indent: 30px; margin-bottom: 15px;">Demikian surat ini disampaikan untuk menjadi perhatian dan dilaksanakan dengan penuh amanah.</p>
</div>

<!-- SALAM PENUTUP -->
<p style="font-style: italic; font-family: \'Times New Roman\', serif; font-size: 12pt; margin-top: 20px; margin-bottom: 30px;">Jazaakumullahu Khairan Katsiran.</p>

<!-- TANDA TANGAN -->
<div style="float: right; width: 320px; text-align: center; font-family: \'Times New Roman\', serif; font-size: 12pt;">
    <p style="margin: 0;">Cirebon, {{tanggal_surat}}</p>
    <p style="margin: 5px 0 0 0; font-weight: bold;">{{jabatan_penandatangan}}</p>
    
    <div style="height: 80px; display: flex; align-items: center; justify-content: center; margin: 10px 0;">
        {{ruang_tanda_tangan}}
    </div>
    
    <p style="margin: 0; font-weight: bold; text-decoration: underline;">{{nama_penandatangan}}</p>
</div>
<div style="clear: both;"></div>';

        $saved_content = $is_edit ? $template['content_template'] : $default_content;
    ?>
        <form action="<?php url('logic/documents/manage_template.php'); ?>" method="POST" enctype="multipart/form-data" id="templateBuilderForm" class="mt-6">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'update' : 'create'; ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
            <?php endif; ?>

            <!-- Split 2-Column Template Builder Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- LEFT COLUMN: CONTROLS & FORM BUILDER (7 Cols) -->
                <div class="lg:col-span-7 space-y-6">

                    <!-- Section 1: Dynamic Template Info -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                        <h2 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center gap-2">
                            <i class="fa-solid fa-file-pen text-indigo-600"></i>
                            <?php echo $is_edit ? 'Edit Template Surat' : 'Buat Template Surat Baru'; ?>
                        </h2>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Nama Template -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Nama Template</label>
                                <input type="text" name="name" id="builder_name" required value="<?php echo $is_edit ? htmlspecialchars($template['name']) : ''; ?>"
                                       oninput="updateLiveCanvasPreview()"
                                       class="mt-1.5 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-xs bg-slate-50 border p-3 text-slate-700"
                                       placeholder="Contoh: Surat Edaran Bidang Pendidikan">
                            </div>

                            <!-- Kode Template -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Kode Unik Template (Singkatan)</label>
                                <input type="text" name="code" id="builder_code" required value="<?php echo $is_edit ? htmlspecialchars($template['code']) : ''; ?>"
                                       oninput="updateLiveCanvasPreview()"
                                       class="mt-1.5 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-xs bg-slate-50 border p-3 text-slate-700"
                                       placeholder="Contoh: BIDIK, ST, SK">
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Format Nomor -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Format Penomoran Otomatis</label>
                                <input type="text" name="number_format" required value="<?php echo $is_edit ? htmlspecialchars($template['number_format']) : '{nomor}/{bulan_romawi}/{kode}/{tahun}'; ?>"
                                       class="mt-1.5 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-xs bg-slate-50 border p-3 text-slate-700">
                                <p class="text-[10px] text-slate-400 mt-1">Tag: `{nomor}`, `{unit}`, `{kode}`, `{bulan_romawi}`, `{tahun}`</p>
                            </div>

                            <!-- Reset Cycle -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Siklus Reset Nomor</label>
                                <select name="reset_cycle" required
                                        class="select-custom mt-1.5 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-xs bg-slate-50 border p-3 text-slate-700">
                                    <option value="never" <?php echo $is_edit && $template['reset_cycle'] === 'never' ? 'selected' : ''; ?>>Jangan Pernah Reset</option>
                                    <option value="monthly" <?php echo $is_edit && $template['reset_cycle'] === 'monthly' ? 'selected' : ''; ?>>Reset Setiap Bulan</option>
                                    <option value="yearly" <?php echo $is_edit && ($template['reset_cycle'] === 'yearly' || empty($template['reset_cycle'])) ? 'selected' : ''; ?>>Reset Setiap Tahun</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Kop Surat (Letterhead Builder) -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-heading text-indigo-600"></i>
                                Pengaturan Kop Surat (Letterhead)
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] text-slate-500">Tipe Kop:</span>
                                <select id="builder_header_type" onchange="toggleHeaderType(); updateLiveCanvasPreview();" class="text-xs border border-slate-200 rounded-lg p-1 bg-slate-50">
                                    <option value="standard" <?php echo empty($template['header_image']) ? 'selected' : ''; ?>>Logo + Teks Instansi</option>
                                    <option value="banner" <?php echo !empty($template['header_image']) ? 'selected' : ''; ?>>Kop Gambar Banner Utuh</option>
                                </select>
                            </div>
                        </div>

                        <!-- Standard Header Fields -->
                        <div id="header_standard_inputs" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Upload Logo -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Logo Kop (Kiri)</label>
                                    <input type="file" name="header_logo" id="input_header_logo" accept="image/*" onchange="handleLogoChange(this)"
                                           class="mt-1.5 block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                    <?php if ($is_edit && !empty($template['header_logo'])): ?>
                                        <div class="mt-2 flex items-center gap-2">
                                            <img id="logo_thumb" src="<?php echo BASE_URL . '/' . htmlspecialchars($template['header_logo']); ?>" class="h-8 object-contain border rounded p-1 bg-white" alt="Logo Kop">
                                            <span class="text-[10px] text-slate-400">Logo Aktif</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Header Line 1 -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Teks Baris 1 (Nama Instansi)</label>
                                    <input type="text" name="header_line_1" id="builder_header_line_1" oninput="updateLiveCanvasPreview()"
                                           value="<?php echo $is_edit && !empty($template['header_line_1']) ? htmlspecialchars($template['header_line_1']) : 'YAYASAN AS SUNNAH CIREBON'; ?>"
                                           class="mt-1.5 block w-full rounded-xl border-slate-200 text-xs bg-slate-50 border p-2.5 text-slate-700" placeholder="YAYASAN AS SUNNAH CIREBON">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Header Line 2 -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Teks Baris 2 (Bidang / Unit)</label>
                                    <input type="text" name="header_line_2" id="builder_header_line_2" oninput="updateLiveCanvasPreview()"
                                           value="<?php echo $is_edit && !empty($template['header_line_2']) ? htmlspecialchars($template['header_line_2']) : 'BIDANG PENDIDIKAN'; ?>"
                                           class="mt-1.5 block w-full rounded-xl border-slate-200 text-xs bg-slate-50 border p-2.5 text-slate-700" placeholder="BIDANG PENDIDIKAN">
                                </div>

                                <!-- Header Address -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Alamat & Kontak Kop Surat</label>
                                    <input type="text" name="header_address" id="builder_header_address" oninput="updateLiveCanvasPreview()"
                                           value="<?php echo $is_edit && !empty($template['header_address']) ? htmlspecialchars($template['header_address']) : 'Jl. Kalitanjung No.52B Kel. Karyamulya Kec. Kesambi Kota Cirebon 45135'; ?>"
                                           class="mt-1.5 block w-full rounded-xl border-slate-200 text-xs bg-slate-50 border p-2.5 text-slate-700" placeholder="Jl. Kalitanjung No.52B Kel. Karyamulya Kec. Kesambi Kota Cirebon 45135">
                                </div>
                            </div>
                        </div>

                        <!-- Banner Header Inputs -->
                        <div id="header_banner_inputs" class="space-y-3 hidden">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Upload Kop Gambar Banner Utuh</label>
                                <input type="file" name="header_image" id="input_header_image" accept="image/*" onchange="handleBannerChange(this)"
                                       class="mt-1.5 block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                <?php if ($is_edit && !empty($template['header_image'])): ?>
                                    <div class="mt-2 flex items-center gap-2">
                                        <img id="banner_thumb" src="<?php echo BASE_URL . '/' . htmlspecialchars($template['header_image']); ?>" class="h-10 object-contain border rounded p-1 bg-white" alt="Header Image">
                                        <span class="text-[10px] text-slate-400">Banner Kop Saat Ini</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Visual Content Builder & Variable Bar -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-pen-ruler text-indigo-600"></i>
                                Isi Surat & Variable Builder
                            </h3>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick="toggleSourceView()" id="btn_toggle_source" class="text-xs px-2.5 py-1 rounded-lg border border-slate-300 bg-slate-50 font-bold text-slate-700 hover:bg-slate-100 transition-colors">
                                    <i class="fa-solid fa-code text-indigo-600 mr-1"></i> Edit HTML Source
                                </button>
                            </div>
                        </div>

                        <!-- Variable Pills Quick Insertion Toolbar -->
                        <div>
                            <span class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-2">Klik untuk Sisipkan Tag Dinamis:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" onclick="insertVariableTag('{{nomor_surat}}')" class="tag-badge hover:bg-indigo-200 transition-colors cursor-pointer" title="Sisipkan Tag Nomor Surat">+ {{nomor_surat}}</button>
                                <button type="button" onclick="insertVariableTag('{{perihal}}')" class="tag-badge hover:bg-indigo-200 transition-colors cursor-pointer" title="Sisipkan Tag Perihal">+ {{perihal}}</button>
                                <button type="button" onclick="insertVariableTag('{{tujuan}}')" class="tag-badge hover:bg-indigo-200 transition-colors cursor-pointer" title="Sisipkan Tag Tujuan">+ {{tujuan}}</button>
                                <button type="button" onclick="insertVariableTag('{{tanggal_surat}}')" class="tag-badge hover:bg-indigo-200 transition-colors cursor-pointer" title="Sisipkan Tag Tanggal">+ {{tanggal_surat}}</button>
                                <button type="button" onclick="insertVariableTag('{{nama_penandatangan}}')" class="tag-badge hover:bg-indigo-200 transition-colors cursor-pointer" title="Sisipkan Tag Nama Penandatangan">+ {{nama_penandatangan}}</button>
                                <button type="button" onclick="insertVariableTag('{{jabatan_penandatangan}}')" class="tag-badge hover:bg-indigo-200 transition-colors cursor-pointer" title="Sisipkan Tag Jabatan Penandatangan">+ {{jabatan_penandatangan}}</button>
                                <button type="button" onclick="insertVariableTag('{{ruang_tanda_tangan}}')" class="tag-badge hover:bg-indigo-200 transition-colors cursor-pointer" title="Sisipkan Tag Ruang Tanda Tangan">+ {{ruang_tanda_tangan}}</button>
                                <button type="button" onclick="insertVariableTag('{{nama}}')" class="tag-badge bg-emerald-100 text-emerald-800 border-emerald-200 hover:bg-emerald-200 transition-colors cursor-pointer">+ {{nama}}</button>
                                <button type="button" onclick="insertVariableTag('{{jabatan}}')" class="tag-badge bg-emerald-100 text-emerald-800 border-emerald-200 hover:bg-emerald-200 transition-colors cursor-pointer">+ {{jabatan}}</button>
                                <button type="button" onclick="insertVariableTag('{{tugas}}')" class="tag-badge bg-emerald-100 text-emerald-800 border-emerald-200 hover:bg-emerald-200 transition-colors cursor-pointer">+ {{tugas}}</button>
                            </div>
                        </div>

                        <!-- Rich Text Formatting Toolbar -->
                        <div id="editor_formatting_toolbar" class="flex flex-wrap items-center gap-1 bg-slate-50 border border-slate-200 p-2 rounded-xl">
                            <button type="button" onclick="execCmd('bold')" class="editor-btn" title="Cetak Tebal (Bold)"><i class="fa-solid fa-bold"></i></button>
                            <button type="button" onclick="execCmd('italic')" class="editor-btn" title="Cetak Miring (Italic)"><i class="fa-solid fa-italic"></i></button>
                            <button type="button" onclick="execCmd('underline')" class="editor-btn" title="Garis Bawah (Underline)"><i class="fa-solid fa-underline"></i></button>
                            <button type="button" onclick="execCmd('strikeThrough')" class="editor-btn" title="Coret (Strikethrough)"><i class="fa-solid fa-strikethrough"></i></button>
                            <div class="h-4 w-px bg-slate-300 mx-1"></div>
                            <button type="button" onclick="execCmd('justifyLeft')" class="editor-btn" title="Rata Kiri"><i class="fa-solid fa-align-left"></i></button>
                            <button type="button" onclick="execCmd('justifyCenter')" class="editor-btn" title="Rata Tengah"><i class="fa-solid fa-align-center"></i></button>
                            <button type="button" onclick="execCmd('justifyRight')" class="editor-btn" title="Rata Kanan"><i class="fa-solid fa-align-right"></i></button>
                            <button type="button" onclick="execCmd('justifyFull')" class="editor-btn" title="Rata Kiri Kanan (Justify)"><i class="fa-solid fa-align-justify"></i></button>
                            <div class="h-4 w-px bg-slate-300 mx-1"></div>
                            <button type="button" onclick="execCmd('insertUnorderedList')" class="editor-btn" title="Daftar Simbol (Bulleted List)"><i class="fa-solid fa-list-ul"></i></button>
                            <button type="button" onclick="execCmd('insertOrderedList')" class="editor-btn" title="Daftar Angka (Numbered List)"><i class="fa-solid fa-list-ol"></i></button>
                            <div class="h-4 w-px bg-slate-300 mx-1"></div>
                            <button type="button" onclick="execCmd('removeFormat')" class="editor-btn" title="Hapus Format Teks"><i class="fa-solid fa-eraser"></i></button>
                        </div>

                        <!-- Visual Editor Box -->
                        <div id="visual_editor_container">
                            <div id="visual_editor" contenteditable="true" oninput="syncVisualToTextarea()" class="editor-editable shadow-inner">
                                <?php echo $saved_content; ?>
                            </div>
                        </div>

                        <!-- Hidden Textarea Form Field (Synchronized with Visual Editor) -->
                        <textarea name="content_template" id="builder_content_template" rows="14" required
                                  oninput="syncTextareaToVisual()"
                                  class="hidden block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-xs bg-slate-900 text-emerald-400 font-mono p-3"><?php echo htmlspecialchars($saved_content); ?></textarea>
                    </div>

                    <!-- Section 4: Workflow Stages Configurator -->
                    <div class="bg-indigo-50/60 rounded-2xl border border-indigo-100/60 p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-indigo-100 pb-2">
                            <h3 class="text-xs font-bold text-indigo-900 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-diagram-project text-indigo-600"></i>
                                Konfigurasi Alur Persetujuan (Workflow Tanda Tangan)
                            </h3>
                            <button type="button" onclick="addWorkflowStep()" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-[10px] font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                                <i class="fa-solid fa-plus mr-1 text-[9px]"></i> Tambah Tahap
                            </button>
                        </div>
                        <p class="text-[11px] text-indigo-700">Tentukan urutan hierarki pejabat penandatangan (Step 1, Step 2, dst.). Jika kosong, dokumen otomatis sah saat diajukan.</p>

                        <div id="workflow-steps-container" class="space-y-3">
                            <?php 
                            $saved_steps = $is_edit ? (json_decode($template['workflow_stages'], true) ?: []) : [];
                            foreach ($saved_steps as $step):
                                $cur_pos_id = intval($step['position_id']);
                            ?>
                                <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-slate-100 shadow-sm workflow-step-row">
                                    <span class="text-xs font-bold text-slate-400 step-label-num">Step <?php echo $step['step']; ?>:</span>
                                    <select name="workflow_stages[]" required
                                            class="select-custom block w-full rounded-xl border-slate-200 text-xs bg-slate-50 border p-2 text-slate-700">
                                        <option value="">-- Pilih Jabatan Approver --</option>
                                        <?php foreach ($positions as $pos): ?>
                                            <option value="<?php echo $pos['id']; ?>" <?php echo $pos['id'] === $cur_pos_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($pos['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" onclick="removeWorkflowStep(this)" class="text-rose-600 hover:text-rose-800 p-2 rounded-lg hover:bg-rose-50 transition-colors">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Form Action Bar -->
                    <div class="flex items-center justify-between border-t border-slate-200 pt-5">
                        <a href="<?php url('views/documents/template_config.php'); ?>" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                            Batal
                        </a>
                        <div class="flex gap-3">
                            <button type="button" onclick="openModalWithCurrentBuilder()" class="inline-flex items-center justify-center rounded-xl bg-slate-100 border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-200 transition-colors">
                                <i class="fa-solid fa-up-right-and-down-left-from-center mr-2 text-xs text-indigo-600"></i>
                                Fullscreen Pratinjau
                            </button>
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                                <i class="fa-solid fa-floppy-disk mr-2 text-xs"></i>
                                Simpan Template
                            </button>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: LIVE CANVAS PREVIEW (5 Cols - Sticky) -->
                <div class="lg:col-span-5 sticky top-6">
                    <div class="bg-slate-800 text-white rounded-2xl p-4 shadow-xl border border-slate-700 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-700 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <h3 class="text-xs font-bold text-slate-200 uppercase tracking-wider">Live Preview Canvas (A4)</h3>
                            </div>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="highlight_tags_toggle" onchange="updateLiveCanvasPreview()" class="sr-only peer">
                                <div class="relative w-8 h-4 bg-slate-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-600"></div>
                                <span class="ms-2 text-[10px] font-medium text-slate-300">Sorot Tag</span>
                            </label>
                        </div>

                        <!-- Canvas Paper Container -->
                        <div class="builder-canvas-wrapper">
                            <div id="live_builder_paper" class="builder-canvas-paper">
                                <!-- Dynamic Rendered Preview Injected via JS -->
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    <!-- MAIN TABLE LIST VIEW -->
    <?php else: ?>
        <!-- Data Table -->
        <div class="mt-8 overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left sm:pl-6 w-12">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Kode</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Nama Template</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Format Nomor</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Reset Counter</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Counter</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Tahapan</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (count($templates) > 0): ?>
                        <?php foreach ($templates as $index => $tpl): 
                            $stages = json_decode($tpl['workflow_stages'], true) ?: [];
                            $stages_names = array_map(function($s) { return $s['position_name']; }, $stages);
                        ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6">
                                    <?php echo $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs font-bold text-slate-800">
                                    <?php echo htmlspecialchars($tpl['code']); ?>
                                </td>
                                <td class="px-3 py-4 text-xs text-slate-700 font-bold">
                                    <?php echo htmlspecialchars($tpl['name']); ?>
                                </td>
                                <td class="px-3 py-4 text-xs font-mono text-slate-500">
                                    <?php echo htmlspecialchars($tpl['number_format']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-center text-slate-500 capitalize">
                                    <?php echo htmlspecialchars($tpl['reset_cycle']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-center font-bold text-indigo-600">
                                    #<?php echo $tpl['counter']; ?>
                                </td>
                                <td class="px-3 py-4 text-xs text-center">
                                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[9px] font-bold text-indigo-700 border border-indigo-100" title="<?php echo implode(' -> ', $stages_names); ?>">
                                        <?php echo count($stages); ?> Tahap
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center text-xs font-medium space-x-1.5">
                                    <!-- Aksi: Preview -->
                                    <button type="button"
                                            data-name="<?php echo htmlspecialchars($tpl['name'], ENT_QUOTES); ?>"
                                            data-content="<?php echo htmlspecialchars($tpl['content_template'], ENT_QUOTES); ?>"
                                            data-logo="<?php echo htmlspecialchars($tpl['header_logo'] ?? 'uploads/kop_logos/logo_yac.png', ENT_QUOTES); ?>"
                                            data-header-line-1="<?php echo htmlspecialchars($tpl['header_line_1'] ?? 'YAYASAN AS SUNNAH CIREBON', ENT_QUOTES); ?>"
                                            data-header-line-2="<?php echo htmlspecialchars($tpl['header_line_2'] ?? 'BIDANG PENDIDIKAN', ENT_QUOTES); ?>"
                                            data-header-address="<?php echo htmlspecialchars($tpl['header_address'] ?? 'Jl. Kalitanjung No.52B Kel. Karyamulya Kec. Kesambi Kota Cirebon 45135', ENT_QUOTES); ?>"
                                            data-header-image="<?php echo htmlspecialchars($tpl['header_image'] ?? '', ENT_QUOTES); ?>"
                                            onclick="previewTableTemplate(this)"
                                            class="inline-flex items-center rounded-lg bg-emerald-50 p-1.5 text-emerald-600 hover:bg-emerald-100 transition-colors" title="Pratinjau Template">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </button>

                                    <!-- Aksi: Edit -->
                                    <a href="?action=edit&id=<?php echo $tpl['id']; ?>" class="inline-flex items-center rounded-lg bg-indigo-50 p-1.5 text-indigo-600 hover:bg-indigo-100 transition-colors" title="Edit Template Builder">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </a>

                                    <!-- Aksi: Delete -->
                                    <a href="<?php url('logic/documents/manage_template.php'); ?>" class="inline-flex items-center rounded-lg bg-rose-50 p-1.5 text-rose-600 hover:bg-rose-100 transition-colors" title="Hapus Template"
                                       onclick="event.preventDefault(); if(confirm('Hapus template surat ini secara permanen?')){ document.getElementById('delete-template-<?php echo $tpl['id']; ?>').submit(); }">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </a>
                                    <form id="delete-template-<?php echo $tpl['id']; ?>" action="<?php url('logic/documents/manage_template.php'); ?>" method="POST" class="hidden">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $tpl['id']; ?>">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="py-10 text-center text-sm text-slate-500 italic">Belum ada template terdaftar. Silakan buat baru.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
// Dynamic logo/banner preview state
let liveLogoDataUrl = null;
let liveBannerDataUrl = null;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * UNIFIED TEMPLATE RENDERING ENGINE
 * Ensures 100% visual consistency between Table Preview & Live Builder Canvas!
 */
function renderTemplateDocument(data) {
    const baseUrl = '<?php echo BASE_URL; ?>/';
    
    // Determine logo & banner URLs
    let logoUrl = data.logoUrl || (data.header_logo ? (data.header_logo.startsWith('http') || data.header_logo.startsWith('data:') ? data.header_logo : baseUrl + data.header_logo) : baseUrl + 'uploads/kop_logos/logo_yac.png');
    let headerImageUrl = data.bannerUrl || (data.header_image ? (data.header_image.startsWith('http') || data.header_image.startsWith('data:') ? data.header_image : baseUrl + data.header_image) : '');
    
    const headerLine1 = data.header_line_1 || 'YAYASAN AS SUNNAH CIREBON';
    const headerLine2 = data.header_line_2 || 'BIDANG PENDIDIKAN';
    const headerAddress = data.header_address || 'Jl. Kalitanjung No.52B Kel. Karyamulya Kec. Kesambi Kota Cirebon 45135';
    let rawContent = data.content || '';

    // Smart Kop Injection: Strip existing embedded kop-surat-header to prevent duplicates
    let cleanContent = rawContent.replace(/<div class="kop-surat-header"[\s\S]*?<\/div>/gi, '');

    // Build Kop HTML dynamically
    let kopHtml = '';
    if (data.header_type === 'banner' && headerImageUrl) {
        kopHtml = `
        <div class="kop-surat-header" style="margin-bottom: 20px; text-align: center;">
            <img src="${headerImageUrl}" style="max-width: 100%; height: auto; display: block; margin: 0 auto;" alt="Kop Surat Banner">
        </div>`;
    } else {
        kopHtml = `
        <div class="kop-surat-header" style="display: flex; align-items: center; border-bottom: 3px double #333; padding-bottom: 8px; margin-bottom: 20px;">
            <div style="flex-shrink: 0; width: 90px; text-align: center; margin-right: 15px;">
                <img src="${logoUrl}" style="max-height: 80px; max-width: 85px; object-contain: contain;" alt="Logo Yayasan">
            </div>
            <div style="flex-grow: 1; text-align: center; font-family: 'Times New Roman', serif;">
                <h2 style="margin: 0; font-size: 17px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #1e293b; line-height: 1.2;">${escapeHtml(headerLine1)}</h2>
                <h3 style="margin: 2px 0 0 0; font-size: 14px; font-weight: bold; text-transform: uppercase; color: #334155; line-height: 1.2;">${escapeHtml(headerLine2)}</h3>
                <p style="margin: 4px 0 0 0; font-size: 10.5px; color: #475569; line-height: 1.3;">${escapeHtml(headerAddress)}</p>
            </div>
        </div>`;
    }

    let fullHtml = kopHtml + cleanContent;

    // Perform Dynamic Variable Replacements
    const sampleNumber = data.code ? `01/II/${data.code}/2026` : '01/II/BIDIK/2026';
    const sampleDate = '<?php echo date("d F Y"); ?>';

    fullHtml = fullHtml.replace(/\{\{logo_url\}\}/g, logoUrl);
    fullHtml = fullHtml.replace(/\{\{nomor_surat\}\}/g, sampleNumber);
    fullHtml = fullHtml.replace(/\{\{perihal\}\}/g, 'Implementasi 6 Program Utama Bidang Pendidikan');
    fullHtml = fullHtml.replace(/\{\{tujuan\}\}/g, 'Kepala Unit TK, SD, MTs, MA, dan Ma\'had Aly');
    fullHtml = fullHtml.replace(/\{\{tanggal_surat\}\}/g, sampleDate);
    fullHtml = fullHtml.replace(/\{\{nama_penandatangan\}\}/g, 'Muadin, Lc. M.Pd.I');
    fullHtml = fullHtml.replace(/\{\{jabatan_penandatangan\}\}/g, 'Ketua Bidang Pendidikan Yayasan Assunnah Cirebon');
    fullHtml = fullHtml.replace(/\{\{ruang_tanda_tangan\}\}/g, '<span style="color: #94a3b8; font-style: italic; font-size: 10pt;">[Ruang Stempel & Tanda Tangan / QR Code Validasi]</span>');

    // Sample custom placeholders
    fullHtml = fullHtml.replace(/\{\{nama\}\}/g, 'Ahmad Fauzi, S.Pd.');
    fullHtml = fullHtml.replace(/\{\{jabatan\}\}/g, 'Guru Pembina');
    fullHtml = fullHtml.replace(/\{\{tugas\}\}/g, 'Pengawasan Evaluasi Kurikulum');

    // Highlight tags option if enabled
    if (data.highlight_tags) {
        fullHtml = fullHtml.replace(/\{\{([^}]+)\}\}/g, '<span class="tag-badge">{{$1}}</span>');
    }

    return fullHtml;
}

/**
 * Update Live Builder Canvas Preview
 */
function updateLiveCanvasPreview() {
    const paper = document.getElementById('live_builder_paper');
    if (!paper) return;

    const name = document.getElementById('builder_name')?.value || '';
    const code = document.getElementById('builder_code')?.value || '';
    const header_line_1 = document.getElementById('builder_header_line_1')?.value || '';
    const header_line_2 = document.getElementById('builder_header_line_2')?.value || '';
    const header_address = document.getElementById('builder_header_address')?.value || '';
    const header_type = document.getElementById('builder_header_type')?.value || 'standard';
    const highlight_tags = document.getElementById('highlight_tags_toggle')?.checked || false;
    
    const visualEditor = document.getElementById('visual_editor');
    const textareaEditor = document.getElementById('builder_content_template');
    const content = visualEditor ? visualEditor.innerHTML : (textareaEditor ? textareaEditor.value : '');

    const existingLogoThumb = document.getElementById('logo_thumb')?.getAttribute('src');
    const existingBannerThumb = document.getElementById('banner_thumb')?.getAttribute('src');

    const htmlContent = renderTemplateDocument({
        name: name,
        code: code,
        header_line_1: header_line_1,
        header_line_2: header_line_2,
        header_address: header_address,
        header_type: header_type,
        logoUrl: liveLogoDataUrl || existingLogoThumb,
        bannerUrl: liveBannerDataUrl || existingBannerThumb,
        content: content,
        highlight_tags: highlight_tags
    });

    paper.innerHTML = htmlContent;
}

/**
 * Handle Logo Image File Change
 */
function handleLogoChange(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            liveLogoDataUrl = e.target.result;
            updateLiveCanvasPreview();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Handle Banner Image File Change
 */
function handleBannerChange(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            liveBannerDataUrl = e.target.result;
            updateLiveCanvasPreview();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Toggle Header Type (Standard vs Banner)
 */
function toggleHeaderType() {
    const type = document.getElementById('builder_header_type')?.value;
    const stdInputs = document.getElementById('header_standard_inputs');
    const bannerInputs = document.getElementById('header_banner_inputs');

    if (type === 'banner') {
        stdInputs?.classList.add('hidden');
        bannerInputs?.classList.remove('hidden');
    } else {
        stdInputs?.classList.remove('hidden');
        bannerInputs?.classList.add('hidden');
    }
}

/**
 * Synchronize Visual Editor -> Hidden Textarea
 */
function syncVisualToTextarea() {
    const visual = document.getElementById('visual_editor');
    const textarea = document.getElementById('builder_content_template');
    if (visual && textarea) {
        textarea.value = visual.innerHTML;
        updateLiveCanvasPreview();
    }
}

/**
 * Synchronize Textarea -> Visual Editor
 */
function syncTextareaToVisual() {
    const visual = document.getElementById('visual_editor');
    const textarea = document.getElementById('builder_content_template');
    if (visual && textarea) {
        visual.innerHTML = textarea.value;
        updateLiveCanvasPreview();
    }
}

/**
 * Toggle Visual vs Source HTML View
 */
function toggleSourceView() {
    const visualContainer = document.getElementById('visual_editor_container');
    const textarea = document.getElementById('builder_content_template');
    const toolbar = document.getElementById('editor_formatting_toolbar');
    const btn = document.getElementById('btn_toggle_source');

    if (textarea.classList.contains('hidden')) {
        textarea.classList.remove('hidden');
        visualContainer.classList.add('hidden');
        toolbar.classList.add('opacity-50', 'pointer-events-none');
        btn.innerHTML = '<i class="fa-solid fa-eye text-indigo-600 mr-1"></i> Edit Visual Mode';
    } else {
        syncTextareaToVisual();
        textarea.classList.add('hidden');
        visualContainer.classList.remove('hidden');
        toolbar.classList.remove('opacity-50', 'pointer-events-none');
        btn.innerHTML = '<i class="fa-solid fa-code text-indigo-600 mr-1"></i> Edit HTML Source';
    }
}

/**
 * Execute Rich Text Editing Command
 */
function execCmd(command, value = null) {
    document.execCommand(command, false, value);
    syncVisualToTextarea();
}

/**
 * Quick Insert Variable Tag into Editor
 */
function insertVariableTag(tag) {
    const visual = document.getElementById('visual_editor');
    const textarea = document.getElementById('builder_content_template');

    if (textarea && !textarea.classList.contains('hidden')) {
        // Source HTML Mode
        const startPos = textarea.selectionStart;
        const endPos = textarea.selectionEnd;
        textarea.value = textarea.value.substring(0, startPos) + tag + textarea.value.substring(endPos, textarea.value.length);
        syncTextareaToVisual();
    } else if (visual) {
        // Visual Mode
        visual.focus();
        document.execCommand('insertText', false, tag);
        syncVisualToTextarea();
    }
}

/**
 * Workflow Steps Management
 */
function addWorkflowStep() {
    const container = document.getElementById('workflow-steps-container');
    const stepCount = container.querySelectorAll('.workflow-step-row').length + 1;
    
    const div = document.createElement('div');
    div.className = "flex items-center gap-3 bg-white p-3 rounded-xl border border-slate-100 shadow-sm workflow-step-row";
    
    div.innerHTML = `
        <span class="text-xs font-bold text-slate-400 step-label-num">Step ${stepCount}:</span>
        <select name="workflow_stages[]" required
                class="select-custom block w-full rounded-xl border-slate-200 text-xs bg-slate-50 border p-2 text-slate-700">
            <option value="">-- Pilih Jabatan Approver --</option>
            <?php foreach ($positions as $pos): ?>
                <option value="<?php echo $pos['id']; ?>"><?php echo htmlspecialchars($pos['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" onclick="removeWorkflowStep(this)" class="text-rose-600 hover:text-rose-800 p-2 rounded-lg hover:bg-rose-50 transition-colors">
            <i class="fa-solid fa-trash text-xs"></i>
        </button>
    `;
    
    container.appendChild(div);
}

function removeWorkflowStep(button) {
    const row = button.closest('.workflow-step-row');
    row.remove();
    reorderWorkflowLabels();
}

function reorderWorkflowLabels() {
    const rows = document.querySelectorAll('.workflow-step-row');
    rows.forEach((row, index) => {
        const label = row.querySelector('.step-label-num');
        label.innerText = `Step ${index + 1}:`;
    });
}

/**
 * Preview Modal Controls
 */
function openPreviewModal(htmlContent, title = "Pratinjau Template Surat") {
    const modal = document.getElementById('templatePreviewModal');
    const backdrop = document.getElementById('templatePreviewBackdrop');
    const panel = document.getElementById('templatePreviewPanel');
    const body = document.getElementById('modal-preview-body');
    const titleEl = document.getElementById('modal-preview-title');

    if (titleEl) titleEl.innerText = title;
    if (body) body.innerHTML = htmlContent;

    modal.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0');
        panel.classList.remove('opacity-0', 'scale-95');
    }, 10);
}

function closePreviewModal() {
    const modal = document.getElementById('templatePreviewModal');
    const backdrop = document.getElementById('templatePreviewBackdrop');
    const panel = document.getElementById('templatePreviewPanel');

    backdrop.classList.add('opacity-0');
    panel.classList.add('opacity-0', 'scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

/**
 * Table List View: Preview Button Handler
 */
function previewTableTemplate(btn) {
    const name = btn.getAttribute('data-name') || '';
    const content = btn.getAttribute('data-content') || '';
    const logo = btn.getAttribute('data-logo') || 'uploads/kop_logos/logo_yac.png';
    const headerLine1 = btn.getAttribute('data-header-line-1') || 'YAYASAN AS SUNNAH CIREBON';
    const headerLine2 = btn.getAttribute('data-header-line-2') || 'BIDANG PENDIDIKAN';
    const headerAddress = btn.getAttribute('data-header-address') || 'Jl. Kalitanjung No.52B Kel. Karyamulya Kec. Kesambi Kota Cirebon 45135';
    const headerImage = btn.getAttribute('data-header-image') || '';

    const htmlContent = renderTemplateDocument({
        name: name,
        content: content,
        header_logo: logo,
        header_line_1: headerLine1,
        header_line_2: headerLine2,
        header_address: headerAddress,
        header_image: headerImage,
        header_type: headerImage ? 'banner' : 'standard'
    });

    openPreviewModal(htmlContent, 'Pratinjau: ' + name);
}

/**
 * Open Fullscreen Preview Modal from Builder Mode
 */
function openModalWithCurrentBuilder() {
    const name = document.getElementById('builder_name')?.value || 'Template Surat';
    const paperHtml = document.getElementById('live_builder_paper')?.innerHTML || '';
    openPreviewModal(paperHtml, 'Pratinjau Fullscreen: ' + name);
}

// Initialize Live Canvas on Page Load (if in builder mode)
document.addEventListener('DOMContentLoaded', () => {
    toggleHeaderType();
    updateLiveCanvasPreview();
});
</script>

<!-- Modal Preview Template -->
<div id="templatePreviewModal" class="fixed inset-0 z-[100] hidden overflow-y-auto" aria-labelledby="modal-preview-title" role="dialog" aria-modal="true">
    <div id="templatePreviewBackdrop" class="fixed inset-0 bg-slate-900/60 transition-opacity duration-300 opacity-0 backdrop-blur-sm" onclick="closePreviewModal()"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center">
        <div id="templatePreviewPanel" class="relative transform overflow-hidden rounded-2xl bg-slate-100 text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 max-w-4xl w-full my-8 border border-slate-200">
            <!-- Modal Header -->
            <div class="bg-white px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                        <i class="fa-solid fa-file-lines text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800" id="modal-preview-title">Pratinjau Template Surat</h3>
                        <p class="text-xs text-slate-500">Tampilan pratinjau format resmi kertas A4</p>
                    </div>
                </div>
                <button type="button" onclick="closePreviewModal()" class="rounded-xl p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <!-- Modal Body (Paper Layout) -->
            <div class="p-6 max-h-[75vh] overflow-y-auto bg-slate-200/60 flex justify-center">
                <div class="bg-white p-8 md:p-12 shadow-lg border border-slate-300 rounded-sm w-full max-w-[210mm] min-h-[297mm] font-serif text-slate-900 leading-relaxed text-sm" id="modal-preview-body">
                    <!-- Dynamic HTML content injected via JS -->
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-white px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="closePreviewModal()" class="inline-flex justify-center rounded-xl bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                    Tutup Pratinjau
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
