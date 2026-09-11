<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Design de la landing page (CMS)
 * ============================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';

// CSM et admin peuvent y acceder (admin en lecture seule)
$readonly = !hasPermission('csm');

$csrf = generateCsrfToken();

// Charger les parametres actuels
$pdo = getDB();
$stmt = $pdo->query('SELECT cle, valeur FROM site_settings');
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['cle']] = $row['valeur'];
}

// Charger les sections
$stmt = $pdo->query('SELECT * FROM site_sections ORDER BY ordre ASC');
$sections = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=2">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-left">
                <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Ouvrir le menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h1>Design de la landing page</h1>
            </div>
            <?php if (!$readonly): ?>
            <div class="header-right">
                <button class="btn btn-outline" id="previewBtn">Previsualiser</button>
                <button class="btn btn-primary" id="publishBtn">Publier les changements</button>
            </div>
            <?php endif; ?>
        </header>

        <div class="page-content">
            <!-- Parametres visuels -->
            <div class="card">
                <div class="card-header"><h2>Apparence generale</h2></div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Couleur primaire</label>
                            <div class="color-input-group">
                                <input type="color" id="couleur_primaire" value="<?php echo htmlspecialchars($settings['couleur_primaire'] ?? '#0a1628', ENT_QUOTES); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars($settings['couleur_primaire'] ?? '#0a1628', ENT_QUOTES); ?>" data-setting="couleur_primaire" <?php echo $readonly ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Couleur secondaire / accent</label>
                            <div class="color-input-group">
                                <input type="color" id="couleur_secondaire" value="<?php echo htmlspecialchars($settings['couleur_secondaire'] ?? '#00b4d8', ENT_QUOTES); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars($settings['couleur_secondaire'] ?? '#00b4d8', ENT_QUOTES); ?>" data-setting="couleur_secondaire" <?php echo $readonly ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Couleur de fond</label>
                            <div class="color-input-group">
                                <input type="color" id="couleur_fond" value="<?php echo htmlspecialchars($settings['couleur_fond'] ?? '#f8fafc', ENT_QUOTES); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars($settings['couleur_fond'] ?? '#f8fafc', ENT_QUOTES); ?>" data-setting="couleur_fond" <?php echo $readonly ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Police des titres</label>
                            <select class="form-select" data-setting="police_titre" <?php echo $readonly ? 'disabled' : ''; ?>>
                                <?php foreach (['Inter', 'Poppins', 'Montserrat', 'Roboto', 'Open Sans', 'Lato'] as $p): ?>
                                <option value="<?php echo $p; ?>" <?php echo ($settings['police_titre'] ?? 'Inter') === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Police du corps</label>
                            <select class="form-select" data-setting="police_corps" <?php echo $readonly ? 'disabled' : ''; ?>>
                                <?php foreach (['Inter', 'Poppins', 'Montserrat', 'Roboto', 'Open Sans', 'Lato'] as $p): ?>
                                <option value="<?php echo $p; ?>" <?php echo ($settings['police_corps'] ?? 'Inter') === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sections -->
            <div class="card">
                <div class="card-header"><h2>Sections de la landing page</h2></div>
                <div class="card-body">
                    <div id="sectionsList">
                        <?php foreach ($sections as $section): ?>
                        <div class="section-editor" data-id="<?php echo $section['id']; ?>">
                            <div class="section-editor-header">
                                <div class="section-drag-handle" title="Glisser pour reordonner">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/></svg>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" class="section-visible" <?php echo $section['visible'] ? 'checked' : ''; ?> <?php echo $readonly ? 'disabled' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <strong><?php echo htmlspecialchars($section['section_key'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="section-order">Ordre: <?php echo $section['ordre']; ?></span>
                            </div>
                            <div class="section-editor-body">
                                <div class="form-group">
                                    <label>Titre</label>
                                    <input type="text" class="form-input section-titre" value="<?php echo htmlspecialchars($section['titre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $readonly ? 'disabled' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label>Contenu</label>
                                    <textarea class="form-textarea section-contenu" rows="3" <?php echo $readonly ? 'disabled' : ''; ?>><?php echo htmlspecialchars($section['contenu'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                                <input type="hidden" class="section-ordre" value="<?php echo $section['ordre']; ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="toastContainer" class="toast-container"></div>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo $csrf; ?>';
    const READONLY = <?php echo $readonly ? 'true' : 'false'; ?>;
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=2"></script>
</body>
</html>
