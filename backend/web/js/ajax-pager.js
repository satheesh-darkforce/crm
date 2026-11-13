/**
 * AJAX pagination + column sorting for every list in the backend (loaded by layouts/main.php).
 *
 * A click on a pager link (.pagination a) or a GridView sort link (thead a[data-sort]) inside
 * #main-content loads that URL in the background and swaps only the list's box — the closest
 * [data-ajax-list] element, else the closest .card / .grid-view holding a table. Filters,
 * Select2 widgets and the rest of the page stay as they are. The address bar follows
 * (history.pushState), so refresh, bookmarks and Back / Forward keep working.
 *
 * Left alone: lists inside a Pjax container (they already use AJAX), links with
 * data-no-ajax, modifier-clicks (open in new tab) and anything outside #main-content.
 * Any failure falls back to a normal page load.
 */
(function () {
    'use strict';

    var main = document.getElementById('main-content');
    if (!main || !window.fetch || !window.DOMParser || !window.history || !history.pushState) { return; }

    var LINK = '.pagination a[href], thead a[data-sort][href], th a.asc[href], th a.desc[href]';
    var busy = false;

    /** The box to swap for a link: explicit [data-ajax-list], else the card / grid holding a table. */
    function regionFor(el) {
        var explicit = el.closest('[data-ajax-list]');
        if (explicit) { return explicit; }
        var node = el.closest('.card, .grid-view, .ui-list, .table-responsive');
        while (node && node !== main) {
            if (node.querySelector('table, .ui-table, [data-list-rows]')) { return node; }
            node = node.parentElement ? node.parentElement.closest('.card, .grid-view, .ui-list, .table-responsive') : null;
        }
        return null;
    }

    /** Stable key for a region so the same box can be found in the fetched page. */
    function keyFor(region) {
        if (region.id) { return {id: region.id}; }
        var all = Array.prototype.slice.call(main.querySelectorAll(region.tagName.toLowerCase() + '.' + (region.classList[0] || '')));
        return {sel: region.tagName.toLowerCase() + '.' + (region.classList[0] || ''), index: all.indexOf(region)};
    }

    function findIn(doc, key) {
        var root = doc.getElementById('main-content');
        if (!root) { return null; }
        if (key.id) { return doc.getElementById(key.id); }
        return root.querySelectorAll(key.sel)[key.index] || null;
    }

    function load(url, region, push) {
        if (busy) { return; }
        busy = true;
        var key = keyFor(region);
        region.classList.add('is-ajax-loading');
        region.setAttribute('aria-busy', 'true');

        fetch(url, {credentials: 'same-origin', headers: {'Accept': 'text/html'}})
            .then(function (res) {
                if (!res.ok || res.redirected && res.url.indexOf('/site/login') !== -1) { throw new Error('HTTP ' + res.status); }
                return res.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = findIn(doc, key);
                if (!fresh) { throw new Error('list not found'); }
                region.replaceWith(fresh);
                if (push) { history.pushState({ajaxList: key}, '', url); }
                // Bring the top of the list into view when it is above the screen.
                var top = fresh.getBoundingClientRect().top;
                if (top < 0) { window.scrollBy({top: top - 90, behavior: 'smooth'}); }
                var focusTarget = fresh.querySelector('.pagination .active a, .pagination .active span') || fresh;
                if (focusTarget === fresh) { fresh.setAttribute('tabindex', '-1'); }
                focusTarget.focus({preventScroll: true});
                document.dispatchEvent(new CustomEvent('ajax-list:loaded', {detail: {region: fresh, url: url}}));
            })
            .catch(function () { window.location.href = url; })
            .then(function () { busy = false; });
    }

    main.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var a = e.target.closest(LINK);
        if (!a || !main.contains(a) || a.hasAttribute('data-no-ajax') || a.target === '_blank') { return; }
        if (a.closest('[data-pjax-container]') || a.closest('.modal')) { return; }   // Pjax lists handle themselves
        var li = a.closest('li');
        if (li && (li.classList.contains('disabled') || li.classList.contains('active'))) { e.preventDefault(); return; }
        var url = a.href;
        if (!url || url.indexOf('#') === url.length - 1 || new URL(url, location.href).origin !== location.origin) { return; }
        var region = regionFor(a);
        if (!region) { return; }
        e.preventDefault();
        load(url, region, true);
    });

    // Back / Forward between AJAX pages: reload the list for that address (or the page).
    window.addEventListener('popstate', function (e) {
        var key = e.state && e.state.ajaxList;
        var region = key ? findIn(document, key) : null;
        if (region) { load(location.href, region, false); } else { window.location.reload(); }
    });
    // Mark the first entry so Back to it reloads only the list.
    if (!history.state) {
        var first = main.querySelector('.pagination');
        var r = first ? regionFor(first) : null;
        if (r) { history.replaceState({ajaxList: keyFor(r)}, '', location.href); }
    }
})();
