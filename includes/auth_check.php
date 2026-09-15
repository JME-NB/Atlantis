<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Middleware d'authentification pour les pages admin
 * ============================================================================
 *
 * Ce fichier est inclus en haut de chaque page admin pour :
 *   1. Demarrer la session
 *   2. Verifier que l'utilisateur est connecte
 *   3. Bloquer l'acces si le mot de passe doit etre change
 *
 * A inclure AVANT tout output HTML :
 *   require_once __DIR__ . '/../includes/auth_check.php';
 * ============================================================================
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

// 0. Connexion automatique via le cookie "se souvenir de moi"
maybeAutoLogin();

// 1. Verifier que l'utilisateur est connecte
requireAuth();

// 2. Verifier le compte et re-synchroniser la session depuis la base
//    (permet de corriger les sessions obsolètes dont le role ne serait pas present)
try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT identifiant, nom_complet, role, is_super_admin, must_change_password, statut_compte
         FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([':id' => getAdminId()]);
    $user = $stmt->fetch();

    // Si le compte est desactive ou supprime, deconnecter
    if (!$user || $user['statut_compte'] === 'desactive') {
        session_destroy();
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }

    // Re-synchroniser les valeurs de session avec la base (autorite du role)
    $_SESSION['admin_id']          = getAdminId();
    $_SESSION['admin_username']    = $user['identifiant'];
    $_SESSION['admin_role']        = $user['role'];
    $_SESSION['admin_nom_complet'] = $user['nom_complet'];
    $_SESSION['admin_is_super']    = (bool) $user['is_super_admin'];

    // Si le mot de passe doit etre change et qu'on n'est pas deja sur la page de changement
    if ($user['must_change_password']) {
        $currentPage = basename($_SERVER['SCRIPT_NAME']);
        if ($currentPage !== 'change_password.php') {
            header('Location: ' . BASE_URL . '/admin/change_password.php');
            exit;
        }
    }
} catch (PDOException $e) {
    // En cas d'erreur DB, on garde la session telle quelle (deja authentifie)
    logError('ERROR', 'Auth check DB error: ' . $e->getMessage(), 'includes/auth_check.php', 61);
}
