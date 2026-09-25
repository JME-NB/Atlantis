<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Editeur du formulaire de contact (landing)
 * ============================================================================
 * Permet de reordonner, afficher/masquer, renommer, rendre obligatoire,
 * editer le placeholder et les options des champs du formulaire de
 * candidature de la landing page. Les champs libres (custom_*) sont stockes
 * dans la table application_champs_personnalises a la soumission.
 *
 * Configuration sauvegardee dans site_settings.landing_form_fields (JSON).
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/admin_theme.php';

$readonly = !hasPermission(['csm', 'admin']);
$csrf = generateCsrfToken();

$fields = getLandingFormFields();

// Pour generer des identifiants custom_* uniques cote JS
$customCounter = 0;
foreach ($fields as $f) {
    $cle = (string)($f['cle'] ?? '');
    if (str_starts_with($cle, 'custom_')) {
        $n = (int) substr($cle, 7);
        if ($n > $customCounter) $customCounter = $n;
    }
}

$fieldTypeLabels = [
    'text'     => 'Texte',
    'tel'      => 'Telephone',
    'email'     => 'Email',
    'textarea' => 'Zone de texte',
    'select'   => 'Liste de choix',
    'file'     => 'Fichier (dossier)',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editer le formulaire - ATLANTIS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/assets/css/admin.css?v=19">
    <?php renderAdminFavicon(); ?>
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
                <h1>Editer le formulaire</h1>
            </div>
            <div class="header-right">
                <?php echo renderNotificationBellHtml(); ?>
                <a class="btn btn-outline" href="<?php echo BASE_URL; ?>/public/" target="_blank" rel="noopener">Voir la landing page</a>
                <?php if (!$readonly): ?>
                <button class="btn btn-primary" id="publishFormBtn">Publier le formulaire</button>
                <?php endif; ?>
            </div>
        </header>

        <div class="page-content">

            <div class="card">
                <div class="card-header">
                    <h2>Champs du formulaire</h2>
                </div>
                <div class="card-body">
                    <p class="info-text" style="margin-bottom:16px">
                        Reordonnez, masquez, renommez et configurez les champs du formulaire de contact de la landing page.
                        Les champs libres (« custom_* ») sont enregistres avec chaque candidature et visibles dans le detail admin.
                    </p>

                    <div id="fieldsList" class="form-fields-list">
                        <?php foreach ($fields as $i => $f): ?>
                        <?php $fCle = (string)($f['cle'] ?? ''); $fType = fieldType($f); $isCustom = str_starts_with($fCle, 'custom_'); ?>
                        <div class="field-row" data-cle="<?php echo htmlspecialchars($fCle, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="field-row-controls">
                                <button type="button" class="btn btn-sm btn-outline field-up" title="Monter" <?php echo $readonly ? 'disabled' : ''; ?>>&#9650;</button>
                                <button type="button" class="btn btn-sm btn-outline field-down" title="Descendre" <?php echo $readonly ? 'disabled' : ''; ?>>&#9660;</button>
                                <label class="toggle-switch" title="Visible / masque">
                                    <input type="checkbox" class="field-visible" <?php echo !empty($f['visible']) ? 'checked' : ''; ?> <?php echo $readonly ? 'disabled' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="field-row-fields">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Libelle</label>
                                        <input type="text" class="form-input field-label" value="<?php echo htmlspecialchars((string)($f['libelle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nom du champ" <?php echo $readonly ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="form-group">
                                        <label>Type</label>
                                        <?php if ($isCustom || $readonly): ?>
                                        <select class="form-select field-type" <?php echo $readonly ? 'disabled' : ''; ?>>
                                            <?php foreach ($fieldTypeLabels as $tv => $tl): ?>
                                            <option value="<?php echo $tv; ?>" <?php echo $fType === $tv ? 'selected' : ''; ?>><?php echo $tl; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php else: ?>
                                        <input type="text" class="form-input" value="<?php echo htmlspecialchars(($fieldTypeLabels[$fType] ?? $fType), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label>Placeholder</label>
                                        <input type="text" class="form-input field-placeholder" value="<?php echo htmlspecialchars((string)($f['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Texte indicatif" <?php echo $readonly ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="form-group">
                                        <label>Obligatoire</label>
                                        <label class="toggle-switch">
                                            <input type="checkbox" class="field-required" <?php echo !empty($f['obligatoire']) ? 'checked' : ''; ?> <?php echo $readonly ? 'disabled' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group field-options-group" <?php echo $fType === 'select' ? '' : 'hidden'; ?>>
                                    <label>Options (une par ligne)</label>
                                    <textarea class="form-textarea field-options" rows="3" placeholder="partenariat&#10;recrutement" <?php echo $readonly ? 'disabled' : ''; ?>><?php echo htmlspecialchars(implode("\n", (array)($f['options'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                            </div>
                            <div class="field-row-actions">
                                <?php if (!$readonly): ?>
                                <button type="button" class="btn btn-sm btn-danger field-remove">Supprimer</button>
                                <?php endif; ?>
                                <span class="badge badge-secondary field-cle"><?php echo htmlspecialchars($fCle, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!$readonly): ?>
                    <div class="form-fields-actions">
                        <button type="button" class="btn btn-outline" id="addFieldBtn">+ Ajouter un champ</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <footer class="app-footer">
            <span>&copy; <?php echo date('Y'); ?> ATLANTIS v2 &mdash; Tous droits reserves.</span>
        </footer>
    </main>

    <div id="toastContainer" class="toast-container"></div>

    <script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo $csrf; ?>';
    const READONLY = <?php echo $readonly ? 'true' : 'false'; ?>;
    const FIELD_CUSTOM_COUNTER = <?php echo (int) $customCounter; ?>;
    </script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/admin.js?v=14"></script>
    <script src="<?php echo BASE_URL; ?>/admin/assets/js/notifications.js?v=1"></script>
    <script>
    (function() {
        'use strict';

        function toast(message, isError) {
            var container = document.getElementById('toastContainer');
            if (!container) return;
            var t = document.createElement('div');
            t.className = 'toast' + (isError ? ' toast-error' : '');
            t.textContent = message;
            container.appendChild(t);
            setTimeout(function() { t.classList.add('show'); }, 10);
            setTimeout(function() {
                t.classList.remove('show');
                setTimeout(function() { t.remove(); }, 300);
            }, 3000);
        }

        function listEl() { return document.getElementById('fieldsList'); }

        function enquireVisuals() {
            ['field-visible', 'field-required', 'field-type', 'field-options'].forEach(function(cls) {
                document.querySelectorAll('.' + cls).forEach(function(el) {
                    el.addEventListener('change', function() {
                        var row = el.closest('.field-row');
                        if (row && (el.classList.contains('field-type') || el.classList.contains('field-options'))) {
                            toggleOptionsGroup(row);
                        }
                    });
                });
            });
        }

        function toggleOptionsGroup(row) {
            var sel = row.querySelector('.field-type');
            var grp = row.querySelector('.field-options-group');
            if (!grp) return;
            var type = sel ? sel.value : '';
            grp.hidden = type !== 'select';
        }

        function collectFields() {
            var rows = listEl().querySelectorAll('.field-row');
            var fields = [];
            var seen = {};
            rows.forEach(function(row) {
                var cle = row.dataset.cle || '';
                var typeEl = row.querySelector('.field-type');
                var type = typeEl ? typeEl.value : 'text';
                var libelle = row.querySelector('.field-label') ? row.querySelector('.field-label').value.trim() : '';
                var placeholder = row.querySelector('.field-placeholder') ? row.querySelector('.field-placeholder').value.trim() : '';
                var req = row.querySelector('.field-required') ? row.querySelector('.field-required').checked : false;
                var vis = row.querySelector('.field-visible') ? row.querySelector('.field-visible').checked : true;
                var options = [];
                if (type === 'select') {
                    var optsTxt = row.querySelector('.field-options') ? row.querySelector('.field-options').value : '';
                    optsTxt.split('\n').forEach(function(o) {
                        o = o.trim();
                        if (o !== '') options.push(o);
                    });
                }
                if (!cle) return;
                if (seen[cle]) return;
                seen[cle] = true;
                fields.push({
                    cle: cle,
                    type: type,
                    libelle: libelle || cle,
                    placeholder: placeholder,
                    obligatoire: !!req,
                    visible: !!vis,
                    options: options
                });
            });
            return fields;
        }

        function reorder(up) {
            var row = this.closest('.field-row');
            if (!row) return;
            var rows = Array.prototype.slice.call(listEl().querySelectorAll('.field-row'));
            var idx = rows.indexOf(row);
            if (up && idx > 0) rows[idx - 1].insertAdjacentElement('beforebegin', row);
            else if (!up && idx < rows.length - 1) rows[idx + 1].insertAdjacentElement('afterend', row);
        }

        document.querySelectorAll('.field-up').forEach(function(b) { b.addEventListener('click', function() { reorder.call(this, true); }); });
        document.querySelectorAll('.field-down').forEach(function(b) { b.addEventListener('click', function() { reorder.call(this, false); }); });

        document.querySelectorAll('.field-remove').forEach(function(b) {
            b.addEventListener('click', function() {
                if (!confirm('Supprimer ce champ du formulaire ?')) return;
                window.atlantisAnim.rowOut(this.closest('.field-row'));
            });
        });

        var addBtn = document.getElementById('addFieldBtn');
        if (addBtn) {
            addBtn.addEventListener('click', function() {
                var counter = window.FIELD_CUSTOM_COUNTER = (window.FIELD_CUSTOM_COUNTER || 0) + 1
                var cle = 'custom_' + counter;
                var div = document.createElement('div');
                div.className = 'field-row';
                div.dataset.cle = cle;
                div.innerHTML = '' +
                    '<div class="field-row-controls">' +
                        '<button type="button" class="btn btn-sm btn-outline field-up">&#9650;</button>' +
                        '<button type="button" class="btn btn-sm btn-outline field-down">&#9660;</button>' +
                        '<label class="toggle-switch"><input type="checkbox" class="field-visible" checked><span class="toggle-slider"></span></label>' +
                    '</div>' +
                    '<div class="field-row-fields">' +
                        '<div class="form-grid">' +
                            '<div class="form-group"><label>Libelle</label><input type="text" class="form-input field-label" placeholder="Nom du champ"></div>' +
                            '<div class="form-group"><label>Type</label><select class="form-select field-type">' +
                                '<option value="text">Texte</option><option value="email">Email</option><option value="tel">Telephone</option>' +
                                '<option value="textarea">Zone de texte</option><option value="select">Liste de choix</option>' +
                            '</select></div>' +
                            '<div class="form-group"><label>Placeholder</label><input type="text" class="form-input field-placeholder" placeholder="Texte indicatif"></div>' +
                            '<div class="form-group"><label>Obligatoire</label><label class="toggle-switch"><input type="checkbox" class="field-required"><span class="toggle-slider"></span></label></div>' +
                        '</div>' +
                        '<div class="form-group field-options-group" hidden><label>Options (une par ligne)</label>' +
                        '<textarea class="form-textarea field-options" rows="3" placeholder="Option 1"></textarea></div>' +
                    '</div>' +
                    '<div class="field-row-actions">' +
                        '<button type="button" class="btn btn-sm btn-danger field-remove">Supprimer</button>' +
                        '<span class="badge badge-secondary field-cle">' + cle + '</span>' +
                    '</div>';
                listEl().appendChild(div);
                if (window.atlantisAnim) window.atlantisAnim.rowIn(div);
                div.querySelector('.field-up').addEventListener('click', function() { reorder.call(this, true); });
                div.querySelector('.field-down').addEventListener('click', function() { reorder.call(this, false); });
                div.querySelector('.field-remove').addEventListener('click', function() {
                    if (!confirm('Supprimer ce champ du formulaire ?')) return;
                    window.atlantisAnim.rowOut(div);
                });
                div.querySelector('.field-type').addEventListener('change', function() { toggleOptionsGroup(div); });
            });
        }

        enquireVisuals();

        var publishBtn = document.getElementById('publishFormBtn');
        if (publishBtn) {
            publishBtn.addEventListener('click', function() {
                var fields = collectFields();
                if (fields.length === 0) { toast('Le formulaire doit contenir au moins un champ.', true); return; }

                var btn = this;
                btn.disabled = true;
                var fd = new FormData();
                fd.append('landing_form_fields', JSON.stringify(fields));

                fetch(BASE_URL + '/api/settings.php?action=update', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: fd
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) toast('Formulaire publie avec succes.');
                    else toast(data.message || 'Erreur lors de la publication.', true);
                })
                .catch(function() { toast('Erreur lors de la publication.', true); })
                .finally(function() { btn.disabled = false; });
            });
        }
    })();
    </script>
</body>
</html>