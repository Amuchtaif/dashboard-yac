<div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
    <div class="relative mb-8">
        <!-- Decoration Circles -->
        <div class="absolute -inset-4 bg-rose-50 rounded-full animate-pulse"></div>
        <div class="relative bg-white p-6 rounded-3xl shadow-xl border border-rose-100">
            <svg class="w-16 h-16 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </svg>
        </div>
    </div>

    <h2 class="text-3xl font-black text-slate-800 mb-2">Akses Terbatas</h2>
    <p class="text-slate-500 max-w-md mx-auto leading-relaxed">
        Maaf, halaman ini hanya dapat diakses oleh
        <span class="font-bold text-rose-600">Wali Kelas</span> yang ditugaskan.
        Jika Anda adalah Wali Kelas dan masih melihat pesan ini, silakan hubungi Administrator.
    </p>

    <div class="mt-10 flex flex-col sm:flex-row gap-4">
        <a href="<?php echo url('views/dashboard/index'); ?>"
            class="inline-flex items-center justify-center px-8 py-3.5 rounded-2xl bg-slate-900 text-white text-sm font-bold hover:bg-slate-800 transition-all active:scale-95 shadow-lg shadow-slate-900/20">
            Kembali ke Beranda
        </a>
        <button onclick="window.history.back()"
            class="inline-flex items-center justify-center px-8 py-3.5 rounded-2xl bg-white border border-slate-200 text-slate-600 text-sm font-bold hover:bg-slate-50 transition-all active:scale-95">
            Halaman Sebelumnya
        </button>
    </div>

    <div class="mt-12 pt-8 border-t border-slate-100 w-full max-w-xs">
        <p class="text-[10px] text-slate-400 font-medium uppercase tracking-[0.2em]">Pesan Sistem:
            <?php echo $error_message ?? 'Unauthorized Access'; ?></p>
    </div>
</div>