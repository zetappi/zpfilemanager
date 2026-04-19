# ZP File Manager

A lightweight file management module for PHP with drag & drop support, multiple file uploads, and folder creation/deletion. Designed for easy integration as a component in other projects.

## Features

- Folder navigation
- Multiple file upload with drag & drop
- Folder creation
- File and folder deletion
- Responsive design
- Fully asynchronous (AJAX)
- **Centralized configuration** for easy integration
- **Reusable helper class** for common functions
- **Improved error handling** with try-catch
- **IP-based rate limiting** (session-bypass resistant)
- **Configurable CSRF protection**
- **Configurable logging** with error handling
- **Localization system** with support for 5 languages (en, it, fr, de, es)
- **Real-time file search**
- **Grid/list view toggle**
- **Light/dark theme** with persistence

## Standalone Usage

> **Note**: when the file manager is integrated into a larger project, configure authentication via `config.local.php`.

1. Copy files to a directory on your PHP server
2. Ensure the directory has write permissions
3. (Optional) Copy `config.local.example.php` to `config.local.php` and customize
4. Access `index.php`

## Configuration

The file manager now uses a centralized configuration system for easier integration.

### Configuration Files

- **config.php**: Default configuration (do not modify)
- **config.local.php**: Local configuration overrides (create this file)
- **config.local.example.php**: Local configuration example

### Creating Custom Configuration

1. Copy `config.local.example.php` to `config.local.php`
2. Modify values according to your needs
3. `config.local.php` overrides values from `config.php`

### Main Configuration Options

```php
// Base path for files
define('FM_DEFAULT_BASE_PATH', __DIR__ . '/uploads');

// Require authentication
define('FM_REQUIRE_AUTH', false);

// Session key for authentication
define('FM_AUTH_SESSION_KEY', 'user');

// CSRF protection
define('FM_ENABLE_CSRF', true);

// Rate limiting (requests/minute)
define('FM_RATE_LIMIT', 30);

// Maximum upload file size (bytes)
define('FM_MAX_FILE_SIZE', 10 * 1024 * 1024);

// Allowed file extensions
define('FM_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt']);

// Enable logging
define('FM_ENABLE_LOGGING', true);

// Allowed CORS origins
define('FM_CORS_ALLOWED_ORIGINS', ['https://your-domain.com']);

// Default language
define('FM_DEFAULT_LANGUAGE', 'en');

// Available languages
define('FM_AVAILABLE_LANGUAGES', ['en', 'it', 'fr', 'de', 'es']);
```

## Integration as a Module

### Option 1: Iframe

```html
<iframe src="path/to/filemanager/index.php" width="100%" height="600"></iframe>
```

### Option 2: Inline (PHP include)

```php
<?php
// Define base path before include
$fm_base_path = '/var/www/my-project/uploads';
?>
<link rel="stylesheet" href="filemanager/style.css">
<div id="fileManager" data-base-path="<?= $fm_base_path ?>"></div>
<script src="filemanager/filemanager.js"></script>
```

### Option 3: Custom base path via URL

```html
<iframe src="filemanager/index.php?base_path=/custom/uploads"></iframe>
```

### Option 4: Full Integration (embedded)

```php
<?php
$fm_base_path = '/custom/uploads';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="filemanager/style.css">
</head>
<body>
    <div id="fileManager" data-base-path="<?= htmlspecialchars($fm_base_path) ?>"></div>
    <script>const FM_BASE_PATH = <?= json_encode($fm_base_path) ?>;</script>
    <script src="filemanager/filemanager.js"></script>
</body>
</html>
```

## File Structure

```
filemanager/
├── index.php                    # Main UI
├── api.php                      # Backend API
├── config.php                   # Default configuration
├── config.local.example.php     # Local configuration example
├── FileManagerHelper.php        # Reusable helper class
├── auth.php                     # Authentication (uses configuration)
├── style.css                    # Styles
├── filemanager.js               # AJAX logic
├── lang/                        # Localization directory
│   ├── Language.php             # Language loading system
│   ├── en.php                   # English translations
│   ├── it.php                   # Italian translations
│   ├── fr.php                   # French translations
│   ├── de.php                   # German translations
│   └── es.php                   # Spanish translations
├── uploads/                     # Upload directory (auto-created)
├── logs/                        # Log directory (auto-created)
└── README.it.md                 # Italian documentation
```

## Integration in Projects with Authentication

The module is designed to be integrated into PHP applications that handle authentication.

### 1. Enable Authentication

In `config.local.php`:

```php
define('FM_REQUIRE_AUTH', true);
define('FM_AUTH_SESSION_KEY', 'user_id'); // or your session key
```

### 2. CORS Configuration

In `config.local.php`:

```php
define('FM_CORS_ALLOWED_ORIGINS', ['https://your-domain.com', 'https://app.your-domain.it']);
define('FM_CORS_ALLOW_CREDENTIALS', true);
```

### 3. Logging Configuration

Logs are written to the configured directory (default: `logs/filemanager.log`). Ensure the directory is writable by the server.

In `config.local.php`:

```php
define('FM_ENABLE_LOGGING', true);
define('FM_LOG_FILE', __DIR__ . '/logs/filemanager.log');
```

### 4. Upload Whitelist Customization

In `config.local.php`:

```php
define('FM_ALLOWED_EXTENSIONS', [
    'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp',
    'pdf', 'doc', 'docx', 'txt'
]);
```

### 5. Rate Limiting

Configurable in `config.local.php`:

```php
define('FM_RATE_LIMIT', 30);           // requests/minute
define('FM_RATE_LIMIT_WINDOW', 60);    // seconds
```

**How Rate Limiting Works:**

The rate limiting system in `api.php` operates as follows:

1. **IP Identification**: Obtains the client's IP address from `$_SERVER['REMOTE_ADDR']` and uses it as a unique identifier
2. **Per-IP Tracking**: Creates a separate JSON file for each IP: `logs/ratelimit_{md5(IP)}.json`
3. **Configuration**: 
   - `FM_RATE_LIMIT`: maximum requests per window (default: 30)
   - `FM_RATE_LIMIT_WINDOW`: window duration in seconds (default: 60)
4. **Logic**:
   - Reads the IP's JSON file if it exists
   - If data is older than the time window → resets counter to 0
   - If counter >= limit → returns HTTP 429 "Too many requests"
   - Otherwise increments counter and saves to file
5. **Security**: 
   - IP-based, not session-based → cannot be bypassed by deleting session cookies
   - Each IP has its own file → isolation between users
   - Automatic reset after time window expires

**Example:**
- Limit: 30 requests/60 seconds
- IP 192.168.1.1 makes 25 requests → all accepted
- 31st request within 60 seconds → blocked with 429
- After 60 seconds → counter automatically resets

## Localization

The file manager supports multiple languages with a complete localization system.

### Available Languages

- 🇬🇧 **English** (en)
- 🇮🇹 **Italian** (it)
- 🇫🇷 **French** (fr)
- 🇩🇪 **German** (de)
- 🇪🇸 **Spanish** (es)

### Adding New Languages

To add a new language:

1. Create a file in the `lang/` directory with the language code (e.g., `ru.php`)
2. Copy the structure from `en.php` or `it.php`
3. Translate all keys
4. Add the language code to `FM_AVAILABLE_LANGUAGES` in `config.php`

### Language Switching

Users can change language via the selector in the header. Preference is saved in a cookie for 30 days.

## API Endpoints

All endpoints accept `base_path` as an optional parameter.

| Action | Method | Description |
|--------|--------|-------------|
| `get_csrf_token` | GET | Get CSRF token |
| `list` | GET | List directory contents |
| `create_folder` | POST | Create new folder |
| `delete` | POST | Delete file/folder |
| `rename` | POST | Rename file/folder |
| `upload` | POST | Upload file |
| `download` | GET | Download file |

## Reusable Helper Class

The `FileManagerHelper` class provides reusable utility functions for integration:

```php
require_once 'FileManagerHelper.php';

// Sanitize input for logging
$safe = FileManagerHelper::sanitizeLog($input);

// Format file size
$size = FileManagerHelper::formatSize(1024 * 1024); // "1.00 MB"

// Check path is safe
if (FileManagerHelper::isPathSafe($path)) { /* ... */ }

// Check path within base
if (FileManagerHelper::isPathWithinBase($target, $base)) { /* ... */ }

// Delete recursively
FileManagerHelper::deleteRecursive($path);

// Ensure directory exists
FileManagerHelper::ensureDirectory($path, 0755);

// Log with error handling
FileManagerHelper::log($message, $logFile);
```

## Requirements

- PHP 7.4+ (recommended)
- Apache/Nginx with PHP support
- Write permissions on target directory
- PHP sessions enabled (for authentication and rate limiting)
- Write access to module's `logs/` directory

## Security

- **CORS**: configurable via `FM_CORS_ALLOWED_ORIGINS`
- **Authentication**: optional, configurable via `FM_REQUIRE_AUTH`
- **CSRF Protection**: enableable via `FM_ENABLE_CSRF`
- **Upload whitelist**: configurable via `FM_ALLOWED_EXTENSIONS`
- **Logging**: operations logged with error handling
- **Rate limiting**: IP-based, configurable
- **Permissions**: configurable via `FM_FILE_PERMISSIONS` and `FM_DIR_PERMISSIONS`
- **Path traversal protection**: blocks access outside base directory
- **Error messages**: do not expose internal paths
- **Error handling**: try-catch for critical operations

## Reliability Improvements

- **Centralized configuration**: easy customization without code modification
- **Helper class**: reusable and testable code
- **Improved error handling**: try-catch for critical operations
- **Robust logging**: error handling in log writing
- **IP-based rate limiting**: session-bypass resistant
- **Configuration validation**: checks for writable directories
- **Helper functions moved**: outside switch case for better organization
- **Centralized input sanitization**: in FileManagerHelper
- **Cross-platform**: Windows/Unix path handling

## License

MIT
