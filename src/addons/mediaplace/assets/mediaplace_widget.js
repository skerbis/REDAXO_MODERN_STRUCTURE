/**
 * MediaPlace – Widget
 *
 * Ersetzt <input class="mp-widget"> automatisch durch eine
 * visuelle Medienauswahl mit Vorschau, die den MP-Overlay nutzt.
 *
 * Attribute:
 *   data-mp-multiple="true"    → Mehrfachauswahl (kommaseparierte Dateinamen)
 *   data-mp-types="image/*"    → Erlaubte Dateitypen fuer Direkt-Upload (wie
 *                                 natives <input accept>: ".ext", "typ/*" oder
 *                                 "typ/subtyp", kommasepariert). Gilt nicht
 *                                 fuer den Picker (MP.open()).
 *   data-mp-preview="true"     → Vorschau anzeigen (Standard: true)
 *   data-mp-upload="true"      → Direkt-Upload per Drag&Drop/Klick, mit
 *                                 Kategorie-Auswahl-Dialog vor dem Hochladen
 *   data-mp-max="5"            → Maximale Dateianzahl bei Mehrfachauswahl
 *                                 (Add/Upload-Buttons werden deaktiviert, wenn
 *                                 erreicht). Cmd/Ctrl-Klick auf Galerie-Items
 *                                 markiert sie zum gemeinsamen Entfernen ueber
 *                                 den Papierkorb-Button.
 *
 * Wert im Input: Dateiname(n), kommasepariert bei Multi.
 *
 * Beispiel:
 *   <input class="mp-widget" name="image" value="foto.jpg">
 *   <input class="mp-widget" name="gallery" data-mp-multiple="true" value="a.jpg,b.png">
 */
(function () {
    'use strict';

    var t = (window.MPCore && window.MPCore.i18n && window.MPCore.i18n.t) || function (key) { return key; };
    var Core = window.MPCore || {};
    var apiUpload = Core.api && Core.api.apiUpload;
    var apiFetchAllCategoriesFlat = Core.api && Core.api.apiFetchAllCategoriesFlat;
    var isResizableImageType = Core.helpers && Core.helpers.isResizableImageType;
    var resizeImageFile = Core.helpers && Core.helpers.resizeImageFile;
    var buildCategoryOptionsHtml = Core.helpers && Core.helpers.buildCategoryOptionsHtml;

    // Upload-Resize-Einstellungen kommen aus derselben #mp-root-Config wie der
    // Overlay-Upload (boot.php) -- unabhaengig davon gelesen, da dieses Skript
    // ohne den Overlay-Kern laufen koennen muss.
    function uploadResizeConfig() {
        var root = document.getElementById('mp-root');
        return {
            enabled: !!root && root.dataset.featureUploadResize === '1',
            width: (root && parseInt(root.dataset.uploadResizeWidth, 10)) || 2000,
            height: (root && parseInt(root.dataset.uploadResizeHeight, 10)) || 2000
        };
    }

    // rex_url::media() (PHP, boot.php -> #mp-root data-media-base-url) --
    // installationsunabhaengig berechnete Basis-URL fuer Original-Mediendateien,
    // statt sie clientseitig zu raten (z.B. per relativem "../media/"-Pfad oder
    // Split auf "/redaxo/" im aktuellen URL-Pfad, siehe filepond_uploader) --
    // waere nur zuverlaessig, solange die Seite im Backend in exakt einer
    // bestimmten Verzeichnistiefe liegt. Gleiches unabhaengiges Lesen wie
    // uploadResizeConfig() oben, aus demselben Grund.
    function mediaBaseUrl() {
        var root = document.getElementById('mp-root');
        return (root && root.dataset.mediaBaseUrl) || '../media/';
    }

    function maybeResizeFile(file) {
        var cfg = uploadResizeConfig();
        if (!cfg.enabled || !resizeImageFile || !isResizableImageType || !isResizableImageType(file.type)) {
            return Promise.resolve(file);
        }
        return resizeImageFile(file, cfg.width, cfg.height);
    }

    // ---- Erlaubte Dateitypen (data-mp-types) ----
    // Gleiche Syntax wie das native <input accept>: kommaseparierte Liste aus
    // ".ext", "typ/*" (Wildcard-Subtyp) oder "typ/subtyp" (exakt). accept
    // steuert nur den nativen Dateidialog -- bei Drag&Drop muss zusaetzlich
    // selbst geprueft werden, matchesAllowedTypes() deckt beide Wege ab.
    function typeMatchesPattern(file, pattern) {
        pattern = pattern.trim();
        if (!pattern) return true;
        if (pattern.charAt(0) === '.') {
            var ext = pattern.slice(1).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return new RegExp('\\.' + ext + '$', 'i').test(file.name);
        }
        if (pattern.slice(-2) === '/*') {
            return (file.type || '').indexOf(pattern.slice(0, -1)) === 0;
        }
        return file.type === pattern;
    }
    function matchesAllowedTypes(file, typesAttr) {
        if (!typesAttr) return true;
        var patterns = typesAttr.split(',').map(function (p) { return p.trim(); }).filter(Boolean);
        if (!patterns.length) return true;
        for (var i = 0; i < patterns.length; i++) {
            if (typeMatchesPattern(file, patterns[i])) return true;
        }
        return false;
    }

    // ---- Ansicht (Kacheln/Liste), geteilte Praeferenz ueber alle Widgets ----
    function getWidgetViewMode() {
        try {
            return localStorage.getItem('mpw_view') === 'list' ? 'list' : 'grid';
        } catch (e) {
            return 'grid';
        }
    }
    function setWidgetViewMode(mode) {
        try { localStorage.setItem('mpw_view', mode); } catch (e) { /* Storage evtl. nicht verfuegbar (Privatmodus) */ }
    }

    // ---- Helpers ----
    function qs(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }
    function qsa(sel, ctx) {
        return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
    }
    function escAttr(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }
    function isImage(filename) {
        return /\.(jpe?g|png|gif|webp|svg|avif|bmp|ico)$/i.test(filename || '');
    }
    function fileIcon(filename) {
        var ext = (filename || '').split('.').pop().toLowerCase();
        var icons = {
            pdf: 'fa-file-pdf', doc: 'fa-file-word', docx: 'fa-file-word',
            xls: 'fa-file-excel', xlsx: 'fa-file-excel',
            mp3: 'fa-file-audio', wav: 'fa-file-audio', ogg: 'fa-file-audio',
            mp4: 'fa-file-video', avi: 'fa-file-video', mov: 'fa-file-video', webm: 'fa-file-video',
            zip: 'fa-file-zipper', rar: 'fa-file-zipper',
            txt: 'fa-file-lines', csv: 'fa-file-csv',
            html: 'fa-file-code', css: 'fa-file-code', js: 'fa-file-code'
        };
        return 'fa-solid ' + (icons[ext] || 'fa-file');
    }
    function thumbUrl(filename) {
        // SVGs werden vom Media Manager nicht zuverlaessig gerendert -> Original
        // referenzieren, ueber die serverseitig berechnete Basis-URL (siehe
        // mediaBaseUrl() oben), nicht per geratenem relativem Pfad.
        if (/\.svg$/i.test(filename || '')) {
            return mediaBaseUrl() + encodeURIComponent(filename);
        }
        return 'index.php?rex_media_type=rex_media_small&rex_media_file=' + encodeURIComponent(filename);
    }

    // ---- Kategorie-Auswahl fuer Direkt-Upload ----
    // Eigene, vom Overlay unabhaengige Modal-Klassen (.mpw-catpick-*, siehe
    // mediaplace_widget.css) statt der Overlay-Klassen .mp-catpick-* -- letztere
    // sind unter "#mp-overlay ..." gescoped, das Overlay-Root existiert aber
    // erst, nachdem MP.open() einmal gebaut hat, und dieses Skript muss ohne
    // den Overlay-Kern funktionieren.
    function showUploadCategoryPicker(fileCount, onConfirm) {
        var modal = document.createElement('div');
        modal.className = 'mpw-catpick-modal';
        modal.innerHTML =
            '<div class="mpw-catpick-box">' +
            '<div class="mpw-catpick-title"><i class="fa-solid fa-folder-open"></i> ' + escAttr(t('mediaplace_pick_upload_category')) + '</div>' +
            '<p class="mpw-catpick-info">' + escAttr(t('mediaplace_widget_upload_category_hint', { count: fileCount })) + '</p>' +
            '<select class="mpw-catpick-select"><option value="0">' + escAttr(t('mediaplace_root_no_category')) + '</option></select>' +
            '<div class="mpw-catpick-actions">' +
            '<button type="button" class="mpw-catpick-cancel">' + escAttr(t('mediaplace_cancel')) + '</button>' +
            '<button type="button" class="mpw-catpick-confirm">' + escAttr(t('mediaplace_upload')) + '</button>' +
            '</div></div>';
        document.body.appendChild(modal);

        var select = modal.querySelector('.mpw-catpick-select');
        function initSelectpicker() {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.selectpicker) {
                window.jQuery(select).selectpicker({ liveSearch: true, width: '100%' });
            }
        }
        if (apiFetchAllCategoriesFlat) {
            apiFetchAllCategoriesFlat().then(function (cats) {
                select.innerHTML = '<option value="0">' + escAttr(t('mediaplace_root_no_category')) + '</option>' + buildCategoryOptionsHtml(cats);
                initSelectpicker();
            }).catch(function () {
                // Bleibt bei der Stamm-Option, falls die Liste nicht geladen werden kann.
                initSelectpicker();
            });
        } else {
            initSelectpicker();
        }

        modal.querySelector('.mpw-catpick-cancel').addEventListener('click', function () {
            modal.remove();
        });
        modal.querySelector('.mpw-catpick-confirm').addEventListener('click', function () {
            var catId = parseInt(select.value || '0', 10);
            modal.remove();
            onConfirm(catId);
        });
    }

    // ---- Widget Class ----
    function MPWidget(input) {
        this.input = input;
        this.multiple = input.getAttribute('data-mp-multiple') === 'true';
        this.uploadEnabled = input.getAttribute('data-mp-upload') === 'true';
        this.allowedTypes = input.getAttribute('data-mp-types') || '';
        this.maxFiles = parseInt(input.getAttribute('data-mp-max'), 10) || 0;
        // data-mp-view legt die Startansicht dieses einen Widgets fest
        // (Kacheln/Liste) -- ohne explizite Angabe gilt die geteilte
        // Nutzer-Praeferenz (localStorage). Ein spaeterer Klick auf den
        // Umschalter aendert weiterhin global alle Widgets der Seite.
        var explicitView = input.getAttribute('data-mp-view');
        this.viewMode = ('list' === explicitView || 'grid' === explicitView) ? explicitView : getWidgetViewMode();
        this.container = null;
        this.previewWrap = null;
        this.fileInput = null;
        this.progressEl = null;
        this.clearBtn = null;
        this.clearAllBtn = null;
        this.addBtn = null;
        this.uploadBtn = null;
        this.markedForDelete = {};
        this._build();
        this._render();
    }

    MPWidget.prototype._getFiles = function () {
        var val = this.input.value.trim();
        if (!val) return [];
        return val.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    };

    MPWidget.prototype._setFiles = function (files) {
        this.input.value = files.join(',');
        // Trigger change event for REDAXO and other listeners
        var evt;
        try { evt = new Event('change', { bubbles: true }); }
        catch (e) { evt = document.createEvent('Event'); evt.initEvent('change', true, true); }
        this.input.dispatchEvent(evt);
        this._render();
    };

    // data-mp-max begrenzt nur Mehrfachauswahl-Widgets -- Einzelauswahl ist
    // durch die eigene Natur schon auf 1 Datei begrenzt.
    MPWidget.prototype._remainingSlots = function () {
        if (!this.multiple || !this.maxFiles) return Infinity;
        return Math.max(0, this.maxFiles - this._getFiles().length);
    };

    MPWidget.prototype._build = function () {
        // Hide original input
        this.input.style.display = 'none';
        this.input.setAttribute('data-mp-initialized', 'true');

        // Create container
        this.container = document.createElement('div');
        this.container.className = 'mpw-container';

        // Direkt-Upload: kein separates Dropzone-Element -- der gesamte
        // Container ist die Drop-Zone (Galerie, Toolbar, alles), dauerhaft
        // aktiv, nicht nur im Leerzustand.
        if (this.uploadEnabled) {
            this._setupUpload();
        }

        // Preview area
        this.previewWrap = document.createElement('div');
        this.previewWrap.className = 'mpw-previews';
        if ('list' === this.viewMode) {
            this.previewWrap.classList.add('mpw-view-list');
        }
        this.container.appendChild(this.previewWrap);

        // Toolbar
        var toolbar = document.createElement('div');
        toolbar.className = 'mpw-toolbar';

        var self = this;

        // Add button
        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'btn btn-xs btn-default mpw-btn mpw-btn-add';
        addBtn.title = this.multiple ? t('mediaplace_widget_add_multiple') : t('mediaplace_widget_add_single');
        addBtn.innerHTML = '<i class="fa-solid fa-plus"></i>';
        addBtn.addEventListener('click', function () {
            self._openPicker();
        });
        toolbar.appendChild(addBtn);
        this.addBtn = addBtn;

        // Upload-Button -- gleiche Aktion wie Klick auf die Dropzone
        // (self.fileInput.click()), als zusaetzlicher, direkter Einstiegspunkt
        // im Toolbar neben "Vorhandenes auswaehlen". Die Dropzone bleibt die
        // eigentliche Buehne (gross, dauerhaft sichtbar, Drag&Drop-Ziel).
        if (this.uploadEnabled) {
            var uploadBtn = document.createElement('button');
            uploadBtn.type = 'button';
            uploadBtn.className = 'btn btn-xs btn-default mpw-btn mpw-btn-upload';
            uploadBtn.title = t('mediaplace_widget_upload_from_computer');
            uploadBtn.innerHTML = '<i class="fa-solid fa-upload"></i>';
            uploadBtn.addEventListener('click', function () {
                self.fileInput.click();
            });
            toolbar.appendChild(uploadBtn);
            this.uploadBtn = uploadBtn;
        }

        // View-Umschalter Kacheln/Liste -- auch bei Einzelauswahl verfuegbar
        // (kompakte Zeile statt Kachel). Start-Icon richtet sich nach der
        // fuer DIESES Widget ermittelten Ansicht (this.viewMode, siehe
        // Konstruktor: data-mp-view-Override oder geteilte Praeferenz),
        // ein Klick schaltet danach global fuer alle Widgets der Seite um.
        var viewBtn = document.createElement('button');
        viewBtn.type = 'button';
        viewBtn.className = 'btn btn-xs btn-default mpw-btn mpw-btn-view';
        viewBtn.title = t('mediaplace_widget_toggle_view');
        viewBtn.innerHTML = '<i class="fa-solid ' + ('list' === this.viewMode ? 'fa-table-cells' : 'fa-list') + '"></i>';
        viewBtn.addEventListener('click', function () {
            self._toggleView();
        });
        toolbar.appendChild(viewBtn);

        // Papierkorb-Button -- entfernt IMMER nur die markierten Dateien
        // (Cmd/Ctrl-Klick auf Galerie-Items), nie alle. Deaktiviert, solange
        // nichts markiert ist -- ein Mülleimer-Icon, das ohne Auswahl alles
        // löscht, wirkt sonst wie ein Bug (man erwartet, dass er nur das
        // Angeklickte betrifft).
        var clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-xs btn-default mpw-btn mpw-btn-clear';
        clearBtn.title = t('mediaplace_widget_clear_marked_disabled');
        clearBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
        clearBtn.disabled = true;
        clearBtn.addEventListener('click', function () {
            var marked = Object.keys(self.markedForDelete);
            if (!marked.length) return;
            var current = self._getFiles().filter(function (f) { return marked.indexOf(f) === -1; });
            self.markedForDelete = {};
            self._setFiles(current);
        });
        toolbar.appendChild(clearBtn);

        // "Leeren"-Button -- eigenstaendig, immer rot (REDAXOs .btn-delete-
        // Konvention), entfernt unconditional ALLE Dateien nach Bestaetigung.
        // Bewusst optisch komplett anders als der Papierkorb, damit die beiden
        // Aktionen (einzelne markierte vs. wirklich alles) nicht verwechselt
        // werden koennen.
        var clearAllBtn = document.createElement('button');
        clearAllBtn.type = 'button';
        clearAllBtn.className = 'btn btn-xs btn-delete mpw-btn mpw-btn-clear-all';
        clearAllBtn.title = t('mediaplace_widget_clear_all');
        clearAllBtn.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + escAttr(t('mediaplace_widget_clear_all'));
        clearAllBtn.addEventListener('click', function () {
            if (!self._getFiles().length) return;
            if (!confirm(t('mediaplace_widget_clear_all_confirm'))) return;
            self.markedForDelete = {};
            self._setFiles([]);
        });
        toolbar.appendChild(clearAllBtn);
        this.clearAllBtn = clearAllBtn;
        this.clearBtn = clearBtn;

        this.container.appendChild(toolbar);

        // Insert after input
        this.input.parentNode.insertBefore(this.container, this.input.nextSibling);

        // Drag & Drop reorder for multi
        if (this.multiple) {
            this._initDragSort();
        }
    };

    MPWidget.prototype._openPicker = function () {
        var self = this;
        if (self.multiple) {
            // Multi: open in multi-select mode, receive array of filenames
            MP.open(function (filenames) {
                var current = self._getFiles();
                var skipped = 0;
                for (var i = 0; i < filenames.length; i++) {
                    if (self.maxFiles && current.length >= self.maxFiles) {
                        skipped += filenames.length - i;
                        break;
                    }
                    if (current.indexOf(filenames[i]) === -1) {
                        current.push(filenames[i]);
                    }
                }
                self._setFiles(current);
                if (skipped > 0) {
                    alert(t('mediaplace_widget_max_files_reached', { max: self.maxFiles }));
                }
            }, { multiple: true });
        } else {
            // Single: open in single-select mode
            MP.open(function (filename) {
                self._setFiles([filename]);
            });
        }
    };

    // Wechselt Kacheln/Liste global fuer alle Widgets auf der Seite (nicht nur
    // dieses), damit mehrere Galerie-Felder (z.B. MBlock-Wiederholung)
    // konsistent bleiben -- Icon zeigt jeweils den Zielzustand des naechsten Klicks.
    MPWidget.prototype._toggleView = function () {
        var next = 'list' === getWidgetViewMode() ? 'grid' : 'list';
        setWidgetViewMode(next);
        qsa('.mpw-previews').forEach(function (el) {
            el.classList.toggle('mpw-view-list', 'list' === next);
        });
        qsa('.mpw-btn-view i').forEach(function (icon) {
            icon.className = 'fa-solid ' + ('list' === next ? 'fa-table-cells' : 'fa-list');
        });
    };

    // ---- Direkt-Upload ----
    // Kein eigenes Dropzone-Element -- der gesamte Container (Galerie,
    // Toolbar, alles) ist die Drop-Zone. Ein Drag-Zaehler statt eines einzelnen
    // dragover/dragleave-Paars ist noetig, weil dragleave bereits beim
    // Ueberqueren von Kind-Elementen (Galerie-Items, Buttons) feuert -- ohne
    // Zaehler wuerde die Hervorhebung waehrend des Ziehens ueber den Container
    // flackern.
    MPWidget.prototype._setupUpload = function () {
        var self = this;
        var dragCounter = 0;

        this.fileInput = document.createElement('input');
        this.fileInput.type = 'file';
        this.fileInput.style.display = 'none';
        if (this.multiple) this.fileInput.multiple = true;
        if (this.allowedTypes) this.fileInput.accept = this.allowedTypes;
        this.fileInput.addEventListener('change', function () {
            self._handleIncomingFiles(this.files);
            this.value = '';
        });
        this.container.appendChild(this.fileInput);

        // Schmale, nur waehrend eines laufenden Uploads sichtbare Statuszeile --
        // kein dauerhaftes Dropzone-Element, nur ein Fortschrittshinweis.
        this.progressEl = document.createElement('div');
        this.progressEl.className = 'mpw-upload-progress';
        this.progressEl.style.display = 'none';
        this.container.appendChild(this.progressEl);

        this.container.addEventListener('dragenter', function (e) {
            e.preventDefault();
            dragCounter++;
            self.container.classList.add('mpw-container-dragover');
        });
        this.container.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
        });
        this.container.addEventListener('dragleave', function () {
            dragCounter = Math.max(0, dragCounter - 1);
            if (dragCounter === 0) {
                self.container.classList.remove('mpw-container-dragover');
            }
        });
        this.container.addEventListener('drop', function (e) {
            e.preventDefault();
            dragCounter = 0;
            self.container.classList.remove('mpw-container-dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                self._handleIncomingFiles(e.dataTransfer.files);
            }
        });
    };

    MPWidget.prototype._handleIncomingFiles = function (fileList) {
        var self = this;
        var files = Array.prototype.slice.call(fileList || []);
        if (!files.length) return;

        if (this.allowedTypes) {
            var rejected = [];
            files = files.filter(function (f) {
                var ok = matchesAllowedTypes(f, self.allowedTypes);
                if (!ok) rejected.push(f.name);
                return ok;
            });
            if (rejected.length) {
                alert(t('mediaplace_widget_upload_type_rejected', { files: rejected.join(', ') }));
            }
            if (!files.length) return;
        }

        if (!this.multiple) {
            files = files.slice(0, 1);
        } else {
            var remaining = this._remainingSlots();
            if (remaining <= 0) {
                alert(t('mediaplace_widget_max_files_reached', { max: this.maxFiles }));
                return;
            }
            if (files.length > remaining) {
                alert(t('mediaplace_widget_max_files_reached', { max: this.maxFiles }));
                files = files.slice(0, remaining);
            }
        }

        showUploadCategoryPicker(files.length, function (catId) {
            self._uploadFiles(files, catId);
        });
    };

    MPWidget.prototype._uploadFiles = function (files, catId) {
        var self = this;
        if (!apiUpload) return;

        var progressEl = this.progressEl;
        var total = files.length;
        var done = 0;
        var failed = 0;
        var uploaded = [];

        // Balken + Text statt nur eines Textknotens (Nutzer-Feedback: der
        // Direkt-Upload im Widget wirkte "stumm" im Vergleich zum Overlay,
        // das einen echten Fortschrittsbalken + eine kurz sichtbar bleibende
        // Erfolgsmeldung hat -- gleiches Muster hier, nur kompakter fuers
        // kleine Widget-Format). onProgress wird jetzt tatsaechlich an
        // apiUpload() durchgereicht (vorher ungenutzt mit null uebergeben).
        progressEl.innerHTML =
            '<div class="mpw-upload-progress-text"></div>' +
            '<div class="mpw-upload-progress-bar"><div class="mpw-upload-progress-fill"></div></div>';
        var textEl = progressEl.querySelector('.mpw-upload-progress-text');
        var fillEl = progressEl.querySelector('.mpw-upload-progress-fill');
        progressEl.classList.remove('mpw-upload-progress-done');
        progressEl.style.display = '';

        function setProgress(fileFraction) {
            textEl.textContent = t('mediaplace_upload_summary', { done: done, total: total });
            var pct = Math.round(((done + failed + (fileFraction || 0)) / total) * 100);
            fillEl.style.width = Math.min(100, Math.max(0, pct)) + '%';
        }
        setProgress(0);

        function uploadNext(idx) {
            if (idx >= total) {
                if (uploaded.length) {
                    if (self.multiple) {
                        var current = self._getFiles();
                        for (var i = 0; i < uploaded.length; i++) {
                            if (current.indexOf(uploaded[i]) === -1) current.push(uploaded[i]);
                        }
                        self._setFiles(current);
                    } else {
                        // Single-Modus: Upload ersetzt die aktuelle Datei, wie
                        // _openPicker() es fuer die Bildauswahl auch tut.
                        self._setFiles([uploaded[0]]);
                    }
                }
                var summary = t('mediaplace_upload_summary', { done: done, total: total });
                if (failed > 0) summary += t('mediaplace_upload_failed_suffix', { count: failed });
                progressEl.classList.add('mpw-upload-progress-done');
                textEl.innerHTML = '<i class="fa-solid ' + (failed > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check') + '"></i> ' + escAttr(summary);
                // Kurz sichtbar stehen bleiben statt sofort zu verschwinden
                // (gleiches Timing wie die Erfolgsmeldung im Overlay), sonst
                // wirkt ein erfolgreicher Upload wie "nichts ist passiert".
                setTimeout(function () {
                    progressEl.style.display = 'none';
                    progressEl.classList.remove('mpw-upload-progress-done');
                }, 1500);
                return;
            }

            maybeResizeFile(files[idx])
                .then(function (fileToSend) {
                    return apiUpload(fileToSend, catId, function (sent, fileTotal) {
                        setProgress(fileTotal ? sent / fileTotal : 0);
                    });
                })
                .then(function (resp) {
                    done++;
                    uploaded.push((resp && resp.filename) ? resp.filename : files[idx].name);
                })
                .catch(function (err) {
                    failed++;
                    console.error('MPWidget upload failed:', files[idx].name, err);
                })
                .then(function () {
                    setProgress(0);
                    uploadNext(idx + 1);
                });
        }

        uploadNext(0);
    };

    MPWidget.prototype._render = function () {
        var files = this._getFiles();
        var self = this;
        var html = '';

        if (files.length === 0) {
            if (this.uploadEnabled) {
                html = '<div class="mpw-empty mpw-empty-upload">' +
                    '<i class="fa-solid fa-cloud-arrow-up"></i><br>' +
                    escAttr(t('mediaplace_widget_dropzone_text')) +
                    '</div>';
            } else {
                html = '<div class="mpw-empty">' +
                    '<i class="fa-solid fa-photo-film"></i> ' +
                    (this.multiple ? t('mediaplace_widget_empty_multiple') : t('mediaplace_widget_empty_single')) +
                    '</div>';
            }
        } else {
            for (var i = 0; i < files.length; i++) {
                html += this._renderItem(files[i], i);
            }
        }

        this.previewWrap.innerHTML = html;

        // Leerzustand mit aktivem Upload ist selbst klickbar (oeffnet den
        // Dateidialog wie der Upload-Button im Toolbar) -- der Hinweistext
        // "...oder klicken zum Hochladen" muss auch wirklich klickbar sein.
        var emptyUpload = qs('.mpw-empty-upload', this.previewWrap);
        if (emptyUpload) {
            emptyUpload.addEventListener('click', function () {
                self.fileInput.click();
            });
        }

        // Bind remove buttons
        qsa('.mpw-item-remove', this.previewWrap).forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var fn = this.getAttribute('data-filename');
                var current = self._getFiles();
                current = current.filter(function (f) { return f !== fn; });
                self._setFiles(current);
            });
        });

        // Details ansehen: oeffnet den Overlay direkt im Detail-Panel des
        // Mediums (Browse-only, kein Callback -- siehe MP.openFile()),
        // ohne die Widget-Auswahl selbst zu veraendern. stopPropagation()
        // noetig, sonst wuerde der Item-Klick-Handler unten (Single-Modus)
        // zusaetzlich den Picker zum Ersetzen oeffnen. Zwei Buttons fuehren
        // dieselbe Aktion aus (Lupe oben links, Auge zentriert auf dem
        // Vorschaubild) -- die Lupe wird laut Nutzer-Feedback leicht
        // uebersehen, bleibt aber als zusaetzlicher Trigger bestehen.
        qsa('.mpw-item-view, .mpw-item-eye', this.previewWrap).forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var fn = this.getAttribute('data-filename');
                if (fn && typeof MP !== 'undefined' && MP.openFile) {
                    MP.openFile(fn);
                }
            });
        });

        // Verwaiste Markierungen entfernen (Datei z.B. per x-Button entfernt,
        // waehrend andere noch markiert waren).
        Object.keys(this.markedForDelete).forEach(function (fn) {
            if (files.indexOf(fn) === -1) delete self.markedForDelete[fn];
        });

        // Bind item clicks → Klick markiert genau dieses eine Medium (hebt
        // alle anderen Markierungen auf), Cmd/Ctrl-Klick fuegt es zur
        // bestehenden Mehrfachmarkierung hinzu/entfernt es daraus, ohne die
        // uebrigen Markierungen zu loeschen -- uebliche Datei-Browser-Konvention.
        // Im Single-Modus gibt es keine Markierung (Ersetzen via _openPicker()).
        qsa('.mpw-item', this.previewWrap).forEach(function (item) {
            var fn = item.getAttribute('data-filename');
            if (self.multiple && self.markedForDelete[fn]) {
                item.classList.add('mpw-item-marked');
            }
            item.addEventListener('click', function (e) {
                if (!self.multiple) {
                    self._openPicker();
                    return;
                }
                e.preventDefault();
                if (e.metaKey || e.ctrlKey) {
                    if (self.markedForDelete[fn]) {
                        delete self.markedForDelete[fn];
                        item.classList.remove('mpw-item-marked');
                    } else {
                        self.markedForDelete[fn] = true;
                        item.classList.add('mpw-item-marked');
                    }
                } else {
                    var alreadyOnlyThis = self.markedForDelete[fn] && Object.keys(self.markedForDelete).length === 1;
                    self.markedForDelete = {};
                    qsa('.mpw-item-marked', self.previewWrap).forEach(function (el) {
                        el.classList.remove('mpw-item-marked');
                    });
                    if (!alreadyOnlyThis) {
                        self.markedForDelete[fn] = true;
                        item.classList.add('mpw-item-marked');
                    }
                }
                self._updateToolbarState();
            });
        });

        this._updateToolbarState();
    };

    // Sichtbarkeit/Beschriftung des Clear-Buttons (alle vs. nur Markierte) und
    // Sperren von Add/Upload bei erreichtem data-mp-max.
    MPWidget.prototype._updateToolbarState = function () {
        var files = this._getFiles();
        var markedCount = Object.keys(this.markedForDelete).length;

        if (this.clearBtn) {
            this.clearBtn.style.display = files.length ? '' : 'none';
            this.clearBtn.disabled = markedCount === 0;
            this.clearBtn.title = markedCount
                ? t('mediaplace_widget_clear_marked', { count: markedCount })
                : t('mediaplace_widget_clear_marked_disabled');
            this.clearBtn.classList.toggle('mpw-btn-clear-marked', markedCount > 0);
        }
        if (this.clearAllBtn) {
            this.clearAllBtn.style.display = files.length ? '' : 'none';
        }

        var atLimit = this._remainingSlots() <= 0;
        if (this.addBtn) {
            this.addBtn.disabled = atLimit;
            this.addBtn.title = atLimit ? t('mediaplace_widget_max_files_reached', { max: this.maxFiles }) : (this.multiple ? t('mediaplace_widget_add_multiple') : t('mediaplace_widget_add_single'));
        }
        if (this.uploadBtn) {
            this.uploadBtn.disabled = atLimit;
            this.uploadBtn.title = atLimit ? t('mediaplace_widget_max_files_reached', { max: this.maxFiles }) : t('mediaplace_widget_upload_from_computer');
        }
    };

    MPWidget.prototype._renderItem = function (filename, index) {
        var preview;
        if (isImage(filename)) {
            preview = '<img src="' + escAttr(thumbUrl(filename)) + '" alt="' + escAttr(filename) + '" draggable="false">';
        } else {
            preview = '<div class="mpw-item-icon"><i class="' + fileIcon(filename) + '"></i></div>';
        }

        var html = '<div class="mpw-item" data-filename="' + escAttr(filename) + '" data-index="' + index + '"' +
            (this.multiple ? ' draggable="true"' : '') + '>';
        html += '<div class="mpw-item-preview">' + preview + '</div>';
        html += '<div class="mpw-item-bottom">' +
            '<button type="button" class="mpw-item-eye" data-filename="' + escAttr(filename) + '" title="' + escAttr(t('mediaplace_widget_view_details')) + '">' +
            '<i class="fa-solid fa-eye"></i></button>' +
            '<div class="mpw-item-name">' + escAttr(filename) + '</div>' +
            '</div>';
        html += '<button type="button" class="mpw-item-view" data-filename="' + escAttr(filename) + '" title="' + escAttr(t('mediaplace_widget_view_details')) + '">' +
            '<i class="fa-solid fa-magnifying-glass"></i></button>';
        html += '<button type="button" class="mpw-item-remove" data-filename="' + escAttr(filename) + '" title="' + escAttr(t('mediaplace_widget_remove')) + '">' +
            '<i class="fa-solid fa-xmark"></i></button>';
        html += '</div>';
        return html;
    };

    // ---- Drag & Drop Sort (Multi only) ----
    MPWidget.prototype._initDragSort = function () {
        var self = this;
        var dragItem = null;

        this.previewWrap.addEventListener('dragstart', function (e) {
            var item = e.target.closest('.mpw-item');
            if (!item) return;
            dragItem = item;
            item.classList.add('mpw-item-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', item.getAttribute('data-index'));
        });

        this.previewWrap.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var target = e.target.closest('.mpw-item');
            if (target && target !== dragItem) {
                var rect = target.getBoundingClientRect();
                var mid = rect.left + rect.width / 2;
                if (e.clientX < mid) {
                    target.classList.add('mpw-drop-before');
                    target.classList.remove('mpw-drop-after');
                } else {
                    target.classList.remove('mpw-drop-before');
                    target.classList.add('mpw-drop-after');
                }
            }
        });

        this.previewWrap.addEventListener('dragleave', function (e) {
            var item = e.target.closest('.mpw-item');
            if (item) {
                item.classList.remove('mpw-drop-before', 'mpw-drop-after');
            }
        });

        this.previewWrap.addEventListener('drop', function (e) {
            e.preventDefault();
            qsa('.mpw-item', self.previewWrap).forEach(function (el) {
                el.classList.remove('mpw-drop-before', 'mpw-drop-after');
            });

            if (!dragItem) return;
            var target = e.target.closest('.mpw-item');
            if (!target || target === dragItem) return;

            var files = self._getFiles();
            var fromIdx = parseInt(dragItem.getAttribute('data-index'), 10);
            var toIdx = parseInt(target.getAttribute('data-index'), 10);

            // Reorder array
            var moved = files.splice(fromIdx, 1)[0];
            var rect = target.getBoundingClientRect();
            var mid = rect.left + rect.width / 2;
            var insertIdx = e.clientX < mid ? toIdx : toIdx + 1;
            if (fromIdx < toIdx) insertIdx--;
            if (insertIdx < 0) insertIdx = 0;
            files.splice(insertIdx, 0, moved);
            self._setFiles(files);
        });

        this.previewWrap.addEventListener('dragend', function () {
            if (dragItem) {
                dragItem.classList.remove('mpw-item-dragging');
                dragItem = null;
            }
            qsa('.mpw-item', self.previewWrap).forEach(function (el) {
                el.classList.remove('mpw-drop-before', 'mpw-drop-after');
            });
        });
    };

    // ---- Auto-Init ----

    /**
     * Initialize widgets – optionally scoped to a container (for MBlock support).
     * When MBlock clones a block, the cloned DOM already contains the old
     * .mpw-container and the hidden input has data-mp-initialized.
     * We must:
     *   1. Remove any cloned .mpw-container inside the scope
     *   2. Clear data-mp-initialized so the input gets re-initialized
     *   3. Build fresh widget instances
     */
    function initWidgets(scope) {
        // Eigenstaendiger Aufruf noetig, da dieses Skript unabhaengig vom
        // Overlay-Kern (mediaplace.js, dessen build() erst beim Oeffnen des
        // Overlays laeuft) auf Widgets auf der Seite reagiert -- idempotent,
        // mehrfacher Aufruf (z.B. auch spaeter durch build()) ist unproblematisch.
        if (window.MPCore && window.MPCore.i18n) {
            window.MPCore.i18n.initLang();
        }

        var root = scope || document;

        // MBlock cleanup: remove cloned widget containers & reset flag
        qsa('input.mp-widget[data-mp-initialized]', root).forEach(function (input) {
            // The container sits right after the hidden input
            var next = input.nextElementSibling;
            if (next && next.classList.contains('mpw-container')) {
                next.parentNode.removeChild(next);
            }
            input.removeAttribute('data-mp-initialized');
            input.style.display = '';
        });

        // Init all un-initialized widgets in scope
        qsa('input.mp-widget', root).forEach(function (input) {
            if (input.getAttribute('data-mp-initialized')) return;
            new MPWidget(input);
        });
    }

    // Init on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initWidgets(); });
    } else {
        initWidgets();
    }

    // Re-init on REDAXO rex:ready (MBlock, Gridblock, etc.)
    // MBlock triggers $(container).trigger('rex:ready', [container])
    // REDAXO core triggers $(document).trigger('rex:ready', [container])
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('rex:ready', function (e, container) {
            // container is a jQuery object or undefined
            var scope = container && container.length ? container[0] : null;
            initWidgets(scope);
        });
    }

    // Public API for manual init
    window.MPWidget = {
        init: function (scope) {
            initWidgets(scope || null);
        }
    };

})();
