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
            overflow: hidden;
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
    <div class="flex flex-col flex-1 min-w-0 md:pl-64 overflow-hidden">

        <!-- Top Header Bar -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-4 md:px-8 z-30 sticky top-0">
            <!-- Left Side: Hamburger (Mobile) / Page Title (Desktop) -->
            <div class="flex items-center gap-4">
                <button type="button" onclick="toggleSidebar()" class="md:hidden text-slate-500 hover:text-slate-700 p-2 rounded-lg hover:bg-slate-50 transition-colors">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <!-- Page title removed from header to avoid redundancy with body content -->
            </div>

            <!-- Right Actions: Search + Icons -->
            <div class="flex items-center gap-3 md:gap-6">
                <!-- Search -->
                <div class="relative hidden lg:block">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text"
                        class="bg-slate-50 border border-slate-200 text-slate-600 sm:text-sm rounded-full pl-10 pr-4 py-2 w-48 xl:w-64 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all placeholder:text-slate-400"
                        placeholder="Cari pegawai, data...">
                </div>

                <!-- Notification Icon -->
                <div class="flex items-center gap-3 md:gap-4 text-slate-500">
                    <button class="relative hover:text-cyan-600 transition-colors p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                            <path fill-rule="evenodd"
                                d="M5.25 9a6.75 6.75 0 0113.5 0v.75c0 2.123.8 4.057 2.118 5.52a.75.75 0 01-.297 1.206c-1.544.57-3.16.99-4.831 1.243a3.75 3.75 0 11-7.48 0 24.585 24.585 0 01-4.831-1.244.75.75 0 01-.298-1.205A8.217 8.217 0 005.25 9.75V9zm4.502 8.9a2.25 2.25 0 104.496 0 25.057 25.057 0 01-4.496 0z"
                                clip-rule="evenodd" />
                        </svg>
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
                                $avatar_url = !empty($profile_photo) 
                                    ? BASE_URL . '/public/uploads/employees/' . $profile_photo 
                                    : "https://ui-avatars.com/api/?name=" . urlencode($profile_name) . "&background=random";
                            ?>
                            <img class="h-8 w-8 rounded-full border border-slate-200 object-cover shadow-sm"
                                src="<?php echo $avatar_url; ?>" alt="User Profile">
                            <span class="hidden md:block ml-2 text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($profile_name); ?></span>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="profile-menu"
                            class="hidden absolute right-0 mt-3 w-48 origin-top-right rounded-xl bg-white py-2 shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                            <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 font-medium">Pengaturan</a>
                            <div class="border-t border-slate-100 my-1"></div>
                            <a href="<?php url('logic/auth/logout.php'); ?>"
                                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 font-semibold">Keluar</a>
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
        </script>

        <main class="flex-1 overflow-y-auto focus:outline-none">
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
                    <div id="alert-success" class="alert-banner mb-6 rounded-xl bg-emerald-600 shadow-2xl px-5 py-4 border border-emerald-500/30 transition-all duration-500 flex items-center justify-between animate-bounce-in">
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 bg-white/20 p-2 rounded-xl flex items-center justify-center">
                                <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[11px] font-black text-emerald-100 uppercase tracking-widest leading-none mb-1">Berhasil!</p>
                                <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($success_msg); ?></p>
                            </div>
                        </div>
                        <button type="button" onclick="closeAlert('alert-success')" class="text-emerald-100 hover:text-white transition-colors focus:outline-none p-2 hover:bg-white/10 rounded-lg">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div id="alert-error" class="alert-banner mb-6 rounded-xl bg-rose-600 shadow-2xl px-5 py-4 border border-rose-500/30 transition-all duration-500 flex items-center justify-between animate-bounce-in">
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 bg-white/20 p-2 rounded-xl flex items-center justify-center">
                                <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[11px] font-black text-rose-100 uppercase tracking-widest leading-none mb-1">Error!</p>
                                <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($error_msg); ?></p>
                            </div>
                        </div>
                        <button type="button" onclick="closeAlert('alert-error')" class="text-rose-100 hover:text-white transition-colors focus:outline-none p-2 hover:bg-white/10 rounded-lg">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
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