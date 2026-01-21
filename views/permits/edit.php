<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Edit Permit";

$db = new Database();
$conn = $db->getConnection();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];

// Fetch Permit
$stmt = $conn->prepare("SELECT * FROM permits WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$permit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permit) {
    header("Location: index.php?error=Permit not found");
    exit;
}

// Fetch Employees
$employees = $conn->query("SELECT id, full_name FROM employees ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="text-slate-500 hover:text-slate-700">Home</a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <a href="<?php url('views/permits/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-700">Permits</a>
                </div>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="ml-1 font-medium text-slate-800">Edit Permit</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Edit Permit</h1>
        <p class="mt-2 text-sm text-slate-600">Update permit details.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
        <form action="<?php url('logic/permits/update.php'); ?>" method="POST">
            <input type="hidden" name="id" value="<?php echo $permit['id']; ?>">

            <div class="px-4 py-6 sm:p-8">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                    <!-- Employee -->
                    <div class="sm:col-span-4">
                        <label for="employee_id"
                            class="block text-sm font-medium leading-6 text-gray-900">Employee</label>
                        <div class="mt-2">
                            <select name="employee_id" id="employee_id" required
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm">
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo $permit['employee_id'] == $emp['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Permit Type -->
                    <div class="sm:col-span-4">
                        <label for="permit_type" class="block text-sm font-medium leading-6 text-gray-900">Permit
                            Type</label>
                        <div class="mt-2">
                            <select name="permit_type" id="permit_type" required
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm">
                                <option value="Sick" <?php echo $permit['permit_type'] == 'Sick' ? 'selected' : ''; ?>
                                    >Sick (Sakit)</option>
                                <option value="Leave" <?php echo $permit['permit_type'] == 'Leave' ? 'selected' : ''; ?>
                                    >Leave (Cuti)</option>
                                <option value="Other" <?php echo $permit['permit_type'] == 'Other' ? 'selected' : ''; ?>
                                    >Other (Lainnya)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="sm:col-span-3">
                        <label for="start_date" class="block text-sm font-medium leading-6 text-gray-900">Start
                            Date</label>
                        <div class="mt-2">
                            <input type="date" name="start_date" id="start_date" required
                                value="<?php echo $permit['start_date']; ?>"
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm">
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <label for="end_date" class="block text-sm font-medium leading-6 text-gray-900">End Date</label>
                        <div class="mt-2">
                            <input type="date" name="end_date" id="end_date" required
                                value="<?php echo $permit['end_date']; ?>"
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm">
                        </div>
                    </div>

                    <!-- Reason -->
                    <div class="col-span-full">
                        <label for="reason" class="block text-sm font-medium leading-6 text-gray-900">Reason</label>
                        <div class="mt-2">
                            <textarea id="reason" name="reason" rows="3"
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm"><?php echo htmlspecialchars($permit['reason']); ?></textarea>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="sm:col-span-4">
                        <label for="status" class="block text-sm font-medium leading-6 text-gray-900">Status</label>
                        <div class="mt-2">
                            <select name="status" id="status" required
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all shadow-sm">
                                <option value="Pending" <?php echo $permit['status'] == 'Pending' ? 'selected' : ''; ?>
                                    >Pending</option>
                                <option value="Approved" <?php echo $permit['status'] == 'Approved' ? 'selected' : ''; ?>
                                    >Approved</option>
                                <option value="Rejected" <?php echo $permit['status'] == 'Rejected' ? 'selected' : ''; ?>
                                    >Rejected</option>
                            </select>
                        </div>
                    </div>

                </div>
            </div>
            <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                <a href="<?php url('views/permits/index.php'); ?>"
                    class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
                <button type="submit"
                    class="rounded-md bg-cyan-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">Update
                    Permit</button>
            </div>
        </form>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>