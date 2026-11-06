-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 07 nov. 2026 à 00:32
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `park_reservation_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `forfaits`
--

CREATE TABLE `forfaits` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `type` enum('horaire','jour','abonnement') NOT NULL,
  `duree_minutes` int(11) DEFAULT NULL,
  `prix_tnd` decimal(6,2) DEFAULT NULL,
  `grace_minutes` int(11) NOT NULL DEFAULT 15,
  `actif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `forfaits`
--

INSERT INTO `forfaits` (`id`, `nom`, `type`, `duree_minutes`, `prix_tnd`, `grace_minutes`, `actif`) VALUES
(1, 'À l\'heure', 'horaire', NULL, 2.50, 15, 1),
(2, 'Journée complète', 'jour', 480, 15.00, 30, 1),
(3, 'Longue durée (mensuel)', 'abonnement', NULL, NULL, 30, 1);

-- --------------------------------------------------------

--
-- Structure de la table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `plate_format` varchar(10) NOT NULL DEFAULT 'STD',
  `plate_left` varchar(6) NOT NULL,
  `plate_right` varchar(6) NOT NULL,
  `plate_digits` varchar(12) NOT NULL,
  `owner_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `forfait_id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `grace_minutes` int(11) NOT NULL,
  `price_tnd` decimal(6,2) NOT NULL,
  `status` enum('confirmed','checked_in','completed','expired','cancelled') NOT NULL DEFAULT 'confirmed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reservations`
--

INSERT INTO `reservations` (`id`, `code`, `plate_format`, `plate_left`, `plate_right`, `plate_digits`, `owner_name`, `phone`, `forfait_id`, `zone_id`, `reservation_date`, `start_time`, `end_time`, `grace_minutes`, `price_tnd`, `status`, `created_at`) VALUES
(1, 'PN-468EEE', 'STD', '254', '4879', '2544879', NULL, NULL, 1, 1, '2026-08-15', '05:00:00', '06:00:00', 15, 2.50, 'confirmed', '2026-08-14 04:42:36'),
(2, 'PN-6CF5F2', 'STD', '254', '4879', '2544879', NULL, NULL, 1, 1, '2026-08-16', '05:30:00', '06:30:00', 15, 2.50, 'cancelled', '2026-08-14 05:07:05'),
(3, 'PN-C5CB2D', 'STD', '555', '5555', '5555555', NULL, NULL, 2, 3, '2026-08-15', '05:30:00', '13:30:00', 30, 15.00, 'cancelled', '2026-08-14 07:18:15'),
(4, 'PN-D6A7AA', 'STD', '247', '7965', '2477965', 'hichem', NULL, 1, 1, '2026-08-15', '03:00:00', '05:00:00', 15, 5.00, 'cancelled', '2026-08-15 04:22:34'),
(5, 'PN-A7DCFF', 'STD', '247', '7965', '2477965', 'hichem', NULL, 1, 1, '2026-08-15', '04:20:00', '07:20:00', 15, 7.50, 'checked_in', '2026-08-15 04:24:38'),
(6, 'PN-7D1534', 'STD', '45', '222', '45222', NULL, NULL, 1, 1, '2026-08-15', '21:49:00', '22:49:00', 15, 2.50, 'confirmed', '2026-08-15 22:17:01'),
(7, 'PN-DE0646', 'RS', 'RS', '123456', 'RS123456', NULL, NULL, 1, 1, '2026-08-15', '23:17:00', '00:17:00', 15, 2.50, 'confirmed', '2026-08-15 23:20:04'),
(8, 'PN-BFDE14', 'RS', 'RS', '268216', 'RS268216', NULL, NULL, 2, 1, '2026-08-16', '00:41:00', '08:41:00', 30, 15.00, 'confirmed', '2026-08-16 01:23:37'),
(9, 'PN-FADCCB', 'STD', '222', '2222', '2222222', NULL, NULL, 1, 1, '2026-08-27', '13:31:00', '14:31:00', 15, 2.50, 'confirmed', '2026-08-27 13:31:54'),
(10, 'PN-1F35C7', 'STD', '456', '4567', '4564567', 'ibrahim chawechi', NULL, 1, 1, '2026-08-29', '09:24:00', '10:24:00', 15, 2.50, 'confirmed', '2026-08-29 09:25:14'),
(11, 'PN-CB649B', 'STD', '444', '8888', '4448888', 'sale7 lbedri', NULL, 1, 1, '2026-08-29', '09:55:00', '10:55:00', 15, 2.50, 'confirmed', '2026-08-29 09:56:03'),
(12, 'PN-4447F3', 'STD', '152', '8269', '1528269', 'ali sale7', NULL, 1, 1, '2026-08-30', '13:29:00', '14:29:00', 15, 2.50, 'checked_in', '2026-08-30 13:30:09'),
(13, 'PN-10A831', 'STD', '555', '8889', '5558889', NULL, NULL, 1, 1, '2026-09-01', '08:53:00', '09:53:00', 15, 2.50, 'confirmed', '2026-09-01 08:53:41'),
(14, 'PN-676F01', 'STD', '152', '8269', '1528269', 'ali sale7', NULL, 1, 1, '2026-09-01', '08:53:00', '09:53:00', 15, 2.50, 'checked_in', '2026-09-01 08:54:42'),
(15, 'PN-A92B29', 'STD', '247', '7965', '2477965', 'ahmed', NULL, 1, 1, '2026-11-06', '23:39:00', '00:39:00', 15, 2.50, 'checked_in', '2026-11-06 23:40:11');

-- --------------------------------------------------------

--
-- Structure de la table `zones`
--

CREATE TABLE `zones` (
  `id` int(11) NOT NULL,
  `code` varchar(5) NOT NULL,
  `label` varchar(100) NOT NULL,
  `capacity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `zones`
--

INSERT INTO `zones` (`id`, `code`, `label`, `capacity`) VALUES
(1, 'A', 'Zone A — Entrée principale', 40),
(2, 'B', 'Zone B — Niveau couvert', 25),
(3, 'C', 'Zone C — Longue durée', 30);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `forfaits`
--
ALTER TABLE `forfaits`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `forfait_id` (`forfait_id`),
  ADD KEY `zone_id` (`zone_id`),
  ADD KEY `idx_plate_digits` (`plate_digits`),
  ADD KEY `idx_date` (`reservation_date`,`start_time`);

--
-- Index pour la table `zones`
--
ALTER TABLE `zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `forfaits`
--
ALTER TABLE `forfaits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `zones`
--
ALTER TABLE `zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`forfait_id`) REFERENCES `forfaits` (`id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
