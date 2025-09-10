-- Gauf Database Migration Export
-- Generated on: 2025-09-04 13:22:01
-- Database: kurssienhallinta
-- 
-- This file is designed for migrating to a new server
-- It creates a fresh database without foreign key conflicts
-- 

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

-- Create database
CREATE DATABASE IF NOT EXISTS `kurssienhallinta` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kurssienhallinta`;

-- --------------------------------------------------------
-- Table structure for table `kayttajat`
-- --------------------------------------------------------

CREATE TABLE `kayttajat` (
  `tunnus` int(11) NOT NULL AUTO_INCREMENT,
  `kayttajanimi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `salasana_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rooli` enum('admin','opettaja','opiskelija') COLLATE utf8mb4_unicode_ci NOT NULL,
  `etunimi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sukunimi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aktiivinen` tinyint(1) DEFAULT 1,
  `salasana_vaihdettu` tinyint(1) DEFAULT 0,
  `salasana_luotu` datetime DEFAULT current_timestamp(),
  `viimeisin_kirjautuminen` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tunnus`),
  UNIQUE KEY `kayttajanimi` (`kayttajanimi`),
  KEY `idx_kayttajat_rooli` (`rooli`),
  KEY `idx_kayttajat_kayttajanimi` (`kayttajanimi`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `kayttajat`
INSERT INTO `kayttajat` (`tunnus`, `kayttajanimi`, `salasana_hash`, `rooli`, `etunimi`, `sukunimi`, `email`, `aktiivinen`, `salasana_vaihdettu`, `salasana_luotu`, `viimeisin_kirjautuminen`, `created_at`, `updated_at`) VALUES
('1', 'mohammad', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Mohammad', 'Altinawi', 'Mohammad@edu.turku.fi', '1', '1', '2025-09-04 10:31:11', '2025-09-04 14:20:25', '2025-09-04 10:31:11', '2025-09-04 14:20:25'),
('2', 'leart.nura', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'opettaja', 'leart', 'nura', 'leart.nura@edu.turku.fi', '1', '1', '2025-09-04 10:31:11', '2025-09-04 12:35:48', '2025-09-04 10:31:11', '2025-09-04 12:35:48'),
('3', 'ivan.oriskwe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'opettaja', 'ivan', 'oriskwe', 'ivan.oriskwe@edu.turku.fi', '1', '1', '2025-09-04 10:31:11', '2025-09-04 12:56:05', '2025-09-04 10:31:11', '2025-09-04 12:56:05'),
('4', 'pekka.makinen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'opettaja', 'Pekka', 'Mäkinen', 'pekka.makinen@school.fi', '1', '1', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 12:48:14'),
('5', 'anthony.oriskwe', '$2y$10$bkF5r4N98KKi9hLdxEw98e9x1dKwsxZniqojfp8ety6cTRzBlyEqO', 'opiskelija', 'anthony', 'oriskwe', 'anthony.oriskwe@edu.turku.fi', '1', '1', '2025-09-04 10:39:42', '2025-09-04 12:55:48', '2025-09-04 10:31:11', '2025-09-04 12:55:48'),
('6', 'jussi.hakala', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'opiskelija', 'Jussi', 'Hakala', 'jussi.hakala@school.fi', '1', '1', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 12:48:14'),
('7', 'maria.laine', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'opiskelija', 'Maria', 'Laine', 'maria.laine@school.fi', '1', '1', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 12:48:14'),
('8', 'timo.rantanen', '$2y$10$usJdMzGYmn.pe1frh24XC.gOwL5mssksz/uJgK9//UDRhkNEMb16a', 'opiskelija', 'Timo', 'Rantanen', 'timo.rantanen@school.fi', '1', '1', '2025-09-04 12:12:41', '2025-09-04 12:14:09', '2025-09-04 10:31:11', '2025-09-04 12:14:09'),
('9', 'sofia.heikkinen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'opiskelija', 'Sofia', 'Heikkinen', 'sofia.heikkinen@school.fi', '1', '1', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 12:48:14'),
('10', 'ahmad.altinawi', '$2y$10$9BJy4zIu29n30IMEckfWiO5Jz/73a0z9c5QE5QfMrF7nRa4xbZmuW', 'opiskelija', 'ahmad', 'altinawi', 'aldlw@gmail.com', '1', '1', '2025-09-04 10:37:07', '2025-09-04 12:35:34', '2025-09-04 10:37:07', '2025-09-04 12:35:34');

-- --------------------------------------------------------
-- Table structure for table `opettajat`
-- --------------------------------------------------------

CREATE TABLE `opettajat` (
  `tunnus` int(11) NOT NULL AUTO_INCREMENT,
  `kayttaja_tunnus` int(11) DEFAULT NULL,
  `etunimi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sukunimi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aine` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tunnus`),
  KEY `kayttaja_tunnus` (`kayttaja_tunnus`),
  KEY `idx_opettajat_aine` (`aine`),
  CONSTRAINT `opettajat_ibfk_1` FOREIGN KEY (`kayttaja_tunnus`) REFERENCES `kayttajat` (`tunnus`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `opettajat`
INSERT INTO `opettajat` (`tunnus`, `kayttaja_tunnus`, `etunimi`, `sukunimi`, `aine`, `created_at`, `updated_at`) VALUES
('1', '2', 'leart', 'nura', 'Matematiikka', '2025-09-04 10:31:11', '2025-09-04 10:34:12'),
('2', '3', 'Liisa', 'Korhonen', 'Fysiikka', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('3', '4', 'ivan', 'oriskwe', 'Kemia', '2025-09-04 10:31:11', '2025-09-04 10:34:33');

-- --------------------------------------------------------
-- Table structure for table `opiskelijat`
-- --------------------------------------------------------

CREATE TABLE `opiskelijat` (
  `tunnus` int(11) NOT NULL AUTO_INCREMENT,
  `kayttaja_tunnus` int(11) DEFAULT NULL,
  `opiskelijanumero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `etunimi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sukunimi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `syntymapaiva` date NOT NULL,
  `vuosikurssi` int(11) NOT NULL CHECK (`vuosikurssi` >= 1 and `vuosikurssi` <= 3),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tunnus`),
  UNIQUE KEY `opiskelijanumero` (`opiskelijanumero`),
  KEY `kayttaja_tunnus` (`kayttaja_tunnus`),
  CONSTRAINT `opiskelijat_ibfk_1` FOREIGN KEY (`kayttaja_tunnus`) REFERENCES `kayttajat` (`tunnus`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `opiskelijat`
INSERT INTO `opiskelijat` (`tunnus`, `kayttaja_tunnus`, `opiskelijanumero`, `etunimi`, `sukunimi`, `syntymapaiva`, `vuosikurssi`, `created_at`, `updated_at`) VALUES
('1', '5', '2024001', 'Anthony', 'oriskwe', '2005-03-15', '1', '2025-09-04 10:31:11', '2025-09-04 10:34:59'),
('2', '6', '2024002', 'Jussi', 'Hakala', '2004-07-22', '2', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('3', '7', '2024003', 'Maria', 'Laine', '2003-11-08', '3', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('4', '8', '2024004', 'Timo', 'Rantanen', '2005-01-30', '1', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('5', '9', '2024005', 'Sofia', 'Heikkinen', '2004-09-12', '2', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('6', '10', '20258360', 'ahmad', 'altinawi', '2001-01-02', '1', '2025-09-04 10:37:07', '2025-09-04 10:37:07');

-- --------------------------------------------------------
-- Table structure for table `tilat`
-- --------------------------------------------------------

CREATE TABLE `tilat` (
  `tunnus` int(11) NOT NULL AUTO_INCREMENT,
  `nimi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kapasiteetti` int(11) NOT NULL CHECK (`kapasiteetti` > 0),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tunnus`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `tilat`
INSERT INTO `tilat` (`tunnus`, `nimi`, `kapasiteetti`, `created_at`, `updated_at`) VALUES
('1', 'Luokka 101', '25', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('2', 'Luokka 102', '30', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('3', 'Laboratorio A', '20', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('4', 'Auditorio 1', '100', '2025-09-04 10:31:11', '2025-09-04 10:31:11');

-- --------------------------------------------------------
-- Table structure for table `kurssit`
-- --------------------------------------------------------

CREATE TABLE `kurssit` (
  `tunnus` int(11) NOT NULL AUTO_INCREMENT,
  `nimi` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kuvaus` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alkupaiva` date NOT NULL,
  `loppupaiva` date NOT NULL,
  `opettaja_tunnus` int(11) NOT NULL,
  `tila_tunnus` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tunnus`),
  KEY `idx_kurssit_opettaja` (`opettaja_tunnus`),
  KEY `idx_kurssit_tila` (`tila_tunnus`),
  KEY `idx_kurssit_alkupaiva` (`alkupaiva`),
  CONSTRAINT `kurssit_ibfk_1` FOREIGN KEY (`opettaja_tunnus`) REFERENCES `opettajat` (`tunnus`) ON DELETE CASCADE,
  CONSTRAINT `kurssit_ibfk_2` FOREIGN KEY (`tila_tunnus`) REFERENCES `tilat` (`tunnus`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `kurssit`
INSERT INTO `kurssit` (`tunnus`, `nimi`, `kuvaus`, `alkupaiva`, `loppupaiva`, `opettaja_tunnus`, `tila_tunnus`, `created_at`, `updated_at`) VALUES
('1', 'Matematiikka 1', 'Perusmatematiikan kurssi ensimmäiselle vuosikurssille', '2025-10-10', '2025-12-10', '1', '1', '2025-09-04 10:31:11', '2025-09-04 10:41:36'),
('2', 'Fysiikka 2', 'Fysiikan jatkokurssi toiselle vuosikurssille', '2025-05-01', '2025-07-20', '2', '2', '2025-09-04 10:31:11', '2025-09-04 10:41:23'),
('3', 'Kemia 1', 'Kemian peruskurssi', '2025-09-03', '2025-12-05', '3', '3', '2025-09-04 10:31:11', '2025-09-04 10:40:41');

-- --------------------------------------------------------
-- Table structure for table `kurssikirjautumiset`
-- --------------------------------------------------------

CREATE TABLE `kurssikirjautumiset` (
  `tunnus` int(11) NOT NULL AUTO_INCREMENT,
  `opiskelija_tunnus` int(11) NOT NULL,
  `kurssi_tunnus` int(11) NOT NULL,
  `kirjautumispvm` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tunnus`),
  UNIQUE KEY `opiskelija_tunnus` (`opiskelija_tunnus`,`kurssi_tunnus`),
  KEY `idx_kirjautumiset_opiskelija` (`opiskelija_tunnus`),
  KEY `idx_kirjautumiset_kurssi` (`kurssi_tunnus`),
  CONSTRAINT `kurssikirjautumiset_ibfk_1` FOREIGN KEY (`opiskelija_tunnus`) REFERENCES `opiskelijat` (`tunnus`) ON DELETE CASCADE,
  CONSTRAINT `kurssikirjautumiset_ibfk_2` FOREIGN KEY (`kurssi_tunnus`) REFERENCES `kurssit` (`tunnus`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `kurssikirjautumiset`
INSERT INTO `kurssikirjautumiset` (`tunnus`, `opiskelija_tunnus`, `kurssi_tunnus`, `kirjautumispvm`, `created_at`, `updated_at`) VALUES
('1', '1', '1', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('2', '2', '2', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('3', '3', '3', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('4', '4', '1', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('5', '5', '2', '2025-09-04 10:31:11', '2025-09-04 10:31:11', '2025-09-04 10:31:11'),
('6', '6', '1', '2025-09-04 10:42:38', '2025-09-04 10:42:38', '2025-09-04 10:42:38'),
('8', '6', '2', '2025-09-04 10:42:50', '2025-09-04 10:42:50', '2025-09-04 10:42:50'),
('9', '2', '1', '2025-09-04 10:42:53', '2025-09-04 10:42:53', '2025-09-04 10:42:53'),
('10', '1', '3', '2025-09-04 10:43:02', '2025-09-04 10:43:02', '2025-09-04 10:43:02'),
('11', '1', '2', '2025-09-04 10:43:06', '2025-09-04 10:43:06', '2025-09-04 10:43:06'),
('12', '4', '2', '2025-09-04 10:43:10', '2025-09-04 10:43:10', '2025-09-04 10:43:10'),
('13', '4', '3', '2025-09-04 10:43:14', '2025-09-04 10:43:14', '2025-09-04 10:43:14'),
('14', '3', '1', '2025-09-04 10:43:26', '2025-09-04 10:43:26', '2025-09-04 10:43:26');

-- --------------------------------------------------------
-- View structure for view `kurssi_nakyma`
-- --------------------------------------------------------

CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `kurssi_nakyma` AS select `k`.`tunnus` AS `tunnus`,`k`.`nimi` AS `nimi`,`k`.`kuvaus` AS `kuvaus`,`k`.`alkupaiva` AS `alkupaiva`,`k`.`loppupaiva` AS `loppupaiva`,concat(`o`.`etunimi`,' ',`o`.`sukunimi`) AS `opettaja_nimi`,`t`.`nimi` AS `tila_nimi`,`t`.`kapasiteetti` AS `kapasiteetti`,count(`kk`.`opiskelija_tunnus`) AS `osallistujia` from (((`kurssit` `k` join `opettajat` `o` on(`k`.`opettaja_tunnus` = `o`.`tunnus`)) join `tilat` `t` on(`k`.`tila_tunnus` = `t`.`tunnus`)) left join `kurssikirjautumiset` `kk` on(`k`.`tunnus` = `kk`.`kurssi_tunnus`)) group by `k`.`tunnus`;

-- --------------------------------------------------------
-- View structure for view `opettaja_nakyma`
-- --------------------------------------------------------

CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `opettaja_nakyma` AS select `o`.`tunnus` AS `tunnus`,`o`.`etunimi` AS `etunimi`,`o`.`sukunimi` AS `sukunimi`,`o`.`aine` AS `aine`,count(`k`.`tunnus`) AS `kurssien_maara` from (`opettajat` `o` left join `kurssit` `k` on(`o`.`tunnus` = `k`.`opettaja_tunnus`)) group by `o`.`tunnus`;

-- --------------------------------------------------------
-- View structure for view `opiskelija_nakyma`
-- --------------------------------------------------------

CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `opiskelija_nakyma` AS select `o`.`tunnus` AS `tunnus`,`o`.`opiskelijanumero` AS `opiskelijanumero`,`o`.`etunimi` AS `etunimi`,`o`.`sukunimi` AS `sukunimi`,`o`.`syntymapaiva` AS `syntymapaiva`,`o`.`vuosikurssi` AS `vuosikurssi`,count(`kk`.`kurssi_tunnus`) AS `kurssien_maara` from (`opiskelijat` `o` left join `kurssikirjautumiset` `kk` on(`o`.`tunnus` = `kk`.`opiskelija_tunnus`)) group by `o`.`tunnus`;

-- --------------------------------------------------------
-- View structure for view `tila_nakyma`
-- --------------------------------------------------------

CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `tila_nakyma` AS select `t`.`tunnus` AS `tunnus`,`t`.`nimi` AS `nimi`,`t`.`kapasiteetti` AS `kapasiteetti`,count(distinct `k`.`tunnus`) AS `kurssien_maara`,count(`kk`.`opiskelija_tunnus`) AS `osallistujia_yhteensa` from ((`tilat` `t` left join `kurssit` `k` on(`t`.`tunnus` = `k`.`tila_tunnus`)) left join `kurssikirjautumiset` `kk` on(`k`.`tunnus` = `kk`.`kurssi_tunnus`)) group by `t`.`tunnus`;

-- --------------------------------------------------------
-- End of migration export
-- --------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
