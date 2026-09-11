<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Connexion PDO a la base de donnees
 * ============================================================================
 *
 * Fournit la fonction getDB() qui retourne une instance PDO singleton.
 * Toutes les requetes SQL du projet passent par cette fonction.
 * ============================================================================
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Retourne une instance unique de PDO (connexion a MariaDB).
 * Utilise une variable statique pour reutiliser la meme connexion.
 *
 * @throws PDOException si la connexion echoue.
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Erreur de connexion a la base de donnees.');
    }

    return $pdo;
}
