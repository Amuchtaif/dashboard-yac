/**
 * Employee Groups Module JavaScript
 * Handles Index, Form, and Detail pages.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Determine current page by checking specific elements
    const isIndexPage = document.getElementById('groupsTableBody') !== null;
    const isFormPage = document.getElementById('groupForm') !== null;
    const isDetailPage = document.getElementById('appContainer')?.getAttribute('data-mode') === 'detail';

    if (isIndexPage) initIndexPage();
    if (isFormPage) initFormPage();
    if (isDetailPage) initDetailPage();
});

// ==========================================
// INDEX PAGE LOGIC
// ==========================================
let currentPage = 1;
const limit = 10;

function initIndexPage() {
    loadGroups();

    // Event Listeners for Filters
    document.getElementById('searchInput').addEventListener('input', debounce(() => loadGroups(1), 500));
    document.getElementById('filterType').addEventListener('change', () => loadGroups(1));
    document.getElementById('filterStatus').addEventListener('change', () => loadGroups(1));
    document.getElementById('filterLimit').addEventListener('change', () => loadGroups(1));
}

function loadGroups(page = 1) {
    if (typeof page !== 'number') {
        page = 1;
    }
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const currentLimit = document.getElementById('filterLimit')?.value || limit;
    
    const tbody = document.getElementById('groupsTableBody');
    const emptyState = document.getElementById('emptyState');
    const table = document.getElementById('groupsTable');
    
    tbody.innerHTML = `<tr>
        <td colspan="7" class="p-6">
            <div class="animate-pulse flex flex-col gap-4">
                <div class="h-4 bg-slate-200 rounded w-full"></div>
                <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                <div class="h-4 bg-slate-200 rounded w-5/6"></div>
            </div>
        </td>
    </tr>`;
    
    table.classList.remove('hidden');
    emptyState.classList.add('hidden');
 
    let url = `${APP_URL}/api/employee_groups/index.php?page=${page}&limit=${currentLimit}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    if (type) url += `&type=${encodeURIComponent(type)}`;
    if (status) url += `&is_active=${encodeURIComponent(status)}`;
 
    fetch(url)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                renderGroupsTable(res.data);
                renderPagination(res.meta);
                if (res.data.length === 0 && !search && !type && !status) {
                    table.classList.add('hidden');
                    emptyState.classList.remove('hidden');
                }
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="p-6 text-center text-red-500">Gagal memuat data</td></tr>`;
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="7" class="p-6 text-center text-red-500">Terjadi kesalahan koneksi</td></tr>`;
        });
}
 
let dragSrcEl = null;

function handleDragStart(e) {
    this.classList.add('bg-blue-50', 'border-y-2', 'border-dashed', 'border-blue-400');
    dragSrcEl = this;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    
    if (this !== dragSrcEl) {
        const rect = this.getBoundingClientRect();
        const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
        const parent = this.parentNode;
        if (next) {
            parent.insertBefore(dragSrcEl, this.nextSibling);
        } else {
            parent.insertBefore(dragSrcEl, this);
        }
    }
    return false;
}

function handleDragEnd(e) {
    document.querySelectorAll('#groupsTableBody tr').forEach(row => {
        row.classList.remove('bg-blue-50', 'border-y-2', 'border-dashed', 'border-blue-400');
    });
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    saveNewOrder();
    return false;
}

function saveNewOrder() {
    const rows = document.querySelectorAll('#groupsTableBody tr');
    const ids = [];
    rows.forEach(row => {
        const id = row.getAttribute('data-id');
        if (id) ids.push(parseInt(id));
    });
    
    if (ids.length === 0) return;
    
    fetch(`${APP_URL}/api/employee_groups/reorder.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids })
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            if (typeof showToast === 'function') {
                showToast('Urutan grup berhasil diperbarui!', 'success');
            } else {
                alert('Urutan grup berhasil diperbarui!');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(res.message || 'Gagal memperbarui urutan.', 'error');
            } else {
                alert(res.message || 'Gagal memperbarui urutan.');
            }
            loadGroups(currentPage);
        }
    })
    .catch(err => {
        console.error(err);
        if (typeof showToast === 'function') {
            showToast('Terjadi kesalahan koneksi.', 'error');
        } else {
            alert('Terjadi kesalahan koneksi.');
        }
        loadGroups(currentPage);
    });
}

function renderGroupsTable(data) {
    const tbody = document.getElementById('groupsTableBody');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="p-6 text-center text-slate-500">Tidak ada grup ditemukan.</td></tr>`;
        return;
    }

    const search = document.getElementById('searchInput').value;
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const isFiltered = !!(search || type || status);
 
    data.forEach(group => {
        const typeBadge = group.type === 'dynamic' 
            ? `<span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10">Dynamic</span>`
            : `<span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">Manual</span>`;
            
        const statusBadge = group.is_active == 1
            ? `<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Aktif</span>`
            : `<span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">Tidak Aktif</span>`;
 
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors';
        tr.setAttribute('data-id', group.id);
        
        if (!isFiltered) {
            tr.setAttribute('draggable', 'true');
            tr.classList.add('cursor-move');
            tr.addEventListener('dragstart', handleDragStart);
            tr.addEventListener('dragover', handleDragOver);
            tr.addEventListener('drop', handleDrop);
            tr.addEventListener('dragend', handleDragEnd);
        } else {
            tr.setAttribute('draggable', 'false');
        }

        const dragHandleCol = !isFiltered 
            ? `<td class="whitespace-nowrap py-4 pl-6 pr-3 text-slate-400 cursor-move drag-handle">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
                </svg>
               </td>`
            : `<td class="whitespace-nowrap py-4 pl-6 pr-3 text-slate-300 select-none" title="Urutan dinonaktifkan saat filter aktif">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 opacity-30">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
                </svg>
               </td>`;

        tr.innerHTML = `
            ${dragHandleCol}
            <td class="whitespace-nowrap py-4 pl-3 pr-3 text-sm font-medium text-slate-900">
                ${escapeHtml(group.name)}
            </td>
            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">${typeBadge}</td>
            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">${statusBadge}</td>
            <td class="px-3 py-4 text-sm text-slate-500 hidden md:table-cell max-w-xs truncate" title="${escapeHtml(group.description || '')}">
                ${escapeHtml(group.description || '-')}
            </td>
            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 hidden lg:table-cell">
                ${group.updated_at ? new Date(group.updated_at).toLocaleDateString('id-ID') : '-'}
            </td>
            <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                <div class="flex items-center justify-end gap-2">
                    <a href="${APP_URL}/views/employee_groups/detail.php?id=${group.id}" class="p-2 text-slate-400 hover:text-cyan-600 rounded-lg transition-colors" title="Detail">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </a>
                    <a href="${APP_URL}/views/employee_groups/form.php?id=${group.id}" class="p-2 text-slate-400 hover:text-blue-600 rounded-lg transition-colors" title="Ubah">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                        </svg>
                    </a>
                    <button type="button" onclick="openDeleteModal(${group.id})" class="p-2 text-slate-400 hover:text-red-600 rounded-lg transition-colors" title="Hapus">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function renderPagination(meta) {
    const container = document.getElementById('paginationContainer');
    if (!meta || meta.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = `
        <div class="hidden sm:block">
            <p class="text-sm text-slate-700">
                Menampilkan <span class="font-medium">${(meta.page - 1) * meta.limit + 1}</span> 
                sampai <span class="font-medium">${Math.min(meta.page * meta.limit, meta.total_records)}</span> 
                dari <span class="font-medium">${meta.total_records}</span> hasil
            </p>
        </div>
        <div class="flex flex-1 justify-between sm:justify-end">
            <button ${meta.page === 1 ? 'disabled' : ''} onclick="loadGroups(${meta.page - 1})" 
                class="relative inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50 mr-2">
                Sebelumnya
            </button>
            <button ${meta.page === meta.total_pages ? 'disabled' : ''} onclick="loadGroups(${meta.page + 1})" 
                class="relative inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50">
                Selanjutnya
            </button>
        </div>
    `;
    container.innerHTML = html;
}

// Delete Modal Logic
let groupToDelete = null;

function openDeleteModal(id) {
    groupToDelete = id;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteErrorMsg').classList.add('hidden');
}

function closeDeleteModal() {
    groupToDelete = null;
    document.getElementById('deleteModal').classList.add('hidden');
}

document.getElementById('confirmDeleteBtn')?.addEventListener('click', function() {
    if (!groupToDelete) return;
    
    const btn = this;
    const originalText = btn.innerText;
    btn.innerText = 'Menghapus...';
    btn.disabled = true;

    fetch(`${APP_URL}/api/employee_groups/index.php`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: groupToDelete })
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            closeDeleteModal();
            loadGroups(currentPage);
        } else {
            const errorMsg = document.getElementById('deleteErrorMsg');
            errorMsg.innerText = res.message || 'Gagal menghapus grup.';
            errorMsg.classList.remove('hidden');
        }
    })
    .catch(err => {
        console.error(err);
        const errorMsg = document.getElementById('deleteErrorMsg');
        errorMsg.innerText = 'Terjadi kesalahan sistem.';
        errorMsg.classList.remove('hidden');
    })
    .finally(() => {
        btn.innerText = originalText;
        btn.disabled = false;
    });
});

// ==========================================
// FORM PAGE LOGIC
// ==========================================
let formGroupId = 0;
let selectedManualMembers = [];
let currentSearchResults = [];

function initFormPage() {
    formGroupId = parseInt(document.getElementById('appContainer').getAttribute('data-id') || 0);
    
    const typeSelect = document.getElementById('group_type');
    typeSelect.addEventListener('change', toggleSections);
    
    document.getElementById('addRuleBtn').addEventListener('click', addEmptyRule);
    document.getElementById('previewBtn').addEventListener('click', previewDynamicGroup);
    
    // Manual Search & List Listeners
    document.getElementById('employeeSearch').addEventListener('input', debounce(searchEmployees, 300));
    document.getElementById('removeAllBtn').addEventListener('click', () => {
        selectedManualMembers = [];
        renderSelectedMembers();
        searchEmployees(); // re-enable in left list
    });
    
    document.getElementById('bulkAddBtn').addEventListener('click', () => {
        if (currentSearchResults.length === 0) return;
        let addedCount = 0;
        currentSearchResults.forEach(emp => {
            if (!selectedManualMembers.some(m => m.id === emp.id)) {
                selectedManualMembers.push({
                    id: emp.id,
                    full_name: emp.full_name,
                    position_name: emp.position_name
                });
                addedCount++;
            }
        });
        if (addedCount > 0) {
            renderSelectedMembers();
            searchEmployees();
        }
    });

    document.getElementById('groupForm').addEventListener('submit', handleFormSubmit);

    if (formGroupId > 0) {
        loadGroupForEdit();
    } else {
        addEmptyRule(); // Initial empty rule
    }
    toggleSections();
}

function toggleSections() {
    const type = document.getElementById('group_type').value;
    if (type === 'dynamic') {
        document.getElementById('dynamicSection').classList.remove('hidden');
        document.getElementById('manualSection').classList.add('hidden');
    } else {
        document.getElementById('dynamicSection').classList.add('hidden');
        document.getElementById('manualSection').classList.remove('hidden');
        if(selectedManualMembers.length === 0) searchEmployees(); // Load initial list
    }
}

// -- Dynamic Group Rule Builder --
function addEmptyRule() {
    const container = document.getElementById('rulesContainer');
    const template = document.getElementById('ruleTemplate').content.cloneNode(true);
    
    // Add remove listener
    template.querySelector('.rule-remove').addEventListener('click', function() {
        this.closest('.rule-item').remove();
    });

    container.appendChild(template);
}

function getRulesData() {
    const rules = [];
    document.querySelectorAll('.rule-item').forEach(item => {
        const field = item.querySelector('.rule-field').value;
        const operator = item.querySelector('.rule-operator').value;
        const value = item.querySelector('.rule-value').value;
        if (value.trim() !== '') {
            rules.push({ field, operator, value });
        }
    });
    return rules;
}

function previewDynamicGroup() {
    const rules = getRulesData();
    if (rules.length === 0) {
        alert("Tambahkan setidaknya satu rule dengan value.");
        return;
    }

    const btn = document.getElementById('previewBtn');
    btn.innerText = 'Memuat...';
    btn.disabled = true;

    fetch(`${APP_URL}/api/employee_groups/preview.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rules })
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            document.getElementById('previewTotal').innerText = res.data.total;
            document.getElementById('previewSummary').innerText = rules.length + ' Rules';
            
            const tbody = document.getElementById('previewTableBody');
            tbody.innerHTML = '';
            
            if (res.data.members.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-4 text-center text-slate-500">Tidak ada anggota yang cocok dengan aturan.</td></tr>`;
            } else {
                res.data.members.forEach(emp => {
                    tbody.innerHTML += `
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-900 font-medium">${escapeHtml(emp.full_name)}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-500">${escapeHtml(emp.nik || '-')}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-500">${emp.unit_id || '-'}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-500">${emp.division_id || '-'}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-500">${emp.status || '-'}</td>
                        </tr>
                    `;
                });
            }
            
            document.getElementById('previewModal').classList.remove('hidden');
        } else {
            alert(res.message || 'Gagal memuat preview.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Terjadi kesalahan sistem saat memuat preview.');
    })
    .finally(() => {
        btn.innerText = 'Preview Anggota';
        btn.disabled = false;
    });
}

function closePreviewModal() {
    document.getElementById('previewModal').classList.add('hidden');
}

// -- Manual Group Members --
function searchEmployees() {
    const q = document.getElementById('employeeSearch').value;
    const list = document.getElementById('employeeList');
    
    list.innerHTML = `<div class="p-4 text-center text-sm text-slate-500">Mencari...</div>`;
    
    fetch(`${APP_URL}/api/get_employees.php?search=${encodeURIComponent(q)}&limit=50`)
        .then(res => res.json())
        .then(res => {
            if (res.success || res.status === 'success') {
                list.innerHTML = '';
                const data = res.data || res.employees || [];
                currentSearchResults = data;
                if(data.length === 0) {
                    list.innerHTML = `<div class="p-4 text-center text-sm text-slate-500">Tidak ditemukan.</div>`;
                }
                data.forEach(emp => {
                    const isSelected = selectedManualMembers.some(m => m.id === emp.id);
                    const div = document.createElement('div');
                    div.className = `flex justify-between items-center p-3 border-b border-slate-100 hover:bg-slate-50 ${isSelected ? 'opacity-50 pointer-events-none' : 'cursor-pointer'}`;
                    div.innerHTML = `
                        <div>
                            <div class="text-sm font-medium text-slate-900">${escapeHtml(emp.full_name)}</div>
                            <div class="text-xs text-slate-500">${escapeHtml(emp.position_name || '-')}</div>
                        </div>
                        <button type="button" class="text-[#2B3990] hover:text-blue-800 p-1">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    `;
                    if (!isSelected) {
                        div.addEventListener('click', () => {
                            selectedManualMembers.push({
                                id: emp.id,
                                full_name: emp.full_name,
                                position_name: emp.position_name
                            });
                            searchEmployees(); // re-render list to disable added
                            renderSelectedMembers();
                        });
                    }
                    list.appendChild(div);
                });
            } else {
                list.innerHTML = `<div class="p-4 text-center text-sm text-red-500">Gagal memuat: ${res.message || 'Error'}</div>`;
            }
        })
        .catch(err => {
            console.error(err);
            list.innerHTML = `<div class="p-4 text-center text-sm text-red-500">Kesalahan sistem, coba lagi.</div>`;
        });
}

function renderSelectedMembers() {
    const list = document.getElementById('selectedList');
    document.getElementById('selectedCount').innerText = selectedManualMembers.length;
    
    if (selectedManualMembers.length === 0) {
        list.innerHTML = `<div class="p-4 text-center text-sm text-slate-500" id="selectedEmpty">Belum ada anggota yang dipilih</div>`;
        return;
    }
    
    list.innerHTML = '';
    selectedManualMembers.forEach((emp, index) => {
        const div = document.createElement('div');
        div.className = `flex justify-between items-center p-3 border-b border-slate-100 bg-white`;
        div.innerHTML = `
            <div>
                <div class="text-sm font-medium text-slate-900">${escapeHtml(emp.full_name)}</div>
                <div class="text-xs text-slate-500">${escapeHtml(emp.position_name || '-')}</div>
            </div>
            <button type="button" class="text-rose-500 hover:text-rose-700 p-1">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </button>
        `;
        div.querySelector('button').addEventListener('click', () => {
            selectedManualMembers.splice(index, 1);
            renderSelectedMembers();
            searchEmployees(); // re-enable in left list
        });
        list.appendChild(div);
    });
}

// -- Load & Save --
function loadGroupForEdit() {
    fetch(`${APP_URL}/api/employee_groups/detail.php?id=${formGroupId}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const g = res.data;
                document.getElementById('group_name').value = g.name;
                document.getElementById('group_type').value = g.type;
                document.getElementById('is_active').value = g.is_active;
                document.getElementById('description').value = g.description;
                
                toggleSections();

                if (g.type === 'dynamic' && g.rules) {
                    let rulesObj = [];
                    try { rulesObj = JSON.parse(g.rules); } catch(e){}
                    document.getElementById('rulesContainer').innerHTML = '';
                    if(rulesObj.length > 0) {
                        rulesObj.forEach(r => {
                            const template = document.getElementById('ruleTemplate').content.cloneNode(true);
                            template.querySelector('.rule-field').value = r.field;
                            template.querySelector('.rule-operator').value = r.operator;
                            template.querySelector('.rule-value').value = r.value;
                            template.querySelector('.rule-remove').addEventListener('click', function() {
                                this.closest('.rule-item').remove();
                            });
                            document.getElementById('rulesContainer').appendChild(template);
                        });
                    } else {
                        addEmptyRule();
                    }
                } else if (g.type === 'manual') {
                    // Load members
                    fetch(`${APP_URL}/api/employee_groups/members.php?group_id=${formGroupId}`)
                        .then(r => r.json())
                        .then(r => {
                            if (r.status === 'success') {
                                selectedManualMembers = r.data.map(m => ({
                                    id: m.id, // employee_id
                                    full_name: m.full_name,
                                    position_name: m.position_id || '-' // Don't have position name from members API directly without join, but it's ok for display
                                }));
                                renderSelectedMembers();
                                searchEmployees();
                            }
                        });
                }
            } else {
                alert('Gagal memuat grup');
            }
        });
}

function handleFormSubmit(e) {
    e.preventDefault();
    
    const name = document.getElementById('group_name').value;
    const type = document.getElementById('group_type').value;
    const is_active = document.getElementById('is_active').value;
    const description = document.getElementById('description').value;
    
    let rulesStr = null;
    let employeeIds = null;

    if (type === 'dynamic') {
        const rules = getRulesData();
        if (rules.length === 0) {
            alert("Harap tambahkan setidaknya satu rule.");
            return;
        }
        rulesStr = JSON.stringify(rules);
    } else {
        if (selectedManualMembers.length === 0) {
            alert("Harap pilih setidaknya satu anggota.");
            return;
        }
        employeeIds = selectedManualMembers.map(m => m.id);
    }

    const payload = {
        group_name: name,
        group_type: type,
        is_active,
        description
    };
    
    if (formGroupId > 0) {
        payload.id = formGroupId;
    }
    
    if (type === 'dynamic') {
        payload.rules = rulesStr;
    } else {
        payload.employee_ids = employeeIds;
    }

    const btn = document.getElementById('saveBtn');
    const originalText = btn.innerText;
    btn.innerText = 'Menyimpan...';
    btn.disabled = true;

    const method = formGroupId > 0 ? 'PUT' : 'POST';
    const url = formGroupId > 0 
        ? `${APP_URL}/api/employee_groups/detail.php?id=${formGroupId}` 
        : `${APP_URL}/api/employee_groups/index.php`;

    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            const msg = encodeURIComponent(formGroupId > 0 ? 'Kelompok karyawan berhasil diperbarui!' : 'Kelompok karyawan berhasil ditambahkan!');
            window.location.href = `${APP_URL}/views/employee_groups/index.php?success=${msg}`;
        } else {
            alert(res.message || 'Gagal menyimpan grup.');
            btn.innerText = originalText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error(err);
        alert('Terjadi kesalahan sistem.');
        btn.innerText = originalText;
        btn.disabled = false;
    });
}


// ==========================================
// DETAIL PAGE LOGIC
// ==========================================
let detailGroupId = 0;

function initDetailPage() {
    detailGroupId = parseInt(document.getElementById('appContainer').getAttribute('data-id') || 0);
    if(detailGroupId <= 0) return;

    fetch(`${APP_URL}/api/employee_groups/detail.php?id=${detailGroupId}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                renderDetailHeader(res.data);
                
                if (res.data.type === 'dynamic') {
                    renderDetailRules(res.data.rules);
                    fetchDynamicMembers(res.data.rules);
                } else {
                    fetchManualMembers();
                }
            } else {
                document.getElementById('detailGroupName').innerText = 'Grup tidak ditemukan';
            }
        });
}

function renderDetailHeader(g) {
    document.getElementById('detailGroupName').innerText = g.name;
    document.getElementById('detailGroupDescription').innerText = g.description || '-';
    
    const typeBadge = g.type === 'dynamic' 
        ? `<span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10">Dynamic Group</span>`
        : `<span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">Manual Group</span>`;
    document.getElementById('detailGroupType').innerHTML = typeBadge;

    const statusBadge = g.is_active == 1
        ? `<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Aktif</span>`
        : `<span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">Tidak Aktif</span>`;
    document.getElementById('detailGroupStatus').innerHTML = statusBadge;
}

function renderDetailRules(rulesStr) {
    const section = document.getElementById('detailRulesSection');
    const list = document.getElementById('detailRulesList');
    section.classList.remove('hidden');
    
    try {
        const rules = JSON.parse(rulesStr);
        list.innerHTML = '';
        rules.forEach(r => {
            list.innerHTML += `<li><span class="font-medium">${r.field}</span> ${r.operator} <span class="bg-slate-100 px-1 rounded">${r.value}</span></li>`;
        });
    } catch(e) {
        list.innerHTML = '<li>Error parsing rules</li>';
    }
}

function fetchDynamicMembers(rulesStr) {
    let rules = [];
    try { rules = JSON.parse(rulesStr); } catch(e){}

    fetch(`${APP_URL}/api/employee_groups/preview.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rules })
    })
    .then(res => res.json())
    .then(res => {
        if(res.status === 'success') {
            document.getElementById('membersCountDesc').innerText = `${res.data.total} anggota ditemukan (Dinamis).`;
            renderDetailTable(res.data.members);
        }
    });
}

function fetchManualMembers() {
    fetch(`${APP_URL}/api/employee_groups/members.php?group_id=${detailGroupId}`)
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success') {
                document.getElementById('membersCountDesc').innerText = `${res.data.length} anggota manual.`;
                renderDetailTable(res.data);
            }
        });
}

function renderDetailTable(data) {
    const tbody = document.getElementById('detailMembersTable');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada anggota.</td></tr>`;
        return;
    }

    data.forEach(emp => {
        tbody.innerHTML += `
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">${escapeHtml(emp.full_name)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">${escapeHtml(emp.nik || '-')}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">${emp.unit_id || '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">${emp.division_id || '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">${emp.status || '-'}</td>
            </tr>
        `;
    });
}

// Utility
function escapeHtml(unsafe) {
    if(!unsafe) return '';
    return (unsafe + '')
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
