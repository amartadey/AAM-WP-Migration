(function () {
    function renameMenu() {
        var el = document.querySelector('#toplevel_page_ai1wm_export .wp-menu-name');
        if (el) {
            el.textContent = 'Aam WP Migration';
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renameMenu);
    } else {
        renameMenu();
    }
})();
