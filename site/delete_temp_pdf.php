<?php
// delete_temp_pdf.php - Suppression des fichiers PDF temporaires
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tempPath = $input['tempPath'] ?? '';

if (empty($tempPath)) {
    echo json_encode(['success' => false, 'error' => 'Chemin non spécifié']);
    exit;
}

// Vérifier que le fichier est bien dans le répertoire temp
$tempDir = __DIR__ . '/uploads/temp/';
if (strpos(realpath($tempPath), realpath($tempDir)) !== 0) {
    echo json_encode(['success' => false, 'error' => 'Chemin invalide']);
    exit;
}

if (file_exists($tempPath)) {
    if (unlink($tempPath)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Fichier non trouvé']);
}
?>
