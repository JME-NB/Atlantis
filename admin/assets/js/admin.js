/* ============================================================================
   ATLANTIS v2 - JavaScript de l'espace d'administration
   ============================================================================ */

(function() {
    'use strict';

    // =========================================================================
    // SIDEBAR MOBILE
    // =========================================================================
    var hamburgerAdmin = document.getElementById('hamburgerAdmin');
    var sidebar = document.getElementById('sidebar');
    var sidebarOverlay = document.getElementById('sidebarOverlay');

    if (hamburgerAdmin && sidebar) {
        hamburgerAdmin.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('active');
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
            fetch(BASE_URL + '/api/auth.php?action=change_password', { method: 'POST', body: fd })
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
            if (typeof FILTER_STATUT !== 'undefined' && FILTER_STATUT) {
                params.set('statut', FILTER_STATUT);
                if (statutF) statutF.value = FILTER_STATUT;
            } else if (statutF && statutF.value) {
                params.set('statut', statutF.value);
            }

            fetch(BASE_URL + '/api/applications.php?' + params.toString())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    renderApplicationsTable(data.items, data.total, data.page, data.pages);
                }
            });
        }

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
                html += '<td><span class="badge badge-' + app.type + '">' + typeLabel(app.type) + '</span></td>';
                html += '<td><span class="badge badge-' + statutClass(app.statut) + '">' + statutLabel(app.statut) + '</span></td>';
                html += '<td>' + (parseInt(app.pieces_count, 10) > 0
                    ? '<button class="btn btn-sm btn-outline detail-btn" data-id="' + app.id + '">' + app.pieces_count + ' fichier(s)</button>'
                    : '<span class="text-muted">-</span>') + '</td>';
                html += '<td>' + formatDate(app.created_at) + '</td>';
                html += '<td>';
                html += '<button class="btn btn-sm btn-outline detail-btn" data-id="' + app.id + '">Voir</button> ';
                html += '<button class="btn btn-sm btn-danger delete-btn" data-id="' + app.id + '" data-nom="' + escHtml(app.nom) + '">X</button>';
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
                    if (confirm('Voulez-vous vraiment supprimer cette demande ? Cette action est irreversible.')) {
                        deleteApplication(this.dataset.id);
                    }
                });
            });
        }

        window._loadApps = function(p) { loadApplications(p); };

        // Filters
        var searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                var val = this.value;
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
            html += detailField('Nom', app.nom);
            html += detailField('Prenom', app.prenom || '-');
            html += detailField('Telephone', app.telephone);
            html += detailField('Email', app.email || '-');
            html += detailField('Entreprise', app.entreprise || '-');
            html += detailField('Type', '<span class="badge badge-' + app.type + '">' + typeLabel(app.type) + '</span>');
            html += detailField('Statut actuel', '<span class="badge badge-' + statutClass(app.statut) + '">' + statutLabel(app.statut) + '</span>');
            html += detailField('Message', app.message || '-');
            html += detailField('Dossier de candidature', piecesDetailHtml(data.pieces));
            html += detailField('Cree le', formatDate(app.created_at));
            html += detailField('Modifie le', formatDate(app.updated_at));
            document.getElementById('modalBody').innerHTML = html;

            // Footer avec changement de statut
            var statuses = ['en_attente', 'en_cours', 'valide', 'refuse', 'archive'];
            var footerHtml = '<div style="display:flex;gap:8px;align-items:center;width:100%">';
            footerHtml += '<select id="modalStatus" class="form-select" style="flex:1">';
            statuses.forEach(function(s) {
                footerHtml += '<option value="' + s + '"' + (app.statut === s ? ' selected' : '') + '>' + statutLabel(s) + '</option>';
            });
            footerHtml += '</select>';
            footerHtml += '<button class="btn btn-primary" id="saveStatusBtn" data-id="' + app.id + '">Mettre a jour</button>';
            footerHtml += '</div>';
            document.getElementById('modalFooter').innerHTML = footerHtml;

            document.getElementById('detailModal').hidden = false;

            document.getElementById('saveStatusBtn').addEventListener('click', function() {
                var newStatut = document.getElementById('modalStatus').value;
                updateStatus(this.dataset.id, newStatut);
            });
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
                html += '<td><span class="badge badge-' + roleBadge(u.role) + '">' + u.role + '</span></td>';
                html += '<td>' + (u.is_super_admin ? '<span class="badge badge-success">Oui</span>' : '-') + '</td>';
                html += '<td><span class="badge badge-' + (u.statut_compte === 'actif' ? 'success' : 'danger') + '">' + u.statut_compte + '</span></td>';
                html += '<td>' + (u.must_change_password ? '<span class="badge badge-warning">Oui</span>' : '-') + '</td>';
                html += '<td>';

                if (!u.is_super_admin) {
                    html += '<button class="btn btn-sm btn-outline role-btn" data-id="' + u.id + '" data-name="' + escHtml(u.identifiant) + '" data-role="' + u.role + '">Role</button> ';
                    html += '<button class="btn btn-sm btn-outline reset-btn" data-id="' + u.id + '" data-name="' + escHtml(u.identifiant) + '">Mdp</button> ';
                    html += '<button class="btn btn-sm btn-danger delete-user-btn" data-id="' + u.id + '" data-name="' + escHtml(u.identifiant) + '">X</button>';
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

        function loadAudit(page) {
            currentAuditPage = page || 1;
            var params = new URLSearchParams();
            params.set('action', 'list');
            params.set('page', currentAuditPage);

            var search = document.getElementById('auditSearch');
            var actionF = document.getElementById('auditActionFilter');
            if (search && search.value) params.set('search', search.value);
            if (actionF && actionF.value) params.set('action_filter', actionF.value);

            fetch(BASE_URL + '/api/audit.php?' + params.toString())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) renderAuditTable(data.items, data.page, data.pages);
            });
        }

        function renderAuditTable(items, page, pages) {
            if (!items || items.length === 0) {
                auditBody.innerHTML = '<tr><td colspan="6" class="text-center">Aucune entree.</td></tr>';
                return;
            }

            var html = '';
            items.forEach(function(log) {
                html += '<tr>';
                html += '<td>' + formatDate(log.created_at) + '</td>';
                html += '<td>' + escHtml(log.identifiant_snapshot) + '</td>';
                html += '<td>' + escHtml(log.role_snapshot) + '</td>';
                html += '<td><span class="badge badge-' + actionBadge(log.action) + '">' + escHtml(log.action) + '</span></td>';
                html += '<td>' + escHtml(log.details || '-') + '</td>';
                html += '<td>' + escHtml(log.adresse_ip || '-') + '</td>';
                html += '</tr>';
            });
            auditBody.innerHTML = html;

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

        window._loadAudit = function(p) { loadAudit(p); };

        var auditSearch = document.getElementById('auditSearch');
        var auditActionFilter = document.getElementById('auditActionFilter');
        if (auditSearch) {
            var auditTimeout;
            auditSearch.addEventListener('input', function() {
                clearTimeout(auditTimeout);
                auditTimeout = setTimeout(function() { loadAudit(1); }, 300);
            });
        }
        if (auditActionFilter) auditActionFilter.addEventListener('change', function() { loadAudit(1); });

        loadAudit(1);
    }

    // =========================================================================
    // DESIGN - SAVE SETTINGS
    // =========================================================================
    var publishBtn = document.getElementById('publishBtn');
    if (publishBtn) {
        publishBtn.addEventListener('click', function() {
            var settings = {};

            // Couleurs
            document.querySelectorAll('[data-setting]').forEach(function(el) {
                settings[el.dataset.setting] = el.value;
            });

            // Sync color pickers with text inputs
            document.querySelectorAll('input[type="color"]').forEach(function(picker) {
                var key = picker.id;
                var textInput = document.querySelector('[data-setting="' + key + '"]');
                if (textInput) {
                    settings[key] = picker.value;
                    textInput.value = picker.value;
                }
            });

            fetch(BASE_URL + '/api/settings.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify(settings)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('Parametres du site mis a jour.');
                } else {
                    showToast(data.message, true);
                }
            });
        });

        // Sync color pickers
        document.querySelectorAll('input[type="color"]').forEach(function(picker) {
            picker.addEventListener('input', function() {
                var textInput = document.querySelector('[data-setting="' + this.id + '"]');
                if (textInput) textInput.value = this.value;
            });
        });
        document.querySelectorAll('.color-input-group .form-input').forEach(function(textInput) {
            textInput.addEventListener('input', function() {
                var picker = document.getElementById(this.dataset.setting);
                if (picker && /^#[0-9A-Fa-f]{6}$/.test(this.value)) picker.value = this.value;
            });
        });

        // Save sections
        publishBtn.addEventListener('click', function() {
            var sections = [];
            document.querySelectorAll('.section-editor').forEach(function(el) {
                sections.push({
                    id: parseInt(el.dataset.id),
                    titre: el.querySelector('.section-titre').value,
                    contenu: el.querySelector('.section-contenu').value,
                    ordre: parseInt(el.querySelector('.section-ordre').value),
                    visible: el.querySelector('.section-visible').checked
                });
            });

            fetch(BASE_URL + '/api/settings.php?action=update_sections', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ sections: sections })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) showToast('Sections mises a jour.');
                else showToast(data.message, true);
            });
        });
    }

    // =========================================================================
    // DESIGN - PREVIEW (apercu de la landing dans un nouvel onglet)
    // =========================================================================
    var previewBtn = document.getElementById('previewBtn');
    if (previewBtn) {
        previewBtn.addEventListener('click', function() {
            window.open(BASE_URL + '/', '_blank');
        });
    }

    // =========================================================================
    // DESIGN - UPLOAD IMAGES (logo, banniere, section)
    // =========================================================================
    document.querySelectorAll('.upload-input').forEach(function(input) {
        input.addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;

            var type = this.dataset.uploadType;
            var sectionId = this.dataset.sectionId || '';
            var fd = new FormData();
            fd.append('image', file);
            fd.append('type', type);
            if (sectionId) fd.append('section_id', sectionId);

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
                    showToast('Image upload avec succes.');
                    setTimeout(function() { window.location.reload(); }, 600);
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

            fetch(BASE_URL + '/api/upload.php?action=remove', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast('Image supprimer.');
                    setTimeout(function() { window.location.reload(); }, 600);
                } else {
                    showToast(data.message, true);
                }
            });
        });
    });

    // =========================================================================
    // DESIGN - DRAG & DROP des sections
    // =========================================================================
    var sectionsList = document.getElementById('sectionsList');
    if (sectionsList && !READONLY) {
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
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
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

    function typeLabel(type) {
        return type === 'partenariat' ? 'Partenariat' : type === 'recrutement' ? 'Recrutement' : type;
    }

    function statutLabel(statut) {
        var labels = { en_attente: 'En attente', en_cours: 'En cours', valide: 'Valide', refuse: 'Refuse', archive: 'Archive' };
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
