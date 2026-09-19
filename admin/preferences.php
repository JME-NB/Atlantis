<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Preferences personnelles
 * ============================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/admin_theme.php';

$csrf = generateCsrfToken();

// Recuperer les infos actuelles
$pdo = getDB();
$stmt = $pdo->prepare('SELECT identifiant, nom_complet FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => getAdminId()]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preferences - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=14">
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
                <h1>Preferences</h1>
            </div>
            <div class="header-right">
                <?php echo renderNotificationBellHtml(); ?>
            </div>
        </header>

        <div class="page-content">
            <!-- Infos personnelles -->
            <div class="card">
                <div class="card-header"><h2>Informations personnelles</h2></div>
                <div class="card-body">
                    <form id="preferencesForm" novalidate>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Identifiant</label>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars($user['identifiant'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                <small class="form-help">L'identifiant ne peut pas etre modifie.</small>
                            </div>
                            <div class="form-group">
                                <label>Nom complet <span class="req">*</span></label>
                                <input type="text" name="nom_complet" class="form-input" value="<?php echo htmlspecialchars($user['nom_complet'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Changement de mot de passe -->
            <div class="card">
                <div class="card-header"><h2>Changer le mot de passe</h2></div>
                <div class="card-body">
                    <form id="changePasswordForm" novalidate>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Mot de passe actuel <span class="req">*</span></label>
                                <input type="password" name="ancien_mot_de_passe" class="form-input" required>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nouveau mot de passe <span class="req">*</span></label>
                                <input type="password" name="nouveau_mot_de_passe" class="form-input" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label>Confirmer <span class="req">*</span></label>
                                <input type="password" name="confirmation" class="form-input" required>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
                        </div>
                    </form>
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
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=8"></script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/notifications.js?v=1"></script>
</body>
</html>
