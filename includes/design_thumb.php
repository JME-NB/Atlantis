<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Miniatures SVG de designs
 * ============================================================================
 *
 * Genere une vignette stylisee (SVG inline) pour representer un design dans
 * la galerie, a partir des couleurs/polices sauvegardees dans sa
 * configuration (cible : landing / login / admin).
 *
 * Aucun navigateur headless requis : rendu 100% deterministe en PHP.
 * ============================================================================
 */

/**
 * Retourne une valeur de couleur hex safe (ou le fallback) depuis un reglage.
 */
function thumbColor(array $config, string $key, string $fallback): string
{
    $v = trim((string)($config[$key] ?? ''));
    if (preg_match('/^#?[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $v)) {
        return ltrim($v, '#');
    }
    return ltrim($fallback, '#');
}

/**
 * Vignette d'un design selon sa cible.
 *
 * @param array  $configExt  configuration JSON du design ([settings] + sections)
 * @param string $cible      landing | login | admin
 * @return string            balise <svg ...>...</svg>
 */
function designThumb(array $configExt, string $cible): string
{
    $s  = $configExt['settings'] ?? [];
    $w  = 280;
    $h  = 180;

    if ($cible === 'login') {
        $fond     = thumbColor($s, 'login_fond', '#0a1628');
        $carte    = thumbColor($s, 'login_carte_fond', '#ffffff');
        $texte    = thumbColor($s, 'login_texte_couleur', '#1e293b');
        $accent   = thumbColor($s, 'login_secondaire', '#00b4d8');
        $titre    = thumbColor($s, 'login_primaire', '#0a1628');

        $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Apercu design connexion">';
        $svg .= '<rect width="' . $w . '" height="' . $h . '" rx="10" fill="#' . $fond . '"/>';
        $svg .= '<rect x="90" y="34" width="100" height="112" rx="8" fill="#' . $carte . '"/>';
        $svg .= '<rect x="108" y="48" width="64" height="10" rx="5" fill="#' . $titre . '" opacity="0.85"/>';
        $svg .= '<rect x="108" y="64" width="64" height="8" rx="4" fill="#' . $texte . '" opacity="0.35"/>';
        $svg .= '<rect x="108" y="78" width="64" height="8" rx="4" fill="#' . $texte . '" opacity="0.35"/>';
        $svg .= '<rect x="108" y="112" width="64" height="14" rx="7" fill="#' . $accent . '"/>';
        $svg .= '<circle cx="140" cy="26" r="6" fill="#' . $accent . '"/>';
        $svg .= '</svg>';
        return $svg;
    }

    if ($cible === 'admin') {
        $primaire  = thumbColor($s, 'admin_couleur_primaire', '#0a1628');
        $primaireL = thumbColor($s, 'admin_couleur_primaire_light', '#1a2d4a');
        $accent    = thumbColor($s, 'admin_couleur_secondaire', '#00b4d8');
        $fond      = thumbColor($s, 'admin_fond', '#f1f5f9');
        $carte     = thumbColor($s, 'admin_fond_card', '#ffffff');
        $border    = thumbColor($s, 'admin_border', '#e2e8f0');
        $texte     = thumbColor($s, 'admin_texte', '#1e293b');

        $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Apercu design admin">';
        $svg .= '<rect width="' . $w . '" height="' . $h . '" rx="10" fill="#' . $fond . '"/>';
        $svg .= '<rect width="64" height="' . $h . '" rx="10" fill="#' . $primaire . '"/>';
        $svg .= '<rect x="8" y="12" width="16" height="16" rx="3" fill="#' . $accent . '"/>';
        $svg .= '<rect x="34" y="16" width="22" height="8" rx="3" fill="#' . $primaireL . '"/>';
        $svg .= '<rect x="12" y="60" width="40" height="8" rx="3" fill="#' . $accent . '"/>';
        $svg .= '<rect x="12" y="76" width="40" height="8" rx="3" fill="#' . $primaireL . '"/>';
        $svg .= '<rect x="12" y="92" width="40" height="8" rx="3" fill="#' . $primaireL . '"/>';
        $svg .= '<rect x="12" y="108" width="40" height="8" rx="3" fill="#' . $primaireL . '"/>';
        $svg .= '<rect x="76" y="12" width="120" height="10" rx="4" fill="#' . $carte . '" stroke="#' . $border . '" stroke-width="1"/>';
        $svg .= '<rect x="76" y="34" width="190" height="26" rx="6" fill="#' . $carte . '" stroke="#' . $border . '" stroke-width="1"/>';
        $svg .= '<rect x="84" y="42" width="60" height="6" rx="3" fill="#' . $texte . '" opacity="0.5"/>';
        $svg .= '<rect x="76" y="70" width="90" height="40" rx="6" fill="#' . $carte . '" stroke="#' . $border . '" stroke-width="1"/>';
        $svg .= '<rect x="176" y="70" width="90" height="40" rx="6" fill="#' . $carte . '" stroke="#' . $border . '" stroke-width="1"/>';
        $svg .= '<rect x="76" y="122" width="90" height="44" rx="6" fill="#' . $carte . '" stroke="#' . $border . '" stroke-width="1"/>';
        $svg .= '<rect x="176" y="122" width="90" height="44" rx="6" fill="#' . $carte . '" stroke="#' . $border . '" stroke-width="1"/>';
        $svg .= '</svg>';
        return $svg;
    }

    // --- landing (defaut) ---
    $primaire  = thumbColor($s, 'couleur_primaire', '#0a1628');
    $secondaire= thumbColor($s, 'couleur_secondaire', '#00b4d8');
    $fond      = thumbColor($s, 'couleur_fond', '#f8fafc');
    $heroTexte = '#ffffff';

    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Apercu design landing">';
    $svg .= '<rect width="' . $w . '" height="' . $h . '" rx="10" fill="#' . $fond . '"/>';
    // Navbar
    $svg .= '<rect width="' . $w . '" height="26" rx="10" fill="#' . $primaire . '"/>';
    $svg .= '<rect x="12" y="9" width="18" height="10" rx="3" fill="#' . $secondaire . '"/>';
    $svg .= '<circle cx="214" cy="13" r="2.5" fill="#' . $fond . '" opacity="0.6"/>';
    $svg .= '<circle cx="226" cy="13" r="2.5" fill="#' . $fond . '" opacity="0.6"/>';
    $svg .= '<circle cx="238" cy="13" r="2.5" fill="#' . $fond . '" opacity="0.6"/>';
    // Hero
    $svg .= '<rect y="26" width="' . $w . '" height="52" fill="#' . $primaire . '"/>';
    $svg .= '<rect x="116" y="40" width="48" height="8" rx="4" fill="#' . $heroTexte . '" opacity="0.9"/>';
    $svg .= '<rect x="126" y="52" width="28" height="6" rx="3" fill="#' . $heroTexte . '" opacity="0.45"/>';
    $svg .= '<rect x="120" y="64" width="40" height="8" rx="4" fill="#' . $secondaire . '"/>';
    // Trois blocs de sections
    foreach ([62, 122, 182] as $i => $x) {
        $svg .= '<rect x="' . $x . '" y="92" width="56" height="72" rx="6" fill="#' . $fond . '" stroke="#' . $secondaire . '" stroke-width="1.5"/>';
        $svg .= '<rect x="' . ($x + 12) . '" y="104" width="32" height="8" rx="3" fill="#' . $primaire . '" opacity="0.75"/>';
        $svg .= '<rect x="' . ($x + 12) . '" y="120" width="32" height="6" rx="3" fill="#' . $primaire . '" opacity="0.3"/>';
        $svg .= '<rect x="' . ($x + 12) . '" y="132" width="32" height="6" rx="3" fill="#' . $primaire . '" opacity="0.3"/>';
        $svg .= '<circle cx="' . ($x + 28) . '" cy="148" r="7" fill="#' . $secondaire . '"/>';
    }
    $svg .= '</svg>';
    return $svg;
}