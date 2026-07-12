<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Tambah Siswa Baru";

$db = new Database();
$conn = $db->getConnection();

// Fetch Grade Levels for Dropdown
$query_grades = "SELECT id, name, education_unit_id FROM grade_levels WHERE is_active = 1 ORDER BY name ASC";
$stmt_grades = $conn->prepare($query_grades);
$stmt_grades->execute();
$grade_levels = $stmt_grades->fetchAll(PDO::FETCH_ASSOC);

// Fetch Education Units for Dropdown
$education_units = $conn->query("SELECT id, name FROM education_units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Academic Years for Dropdown
$academic_years = $conn->query("SELECT id, name, semester FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Academic Year
$active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="inline-flex items-center text-slate-500 hover:text-slate-700">
                    Beranda
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <a href="<?php url('views/students/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-700">Data Siswa</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 font-medium text-slate-800">Tambah Baru</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Tambah Siswa Baru</h1>
        <p class="mt-2 text-sm text-slate-600">Daftarkan siswa baru ke dalam sistem.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="h-1 bg-cyan-500 w-full"></div>

        <form action="<?php url('logic/students/store.php'); ?>" method="POST" enctype="multipart/form-data">

            <!-- Section 1: Identitas Siswa -->
            <div class="p-8 border-b border-slate-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd"
                                d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 uppercase">Identitas Siswa</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="nama_siswa" class="block text-sm font-semibold text-slate-700 mb-1">Nama Lengkap
                            Siswa</label>
                        <input type="text" name="nama_siswa" id="nama_siswa" required
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400 capitalize"
                            placeholder="Contoh: Andi Wijaya"
                            oninput="this.value = this.value.replace(/\b\w/g, c => c.toUpperCase())">
                    </div>
                    <div>
                        <label for="nomor_induk" class="block text-sm font-semibold text-slate-700 mb-1">Nomor Induk
                            (NISN)</label>
                        <input type="text" name="nomor_induk" id="nomor_induk" required
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                            placeholder="Masukkan 10 digit NISN">
                    </div>

                    <div>
                        <label for="tempat_lahir" class="block text-sm font-semibold text-slate-700 mb-1">Tempat
                            Lahir</label>
                        <input type="text" name="tempat_lahir" id="tempat_lahir"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                            placeholder="Kota Kelahiran">
                    </div>
                    <div>
                        <label for="tanggal_lahir" class="block text-sm font-semibold text-slate-700 mb-1">Tanggal
                            Lahir</label>
                        <input type="date" name="tanggal_lahir" id="tanggal_lahir"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-slate-600">
                    </div>

                    <div class="md:col-span-2">
                        <label for="alamat" class="block text-sm font-semibold text-slate-700 mb-1">Alamat
                            Lengkap</label>
                        <textarea name="alamat" id="alamat" rows="2"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                            placeholder="Jl. Merdeka No. 123, Kel. Suka Maju, Kec. Jaya"></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: Informasi Akademik -->
            <div class="p-8 border-b border-slate-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path
                                d="M11.7 2.805a.75.75 0 01.6 0A60.65 60.65 0 0122.83 8.72a.75.75 0 01-.231 1.337 49.949 49.949 0 00-9.902 3.912l-.003.002-.34.18a.75.75 0 01-.707 0A50.009 50.009 0 007.5 12.174v-.224c0-.131.067-.248.172-.311a54.614 54.614 0 014.653-2.52.75.75 0 00-.65-1.352 56.129 56.129 0 00-4.78 2.589 1.868 1.868 0 00-.959 1.718v.497c0 1.047.4 2.086 1.171 2.87C9.363 18.497 12.067 20 15 20s5.637-1.503 7.893-4.544a.75.75 0 011.214.893C21.574 20.088 18.067 22 15 22s-6.574-1.912-9.107-5.651A3.75 3.75 0 015 13.5v-.435c0-.621-.614-1.12-1.23-1.157l-1.356-.081a.75.75 0 01-.595-1.026 60.648 60.648 0 0110.88-8.08z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 uppercase">Informasi Akademik</h3>
                </div>
 
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label for="academic_year_id" class="block text-sm font-semibold text-slate-700 mb-1">Tahun
                            Ajaran</label>
                        <select name="academic_year_id" id="academic_year_id" required
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all bg-white text-slate-700">
                            <option value="">Pilih Tahun</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?php echo $year['id']; ?>" <?php echo ($year['id'] == $active_year_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="unit_id" class="block text-sm font-semibold text-slate-700 mb-1">Unit Pendidikan</label>
                        <select name="unit_id" id="unit_id"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all bg-white text-slate-700">
                            <option value="">Semua Unit</option>
                            <?php foreach ($education_units as $unit): ?>
                                <option value="<?php echo $unit['id']; ?>">
                                    <?php echo htmlspecialchars($unit['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="class_id" class="block text-sm font-semibold text-slate-700 mb-1">Kelas</label>
                        <select name="class_id" id="class_id" required
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all bg-white text-slate-700">
                            <option value="">Pilih Kelas</option>
                            <?php foreach ($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" data-unit-id="<?php echo $grade['education_unit_id']; ?>">
                                    <?php echo htmlspecialchars($grade['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[10px] text-slate-500 mt-1">Siswa otomatis masuk ke history kelas ini.</p>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                        <select name="status" id="status"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all bg-white text-slate-700">
                            <option value="Aktif">Aktif</option>
                            <option value="Non_aktif">Non-aktif</option>
                            <option value="Lulus">Lulus</option>
                            <option value="Pindah">Pindah</option>
                            <option value="Dikeluarkan">Dikeluarkan</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 3: Data Keuangan & Foto (Split) -->
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Left: Data Keuangan -->
                    <div>
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="w-5 h-5">
                                    <path d="M4.5 3.75a3 3 0 00-3 3v.75h21v-.75a3 3 0 00-3-3h-15z" />
                                    <path fill-rule="evenodd"
                                        d="M22.5 9.75h-21v7.5a3 3 0 003 3h15a3 3 0 003-3v-7.5zm-18 3.75a.75.75 0 01.75-.75h6a.75.75 0 010 1.5h-6a.75.75 0 01-.75-.75zm.75 2.25a.75.75 0 000 1.5h3a.75.75 0 000-1.5h-3z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h3 class="text-base font-bold text-slate-800 uppercase">Data Keuangan</h3>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label for="spp" class="block text-sm font-semibold text-slate-700 mb-1">Besar SPP
                                    Bulanan</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-slate-500 text-sm">Rp</span>
                                    </div>
                                    <input type="number" name="spp" id="spp"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                                        placeholder="0">
                                </div>
                            </div>
                            <div>
                                <label for="daftar_ulang" class="block text-sm font-semibold text-slate-700 mb-1">Biaya
                                    Daftar Ulang</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-slate-500 text-sm">Rp</span>
                                    </div>
                                    <input type="number" name="daftar_ulang" id="daftar_ulang"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                                        placeholder="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Unggah Foto -->
                    <div>
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="w-5 h-5">
                                    <path d="M12 9a3.75 3.75 0 100 7.5A3.75 3.75 0 0012 9z" />
                                    <path fill-rule="evenodd"
                                        d="M9.344 3.071a4.993 4.993 0 015.312 0l.208.107a.65.65 0 01.325.567V5.5h1.562c2.071 0 3.75 1.679 3.75 3.75v10.5c0 2.071-1.679 3.75-3.75 3.75h-15c-2.071 0-3.75-1.679-3.75-3.75V9.25c0-2.071 1.679-3.75 3.75-3.75h1.562V3.745a.65.65 0 01.325-.567l.208-.107zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h3 class="text-base font-bold text-slate-800 uppercase">Unggah Foto</h3>
                        </div>

                        <label for="foto"
                            class="block w-full h-[140px] border-2 border-dashed border-slate-300 rounded-lg bg-slate-50 hover:bg-slate-100 hover:border-cyan-500 transition-all cursor-pointer flex flex-col items-center justify-center text-center p-6 group">
                            <div
                                class="p-3 bg-white rounded-full shadow-sm mb-3 group-hover:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-cyan-600">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-slate-700">Klik untuk unggah foto siswa</p>
                            <p class="text-xs text-slate-400 mt-1">Format: JPG, PNG (Max. 2MB)</p>
                            <input type="file" name="foto" id="foto" class="hidden">
                        </label>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-x-6 border-t border-slate-100 px-8 py-6">
                <a href="<?php url('views/students/index.php'); ?>"
                    class="text-sm font-semibold leading-6 text-gray-700 hover:text-gray-900">Batalkan</a>
                <button type="submit"
                    class="rounded-lg bg-cyan-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                    Simpan Data Siswa
                </button>
            </div>

        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const unitSelect = document.getElementById('unit_id');
    const classSelect = document.getElementById('class_id');
    if (unitSelect && classSelect) {
        const classOptions = Array.from(classSelect.querySelectorAll('option'));

        unitSelect.addEventListener('change', function() {
            const selectedUnitId = this.value;
            const currentSelectedValue = classSelect.value;
            
            // Clear existing options except the placeholder
            classSelect.innerHTML = '';
            
            // Re-append matching options
            classOptions.forEach(opt => {
                if (opt.value === '') {
                    classSelect.appendChild(opt);
                } else if (!selectedUnitId || opt.dataset.unitId == selectedUnitId) {
                    classSelect.appendChild(opt);
                }
            });
            
            // Restore selection if it's still available in the filtered list
            if (Array.from(classSelect.options).some(opt => opt.value === currentSelectedValue)) {
                classSelect.value = currentSelectedValue;
            } else {
                classSelect.value = '';
            }
        });
    }
});
</script>

<?php include '../layouts/footer.php'; ?>