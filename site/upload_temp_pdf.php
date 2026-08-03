<?php
// upload_temp_pdf.php - Upload temporaire de fichiers PDF pour prévisualisation
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'upload du fichier']);
    exit;
}

$file = $_FILES['pdf'];

// Vérification du type de fichier
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'application/pdf') {
    echo json_encode(['success' => false, 'error' => 'Seuls les fichiers PDF sont acceptés']);
    exit;
}

// Vérification de la taille
if ($file['size'] > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'error' => 'Le fichier est trop volumineux (maximum 20 MB)']);
    exit;
}

// Création du répertoire temporaire si nécessaire
$tempDir = __DIR__ . '/uploads/temp/';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// Génération d'un nom de fichier unique (nom assaini : pas d'espaces/accents/caractères spéciaux)
$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['name']));
$filename = uniqid('temp_') . '_' . $safeName;
$destination = $tempDir . $filename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode([
        'success' => true,
        'tempPath' => $destination,
        'tempUrl' => 'uploads/temp/' . rawurlencode($filename),
        'filename' => $filename
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors du déplacement du fichier']);
}
?>
