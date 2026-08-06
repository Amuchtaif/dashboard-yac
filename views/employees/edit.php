<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Edit Employee";

$db = new Database();
$conn = $db->getConnection();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];
try {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        header("Location: index.php?error=Pegawai tidak ditemukan");
        exit;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

// Fetch Departments
$departments = $conn->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

// Fetch Units
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
                            Edit
                        </span>
                    </div>
                </li>
            </ol>
        </nav>
        <h2 class="text-2xl font-bold text-slate-800">Edit Employee</h2>
        <p class="text-slate-500 text-sm mt-1">Update the details for
            <?php echo htmlspecialchars($employee['full_name']); ?>.
        </p>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2">
            <i class="fa-solid fa-circle-info h-5 w-5 shrink-0"></i>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form action="<?php url('logic/employees/update.php'); ?>" method="POST" class="space-y-6">
        <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">

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
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Foto Profil</label>
                        <?php
                        $photo_url = null;
                        if (!empty($employee['profile_photo'])) {
                            if (file_exists(BASE_PATH . '/uploads/profile_photos/' . $employee['profile_photo'])) {
                                $photo_url = BASE_URL . '/uploads/profile_photos/' . $employee['profile_photo'];
                            } elseif (file_exists(BASE_PATH . '/public/uploads/employees/' . $employee['profile_photo'])) {
                                $photo_url = BASE_URL . '/public/uploads/employees/' . $employee['profile_photo'];
                            }
                        }
                        if (!$photo_url) {
                            $photo_url = "https://ui-avatars.com/api/?name=" . urlencode($employee['full_name'] ?? 'User') . "&background=0891b2&color=fff&size=128&bold=true";
                        }
                        ?>
                        <div
                            class="flex flex-col items-center justify-center w-32 h-32 rounded-full border-2 border-dashed border-slate-300 bg-slate-50 relative group transition-all overflow-hidden mx-auto md:mx-0 shadow-sm">
                            <img class="w-full h-full rounded-full object-cover"
                                src="<?php echo htmlspecialchars($photo_url); ?>"
                                alt="Foto Profil">
                        </div>
                    </div>

                    <!-- Personal Fields -->
                    <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="full_name" class="block text-sm font-semibold text-slate-700 mb-1">Full Name
                                <span class="text-red-500">*</span></label>
                            <input type="text" name="full_name" id="full_name" required
                                value="<?php echo htmlspecialchars($employee['full_name']); ?>"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Email Address
                                <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required
                                value="<?php echo htmlspecialchars($employee['email']); ?>"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400">
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label for="phone_number" class="block text-sm font-semibold text-slate-700 mb-1">Phone
                                Number <span class="text-red-500">*</span></label>
                            <input type="text" name="phone_number" id="phone_number" required
                                value="<?php echo htmlspecialchars($employee['phone_number']); ?>"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">New Password
                                <span class="text-xs font-normal text-slate-400">(Leave blank to keep
                                     current)</span></label>
                            <input type="password" name="password" id="password"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400">
                        </div>

                        <!-- Gender -->
                        <div>
                            <label for="gender" class="block text-sm font-semibold text-slate-700 mb-1">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" id="gender" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-slate-600">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($employee['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male (Ikhwan)</option>
                                <option value="Female" <?php echo ($employee['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female (Akhwat)</option>
                            </select>
                        </div>


                        <!-- Address (Full Width) -->
                        <div class="md:col-span-2">
                            <label for="address" class="block text-sm font-semibold text-slate-700 mb-1">Address <span
                                    class="text-red-500">*</span></label>
                            <textarea name="address" id="address" rows="3" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all placeholder:text-slate-400"><?php echo htmlspecialchars($employee['address']); ?></textarea>
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
                    <!-- Department -->
                    <div>
                        <label for="department_id" class="block text-sm font-semibold text-slate-700 mb-1">Department
                            <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="department_id" id="department_id" required onchange="filterUnits()"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all appearance-none bg-white text-slate-700">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo ($dept['id'] == $employee['department_id']) ? 'selected' : ''; ?>>
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
                        <label for="unit_id" class="block text-sm font-semibold text-slate-700 mb-1">Unit</label>
                        <div class="relative">
                            <select name="unit_id" id="unit_id" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all appearance-none bg-white text-slate-700">
                                <option value="">Select Unit</option>
                                <!-- JS will populate or PHP fallback -->
                            </select>
                            <div
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                                <i class="fa-solid fa-chevron-down h-4 w-4"></i>
                            </div>
                        </div>
                    </div>
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
                Update Employee
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

        if (deptId) {
            // Filter
            const filteredUnits = allUnits.filter(unit => unit.department_id == deptId);

            if (filteredUnits.length > 0) {
                filteredUnits.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit.id;
                    option.textContent = unit.name;
                    if (unit.id == currentUnitId) { // Pre-select
                        option.selected = true;
                    }
                    unitSelect.appendChild(option);
                });

                // Enable
                unitSelect.disabled = false;
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