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
        'police_titre', 'police_corps', 'logo_url', 'banniere_url',
        'login_fond', 'login_primaire', 'login_secondaire', 'login_bg_url',
        'login_police', 'login_police_taille', 'login_titre_taille',
        'login_texte_couleur', 'login_carte_fond', 'login_bg_fondu',
        'landing_taille_base', 'landing_taille_hero_titre',
        'landing_taille_titre_section', 'landing_taille_corps',
        'admin_couleur_primaire', 'admin_couleur_primaire_light',
        'admin_couleur_secondaire', 'admin_couleur_secondaire_hover',
        'admin_fond', 'admin_fond_card', 'admin_texte',
        'admin_texte_light', 'admin_texte_muted', 'admin_border',
        'admin_danger', 'admin_police', 'admin_police_echelle',
        'landing_form_fields'
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
        logError('ERROR', 'Update settings error: ' . $e->getMessage(), 'api/settings.php', 117);
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

        // Requete UNIQUE de toutes les rubriques (evite le N+1)
        $rubricStmt = $pdo->query('SELECT * FROM site_rubriques ORDER BY section_id ASC, position ASC');
        $rubriques  = $rubricStmt->fetchAll();

        $rubriquesParSection = [];
        foreach ($rubriques as $rubrique) {
            $rubriquesParSection[(int)$rubrique['section_id']][] = $rubrique;
        }

        foreach ($sections as &$section) {
            $section['rubriques'] = $rubriquesParSection[(int)$section['id']] ?? [];
        }
        unset($section);

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
        $userId = getAdminId();

        $stmt = $pdo->prepare(
            'UPDATE site_sections
             SET titre = :titre, contenu = :contenu, image_url = :image_url,
                 fond_couleur = :fond_couleur, texte_couleur = :texte_couleur,
                 fond_image_url = :fond_image_url, titre_taille = :titre_taille,
                 ordre = :ordre, visible = :visible, updated_by = :user_id, updated_at = NOW()
             WHERE id = :id'
        );

        $delRub = $pdo->prepare('DELETE FROM site_rubriques WHERE section_id = :sid');
        $insRub = $pdo->prepare(
            'INSERT INTO site_rubriques (section_id, position, titre, contenu, icone, image_url, fond_couleur, texte_couleur, visible, updated_by)
             VALUES (:sid, :pos, :titre, :contenu, :icone, :image_url, :fond_couleur, :texte_couleur, :visible, :uid)'
        );

        foreach ($input['sections'] as $section) {
            $id = filter_var($section['id'] ?? null, FILTER_VALIDATE_INT);
            $sectionKey = $section['section_key'] ?? null;

            // Fallback: if id missing/0, resolve by section_key
            if (!$id && $sectionKey) {
                $lookup = $pdo->prepare('SELECT id FROM site_sections WHERE section_key = :key');
                $lookup->execute([':key' => $sectionKey]);
                $found = $lookup->fetch();
                if ($found) $id = (int) $found['id'];
            }
            if (!$id) continue;

            $stmt->execute([
                ':titre'          => $section['titre'] ?? null,
                ':contenu'        => $section['contenu'] ?? null,
                ':image_url'      => $section['image_url'] ?? null,
                ':fond_couleur'   => $section['fond_couleur'] ?? null,
                ':texte_couleur'  => $section['texte_couleur'] ?? null,
                ':fond_image_url' => $section['fond_image_url'] ?? null,
                ':titre_taille'   => $section['titre_taille'] ?? null,
                ':ordre'          => (int)($section['ordre'] ?? 0),
                ':visible'        => (bool)($section['visible'] ?? true),
                ':user_id'        => $userId,
                ':id'             => $id,
            ]);

            // Remplacement complet des rubriques (sauvegarde snapshot fiable)
            $delRub->execute([':sid' => $id]);

            if (isset($section['rubriques']) && is_array($section['rubriques'])) {
                foreach ($section['rubriques'] as $pos => $rubrique) {
                    $insRub->execute([
                        ':sid'          => $id,
                        ':pos'          => $pos + 1,
                        ':titre'        => $rubrique['titre'] ?? null,
                        ':contenu'      => $rubrique['contenu'] ?? null,
                        ':icone'        => $rubrique['icone'] ?? null,
                        ':image_url'    => $rubrique['image_url'] ?? null,
                        ':fond_couleur' => $rubrique['fond_couleur'] ?? null,
                        ':texte_couleur'=> $rubrique['texte_couleur'] ?? null,
                        ':visible'      => (bool)($rubrique['visible'] ?? true),
                        ':uid'          => $userId,
                    ]);
                }
            }
        }

        logAudit('update_site_sections', 'settings', null, 'Sections et rubriques de la landing page mises a jour');

        jsonSuccess('Sections mises a jour avec succes.');
    } catch (PDOException $e) {
        logError('ERROR', 'Update sections error: ' . $e->getMessage(), 'api/settings.php', 237);
        jsonError(500, 'Erreur interne.');
    }
}
