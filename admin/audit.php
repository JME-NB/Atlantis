<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Journal d'audit
 * ============================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
requireRole('admin');

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=6">
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
        </header>

        <div class="page-content">
            <div class="filters-bar">
                <div class="filter-group">
                    <input type="text" id="auditSearch" class="form-input" placeholder="Rechercher dans l'audit...">
                </div>
                <div class="filter-group">
                    <select id="auditActionFilter" class="form-select">
                        <option value="">Toutes les actions</option>
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
                    </select>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="auditTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Utilisateur</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody id="auditBody">
                            <tr><td colspan="6" class="text-center">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="auditPagination"></div>
            </div>
        </div>
    </main>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo $csrf; ?>';
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=4"></script>
</body>
</html>
