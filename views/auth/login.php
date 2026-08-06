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
            <div id="error-alert"
                class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 transition-all duration-500">
                <i class="fa-solid fa-circle-info h-5 w-5 shrink-0"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <form action="<?php url('logic/auth/login.php'); ?>" method="POST" class="space-y-5">
            <div>
                <label for="email"
                    class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Alamat
                    Email</label>
                <div class="relative">
                    <input type="email" name="email" id="email" required
                        class="w-full pl-4 pr-10 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-sm placeholder:text-slate-400"
                        placeholder="nama@email.com">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                        <i class="fa-solid fa-envelope w-5 h-5"></i>
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
                        class="w-full pl-4 pr-12 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-sm placeholder:text-slate-400"
                        placeholder="••••••••">
                    <button type="button" onclick="togglePassword()"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-cyan-600 transition-colors">
                        <i id="eye-icon" class="fa-solid fa-eye w-5 h-5"></i>
                        <i id="eye-off-icon" class="fa-solid fa-eye-slash w-5 h-5 hidden"></i>
                    </button>
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
                <i class="fa-solid fa-arrow-right w-4 h-4 inline-block ml-1"></i>
            </button>
        </form>
    </div>

    <div class="fixed bottom-6 w-full text-center">
        <p class="text-xs text-slate-400">Dashboard YAC made with <span
                class="text-red-500 inline-block animate-pulse">❤</span> by Abu Aufar</p>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeOffIcon = document.getElementById('eye-off-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }

        // Auto-close error alert
        const errorAlert = document.getElementById('error-alert');
        if (errorAlert) {
            setTimeout(() => {
                errorAlert.style.opacity = '0';
                errorAlert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    errorAlert.style.display = 'none';
                }, 500);
            }, 3000);
        }
    </script>

</body>

</html>