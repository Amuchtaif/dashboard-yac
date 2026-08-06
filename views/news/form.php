<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_news');

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = !empty($id);
$page_title = $is_edit ? "Edit Berita" : "Buat Berita Baru";

$news = [
    'id' => '',
    'title' => '',
    'category' => '',
    'content' => '',
    'image' => ''
];

if ($is_edit) {
    try {
        $stmt = $conn->prepare("SELECT * FROM news WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $fetched = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fetched) {
            $news = $fetched;
        } else {
            header("Location: index.php?error=Berita tidak ditemukan");
            exit;
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Categories List
$relevant_categories = [
    'Pengumuman',
    'Info Akademik',
    'Kegiatan Santri',
    'Tahfidz & Qur\'an',
    'Prestasi',
    'Berita Yayasan',
    'Artikel & Opini'
];

include '../layouts/header.php';
?>

<div class="w-full pb-8">
    <!-- Header Section -->
    <div class="mb-6">
        <nav class="flex mb-3" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                <li class="inline-flex items-center">
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-cyan-600 transition-colors">Beranda</a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fa-solid fa-chevron-right w-3 h-3 mx-1"></i>
                        <a href="index.php" class="hover:text-cyan-600 transition-colors">Manajemen Berita</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <i class="fa-solid fa-chevron-right w-3 h-3 mx-1"></i>
                        <span class="text-cyan-600"><?php echo $is_edit ? "Edit" : "Baru"; ?></span>
                    </div>
                </li>
            </ol>
        </nav>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-800"><?php echo $page_title; ?></h2>
                <p class="text-slate-500 text-xs mt-0.5">
                    <?php echo $is_edit ? "Perbarui informasi berita agar tetap relevan." : "Tulis berita baru untuk dibagikan kepada seluruh pengguna."; ?>
                </p>
            </div>
            <a href="index.php" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 text-xs font-semibold hover:bg-slate-50 transition-all shadow-sm">
                <i class="fa-solid fa-arrow-left w-3.5 h-3.5"></i>
                Kembali
            </a>
        </div>
    </div>

    <!-- Error Message -->
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 px-3 py-2.5 rounded-r-lg mb-6 text-xs font-medium flex items-center gap-2">
            <i class="fa-solid fa-magnifying-glass h-4 w-4 shrink-0"></i>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo $is_edit ? url('logic/news/update.php') : url('logic/news/store.php'); ?>" 
          method="POST" enctype="multipart/form-data">
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo $news['id']; ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Main Content -->
            <div class="lg:col-span-2 space-y-5">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="h-1 bg-gradient-to-r from-cyan-500 to-blue-500 w-full"></div>
                    <div class="p-5 space-y-5">
                        <!-- Title -->
                        <div class="space-y-1.5">
                            <label for="title" class="block text-xs font-bold text-slate-700">
                                Judul Berita <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="title" id="title" required 
                                   value="<?php echo htmlspecialchars($news['title']); ?>"
                                   class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-slate-800 text-sm font-semibold focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-300"
                                   placeholder="Masukkan judul berita...">
                        </div>

                        <!-- Content -->
                        <div class="space-y-1.5">
                            <label for="content" class="block text-xs font-bold text-slate-700">
                                Isi Berita <span class="text-rose-500">*</span>
                            </label>
                            <textarea name="content" id="content" rows="14" required
                                      class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 text-slate-700 text-sm leading-relaxed focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-300"
                                      placeholder="Tuliskan isi berita secara lengkap..."><?php echo htmlspecialchars($news['content']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Sidebar -->
            <div class="space-y-5 lg:sticky lg:top-6 self-start">
                <!-- Category Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                        <span class="w-7 h-7 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center">
                            <i class="fa-solid fa-tag w-3.5 h-3.5"></i>
                        </span>
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">Kategori</h3>
                    </div>
                    <div class="space-y-1.5">
                        <label for="category" class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Pilih Kategori</label>
                        <select name="category" id="category" required
                               class="w-full px-3.5 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 text-xs font-semibold focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all cursor-pointer hover:bg-white">
                            <option value="" disabled selected>— Pilih Kategori —</option>
                            <?php foreach ($relevant_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $news['category'] === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="p-2.5 bg-emerald-50 rounded-lg border border-emerald-100 flex items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass h-3.5 w-3.5 text-emerald-500 shrink-0"></i>
                        <p class="text-[10px] text-emerald-700 font-medium">Langsung dipublikasikan</p>
                    </div>
                </div>

                <!-- Image Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <i class="fa-solid fa-image w-3.5 h-3.5"></i>
                        </span>
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">Gambar Cover</h3>
                    </div>
                    
                    <div id="image-preview-container" class="relative group aspect-video rounded-lg overflow-hidden border-2 border-dashed border-slate-200 bg-slate-50 flex items-center justify-center cursor-pointer hover:border-cyan-300 transition-all"
                         onclick="document.getElementById('image').click()">
                        <?php if ($news['image']): ?>
                            <img src="<?php echo url('uploads/news/' . $news['image']); ?>" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="text-white text-[10px] font-bold uppercase tracking-wider">Ganti Gambar</span>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-3">
                                <div class="w-8 h-8 bg-white rounded-full shadow-sm flex items-center justify-center mx-auto mb-1.5 text-slate-300 group-hover:text-cyan-500 group-hover:scale-110 transition-all">
                                    <i class="fa-solid fa-plus h-4 w-4"></i>
                                </div>
                                <p class="text-[10px] text-slate-400 font-semibold">Klik untuk unggah gambar</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="image" id="image" class="hidden" accept="image/*" onchange="previewImage(this)">
                    <p class="text-[9px] text-slate-400 text-center font-medium">Format JPG/PNG/WebP maks 2MB. Rasio 16:9.</p>
                </div>

                <!-- Action Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
                    <button type="submit" class="w-full py-3 px-4 bg-cyan-600 hover:bg-cyan-700 text-white font-bold text-xs rounded-lg shadow-sm hover:shadow-md transition-all active:scale-[0.98] flex items-center justify-center gap-2">
                        <i class="fa-solid fa-check w-4 h-4"></i>
                        <?php echo $is_edit ? "Simpan Perubahan" : "Terbitkan Berita"; ?>
                    </button>
                    <a href="index.php" class="block w-full py-2.5 px-4 border border-slate-200 text-slate-500 text-center font-semibold text-xs rounded-lg hover:bg-slate-50 transition-colors">
                        Batalkan
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function previewImage(input) {
    const container = document.getElementById('image-preview-container');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            container.innerHTML = `
                <img src="${e.target.result}" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <span class="text-white text-[10px] font-bold uppercase tracking-wider">Ganti Gambar</span>
                </div>
            `;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include '../layouts/footer.php'; ?>
