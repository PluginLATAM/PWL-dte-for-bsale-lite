/**
 * WP Admin Design System — JS
 * Handles: Tabs, Toggle UI, Dismiss notices, Modal, Copy-to-clipboard
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Tabs
       Usage:
         <div class="wads-tabs" data-wads-tabs>
           <button class="wads-tab is-active" data-tab="tab-1">Tab 1</button>
           <button class="wads-tab"           data-tab="tab-2">Tab 2</button>
         </div>
         <div class="wads-tab-panels">
           <div class="wads-tab-panel is-active" id="tab-1">...</div>
           <div class="wads-tab-panel"           id="tab-2">...</div>
         </div>
    ------------------------------------------------------------------ */
    function initTabs() {
        document.querySelectorAll('[data-wads-tabs]').forEach(function (tabNav) {
            tabNav.addEventListener('click', function (e) {
                var tab = e.target.closest('.wads-tab');
                if (!tab) return;

                var targetId = tab.dataset.tab;
                if (!targetId) return;

                // Deactivate all tabs in this nav
                tabNav.querySelectorAll('.wads-tab').forEach(function (t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });

                // Activate clicked tab
                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');

                // Find the panels container: next sibling or by data attribute
                var panelsWrap = tabNav.nextElementSibling;
                if (!panelsWrap || !panelsWrap.classList.contains('wads-tab-panels')) {
                    panelsWrap = document.querySelector('[data-wads-panels="' + tabNav.id + '"]');
                }
                if (!panelsWrap) return;

                // Deactivate all panels
                panelsWrap.querySelectorAll('.wads-tab-panel').forEach(function (p) {
                    p.classList.remove('is-active');
                });

                // Activate target panel
                var target = document.getElementById(targetId);
                if (target) target.classList.add('is-active');
            });
        });
    }

    /* ------------------------------------------------------------------
       Dismiss notices
       Usage: <div class="wads-notice">
                <div class="wads-notice__content">...</div>
                <button class="wads-notice__dismiss" aria-label="Dismiss">×</button>
              </div>
    ------------------------------------------------------------------ */
    function initNotices() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.wads-notice__dismiss');
            if (!btn) return;

            var notice = btn.closest('.wads-notice');
            if (!notice) return;

            notice.style.transition = 'opacity 200ms ease, max-height 250ms ease, margin 250ms ease, padding 250ms ease';
            notice.style.overflow   = 'hidden';
            notice.style.opacity    = '0';
            notice.style.maxHeight  = notice.offsetHeight + 'px';

            requestAnimationFrame(function () {
                notice.style.maxHeight  = '0';
                notice.style.marginTop  = '0';
                notice.style.marginBottom = '0';
                notice.style.paddingTop = '0';
                notice.style.paddingBottom = '0';
            });

            setTimeout(function () { notice.remove(); }, 270);
        });
    }

    /* ------------------------------------------------------------------
       Modal
       Usage:
         <button data-wads-modal-open="my-modal">Open</button>

         <div class="wads-modal-backdrop is-hidden" id="my-modal" data-wads-modal>
           <div class="wads-modal">
             <div class="wads-modal__header">
               <h3 class="wads-modal__title">Title</h3>
               <button class="wads-modal__close" aria-label="Close">×</button>
             </div>
             <div class="wads-modal__body">...</div>
             <div class="wads-modal__footer">...</div>
           </div>
         </div>
    ------------------------------------------------------------------ */
    function initModals() {
        // Open
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-wads-modal-open]');
            if (!trigger) return;

            var targetId = trigger.dataset.wadsModalOpen;
            var modal    = document.getElementById(targetId);
            if (!modal)  return;

            modal.classList.remove('is-hidden');
            document.body.style.overflow = 'hidden';

            // Focus first focusable element
            var focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusable) setTimeout(function () { focusable.focus(); }, 50);
        });

        // Close via backdrop click or close button
        document.addEventListener('click', function (e) {
            var modal = e.target.closest('[data-wads-modal]');
            if (!modal) return;

            var isBackdrop  = e.target === modal;
            var isCloseBtn  = e.target.closest('.wads-modal__close');
            var isCancelBtn = e.target.closest('[data-wads-modal-close]');

            if (isBackdrop || isCloseBtn || isCancelBtn) {
                modal.classList.add('is-hidden');
                document.body.style.overflow = '';
            }
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var open = document.querySelector('[data-wads-modal]:not(.is-hidden)');
            if (open) {
                open.classList.add('is-hidden');
                document.body.style.overflow = '';
            }
        });
    }

    /* ------------------------------------------------------------------
       Copy to clipboard
       Usage: <button class="wads-btn" data-wads-copy="text-to-copy">Copy</button>
    ------------------------------------------------------------------ */
    function initCopy() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-wads-copy]');
            if (!btn) return;

            var text = btn.dataset.wadsCopy;
            if (!text) return;

            if (!navigator.clipboard) return;

            navigator.clipboard.writeText(text).then(function () {
                var original = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = original; }, 1500);
            });
        });
    }

    /* ------------------------------------------------------------------
       Toggle visibility
       Usage: <button data-wads-toggle="target-id">Toggle</button>
              <div id="target-id" class="wads-hidden">...</div>
    ------------------------------------------------------------------ */
    function initToggleVisibility() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-wads-toggle]');
            if (!btn) return;

            var targetId = btn.dataset.wadsToggle;
            var target   = document.getElementById(targetId);
            if (!target) return;

            target.classList.toggle('wads-hidden');

            var isHidden = target.classList.contains('wads-hidden');
            btn.setAttribute('aria-expanded', String(!isHidden));
        });
    }

    /* ------------------------------------------------------------------
       Dropdown
       Usage: <div class="wads-dropdown">
                <button data-wads-dropdown>Actions</button>
                <div class="wads-dropdown__menu">
                  <a class="wads-dropdown__item" href="#">Option</a>
                </div>
              </div>
    ------------------------------------------------------------------ */
    function initDropdowns() {
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-wads-dropdown]');

            // Close all open dropdowns not related to the click
            document.querySelectorAll('.wads-dropdown.is-open').forEach(function (d) {
                if (!trigger || !d.contains(trigger)) d.classList.remove('is-open');
            });

            if (!trigger) return;
            trigger.closest('.wads-dropdown').classList.toggle('is-open');
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.wads-dropdown.is-open').forEach(function (d) {
                d.classList.remove('is-open');
            });
        });
    }

    /* ------------------------------------------------------------------
       Accordion
       Usage: <div class="wads-accordion">
                <div class="wads-accordion-item">
                  <button class="wads-accordion-trigger" aria-expanded="false">Title
                    <svg class="wads-accordion-trigger__icon" ...>chevron-down</svg>
                  </button>
                  <div class="wads-accordion-panel"><div class="wads-accordion-panel__inner">...</div></div>
                </div>
              </div>
    ------------------------------------------------------------------ */
    function initAccordions() {
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.wads-accordion-trigger');
            if (!trigger) return;

            var isOpen  = trigger.getAttribute('aria-expanded') === 'true';
            var panel   = trigger.nextElementSibling;

            trigger.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            if (panel) panel.classList.toggle('is-open', !isOpen);
        });
    }

    /* ------------------------------------------------------------------
       Search clear button
    ------------------------------------------------------------------ */
    function initSearch() {
        document.addEventListener('input', function (e) {
            var input = e.target.closest('.wads-search__input');
            if (!input) return;

            var clear = input.closest('.wads-search').querySelector('.wads-search__clear');
            if (clear) {
                clear.classList.toggle('is-visible', input.value.length > 0);
            }
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.wads-search__clear');
            if (!btn) return;

            var input = btn.closest('.wads-search').querySelector('.wads-search__input');
            if (input) {
                input.value = '';
                input.focus();
                input.dispatchEvent(new Event('input'));
            }
        });
    }

    /* ------------------------------------------------------------------
       Init
    ------------------------------------------------------------------ */
    function init() {
        initTabs();
        initNotices();
        initModals();
        initCopy();
        initToggleVisibility();
        initDropdowns();
        initAccordions();
        initSearch();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
