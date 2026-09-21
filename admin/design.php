<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Design & themes (CMS)
 * ============================================================================
 * Onglets : Landing / Connexion / Admin
 * Panneau Designs : creer, activer, modifier, dupliquer, supprimer
 * Rubriques : CRUD des cartes de chaque section (landing)
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/admin_theme.php';
require_once __DIR__ . '/../includes/preview.php';
require_once __DIR__ . '/../includes/design_thumb.php';

$readonly = !hasPermission(['csm', 'admin']);

$csrf = generateCsrfToken();

// --- Charger les parametres actuels ---
$pdo = getDB();
$stmt = $pdo->query('SELECT cle, valeur FROM site_settings');
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['cle']] = $row['valeur'];
}

// --- Charger les sections + rubriques ---
$stmt = $pdo->query('SELECT * FROM site_sections ORDER BY ordre ASC');
$sections = $stmt->fetchAll();
$rubStmt = $pdo->prepare('SELECT * FROM site_rubriques WHERE section_id = :id ORDER BY position ASC');
foreach ($sections as &$section) {
    $rubStmt->execute([':id' => $section['id']]);
    $section['rubriques'] = $rubStmt->fetchAll();
}
unset($section);

// --- Design en cours d'edition (?design=ID) ---
$editingDesign = null;
$editingDesignId = 0;
$activeTab = 'landing';
if (isset($_GET['design'])) {
    $designId = (int) $_GET['design'];
    if ($designId > 0 && !$readonly && isset($_GET['tab'])) {
        $tab = preg_replace('/[^a-z]/', '', (string) $_GET['tab']);
        if (in_array($tab, ['landing', 'login', 'admin'], true)) $activeTab = $tab;

        $dStmt = $pdo->prepare('SELECT * FROM designs WHERE id = :id AND deleted_at IS NULL');
        $dStmt->execute([':id' => $designId]);
        $row = $dStmt->fetch();
        if ($row && $row['cible'] === $activeTab) {
            $config = json_decode($row['configuration'], true);
            if (is_array($config)) {
                $editingDesign = $row;
                $editingDesignId = $designId;

                if ($activeTab === 'landing') {
                    if (isset($config['settings']) && is_array($config['settings'])) {
                        $settings = array_merge($settings, $config['settings']);
                    }
                    if (isset($config['sections']) && is_array($config['sections'])) {
                        $sections = $config['sections'];
                    }
                } elseif (isset($config['settings']) && is_array($config['settings'])) {
                    $settings = array_merge($settings, $config['settings']);
                }
            }
        }
    }
}

// --- URL d'apercu du design en cours d'edition (si design chargé) ---
$editingDesignPreviewUrl = '';
if ($editingDesign !== null) {
    $editingDesignPreviewUrl = designPreviewUrl((int) $editingDesign['id'], (string) $editingDesign['cible']);
}

// --- Liste des designs par cible ---
$designs = [];
$stmt = $pdo->query('SELECT * FROM designs WHERE deleted_at IS NULL ORDER BY cible ASC, created_at ASC');
while ($row = $stmt->fetch()) {
    $designs[$row['cible']][] = $row;
}

// --- Constantes d'edition ---
$fontChoices = ['Inter', 'Poppins', 'Montserrat', 'Roboto', 'Open Sans', 'Lato'];

$rubriqueIcons = [
    'ecoute'      => 'Ecoute / Cœur',
    'reactivite'  => 'Reactivite / Horloge',
    'qualite'     => 'Qualite / Bouclier',
    'televente'   => 'Televente / Telephone',
    'prospection' => 'Prospection / Equipe',
    'apres_vente' => 'Apres-vente / Protection',
    'relation'    => 'Relation / Message',
    'telecom'     => 'Telecom / Antenne',
];
$legacyIconNames = [
    'banque'    => 'Banque / Batiment',
    'sante'     => 'Sante / Pulsation',
    'retail'    => 'Retail / Panier',
    'energie'   => 'Energie / Eclair',
    'assurance' => 'Assurance / Parapluie',
];

$rubriqueSections = ['apropos', 'services', 'pourquoi', 'processus', 'secteurs'];

function colorField(string $key, string $current, bool $disabled): string
{
    $val   = htmlspecialchars($current, ENT_QUOTES, 'UTF-8');
    $onoff = $disabled ? ' disabled' : '';
    return '<div class="color-input-group">'
        . '<input type="color" id="' . $key . '" value="' . $val . '"' . $onoff . '>'
        . '<input type="text" class="form-input" value="' . $val . '" data-setting="' . $key . '" placeholder="auto"' . $onoff . '>'
        . '</div>';
}

function sizeSelect(string $key, string $current, array $values, bool $disabled, array $labels = []): string
{
    $html = '<select class="form-select" data-setting="' . $key . '"' . ($disabled ? ' disabled' : '') . '>';
    $html .= '<option value="">CSS (defaut)</option>';
    foreach ($values as $v) {
        $vStr = (string) $v;
        $label = $labels[$vStr] ?? (strpos($vStr, '.') !== false ? $vStr . 'rem' : $vStr . 'px');
        $html .= '<option value="' . $vStr . '"' . ($current === $vStr ? ' selected' : '') . '>' . $label . '</option>';
    }
    $html .= '</select>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design &amp; themes - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=15">
    <?php renderAdminTheme(); ?>
</head>
<body class="admin-body">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-left">
                <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Ouvrir le menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h1>Design &amp; thèmes</h1>
            </div>
            <div class="header-right">
                <?php echo renderNotificationBellHtml(); ?>
                <?php if (!$readonly): ?>
                <?php if ($editingDesignPreviewUrl !== ''): ?>
                <a class="btn btn-outline" id="previewBtn" href="<?php echo $editingDesignPreviewUrl; ?>" target="_blank" rel="noopener">Previsualiser</a>
                <?php else: ?>
                <button class="btn btn-outline" id="previewBtn" disabled title="Ouvrez d'abord un design (Modifier) avant de previsualiser">Previsualiser</button>
                <?php endif; ?>
                <button class="btn btn-primary" id="publishBtn">Publier les changements</button>
                <?php endif; ?>
            </div>
        </header>

        <div class="page-content">

            <?php if ($editingDesign !== null): ?>
            <div class="card">
                <div class="card-body">
                    <p class="info-text">
                        <strong>Edition du design :</strong> <?php echo htmlspecialchars($editingDesign['nom'], ENT_QUOTES, 'UTF-8'); ?>
                        — « Publier les changements » applique au site en live, « Enregistrer le design » met a jour ce design.
                        <a href="design.php?tab=<?php echo $activeTab; ?>">&larr; Revenir a l'edition du site</a>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Designs -->
            <div class="card">
                <div class="card-header"><h2>Designs</h2></div>
                <div class="card-body">
                    <?php if (!$readonly): ?>
                    <div class="design-create">
                        <input type="text" id="designName" class="form-input" placeholder="Nom du nouveau design"
                               value="<?php echo $editingDesign !== null ? htmlspecialchars($editingDesign['nom'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <input type="text" id="designDescription" class="form-input form-input-block" placeholder="Description (optionnelle, affichee dans la galerie)"
                               value="<?php echo $editingDesign !== null ? htmlspecialchars($editingDesign['description'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <div class="design-create-actions">
                            <button type="button" class="btn btn-primary" id="saveDesignBtn">
                                <?php echo $editingDesign !== null ? 'Enregistrer le design' : 'Créer un design'; ?>
                            </button>
                            <?php if ($editingDesign !== null): ?>
                            <button type="button" class="btn btn-outline" id="cancelDesignEditBtn">Annuler</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="info-text" style="margin-bottom:16px">Un design capture la configuration actuellement affichee dans le formulaire pour sa cible (onglet actif). Les designs nommes « Defaut » restaurent le look d'origine.</p>
                    <?php endif; ?>

                    <div class="design-tabs">
                        <button type="button" class="design-tab <?php echo $activeTab === 'landing' ? 'active' : ''; ?>" data-tab="landing">Landing</button>
                        <button type="button" class="design-tab <?php echo $activeTab === 'login' ? 'active' : ''; ?>" data-tab="login">Connexion</button>
                        <button type="button" class="design-tab <?php echo $activeTab === 'admin' ? 'active' : ''; ?>" data-tab="admin">Admin</button>
                    </div>

                    <?php $cibleLabels = ['landing' => 'Landing', 'login' => 'Connexion', 'admin' => 'Admin']; ?>
                    <?php foreach ($cibleLabels as $cible => $label): ?>
                    <div class="designs-list" data-cible="<?php echo $cible; ?>" <?php echo $activeTab === $cible ? '' : 'hidden'; ?>>
                        <div class="design-gallery">
                            <?php foreach (($designs[$cible] ?? []) as $d): ?>
                            <div class="design-card">
                                <div class="design-card-thumb">
                                    <?php
                                    $thumbConfig = json_decode((string)($d['configuration'] ?? '{}'), true);
                                    if (!is_array($thumbConfig)) $thumbConfig = [];
                                    echo designThumb($thumbConfig, $cible);
                                    ?>
                                </div>
                                <div class="design-card-body">
                                    <div class="design-card-title">
                                        <strong><?php echo htmlspecialchars($d['nom'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="badge badge-<?php echo $d['active'] ? 'success' : 'secondary'; ?>"><?php echo $d['active'] ? 'Actif' : 'Inactif'; ?></span>
                                    </div>
                                    <?php if (!empty($d['description'])): ?>
                                    <p class="design-card-desc"><?php echo htmlspecialchars($d['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php else: ?>
                                    <p class="design-card-desc text-muted">Aucune description.</p>
                                    <?php endif; ?>
<div class="design-item-actions">
                                        <?php if (!$readonly): ?>
                                        <button type="button" class="btn btn-sm btn-primary design-activate" data-id="<?php echo $d['id']; ?>">Activer</button>
                                        <a class="btn btn-sm btn-outline" target="_blank" rel="noopener" href="<?php echo designPreviewUrl((int) $d['id'], (string) $d['cible']); ?>">Apercu</a>
                                        <a class="btn btn-sm btn-outline" href="design.php?design=<?php echo $d['id']; ?>&tab=<?php echo $cible; ?>">Modifier</a>
                                        <button type="button" class="btn btn-sm btn-outline design-duplicate" data-id="<?php echo $d['id']; ?>">Dupliquer</button>
                                        <button type="button" class="btn btn-sm btn-danger design-delete" data-id="<?php echo $d['id']; ?>">Supprimer</button>
                                        <?php else: ?>
                                        <span class="text-muted" style="font-size:.85rem">Design « <?php echo $label; ?> »</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($designs[$cible] ?? [])): ?>
                        <p class="info-text">Aucun design pour cette cible.</p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Onglets d'edition -->
            <div class="design-tabs design-tabs-main">
                <button type="button" class="design-tab <?php echo $activeTab === 'landing' ? 'active' : ''; ?>" data-tab="landing">Landing</button>
                <button type="button" class="design-tab <?php echo $activeTab === 'login' ? 'active' : ''; ?>" data-tab="login">Connexion</button>
                <button type="button" class="design-tab <?php echo $activeTab === 'admin' ? 'active' : ''; ?>" data-tab="admin">Admin</button>
            </div>

            <!-- ===================== LANDING ===================== -->
            <div class="design-panel" data-panel="landing" <?php echo $activeTab === 'landing' ? '' : 'hidden'; ?>>

                <div class="card">
                    <div class="card-header"><h2>Apparence generale</h2></div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Couleur primaire</label>
                                <?php echo colorField('couleur_primaire', $settings['couleur_primaire'] ?? '#0a1628', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Couleur secondaire / accent</label>
                                <?php echo colorField('couleur_secondaire', $settings['couleur_secondaire'] ?? '#00b4d8', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Couleur de fond</label>
                                <?php echo colorField('couleur_fond', $settings['couleur_fond'] ?? '#f8fafc', $readonly); ?>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Police des titres</label>
                                <select class="form-select" data-setting="police_titre" <?php echo $readonly ? 'disabled' : ''; ?>>
                                    <?php foreach ($fontChoices as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php echo ($settings['police_titre'] ?? 'Inter') === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Police du corps</label>
                                <select class="form-select" data-setting="police_corps" <?php echo $readonly ? 'disabled' : ''; ?>>
                                    <?php foreach ($fontChoices as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php echo ($settings['police_corps'] ?? 'Inter') === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Taille de base (px)</label>
                                <?php echo sizeSelect('landing_taille_base', $settings['landing_taille_base'] ?? '16', [13, 14, 15, 16, 17, 18, 20], $readonly, ['16' => '16 (defaut)']); ?>
                            </div>
                            <div class="form-group">
                                <label>Taille titre du hero (px)</label>
                                <?php echo sizeSelect('landing_taille_hero_titre', $settings['landing_taille_hero_titre'] ?? '42', [30, 34, 38, 42, 48, 54, 60], $readonly, ['42' => '42 (defaut)']); ?>
                            </div>
                            <div class="form-group">
                                <label>Taille titres de section (px)</label>
                                <?php echo sizeSelect('landing_taille_titre_section', $settings['landing_taille_titre_section'] ?? '54', [36, 42, 48, 54, 60, 66], $readonly, ['54' => '54 (defaut)']); ?>
                            </div>
                            <div class="form-group">
                                <label>Taille du corps de section (px)</label>
                                <?php echo sizeSelect('landing_taille_corps', $settings['landing_taille_corps'] ?? '24', [16, 18, 20, 22, 24, 28], $readonly, ['24' => '24 (defaut)']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2>Logo &amp; banniere</h2></div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Logo (hauteur 38px, PNG/SVG, 2Mo max)</label>
                                <input type="file" class="upload-input" data-upload-type="logo" accept="image/png,image/svg+xml,image/jpeg" <?php echo $readonly ? 'disabled' : ''; ?>>
                                <input type="text" class="form-input" data-setting="logo_url" value="<?php echo htmlspecialchars($settings['logo_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://... (lien direct)" <?php echo $readonly ? 'disabled' : ''; ?>>
                                <?php if (!empty($settings['logo_url'])): ?>
                                    <div class="upload-preview">
                                        <img src="<?php echo htmlspecialchars($settings['logo_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo actuel" class="upload-preview-img">
                                        <?php if (!$readonly): ?><button type="button" class="btn btn-sm btn-danger upload-remove" data-upload-type="logo">Supprimer</button><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>Banniere hero (1920x600 conseille, 4Mo max)</label>
                                <input type="file" class="upload-input" data-upload-type="banniere" accept="image/png,image/jpeg,image/webp" <?php echo $readonly ? 'disabled' : ''; ?>>
                                <input type="text" class="form-input" data-setting="banniere_url" value="<?php echo htmlspecialchars($settings['banniere_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://... (lien direct)" <?php echo $readonly ? 'disabled' : ''; ?>>
                                <?php if (!empty($settings['banniere_url'])): ?>
                                    <div class="upload-preview">
                                        <img src="<?php echo htmlspecialchars($settings['banniere_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Banniere actuelle" class="upload-preview-img">
                                        <?php if (!$readonly): ?><button type="button" class="btn btn-sm btn-danger upload-remove" data-upload-type="banniere">Supprimer</button><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2>Sections de la landing page</h2></div>
                    <div class="card-body">
                        <p class="info-text" style="margin-bottom:16px">Couleur de fond, couleur de texte, taille de titre, image de fond (upload ou lien) et rubriques (cartes) par section. Laisser vide = utiliser le style CSS par defaut.</p>
                        <div id="sectionsList">
                            <?php foreach ($sections as $section): ?>
                            <div class="section-editor" data-id="<?php echo (int)($section['id'] ?? 0); ?>" data-key="<?php echo htmlspecialchars($section['section_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="section-editor-header">
                                    <div class="section-drag-handle" title="Glisser pour reordonner">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/></svg>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" class="section-visible" <?php echo !empty($section['visible']) ? 'checked' : ''; ?> <?php echo $readonly ? 'disabled' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <strong><?php echo htmlspecialchars($section['section_key'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="section-order">Ordre: <?php echo (int)($section['ordre'] ?? 0); ?></span>
                                </div>
                                <div class="section-editor-body">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label>Titre</label>
                                            <input type="text" class="form-input section-titre" value="<?php echo htmlspecialchars($section['titre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                        </div>
                                        <div class="form-group">
                                            <label>Contenu</label>
                                            <textarea class="form-textarea section-contenu" rows="3" <?php echo $readonly ? 'disabled' : ''; ?>><?php echo htmlspecialchars($section['contenu'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label>Fond de la section (couleur)</label>
                                            <div class="color-input-group">
                                                <input type="color" class="sect-fond-color" value="<?php echo htmlspecialchars($section['fond_couleur'] ?? '#000000', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                                <input type="text" class="form-input section-fond" value="<?php echo htmlspecialchars($section['fond_couleur'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="auto" <?php echo $readonly ? 'disabled' : ''; ?>>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Texte de la section (couleur)</label>
                                            <div class="color-input-group">
                                                <input type="color" class="sect-texte-color" value="<?php echo htmlspecialchars($section['texte_couleur'] ?? '#000000', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                                <input type="text" class="form-input section-texte" value="<?php echo htmlspecialchars($section['texte_couleur'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="auto" <?php echo $readonly ? 'disabled' : ''; ?>>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Taille du titre (px)</label>
                                            <select class="form-select section-taille" <?php echo $readonly ? 'disabled' : ''; ?>>
                                                <option value="">CSS (defaut)</option>
                                                <?php foreach ([28, 32, 36, 40, 44, 48, 54, 60, 66] as $tz): ?>
                                                <option value="<?php echo $tz; ?>" <?php echo ($section['titre_taille'] ?? '') === (string)$tz ? 'selected' : ''; ?>><?php echo $tz; ?>px</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Image principale de la section (lien)</label>
                                            <input type="text" class="form-input section-url" value="<?php echo htmlspecialchars($section['image_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://..." <?php echo $readonly ? 'disabled' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label>Image de fond de la section (upload ou lien)</label>
                                            <input type="file" class="upload-input" data-upload-type="section_bg" data-section-id="<?php echo (int)($section['id'] ?? 0); ?>" accept="image/png,image/jpeg,image/webp,image/gif" <?php echo $readonly ? 'disabled' : ''; ?>>
                                            <input type="text" class="form-input section-bg-url" value="<?php echo htmlspecialchars($section['fond_image_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://... (lien direct)" <?php echo $readonly ? 'disabled' : ''; ?>>
                                            <?php if (!empty($section['image_url'])): ?>
                                                <div class="upload-preview">
                                                    <img src="<?php echo htmlspecialchars($section['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Image de la section" class="upload-preview-img">
                                                    <?php if (!$readonly): ?><button type="button" class="btn btn-sm btn-danger upload-remove" data-upload-type="section" data-section-id="<?php echo (int)($section['id'] ?? 0); ?>">Supprimer</button><?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (in_array($section['section_key'], $rubriqueSections, true)): ?>
                                    <div class="rubriques-block">
                                        <div class="rubriques-block-header">
                                            <h4>Rubriques de la section</h4>
                                            <?php if (!$readonly): ?>
                                            <button type="button" class="btn btn-sm btn-outline rubrique-add">+ Ajouter une rubrique</button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="rubriques-list">
                                            <?php foreach (($section['rubriques'] ?? []) as $rub): ?>
                                            <div class="rubrique-row">
                                                <div class="rubrique-controls">
                                                    <button type="button" class="btn btn-sm btn-outline rub-up" title="Monter" <?php echo $readonly ? 'disabled' : ''; ?>>&#9650;</button>
                                                    <button type="button" class="btn btn-sm btn-outline rub-down" title="Descendre" <?php echo $readonly ? 'disabled' : ''; ?>>&#9660;</button>
                                                </div>
                                                <div class="rubrique-fields">
                                                    <div class="form-grid">
                                                        <div class="form-group">
                                                            <label>Icone (choix parmi 8)</label>
                                                            <select class="form-select rub-icone" <?php echo $readonly ? 'disabled' : ''; ?>>
                                                                <option value="">-- (nombre / aucune) --</option>
                                                                <?php if (!empty($rub['icone']) && isset($legacyIconNames[$rub['icone']])): ?>
                                                                <option value="<?php echo htmlspecialchars($rub['icone'], ENT_QUOTES, 'UTF-8'); ?>" selected disabled><?php echo htmlspecialchars($legacyIconNames[$rub['icone']], ENT_QUOTES, 'UTF-8'); ?></option>
                                                                <?php endif; ?>
                                                                <?php foreach ($rubriqueIcons as $k => $label): ?>
                                                                <option value="<?php echo $k; ?>" <?php echo ($rub['icone'] ?? '') === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Visible</label>
                                                            <label class="toggle-switch">
                                                                <input type="checkbox" class="rub-visible" <?php echo !empty($rub['visible']) ? 'checked' : ''; ?> <?php echo $readonly ? 'disabled' : ''; ?>>
                                                                <span class="toggle-slider"></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="form-grid">
                                                        <div class="form-group">
                                                            <label>Titre</label>
                                                            <input type="text" class="form-input rub-titre" value="<?php echo htmlspecialchars($rub['titre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Image (lien ou upload)</label>
                                                            <div class="upload-url-row">
                                                                <input type="text" class="form-input rub-url" value="<?php echo htmlspecialchars($rub['image_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://..." <?php echo $readonly ? 'disabled' : ''; ?>>
                                                                <input type="file" class="upload-input rub-upload" data-upload-type="rubrique" accept="image/png,image/jpeg,image/webp,image/gif" <?php echo $readonly ? 'disabled' : ''; ?>>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Contenu</label>
                                                        <textarea class="form-textarea rub-contenu" rows="2" <?php echo $readonly ? 'disabled' : ''; ?>><?php echo htmlspecialchars($rub['contenu'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    </div>
                                                    <div class="form-grid">
                                                        <div class="form-group">
                                                            <label>Fond de la carte (couleur)</label>
                                                            <div class="color-input-group">
                                                                <input type="color" class="rub-fond-color" value="<?php echo htmlspecialchars($rub['fond_couleur'] ?? '#000000', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                                                <input type="text" class="form-input rub-fond" value="<?php echo htmlspecialchars($rub['fond_couleur'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="auto" <?php echo $readonly ? 'disabled' : ''; ?>>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>Texte de la carte (couleur)</label>
                                                            <div class="color-input-group">
                                                                <input type="color" class="rub-texte-color" value="<?php echo htmlspecialchars($rub['texte_couleur'] ?? '#000000', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                                                <input type="text" class="form-input rub-texte" value="<?php echo htmlspecialchars($rub['texte_couleur'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="auto" <?php echo $readonly ? 'disabled' : ''; ?>>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if (!$readonly): ?>
                                                <button type="button" class="btn btn-sm btn-danger rub-delete">Supprimer</button>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <input type="hidden" class="section-ordre" value="<?php echo (int)($section['ordre'] ?? 0); ?>">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================== CONNEXION ===================== -->
            <div class="design-panel" data-panel="login" <?php echo $activeTab === 'login' ? '' : 'hidden'; ?>>
                <div class="card">
                    <div class="card-header"><h2>Apparence de la page de connexion</h2></div>
                    <div class="card-body">
                        <p class="info-text" style="margin-bottom:16px">Fond (couleur ou image avec voile), couleurs, polices et tailles de la page de connexion de l'espace admin.</p>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Couleur de fond</label>
                                <?php echo colorField('login_fond', $settings['login_fond'] ?? '#0a1628', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Couleur primaire (logo &amp; titre)</label>
                                <?php echo colorField('login_primaire', $settings['login_primaire'] ?? '#0a1628', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Couleur accent (bouton, focus, halos)</label>
                                <?php echo colorField('login_secondaire', $settings['login_secondaire'] ?? '#00b4d8', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Couleur du texte du formulaire</label>
                                <?php echo colorField('login_texte_couleur', $settings['login_texte_couleur'] ?? '#1e293b', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Couleur de la carte du formulaire</label>
                                <?php echo colorField('login_carte_fond', $settings['login_carte_fond'] ?? '#ffffff', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Police</label>
                                <select class="form-select" data-setting="login_police" <?php echo $readonly ? 'disabled' : ''; ?>>
                                    <?php foreach ($fontChoices as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php echo ($settings['login_police'] ?? 'Inter') === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Taille de base du formulaire</label>
                                <?php echo sizeSelect('login_police_taille', $settings['login_police_taille'] ?? '1.15', [0.95, 1, 1.05, 1.1, 1.15, 1.25, 1.35], $readonly, ['1.15' => '1.15rem (defaut)']); ?>
                            </div>
                            <div class="form-group">
                                <label>Taille du titre ATLANTIS</label>
                                <?php echo sizeSelect('login_titre_taille', $settings['login_titre_taille'] ?? '2.5', [1.6, 1.9, 2.2, 2.5, 2.8, 3.1], $readonly, ['2.5' => '2.5rem (defaut)']); ?>
                            </div>
                            <div class="form-group">
                                <label>Voile de couleur sur l'image de fond (%)</label>
                                <input type="range" class="form-range" id="login_bg_fondu" min="0" max="100" step="5" value="<?php echo (int)($settings['login_bg_fondu'] ?? 55); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                <input type="hidden" data-setting="login_bg_fondu" id="login_bg_fondu_hidden">
                                <span class="info-text" id="loginFonduValue"><?php echo (int)($settings['login_bg_fondu'] ?? 55); ?>%</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Image de fond (upload ou lien, 4Mo max)</label>
                            <input type="file" class="upload-input" data-upload-type="login_bg" accept="image/png,image/jpeg,image/webp" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <input type="text" class="form-input" data-setting="login_bg_url" value="<?php echo htmlspecialchars($settings['login_bg_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://... (lien direct)" <?php echo $readonly ? 'disabled' : ''; ?>>
                            <?php if (!empty($settings['login_bg_url'])): ?>
                                <div class="upload-preview">
                                    <img src="<?php echo htmlspecialchars($settings['login_bg_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Fond de connexion actuel" class="upload-preview-img">
                                    <?php if (!$readonly): ?><button type="button" class="btn btn-sm btn-danger upload-remove" data-upload-type="login_bg">Supprimer</button><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ====================== ADMIN ====================== -->
            <div class="design-panel" data-panel="admin" <?php echo $activeTab === 'admin' ? '' : 'hidden'; ?>>
                <div class="card">
                    <div class="card-header"><h2>Apparence de l'espace admin</h2></div>
                    <div class="card-body">
                        <p class="info-text" style="margin-bottom:16px">Couleurs, police et echelle de taille de l'espace d'administration (aucune image).</p>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Couleur primaire (sidebar)</label>
                                <?php echo colorField('admin_couleur_primaire', $settings['admin_couleur_primaire'] ?? '#0a1628', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Primaire claire (hover sidebar)</label>
                                <?php echo colorField('admin_couleur_primaire_light', $settings['admin_couleur_primaire_light'] ?? '#1a2d4a', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Couleur secondaire (boutons, liens)</label>
                                <?php echo colorField('admin_couleur_secondaire', $settings['admin_couleur_secondaire'] ?? '#00b4d8', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Secondaire au survol</label>
                                <?php echo colorField('admin_couleur_secondaire_hover', $settings['admin_couleur_secondaire_hover'] ?? '#0096b7', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Fond de page</label>
                                <?php echo colorField('admin_fond', $settings['admin_fond'] ?? '#f1f5f9', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Fond des cartes</label>
                                <?php echo colorField('admin_fond_card', $settings['admin_fond_card'] ?? '#ffffff', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Texte principal</label>
                                <?php echo colorField('admin_texte', $settings['admin_texte'] ?? '#1e293b', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Texte secondaire</label>
                                <?php echo colorField('admin_texte_light', $settings['admin_texte_light'] ?? '#64748b', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Texte attenue</label>
                                <?php echo colorField('admin_texte_muted', $settings['admin_texte_muted'] ?? '#94a3b8', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Bordures</label>
                                <?php echo colorField('admin_border', $settings['admin_border'] ?? '#e2e8f0', $readonly); ?>
                            </div>
                            <div class="form-group">
                                <label>Couleur danger (suppression)</label>
                                <?php echo colorField('admin_danger', $settings['admin_danger'] ?? '#ef4444', $readonly); ?>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Police</label>
                                <select class="form-select" data-setting="admin_police" <?php echo $readonly ? 'disabled' : ''; ?>>
                                    <?php foreach ($fontChoices as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php echo ($settings['admin_police'] ?? 'Inter') === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Echelle de taille (14 a 18 px)</label>
                                <select class="form-select" data-setting="admin_police_echelle" <?php echo $readonly ? 'disabled' : ''; ?>>
                                    <?php foreach ([14, 15, 16, 17, 18] as $e): ?>
                                    <option value="<?php echo $e; ?>" <?php echo (int)($settings['admin_police_echelle'] ?? 16) === $e ? 'selected' : ''; ?>><?php echo $e; ?>px<?php echo $e === 16 ? ' (defaut)' : ''; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <footer class="app-footer">
            <span>&copy; <?php echo date('Y'); ?> ATLANTIS v2 &mdash; Tous droits reserves.</span>
        </footer>
    </main>

    <div id="toastContainer" class="toast-container"></div>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo $csrf; ?>';
    const READONLY = <?php echo $readonly ? 'true' : 'false'; ?>;
    const ACTIVE_TAB = '<?php echo $activeTab; ?>';
    const EDITING_DESIGN_ID = <?php echo (int) $editingDesignId; ?>;
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=8"></script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/notifications.js?v=1"></script>
</body>
</html>
