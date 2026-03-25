<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Manajemen Lokasi Inventaris";
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    <div class="p-6 border-b border-slate-200 flex justify-between items-center bg-slate-50/50">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Struktur Lokasi</h2>
            <p class="text-sm text-slate-500 mt-1">Kelola hierarki lokasi penempatan barang.</p>
        </div>
        <button onclick="openModal(null)" class="bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Tambah Root Lokasi
        </button>
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
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Lokasi</label>
                <input type="text" id="loc_name" name="name" required class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none transition">
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
    async function loadTree() {
        try {
            const res = await fetch('<?php echo BASE_URL; ?>/api/inventory/locations/get.php');
            const data = await res.json();
            
            const treeContainer = document.getElementById('location-tree');
            treeContainer.innerHTML = '';
            
            if (data.success && data.data) {
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
            <span class="font-semibold text-slate-700">${node.name}</span>
        `;
        
        const actionArea = document.createElement('div');
        actionArea.className = "flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity";
        
        // Buttons
        const btnAdd = document.createElement('button');
        btnAdd.className = "p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-md transition";
        btnAdd.innerHTML = `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>`;
        btnAdd.title = "Tambah Child Node";
        btnAdd.onclick = () => openModal(null, node.id, node.name);
        
        const btnEdit = document.createElement('button');
        btnEdit.className = "p-1.5 text-amber-600 hover:bg-amber-50 rounded-md transition";
        btnEdit.innerHTML = `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>`;
        btnEdit.title = "Edit Nama";
        btnEdit.onclick = () => openModal(node.id, node.parent_id, null, node.name);
        
        const btnDel = document.createElement('button');
        btnDel.className = "p-1.5 text-rose-600 hover:bg-rose-50 rounded-md transition";
        btnDel.innerHTML = `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>`;
        btnDel.title = "Hapus";
        btnDel.onclick = () => deleteNode(node.id);
        
        actionArea.appendChild(btnAdd);
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
    function openModal(id = null, parentId = null, parentName = null, currentName = '') {
        document.getElementById('loc_id').value = id || '';
        document.getElementById('loc_parent_id').value = parentId || '';
        document.getElementById('loc_name').value = currentName;
        
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
        const name = document.getElementById('loc_name').value;
        
        const payload = { name: name, parent_id: parent_id };
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
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
