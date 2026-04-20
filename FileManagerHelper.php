<?php
/**
 * FileManagerHelper
 * 
 * Helper class with common utility functions for the File Manager.
 * Improves testability and code organization.
 */

class FileManagerHelper {
    
    /**
     * Convert absolute path to relative path
     * 
     * @param string $full Full path
     * @param string $base Base path
     * @return string Relative path
     */
    public static function relativePath($full, $base) {
        $full = str_replace('\\', '/', $full);
        $base = str_replace('\\', '/', $base);
        if (strpos($full, $base) === 0) {
            return ltrim(substr($full, strlen($base)), '/');
        }
        return $full;
    }
    
    /**
     * Format file size in human-readable format
     * 
     * @param int $bytes Size in bytes
     * @return string Formatted size
     */
    public static function formatSize($bytes) {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
    
    /**
     * Sanitize string for logging (remove newlines and control chars)
     * 
     * @param string $input Input string
     * @return string Sanitized string
     */
    public static function sanitizeLog($input) {
        return preg_replace('/[\r\n\t]/', '', $input);
    }
    
    /**
     * Sanitize filename for HTTP headers (prevent header injection)
     *
     * @param string $filename Filename
     * @return string Sanitized filename
     */
    public static function sanitizeFilename($filename) {
        // Remove control chars, newlines, and quotes that could break headers
        return preg_replace('/[\r\n"]/', '', $filename);
    }
    
    /**
     * Validate and normalize path (prevent directory traversal)
     * 
     * @param string $path Path to validate
     * @return bool True if safe
     */
    public static function isPathSafe($path) {
        if ($path === null || $path === '') {
            return true;
        }
        
        // Check for directory traversal patterns
        if (strpos($path, '..') !== false) {
            return false;
        }
        
        // Check for null bytes
        if (strpos($path, "\0") !== false) {
            return false;
        }
        
        // Check for absolute paths
        if (substr($path, 0, 1) === '/' || substr($path, 1, 1) === ':') {
            return false;
        }
        
        // Normalize path and check again for encoded traversal
        $normalized = str_replace(['%2e%2e', '%2E%2E', '%252e%252e'], '..', strtolower($path));
        if (strpos($normalized, '..') !== false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if path is within allowed base directory
     * 
     * @param string $targetPath Path to check
     * @param string $basePath Base directory
     * @return bool True if within base
     */
    public static function isPathWithinBase($targetPath, $basePath) {
        $targetReal = realpath($targetPath);
        $baseReal = realpath($basePath);
        
        if ($targetReal === false || $baseReal === false) {
            return false;
        }
        
        // Normalize paths for comparison (handle both forward and backslashes)
        $targetStr = rtrim(str_replace('\\', '/', $targetReal), '/');
        $baseStr = rtrim(str_replace('\\', '/', $baseReal), '/');
        
        // Ensure exact match or target is a subdirectory of base
        if ($targetStr === $baseStr) {
            return true;
        }
        
        // Check if target starts with base path followed by a separator
        if (strpos($targetStr, $baseStr . '/') === 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Recursively delete directory or file
     * 
     * @param string $path Path to delete
     * @return bool Success status
     */
    public static function deleteRecursive($path) {
        if (!file_exists($path)) {
            return false;
        }
        
        if (is_dir($path)) {
            $items = @scandir($path);
            if ($items === false) {
                return false;
            }
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                if (!self::deleteRecursive($path . '/' . $item)) {
                    return false;
                }
            }
            return @rmdir($path);
        }
        
        return @unlink($path);
    }
    
    /**
     * Ensure directory exists and is writable
     * 
     * @param string $path Directory path
     * @param int $permissions Octal permissions
     * @return bool True if directory is ready
     */
    public static function ensureDirectory($path, $permissions = 0755) {
        if (!is_dir($path)) {
            if (!@mkdir($path, $permissions, true)) {
                return false;
            }
        }
        return is_writable($path);
    }
    
    /**
     * Write to log file with error handling
     * 
     * @param string $message Log message
     * @param string $logFile Log file path
     * @return bool Success status
     */
    public static function log($message, $logFile) {
        try {
            $dir = dirname($logFile);
            if (!self::ensureDirectory($dir)) {
                return false;
            }
            return @file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND) !== false;
        } catch (Exception $e) {
            error_log('File Manager logging error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate file extension against whitelist
     * 
     * @param string $filename Filename
     * @param array $allowedExtensions Allowed extensions
     * @return bool True if allowed
     */
    public static function isExtensionAllowed($filename, $allowedExtensions) {
        if (empty($allowedExtensions)) {
            return true; // No restrictions
        }
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $allowedExtensions);
    }
    
    /**
     * Generate safe filename (replace invalid characters)
     * 
     * @param string $filename Original filename
     * @return string Safe filename
     */
    public static function sanitizeFilenameForStorage($filename) {
        return preg_replace('/[\/\\\\:*?"<>|]/', '_', basename($filename));
    }
    
    /**
     * Generate unique filename if file exists
     * 
     * @param string $directory Target directory
     * @param string $filename Desired filename
     * @return string Unique filename
     */
    public static function generateUniqueFilename($directory, $filename) {
        $targetFile = $directory . '/' . $filename;
        
        if (!file_exists($targetFile)) {
            return $filename;
        }
        
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $counter = 1;
        
        do {
            $newName = $baseName . '_' . $counter . ($extension ? '.' . $extension : '');
            $targetFile = $directory . '/' . $newName;
            $counter++;
        } while (file_exists($targetFile));
        
        return $newName;
    }
    
    /**
     * Get MIME type based on file extension
     * 
     * @param string $filename Filename
     * @return string MIME type
     */
    public static function getMimeType($filename) {
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
        
        return $mimeTypes[$ext] ?? 'application/octet-stream';
    }
}
