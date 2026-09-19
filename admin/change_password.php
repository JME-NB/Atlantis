<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Changement de mot de passe obligatoire
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
requireAuth();

$csrf = generateCsrfToken();

// Mettre le meme theme que la page de connexion
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
    logError('ERROR', 'Login settings error: ' . $e->getMessage(), 'admin/change_password.php', 34);
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
    <title>Changer le mot de passe - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=<?php echo htmlspecialchars(str_replace(' ', '+', $loginPolice), ENT_QUOTES, 'UTF-8'); ?>:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=14">
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
    <div class="login-veil" style="background: <?php echo $loginVoile; ?>;" aria-hidden="true"></div>
    <div class="login-wrapper">
        <div class="login-notif"><?php echo renderNotificationBellHtml(); ?></div>
        <div class="login-card">
            <div class="login-header">
                <span class="login-logo">A</span>
                <h1>Changement de mot de passe</h1>
                <p>Vous devez changer votre mot de passe avant de continuer.</p>
            </div>
            <form id="changePasswordForm" class="login-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="force" value="1">
                <div class="form-group">
                    <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
                    <input type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" placeholder="8 caracteres min, 1 majuscule, 1 chiffre" required>
                </div>
                <div class="form-group">
                    <label for="confirmation">Confirmer le mot de passe</label>
                    <input type="password" id="confirmation" name="confirmation" placeholder="Confirmez le mot de passe" required>
                </div>
                <div class="form-feedback" id="changeFeedback" role="alert" hidden></div>
                <button type="submit" class="btn btn-primary btn-block" id="changeBtn">Changer le mot de passe</button>
            </form>
        </div>
    </div>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo $csrf; ?>';
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=8"></script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/notifications.js?v=1"></script>
</body>
</html>
