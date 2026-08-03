<?php
// admin_chants.php - Gestion des chants (admin uniquement)
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('liste.php');
}

$success = '';
$error = '';
$chant_to_edit = null;

// Suppression d'un chant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    $chant_id = intval($_POST['chant_id'] ?? 0);
    
    if ($chant_id > 0) {
        // Récupérer les infos du chant pour supprimer le fichier
        $stmt = $pdo->prepare("SELECT fichier_pdf, chemin_pdf FROM chants WHERE id = ?");
        $stmt->execute([$chant_id]);
        $chant = $stmt->fetch();
        
        // Supprimer le fichier PDF s'il existe
        if ($chant && $chant['fichier_pdf'] && file_exists($chant['chemin_pdf'])) {
            unlink($chant['chemin_pdf']);
        }
        
        // Supprimer de la base de données
        $stmt = $pdo->prepare("DELETE FROM chants WHERE id = ?");
        if ($stmt->execute([$chant_id])) {
            $success = 'Chant supprimé avec succès.';
        }
    }
}

// Modification d'un chant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier') {
    $chant_id = intval($_POST['chant_id'] ?? 0);
    $reference = trim($_POST['reference'] ?? '');
    $sous_reference = trim($_POST['sous_reference'] ?? '') ?: null;
    $titre = trim($_POST['titre'] ?? '');
    $compositeurs = trim($_POST['compositeurs'] ?? '') ?: null;
    $auteurs = trim($_POST['auteurs'] ?? '') ?: null;
    
    if (empty($reference) || empty($titre)) {
        $error = 'La référence et le titre sont obligatoires.';
    } else {
        // Mise à jour de base
        $sql = "UPDATE chants SET reference = ?, sous_reference = ?, titre = ?, compositeurs = ?, auteurs = ? WHERE id = ?";
        $params = [$reference, $sous_reference, $titre, $compositeurs, $auteurs, $chant_id];
        
        // Gestion de l'upload d'une nouvelle partition
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
                // Récupérer l'ancien fichier pour le supprimer
                $stmt = $pdo->prepare("SELECT fichier_pdf, chemin_pdf FROM chants WHERE id = ?");
                $stmt->execute([$chant_id]);
                $old_chant = $stmt->fetch();
                
                // Supprimer l'ancien fichier s'il existe
                if ($old_chant && $old_chant['fichier_pdf'] && file_exists($old_chant['chemin_pdf'])) {
                    unlink($old_chant['chemin_pdf']);
                }
                
                // Upload du nouveau fichier
                $filename = uniqid('partition_') . '.pdf';
                $destination = UPLOAD_DIR . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $sql = "UPDATE chants SET reference = ?, sous_reference = ?, titre = ?, compositeurs = ?, auteurs = ?, a_partition = 1, fichier_pdf = ?, chemin_pdf = ? WHERE id = ?";
                    $params = [$reference, $sous_reference, $titre, $compositeurs, $auteurs, $filename, $destination, $chant_id];
                }
            }
        }
        
        if (empty($error)) {
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $success = 'Chant modifié avec succès.';
            } else {
                $error = 'Erreur lors de la modification.';
            }
        }
    }
}

// Chargement d'un chant pour édition
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM chants WHERE id = ?");
    $stmt->execute([$edit_id]);
    $chant_to_edit = $stmt->fetch();
}

// Paramètres de tri et recherche
$tri = $_GET['tri'] ?? 'titre';
$ordre = $_GET['ordre'] ?? 'ASC';
$recherche = trim($_GET['recherche'] ?? '');

// Paramètres de pagination
$par_page = intval($_GET['par_page'] ?? 25);
$page = max(1, intval($_GET['page'] ?? 1));

// Validation du tri
$tris_valides = ['titre', 'reference'];
if (!in_array($tri, $tris_valides)) {
    $tri = 'titre';
}

// Validation de l'ordre
if (!in_array($ordre, ['ASC', 'DESC'])) {
    $ordre = 'ASC';
}

// Validation du nombre par page
$options_par_page = [10, 25, 50, 75, 100, 250, 500];
if (!in_array($par_page, $options_par_page)) {
    $par_page = 25;
}

// Construction de la requête de comptage
$sql_count = "SELECT COUNT(*) FROM chants WHERE 1=1";
$params = [];

if (!empty($recherche)) {
    $sql_count .= " AND (titre LIKE ? OR reference LIKE ? OR sous_reference LIKE ? OR compositeurs LIKE ? OR auteurs LIKE ?)";
    $searchParam = "%$recherche%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
}

// Comptage total
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_chants = $stmt->fetchColumn();
$total_pages = ceil($total_chants / $par_page);

// Ajustement de la page si nécessaire
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}

// Construction de la requête principale
$sql = "SELECT id, reference, sous_reference, titre, compositeurs, auteurs, a_partition, fichier_pdf 
        FROM chants WHERE 1=1";

if (!empty($recherche)) {
    $sql .= " AND (titre LIKE ? OR reference LIKE ? OR sous_reference LIKE ? OR compositeurs LIKE ? OR auteurs LIKE ?)";
}

// Tri
if ($tri === 'reference') {
    $sql .= " ORDER BY reference $ordre, sous_reference $ordre";
} else {
    $sql .= " ORDER BY titre $ordre";
}

// Pagination
$offset = ($page - 1) * $par_page;
$sql .= " LIMIT ? OFFSET ?";
$params[] = $par_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$chants = $stmt->fetchAll();

// Fonction pour construire l'URL avec les paramètres existants
function buildUrl($newParams = []) {
    global $tri, $ordre, $recherche, $par_page, $page;
    $params = [
        'tri' => $tri,
        'ordre' => $ordre,
        'recherche' => $recherche,
        'par_page' => $par_page,
        'page' => $page
    ];
    // Conserver le paramètre edit si présent
    if (isset($_GET['edit'])) {
        $params['edit'] = intval($_GET['edit']);
    }
    $params = array_merge($params, $newParams);
    // Nettoyer les paramètres vides
    $params = array_filter($params, function($value) {
        return $value !== '' && $value !== null;
    });
    return 'admin_chants.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des chants - <?php echo SITE_NAME; ?></title>
	
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear-fill me-1"></i>Administration
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="admin_utilisateurs.php">
                                <i class="bi bi-people me-1"></i>Gérer les utilisateurs
                            </a></li>
                            <li><a class="dropdown-item active" href="admin_chants.php">
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
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <h2 class="mb-4"><i class="bi bi-music-note-list me-2"></i>Gestion des chants</h2>

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

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="admin_chants.php" class="row g-3">
                    <?php if (isset($_GET['edit'])): ?>
                        <input type="hidden" name="edit" value="<?php echo intval($_GET['edit']); ?>">
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label for="recherche" class="form-label">
                            <i class="bi bi-search me-1"></i>Rechercher
                        </label>
                        <input type="text" class="form-control" id="recherche" name="recherche" 
                               placeholder="Titre, référence, compositeur, auteur..." 
                               value="<?php echo escape($recherche); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="tri" class="form-label">
                            <i class="bi bi-sort-down me-1"></i>Trier par
                        </label>
                        <select class="form-select" id="tri" name="tri">
                            <option value="titre" <?php echo $tri === 'titre' ? 'selected' : ''; ?>>Titre</option>
                            <option value="reference" <?php echo $tri === 'reference' ? 'selected' : ''; ?>>Référence</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="ordre" class="form-label">Ordre</label>
                        <select class="form-select" id="ordre" name="ordre">
                            <option value="ASC" <?php echo $ordre === 'ASC' ? 'selected' : ''; ?>>Croissant</option>
                            <option value="DESC" <?php echo $ordre === 'DESC' ? 'selected' : ''; ?>>Décroissant</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="par_page" class="form-label">
                            <i class="bi bi-card-list me-1"></i>Afficher
                        </label>
                        <select class="form-select" id="par_page" name="par_page">
                            <option value="10" <?php echo $par_page === 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $par_page === 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $par_page === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="75" <?php echo $par_page === 75 ? 'selected' : ''; ?>>75</option>
                            <option value="100" <?php echo $par_page === 100 ? 'selected' : ''; ?>>100</option>
                            <option value="250" <?php echo $par_page === 250 ? 'selected' : ''; ?>>250</option>
                            <option value="500" <?php echo $par_page === 500 ? 'selected' : ''; ?>>500</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i>Filtrer
                        </button>
                    </div>
                    <div class="col-12">
                        <a href="admin_chants.php<?php echo isset($_GET['edit']) ? '?edit=' . intval($_GET['edit']) : ''; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i>Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-<?php echo $chant_to_edit ? '8' : '12'; ?>">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-list me-2"></i>Liste des chants 
                            (<?php echo count($chants); ?> affichés sur <?php echo $total_chants; ?> au total - Page <?php echo $page; ?>/<?php echo max(1, $total_pages); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($chants) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Titre</th>
                                        <th>Référence</th>
                                        <th>Auteurs</th>
                                        <th>Compositeurs</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($chants as $chant): ?>
                                    <tr>
                                        <td><strong><?php echo escape($chant['titre']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo escape($chant['reference']); ?>
                                                <?php if ($chant['sous_reference']): ?>
                                                    -<?php echo escape($chant['sous_reference']); ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td><?php echo escape($chant['auteurs'] ?? '-'); ?></td>
                                        <td><?php echo escape($chant['compositeurs'] ?? '-'); ?></td>
                                        <td>
                                            <a href="<?php echo buildUrl(['edit' => $chant['id']]); ?>" 
                                               class="btn btn-sm btn-warning" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" style="display:inline;" 
                                                  onsubmit="return confirm('Supprimer ce chant définitivement ?');">
                                                <input type="hidden" name="action" value="supprimer">
                                                <input type="hidden" name="chant_id" value="<?php echo $chant['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <!-- Bouton Première page -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo buildUrl(['page' => 1]); ?>">
                                        <i class="bi bi-chevron-double-left"></i>
                                    </a>
                                </li>
                                
                                <!-- Bouton Page précédente -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo buildUrl(['page' => $page - 1]); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php
                                // Afficher les numéros de page
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo buildUrl(['page' => $i]); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <!-- Bouton Page suivante -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo buildUrl(['page' => $page + 1]); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                
                                <!-- Bouton Dernière page -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo buildUrl(['page' => $total_pages]); ?>">
                                        <i class="bi bi-chevron-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucun chant trouvé.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($chant_to_edit): ?>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Modifier le chant</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="admin_chants.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="modifier">
                            <input type="hidden" name="chant_id" value="<?php echo $chant_to_edit['id']; ?>">
                            
                            <div class="mb-3">
                                <label for="reference" class="form-label">Référence</label>
                                <input type="text" class="form-control" id="reference" name="reference" 
                                       value="<?php echo escape($chant_to_edit['reference']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sous_reference" class="form-label">Sous-référence</label>
                                <input type="text" class="form-control" id="sous_reference" name="sous_reference" 
                                       value="<?php echo escape($chant_to_edit['sous_reference'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="titre" class="form-label">Titre</label>
                                <input type="text" class="form-control" id="titre" name="titre" 
                                       value="<?php echo escape($chant_to_edit['titre']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="compositeurs" class="form-label">Compositeurs</label>
                                <input type="text" class="form-control" id="compositeurs" name="compositeurs" 
                                       value="<?php echo escape($chant_to_edit['compositeurs'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="auteurs" class="form-label">Auteurs</label>
                                <input type="text" class="form-control" id="auteurs" name="auteurs" 
                                       value="<?php echo escape($chant_to_edit['auteurs'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="partition" class="form-label">
                                    <?php if ($chant_to_edit['a_partition']): ?>
                                        Remplacer la partition
                                    <?php else: ?>
                                        Ajouter une partition
                                    <?php endif; ?>
                                </label>
                                <input type="file" class="form-control" id="partition" name="partition" accept=".pdf">
                                <?php if ($chant_to_edit['a_partition']): ?>
                                    <small class="form-text text-success">
                                        <i class="bi bi-check-circle me-1"></i>Partition existante
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-check-circle me-1"></i>Enregistrer
                                </button>
                                <a href="<?php echo buildUrl(['edit' => null]); ?>" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-1"></i>Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
