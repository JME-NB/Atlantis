/* ============================================================================
   ATLANTIS v2 - JavaScript de la landing page publique
   ============================================================================ */

(function() {
    'use strict';

    // --- NAVBAR SCROLL ---
    const navbar = document.getElementById('navbar');
    const backToTop = document.getElementById('backToTop');

    window.addEventListener('scroll', function() {
        if (navbar) {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        }
        if (backToTop) {
            backToTop.classList.toggle('visible', window.scrollY > 400);
        }
    });

    if (backToTop) {
        backToTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // --- HAMBURGER MOBILE ---
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('navLinks');

    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function() {
            navLinks.classList.toggle('open');
            const expanded = hamburger.getAttribute('aria-expanded') === 'true';
            hamburger.setAttribute('aria-expanded', !expanded);
        });

        // Fermer le menu au clic sur un lien
        navLinks.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                navLinks.classList.remove('open');
                hamburger.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // --- REVEAL ON SCROLL ---
    const reveals = document.querySelectorAll('.reveal');

    if (reveals.length > 0 && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        reveals.forEach(function(el) { observer.observe(el); });
    } else {
        reveals.forEach(function(el) { el.classList.add('visible'); });
    }

    // --- FORMULAIRE DE CONTACT ---
    const contactForm = document.getElementById('contactForm');

    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Reset erreurs
            document.querySelectorAll('.field-error').forEach(function(el) { el.textContent = ''; });
            hideFeedback('formFeedback');

            // Validation JS
            var errors = {};
            var nom = contactForm.nom.value.trim();
            var telephone = contactForm.telephone.value.trim();
            var type = contactForm.type.value;

            if (!nom) errors.nom = 'Le nom est obligatoire.';
            if (!telephone) errors.telephone = 'Le numero de telephone est obligatoire.';
            else if (!/^[0-9+\-\s()]{8,20}$/.test(telephone)) errors.telephone = 'Veuillez renseigner un numero de telephone valide.';
            if (!type) errors.type = 'Veuillez selectionner un type de candidature.';

            if (Object.keys(errors).length > 0) {
                Object.keys(errors).forEach(function(key) {
                    var el = document.getElementById(key + 'Error');
                    if (el) el.textContent = errors[key];
                });
                return;
            }

            // Envoi via fetch
            var submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi en cours...';

            var formData = new FormData(contactForm);

            fetch(BASE_URL + '/api/applications.php?action=create', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    showFeedback('formFeedback', data.message, 'success');
                    contactForm.reset();
                } else {
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
                showFeedback('formFeedback', 'Une erreur est survenue. Veuillez reessayer.', 'error');
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Envoyer ma demande';
            });
        });
    }

    // --- NOTIFICATIONS ---
    function showNotification(message, isError) {
        var area = document.getElementById('notificationArea');
        if (!area) return;
        var toast = document.createElement('div');
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.textContent = message;
        area.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 4000);
    }

    // --- HELPERS ---
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
