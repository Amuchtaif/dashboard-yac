<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Add New";

$db = new Database();
$conn = $db->getConnection();

// Fetch Departments
$departments = $conn->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

// Fetch all Units for JS filtering
$units = $conn->query("SELECT * FROM units ORDER BY name ASC")->fetchAll();

include '../layouts/header.php';
?>

<div class="max-w-5xl mx-auto pb-10">
    <div class="mb-8">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
                <li class="inline-flex items-center">
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800">Home</a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                        <a href="<?php url('views/employees/index.php'); ?>" class="hover:text-slate-800">Employees</a>
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
        <h2 class="text-2xl font-bold text-slate-800">Add New Employee</h2>
        <p class="text-slate-500 text-sm mt-1">Enter the details below to register a new employee into the system.</p>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2">
            <i class="fa-solid fa-circle-info h-5 w-5 shrink-0"></i>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form action="<?php url('logic/employees/store.php'); ?>" method="POST" class="space-y-6">

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="h-1 bg-cyan-500 w-full"></div>

            <!-- Personal Information Section -->
            <div class="p-8 border-b border-slate-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                        <i class="fa-solid fa-circle-user w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Personal Information</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Profile Photo Placeholder -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Profile Photo</label>
                        <div
                            class="flex flex-col items-center justify-center w-32 h-32 rounded-full border-2 border-dashed border-slate-300 bg-slate-50 relative group cursor-pointer hover:border-cyan-500 hover:bg-cyan-50 transition-all">
                            <i class="fa-solid fa-id-badge w-8 h-8 text-slate-400 group-hover:text-cyan-600"></i>
                            <span
                                class="absolute bottom-0 right-0 bg-white border border-slate-200 rounded-full p-1.5 shadow-sm">
                                <i class="fa-solid fa-pen w-3 h-3 text-slate-500"></i>
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2 text-center w-32">Upload a professional photo. Max
                            size 2MB.</p>
                    </div>

                    <!-- Personal Fields -->
                    <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="full_name" class="block text-sm font-semibold text-slate-700 mb-1">Full Name
                                <span class="text-red-500">*</span></label>
                            <input type="text" name="full_name" id="full_name" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                                placeholder="e.g. Sarah Johnson">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Email Address
                                <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                                placeholder="sarah@company.com">
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label for="phone_number" class="block text-sm font-semibold text-slate-700 mb-1">Phone
                                Number <span class="text-red-500">*</span></label>
                            <input type="text" name="phone_number" id="phone_number" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                                placeholder="+62 812...">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">Default
                                Password <span class="text-red-500">*</span></label>
                            <input type="password" name="password" id="password" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                                placeholder="••••••••">
                        </div>

                        <!-- Gender (Aligned) -->
                        <div>
                            <label for="gender" class="block text-sm font-semibold text-slate-700 mb-1">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" id="gender" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-slate-600">
                                <option value="">Select Gender</option>
                                <option value="Male">Male (Ikhwan)</option>
                                <option value="Female">Female (Akhwat)</option>
                            </select>
                        </div>

                        <!-- Address (Full Width) -->
                        <div class="md:col-span-2">
                            <label for="address" class="block text-sm font-semibold text-slate-700 mb-1">Address <span
                                    class="text-red-500">*</span></label>
                            <textarea name="address" id="address" rows="3" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                                placeholder="Full residential address..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employment Details Section -->
            <div class="p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                        <i class="fa-solid fa-briefcase w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Employment Details</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Employee ID (Read only mock) -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Employee ID</label>
                        <div class="flex">
                            <span
                                class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-200 bg-slate-50 text-slate-500 text-sm">EMP-</span>
                            <input type="text" readonly value="<?php echo date('Y') . rand(100, 999); ?>"
                                class="rounded-r-lg bg-slate-50 border border-slate-200 text-slate-500 focus:ring-0 focus:border-slate-200 block w-full min-w-0 flex-1 text-sm px-3 py-2.5">
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Auto-generated by system.</p>
                    </div>

                    <!-- Date of Joining Mock -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Date of Joining <span
                                class="text-red-500">*</span></label>
                        <input type="date"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-slate-600">
                    </div>

                    <!-- Department -->
                    <div>
                        <label for="department_id" class="block text-sm font-semibold text-slate-700 mb-1">Department
                            <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="department_id" id="department_id" required onchange="filterUnits()"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all appearance-none bg-white text-slate-700">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                                <i class="fa-solid fa-chevron-down h-4 w-4"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Unit -->
                    <div>
                        <label for="unit_id" class="block text-sm font-semibold text-slate-700 mb-1">Unit <span
                                class="text-xs font-normal text-slate-400 ml-1">(Select Department first)</span></label>
                        <div class="relative">
                            <select name="unit_id" id="unit_id" required disabled
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all appearance-none bg-slate-50 text-slate-400 cursor-not-allowed">
                                <option value="">Select Unit...</option>
                            </select>
                            <div
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
                                <i class="fa-solid fa-chevron-down h-4 w-4"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Job Title Mock -->
                <div class="mt-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Job Title / Designation</label>
                    <input type="text"
                        class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                        placeholder="e.g. Senior Backend Engineer">
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-4 pt-2">
            <a href="<?php url('views/employees/index.php'); ?>"
                class="px-6 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-sm">
                Cancel
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-medium rounded-lg shadow-sm hover:shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">
                Create Employee
            </button>
        </div>
    </form>
</div>

<!-- Dependent Dropdown Logic (Original Logic Preserved) -->
<script>
    const allUnits = <?php echo json_encode($units); ?>;

    function filterUnits() {
        const deptId = document.getElementById('department_id').value;
        const unitSelect = document.getElementById('unit_id');

        // Reset
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
        unitSelect.disabled = true;
        unitSelect.classList.add('bg-slate-50', 'text-slate-400', 'cursor-not-allowed');
        unitSelect.classList.remove('bg-white', 'text-slate-700');

        if (deptId) {
            // Filter
            const filteredUnits = allUnits.filter(unit => unit.department_id == deptId);

            if (filteredUnits.length > 0) {
                filteredUnits.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit.id;
                    option.textContent = unit.name;
                    unitSelect.appendChild(option);
                });

                // Enable
                unitSelect.disabled = false;
                unitSelect.classList.remove('bg-slate-50', 'text-slate-400', 'cursor-not-allowed');
                unitSelect.classList.add('bg-white', 'text-slate-700');
            } else {
                unitSelect.innerHTML = '<option value="">No Units in this Department</option>';
            }
        } else {
            unitSelect.innerHTML = '<option value="">Select Department First</option>';
        }
    }
</script>

<?php include '../layouts/footer.php'; ?>