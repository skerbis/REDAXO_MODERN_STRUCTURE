/**
 * MediaPlace -- generische Utility-Funktionen (reine Funktionen, kein
 * Modul-State). Ausgelagert aus mediaplace.js (Stufe C, Teil 1), gleiches
 * Alias-Prinzip wie mediaplace-api.js. getMediaCacheToken()/
 * withMediaCacheBuster()/mediaThumbSrc() bekommen den Cache-Token-Status
 * (mediaForceCacheTokens/lastLoadedFiles) explizit als Parameter statt ihn
 * per Closure zu lesen -- Aufrufer in mediaplace.js reichen ihre lokalen
 * Variablen weiterhin wie gehabt durch.
 */
(function (Core) {
    'use strict';

    Core.helpers = Core.helpers || {};

    var DEFAULT_MEDIA_PER_PAGE = 30;
    // 1000 = "Alle" im Pro-Seite-Selector -- kein echtes "unbegrenzt" (weder
    // unser eigener Fallback-Endpunkt noch die API-Route erlauben das), aber
    // deckt praktisch jede Kategorie ab; siehe MAX_PER_PAGE in
    // rex_api_mediaplace_media_list.php (dort ebenfalls 1000, exakt dieselbe
    // Grenze -- ein hoeherer Wert hier wuerde serverseitig ohnehin gekappt).
    var MEDIA_PER_PAGE_OPTIONS = [30, 50, 100, 250, 1000];
    var DEFAULT_TILE_SIZE = 220;
    var TILE_SIZE_MIN = 140;
    var TILE_SIZE_MAX = 360;

    function qs(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function qsa(sel, ctx) {
        return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
    }

    function formatBytes(b) {
        b = parseInt(b, 10) || 0;
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
        // GB-Stufe noetig fuer den Zahnrad-Menue-Speicherverbrauch (Summe ueber
        // den GESAMTEN Medienpool, siehe refreshStorageUsage() in core.js) --
        // einzelne Dateien (der bisher einzige Aufrufer) erreichen praktisch
        // nie GB-Groesse, aendert also nichts am bisherigen Verhalten.
        if (b < 1073741824) return (b / 1048576).toFixed(1) + ' MB';
        return (b / 1073741824).toFixed(2) + ' GB';
    }

    function isImage(filename) {
        return /\.(jpe?g|png|gif|webp|svg|avif|bmp|ico)$/i.test(filename || '');
    }

    // Deckungsgleich mit FfmpegIntegration::SUPPORTED_EXTENSIONS (mediaplace)
    // bzw. rex_effect_video_to_webp::VIDEO_TYPES (ffmpeg-Addon).
    function isVideo(filename) {
        return /\.(mp4|m4v|avi|mov|webm)$/i.test(filename || '');
    }

    function fileIcon(filename) {
        var ext = (filename || '').split('.').pop().toLowerCase();
        var icons = {
            pdf: 'fa-file-pdf',
            doc: 'fa-file-word', docx: 'fa-file-word',
            xls: 'fa-file-excel', xlsx: 'fa-file-excel',
            ppt: 'fa-file-powerpoint', pptx: 'fa-file-powerpoint',
            zip: 'fa-file-zipper', rar: 'fa-file-zipper', gz: 'fa-file-zipper',
            mp3: 'fa-file-audio', wav: 'fa-file-audio', ogg: 'fa-file-audio', flac: 'fa-file-audio',
            mp4: 'fa-file-video', avi: 'fa-file-video', mov: 'fa-file-video', webm: 'fa-file-video',
            txt: 'fa-file-lines', csv: 'fa-file-csv', log: 'fa-file-lines',
            html: 'fa-file-code', css: 'fa-file-code', js: 'fa-file-code',
            json: 'fa-file-code', xml: 'fa-file-code', php: 'fa-file-code'
        };
        return 'fa-solid ' + (icons[ext] || 'fa-file');
    }

    function escAttr(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    /**
     * <option>-Liste fuer eine flache, tiefensortierte Kategorienliste
     * (id/name/depth, wie von apiFetchAllCategoriesFlat() geliefert), zur
     * Baumtiefe eingerueckt. &nbsp; statt normaler Leerzeichen: sowohl
     * native <select>-Dropdowns als auch bootstrap-select rendern Optionen
     * ueber eigene Layout-Boxen, die fuehrende ASCII-Leerzeichen kollabieren
     * -- &nbsp; ist kein normaler Whitespace und bleibt sichtbar. Gemeinsam
     * genutzt von den Kategorie-Auswahl-Dialogen in mediaplace.js und
     * mediaplace_widget.js statt pro Aufrufer neu gebaut.
     */
    function buildCategoryOptionsHtml(categories, selectedId) {
        var html = '';
        var selected = selectedId != null ? String(selectedId) : null;
        for (var i = 0; i < (categories || []).length; i++) {
            var cat = categories[i];
            html += '<option value="' + escAttr(String(cat.id)) + '"' + (selected !== null && String(cat.id) === selected ? ' selected' : '') + '>' +
                '&nbsp;&nbsp;&nbsp;&nbsp;'.repeat(cat.depth || 0) + escAttr(cat.name) + '</option>';
        }
        return html;
    }

    function formatDate(v) {
        if (!v) return '–';
        var d = (typeof v === 'number' || /^\d{9,}$/.test(String(v)))
            ? new Date(Number(v) * 1000)
            : new Date(v);
        if (isNaN(d.getTime())) return String(v);
        var pad = function (n) { return n < 10 ? '0' + n : n; };
        return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() +
            ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function getFilenameExtension(filename) {
        var name = String(filename || '');
        var pos = name.lastIndexOf('.');
        if (pos < 0 || pos === name.length - 1) return '';
        return name.substring(pos + 1).toLowerCase();
    }

    function normalizeReplacementExtension(ext) {
        ext = String(ext || '').toLowerCase();
        if (ext === 'jpeg' || ext === 'jpe') return 'jpg';
        return ext;
    }

    function normalizeMediaPerPage(value) {
        var parsed = parseInt(value, 10);
        if (isNaN(parsed)) {
            return DEFAULT_MEDIA_PER_PAGE;
        }

        if (MEDIA_PER_PAGE_OPTIONS.indexOf(parsed) < 0) {
            return DEFAULT_MEDIA_PER_PAGE;
        }

        return parsed;
    }

    function normalizeTileSize(value) {
        var parsed = parseInt(value, 10);
        if (isNaN(parsed)) {
            return DEFAULT_TILE_SIZE;
        }
        return Math.max(TILE_SIZE_MIN, Math.min(parsed, TILE_SIZE_MAX));
    }

    function extensionsCompatible(sourceFilename, targetFilename) {
        var sourceExt = normalizeReplacementExtension(getFilenameExtension(sourceFilename));
        var targetExt = normalizeReplacementExtension(getFilenameExtension(targetFilename));
        if (!sourceExt || !targetExt) return false;
        return sourceExt === targetExt;
    }

    function getReplacementAcceptForFilename(filename) {
        var ext = normalizeReplacementExtension(getFilenameExtension(filename));
        if (ext === 'jpg') return '.jpg,.jpeg';
        if (!ext) return '';
        return '.' + ext;
    }

    function getMediaCacheToken(mediaOrFilename, forceTokens, loadedFiles) {
        forceTokens = forceTokens || {};
        loadedFiles = loadedFiles || [];
        var filenameFromArg = '';

        if (mediaOrFilename && typeof mediaOrFilename === 'object') {
            filenameFromArg = String(mediaOrFilename.filename || '');
            if (filenameFromArg && forceTokens[filenameFromArg]) {
                return String(forceTokens[filenameFromArg]);
            }
            if (mediaOrFilename.updatedate) return String(mediaOrFilename.updatedate);
            if (mediaOrFilename.filesize) return String(mediaOrFilename.filesize);
            return '';
        }

        var filename = String(mediaOrFilename || '');
        if (!filename) return '';

        if (forceTokens[filename]) {
            return String(forceTokens[filename]);
        }

        for (var i = 0; i < loadedFiles.length; i++) {
            var f = loadedFiles[i];
            if (String(f.filename || '') === filename) {
                if (f.updatedate) return String(f.updatedate);
                if (f.filesize) return String(f.filesize);
                break;
            }
        }

        return '';
    }

    function withMediaCacheBuster(url, mediaOrFilename, forceTokens, loadedFiles) {
        var token = getMediaCacheToken(mediaOrFilename, forceTokens, loadedFiles);
        if (!token) return url;
        return url + (url.indexOf('?') === -1 ? '?' : '&') + 'mpv=' + encodeURIComponent(token);
    }

    // mediaBaseUrl kommt von rex_url::media() (PHP, boot.php -> #mp-root
    // data-media-base-url) statt clientseitig geraten zu werden -- ein
    // relativer Pfad wie "../media/" oder ein Split auf "/redaxo/" im
    // aktuellen URL-Pfad (siehe filepond_uploader) waere nur zuverlaessig,
    // solange die aufrufende Seite im Backend in exakt einer bestimmten
    // Verzeichnistiefe liegt -- bricht z.B. bei Unterordner-Installationen
    // oder falls MediaPlace mal aus dem Frontend heraus genutzt wird.
    function mediaThumbSrc(filename, mmType, cacheTokenSource, forceTokens, loadedFiles, mediaBaseUrl) {
        if (/\.svg$/i.test(filename || '')) {
            var base = mediaBaseUrl || '../media/';
            return withMediaCacheBuster(base + encodeURIComponent(filename), cacheTokenSource, forceTokens, loadedFiles);
        }
        return withMediaCacheBuster('index.php?rex_media_type=' + mmType + '&rex_media_file=' + encodeURIComponent(filename), cacheTokenSource, forceTokens, loadedFiles);
    }

    function deepClone(value) {
        try {
            return JSON.parse(JSON.stringify(value));
        } catch (e) {
            return value;
        }
    }

    function isObj(v) {
        return v && typeof v === 'object' && !Array.isArray(v);
    }

    /**
     * Normalisiert Werte fuer den Dirty-Check-Vergleich (hasChanged()). Objekt-
     * Keys mit null/undefined-Wert werden komplett weggelassen, nicht nur auf
     * null vereinheitlicht -- collectJsonValuesFromDetail() liefert fuer jedes
     * bekannte Feld explizit einen Eintrag (auch null, wenn leer), waehrend der
     * vom Server geladene Original-Datensatz nie gesetzte Felder als fehlenden
     * Key liefert (siehe MetainfoJsonStorage::setFieldValue(), das leere Werte
     * per unset() entfernt statt sie als null zu speichern). Ohne dieses
     * Weglassen wuerde {feld: null} vs. {} faelschlich als Aenderung gelten und
     * der Speichern-Button schon direkt nach dem Laden aktiv erscheinen.
     */
    function normalizeCompare(v) {
        if (Array.isArray(v)) {
            return v.map(function (x) { return normalizeCompare(x); });
        }
        if (isObj(v)) {
            var keys = Object.keys(v).sort();
            var out = {};
            for (var i = 0; i < keys.length; i++) {
                var normalized = normalizeCompare(v[keys[i]]);
                if (null === normalized) continue;
                out[keys[i]] = normalized;
            }
            return out;
        }
        if (v === null || v === undefined) return null;
        return v;
    }

    function hasChanged(a, b) {
        return JSON.stringify(normalizeCompare(a)) !== JSON.stringify(normalizeCompare(b));
    }

    function isImageFile(filename) {
        return /\.(jpe?g|png|gif|webp|svg|avif|bmp)$/i.test(filename || '');
    }

    // GIFs (koennten animiert sein) und SVGs (kein Rasterbild) werden nie
    // angefasst, auch bei aktivem Upload-Resize.
    function isResizableImageType(type) {
        return !!type && 0 === type.indexOf('image/') && 'image/gif' !== type && 'image/svg+xml' !== type;
    }

    // Skaliert eine Bilddatei im Browser auf maxWidth/maxHeight herunter (Seiten-
    // verhaeltnis erhalten, kein Hochskalieren), Dateiformat bleibt durch
    // canvas.toBlob(cb, file.type) unveraendert. Bei jedem Fehlschlag (Decode-
    // Fehler, canvas.toBlob liefert nichts oder ein anderes Format als angefragt
    // -- toBlob() faellt bei nicht unterstuetzten Typen still auf image/png
    // zurueck) wird die Originaldatei durchgereicht, der Upload darf daran nie
    // scheitern.
    function resizeImageFile(file, maxWidth, maxHeight) {
        return new Promise(function (resolve) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                var w = img.naturalWidth;
                var h = img.naturalHeight;
                if (!w || !h || (w <= maxWidth && h <= maxHeight)) {
                    resolve(file);
                    return;
                }
                var ratio = Math.min(maxWidth / w, maxHeight / h);
                var targetW = Math.max(1, Math.round(w * ratio));
                var targetH = Math.max(1, Math.round(h * ratio));
                var canvas = document.createElement('canvas');
                canvas.width = targetW;
                canvas.height = targetH;
                var ctx = canvas.getContext('2d');
                if (!ctx) { resolve(file); return; }
                ctx.drawImage(img, 0, 0, targetW, targetH);
                canvas.toBlob(function (blob) {
                    if (!blob || blob.type !== file.type) { resolve(file); return; }
                    var resized;
                    try {
                        resized = new File([blob], file.name, { type: file.type, lastModified: file.lastModified });
                    } catch (e) {
                        resized = blob;
                    }
                    if (file.__mpCategoryId != null) {
                        resized.__mpCategoryId = file.__mpCategoryId;
                    }
                    resolve(resized);
                }, file.type);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                resolve(file);
            };
            img.src = url;
        });
    }

    Core.helpers.qs = qs;
    Core.helpers.qsa = qsa;
    Core.helpers.formatBytes = formatBytes;
    Core.helpers.isImage = isImage;
    Core.helpers.isVideo = isVideo;
    Core.helpers.fileIcon = fileIcon;
    Core.helpers.escAttr = escAttr;
    Core.helpers.buildCategoryOptionsHtml = buildCategoryOptionsHtml;
    Core.helpers.formatDate = formatDate;
    Core.helpers.getFilenameExtension = getFilenameExtension;
    Core.helpers.normalizeReplacementExtension = normalizeReplacementExtension;
    Core.helpers.normalizeMediaPerPage = normalizeMediaPerPage;
    Core.helpers.normalizeTileSize = normalizeTileSize;
    Core.helpers.extensionsCompatible = extensionsCompatible;
    Core.helpers.getReplacementAcceptForFilename = getReplacementAcceptForFilename;
    Core.helpers.getMediaCacheToken = getMediaCacheToken;
    Core.helpers.withMediaCacheBuster = withMediaCacheBuster;
    Core.helpers.mediaThumbSrc = mediaThumbSrc;
    Core.helpers.deepClone = deepClone;
    Core.helpers.isObj = isObj;
    Core.helpers.normalizeCompare = normalizeCompare;
    Core.helpers.hasChanged = hasChanged;
    Core.helpers.isImageFile = isImageFile;
    Core.helpers.isResizableImageType = isResizableImageType;
    Core.helpers.resizeImageFile = resizeImageFile;
})(window.MPCore = window.MPCore || {});
