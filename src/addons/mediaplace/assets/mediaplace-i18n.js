/**
 * MediaPlace -- i18n-Schicht (frontend-tauglich, keine REDAXO-Backend-JS-
 * Abhaengigkeit). Die `lang/*.lang`-Dateien sind die EINZIGE Quelle der
 * Wahrheit -- keine zweite, parallel gepflegte Uebersetzungstabelle hier im
 * Script. boot.php loest alle `mediaplace_*`-Schluessel serverseitig ueber
 * rex_i18n::msg() fuer die aktuelle Locale auf (inkl. deren eingebauter
 * Fallback-Kette, z.B. auf de_de, falls ein Schluessel in der aktiven
 * Sprache noch fehlt) und embedded das Ergebnis als JSON in
 * <script id="mp-i18n-data" type="application/json">. Rein PHP-gerendertes
 * JSON in der Seite -- keine REDAXO-Backend-JS-Globals, funktioniert deshalb
 * unveraendert, sobald MediaPlace auch im Frontend eingesetzt wird.
 */
(function (Core) {
    'use strict';

    Core.i18n = Core.i18n || {};

    var dict = {};

    function initLang() {
        var el = document.getElementById('mp-i18n-data');
        if (!el) return;
        try {
            dict = JSON.parse(el.textContent) || {};
        } catch (e) {
            dict = {};
        }
    }

    // {name}-Platzhalter-Interpolation.
    function t(key, vars) {
        var str = dict[key] || key;
        if (vars) {
            Object.keys(vars).forEach(function (k) {
                str = str.replace('{' + k + '}', vars[k]);
            });
        }
        return str;
    }

    // Das Woerterbuch enthaelt rex_i18n::msg()-Ausgaben, also bereits HTML-
    // escapte Strings: t() ist fuer HTML-Sinks gedacht (nicht nochmal
    // escAttr() drumherum). tText() liefert Klartext fuer textContent/title/
    // alert()/Toasts; vars werden dabei unveraendert eingesetzt.
    var ENTITIES = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#039;': "'", '&#39;': "'" };

    function tText(key, vars) {
        var str = String(dict[key] || key).replace(/&(?:amp|lt|gt|quot|#0?39);/g, function (m) {
            return ENTITIES[m];
        });
        if (vars) {
            Object.keys(vars).forEach(function (k) {
                str = str.replace('{' + k + '}', vars[k]);
            });
        }
        return str;
    }

    Core.i18n.t = t;
    Core.i18n.tText = tText;
    Core.i18n.initLang = initLang;
})(window.MPCore = window.MPCore || {});
