<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

// Assuming manage_employees permission for now, or administrator
check_permission('manage_employees');

$page_title = "Pengelompokan Karyawan";
include '../layouts/header.php';
?>

<div class="pb-10">

    <!-- Breadcrumbs -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="hover:text-slate-800 flex items-center gap-1">
                    <i class="fa-solid fa-house w-3 h-3"></i>
                    Beranda
                </a>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 text-slate-500 hover:text-slate-800">Pengelompokan Karyawan</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight">
                Pengelompokan Karyawan
            </h2>
            <p class="mt-1 text-sm text-slate-500">Kelola grup karyawan dinamis dan manual untuk berbagai keperluan sistem.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <a href="<?php url('views/employee_groups/form.php'); ?>"
                class="inline-flex items-center rounded-lg bg-[#2B3990] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-[#2B3990] focus:ring-offset-2 transition-all ml-auto">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Tambah Grup
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
        <!-- Search -->
        <div class="relative w-full sm:w-96">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <i class="fa-solid fa-magnifying-glass h-4 w-4 text-slate-400"></i>
            </div>
            <input type="text" id="searchInput"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari nama grup...">
        </div>

        <div class="flex gap-3 w-full sm:w-auto">
            <select id="filterType" class="block w-full rounded-lg border-slate-300 py-2 pl-3 pr-8 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white">
                <option value="">Semua Jenis</option>
                <option value="dynamic">Dynamic Group</option>
                <option value="manual">Manual Group</option>
            </select>
            <select id="filterStatus" class="block w-full rounded-lg border-slate-300 py-2 pl-3 pr-8 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white">
                <option value="">Semua Status</option>
                <option value="1">Aktif</option>
                <option value="0">Tidak Aktif</option>
            </select>
            <select id="filterLimit" class="block w-full rounded-lg border-slate-300 py-2 pl-3 pr-8 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white">
                <option value="10">10 data</option>
                <option value="25">25 data</option>
                <option value="50">50 data</option>
                <option value="all">Semua data</option>
            </select>
        </div>
    </div>

    <!-- Data Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-xl bg-white">
            <table class="min-w-full divide-y divide-slate-200" id="groupsTable">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="w-10 pl-6 py-3.5 text-left"></th>
                        <th scope="col" class="py-3.5 pl-3 pr-3 text-left">Nama Grup</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Jenis</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Status</th>
                        <th scope="col" class="px-3 py-3.5 text-left hidden md:table-cell">Deskripsi</th>
                        <th scope="col" class="px-3 py-3.5 text-left hidden lg:table-cell">Diperbarui</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white" id="groupsTableBody">
                    <!-- Skeleton Loader -->
                    <tr id="tableSkeleton">
                        <td colspan="7" class="p-6">
                            <div class="animate-pulse flex flex-col gap-4">
                                <div class="h-4 bg-slate-200 rounded w-full"></div>
                                <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                                <div class="h-4 bg-slate-200 rounded w-5/6"></div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Empty State -->
            <div id="emptyState" class="hidden text-center py-12 px-6">
                <i class="fa-solid fa-users mx-auto h-12 w-12 text-slate-300"></i>
                <h3 class="mt-2 text-sm font-semibold text-slate-900">Belum ada pengelompokan karyawan</h3>
                <p class="mt-1 text-sm text-slate-500">Klik "Tambah Grup" untuk membuat grup pertama.</p>
                <div class="mt-6">
                    <a href="<?php url('views/employee_groups/form.php'); ?>" class="inline-flex items-center rounded-md bg-[#2B3990] px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600">
                        <i class="fa-solid fa-plus -ml-0.5 mr-1.5 h-5 w-5"></i>
                        Tambah Grup
                    </a>
                </div>
            </div>
            
        </div>
        
        <!-- Pagination -->
        <div class="mt-4 flex items-center justify-between" id="paginationContainer">
            <!-- Rendered by JS -->
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div id="deleteModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
  <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
      <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
              <i class="fa-solid fa-circle-exclamation h-6 w-6 text-red-600"></i>
            </div>
            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
              <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Hapus Grup</h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500">Apakah Anda yakin ingin menghapus grup ini? Seluruh rule dan relasi member akan ikut terhapus. Aksi ini tidak dapat dibatalkan.</p>
                <div id="deleteErrorMsg" class="mt-2 text-sm text-red-600 hidden"></div>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
          <button type="button" id="confirmDeleteBtn" class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">Hapus</button>
          <button type="button" onclick="closeDeleteModal()" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Batal</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
    // Configuration to pass to JS
    const APP_URL = "<?php echo BASE_URL; ?>";
</script>
<script src="<?php echo url('assets/js/employee_groups.js') . '?v=' . time(); ?>"></script>

<?php include '../layouts/footer.php'; ?>
