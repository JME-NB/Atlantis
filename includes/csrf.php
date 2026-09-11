<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Protection CSRF
 * ============================================================================
 *
 * Genere et verifie les jetons CSRF pour proteger les formulaires
 * et les actions de modification (POST/PATCH/DELETE).
 * ============================================================================
 */

require_once __DIR__ . '/functions.php';

/**
 * Genere et retourne le token CSRF de la session.
 * Cree le token au premier appel, le reutilise ensuite.
 *
 * @return string
 */
function generateCsrfToken(): string
{
    ensureSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verifie la validite d'un token CSRF envoye par le client.
 * Utilise hash_equals() pour eviter les attaques temporelles.
 *
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token): bool
{
    ensureSession();

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
