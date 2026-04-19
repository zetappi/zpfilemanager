<?php
/**
 * File Manager Configuration
 * 
 * Centralized configuration for the File Manager module.
 * Copy this file to config.local.php and customize for your environment.
 * config.local.php is loaded automatically if it exists and overrides these defaults.
 */

// ============================================================================
// PATH CONFIGURATION
// ============================================================================

// Base directory for file operations (relative to this file or absolute)
// Default: ./uploads
define('FM_DEFAULT_BASE_PATH', __DIR__ . '/uploads');

// Allowed base directory (for security - base_path parameter must be within this)
// Default: script directory / uploads
define('FM_ALLOWED_BASE_DIR', __DIR__ . '/uploads');

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================

// Require authentication (set to true to enforce authentication)
// Default: false (for standalone use)
define('FM_REQUIRE_AUTH', false);

// Session key to check for authenticated user
// Default: 'user'
define('FM_AUTH_SESSION_KEY', 'user');

// CSRF Protection (recommended: true)
// Default: true
define('FM_ENABLE_CSRF', true);

// Rate limiting: max requests per minute per IP
// Default: 30
define('FM_RATE_LIMIT', 30);

// Rate limiting window in seconds
// Default: 60
define('FM_RATE_LIMIT_WINDOW', 60);

// ============================================================================
// UPLOAD CONFIGURATION
// ============================================================================

// Maximum file size in bytes (0 = use php.ini upload_max_filesize)
// Default: 10 * 1024 * 1024 (10MB)
define('FM_MAX_FILE_SIZE', 10 * 1024 * 1024);

// Allowed file extensions (whitelist)
// Empty array = allow all extensions (NOT RECOMMENDED)
define('FM_ALLOWED_EXTENSIONS', [
    // Images
    'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp', 'ico',
    // Documents
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
    // Text
    'txt', 'rtf', 'csv', 'md',
    // Archives
    'zip', 'rar', '7z', 'tar', 'gz'
]);

// File permissions for uploaded files (octal)
// Default: 0640 (owner read/write, group read, others none)
define('FM_FILE_PERMISSIONS', 0640);

// Directory permissions for created directories (octal)
// Default: 0750 (owner read/write/execute, group read/execute, others none)
define('FM_DIR_PERMISSIONS', 0750);

// ============================================================================
// LOGGING CONFIGURATION
// ============================================================================

// Enable logging
// Default: true
define('FM_ENABLE_LOGGING', true);

// Log file path (relative to this file or absolute)
// Default: ./logs/filemanager.log
define('FM_LOG_FILE', __DIR__ . '/logs/filemanager.log');

// Log format: 'simple' or 'detailed'
// Default: 'simple'
define('FM_LOG_FORMAT', 'simple');

// ============================================================================
// CORS CONFIGURATION
// ============================================================================

// Allowed CORS origins (empty array = deny all, '*' = allow all)
// Default: [] (deny all for security)
define('FM_CORS_ALLOWED_ORIGINS', []);

// Allow credentials in CORS requests
// Default: false
define('FM_CORS_ALLOW_CREDENTIALS', false);

// ============================================================================
// PERFORMANCE CONFIGURATION
// ============================================================================

// Items per page for pagination
// Default: 10
define('FM_ITEMS_PER_PAGE', 10);

// Maximum items per page
// Default: 100
define('FM_MAX_ITEMS_PER_PAGE', 100);

// Enable file size caching (improves performance on large directories)
// Default: false
define('FM_ENABLE_SIZE_CACHE', false);

// ============================================================================
// BEHAVIOR CONFIGURATION
// ============================================================================

// Auto-rename files on upload if name already exists
// Default: true (adds _1, _2, etc.)
define('FM_AUTO_RENAME_ON_CONFLICT', true);

// Allow deletion of non-empty directories
// Default: true
define('FM_ALLOW_DELETE_NON_EMPTY', true);

// Show hidden files (starting with .)
// Default: false
define('FM_SHOW_HIDDEN_FILES', false);

// ============================================================================
// LOAD LOCAL CONFIGURATION OVERRIDES
// ============================================================================

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
