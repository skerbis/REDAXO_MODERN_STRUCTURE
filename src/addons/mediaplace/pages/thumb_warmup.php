<?php

/**
 * Manuelles Vorwaermen ALLER Grid-Vorschaubilder, mit Fortschrittsanzeige.
 * Siehe Api\ThumbWarmup fuer die Batch-Logik und lib/ThumbWarmupCronjob.php
 * fuer das automatische, periodische Pendant.
 *
 * Zwei Modi:
 * - Hintergrund (empfohlen): startet einen vom Request abgekoppelten
 *   Server-Prozess (shell_exec(), gleiches Prinzip wie ffmpeg's
 *   Api\Converter::handleStart()) -- laeuft weiter, auch wenn der Tab
 *   geschlossen wird, die Seite kann jederzeit neu geladen werden und zeigt
 *   den laufenden Fortschritt weiter an.
 * - Im Browser: treibt die Batches selbst per wiederholtem fetch(), Tab muss
 *   offen bleiben. Automatischer Fallback, falls shell_exec() auf dem Server
 *   nicht verfuegbar ist (manche Hoster sperren das).
 */

$apiUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_thumb_warmup']);

ob_start();
?>
<p><?php echo rex_i18n::msg('mediaplace_thumb_warmup_intro'); ?></p>

<div id="mp-warmup-app">
    <button type="button" id="mp-warmup-start-bg" class="btn btn-save"><i class="fa-solid fa-server"></i> <?php echo rex_i18n::msg('mediaplace_thumb_warmup_start_background'); ?></button>
    <button type="button" id="mp-warmup-start-browser" class="btn btn-default"><i class="fa-solid fa-globe"></i> <?php echo rex_i18n::msg('mediaplace_thumb_warmup_start_browser'); ?></button>
    <button type="button" id="mp-warmup-stop" class="btn btn-abort" style="display:none"><?php echo rex_i18n::msg('mediaplace_thumb_warmup_cancel'); ?></button>
    <p class="text-muted" style="margin-top:6px;"><?php echo rex_i18n::msg('mediaplace_thumb_warmup_background_hint'); ?></p>

    <div id="mp-warmup-preview-wrap" style="display:none; margin-top:16px;">
        <img id="mp-warmup-preview-img" src="" alt="" style="max-width:160px; max-height:120px; border:1px solid #dfe3e9; display:block; margin-bottom:4px;">
        <span id="mp-warmup-preview-name" style="font-size:12px; color:#777; word-break:break-all;"></span>
    </div>

    <div id="mp-warmup-bg-progress-wrap" style="display:none; margin-top:16px;">
        <div style="margin-bottom:6px;">
            <strong><?php echo rex_i18n::msg('mediaplace_thumb_warmup_progress'); ?></strong>
            <span id="mp-warmup-bg-text"></span>
        </div>
        <div class="progress">
            <div id="mp-warmup-bg-bar" class="progress-bar" role="progressbar" style="width:0%"></div>
        </div>
    </div>

    <div id="mp-warmup-progress-wrap" style="display:none; margin-top:16px;">
        <div style="margin-bottom:6px;">
            <strong><?php echo rex_i18n::msg('mediaplace_thumb_warmup_images'); ?></strong>
            <span id="mp-warmup-image-text"></span>
        </div>
        <div class="progress" style="margin-bottom:20px;">
            <div id="mp-warmup-image-bar" class="progress-bar" role="progressbar" style="width:0%"></div>
        </div>

        <div id="mp-warmup-video-wrap" style="display:none;">
            <div style="margin-bottom:6px;">
                <strong><?php echo rex_i18n::msg('mediaplace_thumb_warmup_videos'); ?></strong>
                <span id="mp-warmup-video-text"></span>
            </div>
            <div class="progress">
                <div id="mp-warmup-video-bar" class="progress-bar" role="progressbar" style="width:0%"></div>
            </div>
        </div>

        <div id="mp-warmup-pdf-wrap" style="display:none; margin-top:20px;">
            <div style="margin-bottom:6px;">
                <strong><?php echo rex_i18n::msg('mediaplace_thumb_warmup_pdfs'); ?></strong>
                <span id="mp-warmup-pdf-text"></span>
            </div>
            <div class="progress">
                <div id="mp-warmup-pdf-bar" class="progress-bar" role="progressbar" style="width:0%"></div>
            </div>
        </div>
    </div>

    <div id="mp-warmup-done" class="alert alert-success" style="display:none; margin-top:20px;">
        <?php echo rex_i18n::msg('mediaplace_thumb_warmup_done'); ?>
    </div>
    <div id="mp-warmup-failed" class="alert alert-warning" style="display:none; margin-top:20px;" data-msg="<?php echo rex_escape(rex_i18n::rawMsg('mediaplace_thumb_warmup_background_failed')); ?>"></div>
</div>
<?php
$content = ob_get_clean();

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('mediaplace_thumb_warmup_legend'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
?>
<script>
(function () {
    var API_URL = <?php echo json_encode($apiUrl); ?>;
    var TXT_OF = <?php echo json_encode(rex_i18n::msg('mediaplace_thumb_warmup_of')); ?>;
    var POLL_MS = 1500;

    var startBgBtn = document.getElementById('mp-warmup-start-bg');
    var startBrowserBtn = document.getElementById('mp-warmup-start-browser');
    var stopBtn = document.getElementById('mp-warmup-stop');
    var bgProgressWrap = document.getElementById('mp-warmup-bg-progress-wrap');
    var bgBar = document.getElementById('mp-warmup-bg-bar');
    var bgText = document.getElementById('mp-warmup-bg-text');
    var progressWrap = document.getElementById('mp-warmup-progress-wrap');
    var videoWrap = document.getElementById('mp-warmup-video-wrap');
    var doneBox = document.getElementById('mp-warmup-done');
    var failedBox = document.getElementById('mp-warmup-failed');
    var imageBar = document.getElementById('mp-warmup-image-bar');
    var imageText = document.getElementById('mp-warmup-image-text');
    var videoBar = document.getElementById('mp-warmup-video-bar');
    var videoText = document.getElementById('mp-warmup-video-text');
    var pdfWrap = document.getElementById('mp-warmup-pdf-wrap');
    var pdfBar = document.getElementById('mp-warmup-pdf-bar');
    var pdfText = document.getElementById('mp-warmup-pdf-text');
    var previewWrap = document.getElementById('mp-warmup-preview-wrap');
    var previewImg = document.getElementById('mp-warmup-preview-img');
    var previewName = document.getElementById('mp-warmup-preview-name');

    if (!startBgBtn) return;

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

    function setProgress(bar, textEl, offset, total) {
        var pct = total > 0 ? Math.min(100, Math.round((offset / total) * 100)) : 100;
        bar.style.width = pct + '%';
        textEl.textContent = offset + ' ' + TXT_OF + ' ' + total;
    }

    function showPreview(url, name) {
        if (!url) return;
        previewWrap.style.display = '';
        previewImg.src = url;
        previewImg.alt = name || '';
        previewName.textContent = name || '';
    }

    function filenameFromUrl(url) {
        var m = /rex_media_file=([^&]+)/.exec(url || '');
        return m ? decodeURIComponent(m[1]) : '';
    }

    function setRunningUi(running) {
        startBgBtn.disabled = running;
        startBrowserBtn.disabled = running;
        stopBtn.style.display = running ? '' : 'none';
    }

    // ---- Hintergrund-Modus ----

    function pollBackground() {
        apiCall({ action: 'background_status' }).then(function (status) {
            bgProgressWrap.style.display = '';
            setProgress(bgBar, bgText, status.processed, status.total);
            if (status.last_thumb_url) {
                showPreview(status.last_thumb_url, filenameFromUrl(status.last_thumb_url));
            }
            if (status.done || cancelled) {
                setRunningUi(false);
                if (!cancelled && status.failed > 0) {
                    failedBox.textContent = failedBox.getAttribute('data-msg')
                        .replace('{0}', status.failed).replace('{1}', status.total).replace('{2}', status.server || '');
                    failedBox.style.display = '';
                } else if (!cancelled && status.total > 0) {
                    doneBox.style.display = '';
                }
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
        failedBox.style.display = 'none';
        setRunningUi(true);
        apiCall({ action: 'start_background' }).then(function (result) {
            if (result.error) {
                setRunningUi(false);
                alert(result.error);
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

    function warmType(type, bar, textEl) {
        var offset = 0;
        function step() {
            if (cancelled) return Promise.resolve();
            return apiCall({ action: 'warm_batch', type: type, offset: offset }).then(function (result) {
                offset = result.next_offset;
                setProgress(bar, textEl, offset, result.total);
                if (result.last_thumb_url) showPreview(result.last_thumb_url, result.last_filename);
                if (result.done || cancelled) return;
                return step();
            });
        }
        return step();
    }

    startBrowserBtn.addEventListener('click', function () {
        cancelled = false;
        doneBox.style.display = 'none';
        setRunningUi(true);
        progressWrap.style.display = '';

        apiCall({ action: 'count' }).then(function (counts) {
            setProgress(imageBar, imageText, 0, counts.image.total);
            if (counts.video.active) {
                videoWrap.style.display = '';
                setProgress(videoBar, videoText, 0, counts.video.total);
            }
            if (counts.pdf && counts.pdf.active) {
                pdfWrap.style.display = '';
                setProgress(pdfBar, pdfText, 0, counts.pdf.total);
            }
            return warmType('image', imageBar, imageText).then(function () {
                if (cancelled || !counts.video.active) return;
                return warmType('video', videoBar, videoText);
            }).then(function () {
                if (cancelled || !counts.pdf || !counts.pdf.active) return;
                return warmType('pdf', pdfBar, pdfText);
            });
        }).then(function () {
            setRunningUi(false);
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
