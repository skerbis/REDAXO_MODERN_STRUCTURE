<?php

/**
 * Ersetzt die klassischen mediapool-Unterseiten (media/upload/structure), siehe
 * boot.php PAGES_PREPARED -- MediaPlace oeffnet hier immer als Vollbild-Overlay,
 * auch wenn diese Seite als klassisches Popup-Fenster aufgerufen wurde (TinyMCE/
 * CKEditor5, REX_MEDIA[n]/REX_MEDIALIST[n]-Widgets -- alle senden dabei
 * 'opener_input_field' mit, siehe openMediaPool()/openREXMedia()/addREXMedia()/
 * openREXMedialist() in mediapool/assets/mediapool.js). In dem Fall wird bei
 * Dateiauswahl der klassische Popup-Vertrag auf dem Opener-Fenster nachgebildet
 * (rex:selectMedia-Event bzw. REX_MEDIALIST-Select-Befuellung), siehe
 * selectMedia()/selectMediaListArray() ebenda -- sonst wuerde TinyMCE nie eine
 * Datei zurueckbekommen.
 *
 * $openerInputField/$fileId kommen, wenn vorhanden, aus dem umschliessenden
 * mediapool/pages/index.php (includeCurrentPageSubPath()-Kontext) -- $fileId
 * ist dort bereits sowohl aus 'file_id' als auch aus 'file_name' aufgeloest
 * (Deep-Links wie ?page=mediapool/media&file_id=127 sollen MediaPlace direkt
 * mit geoeffnetem Detail-Panel fuer genau diese Datei zeigen, siehe openFile()).
 */

$openerInputField = isset($openerInputField) ? (string) $openerInputField : '';
$isMedialist = str_starts_with($openerInputField, 'REX_MEDIALIST_');
$closeHref = rex_url::backendPage('');

$deepLinkFilename = '';
if (isset($fileId) && (int) $fileId > 0) {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT filename FROM ' . rex::getTable('media') . ' WHERE id = ?', [(int) $fileId]);
    if (1 === $sql->getRows()) {
        $deepLinkFilename = (string) $sql->getValue('filename');
    }
}

?>
<div id="mp-takeover" style="padding:80px 20px;text-align:center;color:#777;">
    <i class="fa-solid fa-spinner fa-spin" style="font-size:24px;"></i>
    <noscript>
        <p><?= rex_i18n::msg('mediaplace_takeover_noscript') ?></p>
    </noscript>
</div>
<script nonce="<?= rex_response::getNonce() ?>">
(function () {
    var openerField = <?= json_encode($openerInputField) ?>;
    var isMedialist = <?= json_encode($isMedialist) ?>;
    var closeHref = <?= json_encode($closeHref) ?>;
    var deepLinkFilename = <?= json_encode($deepLinkFilename) ?>;

    function finishSingle(filename) {
        var defaultPrevented = false;
        if (window.opener && window.opener.jQuery) {
            var event = window.opener.jQuery.Event('rex:selectMedia');
            window.opener.jQuery(window).trigger(event, [filename, '']);
            defaultPrevented = event.isDefaultPrevented();
        }
        if (!defaultPrevented && openerField && window.opener && window.opener.document) {
            var input = window.opener.document.getElementById(openerField);
            if (input) {
                input.value = filename;
                if (window.opener.jQuery) {
                    window.opener.jQuery(input).trigger('change');
                }
            }
        }
        window.close();
    }

    function finishMulti(filenames) {
        if (window.opener && window.opener.document && openerField) {
            var openerId = openerField.slice('REX_MEDIALIST_'.length);
            var select = window.opener.document.getElementById('REX_MEDIALIST_SELECT_' + openerId);
            if (select) {
                filenames.forEach(function (filename) {
                    var option = window.opener.document.createElement('OPTION');
                    option.text = filename;
                    option.value = filename;
                    select.options.add(option, select.options.length);
                });
                if (typeof window.opener.writeREXMedialist === 'function') {
                    window.opener.writeREXMedialist(openerId);
                }
            }
        }
        window.close();
    }

    var tries = 0;
    function boot() {
        if (window.MP && typeof window.MP.open === 'function' && typeof window.MP.openFile === 'function') {
            if (isMedialist) {
                // Deep-Link + Mehrfachauswahl kommt beim klassischen Vertrag nicht vor
                // (REX_MEDIALIST view() nutzt file_name nur zur reinen Ansicht, nicht
                // kombiniert mit Mehrfachauswahl) -- deepLinkFilename bleibt hier ungenutzt.
                window.MP.open(finishMulti, { multiple: true, fullscreen: true, onClose: function () { window.close(); } });
            } else if (openerField) {
                window.MP.openFile(deepLinkFilename, finishSingle, { fullscreen: true, onClose: function () { window.close(); } });
            } else if (deepLinkFilename) {
                // Direkter Deep-Link ohne Popup-Kontext, z.B. ?page=mediapool/media&file_id=127:
                // Overlay oeffnet direkt mit sichtbarem Detail-Panel fuer diese Datei. callback
                // bleibt null (kein Select-Vorgang, kein Opener) -- der "Auswaehlen"-Button im
                // Detail-Panel blendet sich nur bei einem echten onSelect-Callback ein.
                window.MP.openFile(deepLinkFilename, null, { fullscreen: true, closeHref: closeHref });
            } else if (window.opener) {
                window.MP.open({ fullscreen: true, onClose: function () { window.close(); } });
            } else {
                window.MP.open({ fullscreen: true, closeHref: closeHref });
            }
            return;
        }
        tries++;
        if (tries > 100) {
            var el = document.getElementById('mp-takeover');
            if (el) el.textContent = <?= json_encode(rex_i18n::msg('mediaplace_takeover_failed')) ?>;
            return;
        }
        setTimeout(boot, 30);
    }

    // WICHTIG: mediaplace.js laedt ueber addJsFile() blockierend im Head-Bereich
    // (kein defer/async), window.MP existiert deshalb oft schon, WAEHREND der
    // Browser diese Seite noch mitten im Body parst -- #mp-root/#mp-i18n-data
    // werden aber erst GANZ AM ENDE des Bodys eingefuegt (boot.php OUTPUT_FILTER,
    // direkt vor dem schliessenden body-Tag). Ein sofortiger boot()-Aufruf haette
    // build() dazu gebracht,
    // #mp-root NICHT zu finden und ein leeres Ersatz-Root ohne jegliche data-*-
    // Attribute anzulegen -- u.a. blieb dadurch MPCore.i18n.initLang() komplett
    // wirkungslos (jeder t()-Aufruf zeigte den rohen Sprachschluessel statt Text).
    // Erst NACH DOMContentLoaded starten, wenn der komplette Body inkl. des
    // Inject-Blocks garantiert geparst ist.
    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
