/**
 * MediaPlace -- API-Schicht (reine Funktionen, kein Modul-State).
 * Ausgelagert aus mediaplace.js (Stufe C, Teil 1): alle Funktionen hier
 * nehmen ihre Daten ueber Parameter/Rueckgabewerte entgegen, ohne auf die
 * geteilten Overlay-Statusvariablen (currentCat, selectedFile, ...)
 * zuzugreifen -- deshalb per einfachem Alias in mediaplace.js einbindbar
 * (var apiFetch = MPCore.api.apiFetch; usw.), ohne dass mediaplace.js
 * selbst umgebaut werden musste.
 */
(function (Core) {
    'use strict';

    Core.api = Core.api || {};

    var API_BASE = '/api/backend/';

    // Schattiert das globale fetch() innerhalb dieses Closures -- alle
    // bestehenden fetch(...)-Aufrufe in dieser Datei (20+ Stellen) laufen
    // dadurch automatisch mit einmaligem Retry bei einem 500er, ohne jede
    // Stelle einzeln anfassen zu muessen. Hintergrund: REDAXOs eigener
    // rex_clang-Cache (src/core/lib/clang/clang.php) ist nicht gegen
    // parallele Requests abgesichert -- unmittelbar nach einem Cache-Clear
    // kann ein Request auf einen gerade neu geschriebenen, kurzzeitig leeren
    // Cache treffen und mit "LogicException: No clang found." (HTTP 500)
    // scheitern. Betrifft REDAXO-weit jeden Request, nicht mediaplace-
    // spezifisch (siehe system.log-Eintraege u.a. zu page=credits) --
    // MediaPlace trifft die seltene Race nur ueberproportional oft, weil es
    // beim Oeffnen mehrere parallele Requests gleichzeitig abfeuert (ein
    // Fetch je Dateityp-Filterpille). Kein Fix der eigentlichen Ursache
    // (die liegt in REDAXO-Core), nur eine Abfederung auf Client-Seite.
    // Nur GET (impliziter Default oder explizit) wird retried -- POST/PATCH/
    // DELETE bewusst NICHT, ein Upload/Update/Delete darf nicht unbeabsichtigt
    // doppelt ausgefuehrt werden, nur weil die erste Antwort zufaellig ein
    // 500er war.
    var nativeFetch = window.fetch.bind(window);

    function fetch(url, opts) {
        var method = (opts && opts.method ? String(opts.method) : 'GET').toUpperCase();
        var request = nativeFetch(url, opts);
        if ('GET' !== method) return request;

        return request.then(function (response) {
            if (response.status < 500) return response;
            return new Promise(function (resolve) {
                setTimeout(resolve, 200);
            }).then(function () {
                return nativeFetch(url, opts);
            });
        });
    }

    // Gemeinsame Response-Behandlung fuer die Medienliste (apiFetchRaw() und
    // apiFetchMediaList()): bei Fehlern den HTTP-Status als err.status
    // anhaengen, damit Aufrufer z.B. 403 (keine Kategorie-Berechtigung)
    // gezielt anders behandeln koennen als "API nicht erreichbar" -- ein
    // reiner Error('HTTP 403') liesse sich vom Aufrufer nicht mehr vom
    // Status unterscheiden.
    function handleJsonResponse(r) {
        if (!r.ok) {
            return r.json().catch(function () { return null; }).then(function (body) {
                var err = new Error((body && body.error) || ('HTTP ' + r.status));
                err.status = r.status;
                // Antwort von REDAXO (JSON) oder von einem Server/einer Firewall davor (HTML)?
                err.fromApi = !!(body && typeof body === 'object' && Object.keys(body).length);
                throw err;
            });
        }
        return r.json();
    }

    // Ab dieser Groesse wird chunked hochgeladen statt in einem Request. Der
    // einfache Weg (POST media, multipart) haengt an upload_max_filesize/
    // post_max_size, die das JS nicht kennt (kein Extra-Request zur Erkennung) --
    // 20 MiB liegt sicher ueber typischen Kleininstallations-Defaults, chunked
    // Upload funktioniert unabhaengig davon, solange jeder Chunk drunter bleibt.
    var CHUNK_UPLOAD_THRESHOLD = 20 * 1024 * 1024;
    var CHUNK_UPLOAD_SIZE = 4 * 1024 * 1024;

    function getCategoriesApiUrl() {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.categoriesUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_categories';
        }
        return baseUrl;
    }

    function apiFetchAllCategoriesFlat() {
        return fetch(getCategoriesApiUrl(), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (json) {
            return Array.isArray(json.categories) ? json.categories : [];
        });
    }

    function getUnusedApiUrl() {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.unusedUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_unused';
        }
        return baseUrl;
    }

    // Prueft eine Liste von Dateinamen (typischerweise eine bereits geladene
    // Ergebnisseite) darauf, welche davon unbenutzt sind -- siehe
    // rex_api_mediaplace_unused.php. Absichtlich pro Seite statt fuer
    // den ganzen Bestand auf einmal (siehe dortiger Kommentar zu den Kosten
    // von rex_mediapool::mediaIsInUse()).
    function apiCheckUnusedMedia(filenames) {
        if (!filenames || !filenames.length) return Promise.resolve([]);
        var url = getUnusedApiUrl() + (getUnusedApiUrl().indexOf('?') === -1 ? '?' : '&') + 'filenames=' + encodeURIComponent(filenames.join(','));
        return fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (json) {
            return Array.isArray(json.unused) ? json.unused : [];
        });
    }

    function getStorageUsageApiUrl() {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.storageUsageUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_storage_usage';
        }
        return baseUrl;
    }

    // Gesamt-Speicherverbrauch des kompletten Medienpools (Zahnrad-Menue,
    // siehe refreshStorageUsage() in core.js) -- siehe Api\StorageUsage.php.
    function apiFetchStorageUsage() {
        return fetch(getStorageUsageApiUrl(), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }

    function apiMoveCategory(catId, newParentId) {
        return fetch(getCategoriesApiUrl(), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ id: catId, parent_id: newParentId })
        })
        .then(function (r) {
            return r.json().then(function (body) {
                if (!r.ok || body.error) throwCategoryApiError(r, body);
                return body;
            });
        });
    }

    function getTagsApiUrl(params) {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.tagsUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_tags';
        }
        var query = [];
        var keys = Object.keys(params || {});
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (params[key] === null || params[key] === undefined || params[key] === '') continue;
            query.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(params[key])));
        }
        if (query.length) {
            baseUrl += (baseUrl.indexOf('?') === -1 ? '?' : '&') + query.join('&');
        }
        return baseUrl;
    }

    function apiCollectionCatalogAction(action, payload) {
        var body = payload && typeof payload === 'object' ? payload : {};
        body.action = action;

        return fetch(getTagsApiUrl({}), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        })
        .then(function (r) {
            if (!r.ok) {
                return r.json().then(function (json) {
                    throw new Error((json && json.error) ? json.error : ('HTTP ' + r.status));
                });
            }
            return r.json();
        });
    }

    function getCategoryBulkApiUrl() {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.categoryBulkUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_category_bulk';
        }
        return baseUrl;
    }

    // Massenaktionen fuer ALLE Dateien einer Kategorie (verschieben/loeschen/
    // zu Sammlung/Tag hinzufuegen), siehe Api\CategoryBulk.php -- categoryId
    // steckt IMMER im Payload (category_id), gleiches action-Feld-Dispatch-
    // Muster wie apiCollectionCatalogAction() oben. Optionaler dritter
    // Parameter signal (AbortSignal) fuer den Cancel-Button im Fortschritts-
    // Modal (showBulkProgressModal() in modules/categories.js) -- bricht den
    // GERADE laufenden Batch-Request hart ab, nicht nur "keinen weiteren
    // starten".
    function apiCategoryBulkAction(action, payload, signal) {
        var body = payload && typeof payload === 'object' ? payload : {};
        body.action = action;

        var fetchOpts = {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        };
        if (signal) fetchOpts.signal = signal;

        return fetch(getCategoryBulkApiUrl(), fetchOpts)
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || json.error) {
                    throw new Error((json && json.error) ? json.error : ('HTTP ' + r.status));
                }
                return json;
            });
        });
    }

    // Optionale KI-Alt-Text-Generierung (siehe AiAltTextService::isAvailable()) --
    // beide URLs bleiben leer/undefiniert am #mp-root, wenn das Feature aus
    // ist oder ai_platform fehlt (boot.php), Aufrufer pruefen das VOR dem
    // Aufruf selbst (modules/ai_alt.js).
    function getAiAltUrl() {
        var root = document.getElementById('mp-root');
        return root ? (root.dataset.aiAltUrl || '') : '';
    }

    function getAiAltBulkUrl() {
        var root = document.getElementById('mp-root');
        return root ? (root.dataset.aiAltBulkUrl || '') : '';
    }

    // Einzeldatei-Generierung fuer den "AI generieren"-Button (Detail-Panel +
    // nativer Canvas) -- schreibt serverseitig NICHT in die Datenbank, siehe
    // Api\AiAltText.php-Docblock. imageData (optional) ist ein clientseitig
    // auf Canvas gerendertes PNG als data:-URL, fuer SVGs (siehe
    // rasterizeSvgToPngDataUrl() in modules/ai_alt.js).
    function apiGenerateAiAltText(filename, imageData) {
        var body = { filename: filename };
        if (imageData) body.imageData = imageData;

        return fetch(getAiAltUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || !json.success) {
                    throw new Error((json && json.error) ? json.error : ('HTTP ' + r.status));
                }
                return json;
            });
        });
    }

    // Kategorieuebergreifende Massengenerierung (Zahnrad-Menue), siehe
    // Api\AiAltBulk.php -- gleiches action-Feld-Dispatch-Muster wie
    // apiCategoryBulkAction() oben.
    function apiAiAltBulkAction(action, payload) {
        var body = payload && typeof payload === 'object' ? payload : {};
        body.action = action;

        return fetch(getAiAltBulkUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || !json.success) {
                    throw new Error((json && json.error) ? json.error : ('HTTP ' + r.status));
                }
                return json;
            });
        });
    }

    // Optionale KI-Auto-Tagging-Vorschlaege (siehe AiAutoTagService::isAvailable())
    // -- URL bleibt leer/undefiniert am #mp-root, wenn das Feature aus ist,
    // ai_platform fehlt oder kein Tag fuer KI freigegeben ist (boot.php),
    // Aufrufer prueft das VOR dem Aufruf selbst (modules/ai_tags.js).
    function getAiAutoTagUrl() {
        var root = document.getElementById('mp-root');
        return root ? (root.dataset.aiAutoTagUrl || '') : '';
    }

    // Einzeldatei-Tag-Vorschlaege fuer den "KI-Tags vorschlagen"-Button im
    // Tag-Widget -- schreibt serverseitig NICHT, siehe Api\AiAutoTag.php-
    // Docblock. imageData (optional) siehe apiGenerateAiAltText() oben.
    function apiSuggestAiTags(filename, imageData) {
        var body = { filename: filename };
        if (imageData) body.imageData = imageData;

        return fetch(getAiAutoTagUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || !json.success) {
                    throw new Error((json && json.error) ? json.error : ('HTTP ' + r.status));
                }
                return json;
            });
        });
    }

    function apiLoadSystemTagsForFiles(filenames) {
        var params = {};
        if (filenames && filenames.length) {
            params.filenames = filenames.join(',');
        }

        return fetch(getTagsApiUrl(params), {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (json) {
            return {
                file_tags: (json && typeof json.file_tags === 'object' && json.file_tags) ? json.file_tags : {},
                catalog: Array.isArray(json.catalog) ? json.catalog : [],
                // Global (nicht auf die aktuell geladene Kategorie beschraenkt),
                // respektiert aber die Kategorie-Rechte des Users -- siehe
                // SystemTagManager::getCollectionCounts()/getTagCounts() serverseitig.
                collection_counts: (json && typeof json.collection_counts === 'object' && json.collection_counts) ? json.collection_counts : {},
                tag_counts: (json && typeof json.tag_counts === 'object' && json.tag_counts) ? json.tag_counts : {}
            };
        });
    }

    function apiFetch(endpoint) {
        return fetch(API_BASE + endpoint, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (json) {
            return json.data || json;
        });
    }

    function apiFetchRaw(endpoint) {
        return fetch(API_BASE + endpoint, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(handleJsonResponse);
    }

    // Uebergangs-Fallback: solange die installierte FriendsOfRedaxo/api-Version
    // media/list noch nicht nach Kategorie-Rechten filtert (boot.php setzt
    // data-api-media-list-secure="0" fuer api <1.3.1, siehe dortiges Changelog
    // und rex_api_mediaplace_media_list.php), wird die Medienliste ueber den
    // eigenen Endpunkt statt direkt ueber /api/backend/media geladen -- gleiche
    // Query-Parameter (filter[category_id], filter[term], page, per_page) und
    // gleiche Antwortstruktur ({data, meta}), daher hier nur die Basis-URL
    // ausgetauscht, kein Aufrufer muss angepasst werden. RUECKBAU-TODO: sobald
    // api ueberall >=1.3.1 ist, kann diese Funktion durch apiFetchRaw() ersetzt
    // und der Fallback-Endpunkt entfernt werden.
    // Gemeinsam von apiFetchMediaList() (Uebergangs-Fallback-Fall) und
    // apiFetchOwnMediaList() (siehe dort, IMMER ueber diesen Weg)
    // genutzt: haengt die Query eines api-Addon-foermigen endpoint-Strings an
    // fallbackUrl an, inkl. page/per_page -> mp_page/mp_per_page-Umbenennung
    // (siehe Kommentar unten).
    function fetchViaMediaListFallback(endpoint, fallbackUrl) {
        // Reines String-Anhaengen wie bei getCategoriesApiUrl()/getUnusedApiUrl();
        // URLSearchParams() kodiert "[" / "]" in filter[category_id]/filter[term]
        // dabei korrekt, ohne eine Basis-URL zu brauchen.
        var query = endpoint.indexOf('?') === -1 ? '' : endpoint.slice(endpoint.indexOf('?') + 1);
        var params = new URLSearchParams(query);
        // "page" ist bei einem rex-api-call-Request (anders als bei der echten
        // api-Addon-Route) reserviert -- rex_be_controller liest $_GET['page']
        // selbst, um die Backend-Seite zu waehlen. Ein &page=1 im Query-String
        // wuerde deshalb als "zeig Backend-Seite '1'" interpretiert und auf die
        // Standardseite umleiten (HTML statt JSON). Server-seitig entsprechend
        // in rex_api_mediaplace_media_list.php als mp_page/mp_per_page erwartet.
        if (params.has('page')) {
            params.set('mp_page', params.get('page'));
            params.delete('page');
        }
        if (params.has('per_page')) {
            params.set('mp_per_page', params.get('per_page'));
            params.delete('per_page');
        }
        var extraQuery = params.toString();
        var separator = fallbackUrl.indexOf('?') === -1 ? '?' : '&';
        var url = fallbackUrl + (extraQuery ? separator + extraQuery : '');

        return fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(handleJsonResponse);
    }

    function apiFetchMediaList(endpoint) {
        var root = document.getElementById('mp-root');
        var secure = root ? root.dataset.apiMediaListSecure === '1' : true;
        var fallbackUrl = root ? root.dataset.mediaListFallbackUrl : null;

        if (secure || !fallbackUrl) {
            return apiFetchRaw(endpoint);
        }

        return fetchViaMediaListFallback(endpoint, fallbackUrl);
    }

    // Sammlungen, "Medien ohne ALT-Text" und Tag-Filter (rex_mediaplace_tags/
    // _media_tags) sind MediaPlace-eigene Konzepte, die FriendsOfRedaxo/api's
    // /media-Route gar nicht kennt -- ein filter[collection]/[alt_missing]/
    // [tags] dort wuerde als unbekannter Parameter still ignoriert, die
    // Antwort waere die ungefilterte Gesamtliste. Anfragen mit einem dieser
    // Filter laufen deshalb IMMER ueber den eigenen Endpunkt
    // (Api\MediaList.php), unabhaengig vom data-api-media-list-secure-
    // Schalter oben (der betrifft nur die generische Kategorie-
    // Rechtefilterung der normalen Medienliste).
    function apiFetchOwnMediaList(endpoint) {
        var root = document.getElementById('mp-root');
        var fallbackUrl = root ? root.dataset.mediaListFallbackUrl : null;
        if (!fallbackUrl) {
            return Promise.reject(new Error('Own media list endpoint not available'));
        }
        return fetchViaMediaListFallback(endpoint, fallbackUrl);
    }

    function apiUpload(file, catId, onProgress) {
        if (file.size > CHUNK_UPLOAD_THRESHOLD) {
            return apiUploadChunked(file, catId, onProgress);
        }
        var fd = new FormData();
        fd.append('file', file);
        // catId -1 means collection mode (no real category) → upload to root (0)
        fd.append('category_id', (catId && catId > 0) ? catId : 0);
        return fetch(API_BASE + 'media', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(apiUploadJsonOrError);
    }

    function apiUploadJsonOrError(r) {
        if (r.ok) return r.json();
        return r.json().catch(function () { return {}; }).then(function (body) {
            var err = new Error(body.error || ('HTTP ' + r.status));
            err.status = r.status;
            // Antwort von REDAXO (JSON) oder von einem Server/einer Firewall davor (HTML)?
            err.fromApi = !!(body && typeof body === 'object' && Object.keys(body).length);
            throw err;
        });
    }

    function apiUploadInit(file, catId) {
        return fetch(API_BASE + 'media/upload', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                filename: file.name,
                size: file.size,
                category_id: (catId && catId > 0) ? catId : 0
            })
        }).then(apiUploadJsonOrError);
    }

    function apiUploadChunk(uploadId, index, blob) {
        return fetch(API_BASE + 'media/upload/' + encodeURIComponent(uploadId) + '/chunk/' + index, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/octet-stream'
            },
            body: blob
        }).then(apiUploadJsonOrError);
    }

    function apiUploadFinalize(uploadId) {
        return fetch(API_BASE + 'media/upload/' + encodeURIComponent(uploadId) + '/finalize', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(apiUploadJsonOrError);
    }

    function apiUploadAbort(uploadId) {
        return fetch(API_BASE + 'media/upload/' + encodeURIComponent(uploadId), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    function apiUploadChunked(file, catId, onProgress) {
        return apiUploadInit(file, catId).then(function (initResp) {
            var uploadId = initResp.upload_id;
            var chunkSize = Math.min(CHUNK_UPLOAD_SIZE, initResp.chunk_size_max || CHUNK_UPLOAD_SIZE);
            var totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));

            function sendChunk(index) {
                if (index >= totalChunks) {
                    return apiUploadFinalize(uploadId);
                }
                var start = index * chunkSize;
                var end = Math.min(start + chunkSize, file.size);
                var blob = file.slice(start, end);
                return apiUploadChunk(uploadId, index, blob).then(function (resp) {
                    var sent = (resp && typeof resp.bytes_received === 'number') ? resp.bytes_received : end;
                    if (typeof onProgress === 'function') {
                        onProgress(sent, file.size);
                    }
                    return sendChunk(index + 1);
                });
            }

            return sendChunk(0).catch(function (err) {
                apiUploadAbort(uploadId).catch(function () {});
                throw err;
            });
        });
    }

    function apiUpdate(filename, data) {
        return fetch(API_BASE + 'media/' + encodeURIComponent(filename) + '/update', {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        }).then(function (r) {
            if (!r.ok) {
                return r.json().catch(function () { return null; }).then(function (body) {
                    var err = new Error((body && body.error) || ('HTTP ' + r.status));
                    err.status = r.status;
                    // Antwort von REDAXO (JSON) oder von einem Server/einer Firewall davor (HTML)?
                    err.fromApi = !!(body && typeof body === 'object' && Object.keys(body).length);
                    throw err;
                });
            }
            return r.json();
        });
    }

    function apiDelete(filename) {
        // REST-Form zuerst (api >= 1.3.1). Viele Web Application Firewalls blocken Pfade mit "/delete";
        // bei älteren api-Versionen (404 "Route not found" / 405) auf die bisherige Route ausweichen.
        function send(url) {
            return fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).then(function (r) {
                if (!r.ok) {
                    return r.json().catch(function () { return {}; }).then(function (body) {
                        var err = new Error(body.error || 'HTTP ' + r.status);
                        err.status = r.status;
                        err.fromApi = !!(body && typeof body === 'object' && Object.keys(body).length);
                        err.routeMissing = (404 === r.status && body.error === 'Route not found') || 405 === r.status;
                        throw err;
                    });
                }
                return r.json();
            });
        }
        var name = encodeURIComponent(filename);
        return send(API_BASE + 'media/' + name).catch(function (err) {
            if (err && err.routeMissing) {
                return send(API_BASE + 'media/' + name + '/delete');
            }
            throw err;
        });
    }


    function getJsonApiUrl(filename) {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.jsonUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_json_metainfo';
        }
        if (filename) {
            baseUrl += (baseUrl.indexOf('?') === -1 ? '?' : '&') + 'filename=' + encodeURIComponent(filename);
        }
        return baseUrl;
    }

    // wantDetail=true laesst den Endpoint zusaetzlich detail_html rendern
    // (siehe rex_api_mediaplace_json_metainfo::handleGet() -- vorher per
    // client-seitig mitgeschicktem "info"-Objekt vom api-Addon signalisiert,
    // jetzt per einfachem Flag, weil der Endpoint die Info-Felder selbst
    // berechnet statt sie vom Client zu uebernehmen).
    function apiLoadJsonMetainfo(filename, wantDetail) {
        var url = getJsonApiUrl(filename);
        if (wantDetail) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + 'render_detail=1';
        }
        return fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (json) {
            return {
                data: (json && typeof json.data === 'object' && json.data) ? json.data : {},
                fields: Array.isArray(json.fields) ? json.fields : [],
                clangs: Array.isArray(json.clangs) ? json.clangs : [],
                system_tags: Array.isArray(json.system_tags) ? json.system_tags : [],
                system_tag_catalog: Array.isArray(json.system_tag_catalog) ? json.system_tag_catalog : [],
                detail_html: typeof json.detail_html === 'string' ? json.detail_html : '',
                title: typeof json.title === 'string' ? json.title : ''
            };
        });
    }

    function apiSaveJsonMetainfo(filename, data) {
        return fetch(getJsonApiUrl(filename), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data || {})
        })
        .then(function (r) {
            if (!r.ok) {
                return r.json().then(function (body) {
                    throw new Error(body.error || 'HTTP ' + r.status);
                });
            }
            return r.json();
        });
    }

    // Echte Metainfo-Feld-Bearbeitung, siehe rex_api_mediaplace_metainfo_form.php
    // (boot.php liefert die URL ueber #mp-root[data-metainfo-form-url]).
    function getMetainfoFormApiUrl(filename) {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.metainfoFormUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_metainfo_form';
        }
        if (filename) {
            baseUrl += (baseUrl.indexOf('?') === -1 ? '?' : '&') + 'file=' + encodeURIComponent(filename);
        }
        return baseUrl;
    }

    // Liefert das von REDAXOs MEDIA_FORM_EDIT-Erweiterungspunkt gerenderte
    // Formular-HTML (echte med_*-Metainfo-Felder) als String.
    function apiLoadMetainfoForm(filename) {
        return fetch(getMetainfoFormApiUrl(filename), {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || !json.success) throw new Error(json.error || 'HTTP ' + r.status);
                return typeof json.html === 'string' ? json.html : '';
            });
        });
    }

    // formData = ein FormData-Objekt aus dem #mp-metainfo-form (echtes
    // multipart/form-data, KEIN JSON -- REDAXOs eigener Metainfo-Speicherpfad
    // liest $_POST direkt, siehe rex_api_mediaplace_metainfo_form.php).
    function apiSaveMetainfoForm(filename, formData) {
        return fetch(getMetainfoFormApiUrl(filename), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || !json.success) throw new Error(json.error || 'HTTP ' + r.status);
                return json;
            });
        });
    }

    // Bildbearbeitung, siehe Api\ImageEdit.php (URL und CSRF-Token aus #mp-root)
    function imageEditRequest(action, filename, body) {
        var root = document.getElementById('mp-root');
        var url = (root && root.dataset.imageEditUrl) || 'index.php?rex-api-call=mediaplace_image_edit';
        url += (url.indexOf('?') === -1 ? '?' : '&') + 'action=' + encodeURIComponent(action) + '&file=' + encodeURIComponent(filename);
        var options = {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        };
        if (body) {
            body.append('_csrf_token', (root && root.dataset.imageEditToken) || '');
            options.method = 'POST';
            options.body = body;
        }
        return fetch(url, options).then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || !json.success) throw new Error(json.error || 'HTTP ' + r.status);
                return json;
            });
        });
    }

    function apiImageEditInfo(filename) {
        return imageEditRequest('info', filename, null);
    }

    function apiImageEditSave(filename, ops, mode, name) {
        var body = new FormData();
        body.append('ops', JSON.stringify(ops));
        body.append('mode', mode);
        body.append('name', name || '');
        return imageEditRequest('save', filename, body);
    }

    function apiImageEditRestore(filename) {
        return imageEditRequest('restore', filename, new FormData());
    }

    // "Video optimieren" (ffmpeg-Integration), siehe
    // rex_api_mediaplace_video_optimize.php. Antwortform kommt 1:1 von
    // ffmpeg's eigener Job-Engine durch (status/job/progress/message/log --
    // kein {success:true}-Wrapper wie bei Metainfo), daher hier nur auf
    // r.ok bzw. ein "error"-Feld pruefen.
    function getOptimizeVideoApiUrl(func, params) {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.optimizeVideoUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_video_optimize';
        }
        var query = 'func=' + encodeURIComponent(func);
        for (var key in params) {
            if (Object.prototype.hasOwnProperty.call(params, key)) {
                query += '&' + key + '=' + encodeURIComponent(params[key]);
            }
        }
        return baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + query;
    }

    function apiStartOptimizeVideo(filename) {
        return fetch(getOptimizeVideoApiUrl('start', { file: filename }), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || json.error) throw new Error(json.error || 'HTTP ' + r.status);
                return json;
            });
        });
    }

    function apiPollOptimizeVideo(jobId) {
        return fetch(getOptimizeVideoApiUrl('status', { job: jobId }), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || (json.error && !json.status)) throw new Error(json.error || 'HTTP ' + r.status);
                return json;
            });
        });
    }

    // Lazy nachgeladene Video-Technikdaten fuer den "Technische Details"-
    // Bereich, siehe rex_api_mediaplace_video_info.php.
    function apiLoadVideoDetails(filename) {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.videoInfoUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_video_info';
        }
        baseUrl += (baseUrl.indexOf('?') === -1 ? '?' : '&') + 'file=' + encodeURIComponent(filename);

        return fetch(baseUrl, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || !json.success) throw new Error(json.error || 'HTTP ' + r.status);
                return json.details;
            });
        });
    }

    // "Bild optimieren" -- anders als beim Video ein einzelner synchroner
    // Request (kein Start/Poll-Zyklus), siehe rex_api_mediaplace_image_optimize.php.
    function apiOptimizeImage(filename) {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.optimizeImageUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_image_optimize';
        }
        baseUrl += (baseUrl.indexOf('?') === -1 ? '?' : '&') + 'func=optimize&file=' + encodeURIComponent(filename);

        return fetch(baseUrl, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || json.error) throw new Error(json.error || 'HTTP ' + r.status);
                return json;
            });
        });
    }

    // ---- Cloud-Provider (siehe rex_api_mediaplace_provider.php) ----
    function getProviderApiUrl(func, params) {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.providerUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_provider';
        }
        var query = 'func=' + encodeURIComponent(func);
        for (var key in params) {
            if (Object.prototype.hasOwnProperty.call(params, key)) {
                query += '&' + key + '=' + encodeURIComponent(params[key]);
            }
        }
        return baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + query;
    }

    function apiFetchProviderEntries(provider, path, search) {
        var params = { provider: provider, path: path || '/' };
        if (search) params.search = search;

        return fetch(getProviderApiUrl('entries', params), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || json.error) throw new Error(json.error || 'HTTP ' + r.status);
                return json; // {data:[...], has_search: bool}
            });
        });
    }

    // Direkt als <img src> genutzt (kein fetch()) -- Fallback aufs Datei-Icon
    // laeuft ueber denselben globalen error-Listener wie bei lokalen Video-/
    // Bild-Vorschaubildern, siehe mediaplace.js.
    function getProviderThumbnailUrl(provider, path) {
        return getProviderApiUrl('thumbnail', { provider: provider, path: path });
    }

    function apiImportProviderFile(provider, path, categoryId) {
        return fetch(getProviderApiUrl('import', { provider: provider, path: path, category_id: categoryId }), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || json.error) throw new Error(json.error || 'HTTP ' + r.status);
                return json; // {filename: "..."}
            });
        });
    }

    // Ersetzt den Inhalt einer BESTEHENDEN lokalen Datei durch eine Cloud-Datei
    // (Gegenstueck zu apiReplaceFile() unten, das vom lokalen Datei-Dialog
    // kommt) -- Api\Provider.php::handleReplace(), legt keine neue rex_media-
    // Zeile an. Gleiches GET-mit-Query-Params-Muster wie apiImportProviderFile()
    // (auch dieses mutiert serverseitig, trotzdem GET, siehe dortiger Endpunkt).
    function apiReplaceFileFromProvider(provider, path, filename) {
        return fetch(getProviderApiUrl('replace', { provider: provider, path: path, filename: filename }), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || json.error) throw new Error(json.error || 'HTTP ' + r.status);
                return json; // {filename: "..."}
            });
        });
    }

    // Mehrfachauswahl-Import ("Ausgewaehlte importieren"/"Alle im Ordner
    // importieren", siehe providers.js) -- category_id bleibt Query-Param
    // wie beim Einzel-Import, paths kommt als JSON-Body (Api\Provider.php,
    // handleImportBatch()).
    function apiImportProviderFilesBatch(provider, paths, categoryId) {
        return fetch(getProviderApiUrl('import_batch', { provider: provider, category_id: categoryId }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ paths: paths })
        })
        .then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok || json.error) throw new Error(json.error || 'HTTP ' + r.status);
                return json; // {results: [{path, success, filename|error}]}
            });
        });
    }

    // Gemeinsame Fehlerbehandlung fuer die vier Kategorie-Endpunkte (Create/
    // Rename/Delete/Move) -- haengt err.status an (wie handleJsonResponse()
    // fuer die Medienliste), damit Aufrufer speziell auf 403 (Rechte-Grenze,
    // siehe MediaPermission::hasParentCategoryAccess()) reagieren und statt
    // des rohen Server-Texts eine verstaendliche Meldung zeigen koennen.
    function throwCategoryApiError(r, body) {
        var err = new Error((body && body.error) || ('HTTP ' + r.status));
        err.status = r.status;
        // Antwort von REDAXO (JSON) oder von einem Server/einer Firewall davor (HTML)?
        err.fromApi = !!(body && typeof body === 'object' && Object.keys(body).length);
        throw err;
    }

    // Läuft bewusst NICHT über das api-Addon (media/category), sondern über
    // MediaPlace's eigenen Endpunkt (siehe rex_api_mediaplace_categories.php,
    // handleAdd()/handleRename()/handleDelete()) -- dessen Rechtepruefung
    // schuetzt die freigegebene Ordnergrenze selbst (Umbenennen/Loeschen
    // braucht Zugriff auf die ELTERN-Kategorie, nicht auf die Kategorie
    // selbst), waehrend das api-Addon nur die Kategorie selbst prueft.
    function apiCreateCategory(name, parentId) {
        return fetch(getCategoriesApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ name: name, parent_id: parentId || 0 })
        })
        .then(function (r) {
            return r.json().then(function (body) {
                if (!r.ok || body.error) throwCategoryApiError(r, body);
                return body;
            });
        });
    }

    function resolveFolderCategories(baseCatId, folderPaths) {
        return apiFetchAllCategoriesFlat().then(function (allCats) {
            var childrenByParent = {};
            allCats.forEach(function (c) {
                if (!childrenByParent[c.parent_id]) childrenByParent[c.parent_id] = {};
                childrenByParent[c.parent_id][String(c.name).toLowerCase()] = c.id;
            });

            var resolved = {}; // folderPath -> Promise<catId>

            function resolveSegments(parts) {
                var fullPath = parts.join('/');
                if (resolved[fullPath]) return resolved[fullPath];

                var parentPromise = parts.length > 1
                    ? resolveSegments(parts.slice(0, -1))
                    : Promise.resolve(baseCatId);

                var segment = parts[parts.length - 1];
                var promise = parentPromise.then(function (parentId) {
                    var byName = childrenByParent[parentId];
                    var existingId = byName ? byName[segment.toLowerCase()] : null;
                    if (existingId != null) return existingId;

                    return apiCreateCategory(segment, parentId).then(function (resp) {
                        var newId = resp && resp.id;
                        if (!childrenByParent[parentId]) childrenByParent[parentId] = {};
                        childrenByParent[parentId][segment.toLowerCase()] = newId;
                        return newId;
                    });
                });

                resolved[fullPath] = promise;
                return promise;
            }

            var sortedPaths = folderPaths.slice().sort(function (a, b) {
                return a.split('/').length - b.split('/').length;
            });

            var result = {};
            var chain = Promise.resolve();
            sortedPaths.forEach(function (path) {
                chain = chain.then(function () {
                    return resolveSegments(path.split('/')).then(function (id) {
                        result[path] = id;
                    });
                });
            });

            return chain.then(function () { return result; });
        });
    }

    // Siehe apiCreateCategory() -- läuft über MediaPlace's eigenen Endpunkt,
    // nicht das api-Addon.
    function apiRenameCategory(catId, name) {
        return fetch(getCategoriesApiUrl(), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            // Kein parent_id-Feld: unterscheidet fuer den Server (execute()'s
            // Dispatch) Rename von Move, die dasselbe PATCH nutzen.
            body: JSON.stringify({ id: catId, name: name })
        })
        .then(function (r) {
            return r.json().then(function (body) {
                if (!r.ok || body.error) throwCategoryApiError(r, body);
                return body;
            });
        });
    }

    // Siehe apiCreateCategory() -- läuft über MediaPlace's eigenen Endpunkt,
    // nicht das api-Addon.
    function apiDeleteCategory(catId) {
        var sep = getCategoriesApiUrl().indexOf('?') === -1 ? '?' : '&';
        return fetch(getCategoriesApiUrl() + sep + 'id=' + encodeURIComponent(catId), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) {
            return r.json().then(function (body) {
                if (!r.ok || body.error) throwCategoryApiError(r, body);
                return body;
            });
        });
    }

    function getReplaceFileApiUrl() {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.replaceFileUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_replace_file';
        }
        return baseUrl;
    }

    // Eigener MediaPlace-Endpunkt statt der api-Addon-Route
    // media/{filename}/update: die akzeptiert dort ausschliesslich PUT/PATCH
    // (RoutePackage/Media.php) -- und genau das ist auf PHP-Installationen mit
    // aktivem (Default) enable_post_data_reading ein echtes Problem: PHP liest
    // den kompletten multipart/form-data-Body bereits VOR jedem Nutzcode aus
    // php://input, um ihn (nur bei POST) in $_FILES zu befuellen. Bei
    // PATCH/PUT wird der Body dabei TROTZDEM restlos konsumiert, aber
    // verworfen, ohne $_FILES zu befuellen -- fuer den servereigenen
    // Multipart-Parser bleibt dann nichts mehr uebrig (verifiziert per Debug-
    // Log + echtem Browser-Request: "Ersetzen" meldete Erfolg, die Datei
    // blieb aber unveraendert, kein Fehler sichtbar). Das api-Addon ist ein
    // fremdes Repo (nicht aenderbar) -- deshalb dieser eigene, schlanke
    // Endpunkt: echtes POST, PHP befuellt $_FILES damit nativ und
    // zuverlaessig, kein eigener Multipart-Parser noetig. Ruft serverseitig
    // dieselbe REDAXO-Core-Methode (rex_media_service::updateMedia()) wie
    // der klassische Medienpool auf, siehe Api\ReplaceFile.php.
    function apiReplaceFile(filename, file) {
        var fd = new FormData();
        fd.append('file', file);
        fd.append('filename', filename);

        return fetch(getReplaceFileApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: fd
        }).then(function (r) {
            if (!r.ok) {
                return r.json().catch(function () { return null; }).then(function (body) {
                    var err = new Error((body && body.error) || ('HTTP ' + r.status));
                    err.status = r.status;
                    // Antwort von REDAXO (JSON) oder von einem Server/einer Firewall davor (HTML)?
                    err.fromApi = !!(body && typeof body === 'object' && Object.keys(body).length);
                    throw err;
                });
            }
            return r.json();
        });
    }


    function getFocuspointApiUrl() {
        var root = document.getElementById('mp-root');
        var baseUrl = root ? root.dataset.focuspointUrl : null;
        if (!baseUrl) {
            baseUrl = 'index.php?rex-api-call=mediaplace_focuspoint';
        }
        return baseUrl;
    }

    // Liefert die fokuspunkt-relevanten Media-Manager-Typen, alle Fokuspunkt-
    // Metainfo-Felder und deren aktuell gespeicherte Werte fuer eine Datei --
    // siehe rex_api_mediaplace_focuspoint::handleInfo().
    function apiLoadFocuspointInfo(filename) {
        var url = getFocuspointApiUrl() + (getFocuspointApiUrl().indexOf('?') === -1 ? '?' : '&') + 'action=info&file=' + encodeURIComponent(filename);
        return fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            if (!r.ok) {
                return r.json().then(function (body) {
                    throw new Error((body && body.error) || ('HTTP ' + r.status));
                });
            }
            return r.json();
        })
        .then(function (json) {
            return {
                types: (json && typeof json.types === 'object' && json.types) ? json.types : {},
                fields: Array.isArray(json.fields) ? json.fields : [],
                current: (json && typeof json.current === 'object' && json.current) ? json.current : {}
            };
        });
    }

    // Speichert einen neuen Fokuspunkt-Wert -- siehe
    // rex_api_mediaplace_focuspoint::handleSave(). $xy im Format
    // "x.x,y.y" (siehe rex_effect_abstract_focuspoint::str2fp() im
    // focuspoint-Addon).
    function apiSaveFocuspoint(filename, metafield, xy) {
        var url = getFocuspointApiUrl() + (getFocuspointApiUrl().indexOf('?') === -1 ? '?' : '&') + 'action=save';
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json'
            },
            body: 'file=' + encodeURIComponent(filename) + '&meta=' + encodeURIComponent(metafield) + '&xy=' + encodeURIComponent(xy)
        })
        .then(function (r) {
            return r.json().then(function (body) {
                if (!r.ok) throw new Error((body && body.error) || ('HTTP ' + r.status));
                return body;
            });
        });
    }

    Core.api.getCategoriesApiUrl = getCategoriesApiUrl;
    Core.api.apiCheckUnusedMedia = apiCheckUnusedMedia;
    Core.api.apiFetchStorageUsage = apiFetchStorageUsage;
    Core.api.apiFetchAllCategoriesFlat = apiFetchAllCategoriesFlat;
    Core.api.apiMoveCategory = apiMoveCategory;
    Core.api.getTagsApiUrl = getTagsApiUrl;
    Core.api.apiCollectionCatalogAction = apiCollectionCatalogAction;
    Core.api.apiCategoryBulkAction = apiCategoryBulkAction;
    Core.api.apiGenerateAiAltText = apiGenerateAiAltText;
    Core.api.apiAiAltBulkAction = apiAiAltBulkAction;
    Core.api.apiSuggestAiTags = apiSuggestAiTags;
    Core.api.apiLoadSystemTagsForFiles = apiLoadSystemTagsForFiles;
    Core.api.apiFetch = apiFetch;
    Core.api.apiFetchRaw = apiFetchRaw;
    Core.api.apiFetchMediaList = apiFetchMediaList;
    Core.api.apiFetchOwnMediaList = apiFetchOwnMediaList;
    Core.api.apiUpload = apiUpload;
    Core.api.apiUploadJsonOrError = apiUploadJsonOrError;
    Core.api.apiUploadInit = apiUploadInit;
    Core.api.apiUploadChunk = apiUploadChunk;
    Core.api.apiUploadFinalize = apiUploadFinalize;
    Core.api.apiUploadAbort = apiUploadAbort;
    Core.api.apiUploadChunked = apiUploadChunked;
    Core.api.apiUpdate = apiUpdate;
    Core.api.apiDelete = apiDelete;
    Core.api.getJsonApiUrl = getJsonApiUrl;
    Core.api.apiLoadJsonMetainfo = apiLoadJsonMetainfo;
    Core.api.apiSaveJsonMetainfo = apiSaveJsonMetainfo;
    Core.api.apiLoadMetainfoForm = apiLoadMetainfoForm;
    Core.api.apiSaveMetainfoForm = apiSaveMetainfoForm;
    Core.api.apiImageEditInfo = apiImageEditInfo;
    Core.api.apiImageEditSave = apiImageEditSave;
    Core.api.apiImageEditRestore = apiImageEditRestore;
    Core.api.apiStartOptimizeVideo = apiStartOptimizeVideo;
    Core.api.apiPollOptimizeVideo = apiPollOptimizeVideo;
    Core.api.apiLoadVideoDetails = apiLoadVideoDetails;
    Core.api.apiOptimizeImage = apiOptimizeImage;
    Core.api.apiFetchProviderEntries = apiFetchProviderEntries;
    Core.api.getProviderThumbnailUrl = getProviderThumbnailUrl;
    Core.api.apiImportProviderFile = apiImportProviderFile;
    Core.api.apiImportProviderFilesBatch = apiImportProviderFilesBatch;
    Core.api.apiReplaceFileFromProvider = apiReplaceFileFromProvider;
    Core.api.apiCreateCategory = apiCreateCategory;
    Core.api.resolveFolderCategories = resolveFolderCategories;
    Core.api.apiRenameCategory = apiRenameCategory;
    Core.api.apiDeleteCategory = apiDeleteCategory;
    Core.api.apiReplaceFile = apiReplaceFile;
    Core.api.apiLoadFocuspointInfo = apiLoadFocuspointInfo;
    Core.api.apiSaveFocuspoint = apiSaveFocuspoint;
})(window.MPCore = window.MPCore || {});
