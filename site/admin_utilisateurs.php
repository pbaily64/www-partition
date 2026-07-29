<?php
// admin_utilisateurs.php - Gestion des utilisateurs (admin uniquement)
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('liste.php');
}

$success = '';
$error = '';

// Ajout d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } else {
        // Vérifier si l'utilisateur existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetchColumn() > 0) {
            $error = 'Ce nom d\'utilisateur ou cet email existe déjà !.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$username, $email, $password_hash, $role])) {
                $success = 'Utilisateur ajouté avec succès.';
            } else {
                $error = 'Erreur lors de l\'ajout de l\'utilisateur.';
            }
        }
    }
}

// Désactivation d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'desactiver') {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    // Ne pas se désactiver soi-même
    if ($user_id === $_SESSION['user_id']) {
        $error = 'Vous ne pouvez pas vous désactiver vous-même.';
    } elseif ($user_id > 0) {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET actif = 0 WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success = 'Utilisateur désactivé avec succès.';
        }
    }
}

// Activation d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activer') {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id > 0) {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET actif = 1 WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success = 'Utilisateur activé avec succès.';
        }
    }
}

// Suppression dÃƒÂ©finitive d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    // Ne pas se supprimer soi-même
    if ($user_id === $_SESSION['user_id']) {
        $error = 'Vous ne pouvez pas vous supprimer vous-même.';
    } elseif ($user_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success = 'Utilisateur supprimé avec succès.';
        }
    }
}

// RÃƒÂ©cupÃƒÂ©ration de la liste des utilisateurs
$stmt = $pdo->query("SELECT id, username, email, role, actif, date_creation, derniere_connexion FROM utilisateurs ORDER BY username");
$utilisateurs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - <?php echo SITE_NAME; ?></title>
	
	<link rel="icon" type="image/x-icon" href="favicon.ico">
	<link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
	<link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
	<link rel="icon" type="image/svg+xml" href="favicon.svg">
	<link rel="apple-touch-icon" sizes="180x180" href="favicon-256x256.png">
	<link rel="manifest" href="site.webmanifest">
	<meta name="theme-color" content="#667eea">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="liste.php">
                <i class="bi bi-music-note-beamed me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="liste.php">
                            <i class="bi bi-list-ul me-1"></i>Liste des chants
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ajouter_chant.php">
                            <i class="bi bi-plus-circle me-1"></i>Ajouter un chant
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ajouter_partitions_masse.php">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Ajout en masse
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear-fill me-1"></i>Administration
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="admin_utilisateurs.php">
                                <i class="bi bi-people me-1"></i>Gérer les utilisateurs
                            </a></li>
                            <li><a class="dropdown-item" href="admin_chants.php">
                                <i class="bi bi-music-note-list me-1"></i>Gérer les chants
                            </a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?php echo escape($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profil.php">
                                <i class="bi bi-person-gear me-1"></i>Mon profil
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="mb-4"><i class="bi bi-people-fill me-2"></i>Gestion des utilisateurs</h2>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo escape($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo escape($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-list me-2"></i>Liste des utilisateurs</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nom d'utilisateur</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($utilisateurs as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo escape($user['username']); ?></strong>
                                            <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                                <span class="badge bg-info">Vous</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo escape($user['email']); ?></td>
                                        <td>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span class="badge bg-danger">Administrateur</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Utilisateur</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['actif']): ?>
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Désactivé</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                <?php if ($user['actif']): ?>
                                                    <form method="POST" style="display:inline;" 
                                                          onsubmit="return confirm('Désactiver cet utilisateur ?');">
                                                        <input type="hidden" name="action" value="desactiver">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning" title="Désactiver">
                                                            <i class="bi bi-pause-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="activer">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Activer">
                                                            <i class="bi bi-play-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" style="display:inline;" 
                                                      onsubmit="return confirm('Supprimer dÃƒÂ©finitivement cet utilisateur ?');">
                                                    <input type="hidden" name="action" value="supprimer">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Ajouter un utilisateur</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="admin_utilisateurs.php">
                            <input type="hidden" name="action" value="ajouter">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text text-muted">Minimum 6 caractères</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Rôle</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="user">Utilisateur</option>
                                    <option value="admin">Administrateur</option>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-plus-circle me-1"></i>Ajouter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center text-muted">
            <small>&copy; <?php echo date('Y'); ?> - <?php echo SITE_NAME; ?></small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
