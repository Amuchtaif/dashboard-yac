<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Import Jadwal Pelajaran";

// Capture return filters
$return_filters = $_GET;
unset($return_filters['id'], $return_filters['error'], $return_filters['success']);
$return_filters_qs = http_build_query($return_filters);

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
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <a href="<?php url('views/class_schedules/index.php' . (!empty($return_filters_qs) ? '?' . $return_filters_qs : '')); ?>" class="hover:text-slate-800">Jadwal Pelajaran</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="text-slate-800 font-medium">Import Excel</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Import Jadwal Pelajaran</h1>
        <p class="mt-2 text-sm text-slate-600">Unggah file Excel untuk mengunggah jadwal pelajaran secara massal.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">

        <!-- Instructions -->
        <div class="mb-8 p-4 bg-cyan-50 border border-cyan-100 rounded-lg">
            <h3 class="font-semibold text-cyan-800 mb-2 text-sm">Petunjuk:</h3>
            <ul class="list-disc list-inside text-xs text-cyan-700 space-y-2">
                <li>Gunakan format file Excel (<strong>.xlsx</strong> / <strong>.xls</strong>).</li>
                <li>Struktur kolom: <strong>Hari, Unit, Kelas, Mapel, Guru, Jam Ke Mulai, Jam Ke Selesai, Tahun Akademik</strong>.</li>
                <li><strong>Hari</strong> diisi dalam Bahasa Inggris (Monday, Tuesday, dst).</li>
                <li><strong>Jam Ke</strong> diisi dengan angka urutan jam pelajaran (misal: 1, 2, dst).</li>
                <li>Data Unit, Kelas, Mapel, Guru, dan Tahun Akademik <strong>harus sudah ada</strong> di sistem agar bisa terhubung dengan benar.</li>
                <li>
                    <a href="<?php url('logic/class_schedules/download_template.php'); ?>"
                        class="inline-flex items-center underline font-bold hover:text-cyan-900 mt-2">
                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Unduh Template Excel
                    </a>
                </li>
            </ul>
        </div>

        <form action="<?php url('logic/class_schedules/import_process.php'); ?>" method="POST" enctype="multipart/form-data"
            class="space-y-6">
            <input type="hidden" name="return_filters" value="<?php echo htmlspecialchars($return_filters_qs); ?>">

            <div>
                <label class="block text-sm font-semibold text-slate-900 mb-2">Pilih File Excel</label>
                <div class="flex items-center justify-center w-full">
                    <label for="dropzone-file"
                        class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-300 border-dashed rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition-all">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <svg class="w-8 h-8 mb-4 text-slate-500" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2" />
                            </svg>
                            <p class="mb-2 text-sm text-slate-500"><span class="font-semibold">Klik untuk unggah</span>
                                atau seret file ke sini</p>
                            <p class="text-xs text-slate-500">Excel (.xlsx, .xls). Max. 2MB</p>
                        </div>
                        <input id="dropzone-file" name="import_file" type="file" class="hidden" accept=".xlsx, .xls" required />
                    </label>
                </div>
            </div>

            <!-- Filename Display -->
            <div id="file-name" class="hidden text-sm text-slate-700 bg-slate-100 p-2 rounded border border-slate-200">
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="<?php url('views/class_schedules/index.php' . (!empty($return_filters_qs) ? '?' . $return_filters_qs : '')); ?>"
                    class="px-6 py-2.5 rounded-lg border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition-colors">
                    Batal
                </a>
                <button type="submit"
                    class="px-6 py-2.5 rounded-lg bg-cyan-600 text-white text-sm font-semibold hover:bg-cyan-700 transition-colors shadow-sm">
                    Proses Import
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
            fileNameDisplay.textContent = "File terpilih: " + this.files[0].name;
            fileNameDisplay.classList.remove('hidden');
        } else {
            fileNameDisplay.classList.add('hidden');
        }
    });
</script>

<?php include '../layouts/footer.php'; ?>
