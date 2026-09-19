<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Page de connexion administrateur
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// Si deja connecte (ou cookie "se souvenir de moi" valide), rediriger
maybeAutoLogin();
if (isLoggedIn() && !isset($_GET['preview'])) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$csrf = generateCsrfToken();

// Charger le theme de la page de connexion (configurable depuis le menu Design)
$loginSettings = [
    'login_fond'        => '#0a1628',
    'login_primaire'    => '#0a1628',
    'login_secondaire'  => '#00b4d8',
    'login_bg_url'      => '',
    'login_police'      => 'Inter',
    'login_police_taille' => '1.15',
    'login_titre_taille'  => '2.5',
    'login_texte_couleur' => '#1e293b',
    'login_carte_fond'    => '#ffffff',
    'login_bg_fondu'      => '55',
];
try {
    $cleListe = implode(',', array_map(function ($c) { return '"' . $c . '"'; }, array_keys($loginSettings)));
    $stmt = getDB()->query('SELECT cle, valeur FROM site_settings WHERE cle IN (' . $cleListe . ')');
    while ($row = $stmt->fetch()) {
        $loginSettings[$row['cle']] = $row['valeur'];
    }
} catch (PDOException $e) {
    logError('ERROR', 'Login settings error: ' . $e->getMessage(), 'admin/login.php', 40);
}

// --- Apercu d'un design de connexion non publie (?preview=ID&sig=...) ---
$loginPreviewRibbon = null;
if (isset($_GET['preview'], $_GET['sig'])) {
    require_once __DIR__ . '/../includes/preview.php';
    $previewDesign = previewDesign((int) $_GET['preview'], (string) $_GET['sig']);
    if ($previewDesign !== null && $previewDesign['cible'] === 'login') {
        $cfg = $previewDesign['configuration'];
        if (isset($cfg['settings']) && is_array($cfg['settings'])) {
            $loginSettings = array_merge($loginSettings, $cfg['settings']);
        }
        $loginPreviewRibbon = (string) $previewDesign['nom'];
    }
}

if (!function_exists('hexToRgb')) {
    function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '0, 0, 0';
        }
        return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
    }
}

$loginBg = trim((string)($loginSettings['login_fond'] ?? ''));
$loginPrimary = trim((string)($loginSettings['login_primaire'] ?? ''));
$loginSecondary = trim((string)($loginSettings['login_secondaire'] ?? ''));
$loginBgUrl = trim((string)($loginSettings['login_bg_url'] ?? ''));
$loginPolice = trim((string)($loginSettings['login_police'] ?? 'Inter')) ?: 'Inter';
$loginPoliceTaille = trim((string)($loginSettings['login_police_taille'] ?? ''));
$loginTitreTaille = trim((string)($loginSettings['login_titre_taille'] ?? ''));
$loginTexteCouleur = trim((string)($loginSettings['login_texte_couleur'] ?? ''));
$loginCarteFond = trim((string)($loginSettings['login_carte_fond'] ?? ''));
$loginBgFondu = (int)($loginSettings['login_bg_fondu'] ?? 55);
$loginBgFondu = max(0, min(100, $loginBgFondu));
$loginVoile = $loginBg !== '' ? 'rgba(' . hexToRgb($loginBg) . ', ' . ($loginBgFondu / 100) . ')' : 'rgba(10, 22, 40, ' . ($loginBgFondu / 100) . ')';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=<?php echo htmlspecialchars(str_replace(' ', '+', $loginPolice), ENT_QUOTES, 'UTF-8'); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=11">
    <style>
    :root {
        --login-bg: <?php echo $loginBg !== '' ? htmlspecialchars($loginBg, ENT_QUOTES) : '#0a1628'; ?>;
        --login-primary: <?php echo $loginPrimary !== '' ? htmlspecialchars($loginPrimary, ENT_QUOTES) : '#0a1628'; ?>;
        --login-secondary: <?php echo $loginSecondary !== '' ? htmlspecialchars($loginSecondary, ENT_QUOTES) : '#00b4d8'; ?>;
        --login-secondary-rgb: <?php echo hexToRgb($loginSecondary); ?>;
        --login-police: '<?php echo htmlspecialchars($loginPolice, ENT_QUOTES); ?>', sans-serif;
        --login-police-taille: <?php echo $loginPoliceTaille !== '' ? htmlspecialchars($loginPoliceTaille, ENT_QUOTES) : '1.15'; ?>rem;
        --login-titre-taille: <?php echo $loginTitreTaille !== '' ? htmlspecialchars($loginTitreTaille, ENT_QUOTES) : '2.5'; ?>rem;
        --login-texte-couleur: <?php echo $loginTexteCouleur !== '' ? htmlspecialchars($loginTexteCouleur, ENT_QUOTES) : '#1e293b'; ?>;
        --login-carte-fond: <?php echo $loginCarteFond !== '' ? htmlspecialchars($loginCarteFond, ENT_QUOTES) : '#ffffff'; ?>;
        <?php if ($loginBgUrl !== ''): ?>--login-bg-img: url('<?php echo htmlspecialchars($loginBgUrl, ENT_QUOTES, 'UTF-8'); ?>');<?php endif; ?>
    }
    </style>
</head>
<body class="login-page">
    <?php if ($loginPreviewRibbon !== null): ?>
    <div class="preview-ribbon admin-preview-ribbon">
        <strong>Apercu du design « <?php echo htmlspecialchars($loginPreviewRibbon, ENT_QUOTES, 'UTF-8'); ?> »</strong> — non publie.
        <a href="<?php echo BASE_URL; ?>/admin/login.php">&times; Voir la page reelle</a>
    </div>
    <?php endif; ?>
    <div class="login-veil" style="background: <?php echo $loginVoile; ?>;" aria-hidden="true"></div>
    <div class="login-deco login-deco-1" aria-hidden="true"></div>
    <div class="login-deco login-deco-2" aria-hidden="true"></div>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <span class="login-logo">A</span>
                <h1>ATLANTIS</h1>
                <p>Espace d'administration</p>
            </div>
            <form id="loginForm" class="login-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <div class="form-group input-icon">
                    <label for="identifiant">Identifiant</label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="identifiant" name="identifiant" placeholder="Votre identifiant" required autocomplete="username">
                    </div>
                </div>
                <div class="form-group input-icon">
                    <label for="mot_de_passe">Mot de passe</label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="Votre mot de passe" required autocomplete="current-password">
                    </div>
                </div>
                <div class="login-options">
                    <label class="remember" for="rememberMe">
                        <input type="checkbox" name="remember_me" value="1" id="rememberMe">
                        <span>Se souvenir de moi</span>
                    </label>
                </div>
                <div class="form-feedback" id="loginFeedback" role="alert" hidden></div>
                <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
                    Se connecter
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </form>
        </div>
        <div class="login-footer">
            <a href="<?php echo BASE_URL; ?>/public/">&larr; Retour au site</a>
        </div>
    </div>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=8"></script>
</body>
</html>
