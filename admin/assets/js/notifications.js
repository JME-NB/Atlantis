/* ============================================================================
   ATLANTIS v2 - Boite de notifications (panneau cloche)
   ============================================================================ */

(function () {
    'use strict';

    var trigger   = document.querySelector('.notif-trigger');
    var panel     = document.querySelector('.notif-panel');
    var badge     = document.querySelector('.notif-badge');
    var markAll   = document.querySelector('.notif-mark-all');
    var listEl    = panel ? panel.querySelector('.notif-list') : null;
    var panelOpen = false;
    var timer     = null;

    if (!trigger || !panel || !listEl) return;

    // -------------------------------------------------------------------------
    // API helpers
    // -------------------------------------------------------------------------

    function apiGet(action, extraParams) {
        var base = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/notifications.php';
        var url  = base + '?action=' + encodeURIComponent(action);
        if (extraParams) {
            Object.keys(extraParams).forEach(function (k) {
                url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(extraParams[k]);
            });
        }
        return fetch(url).then(function (r) { return r.json(); });
    }

    function apiPost(action, extraParams) {
        var base = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/notifications.php';
        var url  = base + '?action=' + encodeURIComponent(action);
        if (extraParams) {
            Object.keys(extraParams).forEach(function (k) {
                url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(extraParams[k]);
            });
        }
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '') }
        }).then(function (r) { return r.json(); });
    }

    // -------------------------------------------------------------------------
    // Badge count
    // -------------------------------------------------------------------------

    function refreshCount() {
        apiGet('count')
            .then(function (res) {
                if (!res || !res.success) return;
                var count = parseInt(res.count || 0, 10);
                if (badge) {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? '' : 'none';
                }
            })
            .catch(function () {});
    }

    // Refresh au chargement + toutes les 30s
    refreshCount();
    timer = setInterval(refreshCount, 30000);

    // -------------------------------------------------------------------------
    // Panel toggle
    // -------------------------------------------------------------------------

    function closePanel() {
        panelOpen = false;
        panel.classList.remove('open');
    }

    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        panelOpen = !panelOpen;
        panel.classList.toggle('open', panelOpen);
        if (panelOpen) loadList();
    });

    document.addEventListener('click', function (e) {
        if (panelOpen && !panel.contains(e.target) && !trigger.contains(e.target)) {
            closePanel();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panelOpen) closePanel();
    });

    // -------------------------------------------------------------------------
    // Relative time
    // -------------------------------------------------------------------------

    function timeAgo(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(' ', 'T') + 'Z');
        if (isNaN(d.getTime())) return dateStr;
        var now = Date.now();
        var sec = Math.floor((now - d.getTime()) / 1000);
        if (sec < 0) sec = 0;
        if (sec < 60)   return sec + 's';
        var min = Math.floor(sec / 60);
        if (min < 60)   return min + 'min';
        var h = Math.floor(min / 60);
        if (h < 24)     return h + 'h';
        var j = Math.floor(h / 24);
        if (j < 7)      return j + 'j';
        var s = d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
        return s;
    }

    // -------------------------------------------------------------------------
    // Icones par type
    // -------------------------------------------------------------------------

    var ICONS = {
        ticket_nouveau:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        ticket_statut:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
        ticket_supprime:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
        design_modifie:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>',
        utilisateur_cree:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',
        utilisateur_modifie: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        utilisateur_supprime:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="16"/><line x1="23" y1="11" x2="18" y2="16"/></svg>'
    };

    var PAGE_MAP = {
        application: 'candidatures.php',
        design:      'design.php',
        user:        'utilisateurs.php'
    };

    // -------------------------------------------------------------------------
    // Render list
    // -------------------------------------------------------------------------

    function renderItems(items) {
        if (!items || !items.length) {
            listEl.innerHTML = '<p class="notif-empty">Aucune notification.</p>';
            return;
        }
        listEl.innerHTML = '';
        items.forEach(function (n) {
            var isUnread = (parseInt(n.lu, 10) === 0);
            var iconKey  = ICONS[n.type_notification] ? n.type_notification : 'ticket_nouveau';
            var icon     = ICONS[iconKey];
            var iconClass = String(iconKey).replace(/_/g, '-');
            var target   = PAGE_MAP[n.cible_type] || null;
            var auteurName = escapeHtml(n.auteur_identifiant || '');

            var item = document.createElement('div');
            item.className = 'notif-item' + (isUnread ? ' unread' : '');
            item.setAttribute('data-id', n.id);
            item.setAttribute('data-cible', n.cible_type || '');
            item.setAttribute('data-cible-id', n.cible_id || '');
            item.setAttribute('data-target', target || '');

            item.innerHTML =
                '<div class="notif-icon notif-icon-' + iconClass + '">' + icon + '</div>' +
                '<div class="notif-body">' +
                    '<p class="notif-msg">' + escapeHtml(n.message) + '</p>' +
                    '<span class="notif-meta">' + (auteurName !== '' ? auteurName + ' · ' : '') + timeAgo(n.cree_le) + '</span>' +
                '</div>';

            item.addEventListener('click', function () {
                closePanel();
                if (isUnread) {
                    apiPost('mark_read', { id: n.id }).then(function () {
                        refreshCount();
                        markItemRead(item);
                    });
                }
                if (target) {
                    window.location.href = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/admin/' + target;
                }
            });

            listEl.appendChild(item);
        });
    }

    function markItemRead(el) {
        el.classList.remove('unread');
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function loadList() {
        listEl.innerHTML = '<p class="notif-empty">Chargement...</p>';
        apiGet('list', { limit: 30 })
            .then(function (res) {
                if (!res || !res.success) {
                    listEl.innerHTML = '<p class="notif-empty">Erreur de chargement.</p>';
                    return;
                }
                renderItems(res.items);
            })
            .catch(function () {
                listEl.innerHTML = '<p class="notif-empty">Erreur de chargement.</p>';
            });
    }

    // -------------------------------------------------------------------------
    // Mark all
    // -------------------------------------------------------------------------

    if (markAll) {
        markAll.addEventListener('click', function (e) {
            e.stopPropagation();
            apiPost('mark_all_read')
                .then(function () {
                    refreshCount();
                    listEl.querySelectorAll('.notif-item.unread').forEach(markItemRead);
                });
        });
    }
})();