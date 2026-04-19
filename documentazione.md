# Documentazione ZP File Manager

## Indice

1. [Panoramica](#panoramica)
2. [Architettura](#architettura)
3. [Installazione](#installazione)
4. [Configurazione](#configurazione)
5. [API Reference](#api-reference)
6. [Guida Integrazione](#guida-integrazione)
7. [Helper Class](#helper-class)
8. [Sicurezza](#sicurezza)
9. [Troubleshooting](#troubleshooting)
10. [Best Practices](#best-practices)

---

## Panoramica

ZP File Manager è un modulo di file management leggero per PHP progettato per essere facilmente integrabile come componente in altri progetti. Offre funzionalità complete di gestione file con un'interfaccia utente moderna e responsive.

### Caratteristiche Principali

- Navigazione tra cartelle con breadcrumb
- Upload file multipli con drag & drop
- Creazione ed eliminazione cartelle
- Rinomina file e cartelle
- Download file
- Paginazione risultati
- Ordinamento per nome, dimensione, data
- Design responsive
- Completamente asincrono (AJAX)
- Configurazione centralizzata
- Helper class riutilizzabile
- Error handling migliorato
- Rate limiting basato su IP
- CSRF protection configurabile

### Requisiti di Sistema

- PHP 7.4 o superiore
- Apache/Nginx con supporto PHP
- Estensioni PHP: `json`, `session`, `fileinfo` (opzionale)
- Permessi di scrittura sulle directory target
- Sessioni PHP abilitate

---

## Architettura

### Struttura File

```
zp-filemanager/
├── index.php                    # UI principale
├── api.php                      # Backend API
├── config.php                   # Configurazione predefinita
├── config.local.example.php     # Esempio configurazione locale
├── FileManagerHelper.php        # Classe helper riutilizzabile
├── auth.php                     # Autenticazione
├── style.css                    # Stili CSS
├── filemanager.js               # Logica JavaScript
├── uploads/                     # Directory upload (auto-creata)
├── logs/                        # Directory log (auto-creata)
├── test/                        # Test
└── documentazione.md            # Questo file
```

### Flusso Dati

```
Browser (JavaScript)
    ↓ AJAX requests
api.php (Backend)
    ↓ File operations
File System
    ↓
Response JSON
    ↓
Browser UI Update
```

### Componenti

#### Frontend
- **index.php**: HTML structure
- **filemanager.js**: Logica AJAX, gestione UI, drag & drop
- **style.css**: Styling responsive

#### Backend
- **api.php**: API endpoints, validazione, operazioni file system
- **auth.php**: Gestione autenticazione session-based
- **FileManagerHelper.php**: Funzioni utility riutilizzabili

#### Configurazione
- **config.php**: Valori predefiniti
- **config.local.php**: Override locale (non versionato)

---

## Installazione

### Installazione Standalone

1. Copia i file nella directory desiderata del server
2. Assicurati che la directory abbia permessi di scrittura (755)
3. (Opzionale) Crea `config.local.php` da `config.local.example.php`
4. Accedi a `index.php` via browser

### Installazione come Modulo

#### Metodo 1: Include diretto

```php
<?php
require_once '/path/to/filemanager/config.php';
// Configura il percorso base
define('FM_DEFAULT_BASE_PATH', '/var/www/myapp/uploads');
?>
<link rel="stylesheet" href="/path/to/filemanager/style.css">
<div id="fileManager"></div>
<script src="/path/to/filemanager/filemanager.js"></script>
```

#### Metodo 2: Iframe

```html
<iframe src="/path/to/filemanager/index.php" 
        width="100%" 
        height="600"
        frameborder="0"></iframe>
```

#### Metodo 3: API custom

```php
<?php
// Usa FileManagerHelper nel tuo codice
require_once '/path/to/filemanager/FileManagerHelper.php';

// Esempio: lista file
$files = scandir('/path/to/directory');
foreach ($files as $file) {
    echo FileManagerHelper::formatSize(filesize($file));
}
?>
```

---

## Configurazione

### File di Configurazione

Il sistema usa tre file di configurazione:

1. **config.php** - Configurazione predefinita (non modificare)
2. **config.local.php** - Override locale (crea questo file)
3. **config.local.example.php** - Esempio con commenti

### Creare Configurazione Personalizzata

```bash
cp config.local.example.php config.local.php
# Modifica config.local.php secondo le tue esigenze
```

### Opzioni di Configurazione

#### Path Configuration

```php
// Directory base per operazioni file
define('FM_DEFAULT_BASE_PATH', __DIR__ . '/uploads');

// Directory base consentita (security)
define('FM_ALLOWED_BASE_DIR', __DIR__ . '/uploads');
```

#### Security Configuration

```php
// Richiedi autenticazione
define('FM_REQUIRE_AUTH', false);

// Chiave sessione per autenticazione
define('FM_AUTH_SESSION_KEY', 'user');

// Abilita CSRF protection
define('FM_ENABLE_CSRF', true);

// Rate limiting (richieste/minuto)
define('FM_RATE_LIMIT', 30);

// Finestra rate limiting (secondi)
define('FM_RATE_LIMIT_WINDOW', 60);
```

#### Upload Configuration

```php
// Dimensione massima file (bytes, 0 = php.ini default)
define('FM_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Estensioni file consentite
define('FM_ALLOWED_EXTENSIONS', [
    'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp',
    'pdf', 'doc', 'docx', 'txt'
]);

// Permessi file upload (ottale)
define('FM_FILE_PERMISSIONS', 0640);

// Permessi directory create (ottale)
define('FM_DIR_PERMISSIONS', 0750);

// Auto-renaming su conflitto nomi
define('FM_AUTO_RENAME_ON_CONFLICT', true);
```

#### Logging Configuration

```php
// Abilita logging
define('FM_ENABLE_LOGGING', true);

// Percorso file log
define('FM_LOG_FILE', __DIR__ . '/logs/filemanager.log');
```

#### CORS Configuration

```php
// Origins CORS consentiti
define('FM_CORS_ALLOWED_ORIGINS', [
    'https://example.com',
    'https://app.example.com'
]);

// Allow credentials
define('FM_CORS_ALLOW_CREDENTIALS', false);
```

#### Performance Configuration

```php
// Item per pagina
define('FM_ITEMS_PER_PAGE', 10);

// Max item per pagina
define('FM_MAX_ITEMS_PER_PAGE', 100);
```

#### Behavior Configuration

```php
// Permetti eliminazione directory non vuote
define('FM_ALLOW_DELETE_NON_EMPTY', true);

// Mostra file nascosti (inizia con .)
define('FM_SHOW_HIDDEN_FILES', false);
```

---

## API Reference

### Endpoints

#### GET /api.php?action=get_csrf_token

Ottieni token CSRF per richieste POST.

**Response:**
```json
{
    "success": true,
    "csrf_token": "abc123def456..."
}
```

#### GET /api.php?action=list

Lista contenuti directory.

**Parameters:**
- `path` (optional): Path relativo alla directory base
- `base_path` (optional): Override directory base
- `sort_by` (optional): `name|size|modified` (default: `name`)
- `sort_order` (optional): `asc|desc` (default: `asc`)
- `page` (optional): Numero pagina (default: `1`)
- `per_page` (optional): Item per pagina (default: `10`)

**Response:**
```json
{
    "success": true,
    "path": "subfolder",
    "full_base_path": "/var/www/uploads",
    "items": [
        {
            "name": "file.txt",
            "path": "subfolder/file.txt",
            "is_dir": false,
            "size": 1024,
            "size_fmt": "1.00 KB",
            "modified": 1640995200,
            "modified_fmt": "01/01/2022 00:00"
        }
    ],
    "pagination": {
        "page": 1,
        "per_page": 10,
        "total_items": 1,
        "total_pages": 1
    }
}
```

#### POST /api.php?action=create_folder

Crea nuova cartella.

**Parameters:**
- `path` (optional): Directory padre
- `base_path` (optional): Override directory base
- `name`: Nome cartella
- `csrf_token`: Token CSRF

**Response:**
```json
{
    "success": true,
    "item": {
        "name": "newfolder",
        "path": "newfolder",
        "is_dir": true,
        "size": null,
        "modified": "01/01/2022 00:00"
    }
}
```

#### POST /api.php?action=delete

Elimina file o cartella.

**Parameters:**
- `path`: Path elemento da eliminare
- `base_path` (optional): Override directory base
- `csrf_token`: Token CSRF

**Response:**
```json
{
    "success": true
}
```

#### POST /api.php?action=rename

Rinomina file o cartella.

**Parameters:**
- `path`: Path elemento da rinominare
- `new_name`: Nuovo nome
- `base_path` (optional): Override directory base
- `csrf_token`: Token CSRF

**Response:**
```json
{
    "success": true
}
```

#### POST /api.php?action=upload

Carica file.

**Parameters:**
- `path` (optional): Directory target
- `base_path` (optional): Override directory base
- `files`: File da caricare (multipart/form-data)
- `csrf_token`: Token CSRF

**Response:**
```json
{
    "success": true,
    "uploaded": [
        {
            "name": "file.txt",
            "path": "file.txt",
            "size": "1.00 KB"
        }
    ],
    "errors": []
}
```

#### GET /api.php?action=download

Scarica file.

**Parameters:**
- `path`: Path file da scaricare
- `base_path` (optional): Override directory base

**Response:** Binary file stream

### Codici HTTP

- `200`: Success
- `400`: Bad request (parametri mancanti/invalidi)
- `401`: Unauthorized (autenticazione richiesta)
- `403`: Forbidden (accesso negato, CSRF invalido)
- `404`: Not found (file non trovato)
- `429`: Too Many Requests (rate limit exceeded)
- `500`: Server error (errore interno)

---

## Guida Integrazione

### Integrazione con Autenticazione Esistente

#### 1. Configurare Autenticazione

In `config.local.php`:

```php
define('FM_REQUIRE_AUTH', true);
define('FM_AUTH_SESSION_KEY', 'user_id'); // o la tua chiave
```

#### 2. Impostare Sessione Prima dell'Include

```php
<?php
session_start();
$_SESSION['user_id'] = $userId; // Il tuo sistema di auth

require_once 'filemanager/api.php';
?>
```

#### 3. Custom Session Key

Se il tuo sistema usa una chiave diversa:

```php
define('FM_AUTH_SESSION_KEY', 'logged_in_user');
```

### Integrazione Multi-User

#### Percorsi Base per Utente

```php
<?php
session_start();
$userId = $_SESSION['user_id'];
$userUploadDir = "/var/www/uploads/user_$userId";

define('FM_DEFAULT_BASE_PATH', $userUploadDir);
define('FM_ALLOWED_BASE_DIR', $userUploadDir);

require_once 'filemanager/api.php';
?>
```

### Integrazione CORS

#### Configurazione Multi-Origin

```php
define('FM_CORS_ALLOWED_ORIGINS', [
    'https://app.example.com',
    'https://admin.example.com'
]);
define('FM_CORS_ALLOW_CREDENTIALS', true);
```

#### Preflight Requests

Il frontend deve includere il token CSRF nell'header:

```javascript
xhr.setRequestHeader('X-CSRF-Token', csrfToken);
```

### Integrazione in Framework

#### Laravel

```php
// In controller
public function filemanager()
{
    config(['filemanager.base_path' => storage_path('app/public')]);
    return view('filemanager');
}
```

#### WordPress

```php
// In plugin
add_action('init', function() {
    if (!defined('FM_DEFAULT_BASE_PATH')) {
        define('FM_DEFAULT_BASE_PATH', WP_CONTENT_DIR . '/uploads');
    }
});
```

---

## Helper Class

La classe `FileManagerHelper` fornisce funzioni utility riutilizzabili.

### Metodi Disponibili

#### relativePath($full, $base)

Converte path assoluto in relativo.

```php
$rel = FileManagerHelper::relativePath('/var/www/uploads/file.txt', '/var/www/uploads');
// Returns: "file.txt"
```

#### formatSize($bytes)

Formatta dimensione in formato leggibile.

```php
$size = FileManagerHelper::formatSize(1024 * 1024);
// Returns: "1.00 MB"
```

#### sanitizeLog($input)

Sanitizza input per logging (rimuove newline).

```php
$safe = FileManagerHelper::sanitizeLog($input);
```

#### sanitizeFilename($filename)

Sanitizza filename per header HTTP.

```php
$safe = FileManagerHelper::sanitizeFilename($filename);
```

#### isPathSafe($path)

Verifica path sicuro (no directory traversal).

```php
if (FileManagerHelper::isPathSafe($path)) {
    // Sicuro
}
```

#### isPathWithinBase($target, $base)

Verifica path within base directory.

```php
if (FileManagerHelper::isPathWithinBase($target, $base)) {
    // Dentro base
}
```

#### deleteRecursive($path)

Elimina ricorsivamente con error handling.

```php
FileManagerHelper::deleteRecursive('/path/to/directory');
```

#### ensureDirectory($path, $permissions)

Crea/verifica directory con permessi.

```php
FileManagerHelper::ensureDirectory('/path/to/dir', 0755);
```

#### log($message, $logFile)

Scrivi log con gestione errori.

```php
FileManagerHelper::log('Action completed', '/path/to/log.log');
```

#### isExtensionAllowed($filename, $allowedExtensions)

Verifica estensione file consentita.

```php
if (FileManagerHelper::isExtensionAllowed('file.jpg', ['jpg', 'png'])) {
    // Consentito
}
```

#### sanitizeFilenameForStorage($filename)

Sanitizza filename per storage.

```php
$safe = FileManagerHelper::sanitizeFilenameForStorage($filename);
```

#### generateUniqueFilename($directory, $filename)

Genera nome unico se esistente.

```php
$unique = FileManagerHelper::generateUniqueFilename('/dir', 'file.txt');
// Returns: "file_1.txt" se "file.txt" esiste
```

#### getMimeType($filename)

Ottieni MIME type da estensione.

```php
$mime = FileManagerHelper::getMimeType('file.jpg');
// Returns: "image/jpeg"
```

---

## Sicurezza

### Vulnerabilità Corrette

#### 1. Path Traversal
- **Problema**: Accesso a directory fuori da base
- **Soluzione**: Validazione con `FileManagerHelper::isPathSafe()` e `isPathWithinBase()`

#### 2. File Upload Bypass
- **Problema**: Upload file arbitrari
- **Soluzione**: Whitelist estensioni, validazione `is_uploaded_file()`, rimozione fallback `copy()`

#### 3. Header Injection
- **Problema**: Injection via filename nel download
- **Soluzione**: Sanitizzazione con `FileManagerHelper::sanitizeFilename()`

#### 4. Log Injection
- **Problema**: Injection nei log
- **Soluzione**: Sanitizzazione con `FileManagerHelper::sanitizeLog()`

#### 5. CSRF
- **Problema**: Richieste POST non autenticate
- **Soluzione**: Token CSRF configurabile, verifica su tutte le POST

#### 6. Rate Limiting Bypass
- **Problema**: Bypass via nuova sessione
- **Soluzione**: Rate limiting basato su IP, non su sessione

### Best Practices Sicurezza

1. **Mantenere config.local.php fuori da version control**
2. **Usare HTTPS in produzione**
3. **Limitare CORS origins a domini specifici**
4. **Abilitare autenticazione in produzione**
5. **Limitare estensioni file consentite**
6. **Monitorare log regolarmente**
7. **Usare permessi restrittivi (0640, 0750)**
8. **Verificare permessi directory**
9. **Tenere PHP aggiornato**
10. **Usare fail2ban per rate limiting aggiuntivo**

---

## Troubleshooting

### Problemi Comuni

#### "Impossibile creare directory base"

**Causa**: Permessi insufficienti

**Soluzione**:
```bash
chmod 755 /path/to/uploads
chown www-data:www-data /path/to/uploads
```

#### "CSRF token validation failed"

**Causa**: Token non valido o scaduto

**Soluzione**: 
- Verifica che il frontend ottenga il token prima delle richieste POST
- Controlla che la sessione sia attiva

#### "Too many requests"

**Causa**: Rate limit exceeded

**Soluzione**:
```php
// In config.local.php
define('FM_RATE_LIMIT', 60); // Aumenta limite
```

#### "File non trovato"

**Causa**: Path non valido o fuori da base

**Soluzione**:
- Verifica path sia relativo alla directory base
- Controlla permessi directory

#### "Tipo di file non consentito"

**Causa**: Estensione non in whitelist

**Soluzione**:
```php
// In config.local.php
define('FM_ALLOWED_EXTENSIONS', [
    'jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt'
]);
```

### Debug Mode

Per abilitare debug dettagliato:

```php
// In config.local.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_error.log');
```

### Log Analysis

Controlla log operazioni:

```bash
tail -f logs/filemanager.log
```

Formato log:
```
2024-01-01 12:00:00 | LIST | subfolder | 192.168.1.1
2024-01-01 12:00:05 | UPLOAD_START | subfolder | 192.168.1.1
```

---

## Best Practices

### Performance

1. **Limitare item per pagina**: Usa `FM_ITEMS_PER_PAGE` appropriato
2. **Evitare directory troppo grandi**: Suddivi in sottodirectory
3. **Usare cache**: Abilita `FM_ENABLE_SIZE_CACHE` per directory grandi
4. **Ottimizzare immagini**: Comprimi prima dell'upload
5. **Monitorare spazio disco**: Imposta quote se necessario

### Integrazione

1. **Usare config.local.php**: Non modificare config.php
2. **Versionare config.local.example.php**: Mantieni template aggiornato
3. **Documentare customizzazioni**: Aggiungi commenti in config.local.php
4. **Testare in staging**: Verifica configurazione prima di produzione
5. **Backup prima upgrade**: Mantieni copia configurazione

### Manutenzione

1. **Rotazione log**: Implementa logrotate per file log
2. **Pulizia file temporanei**: Rimuovi vecchi file rate limit
3. **Aggiornamenti regolari**: Mantieni PHP e dipendenze aggiornate
4. **Audit sicurezza**: Controlla permessi regolarmente
5. **Monitoraggio**: Usa strumenti di monitoring per uptime

### Scalabilità

1. **Load balancing**: Usa sessioni condivise (Redis/Memcached)
2. **CDN**: Serve file statici via CDN
3. **Storage esterno**: Usa S3 o simili per upload
4. **Database**: Considera DB per metadata se necessario
5. **Caching**: Implementa caching per operazioni frequenti

---

## Esempi di Utilizzo

### Esempio 1: Integrazione Completa

```php
<?php
// config.local.php
define('FM_REQUIRE_AUTH', true);
define('FM_AUTH_SESSION_KEY', 'user_id');
define('FM_DEFAULT_BASE_PATH', '/var/www/myapp/uploads');
define('FM_CORS_ALLOWED_ORIGINS', ['https://myapp.com']);
define('FM_MAX_FILE_SIZE', 50 * 1024 * 1024);
?>

<?php
// Nel tuo controller
session_start();
$_SESSION['user_id'] = $userId;

require_once 'filemanager/api.php';
?>
```

### Esempio 2: Multi-Tenant

```php
<?php
$tenantId = getTenantId(); // La tua logica
$tenantUploadDir = "/var/www/uploads/tenant_$tenantId";

define('FM_DEFAULT_BASE_PATH', $tenantUploadDir);
define('FM_ALLOWED_BASE_DIR', '/var/www/uploads');
define('FM_REQUIRE_AUTH', true);

require_once 'filemanager/api.php';
?>
```

### Esempio 3: Custom Helper Usage

```php
<?php
require_once 'FileManagerHelper.php';

// Validazione upload personalizzata
if ($_FILES['file']) {
    $tmpName = $_FILES['file']['tmp_name'];
    
    if (!FileManagerHelper::isExtensionAllowed(
        $_FILES['file']['name'],
        ['jpg', 'png', 'pdf']
    )) {
        die('Tipo file non consentito');
    }
    
    $safeName = FileManagerHelper::sanitizeFilenameForStorage(
        $_FILES['file']['name']
    );
    
    move_uploaded_file($tmpName, "/uploads/$safeName");
}
?>
```

---

## Supporto

Per problemi o domande:

1. Controlla questa documentazione
2. Verifica log in `logs/filemanager.log`
3. Controlla error log PHP
4. Rivedi configurazione in `config.local.php`

---

## Changelog

### Versione 2.0 (2026-04-19)

**Nuove funzionalità:**
- Configurazione centralizzata (config.php)
- Helper class riutilizzabile (FileManagerHelper.php)
- Rate limiting basato su IP
- CSRF protection configurabile
- Logging configurabile con gestione errori
- Template configurazione locale

**Miglioramenti:**
- Error handling con try-catch
- Cross-platform path handling
- Validazione dimensione file upload
- Auto-renaming configurabile
- Hidden files option
- Max per page limit

**Sicurezza:**
- Correzione vulnerabilità copy() fallback
- Header injection fix
- Log injection fix
- CSRF token generation con error handling

**Bugfix:**
- Fix path traversal validation
- Fix rate limiting bypass
- Fix filename sanitization

---

## Licenza

MIT License - Vedi file LICENSE per dettagli.
