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
        const toastContainer = document.getElementById('toast-container');

        // Create Toast Element
        const toast = document.createElement('div');
        toast.className = `flex items-center w-full max-w-xs p-4 mb-4 text-gray-500 bg-white rounded-lg shadow dark:text-gray-400 dark:bg-gray-800 transition-all transform -translate-y-full opacity-0 duration-300 ease-out border-l-4 ${type === 'success' ? 'border-green-500' : 'border-red-500'}`;
        toast.role = 'alert';

        // Icon
        let iconHtml = '';
        if (type === 'success') {
            iconHtml = `<div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-green-500 bg-green-100 rounded-lg dark:bg-green-800 dark:text-green-200">
                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z"/>
                </svg>
                <span class="sr-only">Check icon</span>
            </div>`;
        } else {
            iconHtml = `<div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-red-500 bg-red-100 rounded-lg dark:bg-red-800 dark:text-red-200">
                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 11.793a1 1 0 1 1-1.414 1.414L10 11.414l-2.293 2.293a1 1 0 0 1-1.414-1.414L8.586 10 6.293 7.707a1 1 0 0 1 1.414-1.414L10 8.586l2.293-2.293a1 1 0 0 1 1.414 1.414L11.414 10l2.293 2.293Z"/>
                </svg>
                <span class="sr-only">Error icon</span>
            </div>`;
        }

        toast.innerHTML = `
            ${iconHtml}
            <div class="ml-3 text-sm font-normal">${message}</div>
            <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700" aria-label="Close" onclick="this.parentElement.remove()">
                <span class="sr-only">Tutup</span>
                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                </svg>
            </button>
        `;

        toastContainer.appendChild(toast);

        // Animate In
        setTimeout(() => {
            toast.classList.remove('-translate-y-full', 'opacity-0');
        }, 10);

        // Auto Remove
        setTimeout(() => {
            toast.classList.add('opacity-0');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 5000);
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
            
            const container = document.createElement('div');
            container.className = 'hybrid-select-container';
            
            const currentText = select.options[select.selectedIndex]?.text || 'Pilih...';
            
            container.innerHTML = `
                <div class="relative">
                    <input type="text" class="hybrid-search-input" placeholder="${currentText}" readonly>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                <div class="hybrid-select-dropdown">
                    <div class="sticky top-0 bg-white p-2 border-b border-slate-100">
                        <input type="text" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500" placeholder="Ketik untuk mencari...">
                    </div>
                    <div class="options-list"></div>
                </div>
            `;
            
            select.parentNode.insertBefore(container, select);
            select.classList.add('hidden');
            select.dataset.hybridInit = 'true';
            
            const mainInput = container.querySelector('.hybrid-search-input');
            const dropdown = container.querySelector('.hybrid-select-dropdown');
            const searchInput = dropdown.querySelector('input');
            const optionsList = dropdown.querySelector('.options-list');
            const arrow = container.querySelector('svg');
            
            const renderOptions = (filter = '') => {
                optionsList.innerHTML = '';
                Array.from(select.options).forEach(opt => {
                    if (opt.text.toLowerCase().includes(filter.toLowerCase())) {
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
                            renderOptions();
                        };
                        optionsList.appendChild(div);
                    }
                });
                if (optionsList.innerHTML === '') {
                    optionsList.innerHTML = '<div class="p-4 text-center text-xs text-slate-400 italic">Tidak ditemukan</div>';
                }
            };
            
            mainInput.onclick = (e) => {
                e.stopPropagation();
                // Close others
                document.querySelectorAll('.hybrid-select-dropdown').forEach(d => {
                    if (d !== dropdown) d.classList.remove('active');
                });
                
                dropdown.classList.toggle('active');
                arrow.classList.toggle('rotate-180');
                if (dropdown.classList.contains('active')) {
                    searchInput.focus();
                    renderOptions();
                }
            };
            
            searchInput.oninput = (e) => {
                renderOptions(e.target.value);
            };
            
            searchInput.onclick = (e) => e.stopPropagation();
            
            renderOptions();
        });
    }
</script>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 flex flex-col gap-2"></div>

</body>

</html>