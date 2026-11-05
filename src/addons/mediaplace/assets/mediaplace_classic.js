/**
 * MediaPlace – Classic Widget Interception
 *
 * Faengt die "Oeffnen"/"Hinzufuegen"/"Ansehen"-Buttons der klassischen
 * REX_MEDIA[n]- und REX_MEDIALIST[n]-Widgets (core/mediapool) per
 * Event-Delegation ab, bevor deren inline onclick (openREXMedia, ...)
 * das alte Popup-Fenster oeffnet, und nutzt stattdessen den MP-Overlay.
 *
 * Bewusst NICHT ueberschrieben werden die globalen Funktionen
 * (openREXMedia, openMediaPool, ...) selbst, da TinyMCE und CKEditor5
 * diese direkt aufrufen und den zurueckgegebenen Popup-Window als
 * jQuery-Eventziel (rex:selectMedia) verwenden. Das waere bei einer
 * globalen Ueberschreibung nicht mehr kompatibel.
 */
(function () {
    'use strict';

    function qs(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function fireChange(el) {
        if (!el) return;
        var evt;
        try { evt = new Event('change', { bubbles: true }); }
        catch (e) { evt = document.createEvent('Event'); evt.initEvent('change', true, true); }
        el.dispatchEvent(evt);
    }

    function actionFromLink(link) {
        var icon = qs('i', link);
        if (!icon) return null;
        if (icon.classList.contains('rex-icon-open-mediapool')) return 'open';
        if (icon.classList.contains('rex-icon-add-media')) return 'add';
        if (icon.classList.contains('rex-icon-view-media')) return 'view';
        return null; // rex-icon-delete-media bleibt unveraendert
    }

    // mediapool/lib/var_media.php und var_medialist.php bauen fuer jeden
    // Funktions-Button ein inline onclick wie
    // "addREXMedia('42', '&rex_file_category=5');return false;" -- das
    // konfigurierte "category"-Arg des Widgets steckt darin als
    // rex_file_category=N. Kein sauberer data-*-Zugriff moeglich (REDAXO-
    // Core rendert das nicht separat aus), deshalb Regex auf das onclick.
    function categoryFromLink(link) {
        var onclick = link.getAttribute('onclick') || '';
        var match = onclick.match(/rex_file_category=(\d+)/);
        return match ? parseInt(match[1], 10) : null;
    }

    // Ermittelt die "Ansehen"-Zieldatei fuer beide Widget-Typen -- genutzt
    // sowohl fuer den normalen Fall (handleMediaWidget/handleMedialistWidget)
    // als auch fuer den Metainfo-Canvas-Fall unten (dort bisher komplett
    // gefehlt, siehe dortiger Kommentar).
    function resolveViewFilename(wrapper) {
        if (wrapper.classList.contains('rex-js-widget-medialist')) {
            var select = qs('select[id^="REX_MEDIALIST_SELECT_"]', wrapper);
            if (!select) return null;
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].selected) return select.options[i].value;
            }
            return null;
        }
        var input = qs('input[id^="REX_MEDIA_"]', wrapper);
        return input ? (input.value || null) : null;
    }

    // ---- REX_MEDIA[n] (Einzelauswahl) ----
    function handleMediaWidget(wrapper, action, link) {
        var input = qs('input[id^="REX_MEDIA_"]', wrapper);
        if (!input) return;

        function setValue(filename) {
            input.value = filename;
            if (window.jQuery) {
                window.jQuery(input).trigger('change');
            } else {
                fireChange(input);
            }
        }

        if (action === 'view' && input.value) {
            MP.openFile(input.value, setValue);
        } else if (action === 'add') {
            // "+": direkt in den Upload-Modus statt erst Browsen -- feste
            // Ziel-Kategorie, falls das Widget mit einem "category"-Arg
            // konfiguriert ist, sonst fragt MP.openUpload() wie gewohnt
            // (aktuelle Kategorie bzw. Modal-Abfrage, siehe doUpload()).
            var categoryId = categoryFromLink(link);
            MP.openUpload(setValue, null !== categoryId ? { categoryId: categoryId } : undefined);
        } else {
            // 'open': normales Browsen/Auswaehlen
            MP.open(setValue);
        }
    }

    // ---- REX_MEDIALIST[n] (Mehrfachauswahl) ----
    function handleMedialistWidget(wrapper, action, link) {
        var select = qs('select[id^="REX_MEDIALIST_SELECT_"]', wrapper);
        if (!select) return;
        var listId = select.id.slice('REX_MEDIALIST_SELECT_'.length);

        function addFilenames(filenames) {
            filenames.forEach(function (filename) {
                var exists = Array.prototype.some.call(select.options, function (o) { return o.value === filename; });
                if (!exists) select.add(new Option(filename, filename));
            });
            if (typeof window.writeREXMedialist === 'function') {
                window.writeREXMedialist(listId);
            }
        }

        if (action === 'add') {
            var categoryId = categoryFromLink(link);
            MP.openUpload(addFilenames, { multiple: true, categoryId: null !== categoryId ? categoryId : undefined });
            return;
        }

        if (action === 'view') {
            var selected = null;
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].selected) { selected = select.options[i]; break; }
            }
            if (selected) {
                MP.openFile(selected.value);
                return;
            }
        }

        // 'open' und 'view' ohne Auswahl: Mehrfachauswahl-Overlay ('add' bereits
        // oben per return behandelt)
        MP.open(addFilenames, { multiple: true });
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a.btn-popup');
        if (!link) return;

        // Im Metainfo-Canvas: eigenes Grid statt REDAXOs Popup nutzen (MP.startMetainfoPick()
        // in mediaplace.js), sonst wuerde das Popup unseren gerade offenen Overlay verdecken.
        // "Ansehen" (das Auge-Icon) ist davon ausgenommen: die Datei ist ja schon
        // ausgewaehlt, es soll kein Auswahl-Modus starten, sondern nur das eigene
        // Detail-Panel (rechte Sidebar, waehrend des Canvas weiterhin sichtbar) auf
        // diese Datei umschalten -- frueher landete "Ansehen" hier faelschlich
        // ebenfalls im Picker-Modus, weil nur auf "irgendeine Aktion", nicht auf die
        // konkrete Aktion geprueft wurde.
        if (link.closest('#mp-metainfo-canvas')) {
            var mcWrapper = link.closest('.rex-js-widget-media, .rex-js-widget-medialist');
            var mcAction = mcWrapper ? actionFromLink(link) : null;
            if (mcWrapper && mcAction && window.MP) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var viewFilename = mcAction === 'view' ? resolveViewFilename(mcWrapper) : null;
                if (viewFilename && typeof window.MP.showFileDetail === 'function') {
                    window.MP.showFileDetail(viewFilename);
                } else if (typeof window.MP.startMetainfoPick === 'function') {
                    window.MP.startMetainfoPick(mcWrapper, mcWrapper.classList.contains('rex-js-widget-medialist'));
                }
            }
            return;
        }

        var wrapper = link.closest('.rex-js-widget-media, .rex-js-widget-medialist');
        if (!wrapper) return;

        var action = actionFromLink(link);
        if (!action) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        if (wrapper.classList.contains('rex-js-widget-medialist')) {
            handleMedialistWidget(wrapper, action, link);
        } else {
            handleMediaWidget(wrapper, action, link);
        }
    }, true); // Capture-Phase: laeuft vor dem inline onclick des Buttons

    // Hinweis: Der "Medienpool"-Popup-Link (mediapool/package.yml: popup: openMediaPool())
    // wird nicht mehr per JS abgefangen, sondern serverseitig in boot.php via
    // PAGES_PREPARED / rex_be_page::setPopup() direkt auf "MP.open()" umgestellt.

})();
