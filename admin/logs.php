<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Journal des erreurs
 * ============================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/admin_theme.php';
requireRole('admin');

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs - ATLANTIS Admin</title>
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
                <h1>Journal des erreurs</h1>
            </div>
            <div class="header-right">
                <?php echo renderNotificationBellHtml(); ?>
            </div>
        </header>

        <div class="page-content">
            <div class="filters-bar">
                <div class="filter-group">
                    <input type="text" id="logsSearch" class="form-input" placeholder="Rechercher dans les logs...">
                </div>
                <div class="filter-group">
                    <select id="logsNiveauFilter" class="form-select">
                        <option value="">Tous les niveaux</option>
                        <option value="ERROR">Erreur</option>
                        <option value="WARNING">Avertissement</option>
                        <option value="INFO">Information</option>
                    </select>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="logsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Niveau</th>
                                <th>Message</th>
                                <th>Fichier:Ligne</th>
                                <th>URL</th>
                                <th>Utilisateur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="logsBody">
                            <tr><td colspan="7" class="text-center">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="logsPagination"></div>
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
