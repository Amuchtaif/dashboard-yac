<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>
        <?php echo APP_NAME; ?>
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/images/favicon.png">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f1f5f9;
        }

        /* Slate-100/Background */

        /* Custom Checkbox Style */
        .custom-checkbox {
            appearance: none;
            -webkit-appearance: none;
            background-color: #fff;
            margin: 0;
            font: inherit;
            color: currentColor;
            width: 1.25rem;
            height: 1.25rem;
            border: 1px solid #cbd5e1;
            /* slate-300 */
            border-radius: 0.375rem;
            /* rounded-md */
            display: grid;
            place-content: center;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }

        .custom-checkbox:hover {
            border-color: #94a3b8;
            /* slate-400 */
        }

        .custom-checkbox:checked {
            background-color: #3b82f6;
            /* blue-500 matches the vibrant image blue better */
            border-color: #3b82f6;
            background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e");
        }

        .custom-checkbox:focus {
            outline: 2px solid #bfdbfe;
            /* blue-200 */
            outline-offset: 2px;
        }

        /* Custom Select Style */
        .select-custom {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.25em 1.25em;
            padding-right: 2.5rem !important;
            transition: all 0.2s ease-in-out;
        }

        .select-custom:hover {
            border-color: #94a3b8;
            background-color: #f8fafc;
        }

        .select-custom:focus {
            ring: 2px;
            ring-color: #3b82f6;
            border-color: #3b82f6;
            outline: none;
        }

        /* Searchable Select Styles */
        .hybrid-select-container {
            position: relative;
            width: 100%;
        }

        .hybrid-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 50;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1.25rem;
            margin-top: 0.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-height: 250px;
            overflow-y: auto;
            display: none;
        }

        .hybrid-select-dropdown.active {
            display: block;
            animation: dropdownFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes dropdownFadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hybrid-option {
            padding: 0.875rem 1.25rem;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
            color: #475569;
            font-weight: 500;
        }

        .hybrid-option:hover {
            background-color: #ecfeff;
            color: #0891b2;
            padding-left: 1.5rem;
        }

        .hybrid-option.selected {
            background-color: #cffafe;
            color: #0891b2;
            font-weight: 700;
        }

        .hybrid-search-input {
            width: 100%;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            background-color: #fff;
            padding: 0.875rem 1.25rem;
            padding-right: 2.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
            cursor: pointer;
            font-weight: 600;
            color: #1e293b;
        }

        .hybrid-search-input:focus {
            border-color: #06b6d4;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
            outline: none;
        }

        /* Modal Animations */
        .modal-enter {
            opacity: 0;
            transform: scale(0.95) translateY(10px);
        }
        .modal-enter-active {
            opacity: 1;
            transform: scale(1) translateY(0);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        }
        .modal-exit {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
        .modal-exit-active {
            opacity: 0;
            transform: scale(0.95) translateY(10px);
            transition: opacity 0.2s ease-in, transform 0.2s ease-in;
        }

        .backdrop-enter { opacity: 0; }
        .backdrop-enter-active { opacity: 1; transition: opacity 0.3s ease-out; }
        .backdrop-exit { opacity: 1; }
        .backdrop-exit-active { opacity: 0; transition: opacity 0.2s ease-in; }
    </style>
</head>

<body class="h-screen h-[100dvh] flex overflow-hidden bg-slate-100">
    <!-- Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 min-w-0 md:pl-64 overflow-hidden relative">

        <!-- Top Header Bar -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-4 md:px-8 z-30 absolute top-0 left-0 md:left-64 right-0 transition-all duration-300">
            <!-- Left Side: Hamburger (Mobile) / Page Title (Desktop) -->
            <div class="flex items-center gap-4">
                <button type="button" onclick="toggleSidebar()" class="md:hidden text-slate-500 hover:text-slate-700 p-2 rounded-lg hover:bg-slate-50 transition-colors">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <!-- Page title removed from header to avoid redundancy with body content -->
            </div>

            <!-- Right Actions: Search + Icons -->
            <div class="flex items-center gap-3 md:gap-6">
                <!-- Search -->
                <div class="relative hidden lg:block">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-search text-slate-400 text-sm"></i>
                    </span>
                    <input type="text"
                        class="bg-slate-50 border border-slate-200 text-slate-600 sm:text-sm rounded-full pl-10 pr-4 py-2 w-48 xl:w-64 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all placeholder:text-slate-400"
                        placeholder="Cari pegawai, data...">
                </div>

                <!-- Notification Icon -->
                <div class="flex items-center gap-3 md:gap-4 text-slate-500">
                    <button class="relative hover:text-cyan-600 transition-colors p-1 flex items-center justify-center">
                        <i class="fa-solid fa-bell text-lg"></i>
                        <span
                            class="absolute top-1 right-1 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                    </button>

                    <!-- Profile Dropdown -->
                    <div class="relative ml-1" id="profile-dropdown-container">
                        <button type="button" onclick="toggleProfileDropdown()"
                            class="flex items-center focus:outline-none transition-transform hover:scale-105 active:scale-95">
                            <?php 
                                $profile_name = $_SESSION['user_name'] ?? 'User';
                                $profile_photo = $_SESSION['user_photo'] ?? '';
                                $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($profile_name) . "&background=random";
                                if (!empty($profile_photo)) {
                                    if (defined('BASE_PATH') && file_exists(BASE_PATH . '/uploads/profile_photos/' . $profile_photo)) {
                                        $avatar_url = BASE_URL . '/uploads/profile_photos/' . $profile_photo;
                                    } elseif (defined('BASE_PATH') && file_exists(BASE_PATH . '/public/uploads/employees/' . $profile_photo)) {
                                        $avatar_url = BASE_URL . '/public/uploads/employees/' . $profile_photo;
                                    } elseif (file_exists(__DIR__ . '/../../uploads/profile_photos/' . $profile_photo)) {
                                        $avatar_url = BASE_URL . '/uploads/profile_photos/' . $profile_photo;
                                    }
                                }
                            ?>
                            <img class="h-8 w-8 rounded-full border border-slate-200 object-cover shadow-sm"
                                src="<?php echo $avatar_url; ?>" alt="User Profile"
                                onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($profile_name); ?>&background=random';">
                            <span class="hidden md:block ml-2 text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($profile_name); ?></span>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="profile-menu"
                            class="hidden absolute right-0 mt-3 w-56 origin-top-right rounded-2xl bg-white p-2 shadow-2xl ring-1 ring-slate-900/5 focus:outline-none z-50 transition-all duration-200 ease-out transform scale-95 opacity-0">
                            <!-- User Info Header -->
                            <div class="px-4 py-2.5 border-b border-slate-100 mb-1">
                                <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Sudah Masuk</p>
                                <p class="text-sm font-bold text-slate-800 truncate mt-0.5"><?php echo htmlspecialchars($profile_name); ?></p>
                                <p class="text-[11px] text-slate-500 font-medium truncate"><?php echo htmlspecialchars($_SESSION['position_name'] ?? 'Pegawai'); ?></p>
                            </div>
                            <!-- Links -->
                            <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-lg font-medium transition-colors">
                                <i class="fa-solid fa-user text-slate-400 text-xs w-4 text-center"></i>
                                Profil Saya
                            </a>
                            <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-lg font-medium transition-colors">
                                <i class="fa-solid fa-gear text-slate-400 text-xs w-4 text-center"></i>
                                Pengaturan
                            </a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <a href="<?php url('logic/auth/logout.php'); ?>"
                                class="flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-rose-50 rounded-lg font-bold transition-colors">
                                <i class="fa-solid fa-right-from-bracket text-red-500 text-xs w-4 text-center"></i>
                                Keluar Aplikasi
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Sidebar Backdrop (Mobile Only) -->
        <div id="sidebar-backdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/60 z-40 hidden md:hidden transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>

        <script>
            function toggleSidebar() {
                const sidebar = document.getElementById('main-sidebar');
                const backdrop = document.getElementById('sidebar-backdrop');
                
                if (sidebar.classList.contains('-translate-x-full')) {
                    // Open
                    sidebar.classList.remove('-translate-x-full');
                    sidebar.classList.add('translate-x-0');
                    backdrop.classList.remove('hidden');
                    setTimeout(() => backdrop.classList.add('opacity-100'), 10);
                } else {
                    // Close
                    sidebar.classList.add('-translate-x-full');
                    sidebar.classList.remove('translate-x-0');
                    backdrop.classList.remove('opacity-100');
                    setTimeout(() => backdrop.classList.add('hidden'), 300);
                }
            }

            function toggleProfileDropdown() {
                const menu = document.getElementById('profile-menu');
                if (menu.classList.contains('hidden')) {
                    menu.classList.remove('hidden');
                    setTimeout(() => {
                        menu.classList.remove('scale-95', 'opacity-0');
                        menu.classList.add('scale-100', 'opacity-100');
                    }, 10);
                } else {
                    menu.classList.remove('scale-100', 'opacity-100');
                    menu.classList.add('scale-95', 'opacity-0');
                    setTimeout(() => {
                        menu.classList.add('hidden');
                    }, 200);
                }
            }

            // Close dropdown and sidebar when clicking outside
            document.addEventListener('click', function (event) {
                const dropdownContainer = document.getElementById('profile-dropdown-container');
                const menu = document.getElementById('profile-menu');
                
                if (dropdownContainer && !dropdownContainer.contains(event.target)) {
                    if (menu && !menu.classList.contains('hidden')) {
                        menu.classList.remove('scale-100', 'opacity-100');
                        menu.classList.add('scale-95', 'opacity-0');
                        setTimeout(() => {
                            menu.classList.add('hidden');
                        }, 200);
                    }
                }
            });

            // Scroll Header Effect
            document.addEventListener('DOMContentLoaded', () => {
                const mainScroll = document.getElementById('main-content-scroll');
                const mainHeader = document.querySelector('header');
                if (mainScroll && mainHeader) {
                    mainScroll.addEventListener('scroll', () => {
                        if (mainScroll.scrollTop > 10) {
                            mainHeader.classList.add('bg-white/80', 'backdrop-blur-md', 'shadow-sm', 'border-slate-200/50');
                            mainHeader.classList.remove('bg-white', 'border-slate-200');
                        } else {
                            mainHeader.classList.remove('bg-white/80', 'backdrop-blur-md', 'shadow-sm', 'border-slate-200/50');
                            mainHeader.classList.add('bg-white', 'border-slate-200');
                        }
                    });
                }
            });
        </script>

        <main class="flex-1 overflow-y-auto focus:outline-none pt-16" id="main-content-scroll">
            <div class="pt-6 pb-24 px-4 sm:px-6 lg:px-8">
                <!-- Global Notifications (Section Bar Style) -->
                <div id="dynamic-alert-container"></div>
                
                <?php 
                $success_msg = $_GET['success'] ?? $_SESSION['success'] ?? null;
                $error_msg = $_GET['error'] ?? $_SESSION['error'] ?? null;
                if (isset($_SESSION['success'])) unset($_SESSION['success']);
                if (isset($_SESSION['error'])) unset($_SESSION['error']);
                ?>

                <?php if ($success_msg): ?>
                    <div id="alert-success" class="alert-banner mb-6 rounded-xl bg-emerald-600 shadow-2xl px-5 py-4 border border-emerald-500/30 transition-all duration-500 flex flex-wrap items-center justify-between animate-bounce-in">
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 bg-white/20 p-2 rounded-xl flex items-center justify-center">
                                <i class="fa-solid fa-circle-check text-white text-base"></i>
                            </div>
                            <div>
                                <p class="text-[11px] font-black text-emerald-100 uppercase tracking-widest leading-none mb-1">Berhasil!</p>
                                <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($success_msg); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-2 sm:mt-0">
                            <?php if (!empty($_GET['wa_phone']) && !empty($_GET['wa_text'])): ?>
                                <a href="https://wa.me/<?php echo htmlspecialchars($_GET['wa_phone']); ?>?text=<?php echo urlencode($_GET['wa_text']); ?>" target="_blank"
                                   class="inline-flex items-center rounded-xl bg-white text-emerald-700 px-3 py-1.5 text-xs font-bold shadow-sm hover:bg-emerald-50 transition-colors">
                                    <i class="fa-brands fa-whatsapp mr-1.5 text-sm text-emerald-600"></i>
                                    Kirim Notifikasi WhatsApp (wa.me)
                                </a>
                            <?php endif; ?>
                            <button type="button" onclick="closeAlert('alert-success')" class="text-emerald-100 hover:text-white transition-colors focus:outline-none p-2 hover:bg-white/10 rounded-lg">
                                <i class="fa-solid fa-xmark text-sm"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div id="alert-error" class="alert-banner mb-6 rounded-xl bg-rose-600 shadow-2xl px-5 py-4 border border-rose-500/30 transition-all duration-500 flex items-center justify-between animate-bounce-in">
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 bg-white/20 p-2 rounded-xl flex items-center justify-center">
                                <i class="fa-solid fa-circle-xmark text-white text-base"></i>
                            </div>
                            <div>
                                <p class="text-[11px] font-black text-rose-100 uppercase tracking-widest leading-none mb-1">Error!</p>
                                <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($error_msg); ?></p>
                            </div>
                        </div>
                        <button type="button" onclick="closeAlert('alert-error')" class="text-rose-100 hover:text-white transition-colors focus:outline-none p-2 hover:bg-white/10 rounded-lg">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <script>
                    function closeAlert(id) {
                        const alertEl = document.getElementById(id);
                        if (alertEl) {
                            alertEl.classList.add('opacity-0', '-translate-y-4');
                            setTimeout(() => {
                                alertEl.style.display = 'none';
                            }, 500); 
                        }
                    }

                    // Auto hide static alerts after 3.5s
                    document.addEventListener('DOMContentLoaded', () => {
                        setTimeout(() => {
                            document.querySelectorAll('.alert-banner').forEach(alert => {
                                if (alert.id) closeAlert(alert.id);
                            });
                        }, 3500);
                    });

                </script>


                <!-- Content Below -->