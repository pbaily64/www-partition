-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : mariadb:3306
-- Généré le : mer. 29 juil. 2026 à 11:31
-- Version du serveur : 10.11.15-MariaDB-ubu2204
-- Version de PHP : 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `partitions_catholiques`
--

-- --------------------------------------------------------

--
-- Structure de la table `chants`
--

CREATE TABLE `chants` (
  `id` int(11) NOT NULL,
  `reference` varchar(20) NOT NULL,
  `sous_reference` varchar(20) DEFAULT NULL,
  `titre` varchar(200) NOT NULL,
  `compositeurs` text DEFAULT NULL,
  `auteurs` text DEFAULT NULL,
  `a_partition` tinyint(1) DEFAULT 0,
  `fichier_pdf` varchar(255) DEFAULT NULL,
  `chemin_pdf` varchar(500) DEFAULT NULL,
  `statut_omr` enum('non_traitee','en_cours','echec','traitee_brute','en_correction','validee') NOT NULL DEFAULT 'non_traitee',
  `musicxml_path` varchar(500) DEFAULT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cree_par` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `traitements_omr`
--

CREATE TABLE `traitements_omr` (
  `id` int(11) NOT NULL,
  `chant_id` int(11) NOT NULL,
  `statut` enum('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
  `machine` enum('nucbox','pcdejeu') DEFAULT NULL,
  `parametres` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Profil Audiveris, contraste, rotation, signature forcée…' CHECK (json_valid(`parametres`)),
  `date_demande` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_debut` timestamp NULL DEFAULT NULL,
  `date_fin` timestamp NULL DEFAULT NULL,
  `duree_secondes` int(11) DEFAULT NULL,
  `log_path` varchar(500) DEFAULT NULL,
  `erreur` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `actif` tinyint(1) DEFAULT 1,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `derniere_connexion` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `chants`
--
ALTER TABLE `chants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reference` (`reference`,`sous_reference`),
  ADD KEY `idx_titre` (`titre`),
  ADD KEY `idx_a_partition` (`a_partition`),
  ADD KEY `cree_par` (`cree_par`),
  ADD KEY `idx_chants_statut_omr` (`statut_omr`);

--
-- Index pour la table `traitements_omr`
--
ALTER TABLE `traitements_omr`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_omr_statut_demande` (`statut`,`date_demande`),
  ADD KEY `idx_omr_chant` (`chant_id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `chants`
--
ALTER TABLE `chants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `traitements_omr`
--
ALTER TABLE `traitements_omr`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `chants`
--
ALTER TABLE `chants`
  ADD CONSTRAINT `chants_ibfk_1` FOREIGN KEY (`cree_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `traitements_omr`
--
ALTER TABLE `traitements_omr`
  ADD CONSTRAINT `fk_omr_chant` FOREIGN KEY (`chant_id`) REFERENCES `chants` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
