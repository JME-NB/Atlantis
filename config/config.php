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

// --- Environnement (dev par defaut, passer a 'production' en ligne) ---
$appEnv = getenv('APP_ENV') ?: 'dev';
define('APP_ENV', $appEnv);

// --- Affichage des erreurs (actif en dev, desactive en production) ---
error_reporting(E_ALL);
ini_set('display_errors', $appEnv === 'production' ? '0' : '1');

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

// --- Pieces jointes (demandes publiques) ---
define('MAX_PIECES_PAR_DEMANDE', 6);             // Nb max de pieces par demande
define('MAX_TAILLE_PIECE', 5 * 1024 * 1024);     // 5 Mo max par piece

// --- Signature du cookie et des liens d'apercu (obligatoire en production) ---
// En production (APP_ENV=production) le secret DOIT venir de l'environnement :
// sans lui l'application refusera de demarrer (fail-closed) afin d'eviter
// qu'un secret faible et commite ne signe les cookies remember-me et les
// liens d'apercu. En dev le fallback ci-dessous est conserve pour la simplicite.
if (($appEnv === 'production') && !getenv('APP_SECRET')) {
    http_response_code(500);
    exit('ERREUR CONFIGURATION : variable APP_SECRET manquante. Definissez-la dans l\'environnement de production.');
}
define('APP_SECRET', getenv('APP_SECRET') ?: '02501dae012b02b7add78d190ee663142b2d87d73642e0be4f24efb4faf88fd1');

// --- Signature des liens d'apercu de design (?preview=ID&sig=...) ---
define('APP_PREVIEW_KEY', 'atlantis-preview-' . APP_SECRET); // Derive de APP_SECRET (a garder secrete)
define('REMEMBER_COOKIE', 'atlantis_remember');
define('REMEMBER_COOKIE_LIFETIME', 2592000); // 30 jours en secondes
