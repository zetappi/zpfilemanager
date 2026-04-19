# Simple File Manager

Un modulo di file management leggero per PHP con supporto drag & drop, upload multipli, creazione ed eliminazione cartelle.

## Caratteristiche

- Navigazione tra cartelle
- Upload file multipli con drag & drop
- Creazione cartelle
- Eliminazione file e cartelle
- Design responsive
- Completamente asincrono (AJAX)

## Uso Standalone

> **Nota**: quando il file manager è integrato in un progetto più ampio, includere `auth.php` prima dell’inclusione per garantire che gli utenti siano autenticati.



1. Copia i file in una directory del tuo server PHP
2. Assicurati che la directory abbia permessi di scrittura
3. Accedi a `index.php`

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
├── index.php          # UI principale
├── api.php            # Backend API
├── style.css          # Stili
├── filemanager.js     # Logica AJAX
├── auth.php           # Autenticazione (optionale)
├── uploads/           # Directory upload (creata automaticamente)
└── README.md          # Documentazione base
```

## Integrazione in progetti con autenticazione

Il modulo è progettato per essere integrato in applicazioni PHP che gestiscono l'autenticazione.

### 1. Abilitare l'autenticazione

Nel file che include il filemanager, definisci la costante prima di includere `api.php`:

```php
<?php
define('REQUIRE_AUTH', true); // Richiede autenticazione
// Il filemanager richiederà $_SESSION['user'] attivo
?>
```

### 2. Configurazione CORS

In `api.php` modifica la whitelist degli origin consentiti:

```php
$allowedOrigins = ['https://tuo-dominio.com', 'https://app.tuo-dominio.it'];
```

### 3. Configurazione logging

Il log viene scritto in `logs/filemanager.log` (relativo alla directory del modulo). Assicurati che la directory `logs` sia scrivibile dal server.

### 4. Personalizzazione whitelist upload

In `api.php`, cerca `$allowedExtensions` per modificare i tipi di file consentiti.

### 5. Rate limiting

Il limite predefinito è 30 richieste/minuto per IP. Modificabile in `api.php`调整 `$SESSION['rl']` 的阈值。
filemanager/
├── index.php      # UI principale
├── api.php        # Backend API
├── style.css      # Stili
├── filemanager.js # Logica AJAX
└── README.md      # Documentazione
```

## API Endpoints

Tutti gli endpoint accettano `base_path` come parametro opzionale.

| Azione | Metodo | Descrizione |
|--------|--------|-------------|
| `list` | GET | Lista contenuti directory |
| `create_folder` | POST | Crea nuova cartella |
| `delete` | POST | Elimina file/cartella |
| `upload` | POST | Carica file |

## Requisiti

- PHP 7.4+ (consigliato)
- Apache/Nginx con supporto PHP
- Permessi di scrittura sulla directory target
- Sessioni PHP abilitate (per autenticazione e rate limiting)
- Accesso in scrittura alla directory `logs/` del modulo

## Sicurezza

- **CORS**: solo gli origin nella whitelist sono consentiti.
- **Autenticazione**: opzionale, attivabile con `define('REQUIRE_AUTH', true)`.
- **Whitelist upload**: tipi di file limitati (`jpg, jpeg, png, pdf, doc, docx, txt`).
- **Logging**: operazioni loggate in `logs/filemanager.log`.
- **Rate limiting**: 30 richieste/min per IP.
- **Permessi**: directory `0750`, file `0640`.
- **Protezione path traversal**: blocco accesso fuori dalla directory base.
- **Messaggi di errore**: non espongono percorsi interni.

## Licenza

MIT
# zpfilemanager
