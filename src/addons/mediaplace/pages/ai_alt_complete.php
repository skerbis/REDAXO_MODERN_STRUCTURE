<?php

/**
 * Automatische, vollstaendige ALT-Text-Vervollstaendigung fuer ALLE Medien
 * ohne ALT-Text (in irgendeiner der konfigurierten Sprachen -- siehe
 * AltTextStatus::isClassicAltValueMissingAnyLanguage()/isOwnValueEmpty()).
 * Schreibt DIREKT (kein Review-Schritt wie das "AI Bulk Management"-Panel
 * im Overlay), siehe Api\AiAltComplete.
 *
 * Zwei Modi, analog pages/thumb_warmup.php:
 * - Hintergrund (empfohlen): startet Command\AiAltComplete als vom Request
 *   abgekoppelten "php bin/console"-Prozess (shell_exec()) -- laeuft
 *   weiter, auch wenn der Tab geschlossen wird. Anders als bei
 *   thumb_warmup (wget auf eine oeffentliche Thumbnail-URL) braucht eine
 *   ALT-Text-Generierung echte PHP-Logik (KI-API-Call, DB-Schreiben),
 *   deshalb ein echter PHP-Subprozess statt eines simplen wget-Aufrufs.
 * - Im Browser: treibt die Batches selbst per wiederholtem fetch(), Tab
 *   muss offen bleiben. Automatischer Fallback, falls shell_exec() auf dem
 *   Server nicht verfuegbar ist.
 *
 * Fuer einen wirklich unbeaufsichtigten, periodischen Lauf (kein Admin
 * muss die Seite je aufrufen) siehe stattdessen den registrierten
 * Cronjob-Typ AiAltCompleteCronjob (boot.php) -- diese Seite hier ist fuer
 * den bewussten "jetzt einmal alles nachziehen"-Anwendungsfall gedacht.
 */

$enabled = \FriendsOfRedaxo\Mediaplace\Api\AiAltComplete::isEnabled();
$aiAvailable = \FriendsOfRedaxo\Mediaplace\AiAltTextService::isAvailable();

if (!$enabled) {
    echo rex_view::info(rex_i18n::msg('mediaplace_ai_alt_complete_disabled_hint'));

    return;
}
if (!$aiAvailable) {
    echo rex_view::warning(rex_i18n::msg('mediaplace_ai_alt_complete_ai_unavailable_hint'));

    return;
}

$apiUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_ai_alt_complete']);

ob_start();
?>
<p><?php echo rex_i18n::msg('mediaplace_ai_alt_complete_intro'); ?></p>
<p class="text-muted"><?php echo rex_i18n::msg('mediaplace_ai_alt_complete_cronjob_hint'); ?></p>

<div id="mp-ai-alt-complete-app">
    <button type="button" id="mp-ai-alt-complete-start-bg" class="btn btn-save"><i class="fa-solid fa-server"></i> <?php echo rex_i18n::msg('mediaplace_ai_alt_complete_start_background'); ?></button>
    <button type="button" id="mp-ai-alt-complete-start" class="btn btn-default"><i class="fa-solid fa-globe"></i> <?php echo rex_i18n::msg('mediaplace_ai_alt_complete_start_browser'); ?></button>
    <button type="button" id="mp-ai-alt-complete-stop" class="btn btn-abort" style="display:none"><?php echo rex_i18n::msg('mediaplace_ai_alt_complete_cancel'); ?></button>
    <p class="text-muted" style="margin-top:6px;"><?php echo rex_i18n::msg('mediaplace_ai_alt_complete_background_hint'); ?></p>

    <div id="mp-ai-alt-complete-bg-progress-wrap" style="display:none; margin-top:16px;">
        <div style="margin-bottom:6px;">
            <strong><?php echo rex_i18n::msg('mediaplace_ai_alt_complete_progress'); ?></strong>
            <span id="mp-ai-alt-complete-bg-text"></span>
        </div>
        <div class="progress">
            <div id="mp-ai-alt-complete-bg-bar" class="progress-bar" role="progressbar" style="width:0%"></div>
        </div>
    </div>

    <div id="mp-ai-alt-complete-progress-wrap" style="display:none; margin-top:16px;">
        <div style="margin-bottom:6px;">
            <strong><?php echo rex_i18n::msg('mediaplace_ai_alt_complete_progress'); ?></strong>
            <span id="mp-ai-alt-complete-text"></span>
        </div>
        <div class="progress">
            <div id="mp-ai-alt-complete-bar" class="progress-bar" role="progressbar" style="width:0%"></div>
        </div>
    </div>

    <div id="mp-ai-alt-complete-current" class="text-muted" style="margin-top:8px; display:none; word-break:break-all;"></div>

    <div id="mp-ai-alt-complete-errors" style="display:none; margin-top:16px;">
        <strong><?php echo rex_i18n::msg('mediaplace_ai_alt_complete_errors_title'); ?></strong>
        <ul id="mp-ai-alt-complete-errors-list" style="margin-top:6px;"></ul>
    </div>

    <div id="mp-ai-alt-complete-done" class="alert alert-success" style="display:none; margin-top:20px;">
        <?php echo rex_i18n::msg('mediaplace_ai_alt_complete_done'); ?>
    </div>
</div>
<?php
$content = ob_get_clean();

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('mediaplace_ai_alt_complete_legend'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
?>
<script>
(function () {
    var API_URL = <?php echo json_encode($apiUrl); ?>;
    var TXT_OF = <?php echo json_encode(rex_i18n::msg('mediaplace_thumb_warmup_of')); ?>;
    var BATCH_LIMIT = 10;
    var POLL_MS = 1500;

    var startBgBtn = document.getElementById('mp-ai-alt-complete-start-bg');
    var startBtn = document.getElementById('mp-ai-alt-complete-start');
    var stopBtn = document.getElementById('mp-ai-alt-complete-stop');
    var bgProgressWrap = document.getElementById('mp-ai-alt-complete-bg-progress-wrap');
    var bgBar = document.getElementById('mp-ai-alt-complete-bg-bar');
    var bgText = document.getElementById('mp-ai-alt-complete-bg-text');
    var progressWrap = document.getElementById('mp-ai-alt-complete-progress-wrap');
    var bar = document.getElementById('mp-ai-alt-complete-bar');
    var text = document.getElementById('mp-ai-alt-complete-text');
    var currentEl = document.getElementById('mp-ai-alt-complete-current');
    var errorsWrap = document.getElementById('mp-ai-alt-complete-errors');
    var errorsList = document.getElementById('mp-ai-alt-complete-errors-list');
    var doneBox = document.getElementById('mp-ai-alt-complete-done');

    if (!startBtn) return;

    var cancelled = false;
    var pollTimer = null;

    function apiCall(body) {
        return fetch(API_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json();
        });
    }

    function setProgress(bar, textEl, done, total) {
        var pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 100;
        bar.style.width = pct + '%';
        textEl.textContent = done + ' ' + TXT_OF + ' ' + total;
    }

    function setRunningUi(running) {
        startBgBtn.disabled = running;
        startBtn.disabled = running;
        stopBtn.style.display = running ? '' : 'none';
    }

    function addError(message) {
        var li = document.createElement('li');
        li.textContent = message;
        errorsList.appendChild(li);
        errorsWrap.style.display = '';
    }

    // ---- Hintergrund-Modus ----

    function pollBackground() {
        apiCall({ action: 'background_status' }).then(function (status) {
            bgProgressWrap.style.display = '';
            setProgress(bgBar, bgText, status.processed, status.total);
            if (status.last_filename) {
                currentEl.style.display = '';
                currentEl.textContent = status.last_filename;
            }
            if (status.done || cancelled) {
                setRunningUi(false);
                if (!cancelled && status.total > 0) doneBox.style.display = '';
                pollTimer = null;
                return;
            }
            pollTimer = setTimeout(pollBackground, POLL_MS);
        }).catch(function () {
            pollTimer = setTimeout(pollBackground, POLL_MS);
        });
    }

    startBgBtn.addEventListener('click', function () {
        cancelled = false;
        doneBox.style.display = 'none';
        errorsWrap.style.display = 'none';
        errorsList.innerHTML = '';
        setRunningUi(true);
        apiCall({ action: 'start_background' }).then(function (result) {
            if (!result.success) {
                setRunningUi(false);
                addError(result.error || 'Unbekannter Fehler');
                return;
            }
            pollBackground();
        });
    });

    // Beim Laden pruefen, ob bereits ein Hintergrundlauf aktiv ist (z.B. vor
    // dem Schliessen des Tabs gestartet) -- dann sofort weiter anzeigen statt
    // erst auf einen erneuten Klick zu warten.
    apiCall({ action: 'background_status' }).then(function (status) {
        if (status.total > 0 && !status.done) {
            setRunningUi(true);
            pollBackground();
        }
    });

    // ---- Browser-Modus (Tab muss offen bleiben) ----

    startBtn.addEventListener('click', function () {
        cancelled = false;
        doneBox.style.display = 'none';
        errorsWrap.style.display = 'none';
        errorsList.innerHTML = '';
        setRunningUi(true);
        progressWrap.style.display = '';
        currentEl.style.display = '';

        apiCall({ action: 'count' }).then(function (counts) {
            var total = counts.total || 0;
            var done = 0;
            setProgress(bar, text, 0, total);

            if (total === 0) {
                setRunningUi(false);
                doneBox.style.display = '';
                return Promise.resolve();
            }

            var exclude = [];

            function step() {
                if (cancelled) return Promise.resolve();
                currentEl.textContent = '';
                return apiCall({ action: 'complete_batch', limit: BATCH_LIMIT, exclude: exclude }).then(function (result) {
                    if (!result.success) {
                        addError(result.error || 'Unbekannter Fehler');
                        return;
                    }
                    (result.errors || []).forEach(function (err) {
                        addError(err.message || err.filename || 'Fehler');
                    });
                    done += result.processed || 0;
                    setProgress(bar, text, done, total);
                    // Verarbeitete Dateien merken (erfolgreich ODER
                    // fehlgeschlagen), sonst wuerden dauerhaft fehlschlagende
                    // Dateien bei jedem weiteren Batch erneut versucht statt
                    // zu den naechsten Dateien weiterzugehen -- der Endpunkt
                    // ist zustandslos, siehe Api\AiAltComplete-Docblock.
                    (result.processedFilenames || []).forEach(function (fn) {
                        exclude.push(fn);
                    });
                    if (cancelled || result.remaining <= 0 || result.processed === 0) return;
                    return step();
                });
            }

            return step();
        }).then(function () {
            setRunningUi(false);
            currentEl.style.display = 'none';
            if (!cancelled) doneBox.style.display = '';
        });
    });

    // ---- Stop (beide Modi) ----

    stopBtn.addEventListener('click', function () {
        cancelled = true;
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
        apiCall({ action: 'stop_background' });
        setRunningUi(false);
    });
})();
</script>
