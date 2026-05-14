<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Manajemen Barang Inventaris";
require_once __DIR__ . '/../layouts/header.php';
?>

    <div class="sm:flex sm:items-center sm:justify-between mb-8">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Daftar Inventaris</h2>
            <p class="mt-2 text-sm text-slate-500">Kelola data barang dan alokasi lokasinya.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <div class="flex gap-2 items-center">
                <a href="<?php echo BASE_URL; ?>/logic/inventory/export_csv.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100 transition-all">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export CSV
                </a>

                <a href="<?php echo BASE_URL; ?>/views/inventory/import.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100 transition-all">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import CSV
                </a>

                <button onclick="openModal()" class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-cyan-100 hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-all ml-2">
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Tambah Barang
                </button>
            </div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 mb-8">
        <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between rounded-t-2xl">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-cyan-100 text-cyan-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-700">Filter & Pencarian</h3>
            </div>
            <button onclick="resetFilters()" class="text-xs font-semibold text-slate-400 hover:text-cyan-600 transition-colors flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Reset Filter
            </button>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <!-- Search -->
                <div class="md:col-span-3">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 ml-1">Nama / Kode Barang</label>
                    <div class="relative group">
                        <input type="text" id="searchInput" onkeyup="handleSearch()" placeholder="Cari sesuatu..." class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 transition-all text-sm outline-none bg-slate-50/50 focus:bg-white">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400 group-focus-within:text-cyan-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Lokasi Filter -->
                <div class="md:col-span-6">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 ml-1">Pilih Lokasi</label>
                    <div id="filter-location-cascader" class="flex flex-wrap gap-3">
                        <!-- Cascading Dropdowns will be rendered here -->
                        <div class="flex-1 h-[46px] animate-pulse bg-slate-100 rounded-xl"></div>
                    </div>
                </div>

                <!-- Kondisi Filter -->
                <div class="md:col-span-3">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 ml-1">Kondisi Barang</label>
                    <div class="relative">
                        <select id="filterCondition" onchange="fetchData()" class="hybrid-select" data-searchable="false">
                            <option value="">Semua Kondisi</option>
                            <option value="Baik">Baik</option>
                            <option value="Rusak Ringan">Rusak Ringan</option>
                            <option value="Rusak Berat">Rusak Berat</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="relative py-3.5 pl-4 pr-3 sm:pl-6 w-10 text-center">
                            <input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes(this)" class="custom-checkbox">
                        </th>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500 sm:pl-6 w-12 text-center">No</th>
                        <th scope="col" class="py-3.5 px-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500 w-20">Foto</th>
                        <th scope="col" class="py-3.5 px-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500 min-w-[200px]">Barang & Kode</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-500 min-w-[200px]">Lokasi</th>
                        <th scope="col" class="px-3 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-gray-500 min-w-[80px]">Jumlah</th>
                        <th scope="col" class="px-3 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-gray-500 min-w-[120px]">Kondisi</th>
                        <th scope="col" class="px-3 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-gray-500 min-w-[150px]">Sumber & Tanggal</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right text-xs font-bold uppercase tracking-wider text-gray-500 min-w-[100px]">Aksi</th>
                    </tr>
                </thead>
                <tbody id="items-table-body" class="divide-y divide-gray-200 bg-white">
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-slate-500">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Dynamic Pagination -->
    <div class="flex flex-col sm:flex-row items-center justify-between border-t border-gray-200 bg-white px-4 py-4 sm:py-3 sm:px-6 md:rounded-b-lg gap-4">
        <!-- Mobile Pagination Controls -->
        <div class="flex sm:hidden w-full justify-between items-center bg-slate-50 p-2 rounded-lg">
             <button onclick="changePage(currentPage - 1)" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 shadow-sm transition-all" id="prev-btn-mobile">Prev</button>
             <span class="text-xs font-bold text-slate-500 uppercase tracking-tighter" id="pagination-info-mobile">Page 1 of 1</span>
             <button onclick="changePage(currentPage + 1)" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 shadow-sm transition-all" id="next-btn-mobile">Next</button>
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between w-full">
            <div class="flex items-center gap-4">
                <select id="rowsPerPage" onchange="changeRowsPerPage()" class="block rounded-xl border-slate-200 py-1.5 pl-3 pr-8 text-slate-700 text-xs font-bold bg-slate-50 focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 transition-all appearance-none cursor-pointer">
                    <option value="10">Tampilkan 10</option>
                    <option value="50">Tampilkan 50</option>
                    <option value="100">Tampilkan 100</option>
                    <option value="all">Semua</option>
                </select>
                <p class="text-sm text-slate-600 font-medium" id="pagination-info">Menampilkan 0 hasil</p>
            </div>
            
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm border border-slate-200 overflow-hidden" aria-label="Pagination" id="pagination-controls">
                    <!-- Rendered by JS -->
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Floating Bulk Action Toolbar -->
<div id="bulk-toolbar" class="hidden fixed bottom-8 left-1/2 transform -translate-x-1/2 bg-slate-800 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center justify-between z-[100] transition-all min-w-[350px] border border-slate-700">
    <div class="flex items-center gap-3">
        <input type="checkbox" id="bulk-uncheck" onclick="toggleAllCheckboxes(this)" checked class="custom-checkbox w-5 h-5 text-cyan-500 bg-slate-700 border-none rounded focus:ring-cyan-500 focus:ring-offset-slate-800 cursor-pointer">
        <span class="text-sm font-semibold tracking-wide"><span id="bulk-count" class="text-cyan-400">0</span> barang terpilih</span>
    </div>
    <div class="space-x-2 flex items-center border-l border-slate-600 pl-4 ml-4">
        <button onclick="openBulkEditModal()" class="flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-white px-3 py-1.5 rounded-lg text-sm font-semibold transition shadow-sm border border-slate-600">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            Edit
        </button>
        <button onclick="bulkDelete()" class="flex items-center gap-2 bg-rose-600 hover:bg-rose-500 text-white px-3 py-1.5 rounded-lg text-sm font-semibold transition shadow-sm border border-rose-500">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Hapus
        </button>
    </div>
</div>

<!-- Modal Dialog form (Single Upload/Edit) -->
<div id="item-modal" class="fixed inset-0 z-[60] flex items-center justify-center hidden bg-black/50 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden transform transition-all scale-95 opacity-0" id="modal-content">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-800" id="modal-title">Tambah Barang</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form id="item-form" onsubmit="saveItem(event)" class="p-6 overflow-y-auto max-h-[80vh]">
            <input type="hidden" id="item_id">
            
            <div class="mb-4 flex gap-4">
                <div class="w-1/3">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Kode Barang</label>
                    <input type="text" id="item_code" readonly class="w-full rounded-lg border border-slate-300 px-4 py-3 bg-slate-50 text-slate-400 font-mono text-sm cursor-not-allowed outline-none transition uppercase" placeholder="(AUTO-CODE)">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Barang</label>
                    <input type="text" id="item_name" required class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition" placeholder="Contoh: Proyektor Epson">
                </div>
            </div>

            <!-- Foto Barang -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Foto Barang</label>
                <div class="mt-1 flex items-center gap-4">
                    <div id="photo-preview" class="w-16 h-16 rounded-lg bg-slate-100 border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden">
                        <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <input type="file" id="item_photo" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100 transition-all cursor-pointer">
                </div>
            </div>

            <!-- Cascading Dropdowns Wrapper -->
            <div class="mb-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Pilih Hirarki Lokasi</label>
                <div id="location-cascade-container">
                    <!-- Dinamis dropdown dari JS -->
                </div>
            </div>

            <div class="mb-4 flex gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Kondisi Barang</label>
                    <select id="item_condition" required class="hybrid-select" data-searchable="false">
                        <option value="Baik">Baik</option>
                        <option value="Rusak Ringan">Rusak Ringan</option>
                        <option value="Rusak Berat">Rusak Berat</option>
                    </select>
                </div>
                <div class="w-1/4">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Qty</label>
                    <input type="number" id="item_qty" min="0" required class="w-full rounded-lg border border-slate-300 px-3 py-3 text-sm focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition text-center" value="1">
                </div>
                <div class="w-1/4">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Satuan</label>
                    <input type="text" id="item_unit" class="w-full rounded-lg border border-slate-300 px-3 py-3 text-sm focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition text-center" placeholder="Pcs/Unit/Box" value="Pcs">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Sumber Dana</label>
                <input type="text" id="item_funding_source" class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition" placeholder="Contoh: Dana BOS 2024, Hibah, dll">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Tanggal Pembelian</label>
                <div class="relative">
                    <input type="date" id="purchase_date" class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Catatan / Deskripsi</label>
                <textarea id="item_description" rows="2" class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition placeholder-slate-400" placeholder="Opsional..."></textarea>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-medium transition">Batal</button>
                <button type="submit" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition shadow-sm">Simpan Barang</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Dialog BULK EDIT -->
<div id="bulk-edit-modal" class="fixed inset-0 z-[60] flex items-center justify-center hidden bg-black/50 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg transform transition-all scale-95 opacity-0" id="bulk-edit-content">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center rounded-t-2xl">
            <h3 class="text-lg font-bold text-slate-800">Edit Masal</h3>
            <button onclick="closeBulkEditModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form onsubmit="saveBulkEdit(event)" class="p-6 pb-32 rounded-b-2xl">
            <p class="text-sm text-slate-500 mb-4 bg-blue-50 p-3 rounded-lg border border-blue-100">Anda akan mengedit status kondisi & lokasi untuk <strong id="bulk-edit-count">0</strong> barang sekaligus.</p>
            
            <div class="mb-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                <label class="block text-sm font-semibold text-slate-700 mb-2 whitespace-nowrap"><input type="checkbox" id="change_lokasi" onchange="toggleBulkLocation(this.checked)" class="mr-2 rounded ring-0"> Pindahkan ke Lokasi Baru</label>
                <div id="bulk-location-cascade-container" class="opacity-50 pointer-events-none transition-opacity"></div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2 whitespace-nowrap"><input type="checkbox" id="change_kondisi" onchange="toggleBulkCondition(this.checked)" class="mr-2 rounded ring-0"> Ubah Semua Kondisi Menjadi</label>
                <select id="bulk_item_condition" disabled class="hybrid-select" data-searchable="false">
                    <option value="Baik">Baik</option>
                    <option value="Rusak Ringan">Rusak Ringan</option>
                    <option value="Rusak Berat">Rusak Berat</option>
                </select>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeBulkEditModal()" class="px-4 py-2 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-medium transition">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition shadow-sm">Terapkan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Dialog DELETE CONFIRMATION -->
<div id="item-delete-modal" class="fixed inset-0 z-[70] flex items-center justify-center hidden bg-black/50 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-xl w-full max-sm:mx-4 max-w-sm overflow-hidden transform transition-all scale-95 opacity-0" id="item-delete-modal-content">
        <div class="p-6 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 mb-4">
                <svg class="h-8 w-8 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2">Konfirmasi Hapus</h3>
            <p class="text-sm text-slate-500 mb-6 px-4" id="item-delete-modal-message">Apakah Anda yakin ingin menghapus data ini secara permanen? Tindakan ini tidak dapat dibatalkan.</p>
            
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeItemDeleteModal()" class="flex-1 px-4 py-2.5 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl text-sm font-semibold transition">Batal</button>
                <button type="button" id="item-confirm-delete-btn" class="flex-1 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-sm font-semibold transition shadow-sm shadow-rose-200">Hapus</button>
            </div>
        </div>
    </div>
</div>

<script>
    let locationTreeData = [];
    let currentItems = [];
    let selectedItemIds = new Set();
    let deleteTargetIds = [];
    
    // Pagination state
    let currentPage = 1;
    let rowsPerPage = 10;

    async function fetchData() {
        try {
            const locId = getSelectedLocationIdFromUI('filter-location-cascader');
            const condition = document.getElementById('filterCondition').value;

            let url = '<?php echo BASE_URL; ?>/api/inventory/items/get.php?';
            if (locId) url += `&location_id=${locId}`;
            if (condition) url += `&condition=${condition}`;

            const resItems = await fetch(url);
            const dataItems = await resItems.json();
            
            // For locations, we only need to fetch once for the tree
            if (locationTreeData.length === 0) {
                const resLocs = await fetch('<?php echo BASE_URL; ?>/api/inventory/locations/get.php');
                const dataLocs = await resLocs.json();
                if (dataLocs.success && dataLocs.data) {
                    locationTreeData = dataLocs.data;
                    // Render the filter cascader initially
                    buildCascadingDropdowns([], 'filter-location-cascader', () => fetchData());
                }
            }

            if (dataItems.success) {
                allItems = dataItems.data;
                currentItems = allItems;
                currentPage = 1;
                handleSearch(); // Apply search client-side if any text
                renderTable();
                updateBulkToolbar();
            }
        } catch(e) {
            console.error(e);
            showToast("Gagal memuat data barang.", "error");
        }
    }

    function handleSearch() {
        const q = (document.getElementById('searchInput').value || '').toLowerCase();
        if(!q) {
            currentItems = allItems;
        } else {
            currentItems = allItems.filter(it => 
                (it.name || '').toLowerCase().includes(q) || 
                (it.item_code || '').toLowerCase().includes(q)
            );
        }
        currentPage = 1;
        renderTable();
    }

    /* -------------------------------------------------------------
       PAGINATION LOGIC
       ------------------------------------------------------------- */
    function changeRowsPerPage() {
        const val = document.getElementById('rowsPerPage').value;
        rowsPerPage = val === 'all' ? currentItems.length : parseInt(val);
        currentPage = 1;
        renderTable();
    }

    function changePage(page) {
        if (page < 1 || page > Math.ceil(currentItems.length / rowsPerPage)) return;
        currentPage = page;
        renderTable();
    }

    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('filterCondition').value = '';
        
        // Reset location cascader
        buildCascadingDropdowns([], 'filter-location-cascader', () => fetchData());
        
        // Update hybrid selects UI
        const conditionSelect = document.getElementById('filterCondition');
        const hybridInput = conditionSelect.previousElementSibling.querySelector('.hybrid-search-input');
        if (hybridInput) hybridInput.placeholder = 'Semua Kondisi';
        
        fetchData();
    }

    function renderPaginationControls(totalItems) {
        const totalPages = Math.ceil(totalItems / rowsPerPage);
        const controls = document.getElementById('pagination-controls');
        const info = document.getElementById('pagination-info');
        
        // Mobile elements
        const infoMobile = document.getElementById('pagination-info-mobile');
        const prevBtnMobile = document.getElementById('prev-btn-mobile');
        const nextBtnMobile = document.getElementById('next-btn-mobile');

        if (totalItems === 0) {
            info.innerHTML = "Menampilkan 0 hasil";
            if(infoMobile) infoMobile.innerHTML = "Page 0 of 0";
            controls.innerHTML = '';
            return;
        }

        const startIdx = (currentPage - 1) * rowsPerPage + 1;
        const endIdx = Math.min(currentPage * rowsPerPage, totalItems);
        info.innerHTML = `Menampilkan <span class="font-medium">${startIdx}</span> sampai <span class="font-medium">${endIdx}</span> dari <span class="font-medium">${totalItems}</span> hasil`;

        if(infoMobile) infoMobile.innerHTML = `Page ${currentPage} of ${totalPages}`;
        if(prevBtnMobile) {
            prevBtnMobile.disabled = currentPage === 1;
            prevBtnMobile.classList.toggle('opacity-50', currentPage === 1);
            prevBtnMobile.classList.toggle('cursor-not-allowed', currentPage === 1);
        }
        if(nextBtnMobile) {
            nextBtnMobile.disabled = currentPage === totalPages;
            nextBtnMobile.classList.toggle('opacity-50', currentPage === totalPages);
            nextBtnMobile.classList.toggle('cursor-not-allowed', currentPage === totalPages);
        }

        let html = '';

        // Desktop Pagination Links
        if (currentPage > 1) {
            html += `
                <a href="javascript:void(0)" onclick="changePage(${currentPage - 1})" class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                    </svg>
                </a>
            `;
        }

        const range = 2;
        const initial_num = currentPage - range;
        const condition_limit_num = (currentPage + range) + 1;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= initial_num && i < condition_limit_num)) {
                if (i === currentPage) {
                    html += `<a href="javascript:void(0)" class="relative inline-flex items-center px-4 py-2 text-sm font-bold bg-cyan-600 text-white transition-colors">${i}</a>`;
                } else {
                    html += `<a href="javascript:void(0)" onclick="changePage(${i})" class="relative inline-flex items-center px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 border-x border-slate-100 transition-colors">${i}</a>`;
                }
            } else if (i === initial_num - 1 || i === condition_limit_num) {
                html += `<span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400 bg-slate-50/50">...</span>`;
            }
        }

        if (currentPage < totalPages) {
            html += `
                <a href="javascript:void(0)" onclick="changePage(${currentPage + 1})" class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                    </svg>
                </a>
            `;
        }

        controls.innerHTML = html;
    }

    /* -------------------------------------------------------------
       CASCADING DROPDOWN LOGIC
       ------------------------------------------------------------- */
    function findPathToNode(nodes, targetId, currentPath = []) {
        if(!targetId) return [];
        for (let node of nodes) {
            let path = [...currentPath, node.id];
            if (node.id == targetId) return path;
            if (node.children && node.children.length > 0) {
                let found = findPathToNode(node.children, targetId, path);
                if (found) return found;
            }
        }
        return null;
    }

    function buildCascadingDropdowns(path = [], containerId = 'location-cascade-container', onChangeCallback = null) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        
        let currentNodes = locationTreeData;
        let index = 0;

        while(currentNodes && currentNodes.length > 0) {
            let currentSelectedValue = path[index] || '';
            
            const divWrapper = document.createElement('div');
            // For filter bar, use flex-1 to fill space, for modal use relative mb-3
            divWrapper.className = containerId.includes('filter') ? "flex-1 min-w-[140px]" : "relative mb-3";

            const select = document.createElement('select');
            select.className = "hybrid-select"; 
            select.dataset.searchable = "true";
            select.dataset.level = index;
            
            let html = '<option value="">' + (index === 0 ? '-- Pilih Lokasi --' : '-- Semua Sub --') + '</option>';
            currentNodes.forEach(rn => {
                let isSelected = (rn.id == currentSelectedValue) ? 'selected' : '';
                html += `<option value="${rn.id}" ${isSelected}>${rn.name}</option>`;
            });
            select.innerHTML = html;
            
            select.addEventListener('change', (e) => {
                const val = e.target.value;
                let newPath = [];
                const allSelects = container.querySelectorAll('select.hybrid-select');
                for(let i = 0; i <= parseInt(e.target.dataset.level); i++) {
                    if(allSelects[i].value) newPath.push(allSelects[i].value);
                }
                buildCascadingDropdowns(newPath, containerId, onChangeCallback);
                if (onChangeCallback) onChangeCallback(val);
            });
            
            divWrapper.appendChild(select);
            container.appendChild(divWrapper);
            
            if(currentSelectedValue) {
                let selectedNode = currentNodes.find(n => n.id == currentSelectedValue);
                currentNodes = selectedNode ? selectedNode.children : [];
                index++;
            } else {
                break;
            }
        }
        
        if (typeof initHybridSelects === 'function') {
            initHybridSelects();
        }
    }

    /* -------------------------------------------------------------
       REST OF CRUD
       ------------------------------------------------------------- */
    function getConditionBadge(cond) {
        if(cond === 'Baik') return '<span class="inline-flex px-2 py-0.5 mt-1 text-[10px] font-bold rounded bg-emerald-100 text-emerald-700 border border-emerald-200 shadow-sm">Baik</span>';
        if(cond === 'Rusak Ringan') return '<span class="inline-flex px-2 py-0.5 mt-1 text-[10px] font-bold rounded bg-amber-100 text-amber-700 border border-amber-200 shadow-sm">Rusak Ringan</span>';
        if(cond === 'Rusak Berat') return '<span class="inline-flex px-2 py-0.5 mt-1 text-[10px] font-bold rounded bg-rose-100 text-rose-700 border border-rose-200 shadow-sm">Rusak Berat</span>';
        return cond;
    }

    function renderTable() {
        const tbody = document.getElementById('items-table-body');
        tbody.innerHTML = '';

        if(currentItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-6 py-8 text-center text-slate-500">Belum ada barang diinventaris.</td></tr>';
            renderPaginationControls(0);
            return;
        }

        // Apply pagination
        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;
        const pagedData = currentItems.slice(startIndex, endIndex);

        pagedData.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 transition-colors';
            
            let isChecked = selectedItemIds.has(item.id.toString()) ? 'checked' : '';
            let realIndex = startIndex + index + 1; // absolute row number

            let photoUrl = item.item_photo 
                ? `<?php echo BASE_URL; ?>/uploads/inventory/${item.item_photo}` 
                : 'https://placehold.co/100x100?text=No+Photo';

            tr.innerHTML = `
                <td class="relative whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6 text-center">
                    <input type="checkbox" value="${item.id}" ${isChecked} onchange="toggleSelection(this)" class="item-checkbox custom-checkbox">
                </td>
                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6 text-center">
                    ${realIndex}.
                </td>
                <td class="whitespace-nowrap px-3 py-4">
                    <img src="${photoUrl}" class="w-12 h-12 rounded-lg object-cover shadow-sm bg-slate-100 border border-slate-200">
                </td>
                <td class="whitespace-nowrap px-3 py-4">
                    <div class="font-bold text-gray-900">${item.name}</div>
                    <div class="text-[10px] uppercase font-black tracking-widest text-cyan-600 font-mono italic">${item.item_code || 'TANPA-KODE'}</div>
                </td>
                <td class="whitespace-nowrap px-3 py-4">
                    <div class="flex items-center gap-2">
                         <svg class="w-4 h-4 text-orange-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="text-xs text-gray-500 truncate max-w-[200px]" title="${item.location_breadcrumb}">${item.location_breadcrumb}</span>
                    </div>
                </td>
                <td class="whitespace-nowrap px-3 py-4 text-center">
                    <div class="text-sm font-bold text-slate-800">${item.qty}</div>
                    <div class="text-[10px] text-slate-400 uppercase font-black">${item.item_unit || 'Pcs'}</div>
                </td>
                <td class="whitespace-nowrap px-3 py-4 text-center">
                    ${getConditionBadge(item.item_condition)}
                </td>
                <td class="whitespace-nowrap px-3 py-4 text-center">
                    <div class="text-xs font-semibold text-slate-600">${item.funding_source || '-'}</div>
                    <div class="text-[10px] text-slate-500 mt-0.5">
                        ${item.purchase_date ? new Date(item.purchase_date).toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'}) : '-'}
                    </div>
                </td>
                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                    <div class="flex items-center justify-end gap-3 text-gray-400">
                        <button onclick="editItem(${item.id})" class="hover:text-cyan-600 transition-colors" title="Edit">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>
                        </button>
                        <button onclick="singleDelete(${item.id})" class="hover:text-red-600 transition-colors ml-1" title="Hapus">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        renderPaginationControls(currentItems.length);
        
        // Cek selectAll status utk current page
        const isAllSelected = pagedData.length > 0 && pagedData.every(i => selectedItemIds.has(i.id.toString()));
        document.getElementById('selectAll').checked = isAllSelected;
    }

    /* -------------------------------------------------------------
       UI EVENTS & BULK
       ------------------------------------------------------------- */
    function toggleSelection(el) {
        if(el.checked) selectedItemIds.add(el.value);
        else selectedItemIds.delete(el.value);
        updateBulkToolbar();
        
        // sync check all on current page
        const checkboxes = document.querySelectorAll('.item-checkbox');
        let allChecked = true;
        checkboxes.forEach(b => { if(!b.checked) allChecked = false; });
        document.getElementById('selectAll').checked = allChecked && checkboxes.length > 0;
    }

    function toggleAllCheckboxes(el) {
        // Ini milih semua YANG SEDANG TAMPIL di PAGE INI Atau nge-deselect all.
        // Jika dari Bulk Toolbar (yang mana el.id = bulk-uncheck), batalkan semua dari seluruh page.
        if (el.id === 'bulk-uncheck') {
            selectedItemIds.clear();
            const boxes = document.querySelectorAll('.item-checkbox');
            boxes.forEach(b => b.checked = false);
            document.getElementById('selectAll').checked = false;
        } else {
            const isChecked = el.checked;
            const boxes = document.querySelectorAll('.item-checkbox');
            boxes.forEach(b => {
                b.checked = isChecked;
                if(isChecked) selectedItemIds.add(b.value);
                else selectedItemIds.delete(b.value);
            });
        }
        updateBulkToolbar();
    }

    function updateBulkToolbar() {
        const toolbar = document.getElementById('bulk-toolbar');
        const count = document.getElementById('bulk-count');
        const countTxtEdit = document.getElementById('bulk-edit-count');
        
        count.innerText = selectedItemIds.size;
        countTxtEdit.innerText = selectedItemIds.size;

        if(selectedItemIds.size > 0) {
            toolbar.classList.remove('hidden', 'translate-y-full', 'opacity-0');
            toolbar.classList.add('flex', 'translate-y-0', 'opacity-100');
        } else {
            toolbar.classList.remove('flex', 'translate-y-0', 'opacity-100');
            toolbar.classList.add('hidden', 'translate-y-full', 'opacity-0');
        }
    }

    function getSelectedLocationIdFromUI(containerId) {
        const selects = document.getElementById(containerId).querySelectorAll('select');
        for(let i=selects.length-1; i>=0; i--) {
            if(selects[i].value) return selects[i].value;
        }
        return '';
    }

    function openModal() {
        document.getElementById('item_id').value = '';
        document.getElementById('item_code').value = '';
        document.getElementById('item_name').value = '';
        document.getElementById('item_condition').value = 'Baik';
        document.getElementById('item_qty').value = '1';
        document.getElementById('item_unit').value = 'Pcs';
        document.getElementById('item_funding_source').value = '';
        document.getElementById('item_description').value = '';
        document.getElementById('purchase_date').value = '';
        document.getElementById('item_photo').value = '';
        document.getElementById('photo-preview').innerHTML = '<svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';

        document.getElementById('modal-title').innerText = "Tambah Barang";
        buildCascadingDropdowns([], 'location-cascade-container');
        triggerModalUI(true, 'item-modal', 'modal-content');
    }

    function editItem(id) {
        const it = currentItems.find(i => i.id == id);
        if(!it) return;

        document.getElementById('item_id').value = it.id;
        document.getElementById('item_code').value = it.item_code || '';
        document.getElementById('item_name').value = it.name;
        document.getElementById('item_condition').value = it.item_condition || 'Baik';
        document.getElementById('item_qty').value = it.qty;
        document.getElementById('item_unit').value = it.item_unit || 'Pcs';
        document.getElementById('item_funding_source').value = it.funding_source || '';
        document.getElementById('item_description').value = it.description;
        document.getElementById('purchase_date').value = it.purchase_date || '';
        document.getElementById('item_photo').value = '';

        if (it.item_photo) {
            document.getElementById('photo-preview').innerHTML = `<img src="<?php echo BASE_URL; ?>/uploads/inventory/${it.item_photo}" class="w-full h-full object-cover">`;
        } else {
            document.getElementById('photo-preview').innerHTML = '<svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
        }

        document.getElementById('modal-title').innerText = "Edit Barang";
        
        // render cascader based on current path
        let path = findPathToNode(locationTreeData, it.location_id) || [];
        buildCascadingDropdowns(path, 'location-cascade-container');

        triggerModalUI(true, 'item-modal', 'modal-content');
    }

    function triggerModalUI(show, modalId, contentId) {
        const modal = document.getElementById(modalId);
        const content = document.getElementById(contentId);
        
        if(show) {
            modal.classList.remove('hidden');
            void modal.offsetWidth; // reflow
            modal.classList.add('opacity-100');
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        } else {
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            modal.classList.remove('opacity-100');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }
    }

    function closeModal() {
        triggerModalUI(false, 'item-modal', 'modal-content');
    }

    function openItemDeleteModal(ids, message) {
        deleteTargetIds = ids;
        document.getElementById('item-delete-modal-message').innerText = message;
        
        const confirmBtn = document.getElementById('item-confirm-delete-btn');
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        newConfirmBtn.addEventListener('click', async () => {
            newConfirmBtn.disabled = true;
            newConfirmBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg> Menghapus...`;
            await execDelete(deleteTargetIds);
            closeItemDeleteModal();
        });

        triggerModalUI(true, 'item-delete-modal', 'item-delete-modal-content');
    }

    function closeItemDeleteModal() {
        triggerModalUI(false, 'item-delete-modal', 'item-delete-modal-content');
    }

    async function saveItem(e) {
        e.preventDefault();
        
        const loc_id = getSelectedLocationIdFromUI('location-cascade-container');
        if(!loc_id) {
            showToast("Warning: Harap lengkapi lokasi sampai spesifik ruangannya.", "error");
            return;
        }

        const id = document.getElementById('item_id').value;
        const formData = new FormData();
        
        if (id) formData.append('id', id);
        formData.append('item_code', document.getElementById('item_code').value);
        formData.append('name', document.getElementById('item_name').value);
        formData.append('item_condition', document.getElementById('item_condition').value);
        formData.append('location_id', loc_id);
        formData.append('qty', document.getElementById('item_qty').value);
        formData.append('item_unit', document.getElementById('item_unit').value);
        formData.append('funding_source', document.getElementById('item_funding_source').value);
        formData.append('description', document.getElementById('item_description').value);
        formData.append('purchase_date', document.getElementById('purchase_date').value);
        
        const photoFile = document.getElementById('item_photo').files[0];
        if (photoFile) {
            formData.append('item_photo', photoFile);
        }

        try {
            const endpoint = id 
                ? '<?php echo BASE_URL; ?>/api/inventory/items/update.php' 
                : '<?php echo BASE_URL; ?>/api/inventory/items/create.php';
            
            // NOTE: We use POST for both create and update because standard PUT doesn't support multipart/form-data (files) easily in PHP
            const res = await fetch(endpoint, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                closeModal();
                fetchData(); // Refresh table
                showToast(id ? "Barang berhasil diupdate!" : "Barang baru berhasil ditambahkan!", "success");
            } else {
                showToast(data.message || "Gagal menyimpan item.", "error");
            }
        } catch (err) {
            console.error(err);
            showToast("Terjadi kesalahan sistem saat menyimpan.", "error");
        }
    }

    async function singleDelete(id) {
        openItemDeleteModal([id], "Hapus barang ini secara permanen? Tindakan ini tidak dapat dibatalkan.");
    }

    async function bulkDelete() {
        openItemDeleteModal(Array.from(selectedItemIds), `Yakin ingin menghapus ${selectedItemIds.size} barang terpilih secara permanen?`);
    }

    async function execDelete(idList) {
        try {
            const endpoint = '<?php echo BASE_URL; ?>/api/inventory/items/delete.php';
            const tasks = idList.map(id => fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            }));

            await Promise.all(tasks);
            
            // Clear selections after delete
            selectedItemIds.clear();
            fetchData();
            showToast("Data berhasil dihapus!", "success");
        } catch (err) {
            console.error(err);
            showToast("Gagal menghapus beberapa/semua item.", "error");
        }
    }

    /* -------------------------------------------------------------
       BULK EDIT
       ------------------------------------------------------------- */
    function toggleBulkLocation(isChecked) {
        const c = document.getElementById('bulk-location-cascade-container');
        c.classList.toggle('opacity-50', !isChecked);
        c.classList.toggle('pointer-events-none', !isChecked);
    }

    function toggleBulkCondition(isChecked) {
        const s = document.getElementById('bulk_item_condition');
        s.disabled = !isChecked;
        s.classList.toggle('bg-slate-100', !isChecked);
        s.classList.toggle('bg-white', isChecked);
    }

    function openBulkEditModal() {
        document.getElementById('change_lokasi').checked = false;
        document.getElementById('change_kondisi').checked = false;
        toggleBulkLocation(false);
        toggleBulkCondition(false);

        buildCascadingDropdowns([], 'bulk-location-cascade-container');

        triggerModalUI(true, 'bulk-edit-modal', 'bulk-edit-content');
    }

    function closeBulkEditModal() {
        triggerModalUI(false, 'bulk-edit-modal', 'bulk-edit-content');
    }

    async function saveBulkEdit(e) {
        e.preventDefault();

        const changeLokasi = document.getElementById('change_lokasi').checked;
        const changeKondisi = document.getElementById('change_kondisi').checked;

        if (!changeLokasi && !changeKondisi) {
            showToast("Anda belum menyetel apa saja yang ingin diupdate!", "error");
            return;
        }

        let payloadPatches = {};
        if (changeLokasi) {
            const l = getSelectedLocationIdFromUI('bulk-location-cascade-container');
            if(!l) { showToast("Pilih lokasi barunya.", "error"); return; }
            payloadPatches.location_id = l;
        }

        if (changeKondisi) {
            payloadPatches.item_condition = document.getElementById('bulk_item_condition').value;
        }

        const idList = Array.from(selectedItemIds);
        
        try {
            // Kita butuh looping update satu per satu karena API update kita update berdasar ID yang sudah ada 
            // (Kita harus fetch existing datanya buat ngisi default qty, nama kalo nggak dikirim, krn update_api butuh nama)
            // Wait, di update API kita ada IF (!name) -> exit.
            // Oh, jadi jika Bulk Update kita harus ambil item datanya dari currentItems!
            const tasks = idList.map(id => {
                let existingItem = currentItems.find(ci => ci.id == id);
                let bodyData = {
                    id: id,
                    name: existingItem.name,
                    qty: existingItem.qty,
                    description: existingItem.description,
                    item_condition: changeKondisi ? payloadPatches.item_condition : existingItem.item_condition,
                    location_id: changeLokasi ? payloadPatches.location_id : existingItem.location_id
                };
                
                return fetch('<?php echo BASE_URL; ?>/api/inventory/items/update.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(bodyData)
                });
            });

            const results = await Promise.all(tasks);
            
            selectedItemIds.clear();
            closeBulkEditModal();
            fetchData();
            showToast("Bulk edit berhasil disimpan!", "success");
        } catch(err) {
            console.error(err);
            showToast("Terjadi kegagalan saat proses Bulk Update.", "error");
        }
    }

    // Photo Preview Logic
    document.getElementById('item_photo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('photo-preview');
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
            }
            reader.readAsDataURL(file);
        }
    });

    window.onload = fetchData;
</script>

<style>
    /* Prevent hybrid select from causing overflow in flex containers */
    .hybrid-select-container {
        min-width: 0;
        flex-shrink: 1;
    }
    
    /* Ensure the location cascader handles many levels gracefully */
    #filter-location-cascader {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    
    #filter-location-cascader::-webkit-scrollbar {
        height: 4px;
    }
    
    #filter-location-cascader::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }
</style>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
