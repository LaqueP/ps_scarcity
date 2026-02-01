(function () {
    'use strict';

    // Botones de copiar shortcode
    function copyShortcode(idButton, idSource) {
        const btn = document.getElementById(idButton);
        const el  = document.getElementById(idSource);
        if (!btn || !el) return;
        btn.addEventListener('click', () => {
            navigator.clipboard.writeText(el.textContent.trim());
        });
    }

    function init() {
        copyShortcode('psscarcity-copy-smarty', 'psscarcity-short-smarty');
        copyShortcode('psscarcity-copy-id', 'psscarcity-short-id');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
