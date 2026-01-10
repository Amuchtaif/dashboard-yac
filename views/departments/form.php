<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = !empty($id);
$page_title = $is_edit ? "Edit Department" : "Add Department";

// Fetch Schedules
$schedules = $conn->query("SELECT * FROM work_schedules ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$department = [
    'name' => '',
    'schedule_id' => ''
];

if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM departments WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        header("Location: " . BASE_URL . "views/departments/index.php?error=Department not found");
        exit;
    }
}

include '../layouts/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="inline-flex items-center text-slate-500 hover:text-slate-700">
                    Home
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <a href="<?php url('views/departments/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-700">Departments</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 font-medium text-slate-800">
                        <?php echo $is_edit ? "Edit" : "Add New"; ?>
                    </span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">
            <?php echo $is_edit ? "Edit Department" : "Add New Department"; ?>
        </h1>
        <p class="mt-2 text-sm text-slate-600">
            <?php echo $is_edit ? "Update department details and default schedule." : "Create a new department to organize your workforce."; ?>
        </p>
    </div>

    <!-- Alerts -->
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

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
        <form
            action="<?php echo $is_edit ? url('logic/departments/update.php') : url('logic/departments/create.php'); ?>"
            method="POST">
            <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
            <?php endif; ?>

            <div class="px-4 py-6 sm:p-8">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                    <!-- Department Name -->
                    <div class="sm:col-span-4">
                        <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Department
                            Name</label>
                        <div class="mt-2">
                            <input type="text" name="name" id="name" required
                                value="<?php echo htmlspecialchars($department['name']); ?>"
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="e.g. Finance">
                        </div>
                    </div>

                    <!-- Default Work Schedule -->
                    <div class="sm:col-span-4">
                        <label for="schedule_id" class="block text-sm font-medium leading-6 text-gray-900">Default Work
                            Schedule</label>
                        <p class="text-xs text-slate-500 mb-2">Select the default shift for employees in this
                            department.</p>
                        <div class="mt-2">
                            <select name="schedule_id" id="schedule_id" required
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all appearance-none bg-white placeholder:text-slate-400 shadow-sm">
                                <option value="">Select a Schedule...</option>
                                <?php foreach ($schedules as $schedule): ?>
                                    <option value="<?php echo $schedule['id']; ?>" <?php echo ($department['schedule_id'] == $schedule['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($schedule['name']) . " (" . date('H:i', strtotime($schedule['start_time'])) . " - " . date('H:i', strtotime($schedule['end_time'])) . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                </div>
            </div>
            <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                <a href="<?php url('views/departments/index.php'); ?>"
                    class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
                <button type="submit"
                    class="rounded-md bg-cyan-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                    <?php echo $is_edit ? "Update Department" : "Save Department"; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>