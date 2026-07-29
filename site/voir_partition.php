<?php
// voir_partition.php - Affichage d'une partition PDF
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$chant_id = intval($_GET['id'] ?? 0);

if ($chant_id <= 0) {
    die('ID de chant invalide.');
}

// Récupération des informations du chant
$stmt = $pdo->prepare("SELECT id, reference, sous_reference, titre, fichier_pdf, chemin_pdf, a_partition FROM chants WHERE id = ?");
$stmt->execute([$chant_id]);
$chant = $stmt->fetch();

if (!$chant || !$chant['a_partition'] || !$chant['fichier_pdf']) {
    die('Partition non trouvée.');
}

$pdf_url = 'uploads/partitions/' . $chant['fichier_pdf'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($chant['titre']); ?> - Partition</title>
	
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
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        .toolbar {
            background-color: #343a40;
            color: white;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pdf-viewer {
            width: 100%;
            height: calc(100vh - 60px);
            border: none;
        }
        .btn-toolbar {
            color: white;
        }
        .btn-toolbar:hover {
            color: #adb5bd;
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>
                <i class="bi bi-file-pdf me-2"></i>
                <?php echo escape($chant['reference']); ?>
                <?php if ($chant['sous_reference']): ?>
                    -<?php echo escape($chant['sous_reference']); ?>
                <?php endif; ?>
                : <?php echo escape($chant['titre']); ?>
            </strong>
        </div>
        <div>
            <a href="telecharger.php?id=<?php echo $chant['id']; ?>" class="btn btn-sm btn-success me-2">
                <i class="bi bi-download me-1"></i>Télécharger
            </a>
            <a href="liste.php" class="btn btn-sm btn-light">
                <i class="bi bi-x-lg me-1"></i>Fermer
            </a>
        </div>
    </div>
    
    <iframe src="<?php echo escape($pdf_url); ?>" class="pdf-viewer"></iframe>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
