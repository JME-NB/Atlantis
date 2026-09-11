<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Dashboard administrateur
 * ============================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();

// Statistiques
$stats = [];

// Total demandes
$stmt = $pdo->query('SELECT COUNT(*) as total FROM applications');
$stats['total'] = (int) $stmt->fetch()['total'];

// Par type
$stmt = $pdo->query("SELECT type, COUNT(*) as cnt FROM applications GROUP BY type");
$stats['by_type'] = [];
while ($row = $stmt->fetch()) {
    $stats['by_type'][$row['type']] = (int) $row['cnt'];
}

// Par statut
$stmt = $pdo->query("SELECT statut, COUNT(*) as cnt FROM applications GROUP BY statut");
$stats['by_statut'] = [];
while ($row = $stmt->fetch()) {
    $stats['by_statut'][$row['statut']] = (int) $row['cnt'];
}

// Dernieres demandes
$stmt = $pdo->query('SELECT * FROM applications ORDER BY created_at DESC LIMIT 5');
$recent = $stmt->fetchAll();

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=2">
</head>
<body class="admin-body">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-left">
                <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Ouvrir le menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h1>Dashboard</h1>
            </div>
            <div class="header-right">
                <span class="header-date"><?php echo date('d/m/Y'); ?></span>
            </div>
        </header>

        <div class="page-content">
            <!-- Stats cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-total">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $stats['total']; ?></span>
                        <span class="stat-label">Total demandes</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-recrutement">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $stats['by_type']['recrutement'] ?? 0; ?></span>
                        <span class="stat-label">Recrutements</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-partenariat">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $stats['by_type']['partenariat'] ?? 0; ?></span>
                        <span class="stat-label">Partenariats</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-attente">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $stats['by_statut']['en_attente'] ?? 0; ?></span>
                        <span class="stat-label">En attente</span>
                    </div>
                </div>
            </div>

            <!-- Details statuts -->
            <div class="stats-row">
                <div class="stat-mini"><span class="badge badge-info"><?php echo $stats['by_statut']['en_cours'] ?? 0; ?></span> En cours</div>
                <div class="stat-mini"><span class="badge badge-success"><?php echo $stats['by_statut']['valide'] ?? 0; ?></span> Validees</div>
                <div class="stat-mini"><span class="badge badge-danger"><?php echo $stats['by_statut']['refuse'] ?? 0; ?></span> Refusees</div>
                <div class="stat-mini"><span class="badge badge-secondary"><?php echo $stats['by_statut']['archive'] ?? 0; ?></span> Archivees</div>
            </div>

            <!-- Dernieres demandes -->
            <div class="card">
                <div class="card-header">
                    <h2>Dernieres demandes</h2>
                    <a href="<?php echo BASE_URL; ?>/admin/candidatures.php" class="btn btn-sm btn-outline">Voir tout</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent)): ?>
                            <tr><td colspan="5" class="text-center">Aucune demande pour le moment.</td></tr>
                            <?php else: ?>
                            <?php foreach ($recent as $app): ?>
                            <tr>
                                <td>#<?php echo $app['id']; ?></td>
                                <td><?php echo htmlspecialchars($app['nom'] . ' ' . ($app['prenom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge <?php echo $app['type'] === 'partenariat' ? 'badge-partenariat' : 'badge-recrutement'; ?>"><?php echo typeLabel($app['type']); ?></span></td>
                                <td><span class="badge <?php echo statutClass($app['statut']); ?>"><?php echo statutLabel($app['statut']); ?></span></td>
                                <td><?php echo formatDate($app['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>const BASE_URL = '<?php echo BASE_URL; ?>';</script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=2"></script>
</body>
</html>
