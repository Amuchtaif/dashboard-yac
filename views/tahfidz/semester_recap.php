<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

$page_title = "Rekap Semester Tahfidz";

$db = new Database();
$conn = $db->getConnection();

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// --- Fetch Academic Years for Filter ---
$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY name DESC, semester DESC")->fetchAll(PDO::FETCH_ASSOC);
$active_ay = null;
foreach ($academic_years as $ay) {
    if ($ay['is_active']) {
        $active_ay = $ay;
        break;
    }
}

// Get unique academic year names
$academic_year_names = [];
foreach ($academic_years as $ay) {
    if (!in_array($ay['name'], $academic_year_names)) {
        $academic_year_names[] = $ay['name'];
    }
}

// --- Filter Parameters ---
$selected_ay_name = isset($_GET['ay_name']) ? $_GET['ay_name'] : ($active_ay ? $active_ay['name'] : (!empty($academic_year_names) ? $academic_year_names[0] : ''));
$group_id = isset($_GET['group_id']) ? $_GET['group_id'] : '';

// Retrieve start/end dates and IDs for Semester 1 (Ganjil) and Semester 2 (Genap)
$sem1_start = null;
$sem1_end = null;
$sem1_id = null;
$sem2_start = null;
$sem2_end = null;
$sem2_id = null;

foreach ($academic_years as $ay) {
    if ($ay['name'] === $selected_ay_name) {
        if ($ay['semester'] === 'Ganjil') {
            $sem1_start = $ay['start_date'];
            $sem1_end = $ay['end_date'];
            $sem1_id = $ay['id'];
        } elseif ($ay['semester'] === 'Genap') {
            $sem2_start = $ay['start_date'];
            $sem2_end = $ay['end_date'];
            $sem2_id = $ay['id'];
        }
    }
}

// --- Fetch Halaqah Groups for Filter ---
$where_groups = "1=1";
$params_groups = [];
if (!$is_admin) {
    $where_groups = "hg.teacher_id = :uid";
    $params_groups[':uid'] = $_SESSION['user_id'];
}

$groups_query = "
    SELECT hg.*, e.full_name as teacher_name
    FROM halaqah_groups hg
    JOIN employees e ON hg.teacher_id = e.id
    WHERE $where_groups
    ORDER BY CAST(SUBSTRING_INDEX(hg.group_name, ' ', -1) AS UNSIGNED) ASC, hg.group_name ASC
";
$groups_stmt = $conn->prepare($groups_query);
$groups_stmt->execute($params_groups);
$groups = $groups_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no group_id is selected but groups exist, pick the first one
if (!$group_id && count($groups) > 0) {
    $group_id = $groups[0]['id'];
}

// --- Fetch Students and Memorization Recap ---
$students_recap = [];
if ($group_id) {
    // 1. Get students in this halaqah group (with tingkat for unit resolution from selected academic year)
    $selected_ay_id = $sem1_id ?: $sem2_id;
    if ($selected_ay_id) {
        $students_query = "
            SELECT s.id, s.nama_siswa, s.nomor_induk, COALESCE(gl.name, s.kelas) as kelas, s.tingkat
            FROM students s
            JOIN halaqah_members hm ON s.id = hm.student_id
            LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :ay_id AND sch.status = 'ACTIVE'
            LEFT JOIN grade_levels gl ON sch.class_id = gl.id
            WHERE hm.group_id = :group_id
            ORDER BY s.nama_siswa ASC
        ";
        $students_stmt = $conn->prepare($students_query);
        $students_stmt->execute([':group_id' => $group_id, ':ay_id' => $selected_ay_id]);
    } else {
        $students_query = "
            SELECT s.id, s.nama_siswa, s.nomor_induk, s.kelas, s.tingkat
            FROM students s
            JOIN halaqah_members hm ON s.id = hm.student_id
            WHERE hm.group_id = :group_id
            ORDER BY s.nama_siswa ASC
        ";
        $students_stmt = $conn->prepare($students_query);
        $students_stmt->execute([':group_id' => $group_id]);
    }
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as $student) {
        $sid = $student['id'];
        
        // --- Resolve Student Class Number (7-12) ---
        $kelas_num = null;
        if (!empty($student['kelas']) && preg_match('/^(\d+)/', $student['kelas'], $matches)) {
            $kelas_num = (int)$matches[1];
        }

        // --- Resolve Student Education Unit ---
        $tingkat = strtoupper(trim($student['tingkat'] ?? ''));
        $unit_id = null;
        if (strpos($tingkat, 'MTS') !== false) {
            $unit_id = 5;
        } elseif (strpos($tingkat, 'MA') !== false) {
            $unit_id = 6;
        }
        
        // Fallback for Unit ID based on Class Number if tingkat is empty
        if (!$unit_id && $kelas_num) {
            if ($kelas_num >= 7 && $kelas_num <= 9) {
                $unit_id = 5;
            } elseif ($kelas_num >= 10 && $kelas_num <= 12) {
                $unit_id = 6;
            }
        }

        // --- Resolve Student Program (Boarding or Fullday for MTs only) ---
        $program_id = null;
        if ($unit_id === 5) {
            $is_boarding_stmt = $conn->prepare("SELECT COUNT(*) FROM boarding_room_members WHERE student_id = :sid");
            $is_boarding_stmt->execute([':sid' => $sid]);
            $is_boarding = ((int)$is_boarding_stmt->fetchColumn() > 0);
            $program_id = $is_boarding ? 'Boarding' : 'Fullday';
        }

        // --- Resolve Targets ---
        $target_sem1 = null;
        if ($sem1_id && $unit_id && $kelas_num) {
            if ($unit_id === 5) {
                $t_stmt = $conn->prepare("SELECT target_juz FROM target_hafalan WHERE tahun_ajaran_id = :ta_id AND unit_id = :u_id AND program_id = :p_id AND kelas_id = :k_id AND status_aktif = 'Aktif' LIMIT 1");
                $t_stmt->execute([':ta_id' => $sem1_id, ':u_id' => $unit_id, ':p_id' => $program_id, ':k_id' => $kelas_num]);
            } else {
                $t_stmt = $conn->prepare("SELECT target_juz FROM target_hafalan WHERE tahun_ajaran_id = :ta_id AND unit_id = :u_id AND program_id IS NULL AND kelas_id = :k_id AND status_aktif = 'Aktif' LIMIT 1");
                $t_stmt->execute([':ta_id' => $sem1_id, ':u_id' => $unit_id, ':k_id' => $kelas_num]);
            }
            $res = $t_stmt->fetchColumn();
            if ($res !== false) {
                $target_sem1 = (float)$res;
            }
        }

        $target_sem2 = null;
        if ($sem2_id && $unit_id && $kelas_num) {
            if ($unit_id === 5) {
                $t_stmt = $conn->prepare("SELECT target_juz FROM target_hafalan WHERE tahun_ajaran_id = :ta_id AND unit_id = :u_id AND program_id = :p_id AND kelas_id = :k_id AND status_aktif = 'Aktif' LIMIT 1");
                $t_stmt->execute([':ta_id' => $sem2_id, ':u_id' => $unit_id, ':p_id' => $program_id, ':k_id' => $kelas_num]);
            } else {
                $t_stmt = $conn->prepare("SELECT target_juz FROM target_hafalan WHERE tahun_ajaran_id = :ta_id AND unit_id = :u_id AND program_id IS NULL AND kelas_id = :k_id AND status_aktif = 'Aktif' LIMIT 1");
                $t_stmt->execute([':ta_id' => $sem2_id, ':u_id' => $unit_id, ':k_id' => $kelas_num]);
            }
            $res = $t_stmt->fetchColumn();
            if ($res !== false) {
                $target_sem2 = (float)$res;
            }
        }

        // Overall Target is based on the active semester if it is in the selected year, else the latest semester
        $target_overall = null;
        if ($active_ay && $active_ay['name'] === $selected_ay_name) {
            if ($active_ay['semester'] === 'Ganjil') {
                $target_overall = $target_sem1;
            } else {
                $target_overall = $target_sem2;
            }
        } else {
            $target_overall = ($target_sem2 !== null) ? $target_sem2 : $target_sem1;
        }
        
        // Count total setoran lines in Semester 1 (Ganjil)
        $sem1_count = 0;
        if ($sem1_start && $sem1_end) {
            $s1_stmt = $conn->prepare("
                SELECT SUM(total_baris) 
                FROM memorization_entries 
                WHERE student_id = :sid AND date BETWEEN :start AND :end
            ");
            $s1_stmt->execute([':sid' => $sid, ':start' => $sem1_start, ':end' => $sem1_end]);
            $sem1_count = (int)$s1_stmt->fetchColumn();
        }

        // Count total setoran lines in Semester 2 (Genap)
        $sem2_count = 0;
        if ($sem2_start && $sem2_end) {
            $s2_stmt = $conn->prepare("
                SELECT SUM(total_baris) 
                FROM memorization_entries 
                WHERE student_id = :sid AND date BETWEEN :start AND :end
            ");
            $s2_stmt->execute([':sid' => $sid, ':start' => $sem2_start, ':end' => $sem2_end]);
            $sem2_count = (int)$s2_stmt->fetchColumn();
        }

        // Setup displays for Semester 1
        $sem1_target_display = ($target_sem1 !== null) ? str_replace('.', ',', (string)round($target_sem1, 1)) : "-";
        if ($sem1_count == 0) {
            $sem1_display = "Tahsin";
            $sem1_pages = "0";
            $sem1_keterangan = ($target_sem1 !== null && $target_sem1 > 0) ? "BELUM" : ($target_sem1 === null ? "-" : "TERCAPAI");
        } else {
            $sem1_display = $sem1_count;
            $sem1_pages = str_replace('.', ',', (string)round($sem1_count / 15, 1));
            $sem1_juz = $sem1_count / 300; // 300 lines = 20 pages * 15 lines = 1 Juz
            $sem1_keterangan = ($target_sem1 !== null && $sem1_juz >= $target_sem1) ? "TERCAPAI" : "BELUM";
        }

        // Setup displays for Semester 2
        $sem2_target_display = ($target_sem2 !== null) ? str_replace('.', ',', (string)round($target_sem2, 1)) : "-";
        if ($sem2_count == 0) {
            $sem2_display = "Tahsin";
            $sem2_pages = "0";
            $sem2_keterangan = ($target_sem2 !== null && $target_sem2 > 0) ? "BELUM" : ($target_sem2 === null ? "-" : "TERCAPAI");
        } else {
            $sem2_display = $sem2_count;
            $sem2_pages = str_replace('.', ',', (string)round($sem2_count / 15, 1));
            $sem2_juz = $sem2_count / 300; // 300 lines = 20 pages * 15 lines = 1 Juz
            $sem2_keterangan = ($target_sem2 !== null && $sem2_juz >= $target_sem2) ? "TERCAPAI" : "BELUM";
        }

        // Overall (Total Juz is total baris / 300)
        $total_juz = ($sem1_count + $sem2_count) / 300;
        
        // overall Keterangan logic: if total_juz meets overall target, or if either semester was met
        $overall_keterangan = "-";
        if ($target_overall !== null) {
            $overall_keterangan = ($total_juz >= $target_overall || $sem1_keterangan === 'TERCAPAI' || $sem2_keterangan === 'TERCAPAI') ? "TERCAPAI" : "BELUM";
        }

        // Progress percentage & Sisa Target calculations
        $progress_pct = 0;
        if ($target_overall !== null && $target_overall > 0) {
            $progress_pct = ($total_juz / $target_overall) * 100;
        }
        $sisa_target = ($target_overall !== null) ? max(0, $target_overall - $total_juz) : 0;

        $students_recap[] = [
            'id' => $student['id'],
            'name' => $student['nama_siswa'],
            'class' => $student['kelas'] ?? '-',
            'sem1_display' => $sem1_display,
            'sem1_pages' => $sem1_pages,
            'sem1_target' => $sem1_target_display,
            'sem1_keterangan' => $sem1_keterangan,
            'sem2_display' => $sem2_display,
            'sem2_pages' => $sem2_pages,
            'sem2_target' => $sem2_target_display,
            'sem2_keterangan' => $sem2_keterangan,
            'total_juz' => str_replace('.', ',', (string)round($total_juz, 1)),
            'overall_target' => $target_overall !== null ? str_replace('.', ',', (string)round($target_overall, 1)) : "-",
            'overall_keterangan' => $overall_keterangan,
            
            // Raw dynamic target fields for visual cards and modals
            'target_sem1_raw' => $target_sem1,
            'target_sem2_raw' => $target_sem2,
            'target_overall_raw' => $target_overall,
            'total_juz_raw' => round($total_juz, 2),
            'sisa_target_raw' => round($sisa_target, 2),
            'progress_pct' => round($progress_pct, 1),
            'program_id' => $program_id
        ];
    }
}

// Get group info for display
$selected_group = null;
foreach ($groups as $g) {
    if ($g['id'] == $group_id) {
        $selected_group = $g;
        break;
    }
}

include '../layouts/header.php';
?>

<!-- Custom Styles for Table and Formatting -->
<style>
    /* Table Container Styling */
    .recap-table-container {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.02), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .recap-table-container:hover {
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.04), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
    }

    /* Table Reset & Layout */
    .recap-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        font-family: 'Outfit', sans-serif;
    }
    
    /* Table Headers */
    .recap-table th {
        font-family: 'Outfit', sans-serif;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 18px 12px;
        vertical-align: middle;
        transition: background-color 0.2s ease;
    }

    /* Table Cells */
    .recap-table td {
        padding: 16px 14px;
        border-bottom: 1px solid #f1f5f9;
        border-right: 1px solid #f1f5f9;
        vertical-align: middle;
        font-size: 0.875rem;
        color: #475569;
        transition: all 0.2s ease;
    }
    .recap-table td:last-child {
        border-right: none;
    }
    .recap-table tbody tr:last-child td {
        border-bottom: none;
    }
    .recap-table tbody tr {
        transition: background-color 0.15s ease;
    }
    .recap-table tbody tr:hover td {
        background-color: #f8fafc;
    }

    /* Status Pill Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.35rem 0.85rem;
        font-size: 0.65rem;
        font-weight: 800;
        border-radius: 9999px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.02);
        transition: all 0.2s ease;
    }
    .status-tercapai {
        background-color: #ecfdf5 !important;
        color: #059669 !important;
        border: 1px solid #a7f3d0 !important;
    }
    .status-tercapai:hover {
        background-color: #d1fae5 !important;
    }
    .status-belum {
        background-color: #fef2f2 !important;
        color: #dc2626 !important;
        border: 1px solid #fca5a5 !important;
    }
    .status-belum:hover {
        background-color: #fee2e2 !important;
    }
    .status-tahsin {
        background-color: #fffbeb !important;
        color: #d97706 !important;
        border: 1px solid #fde68a !important;
    }
    .status-tahsin:hover {
        background-color: #fef3c7 !important;
    }

    /* Scrollbar Styling */
    .custom-scrollbar::-webkit-scrollbar {
        height: 8px;
        width: 8px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
        border: 2px solid #f1f5f9;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<div class="pb-10">
    <!-- Header Section -->
    <div class="sm:flex sm:items-center justify-between mb-8 no-print">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight flex items-center gap-3">
                <span>Rekap Semester Tahfidz</span>
            </h1>
            <p class="mt-2 text-sm text-slate-500 font-medium">Rekapitulasi pencapaian hafalan santri per halaqah dalam satu tahun ajaran.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:flex-none">
            <button onclick="window.print()"
                class="inline-flex items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 px-6 py-3 text-sm font-bold text-white hover:from-cyan-600 hover:to-blue-700 transition-all active:scale-[0.98] group">
                <svg class="-ml-1 mr-2.5 h-4 w-4 text-blue-200 group-hover:text-white transition-colors" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Rekap
            </button>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm mb-8 no-print">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-6" id="filter-recap-form">
            <!-- Kelompok Halaqah Custom Dropdown -->
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Kelompok Halaqah</label>
                <input type="hidden" name="group_id" id="filter-group-id" value="<?php echo htmlspecialchars($group_id); ?>">
                <div class="relative" id="group-dropdown-container">
                    <!-- Trigger Button -->
                    <button type="button" id="group-dropdown-btn" onclick="toggleGroupDropdown()"
                        class="flex items-center justify-between w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all cursor-pointer shadow-sm text-left">
                        <span id="group-dropdown-text" class="truncate">
                            <?php 
                            if ($selected_group) {
                                echo htmlspecialchars($selected_group['group_name']) . ' (' . htmlspecialchars($selected_group['teacher_name']) . ')';
                            } else {
                                echo 'Pilih Halaqah...';
                            }
                            ?>
                        </span>
                        <svg class="h-4 w-4 text-slate-400 flex-shrink-0 ml-2 transition-transform duration-200" id="group-dropdown-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Panel -->
                    <div id="group-dropdown-menu"
                        class="hidden absolute left-0 right-0 mt-2 bg-white rounded-xl border border-slate-200 shadow-xl z-[60] overflow-hidden transition-all duration-200 origin-top scale-y-0 opacity-0"
                        style="max-height: 280px;">
                        <!-- Options List -->
                        <ul id="group-options-list" class="overflow-y-auto custom-scrollbar" style="max-height: 280px;">
                            <li class="cursor-pointer px-4 py-3 text-sm text-slate-500 hover:bg-slate-50 transition-colors border-b border-slate-100/60"
                                onclick="selectGroup('', 'Pilih Halaqah...')">
                                Pilih Halaqah...
                            </li>
                            <?php foreach ($groups as $g): ?>
                                <li class="group-option cursor-pointer flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors border-b border-slate-50 <?php echo $group_id == $g['id'] ? 'bg-cyan-50/50 text-cyan-700 font-semibold' : ''; ?>"
                                    data-value="<?php echo $g['id']; ?>"
                                    onclick="selectGroup('<?php echo $g['id']; ?>', '<?php echo htmlspecialchars($g['group_name']); ?> (<?php echo htmlspecialchars($g['teacher_name']); ?>)')">
                                    <span class="flex-shrink-0 h-6 w-6 rounded-full bg-cyan-100 text-cyan-600 flex items-center justify-center text-[10px] font-bold">
                                        <?php 
                                        $num = preg_replace('/[^0-9]/', '', $g['group_name']);
                                        echo $num ? $num : strtoupper(substr($g['group_name'], 0, 1)); 
                                        ?>
                                    </span>
                                    <span class="truncate font-medium">
                                        <?php echo htmlspecialchars($g['group_name']); ?>
                                        <span class="text-xs text-slate-400 font-normal ml-1">(<?php echo htmlspecialchars($g['teacher_name']); ?>)</span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Tahun Ajaran Custom Dropdown -->
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Tahun Ajaran</label>
                <input type="hidden" name="ay_name" id="filter-ay-name" value="<?php echo htmlspecialchars($selected_ay_name); ?>">
                <div class="relative" id="ay-dropdown-container">
                    <!-- Trigger Button -->
                    <button type="button" id="ay-dropdown-btn" onclick="toggleAyDropdown()"
                        class="flex items-center justify-between w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all cursor-pointer shadow-sm text-left">
                        <span id="ay-dropdown-text" class="truncate">
                            <?php echo $selected_ay_name ? htmlspecialchars($selected_ay_name) : 'Pilih Tahun Ajaran...'; ?>
                        </span>
                        <svg class="h-4 w-4 text-slate-400 flex-shrink-0 ml-2 transition-transform duration-200" id="ay-dropdown-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown Panel -->
                    <div id="ay-dropdown-menu"
                        class="hidden absolute left-0 right-0 mt-2 bg-white rounded-xl border border-slate-200 shadow-xl z-[60] overflow-hidden transition-all duration-200 origin-top scale-y-0 opacity-0"
                        style="max-height: 280px;">
                        <!-- Options List -->
                        <ul id="ay-options-list" class="overflow-y-auto custom-scrollbar" style="max-height: 280px;">
                            <?php foreach ($academic_year_names as $ay_name): ?>
                                <li class="ay-option cursor-pointer flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors border-b border-slate-50 <?php echo $selected_ay_name == $ay_name ? 'bg-cyan-50/50 text-cyan-700 font-semibold' : ''; ?>"
                                    data-value="<?php echo htmlspecialchars($ay_name); ?>"
                                    onclick="selectAy('<?php echo htmlspecialchars($ay_name); ?>')">
                                    <span class="flex-shrink-0 h-6 w-6 rounded-full bg-slate-150 text-slate-500 flex items-center justify-center text-[10px] font-bold">
                                        📅
                                    </span>
                                    <span class="truncate font-medium"><?php echo htmlspecialchars($ay_name); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex items-end gap-3">
                <button type="submit" class="flex-1 bg-gradient-to-br from-cyan-500 to-blue-600 text-white px-6 py-3 rounded-xl text-sm font-bold hover:from-cyan-600 hover:to-blue-700 transition-all active:scale-[0.98]">
                    Filter
                </button>
                <a href="semester_recap.php" class="bg-white border border-slate-200 text-slate-500 p-3 rounded-xl hover:bg-slate-50 transition-all flex items-center justify-center shadow-sm" title="Reset Filter">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>
        </form>
    </div>

    <script>
        let groupDropdownOpen = false;
        let ayDropdownOpen = false;

        function toggleGroupDropdown() {
            if (groupDropdownOpen) {
                closeGroupDropdown();
            } else {
                closeAyDropdown();
                openGroupDropdown();
            }
        }

        function openGroupDropdown() {
            const menu = document.getElementById('group-dropdown-menu');
            const arrow = document.getElementById('group-dropdown-arrow');
            const btn = document.getElementById('group-dropdown-btn');

            menu.classList.remove('hidden');
            void menu.offsetWidth;
            menu.classList.remove('scale-y-0', 'opacity-0');
            menu.classList.add('scale-y-100', 'opacity-100');
            arrow.classList.add('rotate-180');
            btn.classList.add('border-cyan-500', 'ring-4', 'ring-cyan-500/5', 'bg-white');
            groupDropdownOpen = true;
        }

        function closeGroupDropdown() {
            const menu = document.getElementById('group-dropdown-menu');
            const arrow = document.getElementById('group-dropdown-arrow');
            const btn = document.getElementById('group-dropdown-btn');

            if (!menu) return;

            menu.classList.remove('scale-y-100', 'opacity-100');
            menu.classList.add('scale-y-0', 'opacity-0');
            arrow.classList.remove('rotate-180');
            btn.classList.remove('border-cyan-500', 'ring-4', 'ring-cyan-500/5', 'bg-white');
            groupDropdownOpen = false;

            setTimeout(() => {
                if (!groupDropdownOpen) menu.classList.add('hidden');
            }, 200);
        }

        function selectGroup(value, name) {
            document.getElementById('filter-group-id').value = value;
            document.getElementById('group-dropdown-text').textContent = name;
            closeGroupDropdown();
            document.getElementById('filter-group-id').form.submit();
        }

        function toggleAyDropdown() {
            if (ayDropdownOpen) {
                closeAyDropdown();
            } else {
                closeGroupDropdown();
                openAyDropdown();
            }
        }

        function openAyDropdown() {
            const menu = document.getElementById('ay-dropdown-menu');
            const arrow = document.getElementById('ay-dropdown-arrow');
            const btn = document.getElementById('ay-dropdown-btn');

            menu.classList.remove('hidden');
            void menu.offsetWidth;
            menu.classList.remove('scale-y-0', 'opacity-0');
            menu.classList.add('scale-y-100', 'opacity-100');
            arrow.classList.add('rotate-180');
            btn.classList.add('border-cyan-500', 'ring-4', 'ring-cyan-500/5', 'bg-white');
            ayDropdownOpen = true;
        }

        function closeAyDropdown() {
            const menu = document.getElementById('ay-dropdown-menu');
            const arrow = document.getElementById('ay-dropdown-arrow');
            const btn = document.getElementById('ay-dropdown-btn');

            if (!menu) return;

            menu.classList.remove('scale-y-100', 'opacity-100');
            menu.classList.add('scale-y-0', 'opacity-0');
            arrow.classList.remove('rotate-180');
            btn.classList.remove('border-cyan-500', 'ring-4', 'ring-cyan-500/5', 'bg-white');
            ayDropdownOpen = false;

            setTimeout(() => {
                if (!ayDropdownOpen) menu.classList.add('hidden');
            }, 200);
        }

        function selectAy(value) {
            document.getElementById('filter-ay-name').value = value;
            document.getElementById('ay-dropdown-text').textContent = value;
            closeAyDropdown();
            document.getElementById('filter-ay-name').form.submit();
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            const groupContainer = document.getElementById('group-dropdown-container');
            const ayContainer = document.getElementById('ay-dropdown-container');
            
            if (groupContainer && !groupContainer.contains(e.target) && groupDropdownOpen) {
                closeGroupDropdown();
            }
            if (ayContainer && !ayContainer.contains(e.target) && ayDropdownOpen) {
                closeAyDropdown();
            }
        });
    </script>

    <!-- Info Card & Table Recaps -->
    <?php if ($selected_group): ?>
        <!-- Print-only Header -->
        <div class="hidden print:block mb-6">
            <h2 class="text-2xl font-bold uppercase tracking-tight text-slate-950 border-b-2 border-slate-950 pb-2 flex justify-between items-center">
                <span>REKAPITULASI TAHFIDZ - <?php echo htmlspecialchars(strtoupper($selected_group['group_name'])); ?></span>
                <span class="text-sm font-semibold normal-case">Tahun Ajaran: <?php echo htmlspecialchars($selected_ay_name); ?></span>
            </h2>
            <p class="text-sm text-slate-700 mt-2"><strong>Pengampu Halaqah:</strong> <?php echo htmlspecialchars($selected_group['teacher_name']); ?></p>
        </div>

        <!-- Stats Grid (Desktop) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 no-print">
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-white shadow-md shadow-cyan-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Grup Halaqah</p>
                    <h3 class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($selected_group['group_name']); ?></h3>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white shadow-md shadow-emerald-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pengampu</p>
                    <h3 class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($selected_group['teacher_name']); ?></h3>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tahun Ajaran</p>
                    <h3 class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($selected_ay_name); ?></h3>
                </div>
            </div>
        </div>

        <!-- Recap Table Grid -->
        <div class="recap-table-container custom-scrollbar overflow-x-auto">
            <table class="recap-table">
                <thead>
                    <!-- First row of headers -->
                    <tr class="bg-slate-50 text-[11px] font-bold text-slate-800 uppercase text-center border-b border-slate-200">
                        <th rowspan="2" class="px-4 py-4 w-12 text-center bg-slate-50 text-slate-500 border-r border-slate-200/60">No</th>
                        <th rowspan="2" class="px-5 py-4 text-left min-w-[200px] bg-slate-50 text-slate-700 border-r border-slate-200/60">Nama Santri</th>
                        <th rowspan="2" class="px-4 py-4 w-16 text-center bg-slate-50 text-slate-600 border-r border-slate-200/60">Kelas</th>
                        
                        <!-- Semester 1 Main Header -->
                        <th colspan="3" class="px-4 py-3 text-center bg-cyan-50/50 text-cyan-800 border-r border-slate-200/60 border-b border-cyan-100/50 font-extrabold tracking-wide">Semester 1</th>
                        
                        <!-- Semester 2 Main Header -->
                        <th colspan="3" class="px-4 py-3 text-center bg-blue-50/50 text-blue-800 border-r border-slate-200/60 border-b border-blue-100/50 font-extrabold tracking-wide">Semester 2</th>
                        
                        <!-- Cumulative/Total Main Header -->
                        <th rowspan="2" class="px-5 py-4 text-center w-28 bg-emerald-50/30 text-emerald-800 border-r border-slate-200/60 font-extrabold">Hafalan (Juz)</th>
                        <th rowspan="2" class="px-4 py-4 text-center w-20 bg-emerald-50/30 text-emerald-800 border-r border-slate-200/60 font-extrabold">Target</th>
                        <th rowspan="2" class="px-5 py-4 text-center w-28 bg-emerald-50/30 text-emerald-800 font-extrabold">Status Akhir</th>
                    </tr>
                    <!-- Second row of headers -->
                    <tr class="bg-slate-50 text-[9px] font-bold text-slate-500 uppercase text-center border-b border-slate-200">
                        <!-- Semester 1 subheaders -->
                        <th class="px-4 py-3 w-28 bg-cyan-50/20 text-cyan-700 border-r border-slate-200/60">Setoran (Baris)</th>
                        <th class="px-3 py-3 w-16 bg-cyan-50/20 text-cyan-700 border-r border-slate-200/60">Target</th>
                        <th class="px-4 py-3 w-24 bg-cyan-50/20 text-cyan-700 border-r border-slate-200/60">Status</th>
                        <!-- Semester 2 subheaders -->
                        <th class="px-4 py-3 w-28 bg-blue-50/20 text-blue-700 border-r border-slate-200/60">Setoran (Baris)</th>
                        <th class="px-3 py-3 w-16 bg-blue-50/20 text-blue-700 border-r border-slate-200/60">Target</th>
                        <th class="px-4 py-3 w-24 bg-blue-50/20 text-blue-700 border-r border-slate-200/60">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($students_recap)): ?>
                        <tr><td colspan="12" class="px-6 py-12 text-center text-slate-400 italic font-medium bg-slate-50/30">Tidak ada data santri untuk halaqah ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students_recap as $index => $s): ?>
                            <tr class="hover:bg-slate-50/60 transition-colors text-center">
                                <td class="px-4 py-4 text-slate-400 font-medium border-r border-slate-100"><?php echo $index + 1; ?>.</td>
                                <td class="px-5 py-4 text-left font-bold text-slate-900 border-r border-slate-100">
                                    <div class="flex items-center justify-between group/row">
                                        <span><?php echo htmlspecialchars($s['name']); ?></span>
                                        <button type="button" onclick='openTargetDetailModal(<?php echo json_encode($s); ?>)' 
                                            class="ml-2 p-1.5 rounded-lg border border-slate-200 text-slate-400 hover:text-cyan-600 hover:bg-cyan-50 shadow-sm transition-all opacity-0 group-hover/row:opacity-100 focus:opacity-100 no-print" title="Lihat Target Hafalan">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 111.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-slate-600 font-semibold border-r border-slate-100"><?php echo htmlspecialchars($s['class']); ?></td>
                                
                                <!-- Semester 1 -->
                                <td class="px-4 py-4 text-slate-700 font-semibold border-r border-slate-100">
                                    <?php if ($s['sem1_display'] === 'Tahsin'): ?>
                                        <span class="status-badge status-tahsin">Tahsin</span>
                                    <?php else: ?>
                                        <span class="text-slate-800 font-bold text-base"><?php echo htmlspecialchars($s['sem1_display']); ?></span> 
                                        <span class="text-[10px] text-slate-450 font-medium block">baris</span>
                                        <span class="text-[9px] text-slate-400 font-normal block mt-0.5">(<?php echo htmlspecialchars($s['sem1_pages']); ?> hlm)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-4 text-slate-600 font-medium border-r border-slate-100">
                                    <?php if ($s['sem1_target'] === '-'): ?>
                                        <span class="text-slate-350">-</span>
                                    <?php else: ?>
                                        <span><?php echo htmlspecialchars($s['sem1_target']); ?></span>
                                        <span class="text-[10px] text-slate-400">Juz</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 border-r border-slate-100">
                                    <span class="status-badge <?php echo $s['sem1_keterangan'] === 'TERCAPAI' ? 'status-tercapai' : 'status-belum'; ?>">
                                        <?php echo htmlspecialchars($s['sem1_keterangan']); ?>
                                    </span>
                                </td>
                                
                                <!-- Semester 2 -->
                                <td class="px-4 py-4 text-slate-700 font-semibold border-r border-slate-100">
                                    <?php if ($s['sem2_display'] === 'Tahsin'): ?>
                                        <span class="status-badge status-tahsin">Tahsin</span>
                                    <?php else: ?>
                                        <span class="text-slate-800 font-bold text-base"><?php echo htmlspecialchars($s['sem2_display']); ?></span>
                                        <span class="text-[10px] text-slate-450 font-medium block">baris</span>
                                        <span class="text-[9px] text-slate-400 font-normal block mt-0.5">(<?php echo htmlspecialchars($s['sem2_pages']); ?> hlm)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-4 text-slate-600 font-medium border-r border-slate-100">
                                    <?php if ($s['sem2_target'] === '-'): ?>
                                        <span class="text-slate-350">-</span>
                                    <?php else: ?>
                                        <span><?php echo htmlspecialchars($s['sem2_target']); ?></span>
                                        <span class="text-[10px] text-slate-400">Juz</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 border-r border-slate-100">
                                    <span class="status-badge <?php echo $s['sem2_keterangan'] === 'TERCAPAI' ? 'status-tercapai' : 'status-belum'; ?>">
                                        <?php echo htmlspecialchars($s['sem2_keterangan']); ?>
                                    </span>
                                </td>
                                
                                <!-- Cumulative/Overall -->
                                <td class="px-5 py-4 font-black text-slate-900 border-r border-slate-100 text-base bg-emerald-50/5">
                                    <div class="flex flex-col items-center">
                                        <div>
                                            <span><?php echo htmlspecialchars($s['total_juz']); ?></span>
                                            <span class="text-[11px] text-slate-450 font-semibold">Juz</span>
                                        </div>
                                        <div class="w-full max-w-[100px] mt-2 bg-slate-100 rounded-full h-1.5 overflow-hidden shadow-inner no-print">
                                            <div class="bg-gradient-to-r from-cyan-500 to-blue-500 h-full rounded-full" style="width: <?php echo min(100, $s['progress_pct']); ?>%"></div>
                                        </div>
                                        <span class="text-[9px] text-slate-400 font-bold mt-1 no-print"><?php echo round($s['progress_pct'], 0); ?>%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-slate-600 font-medium border-r border-slate-100 bg-emerald-50/5">
                                    <span><?php echo htmlspecialchars($s['overall_target']); ?></span>
                                    <span class="text-[10px] text-slate-400">Juz</span>
                                </td>
                                <td class="px-5 py-4 bg-emerald-50/5">
                                    <span class="status-badge <?php echo $s['overall_keterangan'] === 'TERCAPAI' ? 'status-tercapai' : 'status-belum'; ?>">
                                        <?php echo htmlspecialchars($s['overall_keterangan']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="py-24 bg-white rounded-2xl border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 text-center px-6 shadow-sm">
            <div class="h-20 w-20 rounded-full bg-slate-50 flex items-center justify-center mb-6 text-slate-350 shadow-inner">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <p class="text-xl font-bold text-slate-700">Pilih Halaqah dan Tahun Ajaran</p>
            <p class="text-sm mt-2 max-w-sm text-slate-500 font-medium">Silakan tentukan filter halaqah dan periode tahun ajaran di atas untuk memuat data rekapitulasi hafalan santri.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Print Styling Sheet -->
<style>
@media print {
    @page { 
        margin: 0.8cm;
        size: A4 landscape;
    }
    body { 
        background: white !important; 
        font-family: 'Times New Roman', serif !important; 
        font-size: 9pt !important;
        color: #000000 !important;
    }
    #main-sidebar, header, .no-print, #sidebar-backdrop, .alert-banner { 
        display: none !important; 
    }
    .pb-10 { 
        padding: 0 !important; 
        margin: 0 !important; 
    }
    .recap-table-container { 
        border: none !important; 
        box-shadow: none !important; 
        overflow: visible !important;
    }
    
    .recap-table {
        border-collapse: collapse !important;
        width: 100% !important;
        margin-top: 15px !important;
    }
    .recap-table th, .recap-table td {
        border: 1px solid #000000 !important;
        padding: 5px 6px !important;
        color: #000000 !important;
        background: transparent !important;
        font-size: 8.5pt !important;
    }
    .recap-table th {
        background-color: #f1f5f9 !important;
        font-weight: bold !important;
        text-align: center !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Flatten badges for paper printing */
    .status-badge {
        border-radius: 0 !important;
        border: none !important;
        padding: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        font-size: 8.5pt !important;
        font-weight: bold !important;
        display: inline !important;
    }
    .status-tercapai {
        color: #166534 !important; /* Rich readable green on print */
    }
    .status-belum {
        color: #991b1b !important; /* Rich readable red on print */
    }
    .status-tahsin {
        color: #b45309 !important; /* Rich readable amber on print */
    }
}
</style>

<!-- Student Target Detail Modal -->
<div id="targetDetailModal" class="fixed inset-0 z-[60] invisible transition-all duration-300 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div id="detailModalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300" onclick="closeTargetDetailModal()"></div>
        
        <div id="detailModalContent" class="relative bg-white rounded-2xl shadow-2xl transform opacity-0 scale-95 transition-all duration-300 w-full max-w-md border border-slate-100 overflow-hidden">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Detail Capaian Target</h3>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5" id="detail-student-class"></p>
                </div>
                <button type="button" onclick="closeTargetDetailModal()" class="p-2 rounded-lg border border-slate-200 text-slate-400 hover:text-rose-600 shadow-sm transition-all group">
                    <svg class="h-5 w-5 transform group-hover:rotate-90 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Modal Body (The Target Hafalan Card) -->
            <div class="px-6 py-6 space-y-6">
                <div class="text-center">
                    <h4 class="text-2xl font-black text-slate-950" id="detail-student-name"></h4>
                    <p class="text-xs text-slate-400 font-semibold mt-1">Halaqah: <?php echo htmlspecialchars($selected_group['group_name'] ?? ''); ?></p>
                </div>

                <!-- The Beautiful Target Card -->
                <div class="bg-gradient-to-br from-cyan-50 to-blue-50/50 border border-cyan-100 rounded-2xl p-6 shadow-inner space-y-4">
                    <h5 class="text-xs font-extrabold text-cyan-800 uppercase tracking-widest flex items-center gap-2">
                        <span>🎯 TARGET HAFALAN</span>
                    </h5>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded-xl border border-cyan-100/50 shadow-sm">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Target Hafalan</span>
                            <span class="text-xl font-black text-slate-900 mt-1 inline-block"><span id="detail-target-juz"></span> <span class="text-xs font-semibold text-slate-500">Juz</span></span>
                        </div>
                        <div class="bg-white p-4 rounded-xl border border-cyan-100/50 shadow-sm">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Hafalan Saat Ini</span>
                            <span class="text-xl font-black text-slate-900 mt-1 inline-block"><span id="detail-current-hafalan"></span> <span class="text-xs font-semibold text-slate-500">Juz</span></span>
                        </div>
                        <div class="bg-white p-4 rounded-xl border border-cyan-100/50 shadow-sm">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Sisa Target</span>
                            <span class="text-xl font-black text-slate-900 mt-1 inline-block"><span id="detail-remaining-target"></span> <span class="text-xs font-semibold text-slate-500">Juz</span></span>
                        </div>
                        <div class="bg-white p-4 rounded-xl border border-cyan-100/50 shadow-sm">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Progress</span>
                            <span class="text-xl font-black text-cyan-600 mt-1 inline-block"><span id="detail-progress-pct"></span>%</span>
                        </div>
                    </div>

                    <!-- Progress Bar Visual -->
                    <div class="space-y-2">
                        <div class="flex justify-between text-xs font-bold text-slate-500">
                            <span>Progress Ketercapaian</span>
                            <span id="detail-progress-pct-label"></span>
                        </div>
                        <div class="w-full bg-slate-200/80 rounded-full h-3 overflow-hidden shadow-inner border border-slate-200/30">
                            <div id="detail-progress-bar" class="bg-gradient-to-r from-cyan-500 to-blue-600 h-full rounded-full transition-all duration-700" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Details breakdown per semester -->
                <div class="space-y-3">
                    <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest ml-1">Detail Rincian Semester</p>
                    
                    <div class="border border-slate-100 rounded-xl overflow-hidden divide-y divide-slate-100 text-xs">
                        <div class="flex justify-between items-center p-3 hover:bg-slate-50">
                            <span class="font-semibold text-slate-600">Target Semester 1 (Ganjil)</span>
                            <span class="font-bold text-slate-800"><span id="detail-target-sem1"></span> Juz</span>
                        </div>
                        <div class="flex justify-between items-center p-3 hover:bg-slate-50">
                            <span class="font-semibold text-slate-600">Target Semester 2 (Genap)</span>
                            <span class="font-bold text-slate-800"><span id="detail-target-sem2"></span> Juz</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100">
                <button type="button" onclick="closeTargetDetailModal()" class="w-full h-12 rounded-xl bg-slate-850 text-white font-bold text-sm shadow-md hover:bg-slate-900 transition-all active:scale-[0.98]">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openTargetDetailModal(s) {
    const modal = document.getElementById('targetDetailModal');
    const overlay = document.getElementById('detailModalOverlay');
    const content = document.getElementById('detailModalContent');
    
    // Fill data
    document.getElementById('detail-student-name').textContent = s.name;
    document.getElementById('detail-student-class').textContent = 'Kelas ' + s.class + (s.program_id ? ' (' + s.program_id + ')' : '');
    
    document.getElementById('detail-target-juz').textContent = s.overall_target;
    document.getElementById('detail-current-hafalan').textContent = s.total_juz;
    document.getElementById('detail-remaining-target').textContent = s.sisa_target_raw !== null && s.sisa_target_raw !== undefined ? s.sisa_target_raw.toString().replace('.', ',') : '-';
    document.getElementById('detail-progress-pct').textContent = s.progress_pct !== null && s.progress_pct !== undefined ? s.progress_pct.toString().replace('.', ',') : '0';
    document.getElementById('detail-progress-pct-label').textContent = (s.progress_pct !== null && s.progress_pct !== undefined ? s.progress_pct.toString().replace('.', ',') : '0') + '%';
    
    document.getElementById('detail-target-sem1').textContent = s.target_sem1_raw !== null && s.target_sem1_raw !== undefined ? s.target_sem1_raw.toString().replace('.', ',') : '-';
    document.getElementById('detail-target-sem2').textContent = s.target_sem2_raw !== null && s.target_sem2_raw !== undefined ? s.target_sem2_raw.toString().replace('.', ',') : '-';
    
    // Animate progress bar
    const bar = document.getElementById('detail-progress-bar');
    bar.style.width = '0%';
    
    modal.classList.remove('invisible');
    setTimeout(() => {
        overlay.classList.remove('opacity-0');
        content.classList.remove('opacity-0', 'scale-95');
        
        // Trigger progress bar fill animation
        setTimeout(() => {
            bar.style.width = Math.min(100, s.progress_pct) + '%';
        }, 150);
    }, 10);
}

function closeTargetDetailModal() {
    const modal = document.getElementById('targetDetailModal');
    const overlay = document.getElementById('detailModalOverlay');
    const content = document.getElementById('detailModalContent');
    
    overlay.classList.add('opacity-0');
    content.classList.add('opacity-0', 'scale-95');
    
    setTimeout(() => {
        modal.classList.add('invisible');
    }, 300);
}
</script>

<?php include '../layouts/footer.php'; ?>
