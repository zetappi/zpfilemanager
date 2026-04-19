# ZP File Manager

Un modulo di file management leggero per PHP con supporto drag & drop, upload multipli, creazione ed eliminazione cartelle. Progettato per essere facilmente integrabile come componente in altri progetti.

## Caratteristiche

- Navigazione tra cartelle
- Upload file multipli con drag & drop
- Creazione cartelle
- Eliminazione file e cartelle
- Design responsive
- Completamente asincrono (AJAX)
- **Configurazione centralizzata** per facile integrazione
- **Helper class riutilizzabile** per funzioni comuni
- **Error handling migliorato** con try-catch
- **Rate limiting basato su IP** (non aggirabile via session)
- **CSRF protection** configurabile
- **Logging configurabile** con gestione errori

## Uso Standalone

> **Nota**: quando il file manager è integrato in un progetto più ampio, configurare l'autenticazione tramite `config.local.php`.

1. Copia i file in una directory del tuo server PHP
2. Assicurati che la directory abbia permessi di scrittura
3. (Opzionale) Copia `config.local.example.php` in `config.local.php` e personalizza
4. Accedi a `index.php`

## Configurazione

Il file manager ora usa un sistema di configurazione centralizzato per facilitare l'integrazione.

### File di configurazione

- **config.php**: Configurazione predefinita (non modificare)
- **config.local.php**: Override configurazione locale (crea questo file)
- **config.local.example.php**: Esempio di configurazione locale

### Creare configurazione personalizzata

1. Copia `config.local.example.php` in `config.local.php`
2. Modifica i valori secondo le tue esigenze
3. `config.local.php` sovrascrive i valori di `config.php`

### Opzioni di configurazione principali

```php
// Percorso base per i file
define('FM_DEFAULT_BASE_PATH', __DIR__ . '/uploads');

// Richiedi autenticazione
define('FM_REQUIRE_AUTH', false);

// Chiave sessione per autenticazione
define('FM_AUTH_SESSION_KEY', 'user');

// Protezione CSRF
define('FM_ENABLE_CSRF', true);

// Rate limiting (richieste/minuto)
define('FM_RATE_LIMIT', 30);

// Dimensione massima file upload (bytes)
define('FM_MAX_FILE_SIZE', 10 * 1024 * 1024);

// Estensioni file consentite
define('FM_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt']);

// Abilita logging
define('FM_ENABLE_LOGGING', true);

// CORS origins consentiti
define('FM_CORS_ALLOWED_ORIGINS', ['https://tuo-dominio.com']);
```

## Integrazione come Modulo

### Opzione 1: Iframe

```html
<iframe src="path/to/filemanager/index.php" width="100%" height="600"></iframe>
```

### Opzione 2: Inline (PHP include)

```php
<?php
// Definisci la cartella base prima dell'include
$fm_base_path = '/var/www/my-project/uploads';
?>
<link rel="stylesheet" href="filemanager/style.css">
<div id="fileManager" data-base-path="<?= $fm_base_path ?>"></div>
<script src="filemanager/filemanager.js"></script>
```

### Opzione 3: Custom base path via URL

```html
<iframe src="filemanager/index.php?base_path=/custom/uploads"></iframe>
```

### Opzione 4: Integrazione completa (embedded)

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

## Struttura File

```
filemanager/
├── index.php              # UI principale
├── api.php                # Backend API
├── config.php             # Configurazione predefinita
├── config.local.example.php # Esempio configurazione locale
├── FileManagerHelper.php   # Classe helper riutilizzabile
├── auth.php               # Autenticazione (usa configurazione)
├── style.css              # Stili
├── filemanager.js         # Logica AJAX
├── uploads/               # Directory upload (creata automaticamente)
├── logs/                  # Directory log (creata automaticamente)
└── README.md              # Documentazione
```

## Integrazione in progetti con autenticazione

Il modulo è progettato per essere integrato in applicazioni PHP che gestiscono l'autenticazione.

### 1. Abilitare l'autenticazione

In `config.local.php`:

```php
define('FM_REQUIRE_AUTH', true);
define('FM_AUTH_SESSION_KEY', 'user_id'); // o la tua chiave sessione
```

### 2. Configurazione CORS

In `config.local.php`:

```php
define('FM_CORS_ALLOWED_ORIGINS', ['https://tuo-dominio.com', 'https://app.tuo-dominio.it']);
define('FM_CORS_ALLOW_CREDENTIALS', true);
```

### 3. Configurazione logging

Il log viene scritto nella directory configurata (default: `logs/filemanager.log`). Assicurati che la directory sia scrivibile dal server.

In `config.local.php`:

```php
define('FM_ENABLE_LOGGING', true);
define('FM_LOG_FILE', __DIR__ . '/logs/filemanager.log');
```

### 4. Personalizzazione whitelist upload

In `config.local.php`:

```php
define('FM_ALLOWED_EXTENSIONS', [
    'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp',
    'pdf', 'doc', 'docx', 'txt'
]);
```

### 5. Rate limiting

Configurabile in `config.local.php`:

```php
define('FM_RATE_LIMIT', 30);           // richieste/minuto
define('FM_RATE_LIMIT_WINDOW', 60);    // secondi
```

## API Endpoints

Tutti gli endpoint accettano `base_path` come parametro opzionale.

| Azione | Metodo | Descrizione |
|--------|--------|-------------|
| `get_csrf_token` | GET | Ottieni token CSRF |
| `list` | GET | Lista contenuti directory |
| `create_folder` | POST | Crea nuova cartella |
| `delete` | POST | Elimina file/cartella |
| `rename` | POST | Rinomina file/cartella |
| `upload` | POST | Carica file |
| `download` | GET | Scarica file |

## Helper Class Riutilizzabile

La classe `FileManagerHelper` fornisce funzioni utility riutilizzabili per integrazione:

```php
require_once 'FileManagerHelper.php';

// Sanitizza input per logging
$safe = FileManagerHelper::sanitizeLog($input);

// Formatta dimensione file
$size = FileManagerHelper::formatSize(1024 * 1024); // "1.00 MB"

// Verifica path sicuro
if (FileManagerHelper::isPathSafe($path)) { /* ... */ }

// Verifica path within base
if (FileManagerHelper::isPathWithinBase($target, $base)) { /* ... */ }

// Elimina ricorsivamente
FileManagerHelper::deleteRecursive($path);

// Assicura directory esista
FileManagerHelper::ensureDirectory($path, 0755);

// Log con gestione errori
FileManagerHelper::log($message, $logFile);
```

## Requisiti

- PHP 7.4+ (consigliato)
- Apache/Nginx con supporto PHP
- Permessi di scrittura sulla directory target
- Sessioni PHP abilitate (per autenticazione e rate limiting)
- Accesso in scrittura alla directory `logs/` del modulo

## Sicurezza

- **CORS**: configurabile via `FM_CORS_ALLOWED_ORIGINS`
- **Autenticazione**: opzionale, configurabile via `FM_REQUIRE_AUTH`
- **CSRF Protection**: abilitabile via `FM_ENABLE_CSRF`
- **Whitelist upload**: configurabile via `FM_ALLOWED_EXTENSIONS`
- **Logging**: operazioni loggate con gestione errori
- **Rate limiting**: basato su IP, configurabile
- **Permessi**: configurabili via `FM_FILE_PERMISSIONS` e `FM_DIR_PERMISSIONS`
- **Protezione path traversal**: blocco accesso fuori dalla directory base
- **Messaggi di errore**: non espongono percorsi interni
- **Error handling**: try-catch per operazioni critiche

## Miglioramenti per Affidabilità

- **Configurazione centralizzata**: facile personalizzazione senza modificare codice
- **Helper class**: codice riutilizzabile e testabile
- **Error handling migliorato**: try-catch per operazioni critiche
- **Logging robusto**: gestione errori nella scrittura log
- **Rate limiting IP-based**: non aggirabile via session
- **Validazione configurazione**: verifica directory scrivibili
- **Funzioni helper spostate**: fuori dallo switch case per migliore organizzazione
- **Sanitizzazione input centralizzata**: in FileManagerHelper
- **Cross-platform**: gestione path Windows/Unix

## Licenza

MIT
