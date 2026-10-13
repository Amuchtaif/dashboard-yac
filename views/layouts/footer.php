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
                        <i class="fa-solid fa-triangle-exclamation h-6 w-6 text-red-600"></i>
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

<!-- Global Generic Confirmation Modal -->
<div id="confirmModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="confirm-modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div id="confirmModalBackdrop" class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Modal Panel -->
        <div id="confirmModalPanel" class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div id="confirmModalIconContainer" class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-cyan-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i id="confirmModalIcon" class="fa-solid fa-triangle-exclamation h-6 w-6 text-cyan-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-bold leading-6 text-slate-900" id="confirm-modal-title">Konfirmasi</h3>
                        <div class="mt-2 text-sm text-slate-500" id="confirm-modal-message">
                            Apakah Anda yakin ingin melanjutkan tindakan ini?
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                <a id="confirmModalBtn" href="#" class="inline-flex w-full justify-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 sm:w-auto transition-all transform active:scale-95">
                    Ya, Lanjutkan
                </a>
                <button type="button" onclick="closeConfirmModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
    function openDeleteModal(url) {
        const csrfToken = '<?php echo csrf_token(); ?>';
        if (url && !url.includes('csrf_token=')) {
            url += (url.includes('?') ? '&' : '?') + 'csrf_token=' + encodeURIComponent(csrfToken);
        }
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

    function openConfirmModal(url, title, message, color = 'cyan') {
        const csrfToken = '<?php echo csrf_token(); ?>';
        if (url && !url.includes('csrf_token=')) {
            url += (url.includes('?') ? '&' : '?') + 'csrf_token=' + encodeURIComponent(csrfToken);
        }
        document.getElementById('confirmModalBtn').href = url;
        document.getElementById('confirm-modal-title').innerText = title;
        document.getElementById('confirm-modal-message').innerText = message;
        
        const btn = document.getElementById('confirmModalBtn');
        const iconContainer = document.getElementById('confirmModalIconContainer');
        const icon = document.getElementById('confirmModalIcon');

        // Apply theme colors
        btn.className = `inline-flex w-full justify-center rounded-lg bg-${color}-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-${color}-500 sm:w-auto transition-all transform active:scale-95`;
        iconContainer.className = `mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-${color}-100 sm:mx-0 sm:h-10 sm:w-10`;
        icon.className = `h-6 w-6 text-${color}-600`;

        const modal = document.getElementById('confirmModal');
        const backdrop = document.getElementById('confirmModalBackdrop');
        const panel = document.getElementById('confirmModalPanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeConfirmModal() {
        const modal = document.getElementById('confirmModal');
        const backdrop = document.getElementById('confirmModalBackdrop');
        const panel = document.getElementById('confirmModalPanel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // --- Toast Notification Logic ---
    function showToast(message, type = 'success') {
        console.log(`showToast called: ${message} (${type})`);
        const alertContainer = document.getElementById('dynamic-alert-container');
        if (!alertContainer) {
            console.error('dynamic-alert-container not found!');
            return;
        }

        // Create Alert Banner Element
        const toast = document.createElement('div');
        // Match the exact PHP banner classes: w-full is implied by flex wrapping block context, but let's keep it safe.
        toast.className = `alert-banner mb-6 rounded-xl shadow-2xl px-5 py-4 border transition-all duration-500 flex items-center justify-between opacity-0 translate-y-4 ${type === 'success' ? 'bg-emerald-600 border-emerald-500/30' : 'bg-rose-600 border-rose-500/30'}`;
        toast.role = 'alert';

        let iconHtml = type === 'success' 
            ? `<i class="fa-solid fa-circle-check text-lg text-white"></i>`
            : `<i class="fa-solid fa-circle-xmark text-lg text-white"></i>`;
            
        let titleText = type === 'success' ? 'Berhasil!' : 'Error!';
        let titleColor = type === 'success' ? 'text-emerald-100' : 'text-rose-100';
        let btnColor = type === 'success' ? 'text-emerald-100' : 'text-rose-100';

        toast.innerHTML = `
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0 bg-white/20 p-2 rounded-xl flex items-center justify-center">
                    ${iconHtml}
                </div>
                <div>
                     <p class="text-[11px] font-black ${titleColor} uppercase tracking-widest leading-none mb-1">${titleText}</p>
                     <p class="text-sm font-bold text-white">${message}</p>
                </div>
            </div>
            <button type="button" onclick="this.closest('[role=alert]').remove()" class="${btnColor} hover:text-white transition-colors focus:outline-none p-2 hover:bg-white/10 rounded-lg">
                <i class="fa-solid fa-xmark h-5 w-5"></i>
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

    function toggleDropdown(id, event = null) {
        if (event && typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }

        const menu = document.getElementById(id + '-menu');
        const arrow = document.getElementById(id + '-arrow');
        const container = document.getElementById(id + '-container');

        if (!menu) return;

        // Close currently active if different
        if (activeDropdownId && activeDropdownId !== id) {
            const activeMenu = document.getElementById(activeDropdownId + '-menu');
            const activeArrow = document.getElementById(activeDropdownId + '-arrow');
            const activeContainer = document.getElementById(activeDropdownId + '-container');
            if (activeMenu) activeMenu.classList.add('hidden');
            if (activeArrow) activeArrow.classList.remove('rotate-180');
            if (activeContainer) activeContainer.classList.remove('z-[60]');
        }

        if (menu.classList.contains('hidden')) {
            // Open
            menu.classList.remove('hidden');
            if (arrow) arrow.classList.add('rotate-180');
            if (container) container.classList.add('z-[60]');
            activeDropdownId = id;
        } else {
            // Close
            menu.classList.add('hidden');
            if (arrow) arrow.classList.remove('rotate-180');
            if (container) container.classList.remove('z-[60]');
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
        const container = document.getElementById(id + '-container');
        if (menu) menu.classList.add('hidden');
        if (arrow) arrow.classList.remove('rotate-180');
        if (container) container.classList.remove('z-[60]');
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
                document.querySelectorAll('.hybrid-select-dropdown').forEach(d => d.classList.remove('active', 'dropup'));
                document.querySelectorAll('.hybrid-select-container').forEach(c => c.classList.remove('is-open'));
                document.querySelectorAll('.hybrid-select-container i.fa-chevron-down, .hybrid-select-container svg').forEach(s => s.classList.remove('rotate-180'));
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
            
            const currentOpt = select.options[select.selectedIndex];
            const currentVal = (currentOpt && currentOpt.value !== '') ? currentOpt.text : '';
            const currentPlaceholder = currentOpt ? currentOpt.text : 'Pilih...';
            
            container.innerHTML = `
                <div class="relative">
                    <input type="text" class="hybrid-search-input" value="${currentVal}" placeholder="${currentPlaceholder}" readonly>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <i class="fa-solid fa-chevron-down text-xs text-slate-500 transition-transform duration-150"></i>
                    </div>
                </div>
                <div class="hybrid-select-dropdown">
                    ${isSearchable ? `
                    <div class="sticky top-0 bg-white p-2 border-b border-slate-100">
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                            <input type="text" class="w-full pl-7 pr-3 py-1.5 text-xs border border-slate-200 rounded-md focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 bg-white placeholder:text-slate-400" placeholder="Ketik untuk mencari...">
                        </div>
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
            const arrow = container.querySelector('i.fa-chevron-down, svg');
            
            const syncPlaceholder = () => {
                const selectedOpt = select.options[select.selectedIndex];
                if (selectedOpt && selectedOpt.value !== '') {
                    mainInput.value = selectedOpt.text;
                    mainInput.placeholder = selectedOpt.text;
                } else {
                    mainInput.value = '';
                    mainInput.placeholder = selectedOpt ? selectedOpt.text : 'Pilih...';
                }
            };

            const renderOptions = (filter = '') => {
                optionsList.innerHTML = '';
                const q = filter.toLowerCase().trim();
                let hasMatch = false;

                Array.from(select.options).forEach(opt => {
                    const matches = !q || opt.text.toLowerCase().includes(q);
                    if (matches) {
                        hasMatch = true;
                        const div = document.createElement('div');
                        div.className = `hybrid-option ${opt.selected ? 'selected' : ''}`;
                        div.innerHTML = `<span>${opt.text}</span>${opt.selected ? '<i class="fa-solid fa-check text-xs text-white"></i>' : ''}`;
                        div.onclick = () => {
                            select.value = opt.value;
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                            syncPlaceholder();
                            dropdown.classList.remove('active', 'dropup');
                            container.classList.remove('is-open');
                            if (arrow) arrow.classList.remove('rotate-180');
                        };
                        optionsList.appendChild(div);
                    }
                });

                if (!hasMatch) {
                    optionsList.innerHTML = '<div class="py-3 px-4 text-center text-xs text-slate-400">Tidak ada opsi ditemukan</div>';
                }
            };
            
            mainInput.onclick = (e) => {
                e.stopPropagation();
                if (select.disabled) return;
                
                const isActive = dropdown.classList.contains('active');
                
                // Close others
                document.querySelectorAll('.hybrid-select-dropdown').forEach(d => d.classList.remove('active', 'dropup'));
                document.querySelectorAll('.hybrid-select-container').forEach(c => c.classList.remove('is-open'));
                document.querySelectorAll('.hybrid-select-container i.fa-chevron-down, .hybrid-select-container svg').forEach(s => s.classList.remove('rotate-180'));
                
                if (!isActive) {
                    // Check available vertical space to flip upwards (dropup) if near bottom
                    const rect = container.getBoundingClientRect();
                    const spaceBelow = window.innerHeight - rect.bottom;
                    const spaceAbove = rect.top;
                    
                    if (spaceBelow < 250 && spaceAbove > spaceBelow) {
                        dropdown.classList.add('dropup');
                    } else {
                        dropdown.classList.remove('dropup');
                    }

                    dropdown.classList.add('active');
                    container.classList.add('is-open');
                    if (arrow) arrow.classList.add('rotate-180');
                    if (isSearchable && searchInput) {
                        searchInput.value = '';
                        setTimeout(() => searchInput.focus(), 50);
                    }
                    renderOptions();
                }
            };
            
            // Watch select for dynamic changes (e.g. options added/removed dynamically)
            const observer = new MutationObserver(function() {
                syncPlaceholder();
                renderOptions();
            });
            observer.observe(select, { childList: true, attributes: true, subtree: true });

            select.addEventListener('change', () => {
                syncPlaceholder();
                renderOptions();
            });
            
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

<!-- Modal Bukti Lampiran with Smooth Animations & Zoom -->
<div id="imageModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-3 sm:p-5" role="dialog" aria-modal="true">
    <!-- Animated Backdrop -->
    <div id="imageModalBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-200 ease-out" onclick="closeImageModal()"></div>
    
    <!-- Animated Modal Card -->
    <div id="imageModalCard" class="relative bg-white rounded-xl shadow-2xl max-w-3xl w-full overflow-hidden flex flex-col max-h-[92vh] opacity-0 scale-95 translate-y-2 transition-all duration-200 ease-out z-10">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 bg-slate-50 select-none">
            <div class="flex items-center gap-2 min-w-0">
                <i class="fa-solid fa-paperclip text-slate-400 text-sm"></i>
                <h3 id="modalImageTitle" class="text-sm font-semibold text-slate-800 truncate">Bukti Lampiran</h3>
            </div>
            <button type="button" onclick="closeImageModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-200 transition-colors ml-2" title="Tutup (Esc)">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Body Container with Scroll Zoom -->
        <div class="relative p-2 flex-1 overflow-hidden flex items-center justify-center bg-slate-100 min-h-[360px] max-h-[72vh] select-none" id="modalImageContainer">
            <!-- Image wrapper for transform -->
            <div id="modalImageWrapper" class="w-full h-full flex items-center justify-center overflow-hidden">
                <img id="modalImage" src="" alt="Bukti Lampiran" class="max-h-[68vh] max-w-full object-contain rounded border border-slate-200 shadow-sm hidden bg-white pointer-events-auto transition-transform duration-75 origin-center">
            </div>
            
            <!-- PDF Frame View -->
            <iframe id="modalPdfFrame" src="" class="w-full h-[68vh] rounded border border-slate-200 hidden bg-white"></iframe>

            <!-- Empty Notice -->
            <div id="modalEmptyNotice" class="text-center py-10 text-slate-400 hidden">
                <i class="fa-solid fa-file-circle-xmark text-4xl mb-2 text-slate-300"></i>
                <p class="text-xs">Berkas lampiran tidak ditemukan atau tidak dapat dimuat.</p>
            </div>

            <!-- Floating Zoom Toolbar (Only visible for images) -->
            <div id="modalZoomControls" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-1.5 bg-slate-900/80 backdrop-blur-md px-3 py-1.5 rounded-full text-white text-xs shadow-lg hidden">
                <button type="button" onclick="zoomStep(-0.2)" class="p-1 hover:text-cyan-400 transition-colors" title="Zoom Out (Scroll Down)">
                    <i class="fa-solid fa-magnifying-glass-minus"></i>
                </button>
                <span id="modalZoomBadge" onclick="resetZoom()" class="font-mono text-[11px] px-1.5 cursor-pointer hover:text-cyan-400 transition-colors select-none" title="Klik untuk Reset Zoom (100%)">100%</span>
                <button type="button" onclick="zoomStep(0.2)" class="p-1 hover:text-cyan-400 transition-colors" title="Zoom In (Scroll Up)">
                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                </button>
                <span class="w-px h-3 bg-slate-700 mx-0.5"></span>
                <button type="button" onclick="resetZoom()" class="p-1 hover:text-cyan-400 transition-colors" title="Reset">
                    <i class="fa-solid fa-rotate-left text-[10px]"></i>
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-3 border-t border-slate-200 bg-white flex items-center justify-between gap-2">
            <p class="text-[11px] text-slate-400 hidden sm:block">
                <i class="fa-solid fa-mouse text-slate-400 mr-1"></i>
                Scroll mouse untuk zoom in/out &bull; Geser foto saat diperbesar
            </p>
            <div class="flex items-center gap-2 ml-auto">
                <button type="button" onclick="closeImageModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    Tutup
                </button>
                <a id="downloadImageBtn" href="#" download target="_blank" class="px-4 py-2 text-xs font-semibold text-white bg-cyan-600 hover:bg-cyan-700 rounded-lg transition-colors inline-flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-download"></i>
                    Unduh
                </a>
            </div>
        </div>
    </div>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
    // Zoom & Pan Engine for Image Modal
    let zoomScale = 1.0;
    let isDragging = false;
    let startX = 0, startY = 0;
    let translateX = 0, translateY = 0;
    let isModalClosing = false;

    function applyTransform() {
        const img = document.getElementById('modalImage');
        const badge = document.getElementById('modalZoomBadge');
        const container = document.getElementById('modalImageContainer');
        if (!img) return;

        img.style.transform = `translate(${translateX}px, ${translateY}px) scale(${zoomScale})`;
        if (badge) badge.textContent = Math.round(zoomScale * 100) + '%';
        if (container) {
            container.style.cursor = zoomScale > 1.05 ? (isDragging ? 'grabbing' : 'grab') : 'default';
        }
    }

    function resetZoom() {
        zoomScale = 1.0;
        translateX = 0;
        translateY = 0;
        applyTransform();
    }

    function zoomStep(delta) {
        const newScale = Math.min(Math.max(zoomScale + delta, 0.5), 4.0);
        zoomScale = parseFloat(newScale.toFixed(2));
        if (zoomScale <= 1.0) {
            translateX = 0;
            translateY = 0;
        }
        applyTransform();
    }

    // Initialize Wheel Zoom and Pan
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('modalImageContainer');
        if (container) {
            container.addEventListener('wheel', function(e) {
                const img = document.getElementById('modalImage');
                if (!img || img.classList.contains('hidden')) return;
                e.preventDefault();
                const delta = e.deltaY > 0 ? -0.15 : 0.15;
                zoomStep(delta);
            }, { passive: false });

            container.addEventListener('mousedown', function(e) {
                if (zoomScale <= 1.05) return;
                if (e.target.closest('#modalZoomControls')) return;
                isDragging = true;
                startX = e.clientX - translateX;
                startY = e.clientY - translateY;
                applyTransform();
            });

            window.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                translateX = e.clientX - startX;
                translateY = e.clientY - startY;
                applyTransform();
            });

            window.addEventListener('mouseup', function() {
                if (isDragging) {
                    isDragging = false;
                    applyTransform();
                }
            });

            container.addEventListener('dblclick', function(e) {
                if (e.target.closest('#modalZoomControls')) return;
                const img = document.getElementById('modalImage');
                if (!img || img.classList.contains('hidden')) return;
                if (zoomScale > 1.05) {
                    resetZoom();
                } else {
                    zoomScale = 2.0;
                    translateX = 0;
                    translateY = 0;
                    applyTransform();
                }
            });
        }
    });

    function openImageModal(url, title = 'Bukti Lampiran') {
        const modal = document.getElementById('imageModal');
        const backdrop = document.getElementById('imageModalBackdrop');
        const card = document.getElementById('imageModalCard');
        if (!modal || !url) return;

        const img = document.getElementById('modalImage');
        const pdf = document.getElementById('modalPdfFrame');
        const emptyNotice = document.getElementById('modalEmptyNotice');
        const titleEl = document.getElementById('modalImageTitle');
        const downloadBtn = document.getElementById('downloadImageBtn');
        const zoomControls = document.getElementById('modalZoomControls');

        if (titleEl) titleEl.textContent = title || 'Bukti Lampiran';
        if (downloadBtn) {
            downloadBtn.href = url;
            const filename = url.split('/').pop().split('?')[0];
            downloadBtn.setAttribute('download', filename);
        }

        resetZoom();

        const isPdf = url.toLowerCase().split('?')[0].endsWith('.pdf');

        if (img) img.classList.add('hidden');
        if (pdf) pdf.classList.add('hidden');
        if (emptyNotice) emptyNotice.classList.add('hidden');
        if (zoomControls) zoomControls.classList.add('hidden');

        if (isPdf) {
            if (pdf) {
                pdf.src = url;
                pdf.classList.remove('hidden');
            }
        } else {
            if (img) {
                img.onload = function() {
                    img.classList.remove('hidden');
                    if (zoomControls) zoomControls.classList.remove('hidden');
                };
                img.onerror = function() {
                    img.classList.add('hidden');
                    if (emptyNotice) emptyNotice.classList.remove('hidden');
                    if (zoomControls) zoomControls.classList.add('hidden');
                };
                img.src = url;
            }
        }

        // Show modal with smooth enter animation
        isModalClosing = false;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        requestAnimationFrame(() => {
            if (backdrop) backdrop.classList.remove('opacity-0');
            if (card) {
                card.classList.remove('opacity-0', 'scale-95', 'translate-y-2');
                card.classList.add('opacity-100', 'scale-100', 'translate-y-0');
            }
        });
    }

    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        const backdrop = document.getElementById('imageModalBackdrop');
        const card = document.getElementById('imageModalCard');
        if (!modal || isModalClosing) return;

        isModalClosing = true;

        // Smooth exit animation
        if (backdrop) backdrop.classList.add('opacity-0');
        if (card) {
            card.classList.remove('opacity-100', 'scale-100', 'translate-y-0');
            card.classList.add('opacity-0', 'scale-95', 'translate-y-2');
        }

        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            const img = document.getElementById('modalImage');
            const pdf = document.getElementById('modalPdfFrame');
            if (img) img.src = '';
            if (pdf) pdf.src = '';
            resetZoom();
            isModalClosing = false;
        }, 200);
    }

    // Close on backdrop click
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('imageModal');
        if (modal && !modal.classList.contains('hidden') && e.target === modal) {
            closeImageModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageModal();
        }
    });

    // Event delegation for preview triggers
    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('[data-preview-url]');
        if (trigger) {
            e.preventDefault();
            const url = trigger.getAttribute('data-preview-url');
            const title = trigger.getAttribute('data-preview-title') || 'Bukti Lampiran';
            openImageModal(url, title);
        }
    });

    // Global CSRF Protection: Auto-inject token into all POST forms and AJAX requests
    (function() {
        const csrfToken = '<?php echo csrf_token(); ?>';
        
        function injectCsrfInputs() {
            document.querySelectorAll('form[method="post" i], form[method="POST"]').forEach(function(form) {
                if (!form.querySelector('input[name="csrf_token"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'csrf_token';
                    input.value = csrfToken;
                    form.appendChild(input);
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', injectCsrfInputs);
        } else {
            injectCsrfInputs();
        }

        // Intercept dynamically added forms
        const observer = new MutationObserver(function() {
            injectCsrfInputs();
        });
        if (document.body) {
            observer.observe(document.body, { childList: true, subtree: true });
        }

        // Intercept window.fetch to attach X-CSRF-TOKEN
        if (window.fetch) {
            const originalFetch = window.fetch;
            window.fetch = function(url, options = {}) {
                if (options.method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method.toUpperCase())) {
                    options.headers = options.headers || {};
                    if (typeof Headers !== 'undefined' && options.headers instanceof Headers) {
                        if (!options.headers.has('X-CSRF-TOKEN')) {
                            options.headers.set('X-CSRF-TOKEN', csrfToken);
                        }
                    } else if (Array.isArray(options.headers)) {
                        let hasHeader = options.headers.some(h => h[0].toLowerCase() === 'x-csrf-token');
                        if (!hasHeader) {
                            options.headers.push(['X-CSRF-TOKEN', csrfToken]);
                        }
                    } else if (typeof options.headers === 'object') {
                        options.headers['X-CSRF-TOKEN'] = options.headers['X-CSRF-TOKEN'] || csrfToken;
                    }
                }
                return originalFetch(url, options);
            };
        }

        // Intercept XMLHttpRequest to attach X-CSRF-TOKEN
        if (window.XMLHttpRequest) {
            const origOpen = XMLHttpRequest.prototype.open;
            const origSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.open = function(method, url) {
                this._method = method;
                return origOpen.apply(this, arguments);
            };
            XMLHttpRequest.prototype.send = function() {
                if (this._method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(this._method.toUpperCase())) {
                    try {
                        this.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                    } catch (e) {}
                }
                return origSend.apply(this, arguments);
            };
        }
    })();
</script>

</body>

</html>