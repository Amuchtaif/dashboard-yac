<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = !empty($id);
$page_title = $is_edit ? "Edit Employee" : "Add New Employee";

$employee = [
    'full_name' => '',
    'email' => '',
    'phone_number' => '',
    'address' => '',
    'department_id' => '',
    'unit_id' => '',
    'schedule_id' => '',
    'id' => ''
];

if ($is_edit) {
    try {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $fetched = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fetched) {
            $employee = $fetched;
        } else {
            header("Location: index.php?error=Employee not found");
            exit;
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Fetch all necessary data
$departments = $conn->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$units = $conn->query("SELECT * FROM units ORDER BY name ASC")->fetchAll();
$schedules = $conn->query("SELECT * FROM work_schedules ORDER BY name ASC")->fetchAll();

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
                        <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 9 4-4-4-4" />
                        </svg>
                        <a href="<?php url('views/employees/index.php'); ?>" class="hover:text-slate-800">Employees</a>
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
                            <?php echo $is_edit ? "Edit" : "Add New"; ?>
                        </span>
                    </div>
                </li>
            </ol>
        </nav>
        <h2 class="text-2xl font-bold text-slate-800"><?php echo $is_edit ? "Edit Employee" : "Add New Employee"; ?>
        </h2>
        <p class="text-slate-500 text-sm mt-1">
            <?php echo $is_edit ? "Update the details for " . htmlspecialchars($employee['full_name']) : "Enter the details below to register a new employee into the system."; ?>
        </p>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd" />
            </svg>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo $is_edit ? url('logic/employees/update.php') : url('logic/employees/store.php'); ?>"
        method="POST" class="space-y-6">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
        <?php endif; ?>

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="h-1 bg-cyan-500 w-full"></div>

            <!-- Personal Information Section -->
            <div class="p-8 border-b border-slate-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd"
                                d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Personal Information</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Profile Photo Placeholder -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Profile Photo</label>
                        <div
                            class="flex flex-col items-center justify-center w-32 h-32 rounded-full border-2 border-dashed border-slate-300 bg-slate-50 relative group cursor-pointer hover:border-cyan-500 hover:bg-cyan-50 transition-all">
                            <?php if ($is_edit): ?>
                                <img class="w-full h-full rounded-full object-cover"
                                    src="https://ui-avatars.com/api/?name=<?php echo urlencode($employee['full_name']); ?>&background=random&size=128"
                                    alt="">
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor" class="w-8 h-8 text-slate-400 group-hover:text-cyan-600">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                                </svg>
                            <?php endif; ?>
                            <span
                                class="absolute bottom-0 right-0 bg-white border border-slate-200 rounded-full p-1.5 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                    class="w-3 h-3 text-slate-500">
                                    <path
                                        d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                                    <path
                                        d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" />
                                </svg>
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
                                value="<?php echo htmlspecialchars($employee['full_name']); ?>"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="e.g. Sarah Johnson">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Email Address
                                <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required
                                value="<?php echo htmlspecialchars($employee['email']); ?>"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="sarah@company.com">
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label for="phone_number" class="block text-sm font-semibold text-slate-700 mb-1">Phone
                                Number <span class="text-red-500">*</span></label>
                            <input type="text" name="phone_number" id="phone_number" required
                                value="<?php echo htmlspecialchars($employee['phone_number']); ?>"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="+62 812...">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">
                                <?php echo $is_edit ? "New Password" : "Default Password"; ?>
                                <span
                                    class="<?php echo $is_edit ? "text-slate-400 text-xs font-normal" : "text-red-500"; ?>">
                                    <?php echo $is_edit ? "(Leave blank to keep)" : "*"; ?>
                                </span>
                            </label>
                            <input type="password" name="password" id="password" <?php echo $is_edit ? '' : 'required'; ?>
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="••••••••">
                        </div>

                        <!-- Address (Full Width) -->
                        <div class="md:col-span-2">
                            <label for="address" class="block text-sm font-semibold text-slate-700 mb-1">Address <span
                                    class="text-red-500">*</span></label>
                            <textarea name="address" id="address" rows="3" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="Full residential address..."><?php echo htmlspecialchars($employee['address']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employment Details Section -->
            <div class="p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd"
                                d="M7.5 5.25a3 3 0 013-3h3a3 3 0 013 3v.25a3 3 0 013 3v1.5a3 3 0 01-3 3v.25h-9v-.25a3 3 0 01-3-3v-1.5a3 3 0 013-3V5.25zM3.75 21a.75.75 0 01.75-.75h15a.75.75 0 010 1.5H4.5a.75.75 0 01-.75-.75zm4.266-4.5H15.98a3 3 0 001.996.75 2.25 2.25 0 002.247-2.072l.027-.333a3.751 3.751 0 00-3.753-4.045H7.501A3.751 3.751 0 003.75 14.8l.026.333A2.25 2.25 0 006.023 17.25a3 3 0 001.993-.75z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Employment Details</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Employee ID (Read only mock) -->
                    <?php if (!$is_edit): ?>
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
                    <?php endif; ?>

                    <!-- Department -->
                    <div>
                        <label for="department_id" class="block text-sm font-semibold text-slate-700 mb-1">Department
                            <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="department_id" id="department_id" required onchange="filterUnits()"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all appearance-none bg-white placeholder:text-slate-400 shadow-sm">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo ($dept['id'] == $employee['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
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
                    </div>

                    <!-- Unit -->
                    <div>
                        <label for="unit_id" class="block text-sm font-semibold text-slate-700 mb-1">Unit <span
                                class="text-xs font-normal text-slate-400 ml-1">(Select Department first)</span></label>
                        <div class="relative">
                            <select name="unit_id" id="unit_id" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all appearance-none bg-white placeholder:text-slate-400 shadow-sm">
                                <option value="">Select Unit...</option>
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
                    </div>

                    <!-- Work Schedule -->
                    <div class="md:col-span-2">
                        <label for="schedule_id" class="block text-sm font-semibold text-slate-700 mb-1">Work
                            Schedule</label>
                        <p class="text-xs text-slate-500 mb-2">Override the department's default schedule if needed.
                            Leave default to follow department.</p>
                        <div class="relative">
                            <select name="schedule_id" id="schedule_id"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all appearance-none bg-white text-slate-700">
                                <option value="">Follow Department Default</option>
                                <?php foreach ($schedules as $sched): ?>
                                    <option value="<?php echo $sched['id']; ?>" <?php echo ($employee['schedule_id'] == $sched['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sched['name']); ?>
                                        (<?php echo date('H:i', strtotime($sched['start_time'])) . ' - ' . date('H:i', strtotime($sched['end_time'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <?php if (!$is_edit): ?>
                        <!-- Job Title Mock -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Job Title / Designation</label>
                            <input type="text"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"
                                placeholder="e.g. Senior Backend Engineer">
                        </div>
                    <?php endif; ?>

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
                <?php echo $is_edit ? "Update Employee" : "Create Employee"; ?>
            </button>
        </div>
    </form>
</div>

<script>
    const allUnits = <?php echo json_encode($units); ?>;
    const currentUnitId = "<?php echo $employee['unit_id']; ?>";

    function filterUnits() {
        const deptId = document.getElementById('department_id').value;
        const unitSelect = document.getElementById('unit_id');

        // Reset
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
        unitSelect.disabled = true;
        // Restore styles if previously disabled
        unitSelect.classList.remove('bg-white', 'text-slate-700');
        unitSelect.classList.add('bg-slate-50', 'text-slate-400');

        if (deptId) {
            // Filter
            const filteredUnits = allUnits.filter(unit => unit.department_id == deptId);

            if (filteredUnits.length > 0) {
                filteredUnits.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit.id;
                    option.textContent = unit.name;
                    if (unit.id == currentUnitId) {
                        option.selected = true;
                    }
                    unitSelect.appendChild(option);
                });

                // Enable
                unitSelect.disabled = false;
                unitSelect.classList.remove('bg-slate-50', 'text-slate-400');
                unitSelect.classList.add('bg-white', 'text-slate-700');
            } else {
                unitSelect.innerHTML = '<option value="">No Units in this Department</option>';
            }
        } else {
            unitSelect.innerHTML = '<option value="">Select Department First</option>';
        }
    }

    // Run on load
    document.addEventListener('DOMContentLoaded', filterUnits);
</script>

<?php include '../layouts/footer.php'; ?>