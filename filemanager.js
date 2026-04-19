(function() {
    'use strict';

    const fm = document.getElementById('fileManager');
    const fileList = document.getElementById('fileList');
    const currentPathEl = document.getElementById('currentPath');
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileInput');
    const uploadQueue = document.getElementById('uploadQueue');
    const paginationEl = document.getElementById('pagination');
    const modalNewFolder = document.getElementById('modalNewFolder');
    const modalConfirm = document.getElementById('modalConfirm');
    const modalRename = document.getElementById('modalRename');
    const newFolderInput = document.getElementById('newFolderName');
    const renameInput = document.getElementById('renameInput');
    const sortBySelect = document.getElementById('sortBy');
    const sortOrderSelect = document.getElementById('sortOrder');

    let currentPath = '';
    let basePath = '';
    let rootPath = '';
    let confirmCallback = null;
    let renameCallback = null;
    let renameOldName = '';
    let currentPage = 1;
    let sortBy = 'name';
    let sortOrder = 'asc';
    let selectedItems = new Set();
    let toolbarEl;
    let selectionCountEl;
    let currentItems = [];
    let csrfToken = '';

    function getBaseUrl() {
        const scripts = document.getElementsByTagName('script');
        for (let script of scripts) {
            if (script.src.includes('filemanager.js')) {
                return script.src.replace('/filemanager.js', '/api.php');
            }
        }
        return 'api.php';
    }

    function ajax(url, data, method = 'GET') {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url, true);
            
            if (!(data instanceof FormData)) {
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            }
            
            // Add CSRF token to POST requests
            if (method === 'POST') {
                if (data instanceof FormData) {
                    data.append('csrf_token', csrfToken);
                } else {
                    data.csrf_token = csrfToken;
                }
                xhr.setRequestHeader('X-CSRF-Token', csrfToken);
            }
            
            xhr.onload = function() {
                try {
                    resolve(JSON.parse(xhr.responseText));
                } catch (e) {
                    reject(e);
                }
            };
            
            xhr.onerror = function() {
                reject(new Error('Errore di rete'));
            };
            
            if (data instanceof FormData) {
                xhr.send(data);
            } else if (method === 'POST') {
                const params = Object.keys(data).map(k => encodeURIComponent(k) + '=' + encodeURIComponent(data[k])).join('&');
                xhr.send(params);
            } else {
                const query = Object.keys(data).map(k => encodeURIComponent(k) + '=' + encodeURIComponent(data[k])).join('&');
                xhr.open(method, url + '?' + query, true);
                xhr.send();
            }
        });
    }

    function renderBreadcrumb(path) {
        if (!path) {
            currentPathEl.innerHTML = '<i class="fa-solid fa-house"></i><span>/</span>';
            return;
        }
        
        const parts = path.split('/');
        let html = '<i class="fa-solid fa-house"></i><span>/</span>';
        let accumulated = '';
        
        parts.forEach((part, i) => {
            accumulated += (i > 0 ? '/' : '') + part;
            if (i === 0) {
                html += `<span class="fm-breadcrumb-item" data-path="">${escapeHtml(part)}</span>`;
            } else {
                html += `<span class="fm-breadcrumb-sep">/</span><span class="fm-breadcrumb-item" data-path="${escapeHtml(accumulated)}">${escapeHtml(part)}</span>`;
            }
        });
        
        currentPathEl.innerHTML = html;
        
        currentPathEl.querySelectorAll('.fm-breadcrumb-item').forEach(item => {
            item.style.cursor = 'pointer';
            item.addEventListener('click', () => {
                loadDirectory(item.dataset.path);
            });
        });
    }

    async function getCsrfToken() {
        try {
            const response = await ajax(getBaseUrl(), { action: 'get_csrf_token' });
            if (response.success && response.csrf_token) {
                csrfToken = response.csrf_token;
            }
        } catch (e) {
            console.error('Failed to get CSRF token:', e);
        }
    }

    async function init() {
        toolbarEl = document.getElementById('toolbar');
        selectionCountEl = document.getElementById('selectionCount');
        
        // Get CSRF token first
        await getCsrfToken();
        
        try {
            const response = await ajax(getBaseUrl(), { 
                action: 'list', 
                path: currentPath,
                sort_by: sortBy,
                sort_order: sortOrder,
                page: currentPage
            });
            if (response.success) {
                rootPath = response.full_base_path || '';
                basePath = response.path || '';
                renderFileList(response);
                renderPagination(response.pagination);
                renderBreadcrumb(currentPath);
            }
        } catch (e) {
            fileList.innerHTML = '<div class="fm-empty"><i class="fa-solid fa-folder-xmark"></i><span>Errore caricamento file</span></div>';
        }
    }

    async function loadDirectory(path, page = 1) {
        path = path || '';
        if (path.includes('..')) {
            return;
        }
        clearSelection();
        currentPath = path;
        currentPage = page;
        fileList.innerHTML = '<div class="fm-loading"><i class="fa-solid fa-spinner fa-spin"></i><span>Caricamento...</span></div>';
        
        try {
            const response = await ajax(getBaseUrl(), { 
                action: 'list', 
                path: currentPath, 
                base_path: rootPath,
                sort_by: sortBy,
                sort_order: sortOrder,
                page: currentPage
            });
            if (response.success) {
                renderFileList(response);
                renderPagination(response.pagination);
                renderBreadcrumb(currentPath);
            }
        } catch (e) {
            fileList.innerHTML = '<div class="fm-empty"><i class="fa-solid fa-folder-xmark"></i><span>Errore caricamento directory</span></div>';
        }
    }

    function renderPagination(pagination) {
        if (!pagination || pagination.total_pages <= 1) {
            paginationEl.innerHTML = '';
            return;
        }

        let html = '';
        
        html += `<button class="fm-page-btn" onclick="document.getElementById('fileManager').FM.goToPage(1)" ${pagination.page <= 1 ? 'disabled' : ''}><i class="fa-solid fa-angles-left"></i></button>`;
        html += `<button class="fm-page-btn" onclick="document.getElementById('fileManager').FM.goToPage(${pagination.page - 1})" ${pagination.page <= 1 ? 'disabled' : ''}><i class="fa-solid fa-angle-left"></i></button>`;
        
        const start = Math.max(1, pagination.page - 2);
        const end = Math.min(pagination.total_pages, pagination.page + 2);
        
        if (start > 1) {
            html += `<button class="fm-page-btn" onclick="document.getElementById('fileManager').FM.goToPage(1)">1</button>`;
            if (start > 2) html += '<span style="padding:0 4px;color:var(--fm-text-muted);">...</span>';
        }
        
        for (let i = start; i <= end; i++) {
            html += `<button class="fm-page-btn ${i === pagination.page ? 'active' : ''}" onclick="document.getElementById('fileManager').FM.goToPage(${i})">${i}</button>`;
        }
        
        if (end < pagination.total_pages) {
            if (end < pagination.total_pages - 1) html += '<span style="padding:0 4px;color:var(--fm-text-muted);">...</span>';
            html += `<button class="fm-page-btn" onclick="document.getElementById('fileManager').FM.goToPage(${pagination.total_pages})">${pagination.total_pages}</button>`;
        }
        
        html += `<button class="fm-page-btn" onclick="document.getElementById('fileManager').FM.goToPage(${pagination.page + 1})" ${pagination.page >= pagination.total_pages ? 'disabled' : ''}><i class="fa-solid fa-angle-right"></i></button>`;
        html += `<button class="fm-page-btn" onclick="document.getElementById('fileManager').FM.goToPage(${pagination.total_pages})" ${pagination.page >= pagination.total_pages ? 'disabled' : ''}><i class="fa-solid fa-angles-right"></i></button>`;
        
        html += `<span class="fm-page-info">${pagination.total_items} elementi - Pag. ${pagination.page}/${pagination.total_pages}</span>`;
        
        paginationEl.innerHTML = html;
    }

    function renderFileList(data) {
        if (!data.items || data.items.length === 0) {
            fileList.innerHTML = '<div class="fm-empty"><i class="fa-solid fa-folder-open"></i><span>Nessun file o cartella</span></div>';
            return;
        }
        
        currentItems = data.items;
        
        let html = '';
        data.items.forEach((item, index) => {
            const iconClass = item.is_dir ? 'fa-folder' : getFileIconClass(item.name);
            const iconColor = item.is_dir ? '' : getFileIconColor(item.name);
            const size = item.size_fmt || item.size || '';
            const modified = item.modified_fmt || item.modified || '';
            const isSelected = selectedItems.has(item.path);
            const downloadLink = !item.is_dir ? `api.php?action=download&path=${encodeURIComponent(item.path)}&base_path=${encodeURIComponent(rootPath)}` : '';
            
            html += `
                <div class="fm-item ${isSelected ? 'selected' : ''}" data-path="${item.path}" data-is-dir="${item.is_dir}" data-index="${index}">
                    <input type="checkbox" class="fm-item-checkbox" ${isSelected ? 'checked' : ''}>
                    <div class="fm-item-icon ${item.is_dir ? 'folder' : 'file'}">
                        <i class="fa-solid ${iconClass}" ${iconColor ? `style="color:${iconColor}"` : ''}></i>
                    </div>
                    <div class="fm-item-info">
                        <div class="fm-item-name">${escapeHtml(item.name)}</div>
                        <div class="fm-item-meta">
                            <span><i class="fa-solid ${item.is_dir ? 'fa-folder' : 'fa-file-lines'}"></i> ${item.is_dir ? 'Cartella' : size}</span>
                            <span><i class="fa-regular fa-clock"></i> ${modified}</span>
                        </div>
                    </div>
                    ${downloadLink ? `<a href="${downloadLink}" class="fm-item-download" target="_blank" title="Scarica"><i class="fa-solid fa-download"></i></a>` : ''}
                    <div class="fm-item-actions">
                        <button type="button" class="fm-item-action btn-rename" title="Rinomina"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="fm-item-action btn-delete" title="Elimina"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
            `;
        });
        
        fileList.innerHTML = html;
        attachItemListeners();
        updateToolbar();
    }

    function getFileIconClass(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const icons = {
            'jpg': 'fa-file-image', 'jpeg': 'fa-file-image', 'png': 'fa-file-image', 
            'gif': 'fa-file-image', 'svg': 'fa-file-image', 'webp': 'fa-file-image',
            'pdf': 'fa-file-pdf',
            'doc': 'fa-file-word', 'docx': 'fa-file-word',
            'xls': 'fa-file-excel', 'xlsx': 'fa-file-excel',
            'zip': 'fa-file-zipper', 'rar': 'fa-file-zipper', '7z': 'fa-file-zipper', 
            'tar': 'fa-file-zipper', 'gz': 'fa-file-zipper',
            'mp3': 'fa-file-audio', 'wav': 'fa-file-audio', 'ogg': 'fa-file-audio',
            'mp4': 'fa-file-video', 'avi': 'fa-file-video', 'mkv': 'fa-file-video', 'mov': 'fa-file-video',
            'html': 'fa-file-code', 'css': 'fa-file-code', 'js': 'fa-file-code', 'json': 'fa-file-code',
            'php': 'fa-file-code', 'py': 'fa-file-code', 'rb': 'fa-file-code', 'java': 'fa-file-code',
            'txt': 'fa-file-lines', 'md': 'fa-file-lines', 'csv': 'fa-file-csv',
            'css': 'fa-file-code', 'scss': 'fa-file-code', 'less': 'fa-file-code',
        };
        return icons[ext] || 'fa-file';
    }

    function getFileIconColor(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const colors = {
            'jpg': '#e74c3c', 'jpeg': '#e74c3c', 'png': '#e74c3c', 
            'gif': '#e74c3c', 'svg': '#e74c3c', 'webp': '#e74c3c',
            'pdf': '#e74c3c',
            'doc': '#2196F3', 'docx': '#2196F3',
            'xls': '#4caf50', 'xlsx': '#4caf50',
            'zip': '#ff9800', 'rar': '#ff9800', '7z': '#ff9800',
            'mp3': '#9c27b0', 'wav': '#9c27b0',
            'mp4': '#e91e63', 'avi': '#e91e63', 'mkv': '#e91e63',
            'html': '#f16529', 'css': '#264de4', 'js': '#f7df1e',
            'php': '#777bb4', 'py': '#3776ab',
        };
        return colors[ext] || '';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function attachItemListeners() {
        document.querySelectorAll('.fm-item').forEach(item => {
            const path = item.dataset.path;
            const isDir = item.dataset.isDir === 'true';
            const checkbox = item.querySelector('.fm-item-checkbox');
            
            checkbox?.addEventListener('change', (e) => {
                e.stopPropagation();
                if (checkbox.checked) {
                    selectedItems.add(path);
                    item.classList.add('selected');
                } else {
                    selectedItems.delete(path);
                    item.classList.remove('selected');
                }
                updateToolbar();
            });
            
            item.querySelector('.fm-item-name')?.addEventListener('dblclick', () => {
                if (isDir) {
                    loadDirectory(path);
                }
            });
            
            item.addEventListener('click', (e) => {
                if (e.target.closest('.btn-delete') || 
                    e.target.closest('.btn-rename') || 
                    e.target.closest('.fm-item-checkbox') ||
                    e.target.closest('.fm-item-download') ||
                    e.target.closest('.fm-item-name')) return;
                if (isDir) {
                    loadDirectory(path);
                }
            });
            
            item.querySelector('.btn-delete')?.addEventListener('click', (e) => {
                e.stopPropagation();
                showConfirm(
                    '<i class="fa-solid fa-trash"></i> Conferma eliminazione',
                    `Sei sicuro di voler eliminare "${item.querySelector('.fm-item-name').textContent}"?`,
                    async () => {
                        await deleteItem(path, isDir);
                    }
                );
            });
            
            item.querySelector('.btn-rename')?.addEventListener('click', (e) => {
                e.stopPropagation();
                renameItem(path, item.querySelector('.fm-item-name').textContent, isDir);
            });
        });
    }

    function updateToolbar() {
        const count = selectedItems.size;
        if (count > 0) {
            toolbarEl.classList.add('visible');
            selectionCountEl.textContent = count;
        } else {
            toolbarEl.classList.remove('visible');
        }
    }

    function clearSelection() {
        selectedItems.clear();
        document.querySelectorAll('.fm-item').forEach(item => {
            item.classList.remove('selected');
            const checkbox = item.querySelector('.fm-item-checkbox');
            if (checkbox) checkbox.checked = false;
        });
        updateToolbar();
    }

    async function deleteSelected() {
        if (selectedItems.size === 0) return;
        
        const count = selectedItems.size;
        showConfirm(
            '<i class="fa-solid fa-trash"></i> Conferma eliminazione',
            `Sei sicuro di voler eliminare ${count} element${count === 1 ? 'o' : 'i'} selezionat${count === 1 ? 'o' : 'i'}?`,
            async () => {
                for (const path of selectedItems) {
                    const item = currentItems.find(i => i.path === path);
                    if (item) {
                        try {
                            await ajax(getBaseUrl(), { action: 'delete', path: path, base_path: rootPath }, 'POST');
                        } catch (e) {
                            console.error('Errore eliminazione:', path);
                        }
                    }
                }
                clearSelection();
                loadDirectory(currentPath, currentPage);
            }
        );
    }

    function downloadSelected() {
        for (const path of selectedItems) {
            const item = currentItems.find(i => i.path === path);
            if (item && !item.is_dir) {
                const link = document.createElement('a');
                link.href = `api.php?action=download&path=${encodeURIComponent(item.path)}&base_path=${encodeURIComponent(rootPath)}`;
                link.download = item.name;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
    }

    async function deleteItem(path, isDir) {
        try {
            const response = await ajax(getBaseUrl(), { action: 'delete', path: path, base_path: rootPath }, 'POST');
            if (response.success) {
                loadDirectory(currentPath);
            } else {
                alert('Errore: ' + (response.error || 'Impossibile eliminare'));
            }
        } catch (e) {
            alert('Errore di rete');
        }
    }

    async function createFolder(name) {
        try {
            const formData = new FormData();
            formData.append('action', 'create_folder');
            formData.append('path', currentPath);
            formData.append('base_path', rootPath);
            formData.append('name', name);
            
            const response = await ajax(getBaseUrl(), formData, 'POST');
            if (response.success) {
                closeModal(modalNewFolder);
                loadDirectory(currentPath);
            } else {
                alert('Errore: ' + (response.error || 'Impossibile creare cartella'));
            }
        } catch (e) {
            alert('Errore di rete');
        }
    }

    async function uploadFiles(files) {
        if (!files || files.length === 0) return;
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const itemId = 'upload-' + Date.now() + '-' + i;
            
            const itemHtml = `
                <div class="fm-upload-item" id="${itemId}">
                    <i class="fa-solid fa-file" style="color:var(--fm-text-muted);"></i>
                    <span class="fm-upload-name">${escapeHtml(file.name)}</span>
                    <span class="fm-upload-status">Caricamento...</span>
                </div>
            `;
            uploadQueue.insertAdjacentHTML('beforeend', itemHtml);
            
            try {
                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('path', currentPath);
                formData.append('base_path', rootPath);
                formData.append('files', file);
                
                const response = await ajax(getBaseUrl(), formData, 'POST');
                const el = document.getElementById(itemId);
                
                if (response.success && response.uploaded && response.uploaded.length > 0) {
                    el.classList.add('success');
                    el.querySelector('.fm-upload-status').textContent = 'Completato';
                    el.querySelector('i').className = 'fa-solid fa-check';
                } else {
                    el.classList.add('error');
                    el.querySelector('.fm-upload-status').textContent = response.errors?.[0] || 'Errore';
                    el.querySelector('i').className = 'fa-solid fa-xmark';
                }
                
                setTimeout(() => el.remove(), 3000);
            } catch (e) {
                const el = document.getElementById(itemId);
                if (el) {
                    el.classList.add('error');
                    el.querySelector('.fm-upload-status').textContent = 'Errore';
                    el.querySelector('i').className = 'fa-solid fa-xmark';
                    setTimeout(() => el.remove(), 3000);
                }
            }
        }
        
        setTimeout(() => loadDirectory(currentPath), 500);
    }

    function showModal(modal) {
        modal.classList.add('active');
        const input = modal.querySelector('input[type="text"]');
        if (input) input.focus();
    }

    function closeModal(modal) {
        modal.classList.remove('active');
        const input = modal.querySelector('input[type="text"]');
        if (input) input.value = '';
    }

    function showConfirm(title, message, callback) {
        document.getElementById('confirmTitle').innerHTML = title;
        document.getElementById('confirmMessage').textContent = message;
        confirmCallback = callback;
        showModal(modalConfirm);
    }

    function showRenameModal(oldName, callback) {
        renameOldName = oldName;
        renameInput.value = oldName;
        renameCallback = callback;
        showModal(modalRename);
        setTimeout(() => renameInput.select(), 50);
    }

    async function renameItem(oldPath, oldName, isDir) {
        showRenameModal(oldName, async (newName) => {
            if (!newName || newName === oldName) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'rename');
                formData.append('path', oldPath);
                formData.append('new_name', newName);
                formData.append('base_path', rootPath);
                
                const response = await ajax(getBaseUrl(), formData, 'POST');
                if (response.success) {
                    closeModal(modalRename);
                    loadDirectory(currentPath);
                } else {
                    alert('Errore: ' + (response.error || 'Impossibile rinominare'));
                }
            } catch (e) {
                alert('Errore di rete');
            }
        });
    }

    document.getElementById('btnNewFolder').addEventListener('click', () => {
        showModal(modalNewFolder);
    });

    document.getElementById('btnCancelFolder').addEventListener('click', () => {
        closeModal(modalNewFolder);
    });

    document.getElementById('btnCreateFolder').addEventListener('click', () => {
        const name = newFolderInput.value.trim();
        if (name) {
            createFolder(name);
        }
    });

    newFolderInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const name = newFolderInput.value.trim();
            if (name) createFolder(name);
        }
    });

    document.getElementById('btnCancelRename').addEventListener('click', () => {
        closeModal(modalRename);
        renameCallback = null;
    });

    document.getElementById('btnConfirmRename').addEventListener('click', () => {
        const newName = renameInput.value.trim();
        if (renameCallback && newName) {
            renameCallback(newName);
        }
    });

    renameInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const newName = renameInput.value.trim();
            if (renameCallback && newName) {
                renameCallback(newName);
            }
        }
    });

    document.getElementById('btnBack').addEventListener('click', () => {
        if (currentPath && currentPath !== '') {
            const parts = currentPath.split('/');
            parts.pop();
            const newPath = parts.join('/');
            loadDirectory(newPath);
        }
    });

    document.getElementById('btnDeleteSelected')?.addEventListener('click', deleteSelected);
    document.getElementById('btnDownloadSelected')?.addEventListener('click', downloadSelected);

    fileInput.addEventListener('change', () => {
        uploadFiles(fileInput.files);
        fileInput.value = '';
    });

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('dragover');
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        uploadFiles(e.dataTransfer.files);
    });

    dropzone.addEventListener('click', () => {
        fileInput.click();
    });

    document.getElementById('btnConfirmCancel').addEventListener('click', () => {
        closeModal(modalConfirm);
        confirmCallback = null;
    });

    document.getElementById('btnConfirmOk').addEventListener('click', () => {
        closeModal(modalConfirm);
        if (confirmCallback) {
            confirmCallback();
            confirmCallback = null;
        }
    });

    document.querySelectorAll('.fm-modal-close[data-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.fm-modal');
            closeModal(modal);
        });
    });

    [modalNewFolder, modalConfirm, modalRename].forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(modal);
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal(modalNewFolder);
            closeModal(modalConfirm);
            closeModal(modalRename);
        }
    });

    sortBySelect.addEventListener('change', () => {
        sortBy = sortBySelect.value;
        currentPage = 1;
        loadDirectory(currentPath, currentPage);
    });

    sortOrderSelect.addEventListener('change', () => {
        sortOrder = sortOrderSelect.value;
        currentPage = 1;
        loadDirectory(currentPath, currentPage);
    });

    const themeIcon = document.getElementById('themeIcon');
    const btnTheme = document.getElementById('btnTheme');

    function setTheme(theme) {
        if (theme === 'light') {
            document.body.dataset.theme = 'light';
            themeIcon.className = 'fa-solid fa-sun';
        } else {
            document.body.dataset.theme = '';
            themeIcon.className = 'fa-solid fa-moon';
        }
        localStorage.setItem('fm-theme', theme);
    }

    function toggleTheme() {
        const current = document.body.dataset.theme;
        setTheme(current === 'light' ? 'dark' : 'light');
    }

    btnTheme.addEventListener('click', toggleTheme);

    const savedTheme = localStorage.getItem('fm-theme');
    if (savedTheme) {
        setTheme(savedTheme);
    }

    function goToPage(page) {
        loadDirectory(currentPath, page);
    }

    fm.FM = { goToPage, loadDirectory, clearSelection };

    init();
})();
