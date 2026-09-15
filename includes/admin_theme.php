<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Theme de l'espace d'administration
 * Surcharge les variables CSS de admin.css depuis les reglages du site
 * (menu Design -> onglet Admin). A utiliser uniquement sur les pages
 * possedant la classe .admin-body.
 * ============================================================================
 */

if (!function_exists('getAdminThemeSettings')) {
    /**
     * Charge les reglages du theme admin depuis site_settings.
     * Peut etre surcharge pour un apercu (voir setAdminThemeOverrides).
     * @return array
     */
    function getAdminThemeSettings(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $defaults = [
            'admin_couleur_primaire'        => '#0a1628',
            'admin_couleur_primaire_light'  => '#1a2d4a',
            'admin_couleur_secondaire'      => '#00b4d8',
            'admin_couleur_secondaire_hover'=> '#0096b7',
            'admin_fond'                    => '#f1f5f9',
            'admin_fond_card'               => '#ffffff',
            'admin_texte'                   => '#1e293b',
            'admin_texte_light'             => '#64748b',
            'admin_texte_muted'             => '#94a3b8',
            'admin_border'                  => '#e2e8f0',
            'admin_danger'                  => '#ef4444',
            'admin_police'                  => 'Inter',
            'admin_police_echelle'          => '16',
        ];
        $cache = $defaults;
        try {
            $cles = array_keys($defaults);
            $placeholders = implode(',', array_fill(0, count($cles), '?'));
            $stmt = getDB()->prepare('SELECT cle, valeur FROM site_settings WHERE cle IN (' . $placeholders . ')');
            $stmt->execute($cles);
            while ($row = $stmt->fetch()) {
                if (isset($cache[$row['cle']])) {
                    $cache[$row['cle']] = $row['valeur'];
                }
            }
        } catch (PDOException $e) {
            logError('ERROR', 'Admin theme settings error: ' . $e->getMessage(), 'includes/admin_theme.php', 50);
        }
        // Apercu d'un design admin (?preview=ID&sig=...) : surcharge en memoire
        $overrides = $GLOBALS['__admin_theme_overrides'] ?? [];
        if (is_array($overrides)) {
            foreach ($overrides as $cle => $valeur) {
                if (isset($cache[$cle])) {
                    $cache[$cle] = $valeur;
                }
            }
        }
        return $cache;
    }
}

if (!function_exists('setAdminThemeOverrides')) {
    /**
     * Force des valeurs du theme admin pour cet appel (apercu d'un design).
     * A appeler avant renderAdminTheme() sur la page concernee.
     * @param array $overrides
     */
    function setAdminThemeOverrides(array $overrides): void
    {
        $GLOBALS['__admin_theme_overrides'] = $overrides;
    }
}

if (!function_exists('renderAdminTheme')) {
    /**
     * Affiche le lien Google Fonts et le <style> de surcharge du theme admin.
     * A placer dans le <head> des pages .admin-body, apres admin.css.
     * Les reglages vides sont ignores (valeur CSS par defaut conservee).
     */
    function renderAdminTheme(): void
    {
        $s = getAdminThemeSettings();

        $police = trim((string)($s['admin_police'] ?? 'Inter')) ?: 'Inter';
        $echelle = trim((string)($s['admin_police_echelle'] ?? ''));

        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link href="https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', htmlspecialchars($police, ENT_QUOTES)) . ':wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n";
        echo '<style>' . "\n";
        echo 'body { font-family: \'' . htmlspecialchars($police, ENT_QUOTES) . '\', -apple-system, sans-serif; }' . "\n";
        if ($echelle !== '') {
            $px = max(10, min(24, (float)$echelle));
            echo 'html { font-size: ' . $px . 'px; }' . "\n";
        }
        echo ':root {' . "\n";
        $map = [
            'admin_couleur_primaire'         => '--primary',
            'admin_couleur_primaire_light'   => '--primary-light',
            'admin_couleur_secondaire'       => '--secondary',
            'admin_couleur_secondaire_hover' => '--secondary-hover',
            'admin_fond'                     => '--bg',
            'admin_fond_card'                => '--bg-card',
            'admin_texte'                    => '--text',
            'admin_texte_light'              => '--text-light',
            'admin_texte_muted'              => '--text-muted',
            'admin_border'                   => '--border',
            'admin_danger'                   => '--danger',
        ];
        foreach ($map as $cle => $var) {
            $valeur = trim((string)($s[$cle] ?? ''));
            if ($valeur === '') {
                continue;
            }
            echo '    ' . $var . ': ' . htmlspecialchars($valeur, ENT_QUOTES) . ';' . "\n";
        }
        echo '}' . "\n";
        echo '</style>' . "\n";
    }
}