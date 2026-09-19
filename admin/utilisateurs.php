<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Gestion des utilisateurs
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
    <title>Gestion des utilisateurs - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=13">
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
                <h1>Gestion des utilisateurs</h1>
            </div>
            <div class="header-right">
                <?php echo renderNotificationBellHtml(); ?>
                <button class="btn btn-primary" id="createUserBtn">Creer un compte</button>
            </div>
        </header>

        <div class="page-content">
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Identifiant</th>
                                <th>Nom complet</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Statut</th>
                                <th>Telephone</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="usersBody">
                            <tr><td colspan="8" class="text-center">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal creation -->
    <div class="modal-overlay" id="createModal" hidden>
        <div class="modal">
            <div class="modal-header">
                <h2>Creer un compte</h2>
                <button class="modal-close" id="closeCreateModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createUserForm" novalidate>
                    <div class="form-group">
                        <label>Identifiant <span class="req">*</span></label>
                        <input type="text" name="identifiant" class="form-input" placeholder="ex: jean.dupont" required>
                        <small class="field-error"></small>
                    </div>
                    <div class="form-group">
                        <label>Nom complet <span class="req">*</span></label>
                        <input type="text" name="nom_complet" class="form-input" placeholder="Jean Dupont" required>
                        <small class="field-error"></small>
                    </div>
                    <div class="form-group">
                        <label>Role <span class="req">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="gestionnaire">Gestionnaire</option>
                            <option value="csm">CSM</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <p class="info-text">Le mot de passe par defaut sera <strong>1234</strong>. L'utilisateur devra le changer a sa premiere connexion.</p>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelCreateBtn">Annuler</button>
                <button class="btn btn-primary" id="confirmCreateBtn">Creer le compte</button>
            </div>
        </div>
    </div>

    <!-- Modal confirmation role -->
    <div class="modal-overlay" id="roleModal" hidden>
        <div class="modal modal-sm">
            <div class="modal-header">
                <h2>Modifier le role</h2>
                <button class="modal-close" id="closeRoleModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Modifier le role de <strong id="roleUserName"></strong> :</p>
                <select id="newRoleSelect" class="form-select">
                    <option value="gestionnaire">Gestionnaire</option>
                    <option value="csm">CSM</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelRoleBtn">Annuler</button>
                <button class="btn btn-primary" id="confirmRoleBtn">Confirmer</button>
            </div>
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo $csrf; ?>';
    const IS_SUPER_ADMIN = <?php echo isSuperAdmin() ? 'true' : 'false'; ?>;
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=8"></script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/notifications.js?v=1"></script>
</body>
</html>
