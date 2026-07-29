<?php
// logout.php - Déconnexion de l'utilisateur
require_once 'config.php';

// Destruction de la session
session_unset();
session_destroy();

// Redirection vers la page de connexion
redirect('login.php');
?>
