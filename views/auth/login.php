<?php
require_once '../../config/app.php';

if (isset($_SESSION['user_id'])) {
    redirect('views/dashboard/index.php');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?php url('assets/images/favicon.png'); ?>">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', 'Outfit', sans-serif;
        }

        .bg-custom-blue {
            background-color: #0575E6;
        }

        .text-custom-blue {
            color: #0575E6;
        }

        .btn-custom-blue {
            background-color: #0575E6;
        }

        .btn-custom-blue:hover {
            background-color: #0465c7;
        }

        .btn-custom-blue:active {
            background-color: #0357ac;
        }

        /* Subtle smooth animation */
        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="bg-white text-slate-800 antialiased min-h-screen flex flex-col lg:flex-row">

    <!-- Left Column: Illustration -->
    <div class="hidden lg:flex lg:w-1/2 bg-white items-center justify-center p-8 xl:p-16 select-none">
        <div class="w-full max-w-[460px] xl:max-w-[500px] flex items-center justify-center">
            <img src="<?php url('public/images/login-illustration-hd.png'); ?>" 
                 alt="Ilustrasi Login" 
                 class="w-full h-auto object-contain pointer-events-none drop-shadow-sm transition-transform duration-500 hover:scale-[1.02]">
        </div>
    </div>

    <!-- Right Column: Blue Container with Rounded Top-Left & Decorative Circles -->
    <div class="flex-1 w-full lg:w-1/2 bg-custom-blue min-h-screen relative flex items-center justify-center p-6 sm:p-10 lg:p-12 overflow-hidden rounded-t-[36px] lg:rounded-t-none lg:rounded-tl-[48px]">

        <!-- Decorative Concentric Circles at Bottom-Right -->
        <div class="absolute -bottom-32 -right-32 w-[460px] h-[460px] rounded-full border-[1.5px] border-white/35 pointer-events-none"></div>
        <div class="absolute -bottom-60 -right-60 w-[680px] h-[680px] rounded-full border-[1.5px] border-white/25 pointer-events-none"></div>
        <div class="absolute -bottom-88 -right-88 w-[900px] h-[900px] rounded-full border-[1.5px] border-white/15 pointer-events-none"></div>

        <!-- Mobile-only top illustration/brand snippet -->
        <div class="lg:hidden absolute top-6 left-6 flex items-center gap-2 text-white/90">
            <img src="<?php url('public/images/logo.png'); ?>" alt="Logo" class="w-7 h-7 object-contain">
            <span class="text-sm font-semibold tracking-wider uppercase"><?php echo APP_NAME; ?></span>
        </div>

        <!-- Center Login Card -->
        <div class="w-full max-w-[390px] bg-white rounded-[24px] shadow-[0_20px_60px_-15px_rgba(0,0,0,0.18)] p-8 sm:p-10 relative z-10 fade-in">
            
            <!-- Card Header with Logo on the right of Ahlan -->
            <div class="mb-6">
                <div class="flex items-center justify-between gap-3 mb-1">
                    <h1 class="text-[30px] font-extrabold text-[#1e2530] tracking-tight leading-tight">Ahlan!</h1>
                    <img src="<?php url('public/images/logo.png'); ?>" 
                         alt="Logo <?php echo APP_NAME; ?>" 
                         class="hidden lg:block h-11 w-auto object-contain shrink-0">
                </div>
                <p class="text-[14px] font-medium text-slate-500">Silakan masukan akun Anda</p>
            </div>

            <!-- Error Notification Alert -->
            <?php if (isset($_GET['error'])): ?>
                <div id="error-alert"
                    class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-2xl mb-5 text-xs font-medium flex items-center gap-2.5 transition-all duration-300">
                    <i class="fa-solid fa-circle-exclamation shrink-0 text-red-500 text-sm"></i>
                    <span><?php echo htmlspecialchars($_GET['error']); ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="<?php url('logic/auth/login.php'); ?>" method="POST" class="space-y-4">
                
                <!-- Email Field -->
                <div>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-envelope text-[17px]"></i>
                        </span>
                        <input type="email" 
                               name="email" 
                               id="email" 
                               required
                               placeholder="Alamat Email"
                               class="w-full pl-11 pr-4 py-3 bg-white text-slate-800 text-[14px] rounded-full border border-slate-300 focus:outline-none focus:border-[#0575E6] focus:ring-2 focus:ring-[#0575E6]/20 transition-all placeholder:text-slate-400">
                    </div>
                </div>

                <!-- Password Field -->
                <div>
                    <div class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-[16px]"></i>
                        </span>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               required
                               placeholder="Kata Sandi"
                               class="w-full pl-11 pr-11 py-3 bg-white text-slate-800 text-[14px] rounded-full border border-slate-300 focus:outline-none focus:border-[#0575E6] focus:ring-2 focus:ring-[#0575E6]/20 transition-all placeholder:text-slate-400">
                        <button type="button" 
                                onclick="togglePassword()"
                                class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none transition-colors"
                                title="Tampilkan / sembunyikan kata sandi">
                            <i id="eye-icon" class="fa-solid fa-eye text-sm"></i>
                            <i id="eye-off-icon" class="fa-solid fa-eye-slash text-sm hidden"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit"
                        class="w-full py-3 px-6 rounded-full btn-custom-blue text-white font-medium text-[15px] transition-all shadow-[0_4px_16px_rgba(5,117,230,0.32)] hover:shadow-[0_6px_22px_rgba(5,117,230,0.42)] active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-[#0575E6]/40">
                        Masuk
                    </button>
                </div>

                <!-- Forgot Password Link -->
                <div class="pt-1">
                    <a href="https://wa.me/6289651804382" 
                       target="_blank" 
                       rel="noopener noreferrer" 
                       class="inline-block text-[13px] font-normal text-slate-500 hover:text-custom-blue transition-colors">
                        Lupa Kata Sandi?
                    </a>
                </div>
            </form>
        </div>

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

        // Auto-close error alert smoothly
        const errorAlert = document.getElementById('error-alert');
        if (errorAlert) {
            setTimeout(() => {
                errorAlert.style.opacity = '0';
                errorAlert.style.transform = 'translateY(-6px)';
                setTimeout(() => {
                    errorAlert.style.display = 'none';
                }, 400);
            }, 4000);
        }
    </script>
</body>

</html>
