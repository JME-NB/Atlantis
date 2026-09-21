# Coquille d'administration fixe - ATLANTIS v2

Documentation technique de la coquille (shell) de l'espace d'administration.

## 1. Vue d'ensemble

L'espace d'administration ATLANTIS utilise une coquille fixe :

- **Sidebar** fixe a gauche (pleine hauteur, menu defilant en interne).
- **Header** fixe en haut de la fenetre.
- **Footer** fixe en bas de la fenetre.
- **Contenu** (`page-content`) qui defile entre le header et le footer.

L'implementation repose sur `position: fixed` (relatif a la fenetre), ce qui
garantit un comportement identique sur desktop, navigation mobile reelle et
**emulation device** des DevTools. `position: sticky` a ete abandonne car il
ne tient pas dans certaines fenetres mises a l'echelle par l'emulateur.

## 2. Structure HTML (identique sur toutes les pages admin)

```html
<body class="admin-body">
    <aside class="sidebar" id="sidebar">          <!-- include sidebar.php -->
        <div class="sidebar-header">              <!-- logo -->
        <nav class="sidebar-nav">                 <!-- menu, scroll interne -->
        <div class="sidebar-footer">              <!-- utilisateur + deconnexion -->
    </aside>

    <main class="main-content">
        <header class="page-header">              <!-- hamburger + titre + actions -->
        <div class="page-content">                <!-- contenu scrollable -->
            ...contenu de la page...
        </div>
        <footer class="app-footer">               <!-- copyright -->
    </main>

    <div class="modal-overlay">...</div>          <!-- modales (optionnel) -->
    <div id="toastContainer" class="toast-container"></div>
</body>
```

Pages concernees (15) : `candidatures`, `enattente`, `encours`, `acceptees`,
`refusees`, `archivees`, `dashboard`, `utilisateurs`, `settings`, `design`,
`design_form`, `logs`, `audit`, `preferences`, `change_password`.

## 3. Regles CSS cles (`admin/assets/css/admin.css`)

| Selecteur       | Ligne ~ | Role |
|---|---|---|
| `.admin-body`   | 447 | `display:flex; min-height:100vh` + variables shell |
| `.main-content` | 455 | `margin-left` sidebar, `min-height:100vh`, padding header/footer |
| `.sidebar`      | 466 | `position:fixed; top/bottom:0; width:280px` |
| `.page-header`  | 704 | `position:fixed; top:0; left:sidebar; right:0; z-index:60` |
| `.app-footer`   | 758 | `position:fixed; bottom:0; left:sidebar; right:0; z-index:60` |

### Variables de hauteur (source unique)

```css
.admin-body {
    --shell-header-h: 84px;   /* hauteur du header (desktop) */
    --shell-footer-h: 52px;   /* hauteur du footer */
}
```

Le `padding-top` / `padding-bottom` de `.main-content` reserve l'espace :

```css
.main-content {
    padding-top: var(--shell-header-h);
    padding-bottom: var(--shell-footer-h);
}
```

> Si le header ou le footer change de hauteur (echelle de police, design),
> ajuster uniquement ces deux variables.

## 4. Hauteurs mesurees

| Element | Valeur |
|---|---|
| Header desktop (padding 20px x2 + contenu ~41px) | ~82 px |
| Header mobile <= 1024 px (padding 16px x2) | ~74 px |
| Footer (padding 14px x2 + texte 0.85rem) | ~49 px |

## 5. Breakpoints responsive

### <= 1024 px (mode mobile de la coquille)

- `.sidebar` -> `translateX(-100%)` (masquee, ouverte via `.sidebar.open`).
- `.main-content { margin-left: 0; padding-top: 76px; }`.
- `.page-header, .app-footer { left: 0; }` (pleine largeur).
- `.hamburger-admin` affiche.

### <= 480 px

- `.notif-panel { top: 76px; }` (ajuste pour passer sous le header fixe).
- `.header-date` masque, stats en 1 colonne.

## 6. Empilement (z-index)

| Element | z-index |
|---|---|
| Header / Footer | 60 |
| Sidebar | 100 |
| Menu utilisateur sidebar | 200 |
| `.modal-overlay` (modales) | 1000 |
| `.confirm-overlay` (popup de confirmation) | 2000 |
| Toasts | 10000 |

Les modales et toasts recouvrent donc toujours le header/footer.

## 7. Ajouter une nouvelle page admin

1. `<body class="admin-body">`.
2. `<?php include __DIR__ . '/sidebar.php'; ?>`.
3. `<main class="main-content">` avec :
   - `<header class="page-header">` (hamburger `#hamburgerAdmin`, `<h1>`,
     `.header-right`),
   - `<div class="page-content">` pour le contenu,
   - `<footer class="app-footer">...</footer>` **avant** `</main>`.
4. Lier le CSS : `admin/assets/css/admin.css?v=16` (voir section 8).
5. Charger `admin.js?v=8` et `notifications.js?v=1` en bas de page.

## 8. Cache navigateur

Le CSS est versionne par querystring : `?v=N`.

- Modifier `admin.css` -> **incrementer `v=` sur les pages** (rechercher toutes
  les occurrences `admin.css?v=`) pour forcer le rechargement.
- Version actuelle : `v=16`.
- En dev, `Ctrl+F5` est necessaire cote navigateur.

## 9. Points de vigilance

- **Ne pas utiliser `position: sticky`** pour la coquille (casse en emulation
  device) ; utiliser `fixed` + variables de hauteur.
- Le contenu doit conserver une marge en bas (padding du `.main-content`) sinon
  le footer fixe recouvre la fin du contenu.
- Si l'echelle de police admin est modifiee (reglage Design), verifier le
  chevauchement header/contenu.
- L'overlay de la sidebar mobile (`.sidebar-overlay`) reste sous les modales
  (z-index 100 < 1000).

## 10. Fonctionnalites livrees dans la meme session

- **Suivi des candidatures** : 6 vues (`candidatures` = "Toutes", `enattente`,
  `encours`, `acceptees`, `refusees`, `archivees`) avec tri par en-tetes, statuts
  `valide`/`refuse` verrouilles, archivage avec `statut_precedent`.
- **API `api/applications.php`** : `list`, `update_status`, `delete` (soft),
  `restore` (`retour=precedent|attente`).
- **Popups de confirmation** (`showConfirmDialog`) avec animation et boutons
  themes.
- **Modales** : header/footer fixes, corps defilant.

## 11. Sauvegarde / rollback (git)

- Point de rollback avant la coquille fixe : branche **`backup-avant-shell-fixe`**
  (sur `b0bda16`).
- Restaurer : `git checkout -- admin/` ou `git reset --hard backup-avant-shell-fixe`.
- Supprimer ensuite la branche : `git branch -D backup-avant-shell-fixe`.