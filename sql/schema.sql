-- ============================================================================
-- ATLANTIS v2 - Schema de la base de donnees MariaDB
-- ============================================================================
-- Importez ce fichier via phpMyAdmin pour creer la base atlantis_v2
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `atlantis_v2`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `atlantis_v2`;

-- ---------------------------------------------------------------------------
-- Table des demandes (candidatures / partenariats)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `applications` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `nom`        VARCHAR(100) NOT NULL,
    `prenom`     VARCHAR(100) DEFAULT NULL,
    `telephone`  VARCHAR(30) NOT NULL,
    `email`      VARCHAR(150) DEFAULT NULL,
    `entreprise` VARCHAR(150) DEFAULT NULL,
    `type`       ENUM('partenariat','recrutement') NOT NULL,
    `message`    TEXT DEFAULT NULL,
    `statut`     ENUM('en_attente','en_cours','valide','refuse','archive') NOT NULL DEFAULT 'en_attente',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_type` (`type`),
    INDEX `idx_statut` (`statut`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Table des comptes administrateurs
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`                   INT AUTO_INCREMENT PRIMARY KEY,
    `identifiant`          VARCHAR(50) NOT NULL UNIQUE,
    `nom_complet`          VARCHAR(150) NOT NULL,
    `mot_de_passe_hash`    VARCHAR(255) NOT NULL,
    `role`                 ENUM('admin','gestionnaire','csm') NOT NULL DEFAULT 'gestionnaire',
    `is_super_admin`       BOOLEAN NOT NULL DEFAULT FALSE,
    `must_change_password` BOOLEAN NOT NULL DEFAULT TRUE,
    `statut_compte`        ENUM('actif','desactive') NOT NULL DEFAULT 'actif',
    `deleted_at`           DATETIME DEFAULT NULL,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role` (`role`),
    INDEX `idx_statut_compte` (`statut_compte`),
    INDEX `idx_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Table du journal d'audit
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`                   INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`              INT DEFAULT NULL,
    `identifiant_snapshot` VARCHAR(50) NOT NULL,
    `role_snapshot`        VARCHAR(20) NOT NULL,
    `action`               VARCHAR(255) NOT NULL,
    `cible_type`           VARCHAR(50) DEFAULT NULL,
    `cible_id`             INT DEFAULT NULL,
    `details`              TEXT DEFAULT NULL,
    `adresse_ip`           VARCHAR(45) DEFAULT NULL,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_cible` (`cible_type`, `cible_id`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Table des parametres du site (V2 - CMS)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `cle`        VARCHAR(100) NOT NULL UNIQUE,
    `valeur`     TEXT NOT NULL,
    `updated_by` INT DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Table des sections de la landing page (V2 - CMS)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_sections` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `section_key`  VARCHAR(100) NOT NULL UNIQUE,
    `titre`        VARCHAR(255) DEFAULT NULL,
    `contenu`      TEXT DEFAULT NULL,
    `image_url`    VARCHAR(255) DEFAULT NULL,
    `ordre`        INT DEFAULT 0,
    `visible`      BOOLEAN DEFAULT TRUE,
    `updated_by`   INT DEFAULT NULL,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Compte super-admin par defaut
-- Mot de passe : admin123 (a changer apres premiere connexion)
-- hash genere avec password_hash('admin123', PASSWORD_BCRYPT)
-- ---------------------------------------------------------------------------
INSERT INTO `users` (`identifiant`, `nom_complet`, `mot_de_passe_hash`, `role`, `is_super_admin`, `must_change_password`)
VALUES (
    'admin',
    'Administrateur',
    '$2y$10$AkfQTARGqVXn922BKJ1mx.cpk994K4h4D48XprDDNC.KW.FVALUEe',
    'admin',
    TRUE,
    TRUE
);

-- ---------------------------------------------------------------------------
-- Parametres du site par defaut
-- ---------------------------------------------------------------------------
INSERT INTO `site_settings` (`cle`, `valeur`) VALUES
('couleur_primaire', '#0a1628'),
('couleur_secondaire', '#00b4d8'),
('couleur_fond', '#f8fafc'),
('police_titre', 'Inter'),
('police_corps', 'Inter'),
('logo_url', ''),
('banniere_url', '');

-- ---------------------------------------------------------------------------
-- Sections de la landing page par defaut
-- ---------------------------------------------------------------------------
INSERT INTO `site_sections` (`section_key`, `titre`, `contenu`, `ordre`, `visible`) VALUES
('hero', 'ATLANTIS', 'Votre relation client, notre savoir-faire.', 1, TRUE),
('apropos', 'Plus qu\'un centre d\'appel. Un partenaire.', 'Chez ATLANTIS, nous ne nous contentons pas de traiter des appels.', 2, TRUE),
('services', 'Des solutions adaptees a vos objectifs', 'De la televente au support client.', 3, TRUE),
('pourquoi', 'Pourquoi choisir ATLANTIS ?', 'Quatre raisons de faire confiance a notre equipe.', 4, TRUE),
('processus', 'Un deploiement maitrise en 4 etapes', NULL, 5, TRUE),
('secteurs', 'Une expertise qui s\'adapte a votre activite', NULL, 6, TRUE),
('contact', 'Travaillons ensemble', 'Vous souhaitez developper votre activite avec ATLANTIS ou rejoindre notre equipe ?', 7, TRUE),
('footer', NULL, NULL, 8, TRUE);
