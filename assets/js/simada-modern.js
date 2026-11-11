/* SIMADA modern UI motion layer. Visual only. */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    ready(function () {
        document.documentElement.setAttribute('data-theme', 'light');
        try { localStorage.removeItem('simada-theme'); } catch (e) {}

        var main = document.querySelector('.app-main') || document.querySelector('.page-wrap');
        if (main) main.classList.add('simada-page-enter');

        var candidates = [];
        if (document.querySelector('.app-main')) {
            candidates = Array.from(document.querySelectorAll(
                '.app-main > .space-y-6 > *, .app-main > div > .grid > .bg-white, .app-main .grid > .bg-white.shadow-sm, .app-main .grid > .bg-white.shadow-soft'
            ));
        } else {
            candidates = Array.from(document.querySelectorAll('.login-stage > *, .form-panel > div'));
        }

        var seen = new Set();
        candidates = candidates.filter(function (el) {
            if (!el || seen.has(el) || el.classList.contains('fixed')) return false;
            seen.add(el);
            return true;
        }).slice(0, 28);

        candidates.forEach(function (el, index) {
            el.classList.add('simada-reveal');
            el.style.transitionDelay = Math.min(index * 35, 280) + 'ms';
        });

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                candidates.forEach(function (el) { el.classList.add('is-visible'); });
            });
        });

        function animateOpenModal(modal) {
            if (!modal || modal.classList.contains('hidden')) return;
            var panel = modal.querySelector(':scope > .bg-white') || modal.querySelector('.bg-white');
            if (!panel) return;
            panel.classList.remove('simada-modal-panel');
            void panel.offsetWidth;
            panel.classList.add('simada-modal-panel');
        }

        var modals = Array.from(document.querySelectorAll('.fixed.inset-0'));
        modals.forEach(function (modal) {
            var observer = new MutationObserver(function () { animateOpenModal(modal); });
            observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
        });

        document.querySelectorAll('button, a').forEach(function (el) {
            el.addEventListener('pointerdown', function () {
                if (!el.disabled) el.style.setProperty('--simada-pressed', '1');
            });
        });
    });
})();
