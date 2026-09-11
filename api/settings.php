<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API : Parametres du site (CMS)
 * ============================================================================
 *
 * Endpoints :
 *   GET  ?action=get      (public)
 *   POST ?action=update    (csm)
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'get':
        handleGet();
        break;
    case 'update':
        handleUpdate();
        break;
    case 'get_sections':
        handleGetSections();
        break;
    case 'update_sections':
        handleUpdateSections();
        break;
    default:
        jsonError(400, 'Action inconnue.');
}

// ---------------------------------------------------------------------------
// GET (public)
// ---------------------------------------------------------------------------
function handleGet(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError(405, 'Methode non autorisee.');

    try {
        $pdo  = getDB();
        $stmt = $pdo->query('SELECT cle, valeur FROM site_settings');
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['cle']] = $row['valeur'];
        }

        jsonSuccess('Parametres recuperes.', ['settings' => $settings]);
    } catch (PDOException $e) {
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// UPDATE (csm)
// ---------------------------------------------------------------------------
function handleUpdate(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission(['csm', 'admin'])) jsonError(403, 'Permissions insuffisantes.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    if (!$input) {
        jsonError(400, 'Donnees invalides.');
    }

    $allowedKeys = [
        'couleur_primaire', 'couleur_secondaire', 'couleur_fond',
        'police_titre', 'police_corps', 'logo_url', 'banniere_url'
    ];

    try {
        $pdo = getDB();

        foreach ($input as $cle => $valeur) {
            if (!in_array($cle, $allowedKeys, true)) continue;

            $stmt = $pdo->prepare(
                'INSERT INTO site_settings (cle, valeur, updated_by, updated_at)
                 VALUES (:cle, :valeur, :user_id, NOW())
                 ON DUPLICATE KEY UPDATE valeur = :valeur2, updated_by = :user_id2, updated_at = NOW()'
            );
            $stmt->execute([
                ':cle'      => $cle,
                ':valeur'   => $valeur,
                ':user_id'  => getAdminId(),
                ':valeur2'  => $valeur,
                ':user_id2' => getAdminId(),
            ]);
        }

        logAudit('update_site_settings', 'settings', null, 'Parametres du site mis a jour');

        jsonSuccess('Parametres mis a jour avec succes.');
    } catch (PDOException $e) {
        error_log('Update settings error: ' . $e->getMessage());
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// GET SECTIONS (public)
// ---------------------------------------------------------------------------
function handleGetSections(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError(405, 'Methode non autorisee.');

    try {
        $pdo  = getDB();
        $stmt = $pdo->query('SELECT * FROM site_sections ORDER BY ordre ASC');
        $sections = $stmt->fetchAll();

        jsonSuccess('Sections recuperees.', ['sections' => $sections]);
    } catch (PDOException $e) {
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// UPDATE SECTIONS (csm)
// ---------------------------------------------------------------------------
function handleUpdateSections(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission(['csm', 'admin'])) jsonError(403, 'Permissions insuffisantes.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    if (!$input || !isset($input['sections']) || !is_array($input['sections'])) {
        jsonError(400, 'Donnees invalides.');
    }

    try {
        $pdo = getDB();

        foreach ($input['sections'] as $section) {
            $id = filter_var($section['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) continue;

            $stmt = $pdo->prepare(
                'UPDATE site_sections
                 SET titre = :titre, contenu = :contenu, image_url = :image_url,
                     ordre = :ordre, visible = :visible, updated_by = :user_id, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                ':titre'     => $section['titre'] ?? null,
                ':contenu'   => $section['contenu'] ?? null,
                ':image_url' => $section['image_url'] ?? null,
                ':ordre'     => (int)($section['ordre'] ?? 0),
                ':visible'   => (bool)($section['visible'] ?? true),
                ':user_id'   => getAdminId(),
                ':id'        => $id,
            ]);
        }

        logAudit('update_site_sections', 'settings', null, 'Sections de la landing page mises a jour');

        jsonSuccess('Sections mises a jour avec succes.');
    } catch (PDOException $e) {
        error_log('Update sections error: ' . $e->getMessage());
        jsonError(500, 'Erreur interne.');
    }
}
