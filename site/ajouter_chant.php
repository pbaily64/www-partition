<?php
// ajouter_chant.php - Formulaire d'ajout d'un chant
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference = trim($_POST['reference'] ?? '');
    $sous_reference = trim($_POST['sous_reference'] ?? '') ?: null;
    $titre = trim($_POST['titre'] ?? '');
    $compositeurs = trim($_POST['compositeurs'] ?? '') ?: null;
    $auteurs = trim($_POST['auteurs'] ?? '') ?: null;
    
    // Validation
    if (empty($reference) || empty($titre)) {
        $error = 'La référence et le titre sont obligatoires.';
    } else {
        // Vérifier si la référence existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chants WHERE reference = ? AND (sous_reference = ? OR (sous_reference IS NULL AND ? IS NULL))");
        $stmt->execute([$reference, $sous_reference, $sous_reference]);
        
        if ($stmt->fetchColumn() > 0) {
            $error = 'Cette référence existe déjà .';
        } else {
            $a_partition = 0;
            $fichier_pdf = null;
            $chemin_pdf = null;
            
            // Gestion de l'upload du fichier PDF
            if (isset($_FILES['partition']) && $_FILES['partition']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['partition'];
                
                // Vérification du type de fichier
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if ($mimeType !== 'application/pdf') {
                    $error = 'Seuls les fichiers PDF sont acceptés.';
                } elseif ($file['size'] > MAX_FILE_SIZE) {
                    $error = 'Le fichier est trop volumineux (maximum 20 MB).';
                } else {
                    // Génération d'un nom de fichier unique
                    $extension = 'pdf';
                    $filename = uniqid('partition_') . '.' . $extension;
                    $destination = UPLOAD_DIR . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $a_partition = 1;
                        $fichier_pdf = $filename;
                        $chemin_pdf = $destination;
                    } else {
                        $error = 'Erreur lors de l\'upload du fichier.';
                    }
                }
            }
            
            if (empty($error)) {
                // Insertion dans la base de données
                $stmt = $pdo->prepare("
                    INSERT INTO chants (reference, sous_reference, titre, compositeurs, auteurs, a_partition, fichier_pdf, chemin_pdf, cree_par)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$reference, $sous_reference, $titre, $compositeurs, $auteurs, $a_partition, $fichier_pdf, $chemin_pdf, $_SESSION['user_id']])) {
                    $success = 'Le chant a été ajouté avec succès.';
                    // Réinitialisation du formulaire
                    $_POST = [];
                } else {
                    $error = 'Erreur lors de l\'ajout du chant.';
                }
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
    <title>Ajouter un chant - <?php echo SITE_NAME; ?></title>

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
                        <a class="nav-link active" href="ajouter_chant.php">
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Ajouter un chant</h4>
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

                        <form method="POST" action="ajouter_chant.php" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="reference" class="form-label">
                                        <i class="bi bi-hash me-1"></i>Référence <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="reference" name="reference" 
                                           value="<?php echo escape($_POST['reference'] ?? ''); ?>" 
                                           placeholder="Ex: A001" required>
                                    <small class="form-text text-muted">Format alphanumérique</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="sous_reference" class="form-label">
                                        <i class="bi bi-hash me-1"></i>Sous-référence
                                    </label>
                                    <input type="text" class="form-control" id="sous_reference" name="sous_reference" 
                                           value="<?php echo escape($_POST['sous_reference'] ?? ''); ?>" 
                                           placeholder="Ex: A">
                                    <small class="form-text text-muted">Optionnel</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="titre" class="form-label">
                                    <i class="bi bi-music-note me-1"></i>Titre <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="titre" name="titre" 
                                       value="<?php echo escape($_POST['titre'] ?? ''); ?>" 
                                       placeholder="Titre du chant" required>
                            </div>

                            <div class="mb-3">
                                <label for="compositeurs" class="form-label">
                                    <i class="bi bi-person me-1"></i>Compositeur(s)
                                </label>
                                <input type="text" class="form-control" id="compositeurs" name="compositeurs" 
                                       value="<?php echo escape($_POST['compositeurs'] ?? ''); ?>" 
                                       placeholder="Nom(s) du/des compositeur(s)">
                                <small class="form-text text-muted">Séparer par des virgules si plusieurs</small>
                            </div>

                            <div class="mb-3">
                                <label for="auteurs" class="form-label">
                                    <i class="bi bi-pen me-1"></i>Auteur(s)
                                </label>
                                <input type="text" class="form-control" id="auteurs" name="auteurs" 
                                       value="<?php echo escape($_POST['auteurs'] ?? ''); ?>" 
                                       placeholder="Nom(s) de(s) auteur(s) ou référence biblique">
                                <small class="form-text text-muted">Séparer par des virgules si plusieurs</small>
                            </div>

                            <div class="mb-4">
                                <label for="partition" class="form-label">
                                    <i class="bi bi-file-pdf me-1"></i>Partition (PDF)
                                </label>
                                <input type="file" class="form-control" id="partition" name="partition" accept=".pdf">
                                <small class="form-text text-muted">
                                    Fichier PDF uniquement, maximum 20 MB
                                </small>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="liste.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-1"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle me-1"></i>Ajouter le chant
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
