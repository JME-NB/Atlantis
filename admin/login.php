<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Page de connexion administrateur
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// Si deja connecte, rediriger
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=2">
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <span class="login-logo">A</span>
                <h1>ATLANTIS</h1>
                <p>Espace d'administration</p>
            </div>
            <form id="loginForm" class="login-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <div class="form-group">
                    <label for="identifiant">Identifiant</label>
                    <input type="text" id="identifiant" name="identifiant" placeholder="Votre identifiant" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="Votre mot de passe" required autocomplete="current-password">
                </div>
                <div class="form-feedback" id="loginFeedback" role="alert" hidden></div>
                <button type="submit" class="btn btn-primary btn-block" id="loginBtn">Se connecter</button>
            </form>
        </div>
        <div class="login-footer">
            <a href="<?php echo BASE_URL; ?>/public/">&larr; Retour au site</a>
        </div>
    </div>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=2"></script>
</body>
</html>
