<?php
// liste.php - Liste des chants avec recherche et tri
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
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
    $params = array_merge($params, $newParams);
    // Nettoyer les paramètres vides
    $params = array_filter($params, function($value) {
        return $value !== '' && $value !== null;
    });
    return 'liste.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des chants - <?php echo SITE_NAME; ?></title>
	<link rel="icon" type="image/x-icon" href="favicon.ico">
	<link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
	<link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
	<link rel="icon" type="image/svg+xml" href="favicon.svg">
	<link rel="apple-touch-icon" sizes="180x180" href="favicon-256x256.png">
	<link rel="manifest" href="site.webmanifest">
	<meta name="theme-color" content="#667eea">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .navbar-brand {
            font-weight: bold;
        }
        .search-bar {
            max-width: 500px;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .btn-pdf {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .reference-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }
        .pagination-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .pagination .page-link {
            color: #667eea;
        }
        .pagination .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
        }
    </style>
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
                        <a class="nav-link active" href="liste.php">
                            <i class="bi bi-list-ul me-1"></i>Liste des chants
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ajouter_chant.php">
                            <i class="bi bi-plus-circle me-1"></i>Ajouter un chant
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
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="bi bi-music-note-list me-2"></i>Liste des chants</h2>
            </div>
            <div class="col-md-4 text-end">
                <a href="ajouter_chant.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i>Ajouter un chant
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="liste.php" class="row g-3">
                    <div class="col-md-6">
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
                            <i class="bi bi-list-ol me-1"></i>Par page
                        </label>
                        <select class="form-select" id="par_page" name="par_page" onchange="this.form.submit()">
                            <?php foreach ($options_par_page as $option): ?>
                                <option value="<?php echo $option; ?>" <?php echo $par_page === $option ? 'selected' : ''; ?>>
                                    <?php echo $option; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i>Filtrer
                        </button>
                        <a href="liste.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i>Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (count($chants) > 0): ?>
                <!-- Information de pagination -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Affichage de <strong><?php echo $offset + 1; ?></strong> Ã  
                        <strong><?php echo min($offset + $par_page, $total_chants); ?></strong> 
                        sur <strong><?php echo $total_chants; ?></strong> chant(s)
                    </div>
                    <?php if ($total_pages > 1): ?>
                    <div>
                        <small class="text-muted">Page <?php echo $page; ?> sur <?php echo $total_pages; ?></small>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Titre</th>
                                <th>Référence</th>
                                <th class="text-center">Partition</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chants as $chant): ?>
                            <tr>
                                <td><strong><?php echo escape($chant['titre']); ?></strong></td>
                                <td>
                                    <span class="reference-badge">
                                        <?php echo escape($chant['reference']); ?>
                                        <?php if ($chant['sous_reference']): ?>
                                            -<?php echo escape($chant['sous_reference']); ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($chant['a_partition'] && $chant['fichier_pdf']): ?>
                                        <a href="voir_partition.php?id=<?php echo $chant['id']; ?>" 
                                           class="btn btn-sm btn-primary btn-pdf me-1" target="_blank" 
                                           title="Voir la partition">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                        <a href="telecharger.php?id=<?php echo $chant['id']; ?>" 
                                           class="btn btn-sm btn-success btn-pdf" 
                                           title="Télécharger la partition">
                                            <i class="bi bi-download"></i> PDF
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="bi bi-x-circle"></i> Non disponible
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des pages" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Première page -->
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildUrl(['page' => 1]); ?>" aria-label="Première">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">&laquo;&laquo;</span>
                        </li>
                        <?php endif; ?>

                        <!-- Page précédente -->
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildUrl(['page' => $page - 1]); ?>" aria-label="Précédent">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">&laquo;</span>
                        </li>
                        <?php endif; ?>

                        <!-- Pages numérotées -->
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif;
                        
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo buildUrl(['page' => $i]); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor;
                        
                        if ($end < $total_pages): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>

                        <!-- Page suivante -->
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildUrl(['page' => $page + 1]); ?>" aria-label="Suivant">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">&raquo;</span>
                        </li>
                        <?php endif; ?>

                        <!-- Dernière page -->
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildUrl(['page' => $total_pages]); ?>" aria-label="Dernière">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">&raquo;&raquo;</span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Aucun chant trouvé. 
                    <?php if (!empty($recherche)): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        <a href="ajouter_chant.php">Ajoutez votre premier chant</a>.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
