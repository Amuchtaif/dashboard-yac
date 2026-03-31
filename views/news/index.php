<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_news');

$page_title = "Manajemen Berita";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- Filter Logic ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(n.title LIKE :search OR n.content LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($category) {
    $where_clauses[] = "n.category = :category";
    $params[':category'] = $category;
}

$where_sql = implode(" AND ", $where_clauses);

// Total Count
$count_query = "SELECT COUNT(*) FROM news n WHERE $where_sql";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Data
$query = "
    SELECT n.*, e.full_name as author_name 
    FROM news n
    LEFT JOIN employees e ON n.author_id = e.id
    WHERE $where_sql
    ORDER BY n.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$news_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<div class="pb-10">
    <!-- Page Header -->
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Manajemen Berita</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola berita dan informasi yang tampil di aplikasi.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <a href="form.php"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                <svg class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Buat Berita Baru
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <form id="filter-form"
        class="mt-8 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center"
        method="GET" action="">
        
        <!-- Search -->
        <div class="relative w-full sm:w-96">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari judul atau isi berita..." onchange="this.form.submit()">
        </div>

        <!-- Category Filter -->
        <div class="flex gap-2 w-full sm:w-auto">
            <select name="category" onchange="this.form.submit()"
                class="block w-full sm:w-48 rounded-lg border-slate-200 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 bg-slate-50 border text-slate-600">
                <option value="">Semua Kategori</option>
                <?php foreach ($relevant_categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <a href="index.php"
                class="inline-flex items-center p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors"
                title="Reset Filters">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>
        </div>
    </form>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-xl bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-6 pr-3 text-left w-16">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[250px]">Berita</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[150px]">Kategori</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[150px]">Penulis</th>
                        <th scope="col" class="px-3 py-3.5 text-left w-32">Tanggal</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php if (empty($news_list)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-slate-500">
                                Tidak ada berita yang ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($news_list as $index => $news): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm text-slate-500">
                                    <?php echo $offset + $index + 1; ?>.
                                </td>
                                <td class="px-3 py-4 max-w-md">
                                    <div class="flex items-center gap-4">
                                        <?php if ($news['image']): ?>
                                            <img src="<?php echo url('uploads/news/' . $news['image']); ?>" 
                                                 class="h-10 w-16 object-cover rounded shadow-sm flex-shrink-0"
                                                 onerror="this.src='https://ui-avatars.com/api/?name=News&background=slate&color=white'">
                                        <?php else: ?>
                                            <div class="h-10 w-16 bg-slate-100 rounded flex items-center justify-center flex-shrink-0 text-slate-400">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6.75a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5v12.75a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <div class="overflow-hidden">
                                            <div class="text-sm font-semibold text-slate-900 truncate" title="<?php echo htmlspecialchars($news['title']); ?>">
                                                <?php echo htmlspecialchars($news['title']); ?>
                                            </div>
                                            <div class="text-xs text-slate-500 line-clamp-1">
                                                <?php echo strip_tags($news['content']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-600">
                                    <span class="inline-flex items-center rounded-md bg-cyan-50 px-2 py-1 text-xs font-medium text-cyan-700 ring-1 ring-inset ring-cyan-600/20">
                                        <?php echo htmlspecialchars($news['category'] ?: 'Umum'); ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-600">
                                    <?php echo htmlspecialchars($news['author_name'] ?: 'Admin'); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                    <?php echo date('d M Y', strtotime($news['created_at'])); ?>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-3 transition-opacity">
                                        <a href="form.php?id=<?php echo $news['id']; ?>" class="p-2 text-slate-400 hover:text-cyan-600 hover:bg-cyan-50 rounded-lg transition-all" title="Ubah">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                            </svg>
                                        </a>
                                        <button onclick="openDeleteModal('<?php url('logic/news/delete.php?id=' . $news['id']); ?>')" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" title="Hapus">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <!-- Dynamic Pagination -->
            <div class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-white px-4 py-4 md:py-3 sm:px-6 gap-4">
                <!-- Mobile Pagination Info -->
                <div class="flex sm:hidden flex-col items-center gap-2">
                    <p class="text-xs text-slate-500">
                        Menampilkan <span class="font-bold text-slate-900"><?php echo $offset + 1; ?></span> - <span class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span>
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Desktop/Tablet Pagination Info -->
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-slate-700 font-medium">
                            Menampilkan <span class="text-slate-900 font-bold"><?php echo $offset + 1; ?></span> sampai <span class="text-slate-900 font-bold"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="text-slate-900 font-bold"><?php echo $total_rows; ?></span> hasil
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm border border-slate-200 overflow-hidden" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo $i == $page ? 'bg-cyan-600 text-white' : 'text-slate-900 hover:bg-slate-50'; ?> border-x border-slate-100 transition-colors">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>" class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
