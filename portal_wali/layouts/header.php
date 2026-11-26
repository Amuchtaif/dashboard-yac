<?php
// portal_wali/layouts/header.php
require_once __DIR__ . '/../init.php';

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$guardian_name = $_SESSION['guardian_name'] ?? 'Wali Santri';
$guardian_phone = $_SESSION['guardian_phone'] ?? '';
$active_child = get_active_child();
$all_children = !empty($_SESSION['guardian_id']) ? get_guardian_children($_SESSION['guardian_id']) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Portal Wali Santri Assunnah</title>

    <!-- PWA & Mobile Capabilities -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#448AFF">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Wali Santri">
    <link rel="apple-touch-icon" href="assets/logo.png">
    <link rel="icon" type="image/png" href="assets/logo.png">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                        arabic: ['Amiri', 'serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef5ff',
                            100: '#d9e8ff',
                            200: '#bcd7ff',
                            300: '#8ebdff',
                            400: '#5ea0ff',
                            500: '#448AFF',
                            600: '#3574e8',
                            700: '#255cc7',
                            800: '#1e489e',
                            900: '#193b7d',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #0f172a; /* Desktop backdrop */
            -webkit-tap-highlight-color: transparent;
        }
        /* Mobile safe-area paddings for notched screens */
        .safe-top {
            padding-top: env(safe-area-inset-top, 0px);
        }
        .safe-bottom {
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex justify-center">

    <!-- Mobile-First App Container (Constrained max-width for native phone feeling) -->
    <div class="w-full max-w-md bg-slate-50 min-h-screen flex flex-col shadow-2xl relative">
        
        <?php if (!empty($_SESSION['guardian_id'])): ?>
            <!-- Top Sticky App Bar -->
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200/80 px-4 py-3 safe-top">
                <div class="flex items-center justify-between">
                    <!-- Brand & Active Child Badge -->
                    <div class="flex items-center gap-2.5">
                        <img src="assets/logo.png" alt="Logo" class="w-8 h-8 object-contain">
                        <div>
                            <span class="text-[10px] font-bold text-[#448AFF] tracking-wider uppercase block leading-none">Yayasan Assunnah</span>
                            <span class="text-xs font-extrabold text-slate-800 leading-tight">Portal Wali Santri</span>
                        </div>
                    </div>

                    <!-- Child Switcher Pill Button -->
                    <?php if ($active_child): ?>
                        <button type="button" onclick="openChildModal()" 
                                class="flex items-center gap-2 bg-brand-50 hover:bg-brand-100 border border-brand-200/80 text-brand-800 px-2.5 py-1 rounded-full text-xs font-semibold transition-all">
                            <span class="w-5 h-5 rounded-full bg-[#448AFF] text-white flex items-center justify-center text-[10px]">
                                <i class="fa-solid fa-child-reaching"></i>
                            </span>
                            <span class="max-w-[100px] truncate"><?php echo htmlspecialchars(explode(' ', trim($active_child['nama_siswa']))[0]); ?></span>
                            <?php if (count($all_children) > 1): ?>
                                <i class="fa-solid fa-chevron-down text-[9px] text-[#448AFF]"></i>
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>
                </div>
            </header>
        <?php endif; ?>

        <!-- Main Content Area -->
        <main class="flex-1 pb-24">

