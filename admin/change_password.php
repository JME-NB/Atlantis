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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=5">
</head>
<body class="login-page">
    <div class="login-wrapper">
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
                    <input type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" placeholder="Minimum 6 caracteres" required>
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
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=4"></script>
</body>
</html>
