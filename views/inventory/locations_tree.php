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
                <i class="fa-solid fa-plus w-5 h-5"></i>
                Tambah Root Lokasi
            </button>
            <button onclick="printAllLabels()" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-print w-5 h-5"></i>
                Cetak Semua Barcode
            </button>
        </div>
    </div>

    <div class="p-6">
        <ul id="location-tree" class="space-y-2">
            <div class="flex items-center justify-center py-12" id="tree-loading">
                <div class="flex flex-col items-center gap-3">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-cyan-600"></div>
                    <span class="text-slate-400 text-sm font-medium">Memuat struktur lokasi...</span>
                </div>
            </div>
        </ul>
    </div>
</div>

<!-- Modal Dialog form -->
<div id="location-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden bg-black/50 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform transition-all scale-95 opacity-0" id="modal-content">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-800" id="modal-title">Tambah Lokasi</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none">
                <i class="fa-solid fa-xmark w-6 h-6"></i>
            </button>
        </div>
        
        <form id="location-form" onsubmit="saveLocation(event)" class="p-6">
            <input type="hidden" id="loc_id" name="id">
            <input type="hidden" id="loc_parent_id" name="parent_id">
            
            <input type="hidden" id="loc_name" name="name">

            <div class="mb-4" id="code-container">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Kode Lokasi</label>
                <div class="relative">
                    <input type="text" id="loc_code" name="location_code" readonly class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-slate-500 outline-none cursor-not-allowed font-mono uppercase" placeholder="Otomatis">
                    <p class="text-[10px] text-slate-400 mt-1">Kode unik otomatis untuk identifikasi barcode.</p>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Label Lokasi</label>
                <input type="text" id="loc_label" name="location_label" required class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition">
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
            <i id="confirm-icon" class="fa-solid fa-location-dot w-8 h-8 text-amber-500"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-800 mb-2" id="confirm-modal-title-text">Konfirmasi Pindah</h3>
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
        const treeContainer = document.getElementById('location-tree');
        const apiPath = '<?php url("api/inventory/locations/get.php"); ?>';
        console.log('Loading tree from:', apiPath);
        
        try {
            const res = await fetch(apiPath);
            
            if (!res.ok) throw new Error(`Fetch failed: ${res.status}`);
            
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
            const treeContainer = document.getElementById('location-tree');
            if (treeContainer) {
                treeContainer.innerHTML = `<li class="text-rose-500 font-bold p-4 bg-rose-50 rounded-xl border border-rose-100 flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation w-5 h-5"></i>
                    Gagal memuat data: ${err.message}
                </li>`;
            }
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
                    openLocalConfirmModal(
                        `Pindahkan <strong>"${data.name}"</strong> ke dalam <strong>"${node.name}"</strong>?`,
                        () => moveNode(data.id, data.name, node.id),
                        'Konfirmasi Pindah',
                        'Pindahkan',
                        'amber'
                    );
                }
            } catch(err) {}
        });
        
        const titleArea = document.createElement('div');
        titleArea.className = "flex items-center gap-3";
        
        const iconSvg = node.children && node.children.length > 0 
            ? `<i class="fa-solid fa-building w-5 h-5"></i>`
            : `<i class="fa-solid fa-location-dot w-5 h-5"></i>`;
            
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
        btnAdd.innerHTML = `<i class="fa-solid fa-plus w-5 h-5"></i>`;
        btnAdd.title = "Tambah Child Node";
        btnAdd.onclick = () => openModal(null, node.id, node.name);
        
        const btnEdit = document.createElement('button');
        btnEdit.className = "p-1.5 text-amber-600 hover:bg-amber-50 rounded-md transition";
        btnEdit.innerHTML = `<i class="fa-solid fa-pen-to-square w-5 h-5"></i>`;
        btnEdit.title = "Edit Detail";
        btnEdit.onclick = () => openModal(node.id, node.parent_id, null, node.name, node.location_code, node.location_label);
        
        const btnDel = document.createElement('button');
        btnDel.className = "p-1.5 text-rose-600 hover:bg-rose-50 rounded-md transition";
        btnDel.innerHTML = `<i class="fa-solid fa-trash w-5 h-5"></i>`;
        btnDel.title = "Hapus";
        btnDel.onclick = () => deleteNode(node.id);
        
        const btnPrint = document.createElement('button');
        btnPrint.className = "p-1.5 text-cyan-600 hover:bg-cyan-50 rounded-md transition";
        btnPrint.innerHTML = `<i class="fa-solid fa-print w-5 h-5"></i>`;
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
        if (!id) {
            showToast("ID lokasi tidak valid.", "error");
            return;
        }
        try {
            const apiPath = '<?php url("api/inventory/locations/update.php"); ?>';
            const res = await fetch(apiPath, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, name: name, parent_id: newParentId })
            });
            
            if (!res.ok) {
                const text = await res.text();
                throw new Error(text || `Server error: ${res.status}`);
            }

            const data = await res.json();
            
            if (data.success) {
                loadTree();
                showToast("Lokasi berhasil dipindahkan!", "success");
            } else {
                showToast(data.message || "Gagal memindahkan lokasi.", "error");
            }
        } catch (err) {
            console.error(err);
            showToast("Terjadi kesalahan sistem: " + err.message, "error");
        }
    }

    // Modal konfirmasi custom
    function openLocalConfirmModal(message, actionCallback, title = 'Konfirmasi', confirmText = 'Ya, Lanjutkan', color = 'amber') {
        document.getElementById('confirm-message').innerHTML = message;
        document.getElementById('confirm-modal-title-text').innerText = title;
        
        const btnConfirm = document.getElementById('btn-confirm-move');
        btnConfirm.innerText = confirmText;
        
        // Dynamic Color classes
        const colorMap = {
            amber: 'bg-amber-600 hover:bg-amber-700',
            rose: 'bg-rose-600 hover:bg-rose-700',
            emerald: 'bg-emerald-600 hover:bg-emerald-700',
            blue: 'bg-blue-600 hover:bg-blue-700'
        };
        
        btnConfirm.className = `px-5 py-2.5 ${colorMap[color] || colorMap.amber} text-white rounded-xl text-sm font-semibold transition shadow-sm w-full`;

        const modal = document.getElementById('confirm-modal');
        const content = document.getElementById('confirm-content');
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
            closeLocalConfirmModal();
            if (typeof actionCallback === 'function') await actionCallback();
        });

        newBtnCancel.addEventListener('click', closeLocalConfirmModal);
    }

    function closeLocalConfirmModal() {
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
        
        // Auto-generate suggestion for NEW locations
        if (!id) {
            const parentCode = document.querySelector(`li[data-id="${parentId}"]`)?.dataset.code || '';
            const suggestCode = (label) => {
                if (!label) return '';
                const initials = label.split(' ').map(w => w[0]).join('').toUpperCase();
                return parentCode ? `${parentCode}.${initials}` : initials;
            };

            const labelInput = document.getElementById('loc_label');
            const codeInput = document.getElementById('loc_code');
            
            // Real-time suggestion while typing label
            labelInput.oninput = () => {
                if (!id) codeInput.value = suggestCode(labelInput.value);
            };
        } else {
            document.getElementById('loc_label').oninput = null;
        }

        const modal = document.getElementById('location-modal');
        const content = document.getElementById('modal-content');
        modal.classList.remove('hidden');
        
        // trigger reflow
        void modal.offsetWidth;
        
        modal.classList.add('opacity-100');
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');

        // Focus the name field
        setTimeout(() => {
            document.getElementById('loc_label').focus();
        }, 150);
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
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        const id = document.getElementById('loc_id').value;
        const parent_id = document.getElementById('loc_parent_id').value;
        const label = document.getElementById('loc_label').value;
        const name = label; // Use label as name
        
        if (!name) {
            showToast("Nama/Label lokasi harus diisi.", "error");
            return;
        }

        const location_code = document.getElementById('loc_code').value;
        const payload = { name: name, parent_id: parent_id, label: label, location_code: location_code };
        if (id) payload.id = id;

        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block"></i> Menyimpan...`;

            const endpoint = id 
                ? '<?php url("api/inventory/locations/update.php"); ?>' 
                : '<?php url("api/inventory/locations/create.php"); ?>';
            const method = id ? 'PUT' : 'POST';

            const res = await fetch(endpoint, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!res.ok) {
                const errorText = await res.text();
                throw new Error(errorText || `HTTP error! status: ${res.status}`);
            }

            const data = await res.json();
            
            if (data.success) {
                closeModal();
                loadTree(); // Refresh
                showToast(id ? "Lokasi berhasil diupdate!" : "Lokasi baru berhasil ditambahkan!", "success");
            } else {
                showToast(data.message || "Gagal menyimpan lokasi.", "error");
            }
        } catch (err) {
            console.error("Save Location Error:", err);
            showToast("Gagal menyimpan: " + (err.message || "Terjadi kesalahan."), "error");
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }

    async function deleteNode(id) {
        if (!id) {
            showToast("ID lokasi tidak valid.", "error");
            return;
        }
        openLocalConfirmModal(
            `<span class="text-rose-600 font-bold">Yakin ingin menghapus lokasi ini beserta seluruh turunannya?</span><br>Tindakan ini tidak dapat dikembalikan.`,
            async () => {
                try {
                    const apiPath = '<?php url("api/inventory/locations/delete.php"); ?>';
                    const res = await fetch(apiPath, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });

                    if (!res.ok) {
                        const text = await res.text();
                        throw new Error(text || `Server error: ${res.status}`);
                    }

                    const data = await res.json();
                    
                    if (data.success) {
                        loadTree();
                        showToast("Lokasi berhasil dihapus permanen!", "success");
                    } else {
                        showToast(data.message || "Gagal menghapus lokasi.", "error");
                    }
                } catch (err) {
                    console.error(err);
                    showToast("Terjadi kesalahan saat menghapus: " + err.message, "error");
                }
            },
            'Hapus Lokasi',
            'Hapus Sekarang',
            'rose'
        );
    }

    document.addEventListener('DOMContentLoaded', () => {
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
                    openLocalConfirmModal(
                        `Pindahkan <strong>"${data.name}"</strong> menjadi <strong>Root Lokasi</strong> (di luar asrama/gedung manapun)?`,
                        () => moveNode(data.id, data.name, null),
                        'Pindah ke Root',
                        'Pindahkan',
                        'amber'
                    );
                }
            } catch(err) {}
        });
    });

    // Print Barcode Label logic
    function openPrintModal(node) {
        const modal = document.getElementById('print-modal');
        const content = document.getElementById('print-modal-content');
        
        document.getElementById('print-label-name').innerText = node.location_label || node.name;
        document.getElementById('print-label-code-text').innerText = node.location_code || 'N/A';
        
        // Generate Barcode as Image
        const tempCanvas = document.createElement('canvas');
        JsBarcode(tempCanvas, node.location_code || '0000', {
            format: "CODE128",
            width: 2,
            height: 60,
            displayValue: false,
            margin: 0
        });
        
        const barcodeImg = document.getElementById('barcode-img-modal');
        barcodeImg.src = tempCanvas.toDataURL();

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
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { margin: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; font-family: sans-serif; }
            .label-card { 
                width: 80mm; 
                height: 40mm; 
                border: 2px solid black; 
                padding: 10px; 
                display: flex; 
                flex-direction: column; 
                align-items: center; 
                justify-content: center; 
                text-align: center; 
                box-sizing: border-box;
            }
            .label-card h4 { margin: 2px 0; font-size: 18px; font-weight: bold; }
            .label-card p { margin: 0 0 5px 0; font-size: 12px; font-family: monospace; color: #333; text-transform: uppercase; }
            .label-card .logo-img { height: 35px; width: auto; margin-bottom: 8px; }
            .barcode-img { max-width: 100%; height: auto; }
            @media print { body { margin: 0; } }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<div class="label-card">');
        
        // Manually build content to ensure images are transferred correctly
        const name = document.getElementById('print-label-name').innerText;
        const code = document.getElementById('print-label-code-text').innerText;
        const barcodeSrc = document.getElementById('barcode-img-modal').src;
        const logoSrc = '<?php echo BASE_URL; ?>/public/images/logo.png';

        printWindow.document.write(`
            <img src="${logoSrc}" class="logo-img">
            <h4>${name}</h4>
            <p>${code}</p>
            <img src="${barcodeSrc}" class="barcode-img">
        `);

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

        // Pre-generate all barcodes as DataURL in the main window
        const tempCanvas = document.createElement('canvas');
        const items = [];
        flattenedLocations.forEach(loc => {
            JsBarcode(tempCanvas, loc.location_code || '0000', {
                format: "CODE128",
                width: 2,
                height: 60,
                displayValue: false,
                margin: 0
            });
            items.push({
                name: loc.location_label || loc.name,
                code: loc.location_code || 'N/A',
                barcode: tempCanvas.toDataURL()
            });
        });

        const printWindow = window.open('', '', 'height=800,width=1000');
        printWindow.document.write('<html><head><title>Cetak Semua Label</title>');
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { margin: 0; font-family: sans-serif; background: #f3f4f6; }
            @media print {
                body { background: white; margin: 0; }
                .page-container { 
                    display: grid; 
                    grid-template-columns: 1fr 1fr; 
                    grid-template-rows: 1fr 1fr; 
                    height: 100vh; 
                    width: 100vw;
                    page-break-after: always; 
                    padding: 15mm;
                    box-sizing: border-box;
                    gap: 10mm;
                }
                .label-item {
                    border: 2px solid #000;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    padding: 5mm;
                    text-align: center;
                    height: calc(50vh - 25mm);
                    box-sizing: border-box;
                }
            }
            .page-container { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                padding: 20px;
                gap: 20px;
                max-width: 1000px;
                margin: 0 auto;
            }
            .label-item {
                background: white;
                border: 1px solid #ddd;
                padding: 20px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                min-height: 150px;
            }
            .logo-img { height: 35px; width: auto; margin-bottom: 8px; }
            .label-item h4 { margin: 2px 0; font-size: 16px; font-weight: bold; }
            .label-item p { margin: 0 0 5px 0; font-size: 11px; font-family: monospace; color: #333; text-transform: uppercase; }
            .barcode-img { max-width: 100%; height: auto; }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        
        const logoSrc = '<?php echo BASE_URL; ?>/public/images/logo.png';

        // Group into pages of 4
        for (let i = 0; i < items.length; i += 4) {
            printWindow.document.write('<div class="page-container">');
            for (let j = i; j < i + 4 && j < items.length; j++) {
                const item = items[j];
                printWindow.document.write(`
                    <div class="label-item">
                        <img src="${logoSrc}" class="logo-img">
                        <h4>${item.name}</h4>
                        <p>${item.code}</p>
                        <img src="${item.barcode}" class="barcode-img">
                    </div>
                `);
            }
            printWindow.document.write('</div>');
        }

        printWindow.document.write('<script>window.onload = function() { setTimeout(() => { window.print(); window.close(); }, 500); };<\/script>');
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
                <i class="fa-solid fa-xmark w-6 h-6"></i>
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
                    <img id="barcode-img-modal" src="" class="max-w-full h-auto">
                </div>
            </div>
            
            <div class="flex flex-col gap-3 mt-8">
                <button onclick="printLabel()" class="w-full py-3 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl font-bold transition shadow-lg flex items-center justify-center gap-2">
                    <i class="fa-solid fa-print w-5 h-5"></i>
                    Cetak Sekarang
                </button>
                <button onclick="closePrintModal()" class="w-full py-3 text-slate-500 hover:text-slate-700 font-semibold transition">Batal</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
