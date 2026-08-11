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

<style>
    /* ===== Premium Dropdown Styles ===== */
    .premium-dropdown {
        position: relative;
    }

    .premium-dropdown-trigger {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: space-between;
        border-radius: 0.5rem;
        border: 1px solid #cbd5e1;
        background-color: #fff;
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
        color: #334155;
        font-weight: 500;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        cursor: pointer;
        transition: all 0.2s ease;
        outline: none;
        text-align: left;
    }

    .premium-dropdown-trigger:hover {
        border-color: #94a3b8;
        background-color: #f8fafc;
    }

    .premium-dropdown-trigger:focus,
    .premium-dropdown-trigger.active {
        border-color: #06b6d4;
        box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.15);
    }

    .premium-dropdown-trigger .dd-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        color: #94a3b8;
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        flex-shrink: 0;
        font-size: 0.75rem;
    }

    .premium-dropdown-trigger.active .dd-icon {
        transform: rotate(180deg);
        color: #06b6d4;
    }

    .premium-dropdown-trigger .dd-label {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .premium-dropdown-trigger .dd-label.placeholder {
        color: #94a3b8;
    }

    .premium-dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        z-index: 50;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        box-shadow: 0 10px 40px -5px rgba(0, 0, 0, 0.12), 0 4px 10px -5px rgba(0, 0, 0, 0.06);
        max-height: 260px;
        overflow-y: auto;
        overscroll-behavior: contain;

        /* Animation */
        opacity: 0;
        transform: translateY(-8px) scale(0.98);
        pointer-events: none;
        transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1),
                    transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .premium-dropdown-menu.open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
    }

    .premium-dropdown-menu .dd-search-wrap {
        position: sticky;
        top: 0;
        background: #fff;
        padding: 0.625rem;
        border-bottom: 1px solid #f1f5f9;
        z-index: 1;
    }

    .premium-dropdown-menu .dd-search-wrap input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        padding-left: 2.25rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        font-size: 0.8125rem;
        outline: none;
        transition: all 0.2s;
        color: #334155;
    }

    .premium-dropdown-menu .dd-search-wrap input:focus {
        border-color: #06b6d4;
        box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
    }

    .premium-dropdown-menu .dd-search-wrap .search-icon {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.75rem;
        pointer-events: none;
    }

    .premium-dropdown-menu .dd-option {
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
        display: flex;
        align-items: center;
        gap: 0.625rem;
        font-weight: 500;
    }

    .premium-dropdown-menu .dd-option:hover {
        background-color: #ecfeff;
        color: #0891b2;
        padding-left: 1.25rem;
    }

    .premium-dropdown-menu .dd-option.selected {
        background-color: #cffafe;
        color: #0891b2;
        font-weight: 700;
    }

    .premium-dropdown-menu .dd-option .check-icon {
        display: none;
        color: #06b6d4;
        font-size: 0.6875rem;
        width: 1rem;
        flex-shrink: 0;
    }

    .premium-dropdown-menu .dd-option.selected .check-icon {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .premium-dropdown-menu .dd-empty {
        padding: 1.5rem;
        text-align: center;
        color: #94a3b8;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Section icon container - ensures consistent sizing */
    .section-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.5rem;
        flex-shrink: 0;
        font-size: 1rem;
    }

    /* Form field icon alignment */
    .field-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }

    /* Scrollbar styling for dropdown menus */
    .premium-dropdown-menu::-webkit-scrollbar {
        width: 6px;
    }
    .premium-dropdown-menu::-webkit-scrollbar-track {
        background: transparent;
    }
    .premium-dropdown-menu::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    .premium-dropdown-menu::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<div class="w-full pb-10">
    <div class="mb-8">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
                <li class="inline-flex items-center">
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800">Beranda</a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fa-solid fa-chevron-right text-[10px] text-gray-400 mx-1"></i>
                        <a href="<?php url('views/employees/index.php'); ?>" class="hover:text-slate-800">Pegawai</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <i class="fa-solid fa-chevron-right text-[10px] text-gray-400 mx-1"></i>
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
            <i class="fa-solid fa-circle-info text-sm flex-shrink-0"></i>
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
            <div class="p-4 sm:p-8 border-b border-slate-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="section-icon bg-blue-50 text-blue-600">
                        <i class="fa-solid fa-circle-user"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Informasi Pribadi</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 sm:gap-8">
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
                                if (!empty($employee['profile_photo'])) {
                                    if (file_exists(BASE_PATH . '/uploads/profile_photos/' . $employee['profile_photo'])) {
                                        $photo_url = BASE_URL . '/uploads/profile_photos/' . $employee['profile_photo'];
                                    } elseif (file_exists(BASE_PATH . '/public/uploads/employees/' . $employee['profile_photo'])) {
                                        $photo_url = BASE_URL . '/public/uploads/employees/' . $employee['profile_photo'];
                                    }
                                }
                                ?>
                                <img id="profile_preview"
                                    class="w-full h-full rounded-full object-cover <?php echo $photo_url ? '' : 'hidden'; ?>"
                                    src="<?php echo $photo_url ?: ''; ?>" alt="">

                                <div id="photo_placeholder"
                                    class="<?php echo $photo_url ? 'hidden' : 'flex'; ?> flex-col items-center justify-center">
                                    <i class="fa-solid fa-id-badge text-2xl text-slate-400 group-hover:text-cyan-600"></i>
                                </div>

                                <!-- Overlay on hover -->
                                <div
                                    class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <i class="fa-solid fa-camera text-lg text-white"></i>
                                </div>
                            </div>

                            <!-- Remove button -->
                            <button type="button" id="remove_photo_btn" onclick="removeImage()"
                                class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md hover:bg-red-600 transition-all <?php echo $photo_url ? '' : 'hidden'; ?>">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>
                        <p
                            class="text-[10px] text-slate-400 mt-3 text-center w-full uppercase tracking-wider font-bold">
                            Ketuk untuk ubah foto</p>
                    </div>

                    <!-- Personal Fields -->
                    <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
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

                        <!-- Gender (Premium Dropdown) -->
                        <div class="premium-dropdown" data-dd-id="gender">
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Gender <span class="text-red-500">*</span></label>
                            <input type="hidden" name="gender" id="input-gender" value="<?php echo htmlspecialchars($employee['gender'] ?? ''); ?>" required>
                            <button type="button" class="premium-dropdown-trigger" onclick="PremiumDD.toggle('gender')">
                                <span class="dd-label <?php echo empty($employee['gender'] ?? '') ? 'placeholder' : ''; ?>">
                                    <?php
                                    $genderVal = $employee['gender'] ?? '';
                                    if ($genderVal === 'Male') echo 'Laki-laki';
                                    elseif ($genderVal === 'Female') echo 'Perempuan';
                                    else echo 'Pilih Gender';
                                    ?>
                                </span>
                                <span class="dd-icon"><i class="fa-solid fa-chevron-down"></i></span>
                            </button>
                            <div class="premium-dropdown-menu" id="dd-menu-gender">
                                <div class="dd-option <?php echo empty($genderVal) ? 'selected' : ''; ?>" data-value="" data-label="Pilih Gender" onclick="PremiumDD.select('gender', this)">
                                    <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                                    Pilih Gender
                                </div>
                                <div class="dd-option <?php echo $genderVal === 'Male' ? 'selected' : ''; ?>" data-value="Male" data-label="Laki-laki" onclick="PremiumDD.select('gender', this)">
                                    <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                                    Laki-laki
                                </div>
                                <div class="dd-option <?php echo $genderVal === 'Female' ? 'selected' : ''; ?>" data-value="Female" data-label="Perempuan" onclick="PremiumDD.select('gender', this)">
                                    <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                                    Perempuan
                                </div>
                            </div>
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
            <div class="p-4 sm:p-8 space-y-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="section-icon bg-cyan-50 text-cyan-600">
                        <i class="fa-solid fa-briefcase"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Rincian Pekerjaan (Organisasi)</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
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

                    <!-- Division (Premium Dropdown) -->
                    <div class="premium-dropdown" data-dd-id="division_id">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Bidang <span
                                class="text-red-500">*</span></label>
                        <input type="hidden" name="division_id" id="input-division_id"
                            value="<?php echo $employee['division_id']; ?>">
                        <button type="button" class="premium-dropdown-trigger" onclick="PremiumDD.toggle('division_id')">
                            <span class="dd-label <?php echo empty($employee['division_id']) ? 'placeholder' : ''; ?>">
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
                            <span class="dd-icon"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="premium-dropdown-menu" id="dd-menu-division_id">
                            <div class="dd-option <?php echo empty($employee['division_id']) ? 'selected' : ''; ?>" data-value="" data-label="Pilih Bidang" onclick="PremiumDD.select('division_id', this)">
                                <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                                Pilih Bidang
                            </div>
                            <?php foreach ($divisions as $div): ?>
                                <div class="dd-option <?php echo ($div['id'] == $employee['division_id']) ? 'selected' : ''; ?>" data-value="<?php echo $div['id']; ?>" data-label="<?php echo htmlspecialchars($div['name'], ENT_QUOTES); ?>" onclick="PremiumDD.select('division_id', this)">
                                    <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                                    <?php echo htmlspecialchars($div['name']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Unit (Premium Dropdown - Dynamic) -->
                    <div class="premium-dropdown" data-dd-id="unit_id">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Unit Organisasi</label>
                        <input type="hidden" name="unit_id" id="input-unit_id"
                            value="<?php echo $employee['unit_id']; ?>">
                        <button type="button" class="premium-dropdown-trigger" onclick="PremiumDD.toggle('unit_id')">
                            <span class="dd-label <?php echo empty($employee['unit_id']) ? 'placeholder' : ''; ?>" id="dd-label-unit_id">
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
                            <span class="dd-icon"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="premium-dropdown-menu" id="dd-menu-unit_id">
                            <!-- Populated via JS -->
                        </div>
                    </div>
                </div>

                <!-- Position (Premium Dropdown with Search) -->
                <div class="premium-dropdown" data-dd-id="position_id">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Jabatan<span
                            class="text-red-500">*</span></label>
                    <input type="hidden" name="position_id" id="input-position_id"
                        value="<?php echo $employee['position_id']; ?>">
                    <button type="button" class="premium-dropdown-trigger" onclick="PremiumDD.toggle('position_id')">
                        <span class="dd-label <?php echo empty($employee['position_id']) ? 'placeholder' : ''; ?>">
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
                        <span class="dd-icon"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="premium-dropdown-menu" id="dd-menu-position_id">
                        <div class="dd-search-wrap">
                            <i class="fa-solid fa-magnifying-glass search-icon"></i>
                            <input type="text" placeholder="Cari jabatan..." oninput="PremiumDD.filter('position_id', this.value)" onclick="event.stopPropagation()">
                        </div>
                        <div id="dd-options-position_id">
                            <div class="dd-option <?php echo empty($employee['position_id']) ? 'selected' : ''; ?>" data-value="" data-label="Pilih Jabatan" onclick="PremiumDD.select('position_id', this)">
                                <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                                Pilih Jabatan
                            </div>
                            <?php foreach ($positions as $pos):
                                // Skip Administrator position
                                if ($pos['name'] === 'Administrator') continue;
                            ?>
                                <div class="dd-option <?php echo ($pos['id'] == $employee['position_id']) ? 'selected' : ''; ?>" data-value="<?php echo $pos['id']; ?>" data-label="<?php echo htmlspecialchars($pos['name'], ENT_QUOTES); ?>" onclick="PremiumDD.select('position_id', this)">
                                    <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                                    <?php echo htmlspecialchars($pos['name']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Work Schedule (Premium Dropdown) -->
                <div class="premium-dropdown" data-dd-id="schedule_id">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Jadwal Kerja</label>
                    <p class="text-xs text-slate-500 mb-2">Ganti jadwal default unit/bidang jika diperlukan.</p>
                    <input type="hidden" name="schedule_id" id="input-schedule_id"
                        value="<?php echo $employee['schedule_id']; ?>">
                    <button type="button" class="premium-dropdown-trigger" onclick="PremiumDD.toggle('schedule_id')">
                        <span class="dd-label <?php echo empty($employee['schedule_id']) ? 'placeholder' : ''; ?>">
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
                        <span class="dd-icon"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="premium-dropdown-menu" id="dd-menu-schedule_id">
                        <div class="dd-search-wrap">
                            <i class="fa-solid fa-magnifying-glass search-icon"></i>
                            <input type="text" placeholder="Cari jadwal kerja..." oninput="PremiumDD.filter('schedule_id', this.value)" onclick="event.stopPropagation()">
                        </div>
                        <div id="dd-options-schedule_id">
                            <div class="dd-option <?php echo empty($employee['schedule_id']) ? 'selected' : ''; ?>" data-value="" data-label="Ikuti Aturan Default" onclick="PremiumDD.select('schedule_id', this)">
                                <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                                Ikuti Aturan Default
                            </div>
                            <?php foreach ($schedules as $sched): ?>
                                <div class="dd-option <?php echo ($sched['id'] == $employee['schedule_id']) ? 'selected' : ''; ?>" data-value="<?php echo $sched['id']; ?>" data-label="<?php echo htmlspecialchars($sched['name'], ENT_QUOTES); ?>" onclick="PremiumDD.select('schedule_id', this)">
                                    <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                                    <?php echo htmlspecialchars($sched['name']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
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

    // --- Data from PHP ---
    const allUnits = <?php echo json_encode($units); ?>;
    const currentUnitId = "<?php echo $employee['unit_id']; ?>";
    const currentDivisionId = "<?php echo $employee['division_id']; ?>";

    // ===== Premium Dropdown Engine =====
    const PremiumDD = {
        _activeId: null,

        toggle(id) {
            const menu = document.getElementById('dd-menu-' + id);
            if (!menu) return;

            // If already open, close it
            if (this._activeId === id && menu.classList.contains('open')) {
                this.close(id);
                return;
            }

            // Close any currently open dropdown first
            if (this._activeId && this._activeId !== id) {
                this.close(this._activeId);
            }

            // Open this dropdown
            const trigger = menu.previousElementSibling;
            if (trigger) trigger.classList.add('active');
            menu.classList.add('open');
            this._activeId = id;

            // Focus search input if present
            const searchInput = menu.querySelector('.dd-search-wrap input');
            if (searchInput) {
                searchInput.value = '';
                setTimeout(() => searchInput.focus(), 80);
                // Reset filter
                this.filter(id, '');
            }
        },

        close(id) {
            const menu = document.getElementById('dd-menu-' + id);
            if (!menu) return;

            const trigger = menu.previousElementSibling;
            if (trigger) trigger.classList.remove('active');
            menu.classList.remove('open');

            if (this._activeId === id) this._activeId = null;
        },

        select(id, optionEl) {
            const value = optionEl.dataset.value;
            const label = optionEl.dataset.label;

            // Update hidden input
            const input = document.getElementById('input-' + id);
            if (input) input.value = value;

            // Update trigger label
            const trigger = document.querySelector(`[data-dd-id="${id}"] .premium-dropdown-trigger`);
            if (trigger) {
                const labelEl = trigger.querySelector('.dd-label');
                if (labelEl) {
                    labelEl.textContent = label;
                    if (value === '') {
                        labelEl.classList.add('placeholder');
                    } else {
                        labelEl.classList.remove('placeholder');
                    }
                }
            }

            // Update selected state
            const menu = document.getElementById('dd-menu-' + id);
            if (menu) {
                menu.querySelectorAll('.dd-option').forEach(opt => opt.classList.remove('selected'));
                optionEl.classList.add('selected');
            }

            this.close(id);

            // Trigger special handlers
            if (id === 'division_id') {
                filterFormUnits(value);
            }
        },

        filter(id, query) {
            const container = document.getElementById('dd-options-' + id);
            if (!container) return;

            const options = container.querySelectorAll('.dd-option');
            let visibleCount = 0;
            const q = query.toLowerCase().trim();

            options.forEach(opt => {
                const label = (opt.dataset.label || opt.textContent).toLowerCase();
                if (!q || label.includes(q)) {
                    opt.style.display = '';
                    visibleCount++;
                } else {
                    opt.style.display = 'none';
                }
            });

            // Show/hide empty state
            let emptyEl = container.querySelector('.dd-empty');
            if (visibleCount === 0) {
                if (!emptyEl) {
                    emptyEl = document.createElement('div');
                    emptyEl.className = 'dd-empty';
                    emptyEl.textContent = 'Tidak ditemukan';
                    container.appendChild(emptyEl);
                }
                emptyEl.style.display = '';
            } else if (emptyEl) {
                emptyEl.style.display = 'none';
            }
        }
    };

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (PremiumDD._activeId) {
            const container = e.target.closest('.premium-dropdown');
            if (!container || container.dataset.ddId !== PremiumDD._activeId) {
                PremiumDD.close(PremiumDD._activeId);
            }
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && PremiumDD._activeId) {
            PremiumDD.close(PremiumDD._activeId);
        }
    });

    // --- Unit Filtering Logic (builds options dynamically) ---
    function filterFormUnits(divisionId) {
        const unitInput = document.getElementById('input-unit_id');
        const unitLabel = document.getElementById('dd-label-unit_id');
        const unitMenu = document.getElementById('dd-menu-unit_id');

        if (!unitMenu) return;

        // Reset
        unitInput.value = '';
        if (unitLabel) {
            unitLabel.textContent = 'Langsung di bawah Bidang (Tanpa Unit)';
            unitLabel.classList.add('placeholder');
        }

        // Build options
        let html = '';
        html += `<div class="dd-option selected" data-value="" data-label="Langsung di bawah Bidang (Tanpa Unit)" onclick="PremiumDD.select('unit_id', this)">
                    <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                    Langsung di bawah Bidang (Tanpa Unit)
                 </div>`;

        if (divisionId) {
            const filtered = allUnits.filter(u => u.division_id == divisionId);
            filtered.forEach(unit => {
                html += `<div class="dd-option" data-value="${unit.id}" data-label="${unit.name}" onclick="PremiumDD.select('unit_id', this)">
                            <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                            ${unit.name}
                         </div>`;
            });
        }

        unitMenu.innerHTML = html;
    }

    // Initialize units on load if division is set
    document.addEventListener('DOMContentLoaded', () => {
        const initialDivId = document.getElementById('input-division_id').value;
        if (initialDivId) {
            const unitMenu = document.getElementById('dd-menu-unit_id');
            if (unitMenu) {
                let html = '';
                html += `<div class="dd-option ${!currentUnitId ? 'selected' : ''}" data-value="" data-label="Langsung di bawah Bidang (Tanpa Unit)" onclick="PremiumDD.select('unit_id', this)">
                            <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                            Langsung di bawah Bidang (Tanpa Unit)
                         </div>`;

                const filtered = allUnits.filter(u => u.division_id == initialDivId);
                filtered.forEach(unit => {
                    const isSelected = unit.id == currentUnitId;
                    html += `<div class="dd-option ${isSelected ? 'selected' : ''}" data-value="${unit.id}" data-label="${unit.name}" onclick="PremiumDD.select('unit_id', this)">
                                <span class="check-icon"><i class="fa-solid fa-check"></i></span>
                                ${unit.name}
                             </div>`;
                });

                unitMenu.innerHTML = html;
            }
        }
    });
</script>

<?php include '../layouts/footer.php'; ?>