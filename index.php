<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZP File Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="fm-wrapper" id="fileManager">
        <div class="fm-container">
            <header class="fm-header">
                <div class="fm-logo">
                    <i class="fa-solid fa-folder-open"></i>
                    <h1>ZP File Manager</h1>
                </div>
                <div class="fm-header-actions">
                    <div class="fm-search">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Cerca file..." autocomplete="off">
                    </div>
                    <button type="button" id="btnTheme" class="fm-btn-icon" title="Cambia tema">
                        <i class="fa-solid fa-moon" id="themeIcon"></i>
                    </button>
                    <button type="button" id="btnViewToggle" class="fm-btn-icon" title="Cambia vista">
                        <i class="fa-solid fa-list" id="viewIcon"></i>
                    </button>
                    <button type="button" id="btnNewFolder" class="fm-btn fm-btn-primary">
                        <i class="fa-solid fa-folder-plus"></i>
                        <span>Nuova Cartella</span>
                    </button>
                    <label class="fm-btn fm-btn-upload">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Upload</span>
                        <input type="file" id="fileInput" multiple hidden>
                    </label>
                </div>
            </header>

            <div class="fm-toolbar" id="toolbar">
                <div class="fm-toolbar-left">
                    <span class="fm-selection-count">
                        <i class="fa-solid fa-check-square"></i>
                        <span id="selectionCount">0</span> selezionati
                    </span>
                </div>
                <div class="fm-toolbar-right">
                    <button type="button" class="fm-btn-icon" id="btnDownloadSelected" title="Scarica">
                        <i class="fa-solid fa-download"></i>
                    </button>
                    <button type="button" class="fm-btn-icon fm-btn-danger" id="btnDeleteSelected" title="Elimina">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>

            <nav class="fm-nav">
                <button type="button" id="btnBack" class="fm-btn fm-btn-ghost" title="Directory superiore">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <div class="fm-breadcrumb" id="currentPath">
                    <i class="fa-solid fa-house"></i>
                    <span>/</span>
                </div>
                <div class="fm-nav-spacer"></div>
                <div class="fm-filters">
                    <select id="sortBy" class="fm-select">
                        <option value="name">Nome</option>
                        <option value="size">Dimensione</option>
                        <option value="modified">Data</option>
                    </select>
                    <select id="sortOrder" class="fm-select">
                        <option value="asc">↑ Cresc.</option>
                        <option value="desc">↓ Disc.</option>
                    </select>
                </div>
            </nav>

            <div class="fm-dropzone" id="dropzone">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <p>Trascina i file qui</p>
                <span>oppure clicca per selezionare</span>
            </div>

            <main class="fm-content" id="fileList">
                <div class="fm-loading">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <span>Caricamento...</span>
                </div>
            </main>

            <footer class="fm-pagination" id="pagination"></footer>

            <div class="fm-upload-queue" id="uploadQueue"></div>
        </div>

        <footer class="fm-footer">
            <span>-- ZP File Manager --</span>
            <span>Copyright 2026 marcozp@gmail.com</span>
        </footer>
    </div>

    <div class="fm-modal" id="modalNewFolder">
        <div class="fm-modal-content">
            <div class="fm-modal-header">
                <h3><i class="fa-solid fa-folder-plus"></i> Nuova Cartella</h3>
                <button type="button" class="fm-modal-close" data-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="fm-modal-body">
                <input type="text" id="newFolderName" class="fm-input" placeholder="Nome cartella" autocomplete="off">
            </div>
            <div class="fm-modal-footer">
                <button type="button" class="fm-btn fm-btn-ghost" id="btnCancelFolder">Annulla</button>
                <button type="button" class="fm-btn fm-btn-primary" id="btnCreateFolder">Crea</button>
            </div>
        </div>
    </div>

    <div class="fm-modal" id="modalRename">
        <div class="fm-modal-content">
            <div class="fm-modal-header">
                <h3><i class="fa-solid fa-pen"></i> Rinomina</h3>
                <button type="button" class="fm-modal-close" data-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="fm-modal-body">
                <input type="text" id="renameInput" class="fm-input" placeholder="Nuovo nome" autocomplete="off">
            </div>
            <div class="fm-modal-footer">
                <button type="button" class="fm-btn fm-btn-ghost" id="btnCancelRename">Annulla</button>
                <button type="button" class="fm-btn fm-btn-primary" id="btnConfirmRename">Rinomina</button>
            </div>
        </div>
    </div>

    <div class="fm-modal" id="modalConfirm">
        <div class="fm-modal-content fm-modal-sm">
            <div class="fm-modal-header">
                <h3 id="confirmTitle"><i class="fa-solid fa-circle-question"></i> Conferma</h3>
                <button type="button" class="fm-modal-close" data-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="fm-modal-body">
                <p id="confirmMessage"></p>
            </div>
            <div class="fm-modal-footer">
                <button type="button" class="fm-btn fm-btn-ghost" id="btnConfirmCancel">Annulla</button>
                <button type="button" class="fm-btn fm-btn-danger" id="btnConfirmOk">Conferma</button>
            </div>
        </div>
    </div>

    <script src="filemanager.js"></script>
</body>
</html>
