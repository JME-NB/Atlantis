# Plan d'exécution — C3 (Paramètres 4 onglets) & C4 (Utilisateurs Activer/Désactiver)

GO reçu (1 question répondue : menu utilisateur = flottant à droite de la sidebar sous ≤1024px).
C1 (menu utilisateur mobile, check) et C2 (scroll global sidebar) sont **déjà en place** dans
`atlantis-s` (BLOC 1 validé). Restent à implémenter C3 et C4.

## C3 — admin/settings.php → 4 onglets

Fichier : `admin/settings.php` (291 lignes actuellement, 3 cards empilées :
« Identite & Landing », « Page de connexion », « Interface admin »).

### 1. CSS des onglets (admin/assets/css/admin.css)
À vérifier / ajouter près du bloc `.user-menu` (l.612-633) :
- `.settings-tabs` : display flex, gap, border-bottom, margin-bottom.
- `.settings-tab-btn` : bouton inline, padding, transparent, border:none, bottom-border 3px
  transparent, cursor pointer, opacity .7. `.active` : bottom-border couleur primaire, opacity 1.
- `.settings-tab-panel` : display none ; `.active` : display block.
- Responsive ≤1024 : tabs scrollable horizontal (overflow-x:auto).

### 2. Restructuration HTML (admin/settings.php)
- Après `page-content`, insérer la barre d'onglets :
  `<nav class="settings-tabs" id="settingsTabs">` avec 4 boutons :
  1. Identifiants & mot de passe (data-panel="identifiants")
  2. Config (data-panel="config") — actif par défaut
  3. Infos utilisateur (data-panel="infos")
  4. Généraux (data-panel="generaux")
- Envelopper chaque groupe dans `<section class="settings-tab-panel" id="panel-identifiants">
  <div class="card">…` / `panel-config` (contenu des 3 cards actuelles), `panel-infos`,
  `panel-generaux`.
- Onglet **Identifiants & mot de passe** : héberge le contenu de `admin/preferences.php`
  (identifiant readonly, nom complet, 3 champs mdp) → migrate. Le bouton « Publier les
  changements » (#saveSettingsBtn) reste global pour l'onglet Config.
- Onglet **Infos utilisateur** : formulaire téléphone (obligatoire), email, adresse —
  endpoint → `api/users.php?action=update_infos` (C4).
- Onglet **Généraux** : cards de réglages transposées (ex : taille police admin déjà présente).
- `admin/sidebar.php` (~l.101) : retirer le lien « Préférences » du user-menu.
- `admin/preferences.php` : devient inutile → rediriger vers `settings.php#identifiants`
  (header("Location: settings.php")). JS des préférences (1465-1482) → migrer handlers.

### 3. JS (admin/assets/js/admin.js)
- `#settingsTabs` click delegation : bascule `.active` sur boutons + panels.
- Réutiliser `saveSettings` (1106-1145) pour l'onglet Config ; ajouter envoi
  `update_infos` pour l'onglet Infos.

## C4 — Utilisateurs : Activer / Désactiver + infos

### 1. Migration base (`sql/schema.sql` + ALTER)
Table `users` : a déjà `telephone VARCHAR(30) NOT NULL`, `email VARCHAR(150) DEFAULT NULL`,
`statut_compte ENUM('actif','desactive') NOT NULL DEFAULT 'actif'`.
- **Manque `adresse`** → `ALTER TABLE users ADD COLUMN adresse VARCHAR(255) DEFAULT NULL AFTER email;`
- À rejouer sur `atlantis_v2` (ALTER + INSERT INTO site_infos) + mettre à jour schema.sql.

### 2. API (`api/users.php`)
- Action `update_statut` : POST `{ user_id, statut: 'actif'|'desactive' }`.
  - Gardes : ne pas permettre de désactiver **soi-même** ; protéger le super-admin
    (statut_compte readonly si niveau='super_admin'); CSRF check.
  - SQL : `UPDATE users SET statut_compte = ? WHERE id = ?`.
- Action `update_infos` : POST `{ user_id, telephone (obligatoire → 400 si vide), email, adresse }`.
  - CSRF check ; garde rôle.

### 3. UI (`admin/utilisateurs.php`)
- Colonnes : ajouter Téléphone, Email, Adresse (provenant du SELECT élargi).
- Bouton par ligne : « Désactiver » si `statut_compte='actif'` (rouge/secondaire), sinon
  « Activer » (primaire/succès) — MASQUÉ pour soi-même et super-admin.
- JS (`admin.js`) : `toggleUserStatut(id, btn)` → confirm() → api update_statut → refresh ;
  formulaires infos inline ou modale → update_infos.

### 4. Validations
- `php -l admin/settings.php admin/preferences.php admin/utilisateurs.php api/users.php`
- `node --check admin/assets/js/admin.js`
- Tests HTTP (via curl) : update_statut (soi-même → refusé ; autre → ok) ; update_infos
  (telephone vide → 400 ; ok sinon) ; CSRF invalide → 403.

## Ordre
1. C3 : CSS onglets → settings.php restructuré → sidebar lien Préférences → preferences.php
   redirection → JS onglets + saveSettings vérif.
2. C4 : ALTER adresse + schema.sql → api/users.php (update_statut, update_infos, SELECT élargi)
   → utilisateurs.php boutons+colonnes → admin.js handlers.
3. Validation complète (php -l, node --check, tests HTTP curl).
