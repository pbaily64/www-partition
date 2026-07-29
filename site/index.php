<?php
// index.php - Redirection vers la page de connexion ou la liste
require_once 'config.php';

if (isLoggedIn()) {
    redirect('liste.php');
} else {
    redirect('login.php');
}
?>
