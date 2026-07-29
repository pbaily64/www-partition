<?php
// profil.php - Page de profil de l'utilisateur
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$success = '';
$error = '';

// Récupération des informations de l'utilisateur
$stmt = $pdo->prepare("SELECT username, email FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    redirect('logout.php');
}

// Traitement de la modification du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        // Vérification du mot de passe actuel
        $stmt = $pdo->prepare("SELECT password_hash FROM utilisateurs WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();
        
        if (!password_verify($current_password, $hash)) {
            $error = 'Le mot de passe actuel est incorrect.';
        } else {
            // Mise à jour du mot de passe
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateurs SET password_hash = ? WHERE id = ?");
            
            if ($stmt->execute([$new_hash, $_SESSION['user_id']])) {
                $success = 'Votre mot de passe a été modifié avec succès.';
                $_POST = []; // Réinitialiser le formulaire
            } else {
                $error = 'Erreur lors de la modification du mot de passe.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - <?php echo SITE_NAME; ?></title>
	
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
                    <?php if (isAdmin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear-fill me-1"></i>Administration
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="admin_utilisateurs.php">
                                <i class="bi bi-people me-1"></i>Gérer les utilisateurs
                            </a></li>
                            <li><a class="dropdown-item" href="admin_chants.php">
                                <i class="bi bi-music-note-list me-1"></i>Gérer les chants
                            </a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?php echo escape($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item active" href="profil.php">
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
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-person-circle me-2"></i>Mon profil</h4>
                    </div>
                    <div class="card-body">
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

                        <!-- Informations de base (lecture seule) -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2 mb-3">
                                <i class="bi bi-info-circle me-2"></i>Informations du compte
                            </h5>
                            
                            <div class="mb-3">
                                <label class="form-label text-muted">Nom d'utilisateur</label>
                                <div class="form-control-plaintext">
                                    <strong><?php echo escape($user['username']); ?></strong>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-muted">Adresse email</label>
                                <div class="form-control-plaintext">
                                    <strong><?php echo escape($user['email']); ?></strong>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted">Rôle</label>
                                <div class="form-control-plaintext">
                                    <?php if (isAdmin()): ?>
                                        <span class="badge bg-danger">Administrateur</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Utilisateur</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Modification du mot de passe -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2 mb-3">
                                <i class="bi bi-key me-2"></i>Modifier mon mot de passe
                            </h5>

                            <form method="POST" action="profil.php">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">
                                        Mot de passe actuel <span class="text-danger">*</span>
                                    </label>
                                    <input type="password" class="form-control" id="current_password" 
                                           name="current_password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">
                                        Nouveau mot de passe <span class="text-danger">*</span>
                                    </label>
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" required>
                                    <small class="form-text text-muted">Minimum 6 caractères</small>
                                </div>

                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">
                                        Confirmer le nouveau mot de passe <span class="text-danger">*</span>
                                    </label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-1"></i>Modifier le mot de passe
                                    </button>
                                    <a href="liste.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left me-1"></i>Retour à la liste
                                    </a>
                                </div>
                            </form>
                        </div>
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
