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
-- Table des pieces jointes (Dossier de candidature)
-- application_id NULL tant que la candidature n'est pas soumise (statut pending).
-- Fichiers stockes hors acces web dans private/candidatures/.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `application_pieces` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT DEFAULT NULL,
    `token`          VARCHAR(64) NOT NULL UNIQUE,
    `session_hash`   VARCHAR(64) NOT NULL,
    `nom_fichier`    VARCHAR(255) NOT NULL,
    `chemin_fichier` VARCHAR(255) NOT NULL,
    `mime_type`      VARCHAR(100) NOT NULL,
    `taille`         INT UNSIGNED NOT NULL,
    `statut`         ENUM('pending','recu') NOT NULL DEFAULT 'pending',
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`),
    INDEX `idx_application` (`application_id`),
    INDEX `idx_statut` (`statut`),
    CONSTRAINT `fk_piece_app` FOREIGN KEY (`application_id`)
        REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Table des champs personnalises des candidatures (editeur de formulaire)
-- Valeurs des champs libres (custom_*) ajoutes via admin/design_form.php.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `application_champs_personnalises` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT NOT NULL,
    `champ`          VARCHAR(100) NOT NULL,
    `valeur`         TEXT DEFAULT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_champ_app` (`application_id`, `champ`),
    CONSTRAINT `fk_champ_app` FOREIGN KEY (`application_id`)
        REFERENCES `applications`(`id`) ON DELETE CASCADE
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
-- Table des erreurs (logs)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `error_logs` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `level`      VARCHAR(20) NOT NULL,
    `message`    TEXT NOT NULL,
    `file`       VARCHAR(500) DEFAULT NULL,
    `line`       INT DEFAULT NULL,
    `url`        VARCHAR(500) DEFAULT NULL,
    `user_id`    INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_level` (`level`),
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
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `section_key`   VARCHAR(100) NOT NULL UNIQUE,
    `titre`         VARCHAR(255) DEFAULT NULL,
    `contenu`       TEXT DEFAULT NULL,
    `image_url`     VARCHAR(255) DEFAULT NULL,
    `fond_couleur`  VARCHAR(9) DEFAULT NULL,
    `texte_couleur` VARCHAR(9) DEFAULT NULL,
    `fond_image_url` VARCHAR(255) DEFAULT NULL,
    `titre_taille`  VARCHAR(10) DEFAULT NULL,
    `ordre`         INT DEFAULT 0,
    `visible`       BOOLEAN DEFAULT TRUE,
    `updated_by`    INT DEFAULT NULL,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Table des rubriques des sections de la landing page (cartes dynamiques)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_rubriques` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `section_id`    INT NOT NULL,
    `position`      INT DEFAULT 0,
    `titre`         VARCHAR(255) DEFAULT NULL,
    `contenu`       TEXT DEFAULT NULL,
    `icone`         VARCHAR(50) DEFAULT NULL,
    `image_url`     VARCHAR(255) DEFAULT NULL,
    `fond_couleur`  VARCHAR(9) DEFAULT NULL,
    `texte_couleur` VARCHAR(9) DEFAULT NULL,
    `visible`       BOOLEAN DEFAULT TRUE,
    `updated_by`    INT DEFAULT NULL,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_rub_section` (`section_id`),
    KEY `idx_rub_position` (`position`),
    CONSTRAINT `fk_rub_section` FOREIGN KEY (`section_id`) REFERENCES `site_sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Table des designs sauvegardes (landing / connexion / admin)
-- Un design = configuration JSON complete de SA cible.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `designs` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `nom`           VARCHAR(150) NOT NULL,
    `description`   VARCHAR(250) DEFAULT NULL,
    `cible`         ENUM('landing','login','admin') NOT NULL,
    `configuration` LONGTEXT NOT NULL,
    `active`        BOOLEAN DEFAULT FALSE,
    `updated_by`    INT DEFAULT NULL,
    `deleted_by`    INT DEFAULT NULL,
    `deleted_at`    DATETIME DEFAULT NULL,
    `created_by`    INT DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_design_cible` (`cible`),
    KEY `idx_design_active` (`cible`, `active`),
    CONSTRAINT `fk_design_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Notifications (boite de notification admin)
-- destinataires : roles separes par des virgules, ex 'admin,gestionnaire'
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `type_notification`  VARCHAR(50) NOT NULL,
    `message`            VARCHAR(255) NOT NULL,
    `destinataires`      VARCHAR(50) NOT NULL,
    `auteur_id`          INT DEFAULT NULL,
    `auteur_identifiant` VARCHAR(50) DEFAULT NULL,
    `cible_type`         VARCHAR(30) DEFAULT NULL,
    `cible_id`           INT DEFAULT NULL,
    `cree_le`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_notif_dest` (`destinataires`),
    KEY `idx_notif_cree` (`cree_le`),
    CONSTRAINT `fk_notif_auteur` FOREIGN KEY (`auteur_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Lectures des notifications (non lu = absence de ligne)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notification_reads` (
    `user_id`          INT NOT NULL,
    `notification_id`  INT NOT NULL,
    `lu_le`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `notification_id`),
    CONSTRAINT `fk_read_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_read_notif` FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE CASCADE
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
('banniere_url', ''),
('login_fond', '#0a1628'),
('login_primaire', '#0a1628'),
('login_secondaire', '#00b4d8'),
('login_bg_url', ''),
('login_police', 'Inter'),
('login_police_taille', '1.15'),
('login_titre_taille', '2.5'),
('login_texte_couleur', '#1e293b'),
('login_carte_fond', '#ffffff'),
('login_bg_fondu', '55'),
('landing_taille_base', '16'),
('landing_taille_hero_titre', '42'),
('landing_taille_titre_section', '54'),
('landing_taille_corps', '24'),
('admin_couleur_primaire', '#0a1628'),
('admin_couleur_primaire_light', '#1a2d4a'),
('admin_couleur_secondaire', '#00b4d8'),
('admin_couleur_secondaire_hover', '#0096b7'),
('admin_fond', '#f1f5f9'),
('admin_fond_card', '#ffffff'),
('admin_texte', '#1e293b'),
('admin_texte_light', '#64748b'),
('admin_texte_muted', '#94a3b8'),
('admin_border', '#e2e8f0'),
('admin_danger', '#ef4444'),
('admin_police', 'Inter'),
('admin_police_echelle', '16'),
('landing_form_fields', '[{"cle":"nom","type":"text","libelle":"Nom","placeholder":"Votre nom","obligatoire":true,"visible":true,"options":[]},{"cle":"prenom","type":"text","libelle":"Prenom","placeholder":"Votre prenom","obligatoire":false,"visible":true,"options":[]},{"cle":"telephone","type":"tel","libelle":"Telephone","placeholder":"+237 6 XX XX XX XX","obligatoire":true,"visible":true,"options":[]},{"cle":"email","type":"email","libelle":"Email","placeholder":"vous@exemple.com","obligatoire":false,"visible":true,"options":[]},{"cle":"entreprise","type":"text","libelle":"Entreprise","placeholder":"Nom de votre entreprise","obligatoire":false,"visible":true,"options":[]},{"cle":"type","type":"select","libelle":"Type de demande","placeholder":"","obligatoire":true,"visible":true,"options":["partenariat","recrutement"]},{"cle":"pieces","type":"file","libelle":"Dossier de candidature","placeholder":"","obligatoire":true,"visible":true,"options":[]},{"cle":"message","type":"textarea","libelle":"Message","placeholder":"Decrivez brievement votre projet ou votre profil...","obligatoire":false,"visible":true,"options":[]}]');

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

-- ---------------------------------------------------------------------------
-- Rubriques par defaut (cartes dynamiques de chaque section)
-- ---------------------------------------------------------------------------
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 1, 'Ecoute', 'Nous comprenons les besoins de vos clients avant de repondre.', 'ecoute' FROM `site_sections` WHERE `section_key` = 'apropos';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 2, 'Reactivite', 'Des agents formes et disponibles pour traiter vos demandes rapidement.', 'reactivite' FROM `site_sections` WHERE `section_key` = 'apropos';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 3, 'Qualite', 'Un suivi rigoureux pour garantir l\'excellence de chaque interaction.', 'qualite' FROM `site_sections` WHERE `section_key` = 'apropos';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 1, 'Televente', 'Nos agents qualifies vendent vos produits et services avec professionalisme et empathie.', 'televente' FROM `site_sections` WHERE `section_key` = 'services';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 2, 'Prospection', 'Identification et contact proactif de prospects qualifies pour developper votre portefeuille.', 'prospection' FROM `site_sections` WHERE `section_key` = 'services';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 3, 'Service apres-vente', 'Support technique et gestion des reclamations pour maintenir la satisfaction client.', 'apres_vente' FROM `site_sections` WHERE `section_key` = 'services';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 4, 'Relation client', 'Prise en charge complete de vos clients pour fideliser et ameliorer leur experience.', 'relation' FROM `site_sections` WHERE `section_key` = 'services';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 1, 'Expertise locale', 'Une equipe bilingue basee a Yaounde, connaissant les realites du marche camerounais et africain.', NULL FROM `site_sections` WHERE `section_key` = 'pourquoi';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 2, 'Flexibilite', 'Des solutions sur mesure adaptees a la taille et aux objectifs de votre entreprise.', NULL FROM `site_sections` WHERE `section_key` = 'pourquoi';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 3, 'Technologie', 'Des outils modernes pour un suivi en temps reel et des rapports detailles.', NULL FROM `site_sections` WHERE `section_key` = 'pourquoi';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 4, 'Engagement', 'Nous nous impliquons dans vos projets comme si c\'etaient les notres.', NULL FROM `site_sections` WHERE `section_key` = 'pourquoi';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 1, 'Analyse', 'Nous etudions vos besoins, votre marche et vos objectifs pour definir une strategie sur mesure.', NULL FROM `site_sections` WHERE `section_key` = 'processus';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 2, 'Mise en place', 'Recrutement, formation et equipement de votre equipe selon vos specifications.', NULL FROM `site_sections` WHERE `section_key` = 'processus';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 3, 'Lancement', 'Deploiement progressif avec des tests pilotes avant le lancement a grande echelle.', NULL FROM `site_sections` WHERE `section_key` = 'processus';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 4, 'Suivi', 'Reporting regulier, optimisation continue et points d\'etat pour garantir la performance.', NULL FROM `site_sections` WHERE `section_key` = 'processus';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 1, 'Telecom', 'Support abonnes, gestion des forfaits et accompagnement technique pour les operateurs.', 'telecom' FROM `site_sections` WHERE `section_key` = 'secteurs';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 2, 'Banque & Finance', 'Service client bancaire, conseil financier et gestion des reclamations.', 'banque' FROM `site_sections` WHERE `section_key` = 'secteurs';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 3, 'Sante', 'Prise de rendez-vous, rappels patients et support aux professionnels de sante.', 'sante' FROM `site_sections` WHERE `section_key` = 'secteurs';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 4, 'Retail & E-commerce', 'SAV client, suivi de commandes et support technique pour vos clients.', 'retail' FROM `site_sections` WHERE `section_key` = 'secteurs';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 5, 'Energie', 'Service client, gestion des comptes et support technique pour les fournisseurs.', 'energie' FROM `site_sections` WHERE `section_key` = 'secteurs';
INSERT INTO `site_rubriques` (`section_id`, `position`, `titre`, `contenu`, `icone`)
SELECT id, 6, 'Assurance', 'Gestion des sinistres, souscription et support assure pour vos clients.', 'assurance' FROM `site_sections` WHERE `section_key` = 'secteurs';
