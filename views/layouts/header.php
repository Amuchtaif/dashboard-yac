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
    </style>
</head>

<body class="h-screen flex overflow-hidden bg-slate-100">
    <!-- Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 w-0 md:pl-64 overflow-hidden">

        <!-- Top Header Bar -->
        <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-8 z-10">
            <!-- Page Title -->
            <h1 class="text-xl font-bold text-slate-800">
            </h1>

            <!-- Right Actions: Search + Icons -->
            <div class="flex items-center gap-6">
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
                        class="bg-slate-50 border border-slate-200 text-slate-600 sm:text-sm rounded-full pl-10 pr-4 py-2 w-64 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all placeholder:text-slate-400"
                        placeholder="Cari pegawai, data...">
                </div>

                <!-- Notification Icon -->
                <div class="flex items-center gap-4 text-slate-500">
                    <button class="relative hover:text-cyan-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                            <path fill-rule="evenodd"
                                d="M5.25 9a6.75 6.75 0 0113.5 0v.75c0 2.123.8 4.057 2.118 5.52a.75.75 0 01-.297 1.206c-1.544.57-3.16.99-4.831 1.243a3.75 3.75 0 11-7.48 0 24.585 24.585 0 01-4.831-1.244.75.75 0 01-.298-1.205A8.217 8.217 0 005.25 9.75V9zm4.502 8.9a2.25 2.25 0 104.496 0 25.057 25.057 0 01-4.496 0z"
                                clip-rule="evenodd" />
                        </svg>
                        <span
                            class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                    </button>

                    <!-- Profile Dropdown -->
                    <div class="relative ml-2" id="profile-dropdown-container">
                        <button type="button" onclick="toggleProfileDropdown()"
                            class="flex items-center gap-2 focus:outline-none">
                            <img class="h-8 w-8 rounded-full border border-slate-200 object-cover"
                                src="https://ui-avatars.com/api/?name=Admin&background=random" alt="User Profile">
                            <span class="hidden md:block text-sm font-medium text-slate-700">Admin</span>
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="profile-menu"
                            class="hidden absolute right-0 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                            <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Pengaturan</a>
                            <a href="<?php url('logic/auth/logout.php'); ?>"
                                class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Keluar</a>
                        </div>
                    </div>
                </div>

                <script>
                    function toggleProfileDropdown() {
                        const menu = document.getElementById('profile-menu');
                        menu.classList.toggle('hidden');
                    }

                    // Close when clicking outside
                    document.addEventListener('click', function (e) {
                        const container = document.getElementById('profile-dropdown-container');
                        const menu = document.getElementById('profile-menu');
                        if (container && !container.contains(e.target)) {
                            menu.classList.add('hidden');
                        }
                    });
                </script>
            </div>
        </header>

        <!-- Mobile Menu Toggle (Hidden on Desktop) -->
        <div class="md:hidden flex items-center justify-between bg-white border-b border-slate-200 px-4 py-2">
            <span class="font-bold text-slate-700">AttendSys</span>
            <button type="button" class="text-slate-500 hover:text-slate-700">
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <main class="flex-1 overflow-y-auto focus:outline-none">
            <div class="py-6 px-4 sm:px-6 lg:px-8">
                <!-- Global Notifications -->
                <?php if (isset($_GET['success'])): ?>
                    <div
                        class="alert-banner mb-6 rounded-md bg-green-50 p-4 border border-green-200 transition-opacity duration-500 flex items-center gap-3">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($_GET['success']); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div
                        class="alert-banner mb-6 rounded-md bg-red-50 p-4 border border-red-200 transition-opacity duration-500 flex items-center gap-3">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($_GET['error']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Content Below -->