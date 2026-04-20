<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = !empty($id);
$page_title = $is_edit ? "Edit Pegawai" : "Tambah Pegawai Baru";

$employee = [
    'full_name' => '',
    'email' => '',
    'phone_number' => '',
    'address' => '',
    'division_id' => '',
    'unit_id' => '',
    'position_id' => '',
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
            header("Location: index.php?error=Pegawai tidak ditemukan");
            exit;
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Fetch all necessary data
$divisions = $conn->query("SELECT * FROM divisions ORDER BY id ASC")->fetchAll();
$units = $conn->query("SELECT * FROM units ORDER BY name ASC")->fetchAll();
$positions = $conn->query("SELECT * FROM positions ORDER BY level ASC")->fetchAll();
$schedules = $conn->query("SELECT * FROM work_schedules ORDER BY name ASC")->fetchAll();

include '../layouts/header.php';

// Capture return filters
$return_filters = $_GET;
unset($return_filters['id'], $return_filters['error'], $return_filters['success']);
$return_filters_qs = http_build_query($return_filters);
?>

<div class="w-full pb-10">
    <div class="mb-8">
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
                        <a href="<?php url('views/employees/index.php'); ?>" class="hover:text-slate-800">Pegawai</a>
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
                            <?php echo $is_edit ? "Edit" : "Tambah Baru"; ?>
                        </span>
                    </div>
                </li>
            </ol>
        </nav>
        <h2 class="text-2xl font-bold text-slate-800"><?php echo $is_edit ? "Edit Pegawai" : "Tambah Pegawai Baru"; ?>
        </h2>
        <p class="text-slate-500 text-sm mt-1">
            <?php echo $is_edit ? "Perbarui rincian untuk " . htmlspecialchars($employee['full_name']) : "Masukkan rincian di bawah ini untuk mendaftarkan pegawai baru ke dalam sistem."; ?>
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
        method="POST" enctype="multipart/form-data" class="space-y-6">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
        <?php endif; ?>
        <input type="hidden" name="return_filters" value="<?php echo htmlspecialchars($return_filters_qs); ?>">

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200">
            <div class="h-1 bg-cyan-500 w-full rounded-t-xl"></div>

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
                    <h3 class="text-base font-bold text-slate-800">Informasi Pribadi</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Profile Photo Placeholder -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Foto Profil</label>
                        <div class="relative w-32 h-32 mx-auto">
                            <input type="file" name="profile_photo" id="profile_photo_input" class="hidden"
                                accept="image/*" onchange="previewImage(this)">
                            <input type="hidden" name="remove_photo" id="remove_photo_input" value="0">

                            <div onclick="document.getElementById('profile_photo_input').click()"
                                class="flex flex-col items-center justify-center w-32 h-32 rounded-full border-2 border-dashed border-slate-300 bg-slate-50 relative group cursor-pointer hover:border-cyan-500 hover:bg-cyan-50 transition-all overflow-hidden">
                                <?php
                                $photo_url = null;
                                if (!empty($employee['profile_photo']) && file_exists(BASE_PATH . '/uploads/profile_photos/' . $employee['profile_photo'])) {
                                    $photo_url = BASE_URL . 'uploads/profile_photos/' . $employee['profile_photo'];
                                }
                                ?>
                                <img id="profile_preview"
                                    class="w-full h-full rounded-full object-cover <?php echo $photo_url ? '' : 'hidden'; ?>"
                                    src="<?php echo $photo_url ?: ''; ?>" alt="">

                                <div id="photo_placeholder"
                                    class="<?php echo $photo_url ? 'hidden' : 'flex'; ?> flex-col items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor"
                                        class="w-8 h-8 text-slate-400 group-hover:text-cyan-600">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                                    </svg>
                                </div>

                                <!-- Overlay on hover -->
                                <div
                                    class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="2" stroke="currentColor" class="w-6 h-6 text-white">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Remove button -->
                            <button type="button" id="remove_photo_btn" onclick="removeImage()"
                                class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full p-1 shadow-md hover:bg-red-600 transition-all <?php echo $photo_url ? '' : 'hidden'; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                    class="w-4 h-4">
                                    <path
                                        d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                </svg>
                            </button>
                        </div>
                        <p
                            class="text-[10px] text-slate-400 mt-3 text-center w-full uppercase tracking-wider font-bold">
                            Ketuk untuk ubah foto</p>
                    </div>

                    <!-- Personal Fields -->
                    <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label for="nik" class="block text-sm font-semibold text-slate-700 mb-1">NIK (Nomor Induk
                                Karyawan)
                                <span class="text-red-500">*</span></label>
                            <input type="text" name="nik" id="nik" required
                                value="<?php echo htmlspecialchars($employee['nik'] ?? ''); ?>"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="masukan NIK">
                        </div>
                        <div>
                            <label for="full_name" class="block text-sm font-semibold text-slate-700 mb-1">Nama Lengkap
                                <span class="text-red-500">*</span></label>
                            <input type="text" name="full_name" id="full_name" required
                                value="<?php echo htmlspecialchars($employee['full_name']); ?>"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="masukan nama lengkap">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Alamat Email
                                <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required
                                value="<?php echo htmlspecialchars($employee['email']); ?>"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="masukan alamat email">
                        </div>

                        <!-- Phone Number -->
                        <div>
                            <label for="phone_number" class="block text-sm font-semibold text-slate-700 mb-1">Nomor
                                Telepon <span class="text-red-500">*</span></label>
                            <input type="text" name="phone_number" id="phone_number" required
                                value="<?php echo htmlspecialchars($employee['phone_number']); ?>"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="08...">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">
                                <?php echo $is_edit ? "Kata Sandi Baru" : "Kata Sandi Default"; ?>
                                <span
                                    class="<?php echo $is_edit ? "text-slate-400 text-xs font-normal" : "text-red-500"; ?>">
                                    <?php echo $is_edit ? "(Kosongkan jika tidak ingin diubah)" : "*"; ?>
                                </span>
                            </label>
                            <input type="password" name="password" id="password" <?php echo $is_edit ? '' : 'required'; ?>
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="••••••••">
                        </div>

                        <!-- Address (Full Width) -->
                        <div class="md:col-span-2">
                            <label for="address" class="block text-sm font-semibold text-slate-700 mb-1">Alamat <span
                                    class="text-red-500">*</span></label>
                            <textarea name="address" id="address" rows="3" required
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="Alamat lengkap tempat tinggal..."><?php echo htmlspecialchars($employee['address']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employment Details Section -->
            <div class="p-8 space-y-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd"
                                d="M7.5 5.25a3 3 0 013-3h3a3 3 0 013 3v.25a3 3 0 013 3v1.5a3 3 0 01-3 3v.25h-9v-.25a3 3 0 01-3-3v-1.5a3 3 0 013-3V5.25zM3.75 21a.75.75 0 01.75-.75h15a.75.75 0 010 1.5H4.5a.75.75 0 01-.75-.75zm4.266-4.5H15.98a3 3 0 001.996.75 2.25 2.25 0 002.247-2.072l.027-.333a3.751 3.751 0 00-3.753-4.045H7.501A3.751 3.751 0 003.75 14.8l.026.333A2.25 2.25 0 006.023 17.25a3 3 0 001.993-.75z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Rincian Pekerjaan (Organisasi)</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Employee ID (Read only mock) -->
                    <?php if (!$is_edit): ?>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">ID Pegawai</label>
                            <div class="flex">
                                <span
                                    class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-200 bg-slate-50 text-slate-500 text-sm">EMP-</span>
                                <input type="text" readonly value="<?php echo date('Y') . rand(100, 999); ?>"
                                    class="rounded-r-lg bg-slate-50 border border-slate-200 text-slate-500 focus:ring-0 focus:border-slate-200 block w-full min-w-0 flex-1 text-sm px-3 py-2.5">
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1">Dibuat otomatis oleh sistem.</p>
                        </div>
                        <!-- Date of Joining Mock -->
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Tanggal Bergabung <span
                                    class="text-red-500">*</span></label>
                            <input type="date"
                                class="w-full px-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-slate-600">
                        </div>
                    <?php endif; ?>

                    <!-- Division (Custom Dropdown) -->
                    <div class="relative group" id="container-division_id">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Bidang <span
                                class="text-red-500">*</span></label>
                        <input type="hidden" name="division_id" id="input-division_id"
                            value="<?php echo $employee['division_id']; ?>">
                        <button type="button" onclick="toggleFormDropdown('division_id')"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                            <span id="text-division_id" class="block truncate">
                                <?php
                                $divName = "Pilih Bidang";
                                foreach ($divisions as $d) {
                                    if ($d['id'] == $employee['division_id']) {
                                        $divName = $d['name'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($divName);
                                ?>
                            </span>
                            <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="arrow-division_id"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="menu-division_id"
                            class="hidden absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                            <ul class="py-1">
                                <li onclick="selectFormOption('division_id', '', 'Pilih Bidang')"
                                    class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-500 hover:bg-slate-50">
                                    Pilih Bidang</li>
                                <?php foreach ($divisions as $div): ?>
                                    <li onclick="selectFormOption('division_id', '<?php echo $div['id']; ?>', '<?php echo htmlspecialchars($div['name'], ENT_QUOTES); ?>')"
                                        class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700">
                                        <?php echo htmlspecialchars($div['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Unit (Custom Dropdown) -->
                    <div class="relative group" id="container-unit_id">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Unit Organisasi</label>
                        <input type="hidden" name="unit_id" id="input-unit_id"
                            value="<?php echo $employee['unit_id']; ?>">
                        <button type="button" onclick="toggleFormDropdown('unit_id')"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                            <span id="text-unit_id" class="block truncate">
                                <?php
                                $unitName = "Langsung di bawah Bidang (Tanpa Unit)";
                                foreach ($units as $u) {
                                    if ($u['id'] == $employee['unit_id']) {
                                        $unitName = $u['name'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($unitName);
                                ?>
                            </span>
                            <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="arrow-unit_id"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="menu-unit_id"
                            class="hidden absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                            <ul class="py-1" id="list-unit_id">
                                <!-- Populated via JS -->
                                <li onclick="selectFormOption('unit_id', '', 'Langsung di bawah Bidang (Tanpa Unit)')"
                                    class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700">
                                    Langsung di bawah Bidang (Tanpa Unit)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Position (Custom Dropdown) -->
                <div class="relative group" id="container-position_id">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Jabatan<span
                            class="text-red-500">*</span></label>
                    <input type="hidden" name="position_id" id="input-position_id"
                        value="<?php echo $employee['position_id']; ?>">
                    <button type="button" onclick="toggleFormDropdown('position_id')"
                        class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                        <span id="text-position_id" class="block truncate">
                            <?php
                            $posName = "Pilih Jabatan";
                            foreach ($positions as $p) {
                                if ($p['id'] == $employee['position_id']) {
                                    $posName = $p['name'];
                                    break;
                                }
                            }
                            echo htmlspecialchars($posName);
                            ?>
                        </span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="arrow-position_id"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="menu-position_id"
                        class="hidden absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                        <ul class="py-1">
                            <li onclick="selectFormOption('position_id', '', 'Pilih Jabatan')"
                                class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-500 hover:bg-slate-50">
                                Pilih Jabatan</li>
                            <?php foreach ($positions as $pos): 
                                // Skip Administrator position
                                if ($pos['name'] === 'Administrator') continue;
                            ?>
                                <li onclick="selectFormOption('position_id', '<?php echo $pos['id']; ?>', '<?php echo htmlspecialchars($pos['name'], ENT_QUOTES); ?>')"
                                    class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700">
                                    <?php echo htmlspecialchars($pos['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Work Schedule (Custom Dropdown) -->
                <div class="relative group" id="container-schedule_id">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Jadwal Kerja</label>
                    <p class="text-xs text-slate-500 mb-2">Ganti jadwal default unit/bidang jika diperlukan.
                    </p>
                    <input type="hidden" name="schedule_id" id="input-schedule_id"
                        value="<?php echo $employee['schedule_id']; ?>">
                    <button type="button" onclick="toggleFormDropdown('schedule_id')"
                        class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                        <span id="text-schedule_id" class="block truncate">
                            <?php
                            $schedName = "Ikuti Aturan Default";
                            foreach ($schedules as $s) {
                                if ($s['id'] == $employee['schedule_id']) {
                                    $schedName = $s['name'];
                                    break;
                                }
                            }
                            echo htmlspecialchars($schedName);
                            ?>
                        </span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="arrow-schedule_id"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="menu-schedule_id"
                        class="hidden absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                        <ul class="py-1">
                            <li onclick="selectFormOption('schedule_id', '', 'Ikuti Aturan Default')"
                                class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700">
                                Ikuti Aturan Default</li>
                            <?php foreach ($schedules as $sched): ?>
                                <li onclick="selectFormOption('schedule_id', '<?php echo $sched['id']; ?>', '<?php echo htmlspecialchars($sched['name'], ENT_QUOTES); ?>')"
                                    class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700">
                                    <?php echo htmlspecialchars($sched['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
</div>

<!-- Action Buttons -->
<div class="flex justify-end gap-4 pt-2">
    <a href="<?php url('views/employees/index.php?' . $return_filters_qs); ?>"
        class="px-6 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-sm">
        Batal
    </a>
    <button type="submit"
        class="px-6 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-medium rounded-lg shadow-sm hover:shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">
        <?php echo $is_edit ? "Simpan Perubahan" : "Simpan Pegawai"; ?>
    </button>
</div>
</form>
</div>

<script>
    // --- Profile Photo Handling ---
    function previewImage(input) {
        const preview = document.getElementById('profile_preview');
        const placeholder = document.getElementById('photo_placeholder');
        const removeBtn = document.getElementById('remove_photo_btn');
        const removeInput = document.getElementById('remove_photo_input');

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
                removeBtn.classList.remove('hidden');
                removeInput.value = "0";
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeImage() {
        const input = document.getElementById('profile_photo_input');
        const preview = document.getElementById('profile_preview');
        const placeholder = document.getElementById('photo_placeholder');
        const removeBtn = document.getElementById('remove_photo_btn');
        const removeInput = document.getElementById('remove_photo_input');

        input.value = "";
        preview.src = "";
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
        removeBtn.classList.add('hidden');
        removeInput.value = "1";
    }

    const allUnits = <?php echo json_encode($units); ?>;
    const currentUnitId = "<?php echo $employee['unit_id']; ?>";
    const currentDivisionId = "<?php echo $employee['division_id']; ?>";

    // activeDropdownId is global from footer.php


    // --- Dropdown Interactions ---
    function toggleFormDropdown(id) {
        const menu = document.getElementById('menu-' + id);
        const arrow = document.getElementById('arrow-' + id);

        // Close others
        if (activeDropdownId && activeDropdownId !== id) {
            closeFormDropdown(activeDropdownId);
        }

        if (menu.classList.contains('hidden')) {
            // Open
            menu.classList.remove('hidden');
            requestAnimationFrame(() => {
                menu.classList.add('opacity-100', 'scale-100');
            });
            if (arrow) arrow.classList.add('rotate-180');
            activeDropdownId = id;
        } else {
            // Close
            closeFormDropdown(id);
        }
    }

    function closeFormDropdown(id) {
        const menu = document.getElementById('menu-' + id);
        const arrow = document.getElementById('arrow-' + id);
        if (menu) menu.classList.add('hidden');
        if (arrow) arrow.classList.remove('rotate-180');
        activeDropdownId = null;
    }

    function selectFormOption(id, value, label) {
        document.getElementById('input-' + id).value = value;
        document.getElementById('text-' + id).textContent = label;
        closeFormDropdown(id);

        if (id === 'division_id') {
            filterFormUnits(value);
        }
    }

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (activeDropdownId) {
            const container = document.getElementById('container-' + activeDropdownId);
            if (container && !container.contains(e.target)) {
                closeFormDropdown(activeDropdownId);
            }
        }
    });

    // --- Unit Filtering Logic ---
    function filterFormUnits(divisionId) {
        const unitList = document.getElementById('list-unit_id');
        const unitInput = document.getElementById('input-unit_id');
        const unitText = document.getElementById('text-unit_id');

        // Reset Unit
        unitInput.value = '';
        unitText.textContent = 'Langsung di bawah Bidang (Tanpa Unit)';
        unitList.innerHTML = '';

        // Default option
        const defaultLi = document.createElement('li');
        defaultLi.onclick = () => selectFormOption('unit_id', '', 'Langsung di bawah Bidang (Tanpa Unit)');
        defaultLi.className = "cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700";
        defaultLi.textContent = "Langsung di bawah Bidang (Tanpa Unit)";
        unitList.appendChild(defaultLi);

        if (divisionId) {
            const filteredUnits = allUnits.filter(unit => unit.division_id == divisionId);
            filteredUnits.forEach(unit => {
                const li = document.createElement('li');
                li.onclick = () => selectFormOption('unit_id', unit.id, unit.name);
                li.className = "cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700";
                li.textContent = unit.name;
                unitList.appendChild(li);
            });
        }
    }

    // Initialize units on load if division is set
    document.addEventListener('DOMContentLoaded', () => {
        const initialDivId = document.getElementById('input-division_id').value;
        if (initialDivId) {
            // We need to re-populate the list, but NOT reset the selected value if it matches.
            // Simplified: Run filter logic, then restore value if exists.

            const unitList = document.getElementById('list-unit_id');
            if (unitList) {
                unitList.innerHTML = ''; // Clear

                const defaultLi = document.createElement('li');
                defaultLi.onclick = () => selectFormOption('unit_id', '', 'Langsung di bawah Bidang (Tanpa Unit)');
                defaultLi.className = "cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700";
                defaultLi.textContent = "Langsung di bawah Bidang (Tanpa Unit)";
                unitList.appendChild(defaultLi);

                const filteredUnits = allUnits.filter(unit => unit.division_id == initialDivId);
                filteredUnits.forEach(unit => {
                    const li = document.createElement('li');
                    li.onclick = () => selectFormOption('unit_id', unit.id, unit.name);
                    li.className = "cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700";
                    li.textContent = unit.name;
                    unitList.appendChild(li);
                });
            }
        }
    });
</script>

<?php include '../layouts/footer.php'; ?>