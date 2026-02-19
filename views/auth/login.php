<?php
require_once '../../config/app.php';

if (isset($_SESSION['user_id'])) {
    redirect('views/dashboard/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php url('assets/images/favicon.png'); ?>">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }

        .bg-grid-pattern {
            background-color: #f8fafc;
            background-image:
                linear-gradient(to right, #e2e8f0 1px, transparent 1px),
                linear-gradient(to bottom, #e2e8f0 1px, transparent 1px);
            background-size: 40px 40px;
        }
    </style>
</head>

<body class="bg-grid-pattern flex items-center justify-center h-screen px-4 text-slate-800">

    <div
        class="w-full max-w-[420px] bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 p-8 sm:p-10 relative">

        <!-- Logo -->
        <div class="flex flex-col items-center mb-8 text-center">
            <img src="<?php url('public/images/logo.png'); ?>" alt="Logo YAC" class="w-24 h-auto mb-4">
            <h3 class="text-lg font-semibold text-slate-700 uppercase tracking-wider">Dashboard YAC</h3>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div
                class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                        clip-rule="evenodd" />
                </svg>
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <form action="<?php url('logic/auth/login.php'); ?>" method="POST" class="space-y-5">
            <div>
                <label for="email"
                    class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Alamat Email</label>
                <div class="relative">
                    <input type="email" name="email" id="email" required
                        class="w-full pl-4 pr-10 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-sm placeholder:text-slate-400"
                        placeholder="nama@email.com">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path
                                d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                            <path
                                d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-2">
                    <label for="password"
                        class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Kata Sandi</label>
                </div>
                <div class="relative">
                    <input type="password" name="password" id="password" required
                        class="w-full pl-4 pr-10 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-sm placeholder:text-slate-400"
                        placeholder="••••••••">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2v-7a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pb-2">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox"
                        class="h-4 w-4 text-cyan-600 focus:ring-cyan-500 border-gray-300 rounded">
                    <label for="remember-me" class="ml-2 block text-xs text-slate-500">
                        Ingat saya
                    </label>
                </div>
                <a href="#" class="text-xs font-medium text-cyan-600 hover:text-cyan-500">Lupa kata sandi?</a>
            </div>

            <button type="submit"
                class="w-full bg-[#007EA7] hover:bg-[#006A8E] text-white font-medium py-2.5 px-4 rounded-lg transition-all shadow-[0_4px_14px_0_rgba(0,126,167,0.39)] hover:shadow-[0_6px_20px_rgba(0,126,167,0.23)] hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">
                Masuk
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor" class="w-4 h-4 inline-block ml-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </button>
        </form>
    </div>

    <div class="fixed bottom-6 w-full text-center">
        <p class="text-xs text-slate-400">Dashboard YAC made with <span class="text-red-500 inline-block animate-pulse">❤</span> by Abu Aufar</p>
        <div class="mt-2 text-xs text-slate-300 space-x-4">
            <a href="#" class="hover:text-slate-500 transition-colors">Kebijakan Privasi</a>
            <a href="#" class="hover:text-slate-500 transition-colors">Bantuan</a>
        </div>
    </div>

</body>

</html>