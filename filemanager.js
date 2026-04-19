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
    let viewMode = 'list'; // 'list' or 'grid'
    let searchQuery = '';
    let translations = window.FM_TRANSLATIONS || {};
    let currentLang = window.FM_CURRENT_LANG || 'en';

    // Translation function
    function t(key, params = {}) {
        let string = translations[key] || key;
        for (const [placeholder, value] of Object.entries(params)) {
            string = string.replace(`{${placeholder}}`, value);
        }
        return string;
    }

    function getBaseUrl() {
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
                reject(new Error(t('error_network')));
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
            currentPathEl.innerHTML = '<i class="fa-solid fa-house fm-breadcrumb-home"></i>';
            currentPathEl.querySelector('.fm-breadcrumb-home').addEventListener('click', () => {
                loadDirectory('');
            });
            return;
        }
        
        const parts = path.split('/');
        let html = '<i class="fa-solid fa-house fm-breadcrumb-home"></i>';
        let accumulated = '';
        
        parts.forEach((part, i) => {
            accumulated += (i > 0 ? '/' : '') + part;
            html += `<i class="fa-solid fa-chevron-right fm-breadcrumb-separator"></i>`;
            html += `<span class="fm-breadcrumb-item" data-path="${escapeHtml(accumulated)}"><i class="fa-solid fa-folder fm-breadcrumb-folder"></i>${escapeHtml(part)}</span>`;
        });
        
        currentPathEl.innerHTML = html;
        
        currentPathEl.querySelector('.fm-breadcrumb-home').addEventListener('click', () => {
            loadDirectory('');
        });
        
        currentPathEl.querySelectorAll('.fm-breadcrumb-item').forEach(item => {
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
            fileList.innerHTML = '<div class="fm-empty"><i class="fa-solid fa-folder-xmark"></i><span>' + t('msg_error_loading') + '</span></div>';
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
        fileList.innerHTML = '<div class="fm-loading"><i class="fa-solid fa-spinner fa-spin"></i><span>' + t('loading') + '</span></div>';
        
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
            fileList.innerHTML = '<div class="fm-empty"><i class="fa-solid fa-folder-xmark"></i><span>' + t('msg_error_dir') + '</span></div>';
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
        
        html += `<span class="fm-page-info">${pagination.total_items} ${t('pagination_items')} - ${t('pagination_page')} ${pagination.page}/${pagination.total_pages}</span>`;
        
        paginationEl.innerHTML = html;
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
        document.querySelectorAll('.fm-item.selected').forEach(el => {
            el.classList.remove('selected');
            const checkbox = el.querySelector('.fm-item-checkbox');
            if (checkbox) checkbox.checked = false;
        });
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
        updateToolbar();
    }

    function renderFileList(data) {
        if (!data.items || data.items.length === 0) {
            fileList.innerHTML = '<div class="fm-empty"><i class="fa-solid fa-folder-open"></i><span>' + t('empty_folder') + '</span></div>';
            return;
        }

        currentItems = data.items;

        // Apply search filter
        let filteredItems = data.items;
        if (searchQuery.trim()) {
            const query = searchQuery.toLowerCase();
            filteredItems = data.items.filter(item => 
                item.name.toLowerCase().includes(query)
            );
        }

        if (filteredItems.length === 0) {
            fileList.innerHTML = '<div class="fm-empty"><i class="fa-solid fa-folder-open"></i><span>' + t('no_results') + '</span></div>';
            return;
        }

        // Apply view mode
        if (viewMode === 'grid') {
            fileList.classList.add('grid-view');
        } else {
            fileList.classList.remove('grid-view');
        }

        let html = '';
        filteredItems.forEach((item, index) => {
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
                            <span><i class="fa-solid ${item.is_dir ? 'fa-folder' : 'fa-file-lines'}"></i> ${item.is_dir ? t('type_folder') : size}</span>
                            <span><i class="fa-regular fa-clock"></i> ${modified}</span>
                        </div>
                    </div>
                    ${downloadLink ? `<a href="${downloadLink}" class="fm-item-download" target="_blank" title="${t('btn_download')}"><i class="fa-solid fa-download"></i></a>` : ''}
                    <div class="fm-item-actions">
                        <button type="button" class="fm-item-action btn-rename" title="${t('btn_rename')}"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="fm-item-action btn-delete" title="${t('btn_delete')}"><i class="fa-solid fa-trash"></i></button>
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
                const itemName = item.querySelector('.fm-item-name').textContent;
                const message = isDir 
                    ? t('modal_confirm_folder', {item: itemName})
                    : t('modal_confirm_message', {item: itemName});
                showConfirm(
                    '<i class="fa-solid fa-trash"></i> ' + t('modal_confirm_title'),
                    message,
                    async () => {
                        const success = await deleteItem(path, isDir);
                        if (success) {
                            loadDirectory(currentPath);
                        }
                    }
                );
            });
            
            item.querySelector('.btn-rename')?.addEventListener('click', (e) => {
                e.stopPropagation();
                renameItem(path, item.querySelector('.fm-item-name').textContent, isDir);
            });
        });
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

    async function deleteSelected() {
        if (selectedItems.size === 0) return;
        
        const count = selectedItems.size;
        showConfirm(
            '<i class="fa-solid fa-trash"></i> ' + t('modal_confirm_title'),
            t('modal_confirm_multiple', {count: count}),
            async () => {
                for (const path of selectedItems) {
                    const item = currentItems.find(i => i.path === path);
                    if (item) {
                        await deleteItem(path, item.is_dir);
                    }
                }
                clearSelection();
                loadDirectory(currentPath, currentPage);
            }
        );
    }

    async function deleteItem(path, isDir) {
        try {
            const response = await ajax(getBaseUrl(), { action: 'delete', path: path, base_path: rootPath }, 'POST');
            if (response.success) {
                // Success - don't reload here, let caller handle it
                return true;
            } else {
                alert(t('error_generic') + ': ' + (response.error || t('msg_delete_error')));
                return false;
            }
        } catch (e) {
            alert(t('error_network'));
            return false;
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
                alert(t('error_generic') + ': ' + (response.error || t('msg_folder_error')));
            }
        } catch (e) {
            alert(t('error_network'));
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
                    <span class="fm-upload-status">${t('loading')}</span>
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
                    el.querySelector('.fm-upload-status').textContent = t('upload_completed');
                    el.querySelector('i').className = 'fa-solid fa-check';
                } else {
                    el.classList.add('error');
                    el.querySelector('.fm-upload-status').textContent = response.errors?.[0] || t('error_generic');
                    el.querySelector('i').className = 'fa-solid fa-xmark';
                }
                
                setTimeout(() => el.remove(), 3000);
            } catch (e) {
                const el = document.getElementById(itemId);
                if (el) {
                    el.classList.add('error');
                    el.querySelector('.fm-upload-status').textContent = t('error_generic');
                    el.querySelector('i').className = 'fa-solid fa-xmark';
                    setTimeout(() => el.remove(), 3000);
                }
            }
        }
        
        // Reset file input after all uploads complete
        fileInput.value = '';
        
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
                    alert(t('error_generic') + ': ' + (response.error || t('msg_rename_error')));
                }
            } catch (e) {
                alert(t('error_network'));
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

    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            const checkboxes = document.querySelectorAll('.fm-item-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
                const item = checkbox.closest('.fm-item');
                if (isChecked) {
                    item.classList.add('selected');
                    selectedItems.add(item.dataset.path);
                } else {
                    item.classList.remove('selected');
                    selectedItems.delete(item.dataset.path);
                }
            });
            
            updateToolbar();
        });
    }

    fileInput.addEventListener('change', () => {
        uploadFiles(fileInput.files);
        // Reset after upload completes (handled in uploadFiles)
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

    // View toggle functionality
    function toggleViewMode() {
        viewMode = viewMode === 'list' ? 'grid' : 'list';
        const viewIcon = document.getElementById('viewIcon');
        
        if (viewMode === 'grid') {
            viewIcon.className = 'fa-solid fa-th-large';
            fileList.classList.add('grid-view');
        } else {
            viewIcon.className = 'fa-solid fa-list';
            fileList.classList.remove('grid-view');
        }
        
        localStorage.setItem('fm-view-mode', viewMode);
        renderFileList({ items: currentItems });
    }

    const btnViewToggle = document.getElementById('btnViewToggle');
    if (btnViewToggle) {
        btnViewToggle.addEventListener('click', toggleViewMode);
    }

    // Load saved view mode
    const savedViewMode = localStorage.getItem('fm-view-mode');
    if (savedViewMode === 'grid') {
        viewMode = 'grid';
        const viewIcon = document.getElementById('viewIcon');
        if (viewIcon) {
            viewIcon.className = 'fa-solid fa-th-large';
        }
    }

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            renderFileList({ items: currentItems });
        });
    }

    // Language switch functionality
    const languageSelect = document.getElementById('languageSelect');
    if (languageSelect) {
        languageSelect.addEventListener('change', (e) => {
            const newLang = e.target.value;
            // Reload page with new language
            window.location.href = window.location.pathname + '?lang=' + newLang;
        });
    }

    function goToPage(page) {
        loadDirectory(currentPath, page);
    }

    fm.FM = { goToPage, loadDirectory, clearSelection, toggleViewMode };

    init();
})();
