(function () {
    'use strict';

    if (window.__PS_SCARCITY_LOADED) return;
    window.__PS_SCARCITY_LOADED = true;

    // -----------------------------------
    // Helpers
    // -----------------------------------

    function readStock() {
        const el = document.querySelector('.product-quantities span[data-stock]');
        if (!el) return null;
        let qty = parseInt(el.dataset.stock, 10);
        return isNaN(qty) ? null : qty;
    }

    function computeBand(box, qty) {
        qty = parseInt(qty, 10) || 0;
        if (qty <= 0) return null;
        if (qty === 1) return 'one';
        const lim10 = parseInt(box.dataset.limitLt10) || 10;
        const lim20 = parseInt(box.dataset.limitLt20) || 20;
        if (qty < lim10) return 'lt10';
        if (qty >= lim10 && qty < lim20) return 'lt20';
        return null;
    }

    function setHtml(box, band, qty) {
        let msg;
        if (band === 'one') msg = box.dataset.msgOne;
        else if (band === 'lt10') msg = box.dataset.msg10.replace('%count%', '<strong>' + qty + '</strong>');
        else if (band === 'lt20') msg = box.dataset.msg20.replace('%count%', '<strong>' + qty + '</strong>');

        box.innerHTML = msg;
        box.style.display = '';
        box.dataset.band = band || '';
        box.dataset.qty = qty;
    }

    function hideBox(box) {
        box.style.display = 'none';
        box.dataset.band = '';
        box.dataset.qty = 0;
    }

    function renderBox(box) {
        if (box.dataset.hookManual === '1') return; // no tocar hooks manuales

        const qty = readStock();
        if (!qty || qty <= 0) { hideBox(box); return; }

        const band = computeBand(box, qty);
        if (!band) { hideBox(box); return; }

        const last = box.dataset.band || '';
        if (band !== last || !box.querySelector('.ps-scarcity-count')) {
            setHtml(box, band, qty);
        } else {
            const countEl = box.querySelector('.ps-scarcity-count');
            if (countEl) countEl.textContent = qty;
            box.dataset.qty = qty;
            box.style.display = '';
        }
    }

    function applyAll() {
        document.querySelectorAll('[data-psscarcity]').forEach(renderBox);
    }

    // -----------------------------------
    // Init
    // -----------------------------------
    function init() {
        applyAll();

        // 1) Detectar cambio de color / combinación usando hash
        window.addEventListener('hashchange', () => setTimeout(applyAll, 20));

        // 2) Escuchar eventos Prestashop
        if (window.prestashop && prestashop.on) {
            prestashop.on('updatedProduct', () => setTimeout(applyAll, 20));
            prestashop.on('updateProduct', () => setTimeout(applyAll, 20));
        }

        // 3) Observador mínimo solo sobre el stock span
        const stockEl = document.querySelector('.product-quantities span[data-stock]');
        if (stockEl && window.MutationObserver) {
            new MutationObserver(() => setTimeout(applyAll, 20))
                .observe(stockEl, { attributes: true, attributeFilter: ['data-stock'] });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

})();
