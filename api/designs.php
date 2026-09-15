<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API : Designs (CMS)
 * ============================================================================
 *
 * Endpoints :
 *   GET  ?action=list        - Liste des designs (connecte)
 *   GET  ?action=get&id=X    - Contenu d'un design (connecte)
 *   POST ?action=save        - Creer / mettre a jour un design (csm)
 *   POST ?action=activate&id=X - Appliquer le design au site (csm)
 *   POST ?action=duplicate&id=X - Dupliquer un design (csm)
 *   POST ?action=delete&id=X    - Supprimer un design (csm)
 *
 * Le design est un instantane ("snapshot") d'une seule cible :
 *   - landing : {settings:[...], sections:[...]} (avec rubriques)
 *   - login   : {settings:[...]}
 *   - admin   : {settings:[...]}
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        handleList();
        break;
    case 'get':
        handleGet();
        break;
    case 'save':
        handleSave();
        break;
    case 'activate':
        handleActivate();
        break;
    case 'duplicate':
        handleDuplicate();
        break;
    case 'delete':
        handleDelete();
        break;
    default:
        jsonError(400, 'Action inconnue.');
}

// ---------------------------------------------------------------------------
// LIST (connecte)
// ---------------------------------------------------------------------------
function handleList(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');

    try {
        $pdo  = getDB();
        $stmt = $pdo->query('SELECT id, nom, description, cible, active, created_at, updated_at FROM designs WHERE deleted_at IS NULL ORDER BY cible ASC, created_at ASC');
        $designs = [];
        while ($row = $stmt->fetch()) {
            $designs[$row['cible']][] = $row;
        }
        jsonSuccess('Designs recuperes.', ['designs' => $designs]);
    } catch (PDOException $e) {
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// GET (connecte)
// ---------------------------------------------------------------------------
function handleGet(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');

    $id = (int) (filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0);
    if ($id <= 0) jsonError(400, 'Identifiant invalide.');

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM designs WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) jsonError(404, 'Design introuvable.');

        $configuration = json_decode($row['configuration'], true);
        jsonSuccess('Design recupere.', [
            'design' => $row,
            'configuration' => is_array($configuration) ? $configuration : null,
        ]);
    } catch (PDOException $e) {
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// Requisitions communes aux actions d'ecriture
// ---------------------------------------------------------------------------
function requireDesignWriteAccess(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission(['csm', 'admin'])) jsonError(403, 'Permissions insuffisantes.');

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }
}

// ---------------------------------------------------------------------------
// SAVE (csm) - creer ou mettre a jour un design
// ---------------------------------------------------------------------------
function handleSave(): void
{
    requireDesignWriteAccess();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    if (!$input) jsonError(400, 'Donnees invalides.');

    $id    = (int)($input['id'] ?? 0);
    $nom   = trim((string)($input['nom'] ?? ''));
    $desc  = trim((string)($input['description'] ?? ''));
    $cible = (string)($input['cible'] ?? '');
    $config = $input['configuration'] ?? null;

    if ($nom === '') jsonError(400, 'Le nom du design est requis.');
    if (mb_strlen($desc) > 250) jsonError(400, 'La description est trop longue (250 caracteres max).');
    if (!in_array($cible, ['landing', 'login', 'admin'], true)) jsonError(400, 'Cible invalide.');
    if (!is_array($config) || !isset($config['settings']) || !is_array($config['settings'])) {
        jsonError(400, 'Configuration invalide.');
    }
    if ($cible === 'landing' && (!isset($config['sections']) || !is_array($config['sections']))) {
        jsonError(400, 'Sections manquantes pour la landing page.');
    }

    // Ne conserver que les parametres connus (selon la cible) pour un snapshot propre.
    $allowedByCible = [
        'landing' => [
            'couleur_primaire', 'couleur_secondaire', 'couleur_fond',
            'police_titre', 'police_corps', 'logo_url', 'banniere_url',
            'landing_taille_base', 'landing_taille_hero_titre',
            'landing_taille_titre_section', 'landing_taille_corps',
        ],
        'login' => [
            'login_fond', 'login_primaire', 'login_secondaire', 'login_bg_url',
            'login_police', 'login_police_taille', 'login_titre_taille',
            'login_texte_couleur', 'login_carte_fond', 'login_bg_fondu',
        ],
        'admin' => [
            'admin_couleur_primaire', 'admin_couleur_primaire_light',
            'admin_couleur_secondaire', 'admin_couleur_secondaire_hover',
            'admin_fond', 'admin_fond_card', 'admin_texte',
            'admin_texte_light', 'admin_texte_muted', 'admin_border',
            'admin_danger', 'admin_police', 'admin_police_echelle',
        ],
    ];

    $cleanSettings = [];
    foreach ($config['settings'] as $cle => $valeur) {
        if (in_array($cle, $allowedByCible[$cible], true)) {
            $cleanSettings[$cle] = is_scalar($valeur) ? (string)$valeur : '';
        }
    }

    $cleanSections = [];
    if ($cible === 'landing' && !empty($config['sections'])) {
        $sectionKeys = ['section_key', 'titre', 'contenu', 'image_url', 'fond_couleur', 'texte_couleur', 'fond_image_url', 'titre_taille', 'ordre', 'visible'];
        $rubRefs = ['titre', 'contenu', 'icone', 'image_url', 'fond_couleur', 'texte_couleur', 'visible'];
        foreach ($config['sections'] as $section) {
            $clean = [];
            foreach ($sectionKeys as $k) {
                $clean[$k] = $section[$k] ?? null;
            }
            $clean['rubriques'] = [];
            if (isset($section['rubriques']) && is_array($section['rubriques'])) {
                foreach ($section['rubriques'] as $rub) {
                    $cleanRub = [];
                    foreach ($rubRefs as $k) {
                        $cleanRub[$k] = $rub[$k] ?? null;
                    }
                    $clean['rubriques'][] = $cleanRub;
                }
            }
            $cleanSections[] = $clean;
        }
    }

    $publicConfig = ['settings' => $cleanSettings];
    if ($cible === 'landing') {
        $publicConfig['sections'] = $cleanSections;
    }
    $json = json_encode($publicConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) jsonError(500, 'Configuration illisible.');

    try {
        $pdo = getDB();
        $userId = getAdminId();

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE designs
                 SET nom = :nom, description = :description, cible = :cible, configuration = :configuration,
                     updated_by = :user_id, updated_at = NOW()
                 WHERE id = :id AND deleted_at IS NULL'
            );
            $stmt->execute([
                ':nom' => $nom,
                ':description' => $desc !== '' ? $desc : null,
                ':cible' => $cible,
                ':configuration' => $json,
                ':user_id' => $userId,
                ':id' => $id,
            ]);
            if ($stmt->rowCount() === 0) {
                // Id inexistant ou deja supprime : on cree un nouveau design
                $id = 0;
            }
        }

        if ($id === 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO designs (nom, description, cible, configuration, active, updated_by)
                 VALUES (:nom, :description, :cible, :configuration, 0, :user_id)'
            );
            $stmt->execute([
                ':nom' => $nom,
                ':description' => $desc !== '' ? $desc : null,
                ':cible' => $cible,
                ':configuration' => $json,
                ':user_id' => $userId,
            ]);
            $id = (int) $pdo->lastInsertId();
        }

        logAudit('save_design', 'designs', $id, 'Design "' . $nom . '" enregistre (cible : ' . $cible . ')');

        $sectionKeys = [];
        if ($cible === 'landing') {
            foreach ($cleanSections as $sec) {
                if (!empty($sec['section_key'])) $sectionKeys[] = (string) $sec['section_key'];
            }
        }
        $designMsg = 'Design "' . $nom . '" enregistre par ' . getAdminDisplayName();
        if ($sectionKeys !== []) {
            $designMsg .= ' - sections : ' . implode(', ', $sectionKeys);
        }
        notify('design_modifie', $designMsg, ['admin', 'csm'], 'design', $id);

        jsonSuccess('Design enregistre avec succes.', ['id' => $id]);
    } catch (PDOException $e) {
        logError('ERROR', 'Design save error: ' . $e->getMessage(), 'api/designs.php', 256);
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// ACTIVATE (csm) - appliquer le design a la configuration live du site
// ---------------------------------------------------------------------------
function handleActivate(): void
{
    requireDesignWriteAccess();

    $id = (int) (filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0);
    if ($id <= 0) jsonError(400, 'Identifiant invalide.');

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT * FROM designs WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $design = $stmt->fetch();
        if (!$design) jsonError(404, 'Design introuvable.');

        $config = json_decode($design['configuration'], true);
        if (!is_array($config) || !isset($config['settings']) || !is_array($config['settings'])) {
            jsonError(500, 'Configuration du design corrompue.');
        }

        $cible = $design['cible'];
        $pdo->beginTransaction();

        // 1) Restauration des parametres
        $upsert = $pdo->prepare(
            'INSERT INTO site_settings (cle, valeur, updated_by, updated_at)
             VALUES (:cle, :valeur, :user_id, NOW())
             ON DUPLICATE KEY UPDATE valeur = :valeur2, updated_by = :user_id2, updated_at = NOW()'
        );
        foreach ($config['settings'] as $cle => $valeur) {
            $upsert->execute([
                ':cle' => $cle,
                ':valeur' => $valeur,
                ':user_id' => getAdminId(),
                ':valeur2' => $valeur,
                ':user_id2' => getAdminId(),
            ]);
        }

        // 2) Restauration des sections + rubriques (landing uniquement)
        if ($cible === 'landing' && isset($config['sections']) && is_array($config['sections'])) {
            $lookup = $pdo->prepare('SELECT id FROM site_sections WHERE section_key = :key');
            $upSec = $pdo->prepare(
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

            foreach ($config['sections'] as $section) {
                $sectionKey = (string)($section['section_key'] ?? '');
                if ($sectionKey === '') continue;

                $lookup->execute([':key' => $sectionKey]);
                $found = $lookup->fetch();
                if (!$found) continue;
                $sectId = (int) $found['id'];

                $upSec->execute([
                    ':titre'          => $section['titre'] ?? null,
                    ':contenu'        => $section['contenu'] ?? null,
                    ':image_url'      => $section['image_url'] ?? null,
                    ':fond_couleur'   => $section['fond_couleur'] ?? null,
                    ':texte_couleur'  => $section['texte_couleur'] ?? null,
                    ':fond_image_url' => $section['fond_image_url'] ?? null,
                    ':titre_taille'   => $section['titre_taille'] ?? null,
                    ':ordre'          => (int)($section['ordre'] ?? 0),
                    ':visible'        => (bool)($section['visible'] ?? true),
                    ':user_id'        => getAdminId(),
                    ':id'             => $sectId,
                ]);

                $delRub->execute([':sid' => $sectId]);

                if (isset($section['rubriques']) && is_array($section['rubriques'])) {
                    foreach ($section['rubriques'] as $pos => $rubrique) {
                        $insRub->execute([
                            ':sid'          => $sectId,
                            ':pos'          => $pos + 1,
                            ':titre'        => $rubrique['titre'] ?? null,
                            ':contenu'      => $rubrique['contenu'] ?? null,
                            ':icone'        => $rubrique['icone'] ?? null,
                            ':image_url'    => $rubrique['image_url'] ?? null,
                            ':fond_couleur' => $rubrique['fond_couleur'] ?? null,
                            ':texte_couleur'=> $rubrique['texte_couleur'] ?? null,
                            ':visible'      => (bool)($rubrique['visible'] ?? true),
                            ':uid'          => getAdminId(),
                        ]);
                    }
                }
            }
        }

        // 3) Basculer le flag actif sur cette cible
        $deactivate = $pdo->prepare('UPDATE designs SET active = 0 WHERE cible = :cible AND deleted_at IS NULL');
        $deactivate->execute([':cible' => $cible]);
        $activate = $pdo->prepare('UPDATE designs SET active = 1, updated_by = :user_id, updated_at = NOW() WHERE id = :id');
        $activate->execute([':user_id' => getAdminId(), ':id' => $id]);

        $pdo->commit();

        logAudit('activate_design', 'designs', $id, 'Design "' . $design['nom'] . '" active sur la cible ' . $cible);

        $sectionKeys = [];
        if ($cible === 'landing' && isset($config['sections']) && is_array($config['sections'])) {
            foreach ($config['sections'] as $sec) {
                if (!empty($sec['section_key'])) $sectionKeys[] = (string) $sec['section_key'];
            }
        }
        $designMsg = 'Design "' . $design['nom'] . '" active par ' . getAdminDisplayName();
        if ($sectionKeys !== []) {
            $designMsg .= ' - sections : ' . implode(', ', $sectionKeys);
        }
        notify('design_modifie', $designMsg, ['admin', 'csm'], 'design', $id);

        jsonSuccess('Design active avec succes.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logError('ERROR', 'Design activate error: ' . $e->getMessage(), 'api/designs.php', 389);
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// DUPLICATE (csm)
// ---------------------------------------------------------------------------
function handleDuplicate(): void
{
    requireDesignWriteAccess();

    $id = (int) (filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0);
    if ($id <= 0) jsonError(400, 'Identifiant invalide.');

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT nom, description, cible, configuration FROM designs WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $design = $stmt->fetch();
        if (!$design) jsonError(404, 'Design introuvable.');

        $copie = $design['nom'] . ' - Copie';

        $ins = $pdo->prepare(
            'INSERT INTO designs (nom, description, cible, configuration, active, updated_by)
             VALUES (:nom, :description, :cible, :configuration, 0, :user_id)'
        );
        $ins->execute([
            ':nom' => $copie,
            ':description' => $design['description'],
            ':cible' => $design['cible'],
            ':configuration' => $design['configuration'],
            ':user_id' => getAdminId(),
        ]);
        $newId = (int) $pdo->lastInsertId();

        logAudit('duplicate_design', 'designs', $newId, 'Design "' . $design['nom'] . '" duplique');

        $dupConfig = json_decode($design['configuration'], true);
        $sectionKeys = [];
        if ($design['cible'] === 'landing' && is_array($dupConfig) && isset($dupConfig['sections']) && is_array($dupConfig['sections'])) {
            foreach ($dupConfig['sections'] as $sec) {
                if (!empty($sec['section_key'])) $sectionKeys[] = (string) $sec['section_key'];
            }
        }
        $designMsg = 'Design "' . $design['nom'] . '" duplique par ' . getAdminDisplayName();
        if ($sectionKeys !== []) {
            $designMsg .= ' - sections : ' . implode(', ', $sectionKeys);
        }
        notify('design_modifie', $designMsg, ['admin', 'csm'], 'design', $newId);

        jsonSuccess('Design duplique avec succes.', ['id' => $newId]);
    } catch (PDOException $e) {
        logError('ERROR', 'Design duplicate error: ' . $e->getMessage(), 'api/designs.php', 443);
        jsonError(500, 'Erreur interne.');
    }
}

// ---------------------------------------------------------------------------
// DELETE (csm) - suppression douce
// ---------------------------------------------------------------------------
function handleDelete(): void
{
    requireDesignWriteAccess();

    $id = (int) (filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0);
    if ($id <= 0) jsonError(400, 'Identifiant invalide.');

    try {
        $pdo = getDB();
        $stmtI = $pdo->prepare('SELECT nom FROM designs WHERE id = :id AND deleted_at IS NULL');
        $stmtI->execute([':id' => $id]);
        $design = $stmtI->fetch();

        if (!$design) jsonError(404, 'Design introuvable.');

        $stmt = $pdo->prepare(
            'UPDATE designs SET active = 0, deleted_by = :user_id, deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':user_id' => getAdminId(), ':id' => $id]);

        logAudit('delete_design', 'designs', $id, 'Design supprime : ' . ($design['nom'] ?? '#' . $id));

        notify(
            'design_modifie',
            'Design "' . ($design['nom'] ?? '#' . $id) . '" supprime par ' . getAdminDisplayName(),
            ['admin', 'csm'],
            'design',
            $id
        );

        jsonSuccess('Design supprime avec succes.');
    } catch (PDOException $e) {
        logError('ERROR', 'Design delete error: ' . $e->getMessage(), 'api/designs.php', 481);
        jsonError(500, 'Erreur interne.');
    }
}