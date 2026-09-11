<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Landing Page Publique
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';

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

$primary   = $settings['couleur_primaire'] ?? '#0a1628';
$secondary = $settings['couleur_secondaire'] ?? '#00b4d8';
$fond      = $settings['couleur_fond'] ?? '#f8fafc';
$policeT   = $settings['police_titre'] ?? 'Inter';
$policeC   = $settings['police_corps'] ?? 'Inter';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary: <?php echo htmlspecialchars($primary, ENT_QUOTES, 'UTF-8'); ?>;
            --secondary: <?php echo htmlspecialchars($secondary, ENT_QUOTES, 'UTF-8'); ?>;
            --fond: <?php echo htmlspecialchars($fond, ENT_QUOTES, 'UTF-8'); ?>;
            --font-titre: '<?php echo htmlspecialchars($policeT, ENT_QUOTES, 'UTF-8'); ?>', sans-serif;
            --font-corps: '<?php echo htmlspecialchars($policeC, ENT_QUOTES, 'UTF-8'); ?>', sans-serif;
        }
    </style>
</head>
<body>

<header id="navbar" class="navbar">
    <div class="container nav-container">
        <a href="#accueil" class="logo" aria-label="ATLANTIS - Accueil">
            <span class="logo-mark">A</span><span class="logo-text">ATLANTIS</span>
        </a>
        <nav class="nav-links" id="navLinks" aria-label="Navigation principale">
            <a href="#accueil">Accueil</a>
            <a href="#apropos">A propos</a>
            <a href="#services">Services</a>
            <a href="#pourquoi">Pourquoi ATLANTIS</a>
            <a href="#contact" class="nav-cta">Contact</a>
        </nav>
        <button class="hamburger" id="hamburger" aria-label="Ouvrir le menu" aria-expanded="false">
            <span class="bar"></span><span class="bar"></span><span class="bar"></span>
        </button>
    </div>
</header>

<main>

    <!-- HERO -->
    <section id="accueil" class="hero">
        <div class="container hero-content reveal">
            <span class="hero-badge">Centre d'appel &middot; Yaounde &middot; Cameroun</span>
            <h1>ATLANTIS</h1>
            <h2 class="hero-title"><?php echo htmlspecialchars($sections['hero']['titre'] ?? 'Votre relation client, notre savoir-faire.', ENT_QUOTES, 'UTF-8'); ?></h2>
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

    <!-- A PROPOS -->
    <section id="apropos" class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="section-tag">A propos</span>
                <h2 class="section-title"><?php echo htmlspecialchars($sections['apropos']['titre'] ?? 'Plus qu\'un centre d\'appel. Un partenaire.', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="section-text"><?php echo htmlspecialchars($sections['apropos']['contenu'] ?? 'Chez ATLANTIS, nous ne nous contentons pas de traiter des appels. Nous representons votre marque aupres de vos clients et prospects avec professionnalisme, ecoute et engagement.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="values-grid">
                <article class="value-card reveal">
                    <div class="value-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
                    <h3>Ecoute</h3>
                    <p>Nous comprenons les besoins de vos clients avant de repondre.</p>
                </article>
                <article class="value-card reveal">
                    <div class="value-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
                    <h3>Reactivite</h3>
                    <p>Des agents formes et disponibles pour traiter vos demandes rapidement.</p>
                </article>
                <article class="value-card reveal">
                    <div class="value-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                    <h3>Qualite</h3>
                    <p>Un suivi rigoureux pour garantir l'excellence de chaque interaction.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- SERVICES -->
    <section id="services" class="section section-alt">
        <div class="container">
            <div class="section-head reveal">
                <span class="section-tag">Services</span>
                <h2 class="section-title"><?php echo htmlspecialchars($sections['services']['titre'] ?? 'Des solutions adaptees a vos objectifs', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="section-text"><?php echo htmlspecialchars($sections['services']['contenu'] ?? 'De la televente au support client, nous couvrons l\'ensemble de vos besoins en relation client.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="services-grid">
                <article class="service-card reveal">
                    <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg></div>
                    <h3>Televente</h3>
                    <p>Nos agents qualifies vendent vos produits et services avec professionalisme et empathie.</p>
                </article>
                <article class="service-card reveal">
                    <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                    <h3>Prospection</h3>
                    <p>Identification et contact proactif de prospects qualifies pour developper votre portefeuille.</p>
                </article>
                <article class="service-card reveal">
                    <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
                    <h3>Service apres-vente</h3>
                    <p>Support technique et gestion des reclamations pour maintenir la satisfaction client.</p>
                </article>
                <article class="service-card reveal">
                    <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
                    <h3>Relation client</h3>
                    <p>Prise en charge complete de vos clients pour fideliser et ameliorer leur experience.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- POURQUOI ATLANTIS -->
    <section id="pourquoi" class="section section-dark">
        <div class="container">
            <div class="section-head reveal">
                <span class="section-tag tag-light">Pourquoi ATLANTIS</span>
                <h2 class="section-title title-light"><?php echo htmlspecialchars($sections['pourquoi']['titre'] ?? 'Pourquoi choisir ATLANTIS ?', ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
            <div class="why-grid">
                <article class="why-card reveal">
                    <span class="why-number">01</span>
                    <h3>Expertise locale</h3>
                    <p>Une equipe bilingue basee a Yaounde, connaitant les realites du marche camerounais et africain.</p>
                </article>
                <article class="why-card reveal">
                    <span class="why-number">02</span>
                    <h3>Flexibilite</h3>
                    <p>Des solutions sur mesure adaptees a la taille et aux objectifs de votre entreprise.</p>
                </article>
                <article class="why-card reveal">
                    <span class="why-number">03</span>
                    <h3>Technologie</h3>
                    <p>Des outils modernes pour un suivi en temps reel et des rapports detailles.</p>
                </article>
                <article class="why-card reveal">
                    <span class="why-number">04</span>
                    <h3>Engagement</h3>
                    <p>Nous nous impliquons dans vos projets comme si c'etait les notres.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- CONTACT -->
    <section id="contact" class="section">
        <div class="container contact-container">
            <div class="contact-info reveal">
                <span class="section-tag">Contact</span>
                <h2 class="section-title"><?php echo htmlspecialchars($sections['contact']['titre'] ?? 'Travaillons ensemble', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="section-text"><?php echo htmlspecialchars($sections['contact']['contenu'] ?? 'Vous souhaitez developper votre activite avec ATLANTIS ou rejoindre notre equipe ? Remplissez le formulaire et notre equipe reviendra vers vous.', ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="section-text small-text">Vos informations sont utilisees uniquement pour traiter votre demande et vous recontacter.</p>
            </div>
            <div class="contact-form-wrap reveal">
                <form id="contactForm" class="contact-form" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom">Nom <span class="req">*</span></label>
                            <input type="text" id="nom" name="nom" placeholder="Votre nom" autocomplete="family-name">
                            <small class="field-error" id="nomError"></small>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prenom</label>
                            <input type="text" id="prenom" name="prenom" placeholder="Votre prenom" autocomplete="given-name">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telephone">Telephone <span class="req">*</span></label>
                            <input type="tel" id="telephone" name="telephone" placeholder="+237 6 XX XX XX XX" autocomplete="tel">
                            <small class="field-error" id="telephoneError"></small>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="vous@exemple.com" autocomplete="email">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="entreprise">Entreprise</label>
                            <input type="text" id="entreprise" name="entreprise" placeholder="Nom de votre entreprise">
                        </div>
                        <div class="form-group">
                            <label for="type">Type de demande <span class="req">*</span></label>
                            <select id="type" name="type">
                                <option value="">Choisir...</option>
                                <option value="partenariat">Partenariat</option>
                                <option value="recrutement">Recrutement</option>
                            </select>
                            <small class="field-error" id="typeError"></small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="4" placeholder="Decrivez brievement votre projet ou votre profil..."></textarea>
                    </div>
                    <div class="form-feedback" id="formFeedback" role="alert" hidden></div>
                    <button type="submit" class="btn btn-primary btn-block" id="submitBtn">Envoyer ma demande</button>
                </form>
            </div>
        </div>
    </section>

</main>

<footer class="footer">
    <div class="container footer-container">
        <div class="footer-brand">
            <a href="#accueil" class="logo logo-footer"><span class="logo-mark">A</span><span class="logo-text">ATLANTIS</span></a>
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

<button id="backToTop" class="back-to-top" aria-label="Retour en haut" title="Retour en haut">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>
<div id="notificationArea" class="notification-area" aria-live="polite"></div>

<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
