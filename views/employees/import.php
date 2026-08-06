<?php
// views/employees/import.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Impor Pegawai";
include '../layouts/header.php';

$errors = isset($_SESSION['import_errors']) ? $_SESSION['import_errors'] : [];
unset($_SESSION['import_errors']);
?>

<div class="w-full pb-10">
    <div class="mb-8">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
                <li class="inline-flex items-center">
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800">Beranda</a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                        <a href="<?php url('views/employees/index.php'); ?>" class="hover:text-slate-800">Pegawai</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-cyan-50 text-cyan-700">Impor Pegawai</span>
                    </div>
                </li>
            </ol>
        </nav>
        <h2 class="text-2xl font-bold text-slate-800">Impor Data Pegawai</h2>
        <p class="text-slate-500 text-sm mt-1">Unggah file Excel atau CSV untuk menambahkan data pegawai secara massal.</p>
    </div>

    <!-- Error Validation Panel -->
    <?php if (!empty($errors)): ?>
        <div class="mb-8 bg-red-50 border border-red-200 rounded-xl overflow-hidden shadow-sm">
            <div class="p-4 bg-red-100/50 border-b border-red-200 flex items-center gap-3">
                <div class="p-1.5 bg-red-500 text-white rounded-lg">
                    <i class="fa-solid fa-triangle-exclamation w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-red-800">Kesalahan Validasi Data</h3>
                    <p class="text-xs text-red-600 mt-0.5">Sebanyak <?php echo count($errors); ?> kesalahan terdeteksi. Silakan perbaiki file template Anda dan unggah kembali.</p>
                </div>
            </div>
            <div class="p-4 max-h-60 overflow-y-auto text-xs text-red-700 space-y-2.5 font-mono">
                <?php foreach ($errors as $error): ?>
                    <div class="flex items-start gap-2">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-red-400 mt-1.5 shrink-0"></span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-8 text-sm flex items-center gap-2">
            <i class="fa-solid fa-circle-info h-5 w-5 shrink-0"></i>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Side: Dropzone & Actions -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="h-1 bg-cyan-600 w-full"></div>
                <div class="p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-base font-bold text-slate-800">Unggah File Template</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Gunakan file template resmi agar data terpetakan dengan benar.</p>
                        </div>
                        <a href="<?php url('views/employees/download_template.php'); ?>" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-cyan-50 border border-cyan-150 rounded-lg text-xs font-semibold text-cyan-700 hover:bg-cyan-100 hover:text-cyan-800 transition-colors shrink-0 shadow-sm">
                            <i class="fa-solid fa-download w-3.5 h-3.5"></i>
                            Unduh Template (.xlsx)
                        </a>
                    </div>
                    
                    <form action="<?php url('logic/employees/import.php'); ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
                        
                        <!-- Drag Drop Container -->
                        <div id="dropzone" class="border-2 border-dashed border-slate-300 hover:border-cyan-500 rounded-2xl p-8 flex flex-col items-center justify-center bg-slate-50/50 hover:bg-cyan-50/10 transition-all cursor-pointer group">
                            <input type="file" name="import_file" id="import_file" class="hidden" accept=".xlsx,.xls,.csv" required onchange="handleFileSelect(this)">
                            
                            <div class="p-4 bg-cyan-50 text-cyan-600 rounded-2xl group-hover:scale-110 transition-transform shadow-sm mb-4">
                                <i class="fa-solid fa-cloud-arrow-up w-8 h-8"></i>
                            </div>
                            
                            <p class="text-sm font-bold text-slate-700 group-hover:text-cyan-600 transition-colors text-center" id="dropzone-text">Pilih atau Seret File ke Sini</p>
                            <p class="text-xs text-slate-400 mt-2 text-center">Mendukung format .xlsx, .xls, dan .csv (Maks. 5 MB)</p>
                            
                            <!-- File Info Display -->
                            <div id="file-info" class="hidden mt-4 px-4 py-2 bg-slate-100 rounded-lg flex items-center gap-2 border border-slate-200">
                                <i class="fa-solid fa-file-excel w-4 h-4 text-cyan-600"></i>
                                <span class="text-xs font-semibold text-slate-600" id="file-name-display">file_name.xlsx</span>
                            </div>
                        </div>

                        <div class="flex justify-end items-center gap-3 pt-6 border-t border-slate-100">
                            <a href="<?php url('views/employees/index.php'); ?>" class="px-5 py-2.5 border border-slate-200 rounded-lg text-sm font-semibold text-slate-500 hover:text-slate-700 bg-white hover:bg-slate-50 transition-colors">
                                Batal
                            </a>
                            <button type="submit" class="px-6 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-bold shadow-md shadow-cyan-100 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">
                                Mulai Impor Pegawai
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Step-by-Step Instructions -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                    <div class="p-1.5 bg-cyan-50 text-cyan-600 rounded-lg">
                        <i class="fa-solid fa-circle-question w-4.5 h-4.5"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-700">Panduan Impor Data</h3>
                </div>
                
                <div class="p-6 space-y-6 text-xs text-slate-600 leading-relaxed">
                    
                    <div class="flex gap-3">
                        <span class="w-6 h-6 rounded-full bg-cyan-100 text-cyan-700 flex items-center justify-center font-bold shrink-0">1</span>
                        <div>
                            <h4 class="font-bold text-slate-800 mb-1">Unduh File Template</h4>
                            <p>Unduh file template resmi terlebih dahulu menggunakan tombol <strong class="text-cyan-700">"Unduh Template (.xlsx)"</strong> di atas untuk memastikan susunan kolom terpetakan dengan benar.</p>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <span class="w-6 h-6 rounded-full bg-cyan-100 text-cyan-700 flex items-center justify-center font-bold shrink-0">2</span>
                        <div>
                            <h4 class="font-bold text-slate-800 mb-1">Lihat Tab "Referensi ID"</h4>
                            <p>Buka sheet kedua bernama <strong class="text-slate-800">"Referensi ID"</strong> di dalam Excel. Di sana Anda dapat menemukan tabel ID Bidang, ID Unit, ID Jabatan, dan ID Jadwal yang saat ini aktif di sistem.</p>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <span class="w-6 h-6 rounded-full bg-cyan-100 text-cyan-700 flex items-center justify-center font-bold shrink-0">3</span>
                        <div>
                            <h4 class="font-bold text-slate-800 mb-1">Input Data Sesuai Aturan</h4>
                            <ul class="list-disc list-inside space-y-1 mt-1 text-[11px] text-slate-500">
                                <li><strong class="text-slate-700">NIK & Email:</strong> Harus unik di sistem.</li>
                                <li><strong class="text-slate-700">Wajib Diisi:</strong> NIK, Nama, Email, Telp, Alamat, Jenis Kelamin (L/P), ID Bidang, ID Jabatan, Password.</li>
                                <li><strong class="text-slate-700">Boleh Kosong:</strong> ID Unit, ID Jadwal.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <span class="w-6 h-6 rounded-full bg-cyan-100 text-cyan-700 flex items-center justify-center font-bold shrink-0">4</span>
                        <div>
                            <h4 class="font-bold text-slate-800 mb-1">Unggah & Validasi</h4>
                            <p>Unggah file yang telah diisi. Sistem akan memvalidasi baris demi baris. Jika ada kesalahan, transaksi akan dibatalkan otomatis demi menjaga konsistensi database.</p>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // --- Drag & Drop Zone Interactions ---
    const dropzone = document.getElementById('dropzone');
    const dropzoneText = document.getElementById('dropzone-text');
    const fileInput = document.getElementById('import_file');
    const fileInfo = document.getElementById('file-info');
    const fileNameDisplay = document.getElementById('file-name-display');

    dropzone.addEventListener('click', () => fileInput.click());

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('border-cyan-500', 'bg-cyan-50/20');
        dropzoneText.textContent = "Lepaskan untuk Memilih File";
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('border-cyan-500', 'bg-cyan-50/20');
        dropzoneText.textContent = "Pilih atau Seret File ke Sini";
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('border-cyan-500', 'bg-cyan-50/20');
        dropzoneText.textContent = "Pilih atau Seret File ke Sini";

        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelect(fileInput);
        }
    });

    function handleFileSelect(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            fileNameDisplay.textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
            fileInfo.classList.remove('hidden');
            dropzoneText.textContent = "File Terpilih Berhasil Dimuat";
            
            // Highlight dropzone
            dropzone.classList.remove('border-slate-300', 'bg-slate-50/50');
            dropzone.classList.add('border-cyan-500', 'bg-cyan-50/10');
        } else {
            fileInfo.classList.add('hidden');
            dropzoneText.textContent = "Pilih atau Seret File ke Sini";
            
            // Restore dropzone
            dropzone.classList.add('border-slate-300', 'bg-slate-50/50');
            dropzone.classList.remove('border-cyan-500', 'bg-cyan-50/10');
        }
    }
</script>

<?php include '../layouts/footer.php'; ?>
