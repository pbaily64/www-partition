<?php
// cleanup_temp_files.php - Nettoyage automatique des fichiers temporaires de plus de 24h
// À exécuter via cron, par exemple : 0 2 * * * /usr/bin/php /var/www/html3/cleanup_temp_files.php

$tempDir = __DIR__ . '/uploads/temp/';

if (!is_dir($tempDir)) {
    exit("Répertoire temporaire non trouvé.\n");
}

$now = time();
$maxAge = 24 * 3600; // 24 heures
$deletedCount = 0;

$files = glob($tempDir . '*');

foreach ($files as $file) {
    if (is_file($file)) {
        $fileAge = $now - filemtime($file);
        
        if ($fileAge > $maxAge) {
            if (unlink($file)) {
                $deletedCount++;
                echo "Supprimé: " . basename($file) . " (âge: " . round($fileAge / 3600, 1) . " heures)\n";
            }
        }
    }
}

echo "Nettoyage terminé. $deletedCount fichier(s) supprimé(s).\n";
?>
