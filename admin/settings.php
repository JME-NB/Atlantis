<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Parametres globaux du site
 * ============================================================================
 * Reglages globaux : identite (logo, banniere), couleurs, polices.
 * Distinct de la page Design (presets/templates/mise en page).
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/admin_theme.php';

requireRole(['admin', 'gestionnaire']);

$csrf = generateCsrfToken();

// --- Charger les parametres actuels ---
$pdo = getDB();
$stmt = $pdo->query('SELECT cle, valeur FROM site_settings');
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['cle']] = $row['valeur'];
}

$fontChoices = ['Inter', 'Poppins', 'Montserrat', 'Roboto', 'Open Sans', 'Lato'];

function colorField(string $key, string $current): string
{
    $val = htmlspecialchars($current, ENT_QUOTES, 'UTF-8');
    return '<div class="color-input-group">'
        . '<input type="color" id="' . $key . '" value="' . $val . '">'
        . '<input type="text" class="form-input" value="' . $val . '" data-setting="' . $key . '" placeholder="auto">'
        . '</div>';
}

function fontSelect(string $key, string $current, array $choices): string
{
    $val = htmlspecialchars($current, ENT_QUOTES, 'UTF-8');
    $html = '<select class="form-select" data-setting="' . $key . '">';
    $html .= '<option value="">CSS (defaut)</option>';
    foreach ($choices as $f) {
        $html .= '<option value="' . htmlspecialchars($f, ENT_QUOTES, 'UTF-8') . '"' . ($val === $f ? ' selected' : '') . '>' . htmlspecialchars($f, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select>';
    return $html;
}

function sizeSelect(string $key, string $current, array $values, array $labels = []): string
{
    $val = htmlspecialchars($current, ENT_QUOTES, 'UTF-8');
    $html = '<select class="form-select" data-setting="' . $key . '">';
    $html .= '<option value="">CSS (defaut)</option>';
    foreach ($values as $v) {
        $vStr = (string) $v;
        $label = $labels[$vStr] ?? (strpos($vStr, '.') !== false ? $vStr . 'rem' : $vStr . 'px');
        $html .= '<option value="' . $vStr . '"' . ($val === $vStr ? ' selected' : '') . '>' . $label . '</option>';
    }
    $html .= '</select>';
    return $html;
}

function uploadRow(string $type, string $label, string $currentUrl): string
{
    $url = htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8');
    $html = '<div class="form-group">'
        . '<label>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>'
        . '<div class="upload-url-row">'
        . '<input type="text" class="form-input upload-url-input" value="' . $url . '" data-setting="' . $type . '_url" placeholder="URL de l\'image">'
        . '<input type="file" class="upload-input" data-upload-type="' . $type . '" accept="image/png,image/jpeg,image/webp,image/gif">'
        . '</div>'
        . '<p class="info-text">Importez une image ou renseignez une URL directe. L\'upload est applique automatiquement.</p>'
        . '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parametres - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=10">
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
                <h1>Parametres du site</h1>
            </div>
            <div class="header-right">
                <?php echo renderNotificationBellHtml(); ?>
                <button class="btn btn-primary" id="saveSettingsBtn">Publier les changements</button>
            </div>
        </header>

        <div class="page-content">

            <div class="card">
                <div class="card-header"><h2>Identite &amp; Landing</h2></div>
                <div class="card-body">
                    <?php echo uploadRow('logo', 'Logo', $settings['logo_url'] ?? ''); ?>
                    <?php echo uploadRow('banniere', 'Banniere', $settings['banniere_url'] ?? ''); ?>

                    <h3 class="section-title">Couleurs (landing)</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Couleur primaire</label>
                            <?php echo colorField('couleur_primaire', $settings['couleur_primaire'] ?? '#0b4f6c'); ?>
                        </div>
                        <div class="form-group">
                            <label>Couleur secondaire</label>
                            <?php echo colorField('couleur_secondaire', $settings['couleur_secondaire'] ?? '#00b4d8'); ?>
                        </div>
                        <div class="form-group">
                            <label>Couleur de fond</label>
                            <?php echo colorField('couleur_fond', $settings['couleur_fond'] ?? '#ffffff'); ?>
                        </div>
                    </div>

                    <h3 class="section-title">Typographie (landing)</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Police des titres</label>
                            <?php echo fontSelect('police_titre', $settings['police_titre'] ?? '', $fontChoices); ?>
                        </div>
                        <div class="form-group">
                            <label>Police du corps</label>
                            <?php echo fontSelect('police_corps', $settings['police_corps'] ?? '', $fontChoices); ?>
                        </div>
                    </div>

                    <h3 class="section-title">Tailles (landing)</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Taille de base</label>
                            <?php echo sizeSelect('landing_taille_base', $settings['landing_taille_base'] ?? '', [16, 17, 18, 19, 20]); ?>
                        </div>
                        <div class="form-group">
                            <label>Taille hero-titre</label>
                            <?php echo sizeSelect('landing_taille_hero_titre', $settings['landing_taille_hero_titre'] ?? '', [2.2, 2.5, 2.8, 3.0, 3.4, 3.8, 4.2]); ?>
                        </div>
                        <div class="form-group">
                            <label>Taille titre de section</label>
                            <?php echo sizeSelect('landing_taille_titre_section', $settings['landing_taille_titre_section'] ?? '', [1.4, 1.6, 1.8, 2.0, 2.2]); ?>
                        </div>
                        <div class="form-group">
                            <label>Taille du corps</label>
                            <?php echo sizeSelect('landing_taille_corps', $settings['landing_taille_corps'] ?? '', [0.95, 1.0, 1.05, 1.1, 1.15]); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Page de connexion</h2></div>
                <div class="card-body">
                    <h3 class="section-title">Couleurs</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fond</label>
                            <?php echo colorField('login_fond', $settings['login_fond'] ?? '#0b4f6c'); ?>
                        </div>
                        <div class="form-group">
                            <label>Primaire</label>
                            <?php echo colorField('login_primaire', $settings['login_primaire'] ?? '#00b4d8'); ?>
                        </div>
                        <div class="form-group">
                            <label>Secondaire</label>
                            <?php echo colorField('login_secondaire', $settings['login_secondaire'] ?? '#ffffff'); ?>
                        </div>
                        <div class="form-group">
                            <label>Couleur du texte</label>
                            <?php echo colorField('login_texte_couleur', $settings['login_texte_couleur'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Couleur de la carte</label>
                            <?php echo colorField('login_carte_fond', $settings['login_carte_fond'] ?? ''); ?>
                        </div>
                    </div>

                    <h3 class="section-title">Image de fond</h3>
                    <?php echo uploadRow('login_bg', 'Image de fond de connexion', $settings['login_bg_url'] ?? ''); ?>

                    <h3 class="section-title">Typographie</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Police</label>
                            <?php echo fontSelect('login_police', $settings['login_police'] ?? '', $fontChoices); ?>
                        </div>
                        <div class="form-group">
                            <label>Taille police</label>
                            <?php echo sizeSelect('login_police_taille', $settings['login_police_taille'] ?? '', [0.95, 1.0, 1.05, 1.1]); ?>
                        </div>
                        <div class="form-group">
                            <label>Taille du titre</label>
                            <?php echo sizeSelect('login_titre_taille', $settings['login_titre_taille'] ?? '', [1.6, 1.8, 2.0, 2.2, 2.4]); ?>
                        </div>
                        <div class="form-group">
                            <label>Intensite du fondu de fond</label>
                            <?php echo sizeSelect('login_bg_fondu', $settings['login_bg_fondu'] ?? '', ['0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Interface admin</h2></div>
                <div class="card-body">
                    <h3 class="section-title">Couleurs</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Primaire</label>
                            <?php echo colorField('admin_couleur_primaire', $settings['admin_couleur_primaire'] ?? '#0b4f6c'); ?>
                        </div>
                        <div class="form-group">
                            <label>Primaire (clair)</label>
                            <?php echo colorField('admin_couleur_primaire_light', $settings['admin_couleur_primaire_light'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Secondaire</label>
                            <?php echo colorField('admin_couleur_secondaire', $settings['admin_couleur_secondaire'] ?? '#00b4d8'); ?>
                        </div>
                        <div class="form-group">
                            <label>Secondaire (hover)</label>
                            <?php echo colorField('admin_couleur_secondaire_hover', $settings['admin_couleur_secondaire_hover'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Fond de page</label>
                            <?php echo colorField('admin_fond', $settings['admin_fond'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Fond des cartes</label>
                            <?php echo colorField('admin_fond_card', $settings['admin_fond_card'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Texte</label>
                            <?php echo colorField('admin_texte', $settings['admin_texte'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Texte clair</label>
                            <?php echo colorField('admin_texte_light', $settings['admin_texte_light'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Texte mute</label>
                            <?php echo colorField('admin_texte_muted', $settings['admin_texte_muted'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Bordures</label>
                            <?php echo colorField('admin_border', $settings['admin_border'] ?? ''); ?>
                        </div>
                        <div class="form-group">
                            <label>Danger</label>
                            <?php echo colorField('admin_danger', $settings['admin_danger'] ?? ''); ?>
                        </div>
                    </div>

                    <h3 class="section-title">Typographie</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Police</label>
                            <?php echo fontSelect('admin_police', $settings['admin_police'] ?? '', $fontChoices); ?>
                        </div>
                        <div class="form-group">
                            <label>Echelle de police</label>
                            <?php echo sizeSelect('admin_police_echelle', $settings['admin_police_echelle'] ?? '', [0.8, 0.85, 0.9, 0.95, 1.0]); ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        var BASE_URL = '<?php echo BASE_URL; ?>';
        var CSRF_TOKEN = '<?php echo $csrf; ?>';
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js"></script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/notifications.js"></script>
</body>
</html>