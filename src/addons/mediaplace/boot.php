<?php

// Steuert kein eigenes Menu, nur den "Nur unbenutzte Medien"-Filter im
// Overlay -- Rollen-Verwaltung muss es unabhaengig vom eingeloggten User
// auflisten koennen, deshalb ausserhalb der isBackend()/getUser()-Bedingung.
rex_perm::register('mediaplace[view_unused_media]', 'Filter "Nur unbenutzte Medien" nutzen');
rex_perm::register('mediaplace[manage_categories]', 'Ordner (Kategorien) umbenennen, verschieben oder löschen');
rex_perm::register('mediaplace[optimize_video]', 'Videos optimieren (ffmpeg-Addon)');
rex_perm::register('mediaplace[optimize_image]', 'Bilder optimieren');
rex_perm::register('mediaplace[manage_tags]', 'Tags umbenennen, Farbe bestehender Tags ändern, Tags löschen oder für KI-Vorschläge freigeben');
rex_perm::register('mediaplace[bulk_operations]', 'Massenaktionen für ganze Kategorien (alle Dateien verschieben/löschen/taggen)');
rex_perm::register(\FriendsOfRedaxo\Mediaplace\MediaRename::PERM, 'Dateien umbenennen (ersetzt alle Verweise in der Datenbank)');
rex_perm::register(\FriendsOfRedaxo\Mediaplace\ImageEditor::PERM_EDIT, 'Bilder bearbeiten und als neue Datei speichern');
rex_perm::register(\FriendsOfRedaxo\Mediaplace\ImageEditor::PERM_OVERWRITE, 'Bearbeitete Bilder überschreiben und Originale wiederherstellen');

// Eigene rex_api_function-Endpunkte laufen unter dem Namespace
// FriendsOfRedaxo\Mediaplace\Api (siehe https://redaxo.org/doku/5.x/api#namespace-registrierung)
// statt der Klassennamenskonvention "rex_api_<name>" -- deshalb explizite
// Registrierung noetig, sonst findet rex_api_function::factory() die Klasse
// nicht mehr. Die rex-api-call-Bezeichner selbst bleiben unveraendert (Client-
// seitig in mediaplace-api.js/README.md verwendet), nur die PHP-Klasse zieht um.
// Unconditional wie die rex_perm::register()-Aufrufe oben -- die Registrierung
// selbst braucht keinen eingeloggten Backend-User, das pruefen die einzelnen
// Endpunkte (rex::getUser() + MediaPermission) ohnehin selbst in execute().
rex_api_function::register('mediaplace_categories', \FriendsOfRedaxo\Mediaplace\Api\Categories::class);
rex_api_function::register('mediaplace_category_bulk', \FriendsOfRedaxo\Mediaplace\Api\CategoryBulk::class);
rex_api_function::register('mediaplace_focuspoint', \FriendsOfRedaxo\Mediaplace\Api\Focuspoint::class);
rex_api_function::register('mediaplace_image_edit', \FriendsOfRedaxo\Mediaplace\Api\ImageEdit::class);
rex_api_function::register('mediaplace_image_optimize', \FriendsOfRedaxo\Mediaplace\Api\ImageOptimize::class);
rex_api_function::register('mediaplace_json_metainfo', \FriendsOfRedaxo\Mediaplace\Api\JsonMetainfo::class);
rex_api_function::register('mediaplace_media_list', \FriendsOfRedaxo\Mediaplace\Api\MediaList::class);
rex_api_function::register('mediaplace_metainfo_form', \FriendsOfRedaxo\Mediaplace\Api\MetainfoForm::class);
rex_api_function::register('mediaplace_provider', \FriendsOfRedaxo\Mediaplace\Api\Provider::class);
rex_api_function::register('mediaplace_replace_file', \FriendsOfRedaxo\Mediaplace\Api\ReplaceFile::class);
rex_api_function::register('mediaplace_rename', \FriendsOfRedaxo\Mediaplace\Api\Rename::class);
rex_api_function::register('mediaplace_schema', \FriendsOfRedaxo\Mediaplace\Api\Schema::class);
rex_api_function::register('mediaplace_storage_usage', \FriendsOfRedaxo\Mediaplace\Api\StorageUsage::class);
rex_api_function::register('mediaplace_tags', \FriendsOfRedaxo\Mediaplace\Api\Tags::class);
rex_api_function::register('mediaplace_thumb_warmup', \FriendsOfRedaxo\Mediaplace\Api\ThumbWarmup::class);
rex_api_function::register('mediaplace_unused', \FriendsOfRedaxo\Mediaplace\Api\Unused::class);
rex_api_function::register('mediaplace_video_info', \FriendsOfRedaxo\Mediaplace\Api\VideoInfo::class);
rex_api_function::register('mediaplace_video_optimize', \FriendsOfRedaxo\Mediaplace\Api\VideoOptimize::class);
// Optionale KI-Alt-Text-Generierung (siehe AiAltTextService::isAvailable()
// -- rein soft-optional, wirkungslos ohne installiertes+konfiguriertes
// ai_platform-Addon UND aktivierte Einstellung "AI Alt-Text aktivieren").
rex_api_function::register('mediaplace_ai_alt_text', \FriendsOfRedaxo\Mediaplace\Api\AiAltText::class);
rex_api_function::register('mediaplace_ai_alt_bulk', \FriendsOfRedaxo\Mediaplace\Api\AiAltBulk::class);
// Vollstaendige, automatische Vervollstaendigung ohne Review (Admin-Seite
// ai_alt_complete + AiAltCompleteCronjob) -- eigener Endpunkt statt Eingriff
// in AiAltBulk, siehe Api\AiAltComplete-Docblock fuer den Unterschied.
rex_api_function::register('mediaplace_ai_alt_complete', \FriendsOfRedaxo\Mediaplace\Api\AiAltComplete::class);
// Optionale KI-Auto-Tagging-Vorschlaege (siehe AiAutoTagService::isAvailable()
// -- rein soft-optional, zusaetzlich nur aktiv, wenn mindestens ein Tag in
// der Tag-Verwaltung fuer KI freigegeben ist).
rex_api_function::register('mediaplace_ai_auto_tag', \FriendsOfRedaxo\Mediaplace\Api\AiAutoTag::class);

// YForm-Werttyp "mediaplace" (lib/yform/value/yform_value_mediaplace.php, per
// Klassennamenskonvention automatisch von YForm erkannt -- keine explizite
// Feldtyp-Registrierung noetig, nur das Template-Verzeichnis und der
// MEDIA_IS_IN_USE-Hook). Unconditional wie bei filepond_uploader/boot.php,
// nicht auf isBackend() beschraenkt, da der Loeschen-Check auch aus anderen
// Kontexten heraus feuern kann.
if (rex_addon::get('yform')->isAvailable()) {
    rex_yform::addTemplatePath($this->getPath('ytemplates'));
    rex_extension::register('MEDIA_IS_IN_USE', ['rex_yform_value_mediaplace', 'isMediaInUse']);
}

// Sicherheitsnetz fuer die eigenen Video-Vorschau-Typen (animiert +
// statisches Standbild, siehe FfmpegIntegration::ensureVideoThumbTypes()):
// beide referenzieren ffmpeg's Effekt "video_to_webp". Wird ffmpeg NACH dem
// Anlegen dieser Typen deinstalliert (oder faellt das Binary weg), bleiben
// die Typ-/Effekt-Zeilen in der DB stehen -- media_manager selbst hat vor dem
// Instanziieren eines Effekts keine class_exists()-Absicherung
// (media_manager.php, applyEffects(): "new $effectClass()") und stuerzt mit
// einem Fatal Error (500) ab, sobald IRGENDEINE Anfrage (auch eine alte/
// gecachte <img>-URL) einen dieser Typen fuer ein Video anfordert. Statt das
// crashen zu lassen, wird das Effekt-Set fuer beide Typen auf leer gesetzt,
// sobald ffmpeg nicht mehr verfuegbar ist -- media_manager liefert dann sein
// eigenes "nicht gefunden"-Verhalten statt eines Fatal Errors. Unconditional
// (nicht nur im isBackend()-Block), da Media-Manager-Anfragen auch anonym/
// frontend-seitig eintreffen koennen.
rex_extension::register('MEDIA_MANAGER_FILTERSET', static function (rex_extension_point $ep) {
    $videoThumbTypes = [
        \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::VIDEO_THUMB_TYPE,
        \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::VIDEO_THUMB_TYPE_STATIC,
    ];
    if (in_array($ep->getParam('rex_media_type'), $videoThumbTypes, true)
        && !\FriendsOfRedaxo\Mediaplace\FfmpegIntegration::isAvailable()) {
        return [];
    }
    // gleiches Sicherheitsnetz fuer die PDF-Vorschau (pdfout entfernt oder kein Konverter mehr)
    if (\FriendsOfRedaxo\Mediaplace\PdfoutIntegration::PDF_THUMB_TYPE === $ep->getParam('rex_media_type')
        && !\FriendsOfRedaxo\Mediaplace\PdfoutIntegration::isThumbAvailable()) {
        return [];
    }

    return $ep->getSubject();
});

// Cronjob-Typ "Vorschaubilder vorwaermen" (siehe ThumbWarmupCronjob) --
// unconditional registrierbar, gleiches Muster wie activity_log/boot.php.
// Bild-Vorschaubilder laufen immer, Video-Vorschaubilder nur wenn ffmpeg
// verfuegbar ist -- entscheidet der Cronjob selbst zur Laufzeit.
if (rex_addon::get('cronjob')->isAvailable() && !rex::isSafeMode()) {
    rex_cronjob_manager::registerType(\FriendsOfRedaxo\Mediaplace\ThumbWarmupCronjob::class);
    // Cronjob-Typ "ALT-Texte automatisch vervollstaendigen" -- unconditional
    // registrierbar wie ThumbWarmupCronjob; ob er tatsaechlich etwas tut,
    // entscheidet der Cronjob selbst zur Laufzeit ueber
    // Api\AiAltComplete::isEnabled()/AiAltTextService::isAvailable() (siehe
    // dortiger Docblock) -- kein toter Eintrag in der Cronjob-Liste, falls
    // die Funktion (noch) nicht aktiviert ist, aber auch kein Fehler.
    rex_cronjob_manager::registerType(\FriendsOfRedaxo\Mediaplace\AiAltCompleteCronjob::class);
    rex_cronjob_manager::registerType(\FriendsOfRedaxo\Mediaplace\MediaRenameCronjob::class);
}

if (rex::isBackend() && rex::getUser()) {
    // Pro-Datei-Cache-Buster, damit ein Deploy einzelner JS/CSS-Dateien nicht
    // durch Browser-Caching unbemerkt ausbleibt.
    $bust = function (string $file) {
        return '?v=' . filemtime($this->getPath('assets/' . $file));
    };

    // Core: Overlay Picker -- i18n/Helfer/API-Schicht zuerst laden (mediaplace.js
    // bindet sie per Alias ein, siehe deren Datei-Header-Kommentare).
    rex_view::addCssFile($this->getAssetsUrl('mediaplace.css') . $bust('mediaplace.css'));
    rex_view::addJsFile($this->getAssetsUrl('mediaplace-i18n.js') . $bust('mediaplace-i18n.js'));
    rex_view::addJsFile($this->getAssetsUrl('mediaplace-helpers.js') . $bust('mediaplace-helpers.js'));
    rex_view::addJsFile($this->getAssetsUrl('mediaplace-api.js') . $bust('mediaplace-api.js'));
    rex_view::addJsFile($this->getAssetsUrl('mediaplace.js') . $bust('mediaplace.js'));

    // Widget: Input-Feld → Media Picker
    rex_view::addCssFile($this->getAssetsUrl('mediaplace_widget.css') . $bust('mediaplace_widget.css'));
    rex_view::addJsFile($this->getAssetsUrl('mediaplace_widget.js') . $bust('mediaplace_widget.js'));

    // Klassische REX_MEDIA[n]/REX_MEDIALIST[n]-Widgets auf den neuen Overlay umleiten
    rex_view::addJsFile($this->getAssetsUrl('mediaplace_classic.js') . $bust('mediaplace_classic.js'));

    // Video-Vorschau-Typen (ffmpeg-Integration, siehe FfmpegIntegration) --
    // idempotente Existenzpruefung, legt beide Typen (animiert + Standbild)
    // bei Bedarf an (z.B. wenn ffmpeg erst nach MediaPlace installiert wurde).
    \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::ensureVideoThumbTypes();
    \FriendsOfRedaxo\Mediaplace\PdfoutIntegration::ensurePdfThumbType();

    // Biegt den klassischen "Medienpool"-Menuepunkt auf unseren Overlay um,
    // abschaltbar ueber die Einstellungsseite.
    // https://friendsofredaxo.github.io/tricks/backend/backend_snippets#seite-eines-addons-durch-eigene-austauschenersetzen
    $addonName = $this->getName();
    // Tagging abgeschaltet: Tag-Verwaltung nicht im Menue (direkter Aufruf zeigt einen Hinweis)
    rex_extension::register('PAGES_PREPARED', static function () use ($addonName) {
        if (rex_config::get($addonName, 'disable_tagging', false)) {
            rex_be_controller::getPageObject($addonName . '/tag_management')?->setHidden(true);
        }
    });

    rex_extension::register('PAGES_PREPARED', static function () use ($addonName) {
        if (!rex_config::get($addonName, 'replace_classic_mediapool', true)) {
            return;
        }

        $mediapool = rex_be_controller::getPages()['mediapool'] ?? null;
        if (!$mediapool instanceof rex_be_page) {
            return;
        }

        $mediapool->setPopup('MP.open(); return false;');
        $mediapool->setTitle('MediaPlace');
        $mediapool->setIcon('rex-icon fa-photo-film');

        $takeoverPath = rex_path::addon($addonName, 'pages/mediapool_takeover.php');

        // Auch klassische Popup-Aufrufe (TinyMCE/CKEditor5, REX_MEDIA[n]/
        // REX_MEDIALIST[n]-Widgets -- erkennbar am mitgeschickten
        // 'opener_input_field', siehe openMediaPool()/openREXMedia()/
        // addREXMedia()/... in mediapool.js) bekommen MediaPlace statt des
        // klassischen Formulars: mediapool_takeover.php bildet dafuer selbst
        // den klassischen Popup-Vertrag (rex:selectMedia-Event bzw.
        // REX_MEDIALIST-Select-Befuellung auf dem Opener-Fenster) nach, siehe
        // dortigen Kommentar.
        $mediaSubpage = $mediapool->getSubpage('media');
        if ($mediaSubpage instanceof rex_be_page) {
            $mediaSubpage->setHidden(true);
            $mediaSubpage->setSubPath($takeoverPath);
        }

        // filepond_uploader kann dieselbe Unterseite ueber seinen eigenen
        // 'replace_mediapool'-Schalter uebernehmen -- in dem Fall bewusst
        // nicht eingreifen, um die beiden Umbiegungen nicht gegeneinander
        // laufen zu lassen (wer zuletzt registriert, wuerde sonst "gewinnen").
        $filepondOwnsUpload = rex_addon::get('filepond_uploader')->isAvailable()
            && rex_config::get('filepond_uploader', 'replace_mediapool', false);
        $uploadSubpage = $mediapool->getSubpage('upload');
        if ($uploadSubpage instanceof rex_be_page && !$filepondOwnsUpload) {
            $uploadSubpage->setHidden(true);
            $uploadSubpage->setSubPath($takeoverPath);
        }

        // 'structure' (Kategorieverwaltung) hat keinen klassischen Popup-Aufrufer,
        // kann also immer umgebogen werden.
        $structureSubpage = $mediapool->getSubpage('structure');
        if ($structureSubpage instanceof rex_be_page) {
            $structureSubpage->setHidden(true);
            $structureSubpage->setSubPath($takeoverPath);
        }

        // 'sync' bewusst unangetastet -- kein Aequivalent im MediaPlace-Overlay.
    });

    // Zeigt med_json_data im klassischen Medienpool-Formular formatiert und
    // schreibgeschuetzt an (Feldtyp "mediaplace_json", siehe install.php).
    if (rex_addon::get('metainfo')->isAvailable()) {
        rex_extension::register('METAINFO_CUSTOM_FIELD', static function (rex_extension_point $ep) {
            $subject = $ep->getSubject();
            $sqlFields = $subject['sql'] ?? null;
            if (!$sqlFields instanceof rex_sql || 'med_json_data' !== (string) $sqlFields->getValue('name')) {
                return null;
            }

            [$field, $tag, $tagAttr, $id, $label, $labelIt] = $subject;

            $rawValues = (array) ($subject['rawvalues'] ?? []);
            $raw = implode('|', $rawValues);

            $decoded = [];
            if ('' !== trim($raw)) {
                $decodedRaw = json_decode($raw, true);
                if (is_array($decodedRaw)) {
                    $decoded = $decodedRaw;
                }
            }

            $innerField = \FriendsOfRedaxo\Mediaplace\ClassicMetainfoFormatter::render($decoded);

            // media_handler.php::renderFormItem() baut keine dt/dd-Huelle --
            // die uebernehmen wir hier selbst per core/form/form.php.
            $formFragment = new rex_fragment();
            $formFragment->setVar('elements', [[
                'label' => $label,
                'field' => $innerField,
                'note' => rex_i18n::msg('mediaplace_metainfo_readonly_hint'),
            ]], false);
            $field = $formFragment->parse('core/form/form.php');

            return [$field, $tag, $tagAttr, $id, $label, $labelIt];
        });
    }

    rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) use ($addonName) {
        $content = $ep->getSubject();
        // Basis-URL fuer Original-Mediendateien (SVG-Vorschau, Lightbox-
        // Grossansicht) serverseitig per rex_url::media() berechnen, statt
        // clientseitig zu raten -- rex_url::media() nutzt REDAXOs eigenen
        // PathProvider und ist damit unabhaengig davon korrekt, ob die Seite,
        // von der aus MediaPlace aufgerufen wird, im Backend (z.B. unter
        // "/redaxo/") oder potenziell im Frontend liegt. Ein clientseitiger
        // Trick wie "../media/" (relativ zur aktuellen Seite) oder ein Split
        // auf "/redaxo/" im aktuellen Pfad (siehe filepond_uploader) waere
        // nur fuer Backend-Seiten in exakt einer bestimmten Verzeichnistiefe
        // zuverlaessig.
        $mediaBaseUrl = rex_url::media();
        $schemaUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_schema', 'prefix' => 'med_']);
        $jsonUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_json_metainfo']);
        $tagsUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_tags']);
        $categoriesUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_categories']);
        $categoryBulkUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_category_bulk']);
        $unusedUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_unused']);
        $storageUsageUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_storage_usage']);
        $replaceFileUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_replace_file']);
        // Uebergangs-Fallback, solange die installierte FriendsOfRedaxo/api-Version
        // media/list noch keinen filter[permitted_only]-Parameter unterstuetzt
        // (Opt-in fuer Kategorie-Rechte-Filterung, api-Addon PR #78 -- der
        // klassische Medienpool gibt traditionell jedem Basis-Medienrecht
        // Leserecht auf alle Kategorien, daher bewusst kein neues Default-
        // Verhalten der Route). buildMediaEndpoint() schickt permitted_only
        // immer mit; eine aeltere api-Version wuerde den unbekannten Parameter
        // aber stillschweigend ignorieren und dadurch wieder ungefiltert
        // liefern -- deshalb der eigene, rechte-gepruefte Fallback-Endpunkt
        // hier. Schwelle bewusst auf 1.3.2 gesetzt (noch keine reale
        // api-Release-Version, Platzhalter fuer die erste Version, die PR #78
        // MIT der spaeter ergaenzten Kaskadierung enthaelt): der eigene
        // Fallback-Endpunkt (Api\MediaList.php) nutzt
        // MediaPermission::getAccessibleCategoryIds(), das bereits kaskadiert
        // (Zugriff auf X gilt fuer den ganzen Unterbaum) -- ohne die Anhebung
        // wuerden Installationen mit reinem api 1.3.1 (Kategorie-Filterung,
        // aber noch exakt statt kaskadierend) den direkten, nicht
        // kaskadierenden media/list-Endpunkt nutzen und Unterkategorien einer
        // freigegebenen Kategorie faelschlich leer/gefiltert sehen. Sobald
        // api die Kaskadierung tatsaechlich released hat, hier die echte
        // Versionsnummer eintragen (dann kann fuer >= diese Version wieder
        // direkt auf media/list gegangen werden); bis api ueberall >= dieser
        // Version installiert ist, bleibt die Weiche inkl. Fallback-Endpunkt
        // noetig.
        $apiVersion = (string) (rex_addon::get('api')->getVersion() ?: '0');
        $apiMediaListSecure = version_compare($apiVersion, '1.3.2', '>=') ? '1' : '0';
        $mediaListFallbackUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_media_list']);
        $canFilterUnused = \FriendsOfRedaxo\Mediaplace\MediaPermission::hasUnusedFilterAccess() ? '1' : '0';
        $canBulkOperations = \FriendsOfRedaxo\Mediaplace\MediaPermission::hasBulkOperationsAccess() ? '1' : '0';
        // "Medien ohne ALT-Text"-Sidebar-Eintrag nur anzeigen, wenn er in den
        // Einstellungen aktiviert ist (Default an, siehe package.yml
        // default_config) UND es ueberhaupt ein ALT-Text-Feld gibt (eigenes
        // oder klassisches med_alt) -- sonst waere die Ansicht immer leer/sinnlos.
        $altMissingFilterAvailable = rex_config::get($addonName, 'enable_alt_missing_filter', true)
            && \FriendsOfRedaxo\Mediaplace\AltTextStatus::hasAltField() ? '1' : '0';
        // Kategorie 0 ("kein Ordner") ist ein eigenes Recht (hasCategoryPerm(0)),
        // das viele auf einzelne Kategorien eingeschraenkte User nicht haben --
        // steuert, ob der "Medienpool"-Sidebar-/Breadcrumb-Link anklickbar ist.
        $canAccessRootCategory = \FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess(0) ? '1' : '0';
        $focuspointUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_focuspoint']);
        $metainfoFormUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_metainfo_form']);
        $metainfoFormAvailable = rex_addon::get('metainfo')->isAvailable() ? '1' : '0';
        $imageEditUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_image_edit']);
        $imageEditToken = rex_csrf_token::factory(\FriendsOfRedaxo\Mediaplace\Api\ImageEdit::CSRF)->getValue();

        // ffmpeg-Integration (siehe FfmpegIntegration): Video-Vorschau im Grid
        // (Typ-Name direkt, kein API-Umweg -- Grid baut die Thumb-URL genauso
        // wie fuer mediaplace_thumb selbst) + "Video optimieren"-Button.
        // Typ-Name statt reinem Flag: haengt vom Einstellungen-Modus ab (aus/
        // Standbild/animiert, siehe getActiveVideoThumbType()) -- leerer
        // String = Video-Vorschau aus, previewHtml() zeigt dann konsequent
        // nur das Datei-Icon.
        $videoThumbType = \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::isAvailable()
            ? (string) (\FriendsOfRedaxo\Mediaplace\FfmpegIntegration::getActiveVideoThumbType() ?? '')
            : '';
        $videoThumbStatic = \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::getVideoThumbMode() === 'static' ? '1' : '0';
        $optimizeVideoUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_video_optimize']);
        $optimizeVideoAvailable = null !== rex::getUser() && rex::getUser()->hasPerm('mediaplace[optimize_video]')
            && \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::isAvailable() ? '1' : '0';
        $videoInfoUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_video_info']);
        // pdfout-Integration (siehe PdfoutIntegration): PDF-Vorschau im Grid, leer = Datei-Icon
        $pdfThumbType = \FriendsOfRedaxo\Mediaplace\PdfoutIntegration::isThumbAvailable() ? \FriendsOfRedaxo\Mediaplace\PdfoutIntegration::PDF_THUMB_TYPE : '';
        // Kein "optimizeImageAvailable"-Seiten-Flag hier (anders als bei Video/
        // Fokuspunkt frueher): der Button selbst wird bereits live pro Datei
        // ueber optimize_image_available in buildFastInfoFields() gerendert --
        // ein zusaetzliches, nur einmal beim Seitenaufbau gecachtes Flag wuerde
        // veralten koennen (siehe Fokuspunkt-Bugfix, CHANGELOG 1.7.1) und ist
        // hier von vornherein unnoetig.
        $optimizeImageUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_image_optimize']);

        // Umbenennen (Recht mediaplace[rename_files]); rename-pending laesst jede Backend-Seite offene Jobs fortsetzen
        $renameUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_rename']);
        $renameToken = \FriendsOfRedaxo\Mediaplace\MediaRename::canRename() ? rex_csrf_token::factory(\FriendsOfRedaxo\Mediaplace\MediaRename::CSRF)->getValue() : '';
        $renamePending = \FriendsOfRedaxo\Mediaplace\MediaRename::hasPending() ? '1' : '0';

        // Feature-Toggles der Einstellungsseite, siehe features-Objekt in mediaplace.js.
        $featureTagging = rex_config::get($addonName, 'disable_tagging', false) ? '0' : '1';
        $featureCollections = rex_config::get($addonName, 'disable_collections', false) ? '0' : '1';
        $featureMetainfoEditing = rex_config::get($addonName, 'enable_metainfo_editing', false) ? '1' : '0';
        $featureUploadResize = rex_config::get($addonName, 'enable_upload_resize', false) ? '1' : '0';
        $uploadResizeWidth = (int) rex_config::get($addonName, 'upload_resize_width', 2000);
        $uploadResizeHeight = (int) rex_config::get($addonName, 'upload_resize_height', 2000);

        // Optionale KI-Alt-Text-Generierung (siehe AiAltTextService::isAvailable()).
        // aiAltAvailable steuert den Einzeldatei-Button (Detail-Panel + nativer
        // Canvas) -- zusaetzlich zu Feature/Addon-Verfuegbarkeit auch
        // hasMediaAccess(), sonst wuerde der Button auch fuer Backend-User
        // OHNE jede Medien-Berechtigung injiziert (nur der Server-Endpunkt
        // haette das dann noch abgefangen -- gleiches Defense-in-Depth-Muster
        // wie optimizeVideoAvailable oben). aiAltBulkAvailable
        // zusaetzlich das granularere Recht fuer die kategorieuebergreifende
        // Massengenerierung (Zahnrad-Menue) -- Massenaktionen sind ein
        // groesseres Blast-Radius-/Kosten-Risiko (viele KI-Aufrufe pro Klick)
        // als eine einzelne, manuell angestossene Generierung.
        $aiAltAvailable = \FriendsOfRedaxo\Mediaplace\AiAltTextService::isAvailable()
            && \FriendsOfRedaxo\Mediaplace\MediaPermission::hasMediaAccess();
        $aiAltUrl = $aiAltAvailable ? rex_url::backendController(['rex-api-call' => 'mediaplace_ai_alt_text']) : '';
        $aiAltBulkAvailable = $aiAltAvailable && \FriendsOfRedaxo\Mediaplace\MediaPermission::hasBulkOperationsAccess();
        $aiAltBulkUrl = $aiAltBulkAvailable ? rex_url::backendController(['rex-api-call' => 'mediaplace_ai_alt_bulk']) : '';

        // Optionale KI-Auto-Tagging-Vorschlaege (siehe AiAutoTagService::
        // isAvailable() -- Feature-Toggle + mindestens ein KI-freigegebener
        // Tag). Gleiches hasMediaAccess()-Defense-in-Depth wie aiAltAvailable.
        $aiAutoTagAvailable = \FriendsOfRedaxo\Mediaplace\AiAutoTagService::isAvailable()
            && \FriendsOfRedaxo\Mediaplace\MediaPermission::hasMediaAccess();
        $aiAutoTagUrl = $aiAutoTagAvailable ? rex_url::backendController(['rex-api-call' => 'mediaplace_ai_auto_tag']) : '';

        // Cloud-Provider-Addons (siehe StorageProviderRegistry), die sich als
        // zusaetzlicher Baum in die Sidebar einklinken. Nur Registrierungs-
        // Metadaten (Label/Icon) hier -- KEINE Provider-Instanziierung an
        // dieser Stelle: ein Provider (z.B. Nextcloud) kann seinen Konstruktor
        // eine Exception werfen lassen, wenn er noch nicht konfiguriert ist,
        // und das darf den Seitenaufbau nicht abreissen, obwohl der User das
        // Recht dafuer haette. hasSearch() kommt deshalb erst mit der
        // tatsaechlichen entries-Antwort vom Server (siehe
        // Api\Provider.php), nicht schon hier.
        $providerUrl = rex_url::backendController(['rex-api-call' => 'mediaplace_provider']);
        $providers = [];
        foreach (\FriendsOfRedaxo\Mediaplace\StorageProviderRegistry::getAvailableProviders() as $providerId => $providerMeta) {
            $providers[] = [
                'id' => $providerId,
                'label' => (string) ($providerMeta['label'] ?? $providerId),
                'icon' => (string) ($providerMeta['icon'] ?? 'fa-solid fa-cloud'),
                'color' => (string) ($providerMeta['color'] ?? ''),
            ];
        }

        // Upload-Provider (siehe UploadProviderRegistry): rein clientseitig,
        // Uebernahme des Upload-Buttons/Drag&Drop erfolgt per
        // MP.registerUploadProvider() in core.js. Nur die AKTIVE Provider-ID
        // wird ans Frontend gereicht (anders als Storage-Provider, von denen
        // mehrere gleichzeitig koexistieren) -- "Eingebaut" (leerer Wert)
        // bleibt Standard, solange kein Provider gewaehlt ist oder der
        // gewaehlte fuer den aktuellen User laut seinem eigenen `perm` nicht
        // verfuegbar ist.
        $uploadProviderId = (string) rex_config::get($addonName, 'upload_provider', '');
        if ('' !== $uploadProviderId && !isset(\FriendsOfRedaxo\Mediaplace\UploadProviderRegistry::getAvailableProviders()[$uploadProviderId])) {
            $uploadProviderId = '';
        }

        // Klassische Unterseiten (Struktur, Hochladen, Sync, ...) bleiben ueber
        // ein Verwaltungs-Icon im Overlay erreichbar.
        $subpages = [];
        $mediapool = rex_be_controller::getPages()['mediapool'] ?? null;
        if ($mediapool instanceof rex_be_page) {
            foreach ($mediapool->getSubpages() as $subpage) {
                if ($subpage->isHidden() || !$subpage->checkPermission(rex::requireUser())) {
                    continue;
                }
                $subpages[] = [
                    'title' => $subpage->getTitle(),
                    'href' => $subpage->getHref(),
                    'icon' => $subpage->hasIcon() ? $subpage->getIcon() : 'rex-icon rex-icon-package-addon',
                    // Klassische Unterseiten oeffnen wie bisher im alten Popup-Fenster
                    // (siehe initAdminMenu() in legacy.js) -- nur der neue
                    // Einstellungen-Eintrag unten (echte MediaPlace-Seite, kein
                    // Popup-Formular) navigiert stattdessen normal.
                    'popup' => true,
                ];
            }
        }

        // Zahnrad-Eintrag im selben Menue: Link zur eigenen Einstellungsseite,
        // nur wenn der User dafuer berechtigt ist (dort per package.yml perm: admin).
        $mediaplaceSettingsPage = rex_be_controller::getPages()[$addonName] ?? null;
        $mediaplaceSettingsSubpage = $mediaplaceSettingsPage instanceof rex_be_page
            ? $mediaplaceSettingsPage->getSubpage('settings')
            : null;
        if ($mediaplaceSettingsSubpage instanceof rex_be_page && $mediaplaceSettingsSubpage->checkPermission(rex::requireUser())) {
            $subpages[] = [
                'title' => rex_i18n::msg('mediaplace_settings'),
                'href' => $mediaplaceSettingsSubpage->getHref(),
                'icon' => $mediaplaceSettingsSubpage->hasIcon() ? $mediaplaceSettingsSubpage->getIcon() : 'rex-icon fa-gear',
                'popup' => false,
            ];
        }

        // Gleiches Muster fuer die Tag-Verwaltung (eigene, engere Berechtigung
        // mediaplace[manage_tags] statt admin, siehe package.yml).
        $mediaplaceTagManagementSubpage = $mediaplaceSettingsPage instanceof rex_be_page
            ? $mediaplaceSettingsPage->getSubpage('tag_management')
            : null;
        if ('1' === $featureTagging && $mediaplaceTagManagementSubpage instanceof rex_be_page && $mediaplaceTagManagementSubpage->checkPermission(rex::requireUser())) {
            $subpages[] = [
                'title' => rex_i18n::msg('mediaplace_tag_management'),
                'href' => $mediaplaceTagManagementSubpage->getHref(),
                'icon' => $mediaplaceTagManagementSubpage->hasIcon() ? $mediaplaceTagManagementSubpage->getIcon() : 'rex-icon fa-tags',
                'popup' => false,
            ];
        }

        // JS-Uebersetzungen: jeder Schluessel aus lang/de_de.lang wird fuer die
        // aktive Locale aufgeloest und als JSON eingebettet (siehe mediaplace-i18n.js).
        $i18nMap = [];
        $langFile = rex_path::addon($addonName, 'lang/de_de.lang');
        if (is_file($langFile)) {
            foreach (file($langFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (preg_match('/^([a-zA-Z0-9_]+)\s*=/', $line, $m)) {
                    $i18nMap[$m[1]] = rex_i18n::msg($m[1]);
                }
            }
        }

        $inject = '<div id="mp-root" data-media-base-url="' . rex_escape($mediaBaseUrl) . '" data-schema-url="' . rex_escape($schemaUrl) . '" data-json-url="' . rex_escape($jsonUrl) . '" data-tags-url="' . rex_escape($tagsUrl) . '" data-categories-url="' . rex_escape($categoriesUrl) . '" data-category-bulk-url="' . rex_escape($categoryBulkUrl) . '" data-unused-url="' . rex_escape($unusedUrl) . '" data-storage-usage-url="' . rex_escape($storageUsageUrl) . '" data-replace-file-url="' . rex_escape($replaceFileUrl) . '" data-can-filter-unused="' . $canFilterUnused . '" data-can-bulk-operations="' . $canBulkOperations . '" data-can-access-root-category="' . $canAccessRootCategory . '" data-media-list-fallback-url="' . rex_escape($mediaListFallbackUrl) . '" data-api-media-list-secure="' . $apiMediaListSecure . '" data-focuspoint-url="' . rex_escape($focuspointUrl) . '" data-metainfo-form-url="' . rex_escape($metainfoFormUrl) . '" data-metainfo-form-available="' . $metainfoFormAvailable . '" data-image-edit-url="' . rex_escape($imageEditUrl) . '" data-image-edit-token="' . rex_escape($imageEditToken) . '" data-video-thumb-type="' . rex_escape($videoThumbType) . '" data-video-thumb-static="' . $videoThumbStatic . '" data-pdf-thumb-type="' . rex_escape($pdfThumbType) . '" data-optimize-video-url="' . rex_escape($optimizeVideoUrl) . '" data-optimize-video-available="' . $optimizeVideoAvailable . '" data-video-info-url="' . rex_escape($videoInfoUrl) . '" data-optimize-image-url="' . rex_escape($optimizeImageUrl) . '" data-provider-url="' . rex_escape($providerUrl) . '" data-providers="' . rex_escape(json_encode($providers)) . '" data-upload-provider="' . rex_escape($uploadProviderId) . '" data-subpages="' . rex_escape(json_encode($subpages)) . '" data-feature-tagging="' . $featureTagging . '" data-feature-collections="' . $featureCollections . '" data-feature-metainfo-editing="' . $featureMetainfoEditing . '" data-feature-upload-resize="' . $featureUploadResize . '" data-upload-resize-width="' . $uploadResizeWidth . '" data-upload-resize-height="' . $uploadResizeHeight . '" data-alt-missing-filter-available="' . $altMissingFilterAvailable . '" data-ai-alt-url="' . rex_escape($aiAltUrl) . '" data-ai-alt-bulk-url="' . rex_escape($aiAltBulkUrl) . '" data-ai-auto-tag-url="' . rex_escape($aiAutoTagUrl) . '" data-rename-url="' . rex_escape($renameUrl) . '" data-rename-token="' . rex_escape($renameToken) . '" data-rename-pending="' . $renamePending . '"></div>'
            . "\n" . '<script type="application/json" id="mp-i18n-data">' . json_encode($i18nMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . '</script>';
        // Nur das LETZTE '</body>' ersetzen, nicht jedes Vorkommen: ein einfaches
        // str_replace() traf schon einmal versehentlich den Text eines eigenen
        // Inline-<script>-Kommentars (der zufaellig die Zeichenkette "</body>"
        // enthielt) weiter oben im Dokument -- der injizierte Block enthaelt
        // selbst ein <script>...</script> (i18n-Daten), dessen schliessendes Tag
        // dadurch mitten im FALSCHEN <script>-Element landete und dieses vorzeitig
        // beendete (sichtbarer JS-Quelltext als Seiteninhalt). Das echte
        // schliessende body-Tag ist immer das letzte im Dokument.
        $lastBodyPos = strrpos($content, '</body>');
        if (false !== $lastBodyPos) {
            $content = substr_replace($content, $inject . "\n" . '</body>', $lastBodyPos, strlen('</body>'));
        }
        $ep->setSubject($content);
    });
}

rex_extension::register('MEDIA_DELETED', static function (rex_extension_point $ep): void {
    \FriendsOfRedaxo\Mediaplace\ImageEditor::deleteBackup((string) $ep->getParam('filename'));
});
