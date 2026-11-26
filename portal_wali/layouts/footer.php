<?php
// portal_wali/layouts/footer.php
require_once __DIR__ . '/bottom_nav.php';
?>
        </main>
        
        <!-- PWA Install Prompt Banner (Floating at bottom right/center if installable) -->
        <div id="pwaInstallBanner" class="fixed bottom-20 left-1/2 -translate-x-1/2 w-[92%] max-w-sm bg-slate-900/95 backdrop-blur-md text-white p-3.5 rounded-2xl shadow-xl border border-slate-700/60 hidden z-30 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <img src="assets/logo.png" alt="Logo" class="w-8 h-8 object-contain shrink-0">
                <div class="truncate">
                    <p class="text-xs font-bold text-white leading-tight">Pasang Aplikasi</p>
                    <p class="text-[10px] text-slate-300 truncate">Akses cepat dari layar utama HP Anda</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <button type="button" id="btnPwaInstall" class="px-3 py-1.5 bg-[#448AFF] hover:bg-brand-600 text-white text-xs font-bold rounded-lg transition-colors shadow-xs">
                    Pasang
                </button>
                <button type="button" onclick="dismissPwaBanner()" class="p-1.5 text-slate-400 hover:text-white">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        // Modal Child Switcher
        function openChildModal() {
            const modal = document.getElementById('childModal');
            if (!modal) return;
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.querySelector('div').classList.remove('translate-y-full');
            }, 10);
        }

        function closeChildModal(e) {
            const modal = document.getElementById('childModal');
            if (!modal) return;
            modal.classList.add('opacity-0');
            modal.querySelector('div').classList.add('translate-y-full');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        // PWA Service Worker Registration & Install Prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            const banner = document.getElementById('pwaInstallBanner');
            if (banner && !sessionStorage.getItem('pwa_dismissed')) {
                banner.classList.remove('hidden');
            }
        });

        const btnInstall = document.getElementById('btnPwaInstall');
        if (btnInstall) {
            btnInstall.addEventListener('click', async () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    deferredPrompt = null;
                    document.getElementById('pwaInstallBanner').classList.add('hidden');
                }
            });
        }

        function dismissPwaBanner() {
            const banner = document.getElementById('pwaInstallBanner');
            if (banner) banner.classList.add('hidden');
            sessionStorage.setItem('pwa_dismissed', 'true');
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').catch((err) => {
                    console.log('SW registration failed: ', err);
                });
            });
        }
    </script>
</body>
</html>

