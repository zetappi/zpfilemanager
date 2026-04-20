<?php
require_once 'config.php';
require_once 'lang/Language.php';

// Load language
$lang = $_GET['lang'] ?? $_COOKIE['fm_lang'] ?? (defined('FM_DEFAULT_LANGUAGE') ? FM_DEFAULT_LANGUAGE : 'en');
Language::load($lang);

// Set language cookie for 30 days
setcookie('fm_lang', $lang, time() + (30 * 24 * 60 * 60), '/');
?>
<!DOCTYPE html>
<html lang="<?php echo Language::getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZP File Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script>
        // Pass translations to frontend
        window.FM_TRANSLATIONS = <?php echo json_encode(Language::$translations); ?>;
        window.FM_CURRENT_LANG = '<?php echo Language::getCurrentLanguage(); ?>';
        window.FM_AVAILABLE_LANGS = <?php echo json_encode(Language::getAvailableLanguages()); ?>;
    </script>
</head>
<body>
    <div class="fm-wrapper" id="fileManager">
        <div class="fm-container">
            <header class="fm-header">
                <div class="fm-logo">
                    <i class="fa-solid fa-folder-open"></i>
                    <h1><?php echo Language::get('title'); ?></h1>
                </div>
                <div class="fm-header-actions">
                    <div class="fm-search">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="searchInput" placeholder="<?php echo Language::get('search'); ?>" autocomplete="off">
                    </div>
                    <?php if (Language::canSwitchLanguage()): ?>
                    <div class="fm-language-selector">
                        <select id="languageSelect" class="fm-select">
                            <?php foreach (Language::getAvailableLanguages() as $langCode): ?>
                            <option value="<?php echo $langCode; ?>" <?php echo $langCode === Language::getCurrentLanguage() ? 'selected' : ''; ?>>
                                <?php
                                $flags = [
                                    'en' => '🇬🇧',
                                    'it' => '🇮🇹',
                                    'es' => '🇪🇸',
                                    'fr' => '🇫🇷',
                                    'de' => '🇩🇪',
                                    'pt' => '🇵🇹',
                                    'ru' => '🇷🇺',
                                    'nl' => '🇳🇱',
                                    'zh' => '🇨🇳',
                                    'ja' => '🇯🇵',
                                    'ko' => '🇰🇷',
                                ];
                                echo $flags[$langCode] ?? '🌐';
                                ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <button type="button" id="btnTheme" class="fm-btn-icon" title="<?php echo Language::get('theme_' . (isset($_COOKIE['fm_theme']) && $_COOKIE['fm_theme'] === 'light' ? 'light' : 'dark')); ?>">
                        <i class="fa-solid fa-moon" id="themeIcon"></i>
                    </button>
                    <button type="button" id="btnViewToggle" class="fm-btn-icon" title="<?php echo Language::get('view_list'); ?>">
                        <i class="fa-solid fa-list" id="viewIcon"></i>
                    </button>
                    <button type="button" id="btnNewFolder" class="fm-btn fm-btn-primary">
                        <i class="fa-solid fa-folder-plus"></i>
                        <span><?php echo Language::get('new_folder'); ?></span>
                    </button>
                    <label class="fm-btn fm-btn-upload">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span><?php echo Language::get('upload'); ?></span>
                        <input type="file" id="fileInput" multiple hidden>
                    </label>
                </div>
            </header>

            <div class="fm-toolbar" id="toolbar">
                <div class="fm-toolbar-left">
                    <label class="fm-select-all">
                        <input type="checkbox" id="selectAllCheckbox">
                        <span><?php echo Language::get('selection_all'); ?></span>
                    </label>
                    <span class="fm-selection-count">
                        <i class="fa-solid fa-check-square"></i>
                        <span id="selectionCount">0</span> <?php echo Language::get('selection_count', ['count' => 0]); ?>
                    </span>
                </div>
                <div class="fm-toolbar-right">
                    <button type="button" id="btnDownloadSelected" class="fm-btn-icon" title="<?php echo Language::get('toolbar_download'); ?>">
                        <i class="fa-solid fa-download"></i>
                    </button>
                    <button type="button" id="btnDeleteSelected" class="fm-btn fm-btn-danger" title="<?php echo Language::get('toolbar_delete'); ?>">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>

            <nav class="fm-nav">
                <button type="button" id="btnBack" class="fm-btn fm-btn-ghost" title="<?php echo Language::get('directory_superiore'); ?>">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <div class="fm-breadcrumb" id="currentPath">
                    <i class="fa-solid fa-house"></i>
                    <span>/</span>
                </div>
                <div class="fm-nav-spacer"></div>
                <div class="fm-filters">
                    <select id="sortBy" class="fm-select">
                        <option value="name"><?php echo Language::get('sort_name'); ?></option>
                        <option value="size"><?php echo Language::get('sort_size'); ?></option>
                        <option value="modified"><?php echo Language::get('sort_date'); ?></option>
                    </select>
                    <select id="sortOrder" class="fm-select">
                        <option value="asc"><?php echo Language::get('sort_asc'); ?></option>
                        <option value="desc"><?php echo Language::get('sort_desc'); ?></option>
                    </select>
                </div>
            </nav>

            <div class="fm-dropzone" id="dropzone">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <p><?php echo Language::get('dropzone_text'); ?></p>
                <span><?php echo Language::get('dropzone_or'); ?> <?php echo Language::get('dropzone_click'); ?></span>
                <input type="file" id="fileInput" multiple hidden>
            </div>

            <main class="fm-content" id="fileList">
                <div class="fm-loading">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <span><?php echo Language::get('loading'); ?></span>
                </div>
            </main>

            <footer class="fm-pagination" id="pagination"></footer>

            <div class="fm-upload-queue" id="uploadQueue"></div>
        </div>

        <footer class="fm-footer">
            <span><?php echo Language::get('copyright'); ?></span>
            <span><?php echo Language::get('copyright_email'); ?></span>
        </footer>
    </div>

    <div class="fm-modal" id="modalNewFolder">
        <div class="fm-modal-content">
            <div class="fm-modal-header">
                <h2><?php echo Language::get('modal_new_folder_title'); ?></h2>
                <button type="button" class="fm-modal-close" data-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="fm-modal-body">
                <input type="text" id="newFolderName" class="fm-input" placeholder="<?php echo Language::get('modal_new_folder_name'); ?>" autocomplete="off">
            </div>
            <div class="fm-modal-footer">
                <button type="button" class="fm-btn fm-btn-ghost" id="btnCancelFolder"><?php echo Language::get('btn_cancel'); ?></button>
                <button type="button" class="fm-btn fm-btn-primary" id="btnCreateFolder"><?php echo Language::get('btn_confirm'); ?></button>
            </div>
        </div>
    </div>

    <div class="fm-modal" id="modalRename">
        <div class="fm-modal-content">
            <div class="fm-modal-header">
                <h2><?php echo Language::get('modal_rename_title'); ?></h2>
                <button type="button" class="fm-modal-close" data-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="fm-modal-body">
                <input type="text" id="renameInput" class="fm-input" placeholder="<?php echo Language::get('modal_rename_name'); ?>" autocomplete="off">
            </div>
            <div class="fm-modal-footer">
                <button type="button" class="fm-btn fm-btn-ghost" id="btnCancelRename"><?php echo Language::get('btn_cancel'); ?></button>
                <button type="button" class="fm-btn fm-btn-primary" id="btnConfirmRename"><?php echo Language::get('btn_rename'); ?></button>
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

    <div class="fm-modal" id="modalEditor">
        <div class="fm-modal-content fm-modal-lg">
            <div class="fm-modal-header">
                <h3 id="editorTitle"><i class="fa-solid fa-pen-to-square"></i> <?php echo Language::get('editor_title'); ?></h3>
                <button type="button" class="fm-modal-close" data-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="fm-modal-body">
                <textarea id="editorTextarea" class="fm-editor-textarea"></textarea>
            </div>
            <div class="fm-modal-footer">
                <button type="button" class="fm-btn fm-btn-ghost" id="btnCancelEditor"><?php echo Language::get('btn_cancel'); ?></button>
                <button type="button" class="fm-btn fm-btn-primary" id="btnSaveEditor"><?php echo Language::get('btn_save'); ?></button>
            </div>
        </div>
    </div>

    <div class="fm-modal" id="modalAlert">
        <div class="fm-modal-content fm-modal-sm">
            <div class="fm-modal-header">
                <h3 id="alertTitle"><i class="fa-solid fa-circle-info"></i> Info</h3>
                <button type="button" class="fm-modal-close" data-close>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="fm-modal-body">
                <p id="alertMessage"></p>
            </div>
            <div class="fm-modal-footer">
                <button type="button" class="fm-btn fm-btn-primary" id="btnAlertOk">OK</button>
            </div>
        </div>
    </div>

    <script src="filemanager.js"></script>
</body>
</html>
