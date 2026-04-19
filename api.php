<?php
header('Content-Type: application/json; charset=utf-8');
// CORS handling – allow only whitelisted origins
$allowedOrigins = ['https://example.com', 'https://myapp.it']; // TODO: adjust as needed
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__.'/auth.php';
// Simple rate limiting: max 30 requests per minute per IP (stored in session)
if (!isset($_SESSION['rl'])) { $_SESSION['rl'] = ['count'=>0,'start'=>time()]; }
if (time() - $_SESSION['rl']['start'] > 60) { $_SESSION['rl']['count']=0; $_SESSION['rl']['start']=time(); }
if ($_SESSION['rl']['count'] >= 30) { http_response_code(429); echo json_encode(['success'=>false,'error'=>'Too many requests']); exit; }
$_SESSION['rl']['count']++;


$scriptDir = dirname(__FILE__);

$inputBase = null;
if (isset($_GET['base_path']) && $_GET['base_path'] !== '') {
    $inputBase = $_GET['base_path'];
} elseif (isset($_POST['base_path']) && $_POST['base_path'] !== '') {
    $inputBase = $_POST['base_path'];
}

$defaultBase = $scriptDir . '/uploads';
if (!is_dir($defaultBase)) {
    @mkdir($defaultBase, 0755, true);
}

$basePath = $inputBase !== null ? rtrim($inputBase, '/\\') : $defaultBase;
// Normalize and ensure base path stays within allowed directory
$basePathReal = realpath($basePath);
if ($basePathReal === false || strpos(str_replace('\\','/', $basePathReal), str_replace('\\','/', $scriptDir . '/uploads')) !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Base path non consentito']);
    exit;
}


$currentPathGet = isset($_GET['path']) ? $_GET['path'] : '';
$currentPathPost = isset($_POST['path']) ? $_POST['path'] : '';
$currentPath = $currentPathGet !== '' ? $currentPathGet : $currentPathPost;
$currentPath = trim($currentPath, '/\\');

if (strpos($currentPath, '..') !== false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

$fullPath = $currentPath !== '' ? $basePath . '/' . $currentPath : $basePath;

$fullPathReal = realpath($fullPath);
$basePathReal = realpath($basePath);

if ($fullPathReal === false) {
    $fullPathReal = $fullPath;
}
if ($basePathReal === false) {
    $basePathReal = $basePath;
}

if ($basePathReal !== false && $fullPathReal !== false) {
    $fullPathReal = str_replace('\\', '/', $fullPathReal);
    $basePathReal = str_replace('\\', '/', $basePathReal);
    if (strpos($fullPathReal, $basePathReal) !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accesso negato: fuori dalla directory base']);
        exit;
    }
}

function relPath($full, $base) {
    $full = str_replace('\\', '/', $full);
    $base = str_replace('\\', '/', $base);
    if (strpos($full, $base) === 0) {
        return ltrim(substr($full, strlen($base)), '/');
    }
    return $full;
}

function fmtSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
            // Log action
            $logLine = sprintf("%s | LIST | %s | %s\n", date('Y-m-d H:i:s'), $currentPath, $_SERVER['REMOTE_ADDR'] ?? '');
            file_put_contents(__DIR__ . '/logs/filemanager.log', $logLine, FILE_APPEND);
        $items = [];
        $listPath = $fullPathReal ?: $fullPath;
        $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'name';
        $sortOrder = isset($_GET['sort_order']) ? strtolower($_GET['sort_order']) : 'asc';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
        
        if (is_dir($listPath)) {
            $entries = scandir($listPath);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                
                $itemPath = $listPath . '/' . $entry;
                $isDir = is_dir($itemPath);
                
                $items[] = [
                    'name' => $entry,
                    'path' => relPath($itemPath, $basePathReal ?: $basePath),
                    'is_dir' => $isDir,
                    'size' => $isDir ? null : filesize($itemPath),
                    'size_fmt' => $isDir ? null : fmtSize(filesize($itemPath)),
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
                    $cmp = $a['size'] - $b['size'];
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
            'path' => relPath($fullPathReal ?: $fullPath, $basePathReal ?: $basePath),
            'full_base_path' => $basePathReal ?: $basePath,
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
            $logLine = sprintf("%s | CREATE_FOLDER | %s | %s\n", date('Y-m-d H:i:s'), $currentPath, $_SERVER['REMOTE_ADDR'] ?? '');
            file_put_contents(__DIR__ . '/logs/filemanager.log', $logLine, FILE_APPEND);
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
        
        if (mkdir($newPath, 0750, false)) {
            echo json_encode([
                'success' => true,
                'item' => [
                    'name' => $name,
                    'path' => relPath($newPath, $basePathReal ?: $basePath),
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
            $logLine = sprintf("%s | DELETE | %s | %s\n", date('Y-m-d H:i:s'), $path ?? '', $_SERVER['REMOTE_ADDR'] ?? '');
            file_put_contents(__DIR__ . '/logs/filemanager.log', $logLine, FILE_APPEND);
        $path = isset($_POST['path']) ? trim($_POST['path'], '/\\') : '';
        
        if (empty($path)) {
            echo json_encode(['success' => false, 'error' => 'Path richiesto']);
            exit;
        }
        
        $targetPath = ($basePathReal ?: $basePath) . '/' . $path;
        $targetReal = realpath($targetPath);
        
        if ($targetReal === false) {
            $targetReal = $targetPath;
        }
        
        $base = $basePathReal ?: $basePath;
        if (strpos(str_replace('\\', '/', $targetReal), str_replace('\\', '/', $base)) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Accesso negato']);
            exit;
        }
        
        function delRecursive($p) {
            if (is_dir($p)) {
                $items = scandir($p);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    delRecursive($p . '/' . $item);
                }
                return rmdir($p);
            }
            return unlink($p);
        }
        
        if (delRecursive($targetReal)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Errore eliminazione. Verifica i permessi.']);
        }
        break;

    case 'rename':
            // Log action
            $logLine = sprintf("%s | RENAME | %s -> %s | %s\n", date('Y-m-d H:i:s'), $path ?? '', $newName ?? '', $_SERVER['REMOTE_ADDR'] ?? '');
            file_put_contents(__DIR__ . '/logs/filemanager.log', $logLine, FILE_APPEND);
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
        if (strpos(str_replace('\\', '/', $targetReal), str_replace('\\', '/', $base)) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Accesso negato']);
            exit;
        }
        
        $dirName = dirname($targetReal);
        $newPath = $dirName . '/' . $newName;
        
        if (file_exists($newPath)) {
            echo json_encode(['success' => false, 'error' => 'Esiste già un elemento con questo nome']);
            exit;
        }
        
        if (rename($targetReal, $newPath)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Errore rinomina. Verifica i permessi.']);
        }
        break;

    case 'upload':
            // Log action start (will log each uploaded file later)
            $logStart = sprintf("%s | UPLOAD_START | %s | %s\n", date('Y-m-d H:i:s'), $currentPath, $_SERVER['REMOTE_ADDR'] ?? '');
            file_put_contents(__DIR__ . '/logs/filemanager.log', $logStart, FILE_APPEND);
        if (empty($_FILES) || !isset($_FILES['files'])) {
            echo json_encode(['success' => false, 'error' => 'Nessun file caricato']);
            exit;
        }
        
        $uploaded = [];
        $errors = [];
        $targetDir = $fullPathReal ?: $fullPath;
        
        if (!is_dir($targetDir)) {
            echo json_encode(['success' => false, 'error' => 'Directory target non esiste']);
            exit;
        }
        
        $files = $_FILES['files'];
        $count = is_array($files['name']) ? count($files['name']) : 1;
        
        for ($i = 0; $i < $count; $i++) {
            $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = $name . ': errore upload (' . $error . ')';
                continue;
            }
            
            $safeName = preg_replace('/[\/\\\\:*?"<>|]/', '_', basename($name));
            // Validate file extension against whitelist
            $allowedExtensions = ['jpg','jpeg','png','pdf','doc','docx','txt'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions)) {
                $errors[] = $name . ': tipo di file non consentito';
                continue;
            }
            $targetFile = $targetDir . '/' . $safeName;
            
            $c = 1;
            $baseName = pathinfo($safeName, PATHINFO_FILENAME);
            $ext = pathinfo($safeName, PATHINFO_EXTENSION);
            
            while (file_exists($targetFile)) {
                $newName = $baseName . '_' . $c . ($ext ? '.' . $ext : '');
                $targetFile = $targetDir . '/' . $newName;
                $c++;
            }
            
            $success = false;
            
            if (is_uploaded_file($tmpName)) {
                $success = move_uploaded_file($tmpName, $targetFile);
            }
            
            if (!$success) {
                $success = copy($tmpName, $targetFile);
            }
            
            if ($success) {
                chmod($targetFile, 0640);
                $uploaded[] = [
                    'name' => basename($targetFile),
                    'path' => relPath($targetFile, $basePathReal ?: $basePath),
                    'size' => fmtSize(filesize($targetFile))
                ];
            } else {
                $errors[] = $name . ': impossibile salvare il file';
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
            $logLine = sprintf("%s | DOWNLOAD | %s | %s\n", date('Y-m-d H:i:s'), $path ?? '', $_SERVER['REMOTE_ADDR'] ?? '');
            file_put_contents(__DIR__ . '/logs/filemanager.log', $logLine, FILE_APPEND);
        $path = isset($_GET['path']) ? trim($_GET['path'], '/\\') : '';
        
        if (empty($path)) {
            http_response_code(400);
            echo 'Path richiesto';
            exit;
        }
        
        $targetPath = ($basePathReal ?: $basePath) . '/' . $path;
        $targetReal = realpath($targetPath);
        
        if ($targetReal === false) {
            http_response_code(404);
            echo 'File non trovato';
            exit;
        }
        
        $base = str_replace('\\', '/', $basePathReal ?: $basePath);
        $targetStr = str_replace('\\', '/', $targetReal);
        
        if (strpos($targetStr, $base) !== 0) {
            http_response_code(403);
            echo 'Accesso negato';
            exit;
        }
        
        if (is_dir($targetReal)) {
            http_response_code(400);
            echo 'Non è un file';
            exit;
        }
        
        $filename = basename($targetReal);
        $filesize = filesize($targetReal);
        $mimetype = 'application/octet-stream';
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp',
            'txt' => 'text/plain', 'html' => 'text/html', 'css' => 'text/css', 'js' => 'application/javascript',
            'json' => 'application/json', 'xml' => 'application/xml',
            'zip' => 'application/zip', 'rar' => 'application/vnd.rar',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4',
        ];
        if (isset($mimeTypes[$ext])) {
            $mimetype = $mimeTypes[$ext];
        }
        
        header('Content-Type: ' . $mimetype);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache');
        
        readfile($targetReal);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Azione non riconosciuta: ' . $action]);
}
