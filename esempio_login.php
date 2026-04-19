<?php
/**
 * Esempio di integrazione del File Manager con autenticazione database
 * 
 * Questo file dimostra come integrare il modulo filemanager in un'applicazione
 * che utilizza un database per l'autenticazione degli utenti.
 * 
 * CONFIGURAZIONE:
 * - Sostituisci i parametri di connessione con quelli del tuo database
 * - Modifica la tabella utenti in base al tuo schema
 * - Integra questo file nel tuo sistema di autenticazione esistente
 */

// ============================================================
// CONFIGURAZIONE DATABASE - DA MODIFICARE
// ============================================================

define('DB_HOST', 'localhost');           // Host del database
define('DB_NAME', 'nome_database');       // Nome del database
define('DB_USER', 'username');            // Username database
define('DB_PASS', 'password');            // Password database
define('DB_CHARSET', 'utf8mb4');          // Charset

// ============================================================
// CONFIGURAZIONE FILE MANAGER
// ============================================================

// Percorso base per il file manager (può essere personalizzato per utente)
define('FM_BASE_PATH', __DIR__ . '/uploads');

// ============================================================
// FUNZIONI DATABASE
// ============================================================

/**
 * Connessione al database (PDO)
 * @return PDO
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die('Errore connessione database');
        }
    }
    
    return $pdo;
}

/**
 * Verifica credenziali utente (esempio con password hashata)
 * 
 * @param string $username
 * @param string $password
 * @return array|false Restituisce dati utente se autenticato, false altrimenti
 */
function authenticateUser($username, $password) {
    $pdo = getDbConnection();
    
    // Prepared statement per prevenire SQL injection
    $stmt = $pdo->prepare(
        'SELECT id, username, password_hash, email, role 
         FROM users 
         WHERE username = :username AND active = 1'
    );
    
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();
    
    // Verifica password con password_hash
    if ($user && password_verify($password, $user['password_hash'])) {
        // Aggiorna ultimo login (opzionale)
        $update = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
        $update->execute(['id' => $user['id']]);
        
        return $user;
    }
    
    return false;
}

/**
 * Verifica se l'utente ha permesso di accedere al file manager
 * 
 * @param array $user
 * @return bool
 */
function userCanAccessFileManager($user) {
    // Ruoli ammessi al filemanager (configura secondo le tue esigenze)
    $allowedRoles = ['admin', 'manager', 'user'];
    
    return in_array($user['role'] ?? '', $allowedRoles);
}

// ============================================================
// GESTIONE SESSIONE
// ============================================================

/**
 * Inizializza la sessione in modo sicuro
 */
function initSecureSession() {
    // Configurazioni di sicurezza sessione
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    // Rigenera ID sessione per prevenire session fixation
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['created'] = time();
    }
    
    // Timeout inattività (30 minuti)
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Login utente
 * 
 * @param string $username
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function login($username, $password) {
    $user = authenticateUser($username, $password);
    
    if ($user === false) {
        return ['success' => false, 'message' => 'Credenziali non valide'];
    }
    
    if (!userCanAccessFileManager($user)) {
        return ['success' => false, 'message' => 'Accesso al file manager non consentito'];
    }
    
    // Imposta variabili di sessione
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role']
    ];
    
    // Opzionale: percorso base personalizzato per utente
    // $_SESSION['fm_base_path'] = __DIR__ . '/uploads/' . $user['id'];
    
    return ['success' => true, 'message' => 'Login effettuato'];
}

/**
 * Logout utente
 */
function logout() {
    // Distruggi sessione in modo sicuro
    $_SESSION = [];
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    session_destroy();
}

// ============================================================
// GESTIONE RICHIESTE
// ============================================================

/**
 * Gestisce il login (esempio base)
 */
function handleLoginRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => 'Metodo non consentito'];
    }
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username e password richiesti'];
    }
    
    return login($username, $password);
}

/**
 * Gestisce il logout
 */
function handleLogoutRequest() {
    logout();
    return ['success' => true, 'message' => 'Logout effettuato'];
}

// ============================================================
// ROUTER SEMPLICE
// ============================================================

initSecureSession();

// Gestione azione (esempio: action=login|logout|fm)
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        header('Content-Type: application/json');
        echo json_encode(handleLoginRequest());
        break;
        
    case 'logout':
        header('Content-Type: application/json');
        echo json_encode(handleLogoutRequest());
        break;
        
    case 'fm':
        // Abilita autenticazione richiesta per il file manager
        define('REQUIRE_AUTH', true);
        
        // Passa il percorso base al file manager (opzionale)
        // $fm_base_path = $_SESSION['fm_base_path'] ?? FM_BASE_PATH;
        
        // Includi il file manager
        require_once __DIR__ . '/filemanager/api.php';
        break;
        
    default:
        // Pagina HTML di esempio per il login
        ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - File Manager</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 300px; }
        h2 { margin-top: 0; color: #333; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { color: red; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Accesso File Manager</h2>
        <form id="loginForm">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Accedi</button>
        </form>
        <p class="error" id="errorMsg"></p>
    </div>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const response = await fetch('?action=login', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                window.location.href = '?action=fm';
            } else {
                document.getElementById('errorMsg').textContent = result.message;
            }
        });
    </script>
</body>
</html>
        <?php
        break;
}

/**
 * ESEMPIO DI SCHEMA DATABASE
 * 
 * CREATE TABLE users (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     username VARCHAR(50) NOT NULL UNIQUE,
 *     password_hash VARCHAR(255) NOT NULL,
 *     email VARCHAR(100),
 *     role ENUM('admin', 'manager', 'user', 'guest') DEFAULT 'user',
 *     active TINYINT(1) DEFAULT 1,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     last_login DATETIME
 * );
 * 
 * ESEMPIO DI INSERIMENTO UTENTE:
 * INSERT INTO users (username, password_hash, email, role) 
 * VALUES ('admin', '$2y$10$...hash bcrypt...', 'admin@example.com', 'admin');
 */