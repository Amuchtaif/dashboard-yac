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

        <!-- Logo / Badge -->
        <div class="flex flex-col items-start mb-8">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-cyan-50 border border-cyan-100 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                    class="w-4 h-4 text-cyan-600">
                    <path fill-rule="evenodd"
                        d="M12.516 2.17a.75.75 0 00-1.032 0 11.209 11.209 0 01-7.877 3.08.75.75 0 00-.722.515A12.74 12.74 0 002.25 9.75c0 5.942 4.064 10.933 9.563 12.348a.749.749 0 00.374 0c5.499-1.415 9.563-6.406 9.563-12.348 0-1.39-.223-2.73-.635-3.985a.75.75 0 00-.722-.516l-.143.001c-2.996 0-5.717-1.17-7.734-3.08zm3.094 8.016a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z"
                        clip-rule="evenodd" />
                </svg>
                <span class="text-xs font-semibold text-cyan-700 tracking-wide uppercase">Admin Portal</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 mb-2">Attendance System</h1>
            <p class="text-slate-500 text-sm">Secure entry for administrators. Please authenticate.</p>
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
                    class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Email
                    Address</label>
                <div class="relative">
                    <input type="email" name="email" id="email" required
                        class="w-full pl-4 pr-10 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-sm placeholder:text-slate-400"
                        placeholder="admin@company.com">
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
                        class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Password</label>
                </div>
                <div class="relative">
                    <input type="password" name="password" id="password" required
                        class="w-full pl-4 pr-10 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all text-sm placeholder:text-slate-400"
                        placeholder="••••••••">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd"
                                d="M3.28 2.22a.75.75 0 00-1.06 1.06v.69c0 .488.134.96.388 1.405l7.63 13.9a.75.75 0 001.354 0l1.458-2.656a11.166 11.166 0 01-1.996-1.503L5.474 4.5H15a2.25 2.25 0 012.25 2.25v2.818c.552.27 1.05.65 1.5 1.135V6.75A3.75 3.75 0 0015 3H3.28zM15.42 12.067a.75.75 0 00-.916 1.183c.307.238.56.536.745.882.164.306.251.64.251.984 0 .344-.087.68-.251.985-.185.346-.438.644-.745.882a.75.75 0 00.916 1.183 4.192 4.192 0 001.33-2.065 4.185 4.185 0 000-1.968 4.192 4.192 0 00-1.33-2.065z"
                                clip-rule="evenodd" />
                            <path d="M11 13a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pb-2">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox"
                        class="h-4 w-4 text-cyan-600 focus:ring-cyan-500 border-gray-300 rounded">
                    <label for="remember-me" class="ml-2 block text-xs text-slate-500">
                        Remember me
                    </label>
                </div>
                <a href="#" class="text-xs font-medium text-cyan-600 hover:text-cyan-500">Forgot password?</a>
            </div>

            <button type="submit"
                class="w-full bg-[#007EA7] hover:bg-[#006A8E] text-white font-medium py-2.5 px-4 rounded-lg transition-all shadow-[0_4px_14px_0_rgba(0,126,167,0.39)] hover:shadow-[0_6px_20px_rgba(0,126,167,0.23)] hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">
                Sign In
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor" class="w-4 h-4 inline-block ml-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </button>
        </form>
    </div>

    <div class="fixed bottom-6 w-full text-center">
        <p class="text-xs text-slate-400">© 2026 Attendance Management System v2.1.0</p>
        <div class="mt-2 text-xs text-slate-300 space-x-4">
            <a href="#" class="hover:text-slate-500 transition-colors">Privacy Policy</a>
            <a href="#" class="hover:text-slate-500 transition-colors">Support</a>
        </div>
    </div>

</body>

</html>