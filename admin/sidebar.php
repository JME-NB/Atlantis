<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Sidebar de navigation admin (filtree par role)
 * ============================================================================
 *
 * Inclus dans chaque page admin pour afficher le menu lateral.
 * Les items sont filtres selon le role de l'utilisateur connecte.
 * Le menu utilisateur (sidebar-user) ouvre un dropdown avec les rubriques
 * secondaires : Parametres, Preferences, Utilisateurs, Audit, Logs.
 * ============================================================================
 */

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$role = getAdminRole();
$superAdmin = isSuperAdmin();
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="sidebar-logo">
            <span class="logo-mark">A</span>
            <span class="logo-text">ATLANTIS</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <!-- Dashboard : tous les roles sauf csm -->
            <?php if ($role !== 'csm'): ?>
            <li>
                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    <span>Dashboard</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Candidatures : admin + gestionnaire (menu deroulant) -->
            <?php
            $subPages = ['candidatures.php', 'enattente.php', 'acceptees.php', 'refusees.php', 'archivees.php'];
            $subOpen  = in_array($currentPage, $subPages, true);
            ?>
            <?php if (hasPermission(['admin', 'gestionnaire'])): ?>
            <li class="sidebar-nav-item has-subnav<?php echo $subOpen ? ' open' : ''; ?>">
                <a href="#" class="sidebar-link<?php echo $subOpen ? ' active' : ''; ?>" data-toggle-subnav aria-expanded="<?php echo $subOpen ? 'true' : 'false'; ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Suivi des candidatures</span>
                    <svg class="subnav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </a>
                <ul class="sidebar-subnav">
                    <li><a href="<?php echo BASE_URL; ?>/admin/candidatures.php" class="<?php echo $currentPage === 'candidatures.php' ? 'active' : ''; ?>">Toutes</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/enattente.php" class="<?php echo $currentPage === 'enattente.php' ? 'active' : ''; ?>">En attente / En cours</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/acceptees.php" class="<?php echo $currentPage === 'acceptees.php' ? 'active' : ''; ?>">Acceptees</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/refusees.php" class="<?php echo $currentPage === 'refusees.php' ? 'active' : ''; ?>">Refusees</a></li>
                    <?php if (hasPermission('admin')): ?>
                    <li><a href="<?php echo BASE_URL; ?>/admin/archivees.php" class="<?php echo $currentPage === 'archivees.php' ? 'active' : ''; ?>">Archivees</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Design : csm + admin (menu deroulant) -->
            <?php
            $designSubPages = ['design.php', 'design_form.php'];
            $designSubOpen  = in_array($currentPage, $designSubPages, true);
            ?>
            <?php if (hasPermission(['csm', 'admin'])): ?>
            <li class="sidebar-nav-item has-subnav<?php echo $designSubOpen ? ' open' : ''; ?>">
                <a href="#" class="sidebar-link<?php echo $designSubOpen ? ' active' : ''; ?>" data-toggle-subnav aria-expanded="<?php echo $designSubOpen ? 'true' : 'false'; ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span>Design</span>
                    <svg class="subnav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </a>
                <ul class="sidebar-subnav">
                    <li><a href="<?php echo BASE_URL; ?>/admin/design.php" class="<?php echo $currentPage === 'design.php' ? 'active' : ''; ?>">Mes pages</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/design_form.php" class="<?php echo $currentPage === 'design_form.php' ? 'active' : ''; ?>">Editer le formulaire</a></li>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <button type="button" class="sidebar-user" id="sidebarUserBtn" aria-expanded="false" aria-haspopup="true">
            <span class="user-name"><?php echo htmlspecialchars(getAdminUsername(), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="user-role"><?php echo htmlspecialchars(ucfirst($role ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <svg class="user-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 6 15 12 9 18"/></svg>
        </button>
        <a href="<?php echo BASE_URL; ?>/admin/logout.php" class="sidebar-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span>Deconnexion</span>
        </a>
    </div>

    <!-- Menu utilisateur (dropdown) -->
    <div class="user-menu" id="userMenu" role="menu" hidden>
        <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="user-menu-item<?php echo $currentPage === 'settings.php' ? ' active' : ''; ?>" role="menuitem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Parametres</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/preferences.php" class="user-menu-item<?php echo $currentPage === 'preferences.php' ? ' active' : ''; ?>" role="menuitem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M20 21v-2a4 4 0 0 0-3-3.85M4 21v-2a4 4 0 0 1 3-3.85"/></svg>
            <span>Preferences</span>
        </a>
        <?php if (hasPermission('admin')): ?>
        <a href="<?php echo BASE_URL; ?>/admin/utilisateurs.php" class="user-menu-item<?php echo $currentPage === 'utilisateurs.php' ? ' active' : ''; ?>" role="menuitem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Utilisateurs</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/audit.php" class="user-menu-item<?php echo $currentPage === 'audit.php' ? ' active' : ''; ?>" role="menuitem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Audit</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/logs.php" class="user-menu-item<?php echo $currentPage === 'logs.php' ? ' active' : ''; ?>" role="menuitem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
            <span>Logs</span>
        </a>
        <?php endif; ?>
    </div>
</aside>

<!-- Overlay mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
