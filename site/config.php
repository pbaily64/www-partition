<?php
// config.php - Configuration de la base de données et paramètres généraux

// Configuration de la base de données
define('DB_HOST', 'mariadb');  // Nom du service Docker MariaDB
define('DB_NAME', 'partitions_catholiques');
define('DB_USER', 'partitions_user');  // À adapter selon votre configuration
define('DB_PASS', '#############');  // À adapter selon votre configuration
define('DB_CHARSET', 'utf8mb4');

// Configuration du site
define('SITE_NAME', 'Gestion des Partitions');
define('SITE_URL', 'https://partition.famillebaily.be');  // À adapter
define('UPLOAD_DIR', __DIR__ . '/uploads/partitions/');
define('MAX_FILE_SIZE', 10485760); // 10 MB en octets

// Sécurité
define('SESSION_LIFETIME', 3600); // 1 heure en secondes

// Création de la connexion PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Forcer l'encodage UTF-8 pour la connexion MySQL
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET CHARACTER SET utf8mb4");
} catch (PDOException $e) {
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}

// Démarrage de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    // Forcer l'encodage UTF-8 pour toutes les sorties
    header('Content-Type: text/html; charset=UTF-8');
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');
    
    // Régénération de l'ID de session pour la sécurité
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > SESSION_LIFETIME) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Fonction de vérification de connexion
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Fonction de vérification du rôle administrateur
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Fonction de redirection
function redirect($url) {
    header("Location: $url");
    exit();
}

// Fonction de sécurisation des sorties HTML
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Vérification et création du répertoire d'upload
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
?>
