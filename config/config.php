<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Configuration generale du projet
 * ============================================================================
 *
 * Ce fichier centralise :
 *   - Les parametres de connexion a la base de donnees
 *   - L'URL de base du projet (BASE_URL) pour les assets et redirections
 *   - Les constantes de configuration generales
 *
 * Tous les autres fichiers du projet incluent ce fichier en premier.
 * ============================================================================
 */

// --- Mode developpement (a desactiver en production) ---
error_reporting(E_ALL);
ini_set('display_errors', '1');

// --- Chemin racine du projet (absolu, sur disque) ---
define('ROOT_PATH', dirname(__DIR__));

// --- URL de base (utilisee pour les assets, redirections, fetch API) ---
// Adapte si le projet est deplace dans un autre dossier.
define('BASE_URL', '/MesProjetsPhp/atlantis-s');

// --- Parametres de connexion MariaDB ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'atlantis_v2');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Configuration de la session ---
define('SESSION_LIFETIME', 3600);           // Duree de vie de la session en secondes (1h)
define('MAX_LOGIN_ATTEMPTS', 5);            // Nombre max de tentatives avant blocage
define('LOGIN_LOCKOUT_TIME', 900);          // Duree du blocage en secondes (15 min)

// --- Configuration du site ---
define('SITE_NAME', 'ATLANTIS');
define('SITE_DESC', 'Centre d\'appel & relation client');
