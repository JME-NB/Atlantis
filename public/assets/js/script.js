/* ============================================================================
   ATLANTIS v2 - JavaScript de la landing page publique
   ============================================================================ */

(function() {
    'use strict';

    // --- THEME TOGGLE (dark/light) ---
    (function() {
        var saved = localStorage.getItem('atlantis-theme');
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = saved || (prefersDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);

        var btn = document.getElementById('themeToggle');
        if (btn) {
            btn.addEventListener('click', function() {
                var current = document.documentElement.getAttribute('data-theme');
                var next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('atlantis-theme', next);
            });
        }
    })();

    // --- NAVBAR SCROLL ---
    var navbar = document.getElementById('navbar');
    var backToTop = document.getElementById('backToTop');

    window.addEventListener('scroll', function() {
        if (navbar) {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        }
        if (backToTop) {
            backToTop.classList.toggle('visible', window.scrollY > 400);
        }
    }, { passive: true });

    if (backToTop) {
        backToTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // --- HERO WAVE PARALLAXE ---
    var heroWave = document.querySelector('.hero-wave');
    if (heroWave) {
        window.addEventListener('scroll', function() {
            var scroll = window.scrollY;
            if (scroll < 600) {
                heroWave.style.transform = 'translateY(' + (scroll * 0.25) + 'px)';
            }
        }, { passive: true });
    }

    // --- HAMBURGER MOBILE ---
    var hamburger = document.getElementById('hamburger');
    var navLinks = document.getElementById('navLinks');

    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            navLinks.classList.toggle('open');
            var expanded = hamburger.getAttribute('aria-expanded') === 'true';
            hamburger.setAttribute('aria-expanded', !expanded);
        });

        navLinks.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                navLinks.classList.remove('open');
                hamburger.classList.remove('active');
                hamburger.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // --- HERO IMAGE LOADING ---
    var heroBg = document.querySelector('.hero-image-bg');
    if (heroBg) {
        var bgUrl = heroBg.getAttribute('data-src');
        if (bgUrl) {
            var img = new Image();
            img.onload = function() {
                heroBg.style.backgroundImage = 'url(' + bgUrl + ')';
                heroBg.classList.add('loaded');
            };
            img.src = bgUrl;
        }
    }

    // --- REVEAL ON SCROLL (re-trigger a chaque passage) ---
    var reveals = document.querySelectorAll('.reveal');
    var observer;

    if (reveals.length > 0 && 'IntersectionObserver' in window) {
        observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                var siblings = entry.target.parentElement.querySelectorAll('.reveal');
                var index = Array.prototype.indexOf.call(siblings, entry.target);
                entry.target.style.transitionDelay = (index * 0.12) + 's';
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                } else {
                    entry.target.classList.remove('visible');
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -10% 0px' });

        reveals.forEach(function(el) { observer.observe(el); });
    } else if (reveals.length > 0) {
        reveals.forEach(function(el) { el.classList.add('visible'); });
    }

    // --- SLIDERS (services + secteurs) ---
    function sliderBounds(grid) {
        var card = grid.querySelector('.reveal, article');
        var step = (card ? card.offsetWidth : 380) + 28;
        return { step: step, max: Math.max(0, grid.scrollWidth - grid.clientWidth) };
    }

    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    // Défilement animé simple (recalage final)
    function animateScroll(grid, targetX, duration) {
        if (grid._raf) { cancelAnimationFrame(grid._raf); grid._raf = null; }
        var start = grid.scrollLeft;
        var change = targetX - start;
        if (Math.abs(change) < 1) { grid.style.scrollSnapType = ''; return; }
        var startTime = null;

        function step(ts) {
            if (!startTime) startTime = ts;
            var progress = Math.min((ts - startTime) / duration, 1);
            grid.scrollLeft = start + change * easeOutCubic(progress);
            if (progress < 1) {
                grid._raf = requestAnimationFrame(step);
            } else {
                grid._raf = null;
                grid.style.scrollSnapType = '';
            }
        }
        grid._raf = requestAnimationFrame(step);
    }

    // Recalage doux sur la carte la plus proche
    function settle(grid) {
        var bounds = sliderBounds(grid);
        var pos = Math.max(0, Math.min(grid.scrollLeft, bounds.max));
        var nearest = Math.round(pos / bounds.step) * bounds.step;
        nearest = Math.max(0, Math.min(nearest, bounds.max));
        animateScroll(grid, nearest, 180);
    }

    // Glissement physique : lancement vif + friction + dépassement + settle
    function flickScroll(grid, velocity) {
        if (grid._raf) { cancelAnimationFrame(grid._raf); grid._raf = null; }
        grid.style.scrollSnapType = 'none';

        if (Math.abs(velocity) < 1) { settle(grid); return; }

        var bounds = sliderBounds(grid);
        var friction = 0.90;

        function step() {
            var next = grid.scrollLeft + velocity;
            if (next < 0) { grid.scrollLeft = 0; velocity = 0; }
            else if (next > bounds.max) { grid.scrollLeft = bounds.max; velocity = 0; }
            else { grid.scrollLeft = next; }
            velocity *= friction;
            if (Math.abs(velocity) > 0.6) {
                grid._raf = requestAnimationFrame(step);
            } else {
                grid._raf = null;
                settle(grid);
            }
        }
        grid._raf = requestAnimationFrame(step);
    }

    // Drag souris 1:1 + inertie au lâcher
    function initDrag(grid) {
        if (!window.PointerEvent) return;
        var dragging = false;
        var startX = 0, startY = 0, startScroll = 0;
        var lastX = 0, lastTime = 0, lastDelta = 0;
        var axis = null;

        grid.addEventListener('pointerdown', function(e) {
            if (e.pointerType !== 'mouse') return;
            dragging = true;
            axis = null;
            startX = e.clientX;
            startY = e.clientY;
            startScroll = grid.scrollLeft;
            lastX = e.clientX;
            lastTime = performance.now();
            lastDelta = 0;
            grid.style.scrollSnapType = 'none';
            if (grid._raf) { cancelAnimationFrame(grid._raf); grid._raf = null; }
            grid.classList.add('dragging');
            grid.setPointerCapture(e.pointerId);
        });

        grid.addEventListener('pointermove', function(e) {
            if (!dragging) return;
            var dx = e.clientX - startX;
            var dy = e.clientY - startY;
            if (!axis && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
                axis = Math.abs(dx) > Math.abs(dy) ? 'h' : 'v';
            }
            if (axis === 'v') return;
            e.preventDefault();
            grid.scrollLeft = startScroll - dx;
            var now = performance.now();
            var dt = now - lastTime;
            if (dt > 16) {
                lastDelta = e.clientX - lastX;
                lastTime = now;
                lastX = e.clientX;
            }
        });

        function finish() {
            if (!dragging) return;
            dragging = false;
            grid.classList.remove('dragging');
            var dt = performance.now() - lastTime;
            var velocity = dt > 0 ? (lastDelta / (dt / 16.67)) * 1.2 : lastDelta;
            if (Math.abs(velocity) > 20) {
                flickScroll(grid, velocity);
            } else {
                settle(grid);
            }
        }

        grid.addEventListener('pointerup', finish);
        grid.addEventListener('pointercancel', finish);
    }

    function initSlider(gridId, prevId, nextId) {
        var grid = document.getElementById(gridId);
        var prevBtn = document.getElementById(prevId);
        var nextBtn = document.getElementById(nextId);
        if (!grid || !prevBtn || !nextBtn) return;

        function scroll(dir) {
            var bounds = sliderBounds(grid);
            var target = grid.scrollLeft + dir * bounds.step;
            target = Math.max(0, Math.min(target, bounds.max));
            flickScroll(grid, (target - grid.scrollLeft) * 0.11);
        }

        prevBtn.addEventListener('click', function() { scroll(-1); });
        nextBtn.addEventListener('click', function() { scroll(1); });
        initDrag(grid);
    }

    initSlider('servicesGrid', 'servicesPrev', 'servicesNext');
    initSlider('secteursGrid', 'secteursPrev', 'secteursNext');

    // --- FORMULAIRE DE CONTACT ---
    var contactForm = document.getElementById('contactForm');

    // --- PIECES JOINTES (DOSSIER DE CANDIDATURE) ---
    var pieces = {};
    var pieceIdCounter = 0;
    var MAX_PIECES = 6;
    var MAX_PIECE_SIZE = 5 * 1024 * 1024;
    var ALLOWED_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];
    var ALLOWED_EXTS = ['.jpg', '.jpeg', '.png', '.pdf'];
    var uploadZone = document.getElementById('uploadZone');
    var piecesInput = document.getElementById('piecesInput');
    var pieceList = document.getElementById('pieceList');
    var piecesError = document.getElementById('piecesError');

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' o';
        if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' Ko';
        return (bytes / 1048576).toFixed(1) + ' Mo';
    }

    function pieceKind(mime, name) {
        return mime === 'application/pdf' || /\.pdf$/i.test(name) ? 'pdf' : 'img';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showPiecesError(msg) {
        if (piecesError) piecesError.textContent = msg || '';
    }

    function updateSubmitState() {
        var btn = document.getElementById('submitBtn');
        var keys = Object.keys(pieces);
        var ready = 0;
        var busy = 0;
        keys.forEach(function(k) {
            if (pieces[k].status === 'ready') ready++;
            if (pieces[k].status === 'uploading') busy++;
        });
        btn.disabled = (ready === 0) || (busy > 0);
    }

    function buildPieceRow(item) {
        var kind = pieceKind(item.file.type, item.file.name);
        var icon = kind === 'pdf'
            ? '<div class="piece-icon icon-pdf">PDF</div>'
            : '<div class="piece-icon icon-img">IMG</div>';
        var state = '';
        var progress = '';

        if (item.status === 'uploading') {
            state = '<span class="piece-state loading"><span class="piece-state-spin"></span> Chargement <span class="piece-pct">' + item.progress + '%</span></span>';
            progress = item.progress > 0
                ? '<div class="piece-progress"><div class="piece-progress-bar" style="width:' + item.progress + '%"></div></div>'
                : '<div class="piece-progress indeterminate"><div class="piece-progress-bar"></div></div>';
        } else if (item.status === 'ready') {
            state = '<span class="piece-state ready">\u2713 Pret</span>';
        } else {
            state = '<span class="piece-state error">\u0021 Erreur</span>';
        }

        return '<div class="piece-item" data-id="' + item.id + '">'
            + icon
            + '<div class="piece-info">'
            + '<span class="piece-name" title="' + escapeHtml(item.file.name) + '">' + escapeHtml(item.file.name) + '</span>'
            + '<span class="piece-meta">' + formatSize(item.file.size) + '</span>'
            + progress
            + '</div>'
            + state
            + '<button class="piece-remove" data-id="' + item.id + '" type="button" title="Retirer"'
            + (item.status === 'uploading' ? ' disabled' : '') + '>\u00d7</button>'
            + '</div>';
    }

    function renderPieces() {
        if (!pieceList) return;
        var html = '';
        Object.keys(pieces).sort(function(a, b) { return a - b; }).forEach(function(k) {
            html += buildPieceRow(pieces[k]);
        });
        pieceList.innerHTML = html;
        pieceList.hidden = Object.keys(pieces).length === 0;

        Object.keys(pieces).forEach(function(k) {
            pieces[k].el = pieceList.querySelector('.piece-item[data-id="' + pieces[k].id + '"]');
        });
        pieceList.querySelectorAll('.piece-remove').forEach(function(btn) {
            btn.addEventListener('click', function() { removePiece(parseInt(btn.dataset.id, 10)); });
        });
    }

    function updatePieceProgress(item) {
        if (!item || !item.el) return;
        var bar = item.el.querySelector('.piece-progress-bar');
        var pct = item.el.querySelector('.piece-pct');
        if (bar) {
            bar.parentElement.classList.remove('indeterminate');
            bar.style.width = item.progress + '%';
        }
        if (pct) pct.textContent = item.progress + '%';
    }

    function uploadPiece(id) {
        var item = pieces[id];
        var fd = new FormData();
        fd.append('piece', item.file);

        var xhr = new XMLHttpRequest();
        item.xhr = xhr;
        item.uploadStarted = true;

        xhr.upload.addEventListener('progress', function(ev) {
            if (!ev.lengthComputable) return;
            var pct = Math.floor((ev.loaded / ev.total) * 100);
            item.progress = Math.max(0, Math.min(100, pct));
            updatePieceProgress(item);
        });

        xhr.addEventListener('load', function() {
            var data = null;
            try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
            if (xhr.status === 200 && data && data.success && data.piece_token) {
                item.token = data.piece_token;
                item.status = 'ready';
                item.progress = 100;
            } else {
                item.status = 'error';
                item.progress = 0;
                showPiecesError((data && data.message) || 'Echec du chargement de "' + item.file.name + '".');
            }
            renderPieces();
            updateSubmitState();
        });

        xhr.addEventListener('error', function() {
            item.status = 'error';
            showPiecesError('Une erreur est survenue pendant le chargement de "' + item.file.name + '". Verifiez votre connexion.');
            renderPieces();
            updateSubmitState();
        });

        xhr.open('POST', BASE_URL + '/api/applications.php?action=upload_piece');
        xhr.send(fd);
    }

    function addPiecesFiles(fileList) {
        var files = Array.prototype.slice.call(fileList);
        var current = Object.keys(pieces).length;
        var firstErr = '';

        files.forEach(function(file) {
            var ext = '.' + (file.name.split('.').pop() || '').toLowerCase();
            var typeOk = ALLOWED_MIMES.indexOf(file.type) !== -1 || ALLOWED_EXTS.indexOf(ext) !== -1;
            var nameShort = file.name.length > 30 ? file.name.slice(0, 27) + '...' : file.name;

            if (!typeOk) {
                if (!firstErr) firstErr = 'Format non autorise pour "' + nameShort + '". Formats acceptes : JPEG, JPG, PNG, PDF.';
                return;
            }
            if (file.size > MAX_PIECE_SIZE) {
                if (!firstErr) firstErr = '"' + nameShort + '" depasse 5 Mo (5 Mo max par fichier).';
                return;
            }
            if (current + 1 > MAX_PIECES) {
                if (!firstErr) firstErr = 'Maximum ' + MAX_PIECES + ' fichiers autorises.';
                return;
            }

            var id = ++pieceIdCounter;
            pieces[id] = { id: id, file: file, token: null, status: 'uploading', uploadStarted: false, progress: 0, xhr: null, el: null };
            current++;
        });

        showPiecesError(firstErr);
        renderPieces();
        updateSubmitState();

        Object.keys(pieces).forEach(function(k) {
            if (pieces[k].status === 'uploading' && !pieces[k].uploadStarted) uploadPiece(k);
        });
    }

    function removePiece(id) {
        var item = pieces[id];
        if (!item) return;
        if (item.status === 'uploading' && item.xhr) {
            try { item.xhr.abort(); } catch (e) {}
        }
        delete pieces[id];
        renderPieces();
        updateSubmitState();
    }

    function resetPieces() {
        Object.keys(pieces).forEach(function(k) {
            var item = pieces[k];
            if (item.status === 'uploading' && item.xhr) {
                try { item.xhr.abort(); } catch (e) {}
            }
        });
        pieces = {};
        pieceIdCounter = 0;
        if (pieceList) { pieceList.innerHTML = ''; pieceList.hidden = true; }
        showPiecesError('');
        updateSubmitState();
    }

    if (uploadZone && piecesInput) {
        uploadZone.addEventListener('click', function() { piecesInput.click(); });
        uploadZone.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                piecesInput.click();
            }
        });
        piecesInput.addEventListener('change', function() {
            addPiecesFiles(piecesInput.files);
            piecesInput.value = '';
        });
        ['dragenter', 'dragover'].forEach(function(type) {
            uploadZone.addEventListener(type, function(e) {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function(type) {
            uploadZone.addEventListener(type, function(e) {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
            });
        });
        uploadZone.addEventListener('drop', function(e) {
            if (e.dataTransfer && e.dataTransfer.files) addPiecesFiles(e.dataTransfer.files);
        });
    }

    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();

            document.querySelectorAll('.field-error').forEach(function(el) { el.textContent = ''; });
            hideFeedback('formFeedback');

            var errors = {};
            var nom = contactForm.nom.value.trim();
            var telephone = contactForm.telephone.value.trim();
            var type = contactForm.type.value;

            if (!nom) errors.nom = 'Le nom est obligatoire.';
            if (!telephone) errors.telephone = 'Le numero de telephone est obligatoire.';
            else if (!/^[0-9+\-\s()]{8,20}$/.test(telephone)) errors.telephone = 'Veuillez renseigner un numero de telephone valide.';
            if (!type) errors.type = 'Veuillez selectionner un type de candidature.';

            var readyKeys = [];
            var busyKeys = [];
            Object.keys(pieces).forEach(function(k) {
                if (pieces[k].status === 'ready') readyKeys.push(k);
                if (pieces[k].status === 'uploading') busyKeys.push(k);
            });
            if (readyKeys.length === 0) {
                errors.pieces = busyKeys.length > 0
                    ? 'Veuillez patienter : le chargement des fichiers est en cours.'
                    : 'Le dossier de candidature est obligatoire : ajoutez au moins une piece jointe.';
            }

            if (Object.keys(errors).length > 0) {
                Object.keys(errors).forEach(function(key) {
                    var el = document.getElementById(key + 'Error');
                    if (el) el.textContent = errors[key];
                });
                return;
            }

            var submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi en cours...';

            var formData = new FormData(contactForm);
            readyKeys.forEach(function(k) {
                formData.append('pieces[]', pieces[k].token);
            });

            fetch(BASE_URL + '/api/applications.php?action=create', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    showNotification(data.message, false);
                    showFeedback('formFeedback', data.message, 'success');
                    contactForm.reset();
                    resetPieces();
                } else {
                    showNotification(data.message, true);
                    showFeedback('formFeedback', data.message, 'error');
                    if (data.errors) {
                        Object.keys(data.errors).forEach(function(key) {
                            var el = document.getElementById(key + 'Error');
                            if (el) el.textContent = data.errors[key];
                        });
                    }
                }
            })
            .catch(function() {
                var msg = 'Une erreur est survenue. Veuillez reessayer.';
                showNotification(msg, true);
                showFeedback('formFeedback', msg, 'error');
            })
            .finally(function() {
                submitBtn.textContent = 'Envoyer ma demande';
                updateSubmitState();
            });
        });
    }

    // --- NOTIFICATIONS (TOASTS) ---
    function showNotification(message, isError) {
        var area = document.getElementById('notificationArea');
        if (!area) return;
        var toast = document.createElement('div');
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.textContent = message;
        area.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(50px)';
            toast.style.transition = '0.3s ease';
            setTimeout(function() { toast.remove(); }, 300);
        }, 3700);
    }

    // --- SERVICES SLIDER (geré par initSlider ci-dessus) ---

    // --- CONTROLS HELPERS ---
    function showFeedback(id, message, type) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = message;
        el.className = 'form-feedback ' + type;
        el.hidden = false;
    }

    function hideFeedback(id) {
        var el = document.getElementById(id);
        if (el) { el.hidden = true; el.className = 'form-feedback'; }
    }

})();
