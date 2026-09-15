<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API : Gestion des utilisateurs
 * ============================================================================
 *
 * Endpoints :
 *   GET    ?action=list
 *   POST   ?action=create
 *   POST   ?action=update_role
 *   POST   ?action=reset_password
 *   DELETE ?action=delete
 *   POST   ?action=update_preferences
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'list':
        handleList();
        break;
    case 'create':
        handleCreate();
        break;
    case 'update_role':
        handleUpdateRole();
        break;
    case 'reset_password':
        handleResetPassword();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'update_preferences':
        handleUpdatePreferences();
        break;
    default:
        jsonError(400, 'Action inconnue.');
}

// ---------------------------------------------------------------------------
// LIST (admin/super-admin)
// ---------------------------------------------------------------------------
function handleList(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission('admin')) jsonError(403, 'Permissions insuffisantes.');

    $pdo  = getDB();
    $stmt = $pdo->query(
        'SELECT id, identifiant, nom_complet, role, is_super_admin, must_change_password, statut_compte, deleted_at, created_at
         FROM users ORDER BY created_at DESC'
    );
    $users = $stmt->fetchAll();

    jsonSuccess('Liste recupereree.', ['items' => $users]);
}

// ---------------------------------------------------------------------------
// CREATE (admin/super-admin)
// ---------------------------------------------------------------------------
function handleCreate(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission('admin')) jsonError(403, 'Permissions insuffisantes.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    $identifiant = cleanRaw($_POST['identifiant'] ?? '');
    $nomComplet  = clean($_POST['nom_complet'] ?? '');
    $role        = $_POST['role'] ?? '';

    $errors = [];

    if ($identifiant === '') {
        $errors['identifiant'] = 'L\'identifiant est obligatoire.';
    } elseif (mb_strlen($identifiant) < 3 || mb_strlen($identifiant) > 50) {
        $errors['identifiant'] = 'L\'identifiant doit faire entre 3 et 50 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $identifiant)) {
        $errors['identifiant'] = 'L\'identifiant ne peut contenir que des lettres, chiffres, points, tirets.';
    }

    if ($nomComplet === '') {
        $errors['nom_complet'] = 'Le nom complet est obligatoire.';
    }

    $allowedRoles = ['admin', 'gestionnaire', 'csm'];
    if (!in_array($role, $allowedRoles, true)) {
        $errors['role'] = 'Role invalide.';
    }

    if (!empty($errors)) {
        jsonError(400, 'Donnees invalides.', ['errors' => $errors]);
    }

    try {
        $pdo = getDB();

        // Verifier l'unicite de l'identifiant
        $stmt = $pdo->prepare('SELECT id FROM users WHERE identifiant = :identifiant LIMIT 1');
        $stmt->execute([':identifiant' => $identifiant]);
        if ($stmt->fetch()) {
            jsonError(409, 'Cet identifiant est deja utilise.');
        }

        // Seul un super-admin peut creer un compte admin
        if ($role === 'admin' && !isSuperAdmin()) {
            jsonError(403, 'Seul le super-administrateur peut creer un compte admin.');
        }

        $defaultPassword = '1234';
        $hash = password_hash($defaultPassword, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (identifiant, nom_complet, mot_de_passe_hash, role, is_super_admin, must_change_password)
             VALUES (:identifiant, :nom_complet, :hash, :role, FALSE, TRUE)'
        );
        $stmt->execute([
            ':identifiant' => $identifiant,
            ':nom_complet' => $nomComplet,
            ':hash'        => $hash,
            ':role'        => $role,
        ]);

        $newId = $pdo->lastInsertId();
        logAudit('create_user', 'user', (int)$newId, 'Compte cree : ' . $identifiant . ' (role: ' . $role . ')');

        notify(
            'utilisateur_cree',
            'Nouvel utilisateur ' . $identifiant . ' (' . $role . ') cree par ' . getAdminDisplayName(),
            ['admin'],
            'user',
            (int)$newId
        );

        jsonSuccess('Compte cree avec succes. Mot de passe par defaut : 1234', ['new_id' => $newId]);
    } catch (PDOException $e) {
        logError('ERROR', 'Create user error: ' . $e->getMessage(), 'api/users.php', 148);
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// UPDATE ROLE (admin/super-admin)
// ---------------------------------------------------------------------------
function handleUpdateRole(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission('admin')) jsonError(403, 'Permissions insuffisantes.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
    $newRole = $input['role'] ?? '';

    if (!$userId || $userId <= 0) jsonError(400, 'Identifiant invalide.');

    $allowedRoles = ['admin', 'gestionnaire', 'csm'];
    if (!in_array($newRole, $allowedRoles, true)) {
        jsonError(400, 'Role invalide.');
    }

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT id, identifiant, role, is_super_admin FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) jsonError(404, 'Utilisateur introuvable.');

        // Le super-admin ne peut pas etre retrograde
        if ($user['is_super_admin'] && !isSuperAdmin()) {
            jsonError(403, 'Vous ne pouvez pas modifier le compte super-administrateur.');
        }

        // Seul le super-admin peut modifier un autre admin
        if ($user['role'] === 'admin' && !isSuperAdmin()) {
            jsonError(403, 'Seul le super-administrateur peut modifier un compte admin.');
        }

        $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute([':role' => $newRole, ':id' => $userId]);

        logAudit('update_user_role', 'user', $userId, 'Role change de "' . $user['role'] . '" vers "' . $newRole . '" pour ' . $user['identifiant']);

        notify(
            'utilisateur_modifie',
            'Utilisateur ' . $user['identifiant'] . ' : role modifie ("' . $user['role'] . '" -> "' . $newRole . '") par ' . getAdminDisplayName(),
            ['admin'],
            'user',
            $userId
        );

        jsonSuccess('Role mis a jour avec succes.');
    } catch (PDOException $e) {
        logError('ERROR', 'Update role error: ' . $e->getMessage(), 'api/users.php', 213);
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// RESET PASSWORD (admin/super-admin)
// ---------------------------------------------------------------------------
function handleResetPassword(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission('admin')) jsonError(403, 'Permissions insuffisantes.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if (!$userId || $userId <= 0) jsonError(400, 'Identifiant invalide.');

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT id, identifiant, role, is_super_admin FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) jsonError(404, 'Utilisateur introuvable.');

        // Seul le super-admin peut reinitialiser un admin
        if ($user['role'] === 'admin' && !isSuperAdmin()) {
            jsonError(403, 'Seul le super-administrateur peut reinitialiser un compte admin.');
        }

        // Un admin ne peut pas reinitialiser un autre admin (seulement super-admin)
        if (hasPermission('admin') && !isSuperAdmin() && $user['role'] === 'admin') {
            jsonError(403, 'Permissions insuffisantes.');
        }

        $newHash = password_hash('1234', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE users SET mot_de_passe_hash = :hash, must_change_password = TRUE WHERE id = :id');
        $stmt->execute([':hash' => $newHash, ':id' => $userId]);

        logAudit('reset_password', 'user', $userId, 'Mot de passe reinitialise pour ' . $user['identifiant']);

        notify(
            'utilisateur_modifie',
            'Utilisateur ' . $user['identifiant'] . ' : mot de passe reinitialise par ' . getAdminDisplayName(),
            ['admin'],
            'user',
            $userId
        );

        jsonSuccess('Mot de passe reinitialise a "1234". L\'utilisateur devra le changer a sa prochaine connexion.');
    } catch (PDOException $e) {
        logError('ERROR', 'Reset password error: ' . $e->getMessage(), 'api/users.php', 270);
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// DELETE (admin/super-admin) - Soft delete
// ---------------------------------------------------------------------------
function handleDelete(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission('admin')) jsonError(403, 'Permissions insuffisantes.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    $userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$userId || $userId <= 0) jsonError(400, 'Identifiant invalide.');

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT id, identifiant, role, is_super_admin FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) jsonError(404, 'Utilisateur introuvable.');

        // Le super-admin ne peut jamais etre supprime
        if ($user['is_super_admin']) {
            jsonError(403, 'Le compte super-administrateur ne peut pas etre supprime.');
        }

        // Seul le super-admin peut supprimer un admin
        if ($user['role'] === 'admin' && !isSuperAdmin()) {
            jsonError(403, 'Seul le super-administrateur peut supprimer un compte admin.');
        }

        // On ne peut pas se supprimer soi-meme
        if ($userId === getAdminId()) {
            jsonError(400, 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $stmt = $pdo->prepare('UPDATE users SET deleted_at = NOW(), statut_compte = "desactive" WHERE id = :id');
        $stmt->execute([':id' => $userId]);

        logAudit('delete_user', 'user', $userId, 'Compte supprime (soft delete) : ' . $user['identifiant']);

        notify(
            'utilisateur_supprime',
            'Utilisateur ' . $user['identifiant'] . ' (' . $user['role'] . ') supprime par ' . getAdminDisplayName(),
            ['admin'],
            'user',
            $userId
        );

        jsonSuccess('Le compte a ete desactive et supprime.');
    } catch (PDOException $e) {
        logError('ERROR', 'Delete user error: ' . $e->getMessage(), 'api/users.php', 331);
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// UPDATE PREFERENCES (tous les roles, sur soi-meme uniquement)
// ---------------------------------------------------------------------------
function handleUpdatePreferences(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $nomComplet = clean($input['nom_complet'] ?? '');

    if ($nomComplet === '') {
        jsonError(400, 'Le nom complet est obligatoire.');
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('UPDATE users SET nom_complet = :nom WHERE id = :id');
        $stmt->execute([':nom' => $nomComplet, ':id' => getAdminId()]);

        $_SESSION['admin_nom_complet'] = $nomComplet;

        logAudit('update_preferences', 'user', getAdminId(), 'Informations personnelles modifiees');

        jsonSuccess('Preferences mises a jour.');
    } catch (PDOException $e) {
        logError('ERROR', 'Update preferences error: ' . $e->getMessage(), 'api/users.php', 368);
        jsonError(500, 'Erreur interne.');
    }
}
