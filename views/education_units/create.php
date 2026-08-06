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
                        <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                        <a href="<?php url('views/education_units/index.php'); ?>"
                            class="hover:text-slate-800">Education Units</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
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
                        <i class="fa-solid fa-book-quran w-5 h-5"></i>
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
                            <i class="fa-solid fa-image w-8 h-8 text-slate-400 group-hover:text-cyan-600"></i>
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
                        <i class="fa-solid fa-calculator w-5 h-5"></i>
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
                                <i class="fa-solid fa-chevron-down h-4 w-4"></i>
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