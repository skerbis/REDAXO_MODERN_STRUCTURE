<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * MediaPlace – JSON Metainfo Storage API
 *
 * Saves metadata JSON for media files.
 * Backend-only (session auth).
 *
 * POST /api/backend/mediaplace_json_metainfo/{filename}
 * Body: { "field_key": value, ... }
 */
class JsonMetainfo extends rex_api_function
{
    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        if (!\rex::getUser()) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        $filename = \rex_request('filename', 'string', '');
        $method = \rex_request::server('REQUEST_METHOD', 'string', 'GET');

        if (!$filename) {
            \rex_response::setStatus(\rex_response::HTTP_BAD_REQUEST);
            \rex_response::sendJson(['error' => 'Missing filename']);
            exit;
        }

        $media = \rex_media::get($filename);
        if (!$media) {
            \rex_response::setStatus(\rex_response::HTTP_NOT_FOUND);
            \rex_response::sendJson(['error' => 'Media not found']);
            exit;
        }

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasCategoryAccess($media->getCategoryId())) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        try {
            if ('GET' === $method) {
                return $this->handleGet($media);
            }

            if ('POST' === $method || 'PATCH' === $method) {
                return $this->handleSave($media);
            }

            \rex_response::setStatus(405);
            \rex_response::sendJson(['error' => 'Method not allowed']);
            exit;
        } catch (\Exception $e) {
            \rex_response::setStatus(\rex_response::HTTP_INTERNAL_ERROR);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        }
    }

    private function handleGet(\rex_media $media): rex_api_result
    {
        \FriendsOfRedaxo\Mediaplace\SystemTagManager::ensureSchema();
        $data = \FriendsOfRedaxo\Mediaplace\MetainfoJsonStorage::loadFromMedia($media);

        $fieldsData = [];
        foreach (\FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::getFields() as $field) {
            if ('tags' === $field->getWidgetType()) {
                continue;
            }

            if (!$field->isVisibleForMedia($media)) {
                continue;
            }

            $fieldsData[] = [
                'id' => $field->getId(),
                'key' => $field->getKey(),
                'label' => $field->getLabel(),
                'widget_type' => $field->getWidgetType(),
                'options' => $field->getOptions(),
                'translatable' => $field->isTranslatable(),
                'image_only' => $field->isImageOnly(),
            ];
        }

        $clangs = [];
        foreach (\rex_clang::getAll() as $clang) {
            $clangs[] = [
                'id' => $clang->getId(),
                'name' => $clang->getName(),
                'code' => $clang->getCode(),
            ];
        }

        $systemTags = \FriendsOfRedaxo\Mediaplace\SystemTagManager::getTagsForFilename($media->getFileName());
        $systemTagCatalog = \FriendsOfRedaxo\Mediaplace\SystemTagManager::getCatalog();

        $apiInfo = $this->buildFastInfoFields($media);

        // detail_html nur bauen, wenn der Client tatsaechlich das Detail-Panel
        // anzeigen will (render_detail=1, siehe showDetail() in mediaplace.js)
        // -- andere Aufrufer (z.B. setFileCollectionMembership()) wollen nur
        // data/system_tags und sollen das Rendering nicht unnoetig mit bezahlen.
        $wantDetail = \rex_request('render_detail', 'bool', false);
        $detailHtml = $wantDetail ? $this->renderDetailHtml($media, $apiInfo, $data, $fieldsData, $clangs, $systemTags, $systemTagCatalog) : '';

        // Auch als eigenes Top-Level-Feld (nicht nur in detail_html eingebettet):
        // erlaubt einen leichten Refresh nach dem nativen Metainfo-Canvas
        // (closeMetainfoCanvas() in modules/detail.js), ohne das ganze Panel neu
        // zu rendern und dabei unsichtbar ungespeicherte Aenderungen zu verwerfen.
        $altTextMissing = (bool) \rex_config::get('mediaplace', 'enable_metainfo_editing', false)
            && \FriendsOfRedaxo\Mediaplace\AltTextStatus::shouldShowNativeCanvasHint($media, $data);

        \rex_response::sendJson([
            'success' => true,
            'data' => $data,
            'fields' => $fieldsData,
            'clangs' => $clangs,
            'system_tags' => $systemTags,
            'system_tag_catalog' => $systemTagCatalog,
            'detail_html' => $detailHtml,
            'title' => $apiInfo['title'],
            'alt_text_missing' => $altTextMissing,
        ]);
        exit;
    }

    /**
     * Berechnet die Info-Felder (Titel/Groesse/Masse/Datum/is_in_use/...)
     * direkt aus $media -- alles simple rex_media-Getter auf einem Objekt,
     * das fuer die Rechtepruefung ohnehin schon geladen ist, plus
     * rex_mediapool::mediaIsInUse(), das exakt dieselbe REDAXO-Core-Funktion
     * ist, die auch media/{filename}/info (FriendsOfRedaxo/api-Addon) nutzt --
     * kein zusaetzlicher HTTP-Roundtrip noetig, kein Verhaltensunterschied.
     * (Ein frueherer Versuch, is_in_use per separatem Lazy-Load nachzuladen,
     * ging von einer angeblich mehrsekuendigen Laufzeit dieser Funktion aus --
     * per curl mit erzwungenem IPv4 verifiziert war das ein Messfehler durch
     * eine macOS-.local-mDNS-Aufloesungsverzoegerung, nicht die Funktion
     * selbst: isoliert braucht der komplette api-Addon-Endpunkt inkl.
     * mediaIsInUse() ca. 20ms. Deshalb hier bewusst wieder synchron.)
     * @return array<string, mixed>
     */
    private function buildFastInfoFields(\rex_media $media): array
    {
        // mediaIsInUse() liefert false ODER ein fertiges, von REDAXO selbst
        // erzeugtes HTML-Fragment inkl. Links zu den referenzierenden Objekten
        // (Artikel, plus was auch immer andere Addons per MEDIA_IS_IN_USE
        // Extension Point beisteuern, siehe yform_value_mediaplace::
        // isMediaInUse() fuer unser eigenes Beispiel) -- bisher wurde das auf
        // ein reines Bool verkuerzt und der Rest verworfen. isInUseDetail
        // wird jetzt zusaetzlich mitgegeben, damit "Datei in Benutzung"-
        // Hinweise ueberall (Detail-Panel-Loeschen, Massenaktionen, ...)
        // dieselbe verlinkte Detailauskunft zeigen koennen wie der klassische
        // Medienpool selbst, statt einer generischen Meldung ohne Kontext.
        try {
            $inUseResult = \rex_mediapool::mediaIsInUse($media->getFileName());
        } catch (\Throwable $e) {
            $inUseResult = false;
        }
        $isInUse = false !== $inUseResult;
        $isInUseDetail = $isInUse ? (string) $inUseResult : null;

        return [
            'filename' => $media->getFileName(),
            'originalname' => (string) ($media->getValue('originalname') ?: $media->getFileName()),
            'title' => (string) $media->getTitle(),
            'filetype' => (string) $media->getType(),
            'filesize' => (int) $media->getSize(),
            'width' => (int) $media->getWidth(),
            'height' => (int) $media->getHeight(),
            'createdate' => $media->getCreateDate(),
            'createuser' => (string) $media->getCreateUser(),
            'updatedate' => $media->getUpdateDate(),
            'updateuser' => (string) $media->getUpdateUser(),
            'is_image' => (bool) $media->isImage(),
            'is_in_use' => $isInUse,
            'is_in_use_detail' => $isInUseDetail,
            'file_exists' => (bool) $media->fileExists(),
            'category_id' => (int) $media->getCategoryId(),
            'focuspoint_available' => $media->isImage() && \FriendsOfRedaxo\Mediaplace\FocuspointIntegration::canEdit(),
            'image_edit_available' => \FriendsOfRedaxo\Mediaplace\ImageEditor::canEdit($media),
            'pdf_thumb_available' => \FriendsOfRedaxo\Mediaplace\DetailPanelFormatter::isPdfFilename($media->getFileName()) && \FriendsOfRedaxo\Mediaplace\PdfoutIntegration::isThumbAvailable(),
            'pdf_viewer_url' => \FriendsOfRedaxo\Mediaplace\PdfoutIntegration::viewerUrl($media->getFileName(), $media->getUpdateDate() ? (string) $media->getUpdateDate() : null, (int) $media->getSize()),
            'optimize_video_available' => \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::canOptimize($media->getFileName()),
            // Nur die (billige, keine ffprobe-Kosten) Sichtbarkeits-Flag hier --
            // die eigentlichen Technikdaten werden erst beim Aufklappen lazy
            // nachgeladen, siehe Api\VideoInfo.php.
            'video_details_available' => \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::isAvailable() && \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::isSupportedVideo($media->getFileName()),
            // Laeuft GERADE eine Optimierung fuer diese Datei (auch wenn nicht
            // in dieser Browser-Session gestartet, z.B. ueber ffmpeg's eigene
            // Video-Tools-Seite oder von einem anderen User) -- damit das
            // Detail-Panel beim (Wieder-)Oeffnen sofort den Status zeigt statt
            // erst nach einem erneuten Klick auf "optimieren".
            'optimize_video_job' => \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::getActiveJobForFile($media->getFileName()),
            'optimize_video_status' => \FriendsOfRedaxo\Mediaplace\FfmpegIntegration::getOptimizedStatus($media->getFileName()),
            'optimize_image_available' => \FriendsOfRedaxo\Mediaplace\ImageOptimizer::canOptimize($media->getFileName()),
            'optimize_image_target' => \FriendsOfRedaxo\Mediaplace\ImageOptimizer::getTargetSize(),
        ];
    }

    /**
     * Baut das komplette Detail-Panel (Header, Vorschau, Bearbeitungs-Sektion,
     * Info-Tabelle, Aktionen) als HTML-String -- ersetzt renderDetail() +
     * die dazugehoerigen Render-Helfer, die frueher in mediaplace.js per
     * JS-String-Konkatenation liefen.
     *
     * Alle Info-Felder (Titel/Groesse/Masse/Datum/is_in_use/...) kommen aus
     * buildFastInfoFields() -- simple rex_media-Getter plus
     * rex_mediapool::mediaIsInUse(), kein Roundtrip noetig, siehe Kommentar
     * dort. Das Addon bleibt bei rex_media/rex_mediapool als Datenquelle,
     * weil das exakt dieselben Klassen/Funktionen sind, die auch das
     * api-Addon fuer media/{filename}/info nutzt -- kein Verhaltensunterschied,
     * nur ein vermiedener zusaetzlicher HTTP-Hop fuer Werte, die durch das
     * ohnehin schon geladene $media-Objekt bereits da sind.
     */
    private function renderDetailHtml(\rex_media $media, array $apiInfo, array $data, array $fields, array $clangs, array $systemTags, array $systemTagCatalog): string
    {
        $filename = $media->getFileName();

        // System-Tags mit "collection:"-Praefix sind Sammlungs-Mitgliedschaften
        // (siehe SystemTagManager::COLLECTION_PREFIX, gespiegelt in COLLECTION_TAG_PREFIX
        // in mediaplace.js) -- werden im Tag-Editor nicht angezeigt, aber als
        // Namensliste in der Info-Tabelle gebraucht.
        $normalTags = [];
        $collectionNames = [];
        foreach ($systemTags as $tag) {
            $name = trim((string) ($tag['name'] ?? ''));
            if ('' === $name) {
                continue;
            }
            if (\FriendsOfRedaxo\Mediaplace\SystemTagManager::isCollectionTagName($name)) {
                $collName = trim(mb_substr($name, mb_strlen(\FriendsOfRedaxo\Mediaplace\SystemTagManager::COLLECTION_PREFIX)));
                if ('' !== $collName) {
                    $collectionNames[] = mb_substr($collName, 0, 60);
                }
                continue;
            }
            $normalTags[] = [
                'name' => $name,
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($tag['color'] ?? '')) ? strtolower((string) $tag['color']) : '#4a90d9',
            ];
        }

        // updatedate kommt vom api-Addon als Unix-Timestamp (rex_media::getUpdateDate()),
        // dient hier nur als Cache-Buster-Token fuer Vorschau-URLs.
        $updatedate = (string) ($apiInfo['updatedate'] ?? '');

        $info = [
            'filename' => $filename,
            'originalname' => (string) ($apiInfo['originalname'] ?? $filename),
            'title' => (string) ($apiInfo['title'] ?? ''),
            'filetype' => (string) ($apiInfo['filetype'] ?? ''),
            'filesize' => (int) ($apiInfo['filesize'] ?? 0),
            'width' => (int) ($apiInfo['width'] ?? 0),
            'height' => (int) ($apiInfo['height'] ?? 0),
            'created_formatted' => \FriendsOfRedaxo\Mediaplace\DetailPanelFormatter::formatDate($apiInfo['createdate'] ?? null),
            'createuser' => (string) ($apiInfo['createuser'] ?? ''),
            'updated_formatted' => \FriendsOfRedaxo\Mediaplace\DetailPanelFormatter::formatDate($apiInfo['updatedate'] ?? null),
            'updateuser' => (string) ($apiInfo['updateuser'] ?? ''),
            'updatedate' => $updatedate,
            'is_image' => (bool) ($apiInfo['is_image'] ?? $media->isImage()),
            'is_in_use' => (bool) ($apiInfo['is_in_use'] ?? false),
            'is_in_use_detail' => $apiInfo['is_in_use_detail'] ?? null,
            'file_exists' => (bool) ($apiInfo['file_exists'] ?? $media->fileExists()),
            'category_id' => (int) ($apiInfo['category_id'] ?? $media->getCategoryId()),
            'focuspoint_available' => (bool) ($apiInfo['focuspoint_available'] ?? false),
            'image_edit_available' => (bool) ($apiInfo['image_edit_available'] ?? false),
            'pdf_thumb_available' => (bool) ($apiInfo['pdf_thumb_available'] ?? false),
            'pdf_viewer_url' => $apiInfo['pdf_viewer_url'] ?? null,
            'optimize_video_available' => (bool) ($apiInfo['optimize_video_available'] ?? false),
            'video_details_available' => (bool) ($apiInfo['video_details_available'] ?? false),
            'optimize_video_job' => $apiInfo['optimize_video_job'] ?? null,
            'optimize_video_status' => $apiInfo['optimize_video_status'] ?? null,
            'optimize_image_available' => (bool) ($apiInfo['optimize_image_available'] ?? false),
            'optimize_image_target' => $apiInfo['optimize_image_target'] ?? null,
        ];

        $fragment = new \rex_fragment();
        $fragment->setVar('info', $info, false);
        $fragment->setVar('data', $data, false);
        $fragment->setVar('fields', $fields, false);
        $fragment->setVar('clangs', $clangs, false);
        $fragment->setVar('system_tags_normal', $normalTags, false);
        $fragment->setVar('system_tag_catalog', $systemTagCatalog, false);
        $fragment->setVar('collection_names', $collectionNames, false);
        $fragment->setVar('category_list', Categories::getFlatCategoryList(), false);
        // Feature-Toggles (Einstellungsseite) -- siehe features-Objekt in mediaplace.js
        // bzw. den entsprechenden Kommentar in boot.php (disable_*-Speicherung).
        $fragment->setVar('feature_own_metadata', (bool) \rex_config::get('mediaplace', 'enable_own_metadata', false), false);
        $fragment->setVar('feature_tagging', !\rex_config::get('mediaplace', 'disable_tagging', false), false);
        $fragment->setVar('feature_collections', !\rex_config::get('mediaplace', 'disable_collections', false), false);
        $fragment->setVar('rename_available', \FriendsOfRedaxo\Mediaplace\MediaRename::canRename($media), false);
        $featureMetainfoEditing = (bool) \rex_config::get('mediaplace', 'enable_metainfo_editing', false);
        $fragment->setVar('feature_metainfo_editing', $featureMetainfoEditing, false);
        $fragment->setVar('alt_text_missing', $featureMetainfoEditing && \FriendsOfRedaxo\Mediaplace\AltTextStatus::shouldShowNativeCanvasHint($media, $data), false);

        return $fragment->parse('mediaplace/detail_panel.php');
    }

    private function handleSave(\rex_media $media): rex_api_result
    {
        \FriendsOfRedaxo\Mediaplace\SystemTagManager::ensureSchema();
        $input = $this->getJsonInput();

        if (isset($input['__system_tags']) && is_array($input['__system_tags'])) {
            \FriendsOfRedaxo\Mediaplace\SystemTagManager::saveTagsForFilename($media->getFileName(), $input['__system_tags']);
            unset($input['__system_tags']);
        }

        // Load current data
        $data = \FriendsOfRedaxo\Mediaplace\MetainfoJsonStorage::loadFromMedia($media);

        // Get all field definitions to validate input
        $fields = \FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::getFields();
        $fieldsByKey = [];
        foreach ($fields as $field) {
            if ('tags' === $field->getWidgetType()) {
                continue;
            }
            $fieldsByKey[$field->getKey()] = $field;
        }

        // Process each input value
        foreach ($input as $key => $value) {
            if (!isset($fieldsByKey[$key])) {
                continue; // Ignore unknown fields
            }

            $field = $fieldsByKey[$key];

            // Normalize widget value once and store directly.
            // This supports scalar values, language maps and nested widget payloads.
            $widget = $field->createWidget();
            $normalized = $widget ? $widget->normalizeValue($value) : $value;
            \FriendsOfRedaxo\Mediaplace\MetainfoJsonStorage::setFieldValue($data, $key, $normalized);
        }

        // Save to database
        if (!\FriendsOfRedaxo\Mediaplace\MetainfoJsonStorage::saveToMedia($media, $data)) {
            \rex_response::setStatus(\rex_response::HTTP_INTERNAL_ERROR);
            \rex_response::sendJson(['error' => 'Failed to save data']);
            exit;
        }

        \rex_response::sendJson(['success' => true, 'data' => $data]);
        exit;
    }

    /**
     * Get raw JSON input from request body.
     * @return array<string, mixed>
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Normalize a single value before storage.
     */
    private function normalizeValue(\FriendsOfRedaxo\Mediaplace\MetainfoField $field, mixed $value): mixed
    {
        $widget = $field->createWidget();
        if (!$widget) {
            return $value;
        }
        return $widget->normalizeValue($value);
    }
}
