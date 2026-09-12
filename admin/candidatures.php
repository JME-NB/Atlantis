<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Suivi des candidatures (toutes)
 * ============================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['admin', 'gestionnaire']);

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidatures - ATLANTIS Admin</title>
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
                <h1>Suivi des candidatures</h1>
            </div>
        </header>

        <div class="page-content">
            <!-- Filtres -->
            <div class="filters-bar">
                <div class="filter-group">
                    <input type="text" id="searchInput" class="form-input" placeholder="Rechercher (nom, tel, email, entreprise)...">
                </div>
                <div class="filter-group">
                    <select id="typeFilter" class="form-select">
                        <option value="">Tous les types</option>
                        <option value="partenariat">Partenariat</option>
                        <option value="recrutement">Recrutement</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select id="statutFilter" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="en_attente">En attente</option>
                        <option value="en_cours">En cours</option>
                        <option value="valide">Valide</option>
                        <option value="refuse">Refuse</option>
                        <option value="archive">Archive</option>
                    </select>
                </div>
            </div>

            <!-- Tableau -->
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="applicationsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Prenom</th>
                                <th>Telephone</th>
                                <th>Entreprise</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Dossier</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="applicationsBody">
                            <tr><td colspan="9" class="text-center">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </main>

    <!-- Modal detail -->
    <div class="modal-overlay" id="detailModal" hidden>
        <div class="modal">
            <div class="modal-header">
                <h2>Demande #<span id="modalId"></span></h2>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer" id="modalFooter"></div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toastContainer" class="toast-container"></div>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo $csrf; ?>';
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=4"></script>
</body>
</html>
