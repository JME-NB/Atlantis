<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API : Authentification
 * ============================================================================
 *
 * Endpoints :
 *   POST ?action=login
 *   POST ?action=logout
 *   POST ?action=change_password
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'change_password':
        handleChangePassword();
        break;
    case 'check':
        handleCheck();
        break;
    default:
        jsonError(400, 'Action inconnue.');
}

// ---------------------------------------------------------------------------
// LOGIN
// ---------------------------------------------------------------------------
function handleLogin(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError(405, 'Methode non autorisee.');
    }

    $identifiant = cleanRaw($_POST['identifiant'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if ($identifiant === '' || $mot_de_passe === '') {
        jsonError(400, 'Veuillez remplir tous les champs.');
    }

    // Verifier le blocage
    if (isLoginLocked($identifiant)) {
        $remaining = LOGIN_LOCKOUT_TIME - (time() - ($_SESSION['login_lock_' . md5($identifiant)]['time'] ?? 0));
        jsonError(429, 'Trop de tentatives. Reessayez dans ' . ceil($remaining / 60) . ' minute(s).');
    }

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT id, identifiant, nom_complet, mot_de_passe_hash, role, is_super_admin, must_change_password, statut_compte
             FROM users
             WHERE identifiant = :identifiant AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':identifiant' => $identifiant]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($mot_de_passe, $user['mot_de_passe_hash'])) {
            recordFailedLogin($identifiant);
            logAudit('login_failed', 'user', null, 'Tentative echouee pour : ' . $identifiant);
            jsonError(401, 'Identifiants incorrects.');
        }

        if ($user['statut_compte'] === 'desactive') {
            jsonError(403, 'Ce compte est desactive. Contactez un administrateur.');
        }

        // Connexion reussie
        resetLoginAttempts($identifiant);
        $_SESSION['admin_id']           = $user['id'];
        $_SESSION['admin_username']     = $user['identifiant'];
        $_SESSION['admin_role']         = $user['role'];
        $_SESSION['admin_nom_complet']  = $user['nom_complet'];
        $_SESSION['admin_is_super']     = (bool) $user['is_super_admin'];
        startAdminSession();

        // "Se souvenir de moi" : cookie signe HMAC de 30 jours
        if (($_POST['remember_me'] ?? '') === '1') {
            issueRememberCookie((int) $user['id']);
        }

        logAudit('login_success', 'user', $user['id'], 'Connexion reussie');

        $mustChange = (bool) $user['must_change_password'];

        jsonSuccess('Connexion reussie.', [
            'must_change_password' => $mustChange,
            'role' => $user['role'],
        ]);
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        jsonError(500, 'Une erreur interne est survenue.');
    }
}

// ---------------------------------------------------------------------------
// LOGOUT
// ---------------------------------------------------------------------------
function handleLogout(): void
{
    ensureSession();

    if (isLoggedIn()) {
        logAudit('logout', 'user', getAdminId(), 'Deconnexion');
    }

    clearRememberCookie();
    session_destroy();
    jsonSuccess('Deconnexion reussie.');
}

// ---------------------------------------------------------------------------
// CHANGE PASSWORD
// ---------------------------------------------------------------------------
function handleChangePassword(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError(405, 'Methode non autorisee.');
    }

    if (!isLoggedIn()) {
        jsonError(401, 'Acces refuse.');
    }

    $ancien     = $_POST['ancien_mot_de_passe'] ?? '';
    $nouveau    = $_POST['nouveau_mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    // Si c'est un changement force (premiere connexion), on ne demande pas l'ancien mdp
    $forceChange = $_POST['force'] ?? '0';

    if ($forceChange !== '1' && $ancien === '') {
        jsonError(400, 'L\'ancien mot de passe est obligatoire.');
    }

    if ($nouveau === '') {
        jsonError(400, 'Le nouveau mot de passe est obligatoire.');
    }

    if (mb_strlen($nouveau) < 6) {
        jsonError(400, 'Le mot de passe doit contenir au moins 6 caracteres.');
    }

    if ($nouveau !== $confirmation) {
        jsonError(400, 'Les mots de passe ne correspondent pas.');
    }

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT mot_de_passe_hash FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => getAdminId()]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonError(404, 'Compte introuvable.');
        }

        // Verifier l'ancien mot de passe sauf si changement force
        if ($forceChange !== '1' && !password_verify($ancien, $user['mot_de_passe_hash'])) {
            jsonError(400, 'L\'ancien mot de passe est incorrect.');
        }

        $newHash = password_hash($nouveau, PASSWORD_BCRYPT);
        $stmt    = $pdo->prepare(
            'UPDATE users SET mot_de_passe_hash = :hash, must_change_password = FALSE WHERE id = :id'
        );
        $stmt->execute([':hash' => $newHash, ':id' => getAdminId()]);

        logAudit('change_password', 'user', getAdminId(), 'Mot de passe modifie');

        jsonSuccess('Mot de passe modifie avec succes.');
    } catch (PDOException $e) {
        error_log('Change password error: ' . $e->getMessage());
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// CHECK (verifier l'etat de la session)
// ---------------------------------------------------------------------------
function handleCheck(): void
{
    if (!isLoggedIn()) {
        jsonError(401, 'Non connecte.');
    }

    jsonSuccess('Connecte.', [
        'id'        => getAdminId(),
        'username'  => getAdminUsername(),
        'role'      => getAdminRole(),
        'is_super'  => isSuperAdmin(),
    ]);
}
