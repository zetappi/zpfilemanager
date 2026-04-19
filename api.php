<?php
// Load configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/FileManagerHelper.php';
require_once __DIR__ . '/lang/Language.php';

// Load language
$lang = $_GET['lang'] ?? $_COOKIE['fm_lang'] ?? (defined('FM_DEFAULT_LANGUAGE') ? FM_DEFAULT_LANGUAGE : 'en');
Language::load($lang);

header('Content-Type: application/json; charset=utf-8');

// CORS handling based on configuration
$allowedOrigins = defined('FM_CORS_ALLOWED_ORIGINS') ? FM_CORS_ALLOWED_ORIGINS : [];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array('*', $allowedOrigins)) {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if (defined('FM_CORS_ALLOW_CREDENTIALS') && FM_CORS_ALLOW_CREDENTIALS) {
    header('Access-Control-Allow-Credentials: true');
}

require_once __DIR__.'/auth.php';

// Rate limiting based on configuration
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimit = defined('FM_RATE_LIMIT') ? FM_RATE_LIMIT : 30;
$rateLimitWindow = defined('FM_RATE_LIMIT_WINDOW') ? FM_RATE_LIMIT_WINDOW : 60;
$rateLimitFile = __DIR__ . '/logs/ratelimit_' . md5($ip) . '.json';
$currentTime = time();
$rateLimitData = [];

if (file_exists($rateLimitFile)) {
    $rateLimitData = json_decode(@file_get_contents($rateLimitFile), true) ?: [];
}

// Reset if older than window
if (!isset($rateLimitData['start']) || ($currentTime - $rateLimitData['start'] > $rateLimitWindow)) {
    $rateLimitData = ['count' => 0, 'start' => $currentTime];
}

if ($rateLimitData['count'] >= $rateLimit) {
    http_response_code(429);
    echo json_encode(['success'=>false,'error'=>'Too many requests']);
    exit;
}

$rateLimitData['count']++;
@file_put_contents($rateLimitFile, json_encode($rateLimitData));

// CSRF Protection: generate and validate token for POST requests
$enableCsrf = defined('FM_ENABLE_CSRF') ? FM_ENABLE_CSRF : true;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $enableCsrf) {
    if (!isset($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            error_log('CSRF token generation failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Server configuration error']);
            exit;
        }
    }
    
    $providedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!hash_equals($_SESSION['csrf_token'], $providedToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF token validation failed']);
        exit;
    }
}


$scriptDir = dirname(__FILE__);

$inputBase = null;
if (isset($_GET['base_path']) && $_GET['base_path'] !== '') {
    $inputBase = $_GET['base_path'];
} elseif (isset($_POST['base_path']) && $_POST['base_path'] !== '') {
    $inputBase = $_POST['base_path'];
}

$defaultBase = defined('FM_DEFAULT_BASE_PATH') ? FM_DEFAULT_BASE_PATH : $scriptDir . '/uploads';
$allowedBaseDir = defined('FM_ALLOWED_BASE_DIR') ? FM_ALLOWED_BASE_DIR : $scriptDir . '/uploads';

// Ensure default base directory exists
if (!FileManagerHelper::ensureDirectory($defaultBase, defined('FM_DIR_PERMISSIONS') ? FM_DIR_PERMISSIONS : 0755)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => Language::get('msg_folder_error')]);
    exit;
}

$basePath = $inputBase !== null ? rtrim($inputBase, '/\\') : $defaultBase;
// Normalize and ensure base path stays within allowed directory
$basePathReal = realpath($basePath);
if ($basePathReal === false || !FileManagerHelper::isPathWithinBase($basePathReal, $allowedBaseDir)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => Language::get('msg_access_denied')]);
    exit;
}


$currentPathGet = isset($_GET['path']) ? $_GET['path'] : '';
$currentPathPost = isset($_POST['path']) ? $_POST['path'] : '';
$currentPath = $currentPathGet !== '' ? $currentPathGet : $currentPathPost;
$currentPath = trim($currentPath, '/\\');

if (!FileManagerHelper::isPathSafe($currentPath)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => Language::get('msg_access_denied')]);
    exit;
}

$fullPath = $currentPath !== '' ? $basePath . '/' . $currentPath : $basePath;

$fullPathReal = realpath($fullPath);
$basePathReal = realpath($basePath);

if ($basePathReal === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => Language::get('msg_folder_error')]);
    exit;
}

if ($fullPathReal === false) {
    // Directory doesn't exist, but we'll handle this per action
}

if ($fullPathReal !== false && !FileManagerHelper::isPathWithinBase($fullPathReal, $basePathReal)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => Language::get('msg_access_denied')]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_csrf_token':
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        echo json_encode(['success' => true, 'csrf_token' => $_SESSION['csrf_token']]);
        break;
    case 'list':
            // Log action
            if (defined('FM_ENABLE_LOGGING') && FM_ENABLE_LOGGING) {
                $logFile = defined('FM_LOG_FILE') ? FM_LOG_FILE : __DIR__ . '/logs/filemanager.log';
                $safePath = FileManagerHelper::sanitizeLog($currentPath);
                $safeIp = FileManagerHelper::sanitizeLog($_SERVER['REMOTE_ADDR'] ?? '');
                $logLine = sprintf("%s | LIST | %s | %s", date('Y-m-d H:i:s'), $safePath, $safeIp);
                FileManagerHelper::log($logLine, $logFile);
            }
        $items = [];
        $listPath = $fullPathReal ?: $fullPath;
        $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'name';
        $sortOrder = isset($_GET['sort_order']) ? strtolower($_GET['sort_order']) : 'asc';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : (defined('FM_ITEMS_PER_PAGE') ? FM_ITEMS_PER_PAGE : 10);
        $maxPerPage = defined('FM_MAX_ITEMS_PER_PAGE') ? FM_MAX_ITEMS_PER_PAGE : 100;
        $perPage = min($perPage, $maxPerPage);
        
        $showHidden = defined('FM_SHOW_HIDDEN_FILES') ? FM_SHOW_HIDDEN_FILES : false;
        
        if (is_dir($listPath)) {
            $entries = @scandir($listPath);
            if ($entries === false) {
                echo json_encode(['success' => false, 'error' => 'Impossibile leggere directory']);
                break;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                if (!$showHidden && strpos($entry, '.') === 0) continue;
                
                $itemPath = $listPath . '/' . $entry;
                $isDir = is_dir($itemPath);
                
                $items[] = [
                    'name' => $entry,
                    'path' => FileManagerHelper::relativePath($itemPath, $basePathReal ?: $basePath),
                    'is_dir' => $isDir,
                    'size' => $isDir ? null : filesize($itemPath),
                    'size_fmt' => $isDir ? null : FileManagerHelper::formatSize(filesize($itemPath)),
                    'modified' => filemtime($itemPath),
                    'modified_fmt' => date('d/m/Y H:i', filemtime($itemPath))
                ];
            }
        }
        
        usort($items, function($a, $b) use ($sortBy, $sortOrder) {
            $foldersFirst = $b['is_dir'] - $a['is_dir'];
            if ($foldersFirst !== 0) return $foldersFirst;
            
            $cmp = 0;
            switch ($sortBy) {
                case 'size':
                    $cmp = ($a['size'] ?? 0) - ($b['size'] ?? 0);
                    break;
                case 'modified':
                    $cmp = $a['modified'] - $b['modified'];
                    break;
                default:
                    $cmp = strcasecmp($a['name'], $b['name']);
            }
            
            return $sortOrder === 'desc' ? -$cmp : $cmp;
        });
        
        $totalItems = count($items);
        $totalPages = ceil($totalItems / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedItems = array_slice($items, $offset, $perPage);
        
        echo json_encode([
            'success' => true,
            'path' => FileManagerHelper::relativePath($fullPathReal ?: $fullPath, $basePathReal ?: $basePath),
            'full_base_path' => '', // Don't send absolute path, use relative for portability
            'items' => $paginatedItems,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages
            ]
        ]);
        break;

    case 'create_folder':
            // Log action
            if (defined('FM_ENABLE_LOGGING') && FM_ENABLE_LOGGING) {
                $logFile = defined('FM_LOG_FILE') ? FM_LOG_FILE : __DIR__ . '/logs/filemanager.log';
                $safePath = FileManagerHelper::sanitizeLog($currentPath);
                $safeIp = FileManagerHelper::sanitizeLog($_SERVER['REMOTE_ADDR'] ?? '');
                $logLine = sprintf("%s | CREATE_FOLDER | %s | %s", date('Y-m-d H:i:s'), $safePath, $safeIp);
                FileManagerHelper::log($logLine, $logFile);
            }
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        
        if (empty($name) || preg_match('/[\/\\\\:*?"<>|]/', $name)) {
            echo json_encode(['success' => false, 'error' => 'Nome cartella non valido']);
            exit;
        }
        
        $newPath = ($fullPathReal ?: $fullPath) . '/' . $name;
        
        if (file_exists($newPath)) {
            echo json_encode(['success' => false, 'error' => 'Cartella già esistente']);
            exit;
        }
        
        $dirPerms = defined('FM_DIR_PERMISSIONS') ? FM_DIR_PERMISSIONS : 0750;
        if (@mkdir($newPath, $dirPerms, false)) {
            echo json_encode([
                'success' => true,
                'item' => [
                    'name' => $name,
                    'path' => FileManagerHelper::relativePath($newPath, $basePathReal ?: $basePath),
                    'is_dir' => true,
                    'size' => null,
                    'modified' => date('d/m/Y H:i')
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Errore creazione cartella. Verifica i permessi.']);
        }
        break;

    case 'delete':
            // Log action
            if (defined('FM_ENABLE_LOGGING') && FM_ENABLE_LOGGING) {
                $logFile = defined('FM_LOG_FILE') ? FM_LOG_FILE : __DIR__ . '/logs/filemanager.log';
                $safePath = FileManagerHelper::sanitizeLog($path ?? '');
                $safeIp = FileManagerHelper::sanitizeLog($_SERVER['REMOTE_ADDR'] ?? '');
                $logLine = sprintf("%s | DELETE | %s | %s", date('Y-m-d H:i:s'), $safePath, $safeIp);
                FileManagerHelper::log($logLine, $logFile);
            }
        $path = isset($_POST['path']) ? trim($_POST['path'], '/\\') : '';
        
        if (empty($path)) {
            echo json_encode(['success' => false, 'error' => Language::get('msg_path_required')]);
            exit;
        }
        
        $targetPath = ($basePathReal ?: $basePath) . '/' . $path;
        $targetReal = realpath($targetPath);
        
        if ($targetReal === false) {
            $targetReal = $targetPath;
        }
        
        $base = $basePathReal ?: $basePath;
        if (!FileManagerHelper::isPathWithinBase($targetReal, $base)) {
            echo json_encode(['success' => false, 'error' => Language::get('msg_access_denied')]);
            exit;
        }
        
        $allowDeleteNonEmpty = defined('FM_ALLOW_DELETE_NON_EMPTY') ? FM_ALLOW_DELETE_NON_EMPTY : true;
        if (!$allowDeleteNonEmpty && is_dir($targetReal)) {
            $items = @scandir($targetReal);
            if ($items && count($items) > 2) { // . and .. always present
                echo json_encode(['success' => false, 'error' => 'Eliminazione directory non vuote non permessa']);
                exit;
            }
        }
        
        if (FileManagerHelper::deleteRecursive($targetReal)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => Language::get('msg_delete_error')]);
        }
        break;

    case 'rename':
            // Log action
            if (defined('FM_ENABLE_LOGGING') && FM_ENABLE_LOGGING) {
                $logFile = defined('FM_LOG_FILE') ? FM_LOG_FILE : __DIR__ . '/logs/filemanager.log';
                $safePath = FileManagerHelper::sanitizeLog($path ?? '');
                $safeNewName = FileManagerHelper::sanitizeLog($newName ?? '');
                $safeIp = FileManagerHelper::sanitizeLog($_SERVER['REMOTE_ADDR'] ?? '');
                $logLine = sprintf("%s | RENAME | %s -> %s | %s", date('Y-m-d H:i:s'), $safePath, $safeNewName, $safeIp);
                FileManagerHelper::log($logLine, $logFile);
            }
        $path = isset($_POST['path']) ? trim($_POST['path'], '/\\') : '';
        $newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
        
        if (empty($path) || empty($newName)) {
            echo json_encode(['success' => false, 'error' => 'Path e nuovo nome richiesti']);
            exit;
        }
        
        if (preg_match('/[\/\\\\:*?"<>|]/', $newName)) {
            echo json_encode(['success' => false, 'error' => 'Nome non valido']);
            exit;
        }
        
        $targetPath = ($basePathReal ?: $basePath) . '/' . $path;
        $targetReal = realpath($targetPath);
        
        if ($targetReal === false) {
            $targetReal = $targetPath;
        }
        
        $base = $basePathReal ?: $basePath;
        if (!FileManagerHelper::isPathWithinBase($targetReal, $base)) {
            echo json_encode(['success' => false, 'error' => Language::get('msg_access_denied')]);
            exit;
        }
        
        $dirName = dirname($targetReal);
        $newPath = $dirName . '/' . $newName;
        
        if (file_exists($newPath)) {
            echo json_encode(['success' => false, 'error' => Language::get('msg_exists')]);
            exit;
        }
        
        if (@rename($targetReal, $newPath)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => Language::get('msg_rename_error')]);
        }
        break;

    case 'upload':
            // Log action start
            if (defined('FM_ENABLE_LOGGING') && FM_ENABLE_LOGGING) {
                $logFile = defined('FM_LOG_FILE') ? FM_LOG_FILE : __DIR__ . '/logs/filemanager.log';
                $safePath = FileManagerHelper::sanitizeLog($currentPath);
                $safeIp = FileManagerHelper::sanitizeLog($_SERVER['REMOTE_ADDR'] ?? '');
                $logStart = sprintf("%s | UPLOAD_START | %s | %s", date('Y-m-d H:i:s'), $safePath, $safeIp);
                FileManagerHelper::log($logStart, $logFile);
            }
        if (empty($_FILES) || !isset($_FILES['files'])) {
            echo json_encode(['success' => false, 'error' => Language::get('msg_no_file')]);
            exit;
        }
        
        $uploaded = [];
        $errors = [];
        $targetDir = $fullPathReal ?: $fullPath;
        
        if (!is_dir($targetDir)) {
            echo json_encode(['success' => false, 'error' => Language::get('msg_folder_error')]);
            exit;
        }
        
        $allowedExtensions = defined('FM_ALLOWED_EXTENSIONS') ? FM_ALLOWED_EXTENSIONS : [];
        $maxFileSize = defined('FM_MAX_FILE_SIZE') ? FM_MAX_FILE_SIZE : 0;
        $filePerms = defined('FM_FILE_PERMISSIONS') ? FM_FILE_PERMISSIONS : 0640;
        $autoRename = defined('FM_AUTO_RENAME_ON_CONFLICT') ? FM_AUTO_RENAME_ON_CONFLICT : true;
        
        $files = $_FILES['files'];
        $count = is_array($files['name']) ? count($files['name']) : 1;
        
        for ($i = 0; $i < $count; $i++) {
            $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = $name . ': ' . Language::get('msg_upload_error') . ' (' . $error . ')';
                continue;
            }
            
            // Check file size
            if ($maxFileSize > 0 && filesize($tmpName) > $maxFileSize) {
                $errors[] = $name . ': ' . Language::get('msg_file_too_large', ['size' => FileManagerHelper::formatSize($maxFileSize)]);
                continue;
            }
            
            $safeName = FileManagerHelper::sanitizeFilenameForStorage($name);
            
            // Validate file extension against whitelist
            if (!FileManagerHelper::isExtensionAllowed($name, $allowedExtensions)) {
                $errors[] = $name . ': ' . Language::get('msg_file_not_allowed');
                continue;
            }
            
            // Generate unique filename if enabled
            if ($autoRename) {
                $safeName = FileManagerHelper::generateUniqueFilename($targetDir, $safeName);
            }
            
            $targetFile = $targetDir . '/' . $safeName;
            
            $success = false;
            
            if (is_uploaded_file($tmpName)) {
                $success = @move_uploaded_file($tmpName, $targetFile);
            }
            
            if ($success) {
                @chmod($targetFile, $filePerms);
                $uploaded[] = [
                    'name' => basename($targetFile),
                    'path' => FileManagerHelper::relativePath($targetFile, $basePathReal ?: $basePath),
                    'size' => FileManagerHelper::formatSize(filesize($targetFile))
                ];
            } else {
                $errors[] = $name . ': ' . Language::get('msg_upload_error');
            }
        }
        
        echo json_encode([
            'success' => count($uploaded) > 0,
            'uploaded' => $uploaded,
            'errors' => $errors
        ]);
        break;

    case 'download':
            // Log download action
            if (defined('FM_ENABLE_LOGGING') && FM_ENABLE_LOGGING) {
                $logFile = defined('FM_LOG_FILE') ? FM_LOG_FILE : __DIR__ . '/logs/filemanager.log';
                $safePath = FileManagerHelper::sanitizeLog($path ?? '');
                $safeIp = FileManagerHelper::sanitizeLog($_SERVER['REMOTE_ADDR'] ?? '');
                $logLine = sprintf("%s | DOWNLOAD | %s | %s", date('Y-m-d H:i:s'), $safePath, $safeIp);
                FileManagerHelper::log($logLine, $logFile);
            }
        $path = isset($_GET['path']) ? trim($_GET['path'], '/\\') : '';
        
        if (empty($path)) {
            http_response_code(400);
            echo Language::get('msg_path_required');
            exit;
        }
        
        $targetPath = ($basePathReal ?: $basePath) . '/' . $path;
        $targetReal = realpath($targetPath);
        
        if ($targetReal === false) {
            http_response_code(404);
            echo Language::get('msg_not_found');
            exit;
        }
        
        $base = str_replace('\\', '/', $basePathReal ?: $basePath);
        $targetStr = str_replace('\\', '/', $targetReal);
        
        if (strpos($targetStr, $base) !== 0) {
            http_response_code(403);
            echo Language::get('msg_access_denied');
            exit;
        }
        
        if (is_dir($targetReal)) {
            http_response_code(400);
            echo Language::get('msg_not_file');
            exit;
        }
        
        $filename = basename($targetReal);
        $filename = FileManagerHelper::sanitizeFilename($filename);
        $filesize = filesize($targetReal);
        $mimetype = FileManagerHelper::getMimeType($filename);
        
        header('Content-Type: ' . $mimetype);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache');
        
        @readfile($targetReal);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Azione non riconosciuta: ' . $action]);
}
