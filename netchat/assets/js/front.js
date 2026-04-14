/**
 * NetChat Frontend JS
 * Author: omhotey
 */
(function(){
    'use strict';
    document.addEventListener('DOMContentLoaded',function(){
        var t = document.getElementById('nc-toggle');
        var b = document.getElementById('nc-bubble');
        var h = document.getElementById('nc-hint');
        var open = false;
        var cfg = window.netchat || {};
        var speed = cfg.speed || 300;

        if (!t || !b) return;

        /**
         * Open the messenger bubble
         */
        function openMenu() {
            open = true;
            b.classList.add('nc-open');
            t.setAttribute('aria-expanded', 'true');
            b.setAttribute('aria-hidden', 'false');

            // Hide hint immediately
            if (h) {
                h.classList.remove('nc-visible');
                clearTimeout(window._ht);
            }

            // Stagger animation for each messenger button
            var btns = b.querySelectorAll('.nc-btn');
            btns.forEach(function(btn, i) {
                btn.style.animationDelay = (i * 60) + 'ms';
                btn.setAttribute('tabindex', '0');
            });

            // Clear delays after animation completes
            var totalDelay = btns.length * 60 + speed;
            setTimeout(function() {
                btns.forEach(function(btn) {
                    btn.style.animationDelay = '';
                });
            }, totalDelay);

            // Focus first button for accessibility
            if (btns.length) {
                setTimeout(function() { btns[0].focus(); }, 100);
            }
        }

        /**
         * Close the messenger bubble
         */
        function closeMenu() {
            open = false;
            b.classList.remove('nc-open');
            t.setAttribute('aria-expanded', 'false');
            b.setAttribute('aria-hidden', 'true');

            // Reset messenger buttons
            b.querySelectorAll('.nc-btn').forEach(function(btn) {
                btn.setAttribute('tabindex', '-1');
                btn.style.animationDelay = '';
            });
        }

        /**
         * Toggle the menu open/close state
         */
        function toggleMenu() {
            if (open) {
                closeMenu();
            } else {
                openMenu();
            }
        }

        // Main button click handler
        t.addEventListener('click', function(e) {
            e.preventDefault();
            toggleMenu();
        });

        // Handle touch events for better mobile support
        t.addEventListener('touchend', function(e) {
            e.preventDefault();
            toggleMenu();
        });

        // Close on outside click (only when menu is open)
        document.addEventListener('click', function(e) {
            if (!open) return;
            // Don't close if clicking inside #netchat or on a link within #netchat
            if (e.target.closest('#netchat')) {
                return;
            }
            closeMenu();
        });

        // Close on outside touch (mobile devices)
        document.addEventListener('touchend', function(e) {
            if (!open) return;
            if (e.target.closest('#netchat')) {
                return;
            }
            closeMenu();
        }, { passive: true });

        // Allow links to work properly inside the bubble
        b.addEventListener('click', function(e) {
            // Don't prevent default - let the link navigate normally
            // Just close the menu after a small delay
            if (e.target.closest('.nc-btn')) {
                setTimeout(function() {
                    closeMenu();
                }, 150);
            }
        });

        // Touch handler for messenger buttons (better mobile support)
        b.addEventListener('touchend', function(e) {
            var btn = e.target.closest('.nc-btn');
            if (btn) {
                // Close menu after touch with slight delay to allow navigation
                setTimeout(function() {
                    closeMenu();
                }, 200);
            }
        }, { passive: true });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && open) {
                closeMenu();
                t.focus();
            }
        });

        // Show hint after delay
        if (h) {
            window._ht = setTimeout(function() {
                // Only show hint if menu is not open
                if (!open) {
                    h.classList.add('nc-visible');
                }
            }, cfg.hintDelay || 2000);

            h.addEventListener('click', function() {
                h.classList.remove('nc-visible');
                clearTimeout(window._ht);
                // Open menu when hint is clicked
                openMenu();
            });
        }

        // Public API
        window.NetChat = {
            open: openMenu,
            close: closeMenu,
            toggle: toggleMenu,
            isOpen: function() { return open; }
        };
    });
})();
