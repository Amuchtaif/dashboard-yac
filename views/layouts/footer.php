</div>
</div>
</main>
</div>
<!-- Global Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog"
    aria-modal="true">
    <!-- Backdrop -->
    <div id="deleteModalBackdrop"
        class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Modal Panel -->
        <div id="deleteModalPanel"
            class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div
                        class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-bold leading-6 text-slate-900" id="modal-title">Hapus Item</h3>
                        <div class="mt-2 text-sm text-slate-500">
                            Apakah Anda yakin ingin menghapus item ini? Tindakan ini tidak dapat dibatalkan.
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                <a id="confirmDeleteBtn" href="#"
                    class="inline-flex w-full justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-all transform active:scale-95">
                    Hapus Item
                </a>
                <button type="button" onclick="closeDeleteModal()"
                    class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function openDeleteModal(url) {
        document.getElementById('confirmDeleteBtn').href = url;
        const modal = document.getElementById('deleteModal');
        const backdrop = document.getElementById('deleteModalBackdrop');
        const panel = document.getElementById('deleteModalPanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        const backdrop = document.getElementById('deleteModalBackdrop');
        const panel = document.getElementById('deleteModalPanel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // --- Toast Notification Logic ---
    function showToast(message, type = 'success') {
        const alertContainer = document.getElementById('dynamic-alert-container');
        if (!alertContainer) return;

        // Create Alert Banner Element
        const toast = document.createElement('div');
        // Match the exact PHP banner classes: w-full is implied by flex wrapping block context, but let's keep it safe.
        toast.className = `alert-banner mb-6 rounded-xl shadow-2xl px-5 py-4 border transition-all duration-500 flex items-center justify-between opacity-0 translate-y-4 ${type === 'success' ? 'bg-emerald-600 border-emerald-500/30' : 'bg-rose-600 border-rose-500/30'}`;
        toast.role = 'alert';

        let svgIcon = type === 'success' 
            ? `<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />`
            : `<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />`;
            
        let titleText = type === 'success' ? 'Berhasil!' : 'Error!';
        let titleColor = type === 'success' ? 'text-emerald-100' : 'text-rose-100';
        let btnColor = type === 'success' ? 'text-emerald-100' : 'text-rose-100';

        toast.innerHTML = `
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0 bg-white/20 p-2 rounded-xl flex items-center justify-center">
                    <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor">${svgIcon}</svg>
                </div>
                <div>
                     <p class="text-[11px] font-black ${titleColor} uppercase tracking-widest leading-none mb-1">${titleText}</p>
                     <p class="text-sm font-bold text-white">${message}</p>
                </div>
            </div>
            <button type="button" onclick="this.closest('[role=alert]').remove()" class="${btnColor} hover:text-white transition-colors focus:outline-none p-2 hover:bg-white/10 rounded-lg">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        `;

        alertContainer.appendChild(toast);

        // Animate In (Fade in and slide up to its normal position)
        setTimeout(() => {
            toast.classList.remove('opacity-0', 'translate-y-4');
        }, 10);

        // Auto Remove after 3 seconds specifically requested
        setTimeout(() => {
            toast.classList.add('opacity-0', '-translate-y-4');
            setTimeout(() => {
                toast.remove();
            }, 500);
        }, 3000);
    }

    // --- Custom Dropdown Logic ---
    let activeDropdownId = null;

    function toggleDropdown(id) {
        const menu = document.getElementById(id + '-menu');
        const arrow = document.getElementById(id + '-arrow');

        if (!menu) return;

        // Close currently active if different
        if (activeDropdownId && activeDropdownId !== id) {
            const activeMenu = document.getElementById(activeDropdownId + '-menu');
            const activeArrow = document.getElementById(activeDropdownId + '-arrow');
            if (activeMenu) activeMenu.classList.add('hidden');
            if (activeArrow) {
                activeArrow.classList.remove('rotate-180');
            }
        }

        if (menu.classList.contains('hidden')) {
            // Open
            menu.classList.remove('hidden');
            if (arrow) arrow.classList.add('rotate-180');
            activeDropdownId = id;
        } else {
            // Close
            menu.classList.add('hidden');
            if (arrow) arrow.classList.remove('rotate-180');
            activeDropdownId = null;
        }
    }

    function selectOption(id, value, text) {
        const input = document.getElementById(id + '-input');
        const textDisplay = document.getElementById(id + '-text');

        if (input) input.value = value;
        if (textDisplay) textDisplay.innerText = text;

        // Manual toggle to close
        const menu = document.getElementById(id + '-menu');
        const arrow = document.getElementById(id + '-arrow');
        if (menu) menu.classList.add('hidden');
        if (arrow) arrow.classList.remove('rotate-180');
        activeDropdownId = null;
    }

    // --- Auto-Close Global Banners ---
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-close banner alerts after 3 seconds
        const alertBanners = document.querySelectorAll('.alert-banner');
        alertBanners.forEach(banner => {
            setTimeout(() => {
                banner.classList.add('opacity-0', '-translate-y-4');
                setTimeout(() => {
                    banner.remove();

                    // Clean URL after banner is removed
                    const url = new URL(window.location);
                    if (url.searchParams.has('success') || url.searchParams.has('error')) {
                        url.searchParams.delete('success');
                        url.searchParams.delete('error');
                        window.history.replaceState({}, document.title, url);
                    }
                }, 500); // Wait for fade out animation
            }, 3000);
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (activeDropdownId) {
                const container = document.getElementById(activeDropdownId + '-container');
                if (container && !container.contains(e.target)) {
                    toggleDropdown(activeDropdownId);
                }
            }
            
            // Close hybrid selects
            if (!e.target.closest('.hybrid-select-container')) {
                document.querySelectorAll('.hybrid-select-dropdown').forEach(d => d.classList.remove('active'));
            }
        });

        // Initialize Hybrid Selects
        initHybridSelects();
    });

    function initHybridSelects() {
        document.querySelectorAll('select.hybrid-select').forEach(select => {
            if (select.dataset.hybridInit) return;
            
            const isSearchable = select.dataset.searchable !== 'false';
            const container = document.createElement('div');
            container.className = 'hybrid-select-container';
            
            const currentText = select.options[select.selectedIndex]?.text || 'Pilih...';
            
            container.innerHTML = `
                <div class="relative">
                    <input type="text" class="hybrid-search-input" placeholder="${currentText}" readonly>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                <div class="hybrid-select-dropdown">
                    ${isSearchable ? `
                    <div class="sticky top-0 bg-white p-3 border-b border-slate-100">
                        <input type="text" class="w-full px-4 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all" placeholder="Ketik untuk mencari...">
                    </div>` : ''}
                    <div class="options-list py-1"></div>
                </div>
            `;
            
            select.parentNode.insertBefore(container, select);
            select.classList.add('hidden');
            select.dataset.hybridInit = 'true';
            
            const mainInput = container.querySelector('.hybrid-search-input');
            const dropdown = container.querySelector('.hybrid-select-dropdown');
            const searchInput = dropdown.querySelector('input[type="text"]');
            const optionsList = dropdown.querySelector('.options-list');
            const arrow = container.querySelector('svg');
            
            const renderOptions = (filter = '') => {
                optionsList.innerHTML = '';
                Array.from(select.options).forEach(opt => {
                    const matches = opt.text.toLowerCase().includes(filter.toLowerCase());
                    if (matches) {
                        const div = document.createElement('div');
                        div.className = `hybrid-option ${opt.selected ? 'selected' : ''}`;
                        div.textContent = opt.text;
                        div.onclick = () => {
                            select.value = opt.value;
                            select.dispatchEvent(new Event('change'));
                            mainInput.placeholder = opt.text;
                            mainInput.value = '';
                            dropdown.classList.remove('active');
                            arrow.classList.remove('rotate-180');
                        };
                        optionsList.appendChild(div);
                    }
                });
                if (optionsList.innerHTML === '') {
                    optionsList.innerHTML = '<div class="p-8 text-center text-xs text-slate-400 font-bold uppercase tracking-widest">Tidak ditemukan</div>';
                }
            };
            
            mainInput.onclick = (e) => {
                e.stopPropagation();
                
                // Block if the underlying original select is disabled
                if (select.disabled) return;
                
                const isActive = dropdown.classList.contains('active');
                
                // Close others
                document.querySelectorAll('.hybrid-select-dropdown').forEach(d => d.classList.remove('active'));
                document.querySelectorAll('.hybrid-select-container svg').forEach(s => s.classList.remove('rotate-180'));
                
                if (!isActive) {
                    dropdown.classList.add('active');
                    arrow.classList.add('rotate-180');
                    if (isSearchable && searchInput) {
                        searchInput.value = '';
                        setTimeout(() => searchInput.focus(), 50);
                    }
                    renderOptions();
                }
            };
            
            // Apply initial disabled state visually
            if (select.disabled) {
                mainInput.classList.add('bg-slate-100', 'cursor-not-allowed', 'opacity-70');
            }
            
            // Watch for property changes using MutationObserver
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === "disabled") {
                        if (select.disabled) {
                            mainInput.classList.add('bg-slate-100', 'cursor-not-allowed', 'opacity-70');
                            dropdown.classList.remove('active');
                            arrow.classList.remove('rotate-180');
                        } else {
                            mainInput.classList.remove('bg-slate-100', 'cursor-not-allowed', 'opacity-70');
                        }
                    }
                });
            });
            observer.observe(select, { attributes: true });
            
            if (searchInput) {
                searchInput.oninput = (e) => renderOptions(e.target.value);
                searchInput.onclick = (e) => e.stopPropagation();
            }
            
            renderOptions();
        });
    }
</script>

<!-- Toast Container (Now acting as Alert Banner global dynamically triggered container) -->
<div id="toast-container" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-[100] flex flex-col gap-3 w-[95%] md:w-full max-w-2xl pointer-events-none *:pointer-events-auto"></div>

</body>

</html>