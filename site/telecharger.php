<?php
// telecharger.php - Téléchargement sécurisé d'une partition PDF
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

$filepath = $chant['chemin_pdf'];

if (!file_exists($filepath)) {
    die('Fichier non trouvé sur le serveur.');
}

// Préparation du nom de fichier pour le téléchargement
$reference_complete = $chant['reference'];
if ($chant['sous_reference']) {
    $reference_complete .= '-' . $chant['sous_reference'];
}
$download_name = $reference_complete . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $chant['titre']) . '.pdf';

// Headers pour le téléchargement
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $download_name . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Lecture et envoi du fichier
readfile($filepath);
exit;
?>
