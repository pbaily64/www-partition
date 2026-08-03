<?php
// ajouter_partitions_masse.php - Ajout en masse de partitions avec prévisualisation
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$success = '';
$error = '';
$errors = [];
$duplicates = [];

// Traitement de la résolution des doublons
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resoudre_doublons') {
    $duplicates_resolutions = json_decode($_POST['duplicates_resolutions'], true);
    
    $added_count = 0;
    
    foreach ($duplicates_resolutions as $resolution) {
        if ($resolution['action'] === 'modifier') {
            // Ajouter avec nouvelle référence
            $reference = trim($resolution['new_reference'] ?? '');
            $sous_reference = trim($resolution['new_sous_reference'] ?? '') ?: null;
            $titre = trim($resolution['titre'] ?? '');
            $compositeurs = trim($resolution['compositeurs'] ?? '') ?: null;
            $auteurs = trim($resolution['auteurs'] ?? '') ?: null;
            $temp_file = $resolution['temp_file'] ?? '';
            
            if (!file_exists($temp_file)) {
                $errors[] = "Fichier temporaire introuvable pour '{$titre}'.";
                continue;
            }
            
            // Vérifier la nouvelle référence
            if (!empty($reference)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM chants WHERE reference = ? AND (sous_reference = ? OR (sous_reference IS NULL AND ? IS NULL))");
                $stmt->execute([$reference, $sous_reference, $sous_reference]);
                
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "La nouvelle référence $reference" . ($sous_reference ? "-$sous_reference" : "") . " existe déjà .";
                    continue;
                }
            }
            
            // Ajouter la partition
            $filename = uniqid('partition_') . '.pdf';
            $destination = UPLOAD_DIR . $filename;
            
            if (copy($temp_file, $destination)) {
                unlink($temp_file);
                
                $stmt = $pdo->prepare("
                    INSERT INTO chants (reference, sous_reference, titre, compositeurs, auteurs, a_partition, fichier_pdf, chemin_pdf, cree_par)
                    VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)
                ");
                
                if ($stmt->execute([$reference, $sous_reference, $titre, $compositeurs, $auteurs, $filename, $destination, $_SESSION['user_id']])) {
                    $added_count++;
                }
            }
        } elseif ($resolution['action'] === 'ignorer') {
            // Supprimer le fichier temporaire
            $temp_file = $resolution['temp_file'] ?? '';
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
        }
    }
    
    if ($added_count > 0) {
        $success = "$added_count partition(s) ajoutée(s) avec succès après résolution des doublons.";
    }
    
    if (count($errors) > 0) {
        $error = "Certaines partitions n'ont pas pu être ajoutées.";
    }
}

// Traitement du formulaire d'ajout en masse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_masse') {
    $partitions_data = json_decode($_POST['partitions_data'], true);
    $files_temp = json_decode($_POST['files_temp'], true);
    
    if (is_array($partitions_data) && count($partitions_data) > 0) {
        $added_count = 0;
        $duplicates = [];
        
        foreach ($partitions_data as $index => $data) {
            $reference = trim($data['reference'] ?? '');
            $sous_reference = trim($data['sous_reference'] ?? '') ?: null;
            $titre = trim($data['titre'] ?? '');
            $compositeurs = trim($data['compositeurs'] ?? '') ?: null;
            $auteurs = trim($data['auteurs'] ?? '') ?: null;
            $temp_file = $files_temp[$index] ?? '';
            
            // Validation
            if (empty($titre)) {
                $errors[] = "Partition #" . ($index + 1) . ": Le titre est obligatoire.";
                // Supprimer le fichier temporaire
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
                continue;
            }
            
            // Vérifier que le fichier temporaire existe
            if (!file_exists($temp_file)) {
                $errors[] = "Partition #" . ($index + 1) . ": Fichier temporaire introuvable.";
                continue;
            }
            
            // Si une référence est fournie, vérifier qu'elle n'existe pas déjà 
            $has_duplicate = false;
            if (!empty($reference)) {
                $stmt = $pdo->prepare("SELECT id, titre, fichier_pdf, compositeurs, auteurs FROM chants WHERE reference = ? AND (sous_reference = ? OR (sous_reference IS NULL AND ? IS NULL))");
                $stmt->execute([$reference, $sous_reference, $sous_reference]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Doublon trouvé - stocker pour résolution
                    $duplicates[] = [
                        'index' => $index,
                        'new' => [
                            'titre' => $titre,
                            'reference' => $reference,
                            'sous_reference' => $sous_reference,
                            'compositeurs' => $compositeurs,
                            'auteurs' => $auteurs,
                            'temp_file' => $temp_file,
                            'temp_url' => 'uploads/temp/' . rawurlencode(basename($temp_file))
                        ],
                        'existing' => [
                            'id' => $existing['id'],
                            'titre' => $existing['titre'],
                            'reference' => $reference,
                            'sous_reference' => $sous_reference,
                            'compositeurs' => $existing['compositeurs'],
                            'auteurs' => $existing['auteurs'],
                            'fichier_pdf' => $existing['fichier_pdf'],
                            'pdf_url' => !empty($existing['fichier_pdf'])
                                ? 'uploads/partitions/' . rawurlencode($existing['fichier_pdf'])
                                : ''
                        ]
                    ];
                    $has_duplicate = true;
                }
            }
            
            // Si pas de doublon, ajouter directement
            if (!$has_duplicate) {
                $filename = uniqid('partition_') . '.pdf';
                $destination = UPLOAD_DIR . $filename;
                
                if (copy($temp_file, $destination)) {
                    unlink($temp_file);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO chants (reference, sous_reference, titre, compositeurs, auteurs, a_partition, fichier_pdf, chemin_pdf, cree_par)
                        VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([$reference, $sous_reference, $titre, $compositeurs, $auteurs, $filename, $destination, $_SESSION['user_id']])) {
                        $added_count++;
                    } else {
                        $errors[] = "Partition #" . ($index + 1) . ": Erreur lors de l'ajout en base de données.";
                        unlink($destination);
                    }
                } else {
                    $errors[] = "Partition #" . ($index + 1) . ": Erreur lors du déplacement du fichier.";
                }
            }
        }
        
        // Si des doublons ont été détectés, afficher l'écran de résolution
        if (count($duplicates) === 0) {
            if ($added_count > 0) {
                $success = "$added_count partition(s) ajoutée(s) avec succès.";
            }
            
            if (count($errors) > 0) {
                $error = "Certaines partitions n'ont pas pu être ajoutées.";
            }
        } else {
            // On a des doublons ET des partitions ajoutées
            if ($added_count > 0) {
                $success = "$added_count partition(s) ajoutée(s) avec succès.";
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
    <title>Ajout en masse - <?php echo SITE_NAME; ?></title>
	
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
        .pdf-preview-container {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f8f9fa;
        }
        .pdf-viewer {
            width: 100%;
            height: 500px;
            border: 1px solid #ced4da;
            border-radius: 5px;
        }
        .partition-form {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .file-upload-area {
            border: 3px dashed #6c757d;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }
        .file-upload-area:hover {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .file-upload-area.drag-over {
            border-color: #198754;
            background-color: #d1e7dd;
        }
        .processing-indicator {
            display: none;
        }
        .partition-card {
            border: 2px solid #0d6efd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            background-color: white;
        }
        .partition-card-header {
            background-color: #0d6efd;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-remove-partition {
            background-color: #dc3545;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            cursor: pointer;
        }
        .btn-remove-partition:hover {
            background-color: #bb2d3b;
        }
        .duplicate-card {
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            background-color: #fff3cd;
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
                        <a class="nav-link active" href="ajouter_partitions_masse.php">
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
        <?php if (count($duplicates) > 0): ?>
            <!-- à‰cran de résolution des doublons -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Résolution des doublons</h2>
                    <p class="text-muted">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i><?php echo escape($success); ?>
                            </div>
                        <?php endif; ?>
                        Les partitions suivantes ont des références qui existent déjà  dans la base de données. 
                        Comparez les deux versions et décidez quoi faire pour chaque doublon.
                    </p>
                </div>
            </div>

            <form id="duplicatesForm" method="POST" action="ajouter_partitions_masse.php">
                <input type="hidden" name="action" value="resoudre_doublons">
                <input type="hidden" name="duplicates_resolutions" id="duplicatesResolutions">
                
                <?php foreach ($duplicates as $idx => $dup): ?>
                <div class="duplicate-card">
                    <h4 class="text-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Doublon #<?php echo ($idx + 1); ?> : Référence <?php echo escape($dup['new']['reference']); ?>
                        <?php if ($dup['new']['sous_reference']): ?>-<?php echo escape($dup['new']['sous_reference']); ?><?php endif; ?>
                    </h4>
                    
                    <div class="row mt-3">
                        <!-- Partition existante -->
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0"><i class="bi bi-database me-2"></i>Partition existante dans la base</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Titre:</strong> <?php echo escape($dup['existing']['titre']); ?></p>
                                    <p><strong>Compositeurs:</strong> <?php echo escape($dup['existing']['compositeurs'] ?? 'Non renseigné'); ?></p>
                                    <p><strong>Auteurs:</strong> <?php echo escape($dup['existing']['auteurs'] ?? 'Non renseigné'); ?></p>
                                    <hr>
                                    <h6>Prévisualisation :</h6>
                                    <?php if (!empty($dup['existing']['pdf_url'])): ?>
                                    <iframe src="<?php echo escape($dup['existing']['pdf_url']); ?>" class="pdf-viewer"></iframe>
                                    <?php else: ?>
                                    <div class="text-muted p-3">Aucun PDF disponible pour cette partition.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Nouvelle partition -->
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="bi bi-file-earmark-plus me-2"></i>Nouvelle partition à ajouter</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Titre:</strong> <?php echo escape($dup['new']['titre']); ?></p>
                                    <p><strong>Compositeurs:</strong> <?php echo escape($dup['new']['compositeurs'] ?? 'Non renseigné'); ?></p>
                                    <p><strong>Auteurs:</strong> <?php echo escape($dup['new']['auteurs'] ?? 'Non renseigné'); ?></p>
                                    <hr>
                                    <h6>Prévisualisation :</h6>
                                    <?php if (!empty($dup['new']['temp_url'])): ?>
                                    <iframe src="<?php echo escape($dup['new']['temp_url']); ?>" class="pdf-viewer"></iframe>
                                    <?php else: ?>
                                    <div class="text-muted p-3">Aperçu indisponible.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Options de résolution -->
                    <div class="mt-3 p-3 bg-light rounded">
                        <h5><i class="bi bi-question-circle me-2"></i>Que souhaitez-vous faire ?</h5>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="resolution_<?php echo $idx; ?>" 
                                   id="ignorer_<?php echo $idx; ?>" value="ignorer" checked
                                   onchange="updateResolution(<?php echo $idx; ?>, 'ignorer')">
                            <label class="form-check-label" for="ignorer_<?php echo $idx; ?>">
                                <strong>Ignorer la nouvelle partition</strong> - Conserver uniquement la partition existante
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="radio" name="resolution_<?php echo $idx; ?>" 
                                   id="modifier_<?php echo $idx; ?>" value="modifier"
                                   onchange="updateResolution(<?php echo $idx; ?>, 'modifier')">
                            <label class="form-check-label" for="modifier_<?php echo $idx; ?>">
                                <strong>Ajouter avec une nouvelle référence</strong> - Modifier la référence et ajouter la nouvelle partition
                            </label>
                        </div>
                        
                        <div id="new_ref_container_<?php echo $idx; ?>" style="display: none;" class="mt-3 ms-4 p-3 border rounded bg-white">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label"><strong>Nouvelle référence</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="new_ref_<?php echo $idx; ?>" 
                                           placeholder="Ex: <?php echo escape($dup['new']['reference']); ?>-B">
                                    <small class="form-text text-muted">Suggestion: <?php echo escape($dup['new']['reference']); ?>-B</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nouvelle sous-référence</label>
                                    <input type="text" class="form-control" id="new_subref_<?php echo $idx; ?>" 
                                           placeholder="Ex: B">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script>
                    // Stocker les données du doublon
                    if (!window.duplicatesData) window.duplicatesData = [];
                    window.duplicatesData[<?php echo $idx; ?>] = <?php echo json_encode($dup); ?>;
                </script>
                <?php endforeach; ?>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4 mb-5">
                    <button type="button" class="btn btn-secondary btn-lg" onclick="location.href='ajouter_partitions_masse.php'">
                        <i class="bi bi-arrow-left me-1"></i>Annuler et recommencer
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle me-1"></i>Valider les choix (<?php echo count($duplicates); ?> doublon<?php echo count($duplicates) > 1 ? 's' : ''; ?>)
                    </button>
                </div>
            </form>
            
            <script>
                function updateResolution(index, action) {
                    const container = document.getElementById('new_ref_container_' + index);
                    if (action === 'modifier') {
                        container.style.display = 'block';
                    } else {
                        container.style.display = 'none';
                    }
                }
                
                document.getElementById('duplicatesForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const resolutions = [];
                    
                    <?php foreach ($duplicates as $idx => $dup): ?>
                    const action<?php echo $idx; ?> = document.querySelector('input[name="resolution_<?php echo $idx; ?>"]:checked').value;
                    const resolution<?php echo $idx; ?> = {
                        action: action<?php echo $idx; ?>,
                        titre: window.duplicatesData[<?php echo $idx; ?>].new.titre,
                        compositeurs: window.duplicatesData[<?php echo $idx; ?>].new.compositeurs,
                        auteurs: window.duplicatesData[<?php echo $idx; ?>].new.auteurs,
                        temp_file: window.duplicatesData[<?php echo $idx; ?>].new.temp_file
                    };
                    
                    if (action<?php echo $idx; ?> === 'modifier') {
                        resolution<?php echo $idx; ?>.new_reference = document.getElementById('new_ref_<?php echo $idx; ?>').value.trim();
                        resolution<?php echo $idx; ?>.new_sous_reference = document.getElementById('new_subref_<?php echo $idx; ?>').value.trim();
                        
                        if (!resolution<?php echo $idx; ?>.new_reference) {
                            alert('Veuillez saisir une nouvelle référence pour le doublon #<?php echo ($idx + 1); ?>');
                            return;
                        }
                    }
                    
                    resolutions.push(resolution<?php echo $idx; ?>);
                    <?php endforeach; ?>
                    
                    document.getElementById('duplicatesResolutions').value = JSON.stringify(resolutions);
                    this.submit();
                });
            </script>

        <?php else: ?>
            <!-- à‰cran normal d'ajout en masse -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2><i class="bi bi-file-earmark-pdf me-2"></i>Ajout en masse de partitions</h2>
                    <p class="text-muted">Sélectionnez plusieurs fichiers PDF et complétez les informations pour chacun</p>
                </div>
            </div>

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
                <?php if (count($errors) > 0): ?>
                    <div class="alert alert-warning">
                        <strong>Détails des erreurs :</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo escape($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div id="upload-section">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="file-upload-area" id="dropZone">
                            <i class="bi bi-cloud-upload display-1 text-primary"></i>
                            <h4 class="mt-3">Glissez-déposez vos fichiers PDF ici</h4>
                            <p class="text-muted">ou</p>
                            <input type="file" id="fileInput" multiple accept=".pdf" style="display: none;">
                            <button type="button" class="btn btn-primary btn-lg" onclick="document.getElementById('fileInput').click()">
                                <i class="bi bi-folder2-open me-2"></i>Sélectionner des fichiers PDF
                            </button>
                            <p class="text-muted mt-3 mb-0">
                                <small>Formats acceptés : PDF uniquement â€¢ Taille max : 20 MB par fichier</small>
                            </p>
                        </div>
                        
                        <div class="processing-indicator mt-3" id="processingIndicator">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border text-primary me-3" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                                <div>
                                    <strong>Traitement des fichiers en cours...</strong>
                                    <div class="text-muted">Veuillez patienter</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form id="massAddForm" method="POST" action="ajouter_partitions_masse.php" style="display: none;">
                <input type="hidden" name="action" value="ajouter_masse">
                <input type="hidden" name="partitions_data" id="partitionsData">
                <input type="hidden" name="files_temp" id="filesTemp">
                
                <div id="partitionsContainer"></div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="button" class="btn btn-secondary btn-lg" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Recommencer
                    </button>
                    <button type="button" class="btn btn-success btn-lg" onclick="confirmSubmit()">
                        <i class="bi bi-check-circle me-1"></i>Enregistrer toutes les partitions
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center text-muted">
            <small>&copy; <?php echo date('Y'); ?> - <?php echo SITE_NAME; ?></small>
        </div>
    </footer>

    <!-- Modal de confirmation -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmer l'envoi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Vous êtes sur le point d'ajouter <strong id="partitionCount">0</strong> partition(s) à  la base de données.</p>
                    <p class="mb-0"><strong>Cette action est définitive.</strong> Voulez-vous continuer ?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="button" class="btn btn-success" onclick="submitForm()">
                        <i class="bi bi-check-circle me-1"></i>Oui, enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // Configuration de PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        
        let filesData = [];
        let confirmModalInstance = null;
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            confirmModalInstance = new bootstrap.Modal(document.getElementById('confirmModal'));
        });
        
        // Gestion du drag & drop
        const dropZone = document.getElementById('dropZone');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.add('drag-over');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.classList.remove('drag-over');
            }, false);
        });
        
        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            handleFiles(files);
        }, false);
        
        document.getElementById('fileInput').addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        async function handleFiles(files) {
            const pdfFiles = Array.from(files).filter(file => file.type === 'application/pdf');
            
            if (pdfFiles.length === 0) {
                alert('Veuillez sélectionner uniquement des fichiers PDF.');
                return;
            }
            
            // Vérifier la taille des fichiers
            const oversizedFiles = pdfFiles.filter(file => file.size > <?php echo MAX_FILE_SIZE; ?>);
            if (oversizedFiles.length > 0) {
                alert(`Certains fichiers sont trop volumineux (max 20 MB):\n${oversizedFiles.map(f => f.name).join('\n')}`);
                return;
            }
            
            document.getElementById('processingIndicator').style.display = 'block';
            document.getElementById('upload-section').style.display = 'none';
            
            // Traiter chaque fichier
            for (let file of pdfFiles) {
                await processFile(file);
            }
            
            document.getElementById('processingIndicator').style.display = 'none';
            document.getElementById('massAddForm').style.display = 'block';
        }
        
        async function processFile(file) {
            const fileData = {
                file: file,
                name: file.name,
                titre: '',
                reference: '',
                sous_reference: '',
                compositeurs: '',
                auteurs: '',
                tempPath: ''
            };
            
            // Upload temporaire du fichier
            const formData = new FormData();
            formData.append('pdf', file);
            
            try {
                const response = await fetch('upload_temp_pdf.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    fileData.tempPath = result.tempPath;
                    fileData.tempUrl = result.tempUrl;
                    
                    // Extraction du titre depuis le PDF
                    fileData.titre = await extractTitleFromPDF(file);
                    
                    filesData.push(fileData);
                    renderPartitionCard(fileData, filesData.length - 1);
                } else {
                    alert(`Erreur lors de l'upload de ${file.name}: ${result.error}`);
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert(`Erreur lors du traitement de ${file.name}`);
            }
        }
        
        async function extractTitleFromPDF(file) {
            try {
                const arrayBuffer = await file.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({data: arrayBuffer}).promise;
                const page = await pdf.getPage(1);
                const textContent = await page.getTextContent();
                
                // Extraire le texte de la première page
                const text = textContent.items.map(item => item.str).join(' ');
                
                // Rechercher le titre (généralement les premières lignes en gros caractères)
                const lines = text.split('\n').filter(line => line.trim().length > 0);
                
                if (lines.length > 0) {
                    let titre = lines[0].trim();
                    titre = titre.replace(/^\d+\s*/, ''); // Enlever les numéros au début
                    titre = titre.substring(0, 200); // Limiter la longueur
                    return titre;
                }
                
                return file.name.replace('.pdf', '');
            } catch (error) {
                console.error('Erreur extraction titre:', error);
                return file.name.replace('.pdf', '');
            }
        }
        
        function renderPartitionCard(fileData, index) {
            const container = document.getElementById('partitionsContainer');
            
            const card = document.createElement('div');
            card.className = 'partition-card';
            card.id = `partition-${index}`;
            
            card.innerHTML = `
                <div class="partition-card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-file-pdf me-2"></i>Partition #${index + 1} : ${escapeHtml(fileData.name)}
                    </h5>
                    <button type="button" class="btn-remove-partition" onclick="removePartition(${index})">
                        <i class="bi bi-trash"></i> Supprimer
                    </button>
                </div>
                
                <div class="row">
                    <div class="col-lg-6">
                        <div class="pdf-preview-container">
                            <h6><i class="bi bi-eye me-2"></i>Prévisualisation</h6>
                            ${fileData.tempUrl
                                ? `<iframe src="${escapeHtml(fileData.tempUrl)}" class="pdf-viewer"></iframe>`
                                : `<div class="text-muted p-3">Aperçu indisponible.</div>`}
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="partition-form">
                            <h6><i class="bi bi-pencil-square me-2"></i>Informations</h6>
                            
                            <div class="mb-3">
                                <label for="titre-${index}" class="form-label">
                                    <i class="bi bi-music-note me-1"></i>Titre <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="titre-${index}" 
                                       value="${escapeHtml(fileData.titre)}" required
                                       onchange="updateFileData(${index}, 'titre', this.value)">
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="reference-${index}" class="form-label">
                                        <i class="bi bi-hash me-1"></i>Référence
                                    </label>
                                    <input type="text" class="form-control" id="reference-${index}" 
                                           placeholder="Ex: A001"
                                           onchange="updateFileData(${index}, 'reference', this.value)">
                                    <small class="form-text text-muted">Optionnel</small>
                                </div>
                                <div class="col-md-4">
                                    <label for="sous-reference-${index}" class="form-label">Sous-réf.</label>
                                    <input type="text" class="form-control" id="sous-reference-${index}" 
                                           placeholder="Ex: A"
                                           onchange="updateFileData(${index}, 'sous_reference', this.value)">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="compositeurs-${index}" class="form-label">
                                    <i class="bi bi-person me-1"></i>Compositeur(s)
                                </label>
                                <input type="text" class="form-control" id="compositeurs-${index}" 
                                       placeholder="Nom(s) du/des compositeur(s)"
                                       onchange="updateFileData(${index}, 'compositeurs', this.value)">
                            </div>
                            
                            <div class="mb-3">
                                <label for="auteurs-${index}" class="form-label">
                                    <i class="bi bi-pen me-1"></i>Auteur(s)
                                </label>
                                <input type="text" class="form-control" id="auteurs-${index}" 
                                       placeholder="Nom(s) ou référence biblique"
                                       onchange="updateFileData(${index}, 'auteurs', this.value)">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(card);
        }
        
        function updateFileData(index, field, value) {
            if (filesData[index]) {
                filesData[index][field] = value;
            }
        }
        
        function removePartition(index) {
            if (confirm('Voulez-vous vraiment supprimer cette partition ?')) {
                // Supprimer le fichier temporaire
                if (filesData[index] && filesData[index].tempPath) {
                    fetch('delete_temp_pdf.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({tempPath: filesData[index].tempPath})
                    });
                }
                
                // Retirer de la liste
                filesData.splice(index, 1);
                
                // Recharger l'affichage
                document.getElementById('partitionsContainer').innerHTML = '';
                filesData.forEach((fileData, idx) => {
                    renderPartitionCard(fileData, idx);
                });
                
                // Si plus de fichiers, retourner à  l'écran d'upload
                if (filesData.length === 0) {
                    location.reload();
                }
            }
        }
        
        function confirmSubmit() {
            document.getElementById('partitionCount').textContent = filesData.length;
            confirmModalInstance.show();
        }
        
        function submitForm() {
            confirmModalInstance.hide();
            
            // Préparer les données
            const partitionsData = filesData.map(fd => ({
                titre: fd.titre,
                reference: fd.reference,
                sous_reference: fd.sous_reference,
                compositeurs: fd.compositeurs,
                auteurs: fd.auteurs
            }));
            
            const filesTemp = filesData.map(fd => fd.tempPath);
            
            document.getElementById('partitionsData').value = JSON.stringify(partitionsData);
            document.getElementById('filesTemp').value = JSON.stringify(filesTemp);
            
            // Soumettre le formulaire
            document.getElementById('massAddForm').submit();
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
