<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Manajemen Lokasi Inventaris";
require_once __DIR__ . '/../layouts/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    <div class="p-6 border-b border-slate-200 flex justify-between items-center bg-slate-50/50">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Struktur Lokasi</h2>
            <p class="text-sm text-slate-500 mt-1">Kelola hierarki lokasi penempatan barang.</p>
        </div>
        <div class="flex gap-2">
            <button onclick="openModal(null)" class="bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Tambah Root Lokasi
            </button>
            <button onclick="printAllLabels()" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Cetak Semua Barcode
            </button>
        </div>
    </div>

    <div class="p-6">
        <ul id="location-tree" class="space-y-2">
            <li>Loading...</li>
        </ul>
    </div>
</div>

<!-- Modal Dialog form -->
<div id="location-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/50 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform transition-all scale-95 opacity-0" id="modal-content">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-800" id="modal-title">Tambah Lokasi</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form id="location-form" onsubmit="saveLocation(event)" class="p-6">
            <input type="hidden" id="loc_id" name="id">
            <input type="hidden" id="loc_parent_id" name="parent_id">
            
            <input type="hidden" id="loc_name" name="name">

            <div class="mb-4" id="code-container">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Kode Lokasi</label>
                <input type="text" id="loc_code" name="location_code" readonly class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-slate-500 outline-none cursor-not-allowed" placeholder="Otomatis">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Label Lokasi</label>
                <input type="text" id="loc_label" name="location_label" required class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition" placeholder="Contoh: Gedung A Lantai 1">
            </div>

            <p id="parent-info" class="text-sm text-amber-600 bg-amber-50 p-3 rounded-lg border border-amber-200 mb-4 hidden"></p>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-medium transition">Batal</button>
                <button type="submit" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Pindah Drag & Drop -->
<div id="confirm-modal" class="fixed inset-0 z-[60] flex items-center justify-center hidden bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden transform transition-all scale-95 opacity-0 p-6 text-center" id="confirm-content">
        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4" id="confirm-icon-bg">
            <svg class="w-8 h-8 text-amber-500" id="confirm-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </div>
        <h3 class="text-xl font-bold text-slate-800 mb-2">Konfirmasi Pindah</h3>
        <p class="text-sm text-slate-500 mb-6 leading-relaxed" id="confirm-message">Apakah Anda yakin?</p>
        <div class="flex justify-center gap-3">
            <button id="btn-cancel-move" class="px-5 py-2.5 text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl text-sm font-semibold transition w-full">Batal</button>
            <button id="btn-confirm-move" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-semibold transition shadow-sm w-full">Pindahkan</button>
        </div>
    </div>
</div>

<script>
    // Fetch and render the tree
    let allLocationsData = [];

    async function loadTree() {
        try {
            const res = await fetch('<?php echo BASE_URL; ?>/api/inventory/locations/get.php');
            const data = await res.json();
            
            const treeContainer = document.getElementById('location-tree');
            treeContainer.innerHTML = '';
            
            if (data.success && data.data) {
                allLocationsData = data.data; // Store globally for bulk print
                data.data.forEach(node => {
                    treeContainer.appendChild(createNodeElement(node));
                });
            } else {
                treeContainer.innerHTML = '<li class="text-red-500">Failed to load locations.</li>';
            }
        } catch (err) {
            console.error(err);
        }
    }

    function createNodeElement(node) {
        const li = document.createElement('li');
        li.className = "relative my-1";
        
        // Node Header
        const header = document.createElement('div');
        header.className = "flex items-center justify-between p-3 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition group shadow-sm cursor-move";
        header.draggable = true;

        header.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', JSON.stringify({ id: node.id, name: node.name }));
            e.dataTransfer.effectAllowed = 'move';
            setTimeout(() => header.classList.add('opacity-40'), 0);
        });

        header.addEventListener('dragend', (e) => {
            header.classList.remove('opacity-40');
            document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('bg-cyan-50', 'ring-2', 'ring-cyan-400', 'drag-over'));
        });

        header.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            header.classList.add('bg-cyan-50', 'ring-2', 'ring-cyan-400', 'drag-over');
        });

        header.addEventListener('dragleave', (e) => {
            header.classList.remove('bg-cyan-50', 'ring-2', 'ring-cyan-400', 'drag-over');
        });

        header.addEventListener('drop', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            header.classList.remove('bg-cyan-50', 'ring-2', 'ring-cyan-400', 'drag-over');
            
            try {
                const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                if (data.id && data.id != node.id) {
                    openConfirmModal(
                        `Pindahkan <strong>"${data.name}"</strong> ke dalam <strong>"${node.name}"</strong>?`,
                        () => moveNode(data.id, data.name, node.id)
                    );
                }
            } catch(err) {}
        });
        
        const titleArea = document.createElement('div');
        titleArea.className = "flex items-center gap-3";
        
        const iconSvg = node.children && node.children.length > 0 
            ? `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1v1H9V7zm5 0h1v1h-1V7zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1zm-5 4h1v1H9v-1zm5 0h1v1h-1v-1z" /></svg>`
            : `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`;
            
        const iconBg = node.children && node.children.length > 0 ? 'bg-amber-50 text-amber-600' : 'bg-cyan-50 text-cyan-600';

        titleArea.innerHTML = `
            <div class="w-8 h-8 rounded-lg ${iconBg} flex items-center justify-center">
                ${iconSvg}
            </div>
            <div class="flex flex-col">
                <span class="font-semibold text-slate-700">${node.location_label || node.name}</span>
                <span class="text-[10px] font-mono text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded w-fit">${node.location_code || 'NO-CODE'}</span>
            </div>
        `;
        
        const actionArea = document.createElement('div');
        actionArea.className = "flex items-center gap-2 transition-opacity";
        
        // Buttons
        const btnAdd = document.createElement('button');
        btnAdd.className = "p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-md transition";
        btnAdd.innerHTML = `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>`;
        btnAdd.title = "Tambah Child Node";
        btnAdd.onclick = () => openModal(null, node.id, node.name);
        
        const btnEdit = document.createElement('button');
        btnEdit.className = "p-1.5 text-amber-600 hover:bg-amber-50 rounded-md transition";
        btnEdit.innerHTML = `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>`;
        btnEdit.title = "Edit Detail";
        btnEdit.onclick = () => openModal(node.id, node.parent_id, null, node.name, node.location_code, node.location_label);
        
        const btnDel = document.createElement('button');
        btnDel.className = "p-1.5 text-rose-600 hover:bg-rose-50 rounded-md transition";
        btnDel.innerHTML = `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>`;
        btnDel.title = "Hapus";
        btnDel.onclick = () => deleteNode(node.id);
        
        const btnPrint = document.createElement('button');
        btnPrint.className = "p-1.5 text-cyan-600 hover:bg-cyan-50 rounded-md transition";
        btnPrint.innerHTML = `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>`;
        btnPrint.title = "Cetak Barcode";
        btnPrint.onclick = () => openPrintModal(node);
        
        actionArea.appendChild(btnAdd);
        actionArea.appendChild(btnPrint);
        actionArea.appendChild(btnEdit);
        actionArea.appendChild(btnDel);
        
        header.appendChild(titleArea);
        header.appendChild(actionArea);
        
        li.appendChild(header);

        // Children
        if (node.children && node.children.length > 0) {
            const childUl = document.createElement('ul');
            childUl.className = "ml-8 mt-2 space-y-2 relative border-l-2 border-slate-100 pl-4";
            node.children.forEach(child => {
                childUl.appendChild(createNodeElement(child));
            });
            li.appendChild(childUl);
        }

        return li;
    }

    async function moveNode(id, name, newParentId) {
        try {
            const res = await fetch('<?php echo BASE_URL; ?>/api/inventory/locations/update.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, name: name, parent_id: newParentId })
            });
            const data = await res.json();
            
            if (data.success) {
                loadTree();
                showToast("Lokasi berhasil dipindahkan!", "success");
            } else {
                showToast(data.message || "Gagal memindahkan lokasi.", "error"); // Jika kena the circular dependency error
            }
        } catch (err) {
            console.error(err);
            showToast("Terjadi kesalahan sistem saat memindah.", "error");
        }
    }

    // Modal konfirmasi custom
    function openConfirmModal(message, actionCallback) {
        document.getElementById('confirm-message').innerHTML = message;
        
        const modal = document.getElementById('confirm-modal');
        const content = document.getElementById('confirm-content');
        const btnConfirm = document.getElementById('btn-confirm-move');
        const btnCancel = document.getElementById('btn-cancel-move');

        modal.classList.remove('hidden');
        void modal.offsetWidth; // force reflow
        
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
        
        let newBtnConfirm = btnConfirm.cloneNode(true);
        btnConfirm.parentNode.replaceChild(newBtnConfirm, btnConfirm);
        let newBtnCancel = btnCancel.cloneNode(true);
        btnCancel.parentNode.replaceChild(newBtnCancel, btnCancel);

        newBtnConfirm.addEventListener('click', async () => {
            closeConfirmModal();
            if (typeof actionCallback === 'function') await actionCallback();
        });

        newBtnCancel.addEventListener('click', closeConfirmModal);
    }

    function closeConfirmModal() {
        const modal = document.getElementById('confirm-modal');
        const content = document.getElementById('confirm-content');
        
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        modal.classList.remove('opacity-100');
        
        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    // Modal logic (location form form)
    function openModal(id = null, parentId = null, parentName = null, currentName = '', currentCode = '', currentLabel = '') {
        document.getElementById('loc_id').value = id || '';
        document.getElementById('loc_parent_id').value = parentId || '';
        document.getElementById('loc_name').value = currentName;
        document.getElementById('loc_code').value = currentCode || 'Otomatis';
        document.getElementById('loc_label').value = currentLabel || currentName;

        const codeContainer = document.getElementById('code-container');
        if (!id) {
            codeContainer.classList.add('hidden');
        } else {
            codeContainer.classList.remove('hidden');
        }
        
        const info = document.getElementById('parent-info');
        if (parentName) {
            info.innerHTML = `Menambahkan child di bawah: <strong>${parentName}</strong>`;
            info.classList.remove('hidden');
        } else {
            info.classList.add('hidden');
            info.innerHTML = '';
        }

        document.getElementById('modal-title').innerText = id ? "Edit Lokasi" : "Tambah Lokasi";
        
        const modal = document.getElementById('location-modal');
        const content = document.getElementById('modal-content');
        modal.classList.remove('hidden');
        
        // trigger reflow
        void modal.offsetWidth;
        
        modal.classList.add('opacity-100');
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }

    function closeModal() {
        const modal = document.getElementById('location-modal');
        const content = document.getElementById('modal-content');
        
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        modal.classList.remove('opacity-100');
        
        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    async function saveLocation(e) {
        e.preventDefault();
        
        const id = document.getElementById('loc_id').value;
        const parent_id = document.getElementById('loc_parent_id').value;
        const label = document.getElementById('loc_label').value;
        const name = label; // Use label as name
        
        const payload = { name: name, parent_id: parent_id, label: label };
        if (id) payload.id = id;

        try {
            const endpoint = id 
                ? '<?php echo BASE_URL; ?>/api/inventory/locations/update.php' 
                : '<?php echo BASE_URL; ?>/api/inventory/locations/create.php';
            const method = id ? 'PUT' : 'POST';

            const res = await fetch(endpoint, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                closeModal();
                loadTree(); // Refresh
                showToast(id ? "Lokasi berhasil diupdate!" : "Lokasi baru berhasil ditambahkan!", "success");
            } else {
                showToast(data.message || "Gagal menyimpan lokasi.", "error");
            }
        } catch (err) {
            console.error(err);
            showToast("Terjadi kesalahan.", "error");
        }
    }

    async function deleteNode(id) {
        openConfirmModal(
            `<span class="text-rose-600 font-bold">Yakin ingin menghapus lokasi ini beserta seluruh turunannya?</span><br>Tindakan ini tidak dapat dikembalikan.`,
            async () => {
                try {
                    const res = await fetch('<?php echo BASE_URL; ?>/api/inventory/locations/delete.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });
                    const data = await res.json();
                    
                    if (data.success) {
                        loadTree();
                        showToast("Lokasi berhasil dihapus permanen!", "success");
                    } else {
                        showToast(data.message || "Gagal menghapus lokasi.", "error");
                    }
                } catch (err) {
                    console.error(err);
                    showToast("Terjadi kesalahan saat menghapus.", "error");
                }
            }
        );
    }

    window.onload = () => {
        loadTree();
        
        // Allow dropping directly onto the container background to make a node become a Root Location
        const treeContainer = document.getElementById('location-tree');
        treeContainer.addEventListener('dragover', (e) => {
            e.preventDefault();
        });
        treeContainer.addEventListener('drop', async (e) => {
            e.preventDefault();
            try {
                // If the drop event got here, it means it wasn't intercepted by stopPropagation() on a Child Node.
                const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                if (data.id) {
                    openConfirmModal(
                        `Pindahkan <strong>"${data.name}"</strong> menjadi <strong>Root Lokasi</strong> (di luar asrama/gedung manapun)?`,
                        () => moveNode(data.id, data.name, null)
                    );
                }
            } catch(err) {}
        });
    };

    // Print Barcode Label logic
    function openPrintModal(node) {
        const modal = document.getElementById('print-modal');
        const content = document.getElementById('print-modal-content');
        
        document.getElementById('print-label-name').innerText = node.location_label || node.name;
        document.getElementById('print-label-code-text').innerText = node.location_code || 'N/A';
        
        // Generate Barcode
        JsBarcode("#barcode-canvas", node.location_code || '0000', {
            format: "CODE128",
            width: 2,
            height: 60,
            displayValue: false,
            margin: 0
        });

        modal.classList.remove('hidden');
        void modal.offsetWidth;
        modal.classList.add('opacity-100');
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }

    function closePrintModal() {
        const modal = document.getElementById('print-modal');
        const content = document.getElementById('print-modal-content');
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        modal.classList.remove('opacity-100');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    function printLabel() {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Cetak Label Lokasi</title>');
        printWindow.document.write('<script src="https://cdn.tailwindcss.com"><\/script>');
        printWindow.document.write('<style>@media print { body { margin: 0; } .label-card { page-break-after: always; display: flex !important; } }</style>');
        printWindow.document.write('</head><body class="flex flex-col items-center justify-center min-h-screen bg-white">');
        printWindow.document.write('<div class="label-card" style="width: 80mm; height: 40mm; border: 2px solid black; padding: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-family: sans-serif; text-align: center;">');
        printWindow.document.write(document.getElementById('label-to-print').innerHTML);
        printWindow.document.write('</div>');
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    }

    function printAllLabels() {
        if (!allLocationsData || allLocationsData.length === 0) {
            showToast("Data lokasi kosong.", "error");
            return;
        }

        const flattenedLocations = [];
        function flatten(nodes) {
            nodes.forEach(n => {
                flattenedLocations.push(n);
                if (n.children && n.children.length > 0) flatten(n.children);
            });
        }
        flatten(allLocationsData);

        const printWindow = window.open('', '', 'height=800,width=1000');
        printWindow.document.write('<html><head><title>Cetak Semua Label</title>');
        printWindow.document.write('<script src="https://cdn.tailwindcss.com"><\/script>');
        printWindow.document.write('<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>');
        printWindow.document.write('<style>');
        printWindow.document.write('@media print { body { margin: 0; } .label-page { page-break-after: always; } }');
        printWindow.document.write('.label-container { width: 80mm; height: 40mm; border: 2px solid black; padding: 8px; margin: 10px auto; display: flex; flex-direction: column; align-items: center; justify-content: center; font-family: sans-serif; text-align: center; }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body class="bg-gray-100 py-10 print:bg-white print:py-0">');
        
        flattenedLocations.forEach((loc, index) => {
            const canvasId = `barcode-${index}`;
            printWindow.document.write(`
                <div class="label-container label-page">
                    <div style="margin-bottom: 4px;">
                        <img src="<?php echo BASE_URL; ?>/public/images/logo.png" style="height: 30px; width: auto;">
                    </div>
                    <h4 style="font-weight: bold; font-size: 14px; margin-bottom: 0px; line-height: 1;">${loc.location_label || loc.name}</h4>
                    <p style="font-size: 10px; font-family: monospace; color: #4b5563; margin-bottom: 4px; letter-spacing: 0.1em; text-transform: uppercase;">${loc.location_code || 'N/A'}</p>
                    <canvas id="${canvasId}"></canvas>
                </div>
            `);
        });

        printWindow.document.write('<script>');
        flattenedLocations.forEach((loc, index) => {
            const canvasId = `barcode-${index}`;
            printWindow.document.write(`
                JsBarcode("#${canvasId}", "${loc.location_code || '0000'}", {
                    format: "CODE128",
                    width: 1.5,
                    height: 50,
                    displayValue: false,
                    margin: 0
                });
            `);
        });
        printWindow.document.write('setTimeout(() => { window.print(); window.close(); }, 800);');
        printWindow.document.write('<\/script>');
        printWindow.document.write('</body></html>');
        printWindow.document.close();
    }
</script>

<!-- Modal Print Barcode -->
<div id="print-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/50 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden transform transition-all scale-95 opacity-0" id="print-modal-content">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-800">Cetak Label Lokasi</h3>
            <button onclick="closePrintModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <div class="p-8">
            <div id="label-to-print" class="bg-white border-2 border-slate-800 rounded-lg p-6 flex flex-col items-center justify-center text-center">
                <div class="mb-2">
                    <img src="<?php echo BASE_URL; ?>/public/images/logo.png" class="h-10 w-auto">
                </div>
                <h4 id="print-label-name" class="font-bold text-lg text-slate-900 mb-0 leading-tight">-</h4>
                <p id="print-label-code-text" class="text-[10px] font-mono text-slate-600 mb-2 tracking-widest uppercase">-</p>
                <div class="w-full flex justify-center bg-white p-1">
                    <canvas id="barcode-canvas"></canvas>
                </div>
            </div>
            
            <div class="flex flex-col gap-3 mt-8">
                <button onclick="printLabel()" class="w-full py-3 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl font-bold transition shadow-lg flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Cetak Sekarang
                </button>
                <button onclick="closePrintModal()" class="w-full py-3 text-slate-500 hover:text-slate-700 font-semibold transition">Batal</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
