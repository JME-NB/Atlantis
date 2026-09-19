<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Landing Page Publique
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$csrf = generateCsrfToken();

$pdo = getDB();

// Charger les parametres du site
$stmt = $pdo->query('SELECT cle, valeur FROM site_settings');
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['cle']] = $row['valeur'];
}

// Charger les sections visibles
$stmt = $pdo->query('SELECT * FROM site_sections WHERE visible = 1 ORDER BY ordre ASC');
$sections = [];
while ($row = $stmt->fetch()) {
    $sections[$row['section_key']] = $row;
}

// --- Apercu d'un design non publie (?preview=ID&sig=...) ---
$previewRibbon = null;
if (isset($_GET['preview'], $_GET['sig'])) {
    require_once __DIR__ . '/../includes/preview.php';
    $previewDesign = previewDesign((int) $_GET['preview'], (string) $_GET['sig']);
    if ($previewDesign !== null && $previewDesign['cible'] === 'landing') {
        $cfg = $previewDesign['configuration'];
        if (isset($cfg['settings']) && is_array($cfg['settings'])) {
            $settings = array_merge($settings, $cfg['settings']);
        }
        if (isset($cfg['sections']) && is_array($cfg['sections'])) {
            $previewSections = [];
            foreach ($cfg['sections'] as $sec) {
                $secKey = (string)($sec['section_key'] ?? '');
                if ($secKey !== '') $previewSections[$secKey] = $sec;
            }
            if (!empty($previewSections)) $sections = $previewSections;
        }
        $previewRibbon = (string) $previewDesign['nom'];
    }
}

// Charger les rubriques (cartes) de chaque section
// Charger les rubriques (cartes) de chaque section (1 seule requete, plus de N+1)
$rubriques = [];
if ($previewRibbon !== null) {
    // En mode apercu, les rubriques viennent du design (gonserve l'ordre du design)
    foreach ($sections as $key => $sec) {
        $rubriques[$key] = array_values(array_filter($sec['rubriques'] ?? [], function ($r) {
            return !empty($r['visible']);
        }));
    }
} else {
    $rubStmt = $pdo->query(
        'SELECT * FROM site_rubriques WHERE visible = 1 ORDER BY section_id ASC, position ASC'
    );
    $rubriquesParSection = [];
    while ($rub = $rubStmt->fetch()) {
        $rubriquesParSection[(int)$rub['section_id']][] = $rub;
    }
    foreach ($sections as $key => $sec) {
        $rubriques[$key] = $rubriquesParSection[(int)$sec['id']] ?? [];
    }
}

$primary   = $settings['couleur_primaire'] ?? '#0a1628';
$secondary = $settings['couleur_secondaire'] ?? '#00b4d8';
$fond      = $settings['couleur_fond'] ?? '#f8fafc';
$policeT   = $settings['police_titre'] ?? 'Inter';
$policeC   = $settings['police_corps'] ?? 'Inter';
$banniere  = $settings['banniere_url'] ?? '';
$logoUrl   = $settings['logo_url'] ?? '';

// Tailles de police configurables (px). Vides => le CSS par defaut s'applique.
$tailleBase       = $settings['landing_taille_base'] ?? '';
$tailleHeroTitre  = $settings['landing_taille_hero_titre'] ?? '';
$tailleTitreSec   = $settings['landing_taille_titre_section'] ?? '';
$tailleCorps      = $settings['landing_taille_corps'] ?? '';

/**
 * Styles inline d'une section : fond image + fond couleur + couleur du texte.
 * Appliques UNIQUEMENT si explicitement renseignes (pas de degradation du look par defaut).
 */
function sectionStyle(array $sec): string
{
    $out = '';
    if (!empty($sec['fond_image_url'])) {
        $out .= 'background-image:url(' . htmlspecialchars($sec['fond_image_url'], ENT_QUOTES, 'UTF-8') . ');background-size:cover;background-position:center;';
    }
    if (!empty($sec['fond_couleur'])) {
        $out .= 'background-color:' . htmlspecialchars($sec['fond_couleur'], ENT_QUOTES, 'UTF-8') . ';';
    }
    if (!empty($sec['texte_couleur'])) {
        $out .= 'color:' . htmlspecialchars($sec['texte_couleur'], ENT_QUOTES, 'UTF-8') . ';';
    }
    return $out;
}

/**
 * Style inline du titre d'une section (taille en px).
 */
function titleSizeStyle(array $sec, string $fallbackCss): string
{
    if (!empty($sec['titre_taille'])) {
        return ' font-size:' . htmlspecialchars($sec['titre_taille'], ENT_QUOTES, 'UTF-8') . 'px;';
    }
    return $fallbackCss;
}

/**
 * Style inline d'une carte de rubrique (fond + texte si renseignes).
 */
function rubriqueStyle(array $rub): string
{
    $out = '';
    if (!empty($rub['fond_couleur'])) {
        $out .= 'background-color:' . htmlspecialchars($rub['fond_couleur'], ENT_QUOTES, 'UTF-8') . ';';
    }
    if (!empty($rub['texte_couleur'])) {
        $out .= 'color:' . htmlspecialchars($rub['texte_couleur'], ENT_QUOTES, 'UTF-8') . ';';
    }
    return $out;
}

/**
 * SVG d'une icone de rubrique (les 8 choisissables + les 5 heritees).
 */
function rubriqueIconSvg(?string $key): string
{
    $paths = [
        'ecoute'      => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
        'reactivite'  => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'qualite'     => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'televente'   => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
        'prospection' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'apres_vente' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'relation'    => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'telecom'     => '<path d="M1 1l4 4m14-4l-4 4"/><circle cx="12" cy="12" r="3"/><path d="M5 5l2 2m10-2l-2 2M5 19l2-2m10 2l-2-2"/><path d="M1 12h4m14 0h4"/>',
        'banque'      => '<rect x="2" y="3" width="20" height="18" rx="2"/><path d="M2 9h20"/><path d="M9 21V9"/>',
        'sante'       => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'retail'      => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'energie'     => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'assurance'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
    ];
    if (!$key || !isset($paths[$key])) {
        return '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/>';
    }
    return $paths[$key];
}

// Aide : icone ou image d'une rubrique (utilise surtout pour les cartes).
function rubriqueVisual(array $rub, string $wrapperClass): string
{
    if (!empty($rub['image_url'])) {
        return '<div class="' . $wrapperClass . '"><img src="' . htmlspecialchars($rub['image_url'], ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($rub['titre'] ?? '', ENT_QUOTES, 'UTF-8') . '" loading="lazy" class="' . $wrapperClass . '-img"></div>';
    }
    $svg = rubriqueIconSvg($rub['icone'] ?? null);
    return '<div class="' . $wrapperClass . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">' . $svg . '</svg></div>';
}

/**
 * Rend un champ du formulaire de contact (configurable).
 */
function formFieldHtml(array $field): string
{
    $cle         = (string)($field['cle'] ?? '');
    $libelle     = (string)($field['libelle'] ?? $cle);
    $placeholder = (string)($field['placeholder'] ?? '');
    $type        = fieldType($field);
    $obligato    = !empty($field['obligatoire']);
    $options     = (array)($field['options'] ?? []);

    $req = $obligato ? ' <span class="req">*</span>' : '';
    $label = '<label for="' . htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8') . $req . '</label>';

    if ($cle === 'pieces') {
        $hintAllowed = $obligato ? '6 fichiers max' : 'Optionnel';
        return '<div class="form-group">'
            . $label
            . '<div class="upload-zone" id="uploadZone" role="button" tabindex="0" aria-label="Ajouter des pieces jointes">'
            . '<svg class="upload-zone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>'
            . '<span class="upload-zone-title">Cliquez pour ajouter vos pieces jointes</span>'
            . '<small class="upload-hint">Formats : PDF, JPG, JPEG, PNG - 5 Mo max par fichier - ' . $hintAllowed . '</small>'
            . '</div>'
            . '<input type="file" id="piecesInput" name="pieces" accept=".jpg,.jpeg,.png,.pdf" multiple hidden>'
            . '<div class="piece-list" id="pieceList" hidden></div>'
            . '<small class="field-error" id="piecesError"></small>'
            . '</div>';
    }

    $dataType = $type;
    if ($type === 'select') $dataType = 'select';
    $dataTypeAttr = in_array($dataType, ['email', 'tel', 'select'], true)
        ? ' data-type="' . $dataType . '"'
        : '';
    $dataReq = $obligato ? ' data-required="1"' : '';

    $input = '';
    if ($type === 'textarea') {
        $input = '<textarea id="' . htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') . '" rows="4" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '"' . $dataReq . '></textarea>';
    } elseif ($type === 'select') {
        $input = '<select id="' . htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') . '"' . $dataReq . '>';
        $input .= '<option value="">Choisir...</option>';
        foreach ($options as $opt) {
            $opt = (string) $opt;
            $optEsc = htmlspecialchars($opt, ENT_QUOTES, 'UTF-8');
            $optLabel = $cle === 'type' ? htmlspecialchars(typeLabel($opt), ENT_QUOTES, 'UTF-8') : $optEsc;
            $input .= '<option value="' . $optEsc . '">' . $optLabel . '</option>';
        }
        $input .= '</select>';
    } else {
        $inputType = $type === 'email' ? 'email' : ($type === 'tel' ? 'tel' : 'text');
        $input = '<input type="' . $inputType . '" id="' . htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') . '" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '"' . $dataTypeAttr . $dataReq . '>';
    }

    return '<div class="form-group">'
        . $label
        . $input
        . '<small class="field-error" id="' . htmlspecialchars($cle, ENT_QUOTES, 'UTF-8') . 'Error"></small>'
        . '</div>';
}

// Police : URL Google Fonts (de-dupliquee si titre == corps)
$fontUrl = 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', $policeT) . ':wght@400;500;600;700;800&display=swap';
if ($policeC !== $policeT) {
    $fontUrl .= '&family=' . str_replace(' ', '+', $policeC) . ':wght@400;500;600;700;800&display=swap';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATLANTIS | Centre d'appel &amp; relation client</title>
    <meta name="description" content="ATLANTIS est un centre d'appel specialise en televente, prospection, service apres-vente et relation client.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23<?php echo ltrim($primary, '#'); ?>'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='%23<?php echo ltrim($secondary, '#'); ?>' font-family='Arial' font-weight='bold'>A</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?php echo htmlspecialchars($fontUrl, ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=12">
    <style>
        :root {
            --primary: <?php echo htmlspecialchars($primary, ENT_QUOTES, 'UTF-8'); ?>;
            --secondary: <?php echo htmlspecialchars($secondary, ENT_QUOTES, 'UTF-8'); ?>;
            --fond: <?php echo htmlspecialchars($fond, ENT_QUOTES, 'UTF-8'); ?>;
            --font-titre: '<?php echo htmlspecialchars($policeT, ENT_QUOTES, 'UTF-8'); ?>', sans-serif;
            --font-corps: '<?php echo htmlspecialchars($policeC, ENT_QUOTES, 'UTF-8'); ?>', sans-serif;
            <?php if ($tailleHeroTitre !== ''): ?>--taille-hero-titre: <?php echo (int) $tailleHeroTitre; ?>px;<?php endif; ?>
            <?php if ($tailleTitreSec !== ''): ?>--taille-titre-section: <?php echo (int) $tailleTitreSec; ?>px;<?php endif; ?>
            <?php if ($tailleCorps !== ''): ?>--taille-corps: <?php echo (int) $tailleCorps; ?>px;<?php endif; ?>
        }
        <?php if ($tailleBase !== ''): ?>
        html { font-size: <?php echo (int) $tailleBase; ?>px; }
        <?php endif; ?>
    </style>
</head>
<body>

<?php if ($previewRibbon !== null): ?>
<div class="preview-ribbon">
    <strong>Apercu du design « <?php echo htmlspecialchars($previewRibbon, ENT_QUOTES, 'UTF-8'); ?> »</strong> — non publie.
    <a href="<?php echo BASE_URL; ?>/public/">&times; Retour au site</a>
</div>
<?php endif; ?>

<header id="navbar" class="navbar">
    <div class="container nav-container">
        <a href="#accueil" class="logo" aria-label="ATLANTIS - Accueil">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="ATLANTIS" style="height:38px;width:38px;object-fit:contain;border-radius:10px;">
            <?php else: ?>
                <span class="logo-mark">A</span>
            <?php endif; ?>
            <span class="logo-text">ATLANTIS</span>
        </a>
        <nav class="nav-links" id="navLinks" aria-label="Navigation principale">
            <a href="#accueil">Accueil</a>
            <a href="#apropos">A propos</a>
            <a href="#services">Services</a>
            <a href="#pourquoi">Pourquoi ATLANTIS</a>
            <a href="#contact" class="nav-cta">Contact</a>
        </nav>
        <button class="theme-toggle" id="themeToggle" aria-label="Basculer le mode jour/nuit">
            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>
        <button class="hamburger" id="hamburger" aria-label="Ouvrir le menu" aria-expanded="false">
            <span class="bar"></span><span class="bar"></span><span class="bar"></span>
        </button>
    </div>
</header>

<main>

    <!-- HERO -->
    <?php if (!empty($sections['hero'])): ?>
    <section id="accueil" class="hero">
        <svg class="hero-art" viewBox="0 0 1440 760" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="heroArtGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#0a1628"/>
                    <stop offset="55%" stop-color="#0c2740"/>
                    <stop offset="100%" stop-color="#07566e"/>
                </linearGradient>
                <radialGradient id="heroArtGlow" cx="0.78" cy="0.25" r="0.65">
                    <stop offset="0%" stop-color="#00b4d8" stop-opacity="0.35"/>
                    <stop offset="100%" stop-color="#00b4d8" stop-opacity="0"/>
                </radialGradient>
                <radialGradient id="heroArtGlow2" cx="0.12" cy="0.85" r="0.55">
                    <stop offset="0%" stop-color="#123a5c" stop-opacity="0.55"/>
                    <stop offset="100%" stop-color="#123a5c" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <rect width="1440" height="760" fill="url(#heroArtGrad)"/>
            <rect width="1440" height="760" fill="url(#heroArtGlow)"/>
            <rect width="1440" height="760" fill="url(#heroArtGlow2)"/>
            <circle cx="1180" cy="170" r="330" fill="#00b4d8" opacity="0.06"/>
            <circle cx="160" cy="640" r="320" fill="#ffffff" opacity="0.03"/>
            <circle cx="1340" cy="600" r="90" fill="#ffffff" opacity="0.05"/>
            <g fill="#ffffff" opacity="0.10">
                <rect x="330" y="300" width="170" height="58" rx="29"/>
                <path d="M366 358 l20 30 l36 -30 z"/>
            </g>
            <g fill="#00b4d8" opacity="0.16">
                <rect x="520" y="180" width="200" height="62" rx="31"/>
                <path d="M560 242 l22 30 l42 -30 z"/>
            </g>
            <g fill="none" stroke="#00b4d8" stroke-width="3" opacity="0.25" stroke-linecap="round">
                <path d="M640 360 q16 -13 32 0 q16 13 32 0"/>
                <path d="M640 380 q16 -13 32 0 q16 13 32 0"/>
                <path d="M640 400 q16 -13 32 0 q16 13 32 0"/>
            </g>
            <g fill="#ffffff" opacity="0.13">
                <circle cx="1120" cy="470" r="28"/>
                <rect x="1080" y="508" width="80" height="150" rx="36"/>
                <circle cx="1232" cy="470" r="28"/>
                <rect x="1192" y="508" width="80" height="150" rx="36"/>
            </g>
            <g fill="none" stroke="#00b4d8" stroke-width="4" opacity="0.55" stroke-linecap="round">
                <path d="M1122 444 a28 28 0 0 1 46 0"/>
                <path d="M1145 444 v18"/>
            </g>
            <g fill="none" stroke="#00b4d8" stroke-width="4" opacity="0.4" stroke-linecap="round">
                <path d="M1234 444 a28 28 0 0 1 46 0"/>
                <path d="M1257 444 v18"/>
            </g>
        </svg>
        <?php if (!empty($banniere)): ?>
        <div class="hero-image-bg" data-src="<?php echo htmlspecialchars($banniere, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <?php endif; ?>
        <div class="hero-image-overlay"></div>
        <div class="container hero-content reveal">
            <span class="hero-badge">Centre d'appel &middot; Yaounde &middot; Cameroun</span>
            <h1>ATLANTIS</h1>
            <h2 class="hero-title" style="<?php echo titleSizeStyle($sections['hero'] ?? [], ''); ?>"><?php echo htmlspecialchars($sections['hero']['titre'] ?? 'Votre relation client, notre savoir-faire.', ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="hero-subtitle"><?php echo htmlspecialchars($sections['hero']['contenu'] ?? 'Centre d\'appel base a Yaounde, ATLANTIS accompagne ses partenaires dans la televente, la prospection, le service apres-vente et la relation client.', ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="hero-actions">
                <a href="#contact" class="btn btn-primary">Demarrer un projet</a>
                <a href="#services" class="btn btn-outline">Decouvrir nos services</a>
            </div>
        </div>
        <div class="hero-wave" aria-hidden="true">
            <svg viewBox="0 0 1440 100" preserveAspectRatio="none"><path d="M0,64 C360,110 720,20 1080,52 C1260,68 1350,72 1440,58 L1440,100 L0,100 Z" fill="<?php echo htmlspecialchars($fond, ENT_QUOTES, 'UTF-8'); ?>"/></svg>
        </div>
    </section>
    <?php endif; ?>

    <?php $secApropos = $sections['apropos'] ?? []; $rubApropos = $rubriques['apropos'] ?? []; ?>
    <?php if (!empty($secApropos)): ?>
    <section id="apropos" class="section" style="<?php echo sectionStyle($secApropos); ?>">
        <div class="container">
            <div class="section-head reveal">
                <span class="section-tag">A propos</span>
                <h2 class="section-title" style="<?php echo titleSizeStyle($secApropos, ''); ?>"><?php echo htmlspecialchars($secApropos['titre'] ?? 'Plus qu\'un centre d\'appel. Un partenaire.', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="section-text" style="<?php if ($tailleCorps !== '') echo 'font-size:var(--taille-corps);'; ?>"><?php echo htmlspecialchars($secApropos['contenu'] ?? 'Chez ATLANTIS, nous ne nous contentons pas de traiter des appels. Nous representons votre marque aupres de vos clients et prospects avec professionnalisme, ecoute et engagement.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="values-grid">
                <?php if (empty($rubApropos)): ?>
                <article class="value-card reveal">
                    <div class="value-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
                    <h3>Ecoute</h3>
                    <p>Nous comprenons les besoins de vos clients avant de repondre.</p>
                </article>
                <?php else: ?>
                <?php foreach ($rubApropos as $i => $rub): ?>
                <article class="value-card reveal" style="<?php echo rubriqueStyle($rub); ?>">
                    <?php echo rubriqueVisual($rub, 'value-icon'); ?>
                    <h3><?php echo htmlspecialchars($rub['titre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars($rub['contenu'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php $secServices = $sections['services'] ?? []; $rubServices = $rubriques['services'] ?? []; ?>
    <?php if (!empty($secServices)): ?>
    <section id="services" class="section section-alt" style="<?php echo sectionStyle($secServices); ?>">
        <div class="container">
            <div class="section-head reveal">
                <span class="section-tag">Services</span>
                <h2 class="section-title" style="<?php echo titleSizeStyle($secServices, ''); ?>"><?php echo htmlspecialchars($secServices['titre'] ?? 'Des solutions adaptees a vos objectifs', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="section-text" style="<?php if ($tailleCorps !== '') echo 'font-size:var(--taille-corps);'; ?>"><?php echo htmlspecialchars($secServices['contenu'] ?? 'De la televente au support client, nous couvrons l\'ensemble de vos besoins en relation client.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="services-slider">
                <div class="services-grid" id="servicesGrid">
                    <?php if (empty($rubServices)): ?>
                    <article class="service-card reveal">
                        <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg></div>
                        <h3>Televente</h3>
                        <p>Nos agents qualifies vendent vos produits et services avec professionalisme et empathie.</p>
                    </article>
                    <?php else: ?>
                    <?php foreach ($rubServices as $i => $rub): ?>
                    <article class="service-card reveal" style="<?php echo rubriqueStyle($rub); ?>">
                        <?php echo rubriqueVisual($rub, 'service-icon'); ?>
                        <h3><?php echo htmlspecialchars($rub['titre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($rub['contenu'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </article>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="slider-nav">
                    <button type="button" class="slider-btn" id="servicesPrev" aria-label="Services precedents">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button type="button" class="slider-btn" id="servicesNext" aria-label="Services suivants">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php $secPourquoi = $sections['pourquoi'] ?? []; $rubPourquoi = $rubriques['pourquoi'] ?? []; ?>
    <?php if (!empty($secPourquoi)): ?>
    <section id="pourquoi" class="section section-dark" style="<?php echo sectionStyle($secPourquoi); ?>">
        <div class="why-bg" aria-hidden="true">
            <?php if (!empty($secPourquoi['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($secPourquoi['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
            <?php else: ?>
                <img src="assets/images/why-bg.jpg" alt="" loading="lazy">
            <?php endif; ?>
        </div>
        <div class="container">
            <div class="section-head reveal">
                <span class="section-tag tag-light">Pourquoi ATLANTIS</span>
                <h2 class="section-title title-light" style="<?php echo titleSizeStyle($secPourquoi, ''); ?>"><?php echo htmlspecialchars($secPourquoi['titre'] ?? 'Pourquoi choisir ATLANTIS ?', ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
            <div class="why-grid">
                <?php $rubCount = 0; foreach (($rubPourquoi ?: array_fill(0, 4, null)) as $i => $rub): $rubCount++; ?>
                <article class="why-card reveal" style="<?php echo $rub ? rubriqueStyle($rub) : ''; ?>">
                    <span class="why-number"><?php echo sprintf('%02d', $i + 1); ?></span>
                    <h3><?php echo htmlspecialchars($rub['titre'] ?? ['Expertise locale', 'Flexibilite', 'Technologie', 'Engagement'][$i % 4], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars($rub['contenu'] ?? ['Une equipe bilingue basee a Yaounde, connaissant les realites du marche camerounais et africain.', 'Des solutions sur mesure adaptees a la taille et aux objectifs de votre entreprise.', 'Des outils modernes pour un suivi en temps reel et des rapports detailles.', 'Nous nous impliquons dans vos projets comme si c\'etait les notres.'][$i % 4], ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php $secProcessus = $sections['processus'] ?? []; $rubProcessus = $rubriques['processus'] ?? []; ?>
    <?php if (!empty($secProcessus)): ?>
    <section id="processus" class="section" style="<?php echo sectionStyle($secProcessus); ?>">
        <div class="container">
            <div class="section-head reveal">
                <span class="section-tag">Processus</span>
                <h2 class="section-title" style="<?php echo titleSizeStyle($secProcessus, ''); ?>"><?php echo htmlspecialchars($secProcessus['titre'] ?? 'Un deploiement maitrise en 4 etapes', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="section-text" style="<?php if ($tailleCorps !== '') echo 'font-size:var(--taille-corps);'; ?>"><?php echo htmlspecialchars($secProcessus['contenu'] ?? 'De l\'analyse initiale au suivi continu, chaque etape est concue pour maximiser vos resultats.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="process-grid">
                <div class="process-line"></div>
                <?php foreach (($rubProcessus ?: array_fill(0, 4, null)) as $i => $rub): ?>
                <article class="process-step reveal" style="<?php echo $rub ? rubriqueStyle($rub) : ''; ?>">
                    <span class="process-number"><?php echo sprintf('%02d', $i + 1); ?></span>
                    <h3><?php echo htmlspecialchars($rub['titre'] ?? ['Analyse', 'Mise en place', 'Lancement', 'Suivi'][$i % 4], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars($rub['contenu'] ?? ['Nous etudions vos besoins, votre marche et vos objectifs pour definir une strategie sur mesure.', 'Recrutement, formation et equipement de votre equipe selon vos specifications.', 'Deploiement progressif avec des tests pilotes avant le lancement a grande echelle.', 'Reporting regulier, optimisation continue et points d\'etat pour garantir la performance.'][$i % 4], ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php $secSecteurs = $sections['secteurs'] ?? []; $rubSecteurs = $rubriques['secteurs'] ?? []; ?>
    <?php if (!empty($secSecteurs)): ?>
    <section id="secteurs" class="section section-alt" style="<?php echo sectionStyle($secSecteurs); ?>">
        <div class="container">
            <div class="section-head reveal">
                <span class="section-tag">Secteurs</span>
                <h2 class="section-title" style="<?php echo titleSizeStyle($secSecteurs, ''); ?>"><?php echo htmlspecialchars($secSecteurs['titre'] ?? 'Une expertise qui s\'adapte a votre activite', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="section-text" style="<?php if ($tailleCorps !== '') echo 'font-size:var(--taille-corps);'; ?>"><?php echo htmlspecialchars($secSecteurs['contenu'] ?? 'Notre experience couvre une variete de secteurs d\'activite, chacun avec ses specificites et exigences.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="secteurs-slider">
                <div class="secteurs-grid" id="secteursGrid">
                    <?php foreach ($rubSecteurs as $i => $rub): ?>
                    <article class="secteur-card reveal" style="<?php echo rubriqueStyle($rub); ?>">
                        <?php echo rubriqueVisual($rub, 'secteur-icon'); ?>
                        <h3><?php echo htmlspecialchars($rub['titre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($rub['contenu'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </article>
                    <?php endforeach; ?>
                </div>
                <div class="slider-nav">
                    <button type="button" class="slider-btn" id="secteursPrev" aria-label="Secteurs precedents">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button type="button" class="slider-btn" id="secteursNext" aria-label="Secteurs suivants">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php $secContact = $sections['contact'] ?? []; ?>
    <?php if (!empty($secContact)): ?>
    <section id="contact" class="section" style="<?php echo sectionStyle($secContact); ?>">
        <div class="container contact-container">
            <div class="contact-info reveal">
                <span class="section-tag">Contact</span>
                <h2 class="section-title" style="<?php echo titleSizeStyle($secContact, ''); ?>"><?php echo htmlspecialchars($secContact['titre'] ?? 'Travaillons ensemble', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="section-text" style="<?php if ($tailleCorps !== '') echo 'font-size:var(--taille-corps);'; ?>"><?php echo htmlspecialchars($secContact['contenu'] ?? 'Vous souhaitez developper votre activite avec ATLANTIS ou rejoindre notre equipe ? Remplissez le formulaire et notre equipe reviendra vers vous.', ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="section-text small-text">Vos informations sont utilisees uniquement pour traiter votre demande et vous recontacter.</p>
            </div>
            <div class="contact-form-wrap reveal">
                <?php
                $formFieldsAll = getLandingFormFields();
                $formFieldsVisible = array_values(array_filter($formFieldsAll, function ($f) {
                    return !empty($f['visible']);
                }));
                $formPiecesVisible  = false;
                $formPiecesRequired = false;
                foreach ($formFieldsVisible as $f) {
                    if (($f['cle'] ?? '') === 'pieces') {
                        $formPiecesVisible  = true;
                        $formPiecesRequired = !empty($f['obligatoire']);
                    }
                }

                // Groupement : 2 champs par ligne ; pieces / textarea -> pleine largeur
                $formRows = [];
                $chunk = [];
                foreach ($formFieldsVisible as $f) {
                    $ft = fieldType($f);
                    if (($f['cle'] ?? '') === 'pieces' || $ft === 'textarea') {
                        if (!empty($chunk)) { $formRows[] = $chunk; $chunk = []; }
                        $formRows[] = [$f];
                    } else {
                        $chunk[] = $f;
                        if (count($chunk) === 2) { $formRows[] = $chunk; $chunk = []; }
                    }
                }
                if (!empty($chunk)) $formRows[] = $chunk;
                ?>
                <form id="contactForm" class="contact-form" novalidate>
                    <?php foreach ($formRows as $row): ?>
                    <?php if (count($row) === 1): ?>
                    <div class="form-row form-row-single"><?php echo formFieldHtml($row[0]); ?></div>
                    <?php else: ?>
                    <div class="form-row">
                        <?php foreach ($row as $f): echo formFieldHtml($f); endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="form-feedback" id="formFeedback" role="alert" hidden></div>
                    <button type="submit" class="btn btn-primary btn-block" id="submitBtn" disabled>Envoyer ma demande</button>
                </form>
            </div>
        </div>
    </section>
    <?php endif; ?>

</main>

<?php if (!empty($sections['footer'])): ?>
<footer class="footer">
    <div class="container footer-container">
        <div class="footer-brand">
            <a href="#accueil" class="logo logo-footer">
                <?php if (!empty($logoUrl)): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="ATLANTIS" style="height:38px;width:38px;object-fit:contain;border-radius:10px;background:rgba(255,255,255,0.1);">
                <?php else: ?>
                    <span class="logo-mark">A</span>
                <?php endif; ?>
                <span class="logo-text">ATLANTIS</span>
            </a>
            <p>Votre relation client, notre savoir-faire.</p>
        </div>
        <div class="footer-links">
            <h4>Navigation</h4>
            <a href="#accueil">Accueil</a>
            <a href="#apropos">A propos</a>
            <a href="#services">Services</a>
            <a href="#contact">Contact</a>
        </div>
        <div class="footer-address">
            <h4>Nous trouver</h4>
            <p>Quartier Fouda<br>Yaounde, Cameroun</p>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p>&copy; 2026 ATLANTIS &mdash; Tous droits reserves.</p>
            <p class="legal-mention">Conformement a la loi camerounaise sur la protection des donnees personnelles, vos informations sont traitees uniquement dans le but de repondre a votre demande. Elles sont conservees pour une duree maximale de 24 mois et ne sont en aucun cas revendues a des tiers. Pour toute question ou demande de suppression, contactez-nous via le formulaire de contact.</p>
        </div>
    </div>
</footer>
<?php endif; ?>

<button id="backToTop" class="back-to-top" aria-label="Retour en haut" title="Retour en haut">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>
<div id="notificationArea" class="notification-area" aria-live="polite"></div>

<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
const CSRF_TOKEN = '<?php echo $csrf; ?>';
var FORM_PIECES_VISIBLE = <?php echo $formPiecesVisible ? 'true' : 'false'; ?>;
var FORM_PIECES_REQUIRED = <?php echo $formPiecesRequired ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/script.js?v=12"></script>
</body>
</html>
