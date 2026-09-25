<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Journal d'audit
 * ============================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/admin_theme.php';
requireRole('admin');

$csrf = generateCsrfToken();

$auditUsers = [];
$auditRoles = [];
try {
    $pdo       = getDB();
    $auditUsers = $pdo->query(
        'SELECT DISTINCT user_id, identifiant_snapshot FROM audit_log
          WHERE user_id IS NOT NULL ORDER BY identifiant_snapshot'
    )->fetchAll();
    $auditRoles = $pdo->query(
        'SELECT DISTINCT role_snapshot FROM audit_log
          WHERE role_snapshot IS NOT NULL AND role_snapshot <> "" ORDER BY role_snapshot'
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $auditUsers = [];
    $auditRoles = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit - ATLANTIS Admin</title>
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
                <h1>Journal d'audit</h1>
            </div>
            <div class="header-right">
                <?php echo renderNotificationBellHtml(); ?>
            </div>
        </header>

        <div class="page-content">
            <div class="filters-bar audit-filters">
                <div class="filter-group">
                    <label class="filter-label" for="auditDateDeb">Date du</label>
                    <input type="date" id="auditDateDeb" class="form-input">
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="auditDateFin">Au</label>
                    <input type="date" id="auditDateFin" class="form-input">
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="auditUserFilter">Utilisateur</label>
                    <select id="auditUserFilter" class="form-select">
                        <option value="">Tous les utilisateurs</option>
                        <?php foreach ($auditUsers as $u) : ?>
                        <option value="<?php echo (int) $u['user_id']; ?>"><?php echo htmlspecialchars($u['identifiant_snapshot']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="auditRoleFilter">R&ocirc;le</label>
                    <select id="auditRoleFilter" class="form-select">
                        <option value="">Tous les r&ocirc;les</option>
                        <?php foreach ($auditRoles as $r) : ?>
                        <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="auditActionFilter">Action</label>
                    <select id="auditActionFilter" class="form-select">
                        <option value="">Tous les details</option>
                        <option value="login_success">Connexion reussie</option>
                        <option value="login_failed">Connexion echouee</option>
                        <option value="logout">Deconnexion</option>
                        <option value="create_application">Creation demande</option>
                        <option value="update_application_status">Modification statut</option>
                        <option value="delete_application">Suppression demande</option>
                        <option value="create_user">Creation compte</option>
                        <option value="update_user_role">Modification role</option>
                        <option value="reset_password">Reinitialisation mdp</option>
                        <option value="delete_user">Suppression compte</option>
                        <option value="change_password">Changement mdp</option>
                        <option value="update_preferences">Modification preferences</option>
                        <option value="update_site_settings">Modification design</option>
                        <option value="permanent_delete">Suppression definitive</option>
                        <option value="restore_application">Restauration demande</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="auditSearch">D&eacute;tails</label>
                    <input type="text" id="auditSearch" class="form-input" placeholder="Rechercher...">
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="auditTable">
                        <thead>
                            <tr>
                                <th class="th-sortable" data-sort="created_at">Date<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="identifiant_snapshot">Utilisateur<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="role_snapshot">Role<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="action">Action<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="details">Details<span class="sort-indicator"></span></th>
                                <th class="th-sortable" data-sort="adresse_ip">IP<span class="sort-indicator"></span></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="auditBody">
                            <tr><td colspan="7" class="text-center">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="auditPagination"></div>
            </div>
        </div>

        <footer class="app-footer">
            <span>&copy; <?php echo date('Y'); ?> ATLANTIS v2 &mdash; Tous droits reserves.</span>
        </footer>
    </main>

    <div class="modal-overlay" id="auditDetailModal" hidden>
        <div class="modal modal-lg">
            <div class="modal-header"><h2>Details de l'entree d'audit</h2><button class="modal-close" id="closeAuditModal">&times;</button></div>
            <div class="modal-body" id="auditDetailBody"></div>
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo $csrf; ?>';
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=14"></script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/notifications.js?v=2"></script>
</body>
</html>
