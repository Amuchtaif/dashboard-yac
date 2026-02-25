<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Add Education Unit";

$db = new Database();
$conn = $db->getConnection();

// Fetch Units for Operational Unit dropdown (Filtered by Division ID 2 - Education, Excl specific names)
$units = $conn->query("SELECT id, name FROM units WHERE division_id = 2 AND name NOT IN ('Pengawas', 'Sub. Kurikulum', 'Staf Bidik') ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <div class="mb-8">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
                <li class="inline-flex items-center">
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800">Home</a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 9 4-4-4-4" />
                        </svg>
                        <a href="<?php url('views/education_units/index.php'); ?>"
                            class="hover:text-slate-800">Education Units</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 9 4-4-4-4" />
                        </svg>
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-cyan-50 text-cyan-700">
                            Add New
                        </span>
                    </div>
                </li>
            </ol>
        </nav>
        <h2 class="text-2xl font-bold text-slate-800">Add New Education Unit</h2>
        <p class="text-slate-500 text-sm mt-1">Register a new school or education unit.</p>
    </div>

    <form action="<?php url('logic/education_units/store.php'); ?>" method="POST" enctype="multipart/form-data"
        class="space-y-6">

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="h-1 bg-cyan-500 w-full"></div>

            <!-- Unit Information Section -->
            <div class="p-8 border-b border-slate-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path
                                d="M11.25 4.533A9.707 9.707 0 006 3.75a9.753 9.753 0 00-3.25.557.75.75 0 00-.5.713v13.715a.75.75 0 00.5.713A9.753 9.753 0 016 18.75c1.55 0 3.038.309 4.385.877a.75.75 0 00.615 0c1.347-.568 2.835-.877 4.385-.877 1.55 0 3.038.309 4.385.877a.75.75 0 00.615 0c1.347-.568 2.835-.877 4.385-.877a.75.75 0 00.5-.713V5.02a.75.75 0 00-.5-.713A9.753 9.753 0 0018 3.75c-1.55 0-3.038.309-4.385.877A9.707 9.707 0 0012 3.75a9.707 9.707 0 00-.75.783z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Unit Information</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Icon Upload -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Unit Logo/Icon</label>
                        <div
                            class="flex flex-col items-center justify-center w-32 h-32 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 relative group cursor-pointer hover:border-cyan-500 hover:bg-cyan-50 transition-all">
                            <input type="file" name="icon" id="icon"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="image/*">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-8 h-8 text-slate-400 group-hover:text-cyan-600">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                            </svg>
                            <p class="text-[10px] text-slate-400 mt-2 text-center">Click to Upload</p>
                        </div>
                    </div>

                    <!-- Input Fields -->
                    <div class="md:col-span-3 grid grid-cols-1 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-semibold text-slate-700 mb-1">Unit Name <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                                placeholder="e.g. SDIT">
                        </div>

                        <div>
                            <label for="description"
                                class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                            <textarea name="description" id="description" rows="3"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                                placeholder="Short description of the unit..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuration Section -->
            <div class="p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd"
                                d="M3 6a3 3 0 013-3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6zm4.5 7.5a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0v-2.25a.75.75 0 01.75-.75zm3.75-1.5a.75.75 0 00-1.5 0v4.5a.75.75 0 001.5 0V12zm2.25-3a.75.75 0 01.75.75v6.75a.75.75 0 01-1.5 0V9.75A.75.75 0 0113.5 9zm3.75-1.5a.75.75 0 00-1.5 0v9a.75.75 0 001.5 0v-9z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Configuration</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Operational Unit -->
                    <div class="col-span-1 md:col-span-2">
                        <label for="operational_unit_id"
                            class="block text-sm font-semibold text-slate-700 mb-1">Operational Unit (Staff)</label>
                        <div class="relative">
                            <select name="operational_unit_id" id="operational_unit_id"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all appearance-none bg-white text-slate-700">
                                <option value="">Select Operational Unit</option>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2">Link to an operational unit to calculate Teacher
                            count automatically.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-4 pt-2">
            <a href="<?php url('views/education_units/index.php'); ?>"
                class="px-6 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-sm">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-medium rounded-lg shadow-sm hover:shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">
                Save Unit
            </button>
        </div>
    </form>
</div>

<?php include '../layouts/footer.php'; ?>