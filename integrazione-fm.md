# File Manager - Guida all'Integrazione

## Struttura del Progetto

```
filemanager/
├── index.php          # UI principale
├── api.php            # Backend API
├── style.css          # Stili CSS
├── filemanager.js     # Logica JavaScript/AJAX
├── uploads/           # Directory upload (creata automaticamente)
└── README.md          # Documentazione base
```

---

## Integrazione Rapida

### 1. Copia la cartella

Copia l'intera cartella `filemanager/` nel tuo progetto.

### 2. Percorso base predefinito

La directory `uploads/` viene creata automaticamente nella stessa cartella del file manager. Per cambiare:

**Via URL (iframe o link):**
```html
<iframe src="filemanager/?base_path=/var/www/progetto/uploads"></iframe>
```

**Via PHP (embedded):**
```php
<?php
$fm_base_path = '/var/www/progetto/uploads';
?>
<iframe src="filemanager/?base_path=<?= urlencode($fm_base_path) ?>"></iframe>
```

---

## Modalità di Integrazione

### Opzione 1: Iframe (Consigliata)

```html
<!-- Iframe responsive -->
<iframe src="filemanager/" 
        width="100%" 
        height="600" 
        style="border: 1px solid #ddd; border-radius: 8px;">
</iframe>
```

**Con percorso personalizzato:**
```html
<iframe src="filemanager/?base_path=/percorso/assoluto" 
        width="100%" 
        height="600">
</iframe>
```

### Opzione 2: Embedded (Inline)

```php
<?php
$fm_base_path = __DIR__ . '/../../uploads/';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gestione File</title>
    <link rel="stylesheet" href="filemanager/style.css">
</head>
<body>
    <h1>I miei File</h1>
    <div id="fileManager"></div>
    <script src="filemanager/filemanager.js"></script>
</body>
</html>
```

### Opzione 3: Pagina PHP dedicata

```php
<?php
// config.php
define('FM_BASE_PATH', '/var/www/progetto/uploads');

// index.php
require_once 'filemanager/api.php';
?>
```

---

## Personalizzazione UI

### Cambiare il titolo

In `index.php`:
```html
<h1>Gestione Documenti</h1>
```

### Modificare i colori

In `style.css`, modifica le variabili colore principali:

```css
.fm-header {
    background: #1a1a2e;  /* Colore header */
}

.btn-upload, .btn-folder {
    background: #e94560;  /* Colore pulsanti */
}

.fm-toolbar {
    background: #16213e;  /* Colore toolbar selezione */
}
```

### Nascondere elementi UI

```css
/* Nascondi pulsante nuova cartella */
.btn-folder { display: none; }

/* Nascondi drag & drop */
.fm-dropzone { display: none; }

/* Nascondi filtri */
.fm-filters { display: none; }
```

### Cambiare lingua

In `index.php` e `filemanager.js`, modifica i testi:
- "Nuova Cartella" / "Upload File"
- "Conferma eliminazione"
- "Nessun file o cartella"

---

## Personalizzazione Backend

### Limitare dimensione upload

In `php.ini`:
```ini
upload_max_filesize = 50M
post_max_size = 50M
```

### Permettere solo certi tipi di file

In `api.php`, aggiungi alla sezione `upload`:

```php
$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
$ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    $errors[] = $name . ': tipo di file non consentito';
    continue;
}
```

### Log operazioni

In `api.php`, le operazioni vengono già loggate automaticamente in `logs/filemanager.log`.

---

## API Endpoints

### `GET api.php?action=list`

Lista contenuti directory.

| Parametro | Descrizione |
|-----------|-------------|
| `path` | Percorso relativo dalla base |
| `sort_by` | `name`, `size`, `modified` |
| `sort_order` | `asc`, `desc` |
| `page` | Numero pagina |
| `per_page` | Elementi per pagina (default: 10) |
| `base_path` | Percorso base assoluto (opzionale) |

**Risposta:**
```json
{
  "success": true,
  "path": "sottocartella",
  "full_base_path": "/var/www/uploads",
  "items": [...],
  "pagination": {
    "page": 1,
    "per_page": 10,
    "total_items": 25,
    "total_pages": 3
  }
}
```

### `POST api.php?action=create_folder`

Crea una cartella.

| Parametro | Descrizione |
|-----------|-------------|
| `path` | Percorso dove creare |
| `name` | Nome cartella |

### `POST api.php?action=delete`

Elimina file o cartella.

| Parametro | Descrizione |
|-----------|-------------|
| `path` | Percorso elemento da eliminare |

### `POST api.php?action=rename`

Rinomina file o cartella.

| Parametro | Descrizione |
|-----------|-------------|
| `path` | Percorso elemento |
| `new_name` | Nuovo nome |

### `POST api.php?action=upload`

Carica file (multipart/form-data).

| Campo | Descrizione |
|-------|-------------|
| `files` | File da caricare |
| `path` | Directory target |

### `GET api.php?action=download`

Scarica un file.

| Parametro | Descrizione |
|-----------|-------------|
| `path` | Percorso file |

---

## Eventi JavaScript (Callbacks)

Puoi estendere `filemanager.js` per aggiungere callbacks:

```javascript
// Dopo l'upload
function afterUpload(uploadedFiles) {
    console.log('File caricati:', uploadedFiles);
    // es: aggiorna una lista nel tuo progetto
}

// Dopo l'eliminazione
function afterDelete(deletedPath) {
    console.log('Eliminato:', deletedPath);
}

// Dopo la rinomina
function afterRename(oldPath, newPath) {
    console.log('Rinominato:', oldPath, '->', newPath);
}
```

---

## Sicurezza

### Protezioni implementate

- **CORS**: whitelist di origin in `api.php`
- **Autenticazione**: opzionale via `define('REQUIRE_AUTH', true)` + `auth.php`
- **Whitelist upload**: solo tipi consentiti (`jpg, jpeg, png, pdf, doc, docx, txt`)
- **Logging**: operazioni registrate in `logs/filemanager.log`
- **Rate limiting**: 30 richieste/min per IP
- **Permessi**: directory `0750`, file `0640`
- **Path traversal**: blocco accesso fuori dalla directory base
- **Validazione nomi**: caratteri non consentiti rimossi
- **MIME type**: rilevamento automatico per download

### Abilitare autenticazione

```php
<?php
define('REQUIRE_AUTH', true); // Richiede $_SESSION['user']
require_once 'filemanager/api.php';
?>
```

### Configurare CORS

In `api.php`:
```php
$allowedOrigins = ['https://tuo-dominio.com'];
```

### Permessi cartella (produzione)

- Directory: `chmod 750`
- File: `chmod 640`
- Assicurati che il server possa scrivere nella directory `logs/` del modulo

---

## Troubleshooting

### Upload non funziona
- Verifica permessi cartella: `chmod 755 uploads/`
- Controlla `upload_max_filesize` in php.ini

### Directory non visualizzata
- Il percorso base deve essere assoluto
- Verifica che la cartella esista e sia leggibile

### CORS error (embedded)
- Assicurati che api.php sia nella stessa cartella di index.php
- Verifica che il tuo dominio sia nella whitelist `$allowedOrigins` in api.php

### Errore 401 Unauthorized
- Se usi `define('REQUIRE_AUTH', true)`, assicurati che la sessione contenga `$_SESSION['user']`
- Verifica che la sessione sia avviata prima di includere api.php

### Rate limiting (429 Too Many Requests)
- Il limite predefinito è 30 richieste/minuto per IP
- Se necessario, modifica il limite in api.php

### Log non written
- Verifica permessi di scrittura sulla directory `logs/` del modulo
- In alternativa, modifica il percorso in api.php

---

## Supporto

Per problemi o domande, verifica:
1. Console browser (F12) per errori JavaScript
2. Log PHP errori del server
3. Permessi file system
