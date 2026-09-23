/* ============================================================================
   ATLANTIS v2 - JavaScript de l'espace d'administration
   ============================================================================ */

(function() {
    'use strict';

    // Garde anti double-chargement : si le script est inclus 2 fois dans une
    // meme page, on ne rebinde pas les listeners (sinon menus/toggles se ferment
    // aussitot qu'ils s'ouvrent).
    if (document.documentElement.hasAttribute('data-admin-js-loaded')) return;
    document.documentElement.setAttribute('data-admin-js-loaded', '1');

    // =========================================================================
    // SIDEBAR MOBILE
    // =========================================================================
    var hamburgerAdmin = document.getElementById('hamburgerAdmin');
    var sidebar = document.getElementById('sidebar');
    var sidebarOverlay = document.getElementById('sidebarOverlay');

    if (hamburgerAdmin && sidebar) {
        hamburgerAdmin.addEventListener('click', function() {
            var isOpen = sidebar.classList.toggle('open');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('active');
            if (!isOpen && userBtn && userMenu && closeUserMenu) closeUserMenu();
        });
    }
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
        });
    }

    // Sous-menu "Suivi des candidatures" : bascule au clic (mobile/tactile)
    document.querySelectorAll('.has-subnav > [data-toggle-subnav]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var item = link.parentElement;
            var isOpen = item.classList.toggle('open');
            link.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });

    // =========================================================================
    // MENU UTILISATEUR (sidebar-user -> dropdown)
    // =========================================================================
    var userBtn = document.getElementById('sidebarUserBtn');
    var userMenu = document.getElementById('userMenu');

    if (userBtn && userMenu) {
        var closeUserMenu = function() {
            userMenu.hidden = true;
            userBtn.setAttribute('aria-expanded', 'false');
        };

        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var hidden = userMenu.hidden;
            closeUserMenu();
            if (hidden) {
                userMenu.hidden = false;
                userBtn.setAttribute('aria-expanded', 'true');
                repositionUserMenu();
            }
        });

        userMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        document.addEventListener('click', function() {
            closeUserMenu();
        });

        function repositionUserMenu() {
            if (userMenu.hidden) return;

            // Mobile / tablette : menu flottant rendu juste au-dessus du bouton
            // utilisateur (la sidebar etant ouverte au moment du clic).
            if (window.innerWidth <= 1024) {
                var btnRect = userBtn.getBoundingClientRect();
                var menuH = userMenu.offsetHeight;
                userMenu.style.width = btnRect.width + 'px';
                userMenu.style.left = btnRect.left + 'px';
                userMenu.style.top = Math.max(8, Math.round(btnRect.top - menuH - 8)) + 'px';
                userMenu.style.bottom = 'auto';
                return;
            }

            var btnRect = userBtn.getBoundingClientRect();
            userMenu.style.left = btnRect.left + btnRect.width + 8 + 'px';
            if (btnRect.bottom + userMenu.offsetHeight > window.innerHeight) {
                userMenu.style.top = 'auto';
                userMenu.style.bottom = '70px';
            } else {
                userMenu.style.top = btnRect.top + 'px';
                userMenu.style.bottom = 'auto';
            }
        }

        userBtn.addEventListener('mouseenter', repositionUserMenu);
        window.addEventListener('resize', repositionUserMenu);
        window.addEventListener('scroll', repositionUserMenu, { capture: true, passive: true });
    }

    // =========================================================================
    // LOGIN
    // =========================================================================
    var loginForm = document.getElementById('loginForm');
    if (loginForm) {

        // Anti pre-remplissage automatique du navigateur (sans casser la sauvegarde) :
        // champs en readonly au chargement -> vides ; readonly retire au premier focus.
        ['identifiant', 'mot_de_passe'].forEach(function(id) {
            var field = document.getElementById(id);
            if (!field) return;
            field.readOnly = true;
            field.value = '';
            field.addEventListener('focus', function() {
                field.removeAttribute('readonly');
            });
        });

        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.textContent = 'Connexion...';

            var fd = new FormData(loginForm);
            fetch(BASE_URL + '/api/auth.php?action=login', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.must_change_password) {
                        window.location.href = BASE_URL + '/admin/change_password.php';
                    } else {
                        window.location.href = BASE_URL + '/admin/dashboard.php';
                    }
                } else {
                    showFeedback('loginFeedback', data.message, 'error');
                }
            })
            .catch(function() { showFeedback('loginFeedback', 'Erreur de connexion.', 'error'); })
            .finally(function() { btn.disabled = false; btn.textContent = 'Se connecter'; });
        });
    }

    // =========================================================================
    // CHANGE PASSWORD (force)
    // =========================================================================
    var changePwForm = document.getElementById('changePasswordForm');
    if (changePwForm && !changePwForm.closest('.card-body')) {
        // Page change_password.php (pas preferences)
        changePwForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('changeBtn');
            btn.disabled = true;
            btn.textContent = 'Changement...';

            var fd = new FormData(changePwForm);
            fd.append('force', '1');
            fetch(BASE_URL + '/api/auth.php?action=change_password', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.href = BASE_URL + '/admin/dashboard.php';
                } else {
                    showFeedback('changeFeedback', data.message, 'error');
                }
            })
            .catch(function() { showFeedback('changeFeedback', 'Erreur.', 'error'); })
            .finally(function() { btn.disabled = false; btn.textContent = 'Changer le mot de passe'; });
        });
    }

    // =========================================================================
    // APPLICATIONS - LISTING
    // =========================================================================
    var applicationsBody = document.getElementById('applicationsBody');
    if (applicationsBody) {
        var currentApplicationsPage = 1;
        var searchTimeout = null;
        var currentSort = 'created_at';
        var currentSortDir = 'desc';

        function loadApplications(page) {
            currentApplicationsPage = page || 1;
            var params = new URLSearchParams();
            params.set('action', 'list');
            params.set('page', currentApplicationsPage);

            var search = document.getElementById('searchInput');
            var typeF = document.getElementById('typeFilter');
            var statutF = document.getElementById('statutFilter');

            if (search && search.value) params.set('search', search.value);
            if (typeF && typeF.value) params.set('type', typeF.value);

            // FILTER_STATUT = perimetre fixe de la page (ex. "tout sauf archives"),
            // le dropdown statut (page "Toutes") permet d'affiner dessous.
            var st = null;
            if (statutF && statutF.value) {
                st = statutF.value;
            } else if (typeof FILTER_STATUT !== 'undefined' && FILTER_STATUT) {
                st = FILTER_STATUT;
            }
            if (st) params.set('statut', st);

            params.set('sort', currentSort);
            params.set('dir', currentSortDir);

            fetch(BASE_URL + '/api/applications.php?' + params.toString())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    renderApplicationsTable(data.items, data.total, data.page, data.pages);
                }
            });
        }

        // Tri par en-tete de colonne (ascendant / descendant)
        var sortHeaders = Array.prototype.slice.call(document.querySelectorAll('#applicationsTable thead th[data-sort]'));
        sortHeaders.forEach(function(th) {
            if (th.dataset.sort === currentSort) {
                th.classList.add('active-sort');
                if (currentSortDir === 'desc') th.classList.add('sort-desc');
            }
            th.addEventListener('click', function() {
                var col = this.dataset.sort;
                if (currentSort === col) {
                    currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort = col;
                    currentSortDir = 'asc';
                }
                renderSortIndicators();
                loadApplications(1);
            });
        });

        function renderSortIndicators() {
            sortHeaders.forEach(function(th) {
                th.classList.toggle('active-sort', th.dataset.sort === currentSort);
                th.classList.toggle('sort-desc', th.dataset.sort === currentSort && currentSortDir === 'desc');
            });
        }

        var isArchiveListing = typeof FILTER_STATUT !== 'undefined' && FILTER_STATUT === 'archive';

        function renderApplicationsTable(items, total, page, pages) {
            if (!items || items.length === 0) {
                applicationsBody.innerHTML = '<tr><td colspan="10" class="text-center">Aucune demande trouvee.</td></tr>';
                document.getElementById('pagination').innerHTML = '';
                return;
            }

            var html = '';
            items.forEach(function(app) {
                html += '<tr>';
                html += '<td>#' + app.id + '</td>';
                html += '<td>' + escHtml(app.nom) + '</td>';
                html += '<td>' + escHtml(app.prenom || '-') + '</td>';
                html += '<td>' + escHtml(app.telephone) + '</td>';
                html += '<td>' + escHtml(app.entreprise || '-') + '</td>';
                html += '<td><span class="badge badge-' + typeClass(app.type) + '">' + escHtml(typeLabel(app.type)) + '</span></td>';
                html += '<td><span class="badge badge-' + statutClass(app.statut) + '">' + escHtml(statutLabel(app.statut)) + '</span></td>';
                html += '<td>' + (parseInt(app.pieces_count, 10) > 0
                    ? '<button class="btn btn-sm btn-outline detail-btn" data-id="' + safeId(app.id) + '">' + app.pieces_count + ' fichier(s)</button>'
                    : '<span class="text-muted">-</span>') + '</td>';
                html += '<td>' + formatDate(app.created_at) + '</td>';
                html += '<td>';
                html += '<button class="btn btn-sm btn-outline detail-btn" data-id="' + safeId(app.id) + '">Voir</button> ';
                if (isArchiveListing) {
                    html += '<button class="btn btn-sm btn-outline restore-row-btn" data-id="' + safeId(app.id) + '" data-nom="' + escHtml(app.nom) + '">Restaurer</button> ';
                    html += '<button class="btn btn-sm btn-danger permdel-row-btn" data-id="' + safeId(app.id) + '" data-nom="' + escHtml(app.nom) + '">Supprimer definitivement</button>';
                } else {
                    html += '<button class="btn btn-sm btn-danger delete-btn" data-id="' + safeId(app.id) + '" data-nom="' + escHtml(app.nom) + '">X</button>';
                }
                html += '</td>';
                html += '</tr>';
            });
            applicationsBody.innerHTML = html;

            // Pagination
            var pag = document.getElementById('pagination');
            if (pag && pages > 1) {
                var ph = '';
                for (var i = 1; i <= pages; i++) {
                    ph += '<button class="' + (i === page ? 'active' : '') + '" onclick="window._loadApps(' + i + ')">' + i + '</button>';
                }
                pag.innerHTML = ph;
            } else if (pag) {
                pag.innerHTML = '';
            }

            // Event listeners
            applicationsBody.querySelectorAll('.detail-btn').forEach(function(btn) {
                btn.addEventListener('click', function() { openDetailModal(this.dataset.id); });
            });
            applicationsBody.querySelectorAll('.delete-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var btnEl = this;
                    showConfirmDialog({
                        title: 'Archiver cette demande ?',
                        message: 'La demande de ' + btnEl.dataset.nom + ' ne sera plus visible dans le suivi et passera dans les archives. Les donnees et les fichiers seront conserves. Seul un administrateur pourra la restaurer ou la supprimer definitivement.',
                        okLabel: 'Archiver',
                        okType: 'danger',
                        buttons: [{ label: 'Annuler', type: 'outline', ok: null }],
                        ok: function() { deleteApplication(btnEl.dataset.id); }
                    });
                });
            });
            applicationsBody.querySelectorAll('.restore-row-btn').forEach(function(btn) {
                btn.addEventListener('click', function() { openRestoreConfirm(this.dataset.id, 'la demande de ' + this.dataset.nom); });
            });
            applicationsBody.querySelectorAll('.permdel-row-btn').forEach(function(btn) {
                btn.addEventListener('click', function() { openPermanentDeleteConfirm(this.dataset.id, 'la demande de ' + this.dataset.nom); });
            });
        }

        window._loadApps = function(p) { loadApplications(p); };

        // Filters
        var searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() { loadApplications(1); }, 300);
            });
        }
        var typeFilter = document.getElementById('typeFilter');
        var statutFilter = document.getElementById('statutFilter');
        if (typeFilter) typeFilter.addEventListener('change', function() { loadApplications(1); });
        if (statutFilter) statutFilter.addEventListener('change', function() { loadApplications(1); });

        loadApplications(1);
    }

    // =========================================================================
    // APPLICATION DETAIL MODAL
    // =========================================================================
    function openDetailModal(id) {
        fetch(BASE_URL + '/api/applications.php?action=detail&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            var app = data.item;
            document.getElementById('modalId').textContent = app.id;

            var html = '';
            html += detailField('Nom', escHtml(app.nom));
            html += detailField('Prenom', escHtml(app.prenom || '-'));
            html += detailField('Telephone', escHtml(app.telephone));
            html += detailField('Email', escHtml(app.email || '-'));
            html += detailField('Entreprise', escHtml(app.entreprise || '-'));
            html += detailField('Type', '<span class="badge badge-' + typeClass(app.type) + '">' + escHtml(typeLabel(app.type)) + '</span>');
            html += detailField('Statut actuel', '<span class="badge badge-' + statutClass(app.statut) + '">' + escHtml(statutLabel(app.statut)) + '</span>');
            if (data.champs_personnalises && data.champs_personnalises.length) {
                data.champs_personnalises.forEach(function(cp) {
                    html += detailField(escHtml(cp.libelle || cp.champ), escHtml(cp.valeur || '-'));
                });
            }
            html += detailField('Message', escHtml(app.message || '-'));
            html += detailField('Dossier de candidature', piecesDetailHtml(data.pieces));
            html += detailField('Cree le', formatDate(app.created_at));
            html += detailField('Modifie le', formatDate(app.updated_at));
            document.getElementById('modalBody').innerHTML = html;

            // Footer : actions selon la page (archivees / terminees / suivi)
            var footerHtml = '';
            var isArchivePage = typeof FILTER_STATUT !== 'undefined' && FILTER_STATUT === 'archive';
            var isTerminatedPage = isArchivePage
                ? false
                : (typeof FILTER_STATUT !== 'undefined' && (FILTER_STATUT === 'valide' || FILTER_STATUT === 'refuse'));

            if (isArchivePage) {
                footerHtml = '<div style="display:flex;gap:8px;align-items:center;width:100%">';
                footerHtml += '<button class="btn btn-primary" id="restoreBtn" data-id="' + safeId(app.id) + '">Restaurer</button>';
                footerHtml += '<button class="btn btn-danger" id="permanentDeleteBtn" data-id="' + safeId(app.id) + '">Supprimer definitivement</button>';
                footerHtml += '</div>';
            } else if (isTerminatedPage) {
                footerHtml = '<div style="display:flex;gap:8px;align-items:center;width:100%">';
                footerHtml += '<span class="text-muted" style="flex:1;font-size:.85rem">Cette candidature est '
                    + (FILTER_STATUT === 'valide' ? 'acceptee' : 'refusee')
                    + ', elle ne peut plus changer de statut.</span>';
                footerHtml += '<button class="btn btn-danger" id="archiveModalBtn" data-id="' + safeId(app.id) + '">Archiver</button>';
                footerHtml += '</div>';
            } else {
                var statuses = ['en_attente', 'en_cours', 'valide', 'refuse'];
                footerHtml = '<div style="display:flex;gap:8px;align-items:center;width:100%">';
                footerHtml += '<select id="modalStatus" class="form-select" style="flex:1">';
                statuses.forEach(function(s) {
                    footerHtml += '<option value="' + s + '"' + (app.statut === s ? ' selected' : '') + '>' + statutLabel(s) + '</option>';
                });
                footerHtml += '</select>';
                footerHtml += '<button class="btn btn-primary" id="saveStatusBtn" data-id="' + safeId(app.id) + '">Mettre a jour</button>';
                footerHtml += '</div>';
            }
            document.getElementById('modalFooter').innerHTML = footerHtml;

            document.getElementById('detailModal').hidden = false;

            if (isArchivePage) {
                var restoreBtn = document.getElementById('restoreBtn');
                var permDelBtn = document.getElementById('permanentDeleteBtn');
                if (restoreBtn) restoreBtn.addEventListener('click', function() {
                    openRestoreConfirm(this.dataset.id, 'cette demande');
                });
                if (permDelBtn) permDelBtn.addEventListener('click', function() {
                    openPermanentDeleteConfirm(this.dataset.id, 'cette demande');
                });
            } else if (isTerminatedPage) {
                document.getElementById('archiveModalBtn').addEventListener('click', function() {
                    var btnEl = this;
                    showConfirmDialog({
                        title: 'Archiver cette demande ?',
                        message: 'Cette candidature passe dans les archives. Elle n\u2019apparaitra plus dans le suivi. Seul un administrateur pourra la restaurer ou la supprimer definitivement.',
                        okLabel: 'Archiver',
                        okType: 'danger',
                        buttons: [{ label: 'Annuler', type: 'outline', ok: null }],
                        ok: function() { deleteApplication(btnEl.dataset.id); }
                    });
                });
            } else {
                document.getElementById('saveStatusBtn').addEventListener('click', function() {
                    var newStatut = document.getElementById('modalStatus').value;
                    if (newStatut === app.statut) {
                        document.getElementById('detailModal').hidden = true;
                        return;
                    }
                    updateStatus(this.dataset.id, newStatut);
                });
            }
        });
    }

    function detailField(label, value) {
        return '<div class="detail-field"><span class="detail-label">' + label + '</span><div class="detail-value">' + value + '</div></div>';
    }

    // Close modal
    document.addEventListener('click', function(e) {
        if (e.target.id === 'closeModal' || e.target.id === 'detailModal') {
            document.getElementById('detailModal').hidden = true;
        }
        if (e.target.id === 'closeCreateModal' || e.target.id === 'cancelCreateBtn' || e.target.id === 'createModal') {
            var cm = document.getElementById('createModal');
            if (cm) cm.hidden = true;
        }
        if (e.target.id === 'closeRoleModal' || e.target.id === 'cancelRoleBtn' || e.target.id === 'roleModal') {
            var rm = document.getElementById('roleModal');
            if (rm) rm.hidden = true;
        }
    });

    // =========================================================================
    // UPDATE STATUS
    // =========================================================================
    function updateStatus(id, statut) {
        var fd = new FormData();
        fd.append('statut', statut);
        fetch(BASE_URL + '/api/applications.php?action=update_status&id=' + id, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('Statut mis a jour.');
                document.getElementById('detailModal').hidden = true;
                if (typeof loadApplications === 'function' || typeof _loadApps === 'function') {
                    _loadApps(currentApplicationsPage || 1);
                }
            } else {
                showToast(data.message, true);
            }
        });
    }

    // =========================================================================
    // DELETE APPLICATION
    // =========================================================================
    function deleteApplication(id) {
        fetch(BASE_URL + '/api/applications.php?action=delete&id=' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('Demande supprimee.');
                _loadApps(currentApplicationsPage || 1);
            } else {
                showToast(data.message, true);
            }
        });
    }

    // =========================================================================
    // RESTORE APPLICATION (from archivees)
    // retour : "precedent" -> statut d'avant archivage | "attente" -> en_attente
    // =========================================================================
    function restoreApplication(id, retour) {
        var fd = new FormData();
        fd.append('retour', retour || 'attente');
        fetch(BASE_URL + '/api/applications.php?action=restore&id=' + id, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('Demande restauree.');
                document.getElementById('detailModal').hidden = true;
                _loadApps(currentApplicationsPage || 1);
            } else {
                showToast(data.message, true);
            }
        });
    }

    // =========================================================================
    // PERMANENT DELETE APPLICATION (from archivees)
    // =========================================================================
    function permanentDeleteApplication(id) {
        fetch(BASE_URL + '/api/applications.php?action=permanent_delete&id=' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('Demande supprimee definitivement.');
                document.getElementById('detailModal').hidden = true;
                _loadApps(currentApplicationsPage || 1);
            } else {
                showToast(data.message, true);
            }
        });
    }

    // =========================================================================
    // POPUP DE CONFIRMATION (respecte le theme via les variables CSS)
    // =========================================================================
    function showConfirmDialog(opts) {
        var title    = opts.title || 'Confirmation';
        var message  = opts.message || '';
        var okLabel  = opts.okLabel || 'OK';
        var okType   = opts.okType || 'danger'; // danger | primary | outline
        var onOk     = opts.ok || null;
        var extra    = opts.buttons || [];

        var overlay = document.createElement('div');
        overlay.className = 'modal-overlay confirm-overlay';

        var dialog = document.createElement('div');
        dialog.className = 'modal modal-sm confirm-modal';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');

        var iconColor = okType === 'danger' ? 'var(--danger)' : 'var(--secondary)';
        var iconBg    = okType === 'danger' ? '#fee2e2' : '#ccfbf1';
        var iconPath  = okType === 'danger'
            ? '<path d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>'
            : '<path d="M9 12l2 2 4-4m5.62-4A11 11 0 1 1 12 3a11 11 0 0 1 9.62 5z"/>';

        dialog.innerHTML =
            '<div class="modal-header">'
            + '<h2><span class="confirm-icon" style="background:' + iconBg + ';color:' + iconColor + '">'
            + '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + iconPath + '</svg>'
            + '</span><span>' + escHtml(title) + '</span></h2>'
            + '<button type="button" class="modal-close" aria-label="Fermer">&times;</button>'
            + '</div>'
            + '<div class="modal-body"><p>' + escHtml(message).replace(/\n/g, '<br>') + '</p></div>'
            + '<div class="modal-footer"><div class="confirm-buttons"></div></div>';

        overlay.appendChild(dialog);
        document.body.appendChild(overlay);

        var btnContainer = dialog.querySelector('.confirm-buttons');

        function close() {
            if (overlay.classList.contains('is-closing')) return;
            document.removeEventListener('keydown', onKey);
            overlay.classList.add('is-closing');
            setTimeout(function() { overlay.remove(); }, 180);
        }

        function onKey(e) {
            if (e.key === 'Escape') close();
        }
        document.addEventListener('keydown', onKey);

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) close();
        });
        dialog.querySelector('.modal-close').addEventListener('click', close);

        function addButton(label, type, cb) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn ' + (type === 'primary' ? 'btn-primary' : type === 'danger' ? 'btn-danger' : 'btn-outline');
            b.textContent = label;
            b.addEventListener('click', function() {
                close();
                if (cb) cb();
            });
            btnContainer.appendChild(b);
            return b;
        }

        extra.forEach(function(eb) {
            addButton(eb.label, eb.type, eb.ok);
        });
        addButton(okLabel, okType, onOk);

        // Focus initial : le bouton "Annuler" si present, sinon le premier
        var annulerBtn = null;
        btnContainer.querySelectorAll('button').forEach(function(b) {
            if (b.textContent.toLowerCase().indexOf('annuler') !== -1) annulerBtn = b;
        });
        var first = annulerBtn || btnContainer.querySelector('button');
        if (first) first.focus();
    }

    function openRestoreConfirm(id, mention) {
        showConfirmDialog({
            title: 'Restaurer la Candidature #' + id,
            message: 'Voulez-vous restaurer ' + mention + '  au statut précédent ?\n'
                ,
            okLabel: 'Annuler',
            okType: 'outline',
            buttons: [
                { label: 'Oui', type: 'primary', ok: function() { restoreApplication(id, 'precedent'); } },
                { label: 'Non', type: 'outline', ok: function() { restoreApplication(id, 'attente'); } }
            ]
        });
    }

    function openPermanentDeleteConfirm(id, nom) {
        showConfirmDialog({
            title: 'Suppression definitive',
            message: 'Supprimer definitivement la demande de ' + nom + ' ?\n'
                + 'Cette action est irreversible : toutes les donnees ainsi que les fichiers joints seront effaces de la base de donnees.',
            okLabel: 'Supprimer definitivement',
            okType: 'danger',
            buttons: [{ label: 'Annuler', type: 'outline', ok: null }],
            ok: function() { permanentDeleteApplication(id); }
        });
    }

    // =========================================================================
    // USERS - LISTING
    // =========================================================================
    var usersBody = document.getElementById('usersBody');
    if (usersBody) {
        loadUsers();

        function loadUsers() {
            fetch(BASE_URL + '/api/users.php?action=list')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) renderUsersTable(data.items);
            });
        }

        function renderUsersTable(users) {
            if (!users || users.length === 0) {
                usersBody.innerHTML = '<tr><td colspan="8" class="text-center">Aucun utilisateur.</td></tr>';
                return;
            }

            var html = '';
            users.forEach(function(u) {
                if (u.deleted_at) return; // Skip deleted
                html += '<tr>';
                html += '<td>#' + u.id + '</td>';
                html += '<td>' + escHtml(u.identifiant) + '</td>';
                html += '<td>' + escHtml(u.nom_complet) + '</td>';
                html += '<td><span class="badge badge-' + roleBadge(u.role) + '">' + escHtml(u.role) + '</span></td>';
                html += '<td>' + (u.is_super_admin ? '<span class="badge badge-success">Oui</span>' : '-') + '</td>';
                html += '<td><span class="badge badge-' + (u.statut_compte === 'actif' ? 'success' : 'danger') + '">' + u.statut_compte + '</span></td>';
                html += '<td>' + (u.must_change_password ? '<span class="badge badge-warning">Oui</span>' : '-') + '</td>';
                html += '<td>';

                if (!u.is_super_admin) {
                    html += '<button class="btn btn-sm btn-outline role-btn" data-id="' + safeId(u.id) + '" data-name="' + escHtml(u.identifiant) + '" data-role="' + escHtml(u.role) + '">Role</button> ';
                    html += '<button class="btn btn-sm btn-outline reset-btn" data-id="' + safeId(u.id) + '" data-name="' + escHtml(u.identifiant) + '">Mdp</button> ';
                    html += '<button class="btn btn-sm btn-danger delete-user-btn" data-id="' + safeId(u.id) + '" data-name="' + escHtml(u.identifiant) + '">X</button>';
                } else {
                    html += '<em style="color:var(--text-muted)">Super Admin</em>';
                }

                html += '</td></tr>';
            });
            usersBody.innerHTML = html;

            // Event listeners
            usersBody.querySelectorAll('.role-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    openRoleModal(this.dataset.id, this.dataset.name, this.dataset.role);
                });
            });
            usersBody.querySelectorAll('.reset-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (confirm('Reinitialiser le mot de passe de ' + this.dataset.name + ' ? Le nouveau mot de passe sera "1234".')) {
                        resetPassword(this.dataset.id);
                    }
                });
            });
            usersBody.querySelectorAll('.delete-user-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (confirm('Supprimer le compte ' + this.dataset.name + ' ? Cette action est irreversible.')) {
                        deleteUser(this.dataset.id);
                    }
                });
            });
        }

        window._loadUsers = loadUsers;
    }

    // --- CREATE USER ---
    var createUserBtn = document.getElementById('createUserBtn');
    var createUserForm = document.getElementById('createUserForm');
    var confirmCreateBtn = document.getElementById('confirmCreateBtn');

    if (createUserBtn) {
        createUserBtn.addEventListener('click', function() {
            document.getElementById('createModal').hidden = false;
        });
    }

    if (confirmCreateBtn) {
        confirmCreateBtn.addEventListener('click', function() {
            var fd = new FormData(createUserForm);
            var btn = this;
            btn.disabled = true;

            fetch(BASE_URL + '/api/users.php?action=create', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('Compte cree avec succes.');
                    document.getElementById('createModal').hidden = true;
                    createUserForm.reset();
                    if (typeof _loadUsers === 'function') _loadUsers();
                } else {
                    showToast(data.message, true);
                    if (data.errors) {
                        Object.keys(data.errors).forEach(function(k) {
                            var small = createUserForm.querySelector('[name="' + k + '"]');
                            if (small) {
                                var errEl = small.parentElement.querySelector('.field-error');
                                if (errEl) errEl.textContent = data.errors[k];
                            }
                        });
                    }
                }
            })
            .finally(function() { btn.disabled = false; });
        });
    }

    // --- ROLE MODAL ---
    function openRoleModal(userId, name, currentRole) {
        document.getElementById('roleUserName').textContent = name;
        document.getElementById('newRoleSelect').value = currentRole;
        document.getElementById('roleModal').hidden = false;
        document.getElementById('confirmRoleBtn').dataset.id = userId;
    }

    var confirmRoleBtn = document.getElementById('confirmRoleBtn');
    if (confirmRoleBtn) {
        confirmRoleBtn.addEventListener('click', function() {
            var userId = this.dataset.id;
            var newRole = document.getElementById('newRoleSelect').value;

            var fd = new FormData();
            fd.append('user_id', parseInt(userId, 10));
            fd.append('role', newRole);

            fetch(BASE_URL + '/api/users.php?action=update_role', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('Role mis a jour.');
                    document.getElementById('roleModal').hidden = true;
                    if (typeof _loadUsers === 'function') _loadUsers();
                } else {
                    showToast(data.message, true);
                }
            });
        });
    }

    // --- RESET PASSWORD ---
    function resetPassword(userId) {
        var fd = new FormData();
        fd.append('user_id', userId);

        fetch(BASE_URL + '/api/users.php?action=reset_password', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            showToast(data.success ? 'Mot de passe reinitialise.' : data.message, !data.success);
        });
    }

    // --- DELETE USER ---
    function deleteUser(userId) {
        fetch(BASE_URL + '/api/users.php?action=delete&id=' + userId, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('Compte desactive.');
                if (typeof _loadUsers === 'function') _loadUsers();
            } else {
                showToast(data.message, true);
            }
        });
    }

    // =========================================================================
    // AUDIT - LISTING
    // =========================================================================
    var auditBody = document.getElementById('auditBody');
    if (auditBody) {
        var currentAuditPage = 1;
        var currentSort = 'created_at';
        var currentSortDir = 'desc';

        function loadAudit(page) {
            currentAuditPage = page || 1;
            var params = new URLSearchParams();
            params.set('action', 'list');
            params.set('page', currentAuditPage);

            var vals = {
                search: document.getElementById('auditSearch'),
                actionF: document.getElementById('auditActionFilter'),
                userF: document.getElementById('auditUserFilter'),
                roleF: document.getElementById('auditRoleFilter'),
                dateDeb: document.getElementById('auditDateDeb'),
                dateFin: document.getElementById('auditDateFin')
            };
            if (vals.search && vals.search.value) params.set('search', vals.search.value);
            if (vals.actionF && vals.actionF.value) params.set('action_filter', vals.actionF.value);
            if (vals.userF && vals.userF.value) params.set('user_id', vals.userF.value);
            if (vals.roleF && vals.roleF.value) params.set('role', vals.roleF.value);
            if (vals.dateDeb && vals.dateDeb.value) params.set('date_debut', vals.dateDeb.value);
            if (vals.dateFin && vals.dateFin.value) params.set('date_fin', vals.dateFin.value);

            params.set('sort', currentSort);
            params.set('dir', currentSortDir.toUpperCase());

            fetch(BASE_URL + '/api/audit.php?' + params.toString())
            .then(function(r) {
                if (r.status === 401 || r.status === 403) { handleUnauthorized(); return null; }
                return r.json();
            })
            .then(function(data) {
                if (data && data.success) renderAuditTable(data.items, data.page, data.pages);
            });
        }

        function renderAuditTable(items, page, pages) {
            if (!items || items.length === 0) {
                auditBody.innerHTML = '<tr><td colspan="7" class="text-center">Aucune entree trouvee.</td></tr>';
                var pagEmpty = document.getElementById('auditPagination');
                if (pagEmpty) pagEmpty.innerHTML = '';
                return;
            }

            var html = '';
            items.forEach(function(log) {
                html += '<tr>';
                html += '<td>' + formatDate(log.created_at) + '</td>';
                html += '<td>' + escHtml(log.identifiant_snapshot || '-') + '</td>';
                html += '<td>' + escHtml(log.role_snapshot || '-') + '</td>';
                html += '<td><span class="badge badge-' + actionBadge(log.action) + '">' + escHtml(log.action) + '</span></td>';
                html += '<td>' + escHtml(log.details || '-') + '</td>';
                html += '<td>' + escHtml(log.adresse_ip || '-') + '</td>';
                html += '<td><button class="btn btn-sm btn-outline audit-detail-btn" data-id="' + safeId(log.id) + '">Voir</button></td>';
                html += '</tr>';
            });
            auditBody.innerHTML = html;

            auditBody.querySelectorAll('.audit-detail-btn').forEach(function(btn) {
                btn.addEventListener('click', function() { openAuditDetail(this.dataset.id); });
            });

            var pag = document.getElementById('auditPagination');
            if (pag && pages > 1) {
                var ph = '';
                for (var i = 1; i <= pages; i++) {
                    ph += '<button class="' + (i === page ? 'active' : '') + '" onclick="window._loadAudit(' + i + ')">' + i + '</button>';
                }
                pag.innerHTML = ph;
            } else if (pag) {
                pag.innerHTML = '';
            }
        }

        // Tri par en-tete de colonne (ascendant / descendant)
        var auditSortHeaders = Array.prototype.slice.call(document.querySelectorAll('#auditTable thead th[data-sort]'));
        auditSortHeaders.forEach(function(th) {
            if (th.dataset.sort === currentSort) {
                th.classList.add('active-sort');
                if (currentSortDir === 'desc') th.classList.add('sort-desc');
            }
            th.addEventListener('click', function() {
                var col = this.dataset.sort;
                if (currentSort === col) {
                    currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort = col;
                    currentSortDir = 'asc';
                }
                renderAuditSortIndicators();
                loadAudit(1);
            });
        });

        function renderAuditSortIndicators() {
            auditSortHeaders.forEach(function(th) {
                th.classList.toggle('active-sort', th.dataset.sort === currentSort);
                th.classList.toggle('sort-desc', th.dataset.sort === currentSort && currentSortDir === 'desc');
            });
        }

        window._loadAudit = function(p) { loadAudit(p); };

        var auditSelectFilters = ['auditActionFilter', 'auditUserFilter', 'auditRoleFilter', 'auditDateDeb', 'auditDateFin'];
        auditSelectFilters.forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', function() { loadAudit(1); });
        });

        var auditSearch = document.getElementById('auditSearch');
        if (auditSearch) {
            var auditTimeout;
            auditSearch.addEventListener('input', function() {
                clearTimeout(auditTimeout);
                auditTimeout = setTimeout(function() { loadAudit(1); }, 300);
            });
        }

        loadAudit(1);
    }

    // =========================================================================
    // AUDIT - DETAIL MODAL
    // =========================================================================
    function openAuditDetail(id) {
        var modal = document.getElementById('auditDetailModal');
        var body  = document.getElementById('auditDetailBody');
        if (!modal || !body) return;

        body.innerHTML = '<p style="text-align:center;color:var(--text-muted)">Chargement...</p>';
        modal.hidden = false;

        fetch(BASE_URL + '/api/audit.php?action=details&id=' + id)
        .then(function(r) {
            if (r.status === 401 || r.status === 403) { handleUnauthorized(); return null; }
            return r.json();
        })
        .then(function(data) {
            if (!data) return;
            if (!data.success) {
                body.innerHTML = '<p style="color:var(--danger)">' + escHtml(data.message) + '</p>';
                return;
            }

            var log    = data.log;
            var entity = data.entity;
            var pieces = data.pieces || [];
            var champs = data.champs || [];

            var html = '';

            html += '<div class="detail-field"><span class="detail-label">Date</span><div class="detail-value">' + formatDate(log.created_at) + '</div></div>';
            html += '<div class="detail-field"><span class="detail-label">Utilisateur</span><div class="detail-value">' + escHtml(log.identifiant_snapshot) + ' <span class="text-muted">(' + escHtml(log.role_snapshot) + ')</span></div></div>';
            html += '<div class="detail-field"><span class="detail-label">Action</span><div class="detail-value"><span class="badge badge-' + actionBadge(log.action) + '">' + escHtml(log.action) + '</span></div></div>';
            html += '<div class="detail-field"><span class="detail-label">Adresse IP</span><div class="detail-value">' + escHtml(log.adresse_ip || '-') + '</div></div>';
            if (log.details) {
                html += '<div class="detail-field"><span class="detail-label">Details</span><div class="detail-value">' + escHtml(log.details) + '</div></div>';
            }

            html += '<hr style="margin:12px 0;border-color:var(--border)">';

            if (log.cible_type && log.cible_id) {
                html += '<div class="detail-field"><span class="detail-label">Entite</span><div class="detail-value">' + escHtml(log.cible_type) + ' #' + log.cible_id + '</div></div>';
            } else if (log.cible_type) {
                html += '<div class="detail-field"><span class="detail-label">Entite</span><div class="detail-value">' + escHtml(log.cible_type) + '</div></div>';
            }

            if (log.cible_type === 'application' && entity && typeof entity === 'object' && !Array.isArray(entity)) {
                html += '<div class="detail-field"><span class="detail-label">Nom</span><div class="detail-value">' + escHtml(entity.nom) + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Prenom</span><div class="detail-value">' + escHtml(entity.prenom || '-') + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Telephone</span><div class="detail-value">' + escHtml(entity.telephone) + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Email</span><div class="detail-value">' + escHtml(entity.email || '-') + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Entreprise</span><div class="detail-value">' + escHtml(entity.entreprise || '-') + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Type</span><div class="detail-value"><span class="badge badge-' + typeClass(entity.type) + '">' + escHtml(typeLabel(entity.type)) + '</span></div></div>';
                html += '<div class="detail-field"><span class="detail-label">Statut</span><div class="detail-value"><span class="badge badge-' + statutClass(entity.statut) + '">' + escHtml(statutLabel(entity.statut)) + '</span></div></div>';
                html += '<div class="detail-field"><span class="detail-label">Message</span><div class="detail-value">' + escHtml(entity.message || '-') + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Cree le</span><div class="detail-value">' + formatDate(entity.created_at) + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Modifie le</span><div class="detail-value">' + formatDate(entity.updated_at) + '</div></div>';

                if (champs && champs.length) {
                    champs.forEach(function(cp) {
                        html += '<div class="detail-field"><span class="detail-label">' + escHtml(cp.libelle || cp.champ) + '</span><div class="detail-value">' + escHtml(cp.valeur || '-') + '</div></div>';
                    });
                }
                if (pieces && pieces.length) {
                    html += '<div class="detail-field"><span class="detail-label">Fichiers</span><div class="detail-value">' + piecesDetailHtml(pieces) + '</div></div>';
                }
            } else if (log.cible_type === 'user' && entity && typeof entity === 'object') {
                html += '<div class="detail-field"><span class="detail-label">Identifiant</span><div class="detail-value">' + escHtml(entity.identifiant) + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Nom complet</span><div class="detail-value">' + escHtml(entity.nom_complet) + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Role</span><div class="detail-value">' + escHtml(entity.role) + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Super Admin</span><div class="detail-value">' + (entity.is_super_admin ? 'Oui' : 'Non') + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Statut</span><div class="detail-value">' + escHtml(entity.statut_compte) + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Doit changer mdp</span><div class="detail-value">' + (entity.must_change_password ? 'Oui' : 'Non') + '</div></div>';
                html += '<div class="detail-field"><span class="detail-label">Cree le</span><div class="detail-value">' + formatDate(entity.created_at) + '</div></div>';
            } else if (log.cible_type === 'settings' && Array.isArray(entity)) {
                entity.forEach(function(row) {
                    html += '<div class="detail-field"><span class="detail-label">' + escHtml(row.cle) + '</span><div class="detail-value" style="word-break:break-all">' + escHtml(row.valeur || '-') + '</div></div>';
                });
            } else if (entity === null && log.cible_type) {
                html += '<p class="text-muted" style="margin-top:8px">Entite introuvable ou supprimee.</p>';
            }

            body.innerHTML = html;
        })
        .catch(function() {
            body.innerHTML = '<p style="color:var(--danger)">Erreur lors du chargement.</p>';
        });
    }

    document.addEventListener('click', function(e) {
        if (e.target.id === 'closeAuditModal' || e.target.id === 'auditDetailModal') {
            var am = document.getElementById('auditDetailModal');
            if (am) am.hidden = true;
        }
    });

    // =========================================================================
    // DESIGN - ONGLETS (communs aux deux grappes design-tabs)
    // =========================================================================
    function switchDesignTab(tab) {
        document.querySelectorAll('.design-tab').forEach(function(b) {
            b.classList.toggle('active', b.dataset.tab === tab);
        });
        document.querySelectorAll('.design-panel').forEach(function(p) {
            p.hidden = p.dataset.panel !== tab;
        });
        document.querySelectorAll('.designs-list').forEach(function(l) {
            l.hidden = l.dataset.cible !== tab;
        });
    }

    document.querySelectorAll('.design-tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            switchDesignTab(this.dataset.tab);
        });
    });

    function activeDesignTab() {
        var active = document.querySelector('.design-tab-main .design-tab.active, .design-tabs .design-tab.active');
        return active ? active.dataset.tab : 'landing';
    }

    // =========================================================================
    // DESIGN - PUBLICATION des changements (settings + sections + rubriques)
    // =========================================================================
    var publishBtn = document.getElementById('publishBtn');
    if (publishBtn) {
        publishBtn.addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;

            fetch(BASE_URL + '/api/settings.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify(collectSettings())
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) throw new Error(data.message || 'Erreur');
                return fetch(BASE_URL + '/api/settings.php?action=update_sections', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({ sections: collectSections() })
                }).then(function(r) { return r.json(); });
            })
            .then(function(data) {
                if (data.success) showToast('Changements publies sur le site.');
                else showToast(data.message, true);
            })
            .catch(function() { showToast('Erreur lors de la publication.', true); })
            .finally(function() { btn.disabled = false; });
        });
    }

    function collectSettings() {
        var settings = {};
        document.querySelectorAll('[data-setting]').forEach(function(el) {
            if (el.type === 'color') return;
            settings[el.dataset.setting] = el.value;
        });
        return settings;
    }

    function collectSections() {
        var sections = [];
        document.querySelectorAll('.section-editor').forEach(function(el) {
            var rubriques = [];
            el.querySelectorAll('.rubrique-row').forEach(function(row) {
                rubriques.push({
                    icone: fieldVal(row.querySelector('.rub-icone')),
                    titre: fieldVal(row.querySelector('.rub-titre')),
                    contenu: fieldVal(row.querySelector('.rub-contenu')),
                    image_url: fieldVal(row.querySelector('.rub-url')),
                    fond_couleur: nullIfEmpty(fieldVal(row.querySelector('.rub-fond'))),
                    texte_couleur: nullIfEmpty(fieldVal(row.querySelector('.rub-texte'))),
                    visible: toBool(row.querySelector('.rub-visible').checked)
                });
            });
            sections.push({
                id: parseInt(el.dataset.id, 10) || 0,
                section_key: el.dataset.key || '',
                titre: fieldVal(el.querySelector('.section-titre')),
                contenu: fieldVal(el.querySelector('.section-contenu')),
                image_url: fieldVal(el.querySelector('.section-url')),
                fond_couleur: nullIfEmpty(fieldVal(el.querySelector('.section-fond'))),
                texte_couleur: nullIfEmpty(fieldVal(el.querySelector('.section-texte'))),
                fond_image_url: fieldVal(el.querySelector('.section-bg-url')),
                titre_taille: fieldVal(el.querySelector('.section-taille')),
                ordre: parseInt(fieldVal(el.querySelector('.section-ordre')), 10) || 0,
                visible: toBool(el.querySelector('.section-visible').checked)
            });
        });
        return sections;
    }

    function fieldVal(el) { return el ? el.value : ''; }
    function nullIfEmpty(v) { return v === '' ? null : v; }
    function toBool(v) { return v === 'false' ? false : !!v; }

    // =========================================================================
    // DESIGN - SAUVEGARDE d'un design (capture de l'onglet actif)
    // =========================================================================
    var saveDesignBtn = document.getElementById('saveDesignBtn');
    if (saveDesignBtn) {
        saveDesignBtn.addEventListener('click', function() {
            var nameInput = document.getElementById('designName');
            var descInput = document.getElementById('designDescription');
            var nom = nameInput ? nameInput.value : '';
            var description = descInput ? descInput.value : '';
            if (!nom) { showToast('Indiquez un nom pour le design.', true); return; }

            var tab = activeDesignTab();
            var config = { settings: collectPanelSettings(tab) };
            if (tab === 'landing') config.sections = collectSections();

            var btn = this;
            btn.disabled = true;
            fetch(BASE_URL + '/api/designs.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({
                    id: typeof EDITING_DESIGN_ID !== 'undefined' ? EDITING_DESIGN_ID : 0,
                    cible: tab,
                    nom: nom,
                    description: description,
                    configuration: config
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('Design enregistre.');
                    setTimeout(function() {
                        if (data.id) window.location.href = BASE_URL + '/admin/design.php?design=' + data.id + '&tab=' + tab;
                        else window.location.reload();
                    }, 500);
                } else {
                    showToast(data.message, true);
                }
            })
            .catch(function() { showToast('Erreur lors de la sauvegarde.', true); })
            .finally(function() { btn.disabled = false; });
        });
    }

    function collectPanelSettings(cible) {
        var panel = document.querySelector('.design-panel[data-panel="' + cible + '"]');
        if (!panel) return {};
        var settings = {};
        panel.querySelectorAll('[data-setting]').forEach(function(el) {
            if (el.type === 'color') return;
            settings[el.dataset.setting] = el.value;
        });
        return settings;
    }

    // =========================================================================
    // DESIGN - ACTIONS sur les designs (activer / dupliquer / supprimer)
    // =========================================================================
    document.querySelectorAll('.design-activate').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Activer ce design ? Son contenu remplacera la configuration actuelle du site.')) return;
            designAction('activate', this.dataset.id, this);
        });
    });
    document.querySelectorAll('.design-duplicate').forEach(function(btn) {
        btn.addEventListener('click', function() { designAction('duplicate', this.dataset.id, this); });
    });
    document.querySelectorAll('.design-delete').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Supprimer ce design ? Cette action est irreversible.')) return;
            designAction('delete', this.dataset.id, this);
        });
    });

    function designAction(action, id, btn) {
        var fd = new FormData();
        fd.append('id', id);
        btn.disabled = true;
        fetch(BASE_URL + '/api/designs.php?action=' + action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('Design ' + action + ' avec succes.');
                setTimeout(function() { window.location.reload(); }, 600);
            } else {
                showToast(data.message, true);
                btn.disabled = false;
            }
        })
        .catch(function() { showToast('Erreur.', true); btn.disabled = false; });
    }

    // =========================================================================
    // DESIGN - PREVIEW (lien signe genere cote serveur dans design.php)
    // Le bouton #previewBtn est un vrai lien (ou desactive) ; aucune logique ici.
    // =========================================================================

    // =========================================================================
    // DESIGN - UPLOAD IMAGES (logo, banniere, login_bg, section, section_bg, rubrique)
    // =========================================================================
    document.querySelectorAll('.upload-input:not(.rub-upload)').forEach(function(input) {
        input.addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;

            var type = this.dataset.uploadType;
            var sectionId = this.dataset.sectionId || '';
            var fd = new FormData();
            fd.append('image', file);
            fd.append('type', type);
            if (sectionId) fd.append('section_id', sectionId);
            // Pour les sections chargees depuis un design (id=0), on passe la cle
            // afin de retrouver la bonne section en base au moment de la publication.
            if (!sectionId || sectionId === '0') {
                var sec = this.closest('.section-editor');
                if (sec && sec.dataset.key) fd.append('section_key', sec.dataset.key);
            }

            var btn = this;
            var originalText = btn.value;
            btn.disabled = true;

            fetch(BASE_URL + '/api/upload.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (type === 'rubrique') {
                        var row = btn.closest('.rubrique-fields');
                        var urlInput = row ? row.querySelector('.rub-url') : null;
                        if (urlInput) { urlInput.value = data.url; showToast('Image de rubrique ajoutee au lien.'); }
                    } else if (type === 'section_bg') {
                        var sec2 = btn.closest('.section-editor');
                        var bgInput = sec2 ? sec2.querySelector('.section-bg-url') : null;
                        if (bgInput) { bgInput.value = data.url; showToast('Image de fond ajoutee au lien.'); }
                    } else {
                        showToast('Image upload avec succes.');
                        setTimeout(function() { window.location.reload(); }, 600);
                    }
                } else {
                    showToast(data.message, true);
                }
            })
            .catch(function() {
                showToast('Erreur lors de l\'upload.', true);
            })
            .finally(function() {
                btn.disabled = false;
                btn.value = originalText;
            });
        });
    });

    // Remove uploaded image
    document.querySelectorAll('.upload-remove').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = this.dataset.uploadType;
            var sectionId = this.dataset.sectionId || '';
            var fd = new FormData();
            fd.append('type', type);
            fd.append('remove', '1');
            if (sectionId) fd.append('section_id', sectionId);

            fetch(BASE_URL + '/api/upload.php?action=remove', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('Image supprimee.');
                    setTimeout(function() { window.location.reload(); }, 600);
                } else {
                    showToast(data.message, true);
                }
            });
        });
    });

    // =========================================================================
    // SETTINGS - PAGE PARAMETRES GLOBAUX
    // =========================================================================
    var saveSettingsBtn = document.getElementById('saveSettingsBtn');
    if (saveSettingsBtn) {
        saveSettingsBtn.addEventListener('click', function() {
            var payload = {};
            document.querySelectorAll('[data-setting]').forEach(function(input) {
                payload[input.dataset.setting] = input.value;
            });

            var btn = saveSettingsBtn;
            btn.disabled = true;
            btn.textContent = 'Publication...';

            fetch(BASE_URL + '/api/settings.php?action=update', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('Parametres mis a jour avec succes.');
                } else {
                    showToast(data.message, true);
                }
            })
            .catch(function() {
                showToast('Erreur lors de la sauvegarde.', true);
            })
            .finally(function() {
                btn.disabled = false;
                btn.textContent = 'Publier les changements';
            });
        });
    }

    // =========================================================================
    // DESIGN - SYNCHRO des pickers de couleur (sections + rubriques)
    // =========================================================================
    function syncColorGroup(picker, textInput) {
        if (!picker || !textInput) return;
        picker.addEventListener('input', function() { textInput.value = this.value; });
        textInput.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) picker.value = this.value;
            else picker.value = '#000000';
        });
    }

    document.querySelectorAll('.section-editor').forEach(function(sec) {
        syncColorGroup(sec.querySelector('.sect-fond-color'), sec.querySelector('.section-fond'));
        syncColorGroup(sec.querySelector('.sect-texte-color'), sec.querySelector('.section-texte'));
        sec.querySelectorAll('.rubrique-row').forEach(function(row) {
            syncColorGroup(row.querySelector('.rub-fond-color'), row.querySelector('.rub-fond'));
            syncColorGroup(row.querySelector('.rub-texte-color'), row.querySelector('.rub-texte'));
        });
    });

    // =========================================================================
    // DESIGN - RUBRIQUES : ajout / montee / descente / suppression
    // =========================================================================
    function rubriqueRowTemplate() {
        var icons = [
            ['ecoute', 'Ecoute / Cœur'],
            ['reactivite', 'Reactivite / Horloge'],
            ['qualite', 'Qualite / Bouclier'],
            ['televente', 'Televente / Telephone'],
            ['prospection', 'Prospection / Equipe'],
            ['apres_vente', 'Apres-vente / Protection'],
            ['relation', 'Relation / Message'],
            ['telecom', 'Telecom / Antenne']
        ];
        var opts = '<option value="">-- (nombre / aucune) --</option>';
        icons.forEach(function(ic) {
            opts += '<option value="' + ic[0] + '">' + ic[1] + '</option>';
        });
        return '<div class="rubrique-row">'
            + '<div class="rubrique-controls">'
            + '<button type="button" class="btn btn-sm btn-outline rub-up" title="Monter">&#9650;</button>'
            + '<button type="button" class="btn btn-sm btn-outline rub-down" title="Descendre">&#9660;</button>'
            + '</div>'
            + '<div class="rubrique-fields">'
            + '<div class="form-grid">'
            + '<div class="form-group"><label>Icone (choix parmi 8)</label>'
            + '<select class="form-select rub-icone">' + opts + '</select></div>'
            + '<div class="form-group"><label>Visible</label>'
            + '<label class="toggle-switch"><input type="checkbox" class="rub-visible" checked><span class="toggle-slider"></span></label>'
            + '</div></div>'
            + '<div class="form-grid">'
            + '<div class="form-group"><label>Titre</label>'
            + '<input type="text" class="form-input rub-titre" placeholder="Titre de la rubrique"></div>'
            + '<div class="form-group"><label>Image (lien ou upload)</label>'
            + '<div class="upload-url-row">'
            + '<input type="text" class="form-input rub-url" placeholder="https://...">'
            + '<input type="file" class="upload-input rub-upload" data-upload-type="rubrique" accept="image/png,image/jpeg,image/webp,image/gif">'
            + '</div></div></div>'
            + '<div class="form-group"><label>Contenu</label>'
            + '<textarea class="form-textarea rub-contenu" rows="2" placeholder="Description courte"></textarea></div>'
            + '<div class="form-grid">'
            + '<div class="form-group"><label>Fond de la carte (couleur)</label>'
            + '<div class="color-input-group">'
            + '<input type="color" class="rub-fond-color" value="#000000"><input type="text" class="form-input rub-fond" placeholder="auto">'
            + '</div></div>'
            + '<div class="form-group"><label>Texte de la carte (couleur)</label>'
            + '<div class="color-input-group">'
            + '<input type="color" class="rub-texte-color" value="#000000"><input type="text" class="form-input rub-texte" placeholder="auto">'
            + '</div></div></div>'
            + '</div>'
            + '<button type="button" class="btn btn-sm btn-danger rub-delete">Supprimer</button>'
            + '</div>';
    }

    function bindRubriqueRow(row) {
        var up = row.querySelector('.rub-up');
        var down = row.querySelector('.rub-down');
        var del = row.querySelector('.rub-delete');
        if (up) up.addEventListener('click', function() {
            var prev = row.previousElementSibling;
            if (prev) row.parentNode.insertBefore(row, prev);
        });
        if (down) down.addEventListener('click', function() {
            var next = row.nextElementSibling;
            if (next) row.parentNode.insertBefore(next, row);
        });
        if (del) del.addEventListener('click', function() { row.remove(); });

        var upload = row.querySelector('.rub-upload');
        if (upload) upload.addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;
            var fd = new FormData();
            fd.append('image', file);
            fd.append('type', 'rubrique');
            var btn = this;
            btn.disabled = true;
            fetch(BASE_URL + '/api/upload.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var urlInput = row.querySelector('.rub-url');
                    if (urlInput) { urlInput.value = data.url; showToast('Image de rubrique ajoutee au lien.'); }
                } else showToast(data.message, true);
            })
            .catch(function() { showToast('Erreur lors de l\'upload.', true); })
            .finally(function() { btn.disabled = false; btn.value = ''; });
        });

        syncColorGroup(row.querySelector('.rub-fond-color'), row.querySelector('.rub-fond'));
        syncColorGroup(row.querySelector('.rub-texte-color'), row.querySelector('.rub-texte'));
    }

    document.querySelectorAll('.rubrique-add').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var list = btn.closest('.rubriques-block').querySelector('.rubriques-list');
            var temp = document.createElement('div');
            temp.innerHTML = rubriqueRowTemplate();
            var row = temp.firstChild;
            list.appendChild(row);
            bindRubriqueRow(row);
        });
    });

    document.querySelectorAll('.rubrique-row').forEach(bindRubriqueRow);

    // =========================================================================
    // DESIGN - SLIDER du voile de connexion (login_bg_fondu)
    // =========================================================================
    var fonduRange = document.getElementById('login_bg_fondu');
    var fonduHidden = document.getElementById('login_bg_fondu_hidden');
    var fonduLabel = document.getElementById('loginFonduValue');
    function syncFondu() {
        if (fonduHidden) fonduHidden.value = fonduRange.value;
        if (fonduLabel) fonduLabel.textContent = fonduRange.value + '%';
    }
    if (fonduRange) {
        fonduRange.addEventListener('input', syncFondu);
        syncFondu();
    }

    // =========================================================================
    // DESIGN - DRAG & DROP des sections
    // =========================================================================
    var sectionsList = document.getElementById('sectionsList');
    if (sectionsList && typeof READONLY !== 'undefined' && !READONLY) {
        var dragItem = null;

        sectionsList.querySelectorAll('.section-editor').forEach(function(item) {
            var handle = item.querySelector('.section-drag-handle');
            handle.draggable = true;
            handle.style.cursor = 'grab';
            handle.addEventListener('dragstart', function(e) {
                dragItem = item;
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            handle.addEventListener('dragend', function() {
                item.classList.remove('dragging');
                dragItem = null;
                updateSectionOrder();
            });
        });

        sectionsList.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (!dragItem) return;
            var afterElement = getDragAfterElement(sectionsList, e.clientY);
            if (afterElement == null) {
                sectionsList.appendChild(dragItem);
            } else {
                sectionsList.insertBefore(dragItem, afterElement);
            }
        });

        function getDragAfterElement(container, y) {
            var els = Array.prototype.slice.call(container.querySelectorAll('.section-editor:not(.dragging)'));
            return els.reduce(function(closest, child) {
                var box = child.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                }
                return closest;
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        function updateSectionOrder() {
            sectionsList.querySelectorAll('.section-editor').forEach(function(el, idx) {
                var orderInput = el.querySelector('.section-ordre');
                var orderLabel = el.querySelector('.section-order');
                orderInput.value = idx + 1;
                if (orderLabel) orderLabel.textContent = 'Ordre: ' + (idx + 1);
            });
        }
    }

    // =========================================================================
    // DESIGN - ACCORDION des sections
    // =========================================================================
    document.querySelectorAll('.section-editor-header').forEach(function(header) {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function(e) {
            if (e.target.closest('.toggle-switch') || e.target.closest('.section-drag-handle')) return;
            var editor = this.closest('.section-editor');
            var body = editor.querySelector('.section-editor-body');
            body.hidden = !body.hidden;
        });
    });

    // =========================================================================
    // PREFERENCES FORMS
    // =========================================================================

    // =========================================================================
    // ERROR LOGS - LISTING
    // =========================================================================
    var logsBody = document.getElementById('logsBody');
    if (logsBody) {
        var currentLogsPage = 1;

        function loadLogs(page) {
            currentLogsPage = page || 1;
            var params = new URLSearchParams();
            params.set('action', 'list');
            params.set('page', currentLogsPage);

            var search = document.getElementById('logsSearch');
            var niveauF = document.getElementById('logsNiveauFilter');
            if (search && search.value) params.set('search', search.value);
            if (niveauF && niveauF.value) params.set('niveau', niveauF.value);

            fetch(BASE_URL + '/api/logs.php?' + params.toString())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) renderLogsTable(data.items, data.total, data.page, data.pages);
            });
        }

        function renderLogsTable(items, total, page, pages) {
            if (!items || items.length === 0) {
                logsBody.innerHTML = '<tr><td colspan="7" class="text-center">Aucune erreur enregistree.</td></tr>';
                document.getElementById('logsPagination').innerHTML = '';
                return;
            }

            var html = '';
            items.forEach(function(log) {
                var niveauClass = log.niveau === 'ERROR' ? 'danger' : log.niveau === 'WARNING' ? 'warning' : 'info';
                html += '<tr>';
                html += '<td>' + formatDate(log.created_at) + '</td>';
                html += '<td><span class="badge badge-' + niveauClass + '">' + escHtml(log.niveau) + '</span></td>';
                html += '<td style="max-width:350px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escHtml(log.message) + '">' + escHtml(log.message) + '</td>';
                html += '<td><span class="text-muted">' + escHtml(log.fichier || '-') + ':' + log.ligne + '</span></td>';
                html += '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escHtml(log.url || '') + '">' + escHtml(log.url || '-') + '</td>';
                html += '<td>' + (log.user_id ? '#' + log.user_id : '<span class="text-muted">-</span>') + '</td>';
                html += '<td><button class="btn btn-sm btn-danger delete-log-btn" data-id="' + safeId(log.id) + '">X</button></td>';
                html += '</tr>';
            });
            logsBody.innerHTML = html;

            var pag = document.getElementById('logsPagination');
            if (pag && pages > 1) {
                var ph = '';
                for (var i = 1; i <= pages; i++) {
                    ph += '<button class="' + (i === page ? 'active' : '') + '" onclick="window._loadLogs(' + i + ')">' + i + '</button>';
                }
                pag.innerHTML = ph;
            } else if (pag) {
                pag.innerHTML = '';
            }

            logsBody.querySelectorAll('.delete-log-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (confirm('Supprimer cette entree du journal ?')) {
                        deleteLogEntry(this.dataset.id);
                    }
                });
            });
        }

        function deleteLogEntry(id) {
            fetch(BASE_URL + '/api/logs.php?action=delete&id=' + id, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('Entree supprimee.');
                    loadLogs(currentLogsPage);
                } else {
                    showToast(data.message, true);
                }
            });
        }

        window._loadLogs = function(p) { loadLogs(p); };

        var logsSearch = document.getElementById('logsSearch');
        var logsNiveauFilter = document.getElementById('logsNiveauFilter');
        if (logsSearch) {
            var logsTimeout;
            logsSearch.addEventListener('input', function() {
                clearTimeout(logsTimeout);
                logsTimeout = setTimeout(function() { loadLogs(1); }, 300);
            });
        }
        if (logsNiveauFilter) logsNiveauFilter.addEventListener('change', function() { loadLogs(1); });

        loadLogs(1);
    }

    // =========================================================================
    // PREFERENCES FORMS (suite)
    // =========================================================================
    var preferencesForm = document.getElementById('preferencesForm');
    if (preferencesForm) {
        preferencesForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var fd = new FormData(preferencesForm);

            fetch(BASE_URL + '/api/users.php?action=update_preferences', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { showToast(data.success ? 'Preferences mises a jour.' : data.message, !data.success); });
        });
    }

    // Password change from preferences page
    var pwChangeForm = document.getElementById('changePasswordForm');
    if (pwChangeForm && pwChangeForm.closest('.card-body')) {
        pwChangeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var fd = new FormData(pwChangeForm);

            fetch(BASE_URL + '/api/auth.php?action=change_password', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { showToast(data.success ? 'Mot de passe modifie.' : data.message, !data.success); });
        });
    }

    // =========================================================================
    // HELPERS
    // =========================================================================
    function escHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showToast(message, isError) {
        var container = document.getElementById('toastContainer');
        if (!container) return;
        var toast = document.createElement('div');
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 4000);
    }

    var authRedirecting = false;
    function handleUnauthorized() {
        if (authRedirecting) return;
        authRedirecting = true;
        showToast('Session expiree. Redirection vers la connexion...', true);
        setTimeout(function() { window.location.href = BASE_URL + '/admin/login.php'; }, 1500);
    }
    window.handleUnauthorized = handleUnauthorized;

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        if (isNaN(d)) return dateStr;
        var day = ('0' + d.getDate()).slice(-2);
        var month = ('0' + (d.getMonth() + 1)).slice(-2);
        var year = d.getFullYear();
        var hours = ('0' + d.getHours()).slice(-2);
        var mins = ('0' + d.getMinutes()).slice(-2);
        return day + '/' + month + '/' + year + ' a ' + hours + ':' + mins;
    }

    function formatSize(bytes) {
        if (bytes == null) return '-';
        bytes = parseInt(bytes, 10);
        if (bytes < 1024) return bytes + ' o';
        if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' Ko';
        return (bytes / 1048576).toFixed(1) + ' Mo';
    }

    function piecesDetailHtml(pieces) {
        if (!pieces || pieces.length === 0) return '-';
        var rows = '';
        pieces.forEach(function(p) {
            var isPdf = p.mime_type === 'application/pdf';
            var badge = isPdf ? 'danger' : 'success';
            var label = isPdf ? 'PDF' : 'IMG';
            rows += '<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border)">';
            rows += '<span class="badge badge-' + badge + '" style="min-width:40px;text-align:center">' + label + '</span>';
            rows += '<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + escHtml(p.nom_fichier) + '">' + escHtml(p.nom_fichier) + '</span>';
            rows += '<span style="color:var(--text-muted);font-size:.8rem;white-space:nowrap">' + formatSize(p.taille) + '</span>';
            rows += '<a class="btn btn-sm btn-outline" href="' + BASE_URL + '/api/applications.php?action=download_piece&id=' + p.id + '" download="' + escHtml(p.nom_fichier) + '">Telecharger</a>';
            rows += '</div>';
        });
        return rows;
    }

    function safeId(v) {
        var n = parseInt(v, 10);
        return isNaN(n) ? '' : n;
    }

    function typeClass(type) {
        return type === 'partenariat' ? 'partenariat' : type === 'recrutement' ? 'recrutement' : 'secondary';
    }

    function typeLabel(type) {
        return type === 'partenariat' ? 'Partenariat' : type === 'recrutement' ? 'Recrutement' : type;
    }

    function statutLabel(statut) {
        var labels = { en_attente: 'En attente', en_cours: 'En cours', valide: 'Acceptée', refuse: 'Refusée', archive: 'Archivée' };
        return labels[statut] || statut;
    }

    function statutClass(statut) {
        var classes = { en_attente: 'warning', en_cours: 'info', valide: 'success', refuse: 'danger', archive: 'secondary' };
        return classes[statut] || 'secondary';
    }

    function roleBadge(role) {
        return role === 'admin' ? 'danger' : role === 'csm' ? 'partenariat' : 'info';
    }

    function actionBadge(action) {
        if (action.indexOf('success') !== -1 || action.indexOf('create') !== -1) return 'success';
        if (action.indexOf('failed') !== -1 || action.indexOf('delete') !== -1) return 'danger';
        if (action.indexOf('update') !== -1 || action.indexOf('change') !== -1 || action.indexOf('reset') !== -1) return 'info';
        return 'secondary';
    }

})();

/* ============================================================================
 * C3 - Bascule des onglets Parametres (settings-tabs)
 * ============================================================================ */
(function () {
    var tabsNav = document.getElementById('settingsTabs');
    if (!tabsNav) return;

    var btns = tabsNav.querySelectorAll('.settings-tab-btn[data-tab]');
    var panels = document.querySelectorAll('.settings-tab-panel');

    function showTab(tabId) {
        for (var i = 0; i < btns.length; i++) {
            btns[i].classList.toggle('active', btns[i].getAttribute('data-tab') === tabId);
        }
        for (var j = 0; j < panels.length; j++) {
            panels[j].classList.toggle('active', panels[j].id === 'panel-' + tabId);
        }
    }

    for (var k = 0; k < btns.length; k++) {
        btns[k].addEventListener('click', function () {
            showTab(this.getAttribute('data-tab'));
        });
    }
})();
