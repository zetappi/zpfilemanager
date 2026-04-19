<?php
/**
 * File Manager Local Configuration Example
 * 
 * Copy this file to config.local.php and customize for your environment.
 * config.local.php overrides the default values in config.php.
 * 
 * IMPORTANT: Never commit config.local.php to version control!
 */

// ============================================================================
// PATH CONFIGURATION
// ============================================================================

// Base directory for file operations (relative to this file or absolute)
// Example: define('FM_DEFAULT_BASE_PATH', '/var/www/myapp/uploads');
define('FM_DEFAULT_BASE_PATH', __DIR__ . '/uploads');

// Allowed base directory (for security - base_path parameter must be within this)
// Example: define('FM_ALLOWED_BASE_DIR', '/var/www/myapp/uploads');
define('FM_ALLOWED_BASE_DIR', __DIR__ . '/uploads');

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================

// Require authentication (set to true to enforce authentication)
define('FM_REQUIRE_AUTH', false);

// Session key to check for authenticated user
// Change this to match your application's session key
define('FM_AUTH_SESSION_KEY', 'user');

// CSRF Protection (recommended: true)
define('FM_ENABLE_CSRF', true);

// Rate limiting: max requests per minute per IP
define('FM_RATE_LIMIT', 30);

// Rate limiting window in seconds
define('FM_RATE_LIMIT_WINDOW', 60);

// ============================================================================
// UPLOAD CONFIGURATION
// ============================================================================

// Maximum file size in bytes (0 = use php.ini upload_max_filesize)
// Example: 50MB = 50 * 1024 * 1024
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
define('FM_FILE_PERMISSIONS', 0640);

// Directory permissions for created directories (octal)
define('FM_DIR_PERMISSIONS', 0750);

// ============================================================================
// LOGGING CONFIGURATION
// ============================================================================

// Enable logging
define('FM_ENABLE_LOGGING', true);

// Log file path (relative to this file or absolute)
define('FM_LOG_FILE', __DIR__ . '/logs/filemanager.log');

// ============================================================================
// CORS CONFIGURATION
// ============================================================================

// Allowed CORS origins (empty array = deny all, '*' = allow all)
// Example: define('FM_CORS_ALLOWED_ORIGINS', ['https://example.com', 'https://app.example.com']);
define('FM_CORS_ALLOWED_ORIGINS', []);

// Allow credentials in CORS requests
define('FM_CORS_ALLOW_CREDENTIALS', false);

// ============================================================================
// PERFORMANCE CONFIGURATION
// ============================================================================

// Items per page for pagination
define('FM_ITEMS_PER_PAGE', 10);

// Maximum items per page
define('FM_MAX_ITEMS_PER_PAGE', 100);

// ============================================================================
// BEHAVIOR CONFIGURATION
// ============================================================================

// Auto-rename files on upload if name already exists
define('FM_AUTO_RENAME_ON_CONFLICT', true);

// Allow deletion of non-empty directories
define('FM_ALLOW_DELETE_NON_EMPTY', true);

// Show hidden files (starting with .)
define('FM_SHOW_HIDDEN_FILES', false);
