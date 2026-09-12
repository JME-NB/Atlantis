<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Deconnexion
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';

ensureSession();

if (isLoggedIn()) {
    logAudit('logout', 'user', getAdminId(), 'Deconnexion');
}

// Supprime le cookie "se souvenir de moi" pour eviter la reconnexion automatique
clearRememberCookie();

session_destroy();
header('Location: ' . BASE_URL . '/admin/login.php');
exit;
