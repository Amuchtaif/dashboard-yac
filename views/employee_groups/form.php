<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_title = $id > 0 ? "Ubah Grup Karyawan" : "Tambah Grup Karyawan";

include '../layouts/header.php';
?>

<div class="pb-10 max-w-7xl mx-auto" id="appContainer" data-id="<?php echo $id; ?>">
    <!-- Breadcrumbs -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 flex items-center gap-1">
                    Beranda
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4" />
                    </svg>
                    <a href="<?php url('views/employee_groups/index.php'); ?>" class="ml-1 hover:text-slate-800">Pengelompokan Karyawan</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 text-slate-800 font-medium"><?php echo $id > 0 ? 'Ubah' : 'Tambah'; ?> Grup</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight" id="pageHeaderTitle">
                <?php echo $page_title; ?>
            </h2>
        </div>
    </div>

    <form id="groupForm" class="space-y-6">
        <!-- Basic Info Section -->
        <div class="bg-white shadow-sm ring-1 ring-slate-200 rounded-xl overflow-hidden">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-base font-semibold leading-6 text-slate-900 border-b border-slate-200 pb-4 mb-4">Informasi Dasar</h3>
                
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <!-- Nama Grup -->
                    <div class="sm:col-span-4">
                        <label for="group_name" class="block text-sm font-semibold text-slate-700 mb-1">Nama Grup <span class="text-red-500">*</span></label>
                        <div class="mt-2">
                            <input type="text" name="group_name" id="group_name" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="masukan nama grup">
                        </div>
                    </div>

                    <!-- Jenis Grup -->
                    <div class="sm:col-span-2">
                        <label for="group_type" class="block text-sm font-semibold text-slate-700 mb-1">Jenis Grup <span class="text-red-500">*</span></label>
                        <div class="mt-2">
                            <select id="group_type" name="group_type" required <?php echo $id > 0 ? 'disabled' : ''; ?>
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all select-custom shadow-sm">
                                <option value="dynamic">Dynamic Group</option>
                                <option value="manual">Manual Group</option>
                            </select>
                            <?php if($id > 0): ?>
                                <p class="mt-1 text-xs text-slate-500">Jenis grup tidak dapat diubah setelah dibuat.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="sm:col-span-2">
                        <label for="is_active" class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                        <div class="mt-2">
                            <select id="is_active" name="is_active"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all select-custom shadow-sm">
                                <option value="1">Aktif</option>
                                <option value="0">Tidak Aktif</option>
                            </select>
                        </div>
                    </div>

                    <!-- Deskripsi -->
                    <div class="sm:col-span-6">
                        <label for="description" class="block text-sm font-semibold text-slate-700 mb-1">Deskripsi</label>
                        <div class="mt-2">
                            <textarea id="description" name="description" rows="3"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="masukan deskripsi kelompok..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dynamic Group Builder Section -->
        <div id="dynamicSection" class="transition-all duration-300">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Left: Builder Rule -->
                <div class="lg:col-span-3 bg-white shadow-sm ring-1 ring-slate-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-4 mb-4">
                            <div>
                                <h3 class="text-base font-semibold leading-6 text-slate-900">Builder Rule</h3>
                                <p class="mt-1 text-sm text-slate-500">Buat aturan untuk menambahkan anggota secara dinamis.</p>
                            </div>
                            <div>
                                <button type="button" id="previewBtn" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                                    Preview Anggota
                                </button>
                            </div>
                        </div>

                        <div id="rulesContainer" class="space-y-4">
                            <!-- Rules will be injected here via JS -->
                        </div>

                        <div class="mt-4 pt-4 border-t border-slate-100 border-dashed">
                            <button type="button" id="addRuleBtn" class="inline-flex items-center text-sm font-medium text-[#2B3990] hover:text-blue-800">
                                <svg class="mr-1 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                                </svg>
                                Tambah Rule
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right: Kamus Referensi Value -->
                <div class="lg:col-span-1 bg-white shadow-sm ring-1 ring-slate-200 rounded-xl overflow-hidden flex flex-col self-start">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-sm font-semibold text-slate-850 mb-4 flex items-center gap-1.5">
                            <svg class="w-4.5 h-4.5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Panduan ID & Value
                        </h3>
                        <div class="space-y-3">
                            <!-- Category: Unit -->
                            <details class="group border border-slate-100 rounded-lg bg-slate-50 overflow-hidden" open>
                                <summary class="flex justify-between items-center font-medium text-xs text-slate-700 p-3 cursor-pointer select-none hover:bg-slate-100">
                                    <span>Unit</span>
                                    <span class="transition group-open:rotate-180">
                                        <svg fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="24" class="w-4 h-4"><path d="M6 9l6 6 6-6"></path></svg>
                                    </span>
                                </summary>
                                <div class="p-3 border-t border-slate-100 bg-white text-xs space-y-1 text-slate-600 font-mono">
                                    <div class="flex justify-between"><span>11: TKIT</span></div>
                                    <div class="flex justify-between"><span>12: SDIT</span></div>
                                    <div class="flex justify-between"><span>13: MTs</span></div>
                                    <div class="flex justify-between"><span>14: MA</span></div>
                                    <div class="flex justify-between"><span>15: Ma'had Aly</span></div>
                                    <div class="flex justify-between"><span>16: Ma'had</span></div>
                                    <div class="flex justify-between"><span>25: Playgroup</span></div>
                                    <div class="flex justify-between"><span>17: Media Official</span></div>
                                    <div class="flex justify-between"><span>18: Sub. Kurikulum</span></div>
                                    <div class="flex justify-between"><span>20: Keamanan</span></div>
                                    <div class="flex justify-between"><span>24: Kebersihan</span></div>
                                </div>
                            </details>

                            <!-- Category: Departemen -->
                            <details class="group border border-slate-100 rounded-lg bg-slate-50 overflow-hidden">
                                <summary class="flex justify-between items-center font-medium text-xs text-slate-700 p-3 cursor-pointer select-none hover:bg-slate-100">
                                    <span>Departemen (Divisi)</span>
                                    <span class="transition group-open:rotate-180">
                                        <svg fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="24" class="w-4 h-4"><path d="M6 9l6 6 6-6"></path></svg>
                                    </span>
                                </summary>
                                <div class="p-3 border-t border-slate-100 bg-white text-xs space-y-1 text-slate-600 font-mono">
                                    <div>1: Mudir Yayasan</div>
                                    <div>2: Pendidikan</div>
                                    <div>3: Ekonomi</div>
                                    <div>4: Dakwah & Sosial</div>
                                    <div>5: Bendahara</div>
                                    <div>6: Personalia & Sekr.</div>
                                    <div>7: Umum</div>
                                </div>
                            </details>

                            <!-- Category: Jabatan -->
                            <details class="group border border-slate-100 rounded-lg bg-slate-50 overflow-hidden">
                                <summary class="flex justify-between items-center font-medium text-xs text-slate-700 p-3 cursor-pointer select-none hover:bg-slate-100">
                                    <span>Jabatan</span>
                                    <span class="transition group-open:rotate-180">
                                        <svg fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="24" class="w-4 h-4"><path d="M6 9l6 6 6-6"></path></svg>
                                    </span>
                                </summary>
                                <div class="p-3 border-t border-slate-100 bg-white text-xs space-y-1 text-slate-600 font-mono max-h-48 overflow-y-auto">
                                    <div>1: Mudir</div>
                                    <div>2: Kepala Bidang</div>
                                    <div>3: Kepala Unit</div>
                                    <div>4: Guru</div>
                                    <div>5: Staf</div>
                                    <div>6: Musyrif</div>
                                    <div>7: Musyrifah</div>
                                    <div>8: Koordinator</div>
                                    <div>9: Kepala Sub</div>
                                    <div>10: Keamanan</div>
                                    <div>11: Kebersihan</div>
                                    <div>12: Koord. Tahfidz</div>
                                    <div>13: Administrator</div>
                                    <div>17: Guru Tahfidz</div>
                                    <div>18: Guru Tahsin</div>
                                </div>
                            </details>

                            <!-- Category: Lainnya -->
                            <details class="group border border-slate-100 rounded-lg bg-slate-50 overflow-hidden">
                                <summary class="flex justify-between items-center font-medium text-xs text-slate-700 p-3 cursor-pointer select-none hover:bg-slate-100">
                                    <span>Gender & Status</span>
                                    <span class="transition group-open:rotate-180">
                                        <svg fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="24" class="w-4 h-4"><path d="M6 9l6 6 6-6"></path></svg>
                                    </span>
                                </summary>
                                <div class="p-3 border-t border-slate-100 bg-white text-xs space-y-2 text-slate-600 font-mono">
                                    <div>
                                        <strong class="text-slate-800">Gender:</strong><br>
                                        - Male (Ikhwan)<br>
                                        - Female (Akhwat)
                                    </div>
                                    <div>
                                        <strong class="text-slate-800">Status:</strong><br>
                                        - active (Aktif)<br>
                                        - inactive (Tidak Aktif)
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Group Members Section -->
        <div id="manualSection" class="hidden bg-white shadow-sm ring-1 ring-slate-200 rounded-xl overflow-hidden transition-all duration-300">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4 mb-4">
                    <div>
                        <h3 class="text-base font-semibold leading-6 text-slate-900">Pilih Anggota</h3>
                        <p class="mt-1 text-sm text-slate-500">Tambahkan karyawan ke dalam grup secara manual.</p>
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- Left: Search Employee -->
                    <div class="lg:w-1/2 flex flex-col border border-slate-200 rounded-lg overflow-hidden h-[500px]">
                        <div class="p-3 bg-slate-50 border-b border-slate-200 flex items-center gap-2">
                            <input type="text" id="employeeSearch" placeholder="Cari nama karyawan..." class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm">
                            <button type="button" id="bulkAddBtn" class="text-xs font-semibold bg-white border border-slate-300 rounded px-2 py-1.5 hover:bg-slate-50 text-slate-700 whitespace-nowrap">Tambah Semua</button>
                        </div>
                        <div class="overflow-y-auto flex-1 bg-white" id="employeeList">
                            <!-- Employee list injected here -->
                            <div class="p-4 text-center text-sm text-slate-500">Mencari karyawan...</div>
                        </div>
                    </div>

                    <!-- Right: Selected Members -->
                    <div class="lg:w-1/2 flex flex-col border border-slate-200 rounded-lg overflow-hidden h-[500px]">
                        <div class="p-3 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                            <span class="text-sm font-semibold text-slate-700">Terpilih (<span id="selectedCount">0</span>)</span>
                            <button type="button" id="removeAllBtn" class="text-xs text-rose-600 hover:text-rose-800 font-medium">Hapus Semua</button>
                        </div>
                        <div class="overflow-y-auto flex-1 bg-white" id="selectedList">
                            <!-- Selected list injected here -->
                            <div class="p-4 text-center text-sm text-slate-500" id="selectedEmpty">Belum ada anggota yang dipilih</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-x-6 border-t border-slate-200 pt-6">
            <a href="<?php url('views/employee_groups/index.php'); ?>" class="text-sm font-semibold leading-6 text-slate-900 hover:text-slate-700">Batal</a>
            <button type="submit" id="saveBtn" class="rounded-md bg-[#2B3990] px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2B3990]">
                Simpan Grup
            </button>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
  <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
      <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl">
        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
              <h3 class="text-lg font-semibold leading-6 text-gray-900 border-b pb-2" id="modal-title">Preview Anggota Dynamic Group</h3>
              
              <div class="mt-4 flex gap-4">
                  <div class="bg-cyan-50 text-cyan-700 px-3 py-2 rounded-lg text-sm font-medium">Total: <span id="previewTotal">0</span> Karyawan</div>
                  <div class="bg-slate-50 text-slate-700 px-3 py-2 rounded-lg text-sm">Rules: <span id="previewSummary" class="font-medium">-</span></div>
              </div>

              <div class="mt-4 border rounded-lg overflow-hidden h-[400px] overflow-y-auto">
                  <table class="min-w-full divide-y divide-slate-200 text-sm">
                      <thead class="bg-slate-50 sticky top-0">
                          <tr>
                              <th class="px-3 py-2 text-left font-semibold text-slate-900">Nama</th>
                              <th class="px-3 py-2 text-left font-semibold text-slate-900">NIK</th>
                              <th class="px-3 py-2 text-left font-semibold text-slate-900">Unit ID</th>
                              <th class="px-3 py-2 text-left font-semibold text-slate-900">Divisi ID</th>
                              <th class="px-3 py-2 text-left font-semibold text-slate-900">Status</th>
                          </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-200 bg-white" id="previewTableBody">
                          <!-- Injected via JS -->
                      </tbody>
                  </table>
              </div>
              
            </div>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
          <button type="button" onclick="closePreviewModal()" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Tutup</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Rule Template -->
<template id="ruleTemplate">
    <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200 rule-item relative group">
        <div class="w-1/3">
            <select class="rule-field w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all select-custom shadow-sm">
                <option value="Unit">Unit</option>
                <option value="Departemen">Departemen</option>
                <option value="Jabatan">Jabatan</option>
                <option value="Gender">Gender</option>
                <option value="Status Karyawan">Status Karyawan</option>
                <option value="Jam Ngajar di Unit">Jam Ngajar di Unit</option>
            </select>
        </div>
        <div class="w-1/4">
            <select class="rule-operator w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all select-custom shadow-sm">
                <option value="=">=</option>
                <option value="!=">!=</option>
            </select>
        </div>
        <div class="w-1/3">
            <input type="text" class="rule-value w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm" placeholder="Value">
        </div>
        <div>
            <button type="button" class="rule-remove text-slate-400 hover:text-rose-500 p-1 rounded-md hover:bg-white transition-colors">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                </svg>
            </button>
        </div>
    </div>
</template>

<script>
    const APP_URL = "<?php echo BASE_URL; ?>";
</script>
<script src="<?php echo url('assets/js/employee_groups.js') . '?v=' . time(); ?>"></script>

<?php include '../layouts/footer.php'; ?>
