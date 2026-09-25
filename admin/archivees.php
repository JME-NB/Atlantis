<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Candidatures archivees
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
    <title>Candidatures archivees - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=19">
    <?php renderAdminFavicon(); ?>
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
                <h1>Candidatures archivees</h1>
            </div>
            <div class="header-right">
                <?php echo renderNotificationBellHtml(); ?>
            </div>
        </header>

        <div class="page-content">
            <div class="filters-bar">
                <div class="filter-group">
                    <input type="text" id="searchInput" class="form-input" placeholder="Rechercher...">
                </div>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="applicationsTable">
                        <thead>
                            <tr>
                                <th class="th-sortable" data-sort="id">ID<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="nom">Nom<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="prenom">Prenom<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="telephone">Telephone<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="entreprise">Entreprise<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="type">Type<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="statut">Statut<span class="sort-indicator"></span></th>
                                <th>Dossier</th>
                                <th class="th-sortable" data-sort="created_at">Date<span class="sort-indicator"></span></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="applicationsBody"><tr><td colspan="10" class="text-center">Chargement...</td></tr></tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>

        <footer class="app-footer">
            <span>&copy; <?php echo date('Y'); ?> ATLANTIS v2 &mdash; Tous droits reserves.</span>
        </footer>
    </main>

    <div class="modal-overlay" id="detailModal" hidden>
        <div class="modal">
            <div class="modal-header"><h2>Demande #<span id="modalId"></span></h2><button class="modal-close" id="closeModal">&times;</button></div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer" id="modalFooter"></div>
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo $csrf; ?>';
    const FILTER_STATUT = 'archive';
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=14"></script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/notifications.js?v=1"></script>
</body>
</html>
