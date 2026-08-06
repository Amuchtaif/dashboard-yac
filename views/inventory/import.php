<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Import Data Inventaris";

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800">Beranda</a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <a href="<?php url('views/inventory/items.php'); ?>" class="hover:text-slate-800">Kelola Inventaris</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="text-slate-800 font-medium">Import Excel / CSV</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Import Data Inventaris</h1>
        <p class="mt-2 text-sm text-slate-600">Unggah file Excel atau CSV untuk mengunggah data barang secara massal ke gudang.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">

        <!-- Instructions -->
        <div class="mb-8 p-5 bg-cyan-50 border border-cyan-100 rounded-xl">
            <h3 class="font-semibold text-cyan-800 mb-2 text-sm">Petunjuk Penting:</h3>
            <ul class="list-disc list-inside text-xs text-cyan-700 space-y-2">
                <li>Pastikan file dalam format Excel (<strong>.xlsx</strong> / <strong>.xls</strong>) atau <strong>.csv</strong>.</li>
                <li>Gunakan header kolom yang sesuai dengan template (No, Kode, Nama, Lokasi, Qty, Satuan, Kondisi, Sumber Dana, Tanggal Pembelian, Deskripsi).</li>
                <li><strong>Lokasi:</strong> Gunakan nama lokasi yang sudah terdaftar di sistem (misal: "Kantor Bidik"). Jika lokasi tidak ditemukan, data akan dilewati.</li>
                <li><strong>Kondisi:</strong> Pilih salah satu: "Baik", "Rusak Ringan", atau "Rusak Berat".</li>
                <li><strong>Kode Barang:</strong> Bisa dikosongkan agar sistem men-generate otomatis berdasarkan skema lokasi.</li>
                <li>
                    <a href="<?php url('logic/inventory/download_template.php'); ?>"
                        class="inline-flex items-center underline font-bold hover:text-cyan-900 mt-2">
                        <i class="fa-solid fa-download w-4 h-4 mr-1"></i>
                        Download Template Excel
                    </a>
                </li>
            </ul>
        </div>

        <form action="<?php url('logic/inventory/import_process.php'); ?>" method="POST" enctype="multipart/form-data"
            class="space-y-6">

            <div>
                <label class="block text-sm font-semibold text-slate-900 mb-2">Pilih File Excel / CSV Inventaris</label>
                <div class="flex items-center justify-center w-full">
                    <label for="dropzone-file"
                        class="flex flex-col items-center justify-center w-full h-40 border-2 border-slate-300 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 transition-all">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <i class="fa-solid fa-cloud-arrow-up w-10 h-10 mb-4 text-slate-400"></i>
                            <p class="mb-2 text-sm text-slate-500 font-medium">Klik untuk pilih file Excel / CSV</p>
                            <p class="text-xs text-slate-400 uppercase tracking-widest">Atau drag and drop file di sini</p>
                        </div>
                        <input id="dropzone-file" name="csv_file" type="file" class="hidden" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" required />
                    </label>
                </div>
            </div>

            <div id="file-name" class="hidden text-sm text-cyan-700 bg-cyan-50 p-4 rounded-lg border border-cyan-100">
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="<?php url('views/inventory/items.php'); ?>"
                    class="px-6 py-2.5 rounded-lg border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition-colors">
                    Kembali
                </a>
                <button type="submit"
                    class="px-6 py-2.5 rounded-lg bg-cyan-600 text-white text-sm font-semibold hover:bg-cyan-700 transition-colors shadow-lg shadow-cyan-100">
                    Selesai & Proses Import
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const fileInput = document.getElementById('dropzone-file');
    const fileNameDisplay = document.getElementById('file-name');

    fileInput.addEventListener('change', function () {
        if (this.files && this.files.length > 0) {
            fileNameDisplay.innerHTML = `
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-file-lines w-5 h-5 text-cyan-600"></i>
                    <span>File terpilih: <b>${this.files[0].name}</b></span>
                </div>
            `;
            fileNameDisplay.classList.remove('hidden');
        } else {
            fileNameDisplay.classList.add('hidden');
        }
    });
</script>

<?php include '../layouts/footer.php'; ?>
