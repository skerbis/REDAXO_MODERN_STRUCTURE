<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Schreibt generierten ALT-Text ins richtige Ziel -- eigenes JSON-Feld
 * (Widget-Typ "alt") falls aktiv+konfiguriert, sonst klassisches med_alt.
 * Nutzt AltTextStatus::resolveOwnAltField() fuer dieselbe Prioritaets-Logik
 * wie der "ALT-Text fehlt"-Filter, statt sie ein zweites Mal zu ermitteln.
 * Nur fuer Api\AiAltBulk.php (Massengenerierung, kein Einzel-Review) --
 * der Einzeldatei-Button (Api\AiAltText.php) schreibt bewusst NICHT selbst,
 * siehe dortiger Docblock.
 */
class AiAltTextWriter
{
    /**
     * @return list<int>
     */
    public static function resolveClangIds(): array
    {
        $ownField = AltTextStatus::resolveOwnAltField();
        if ($ownField instanceof MetainfoField && $ownField->isTranslatable()) {
            return \rex_clang::getAllIds();
        }

        // Klassisches med_alt: seit der Umstellung auf metainfo_lang_fields
        // (lang_text) mehrsprachig -- alle Sprachen anbieten statt nur die
        // Startsprache.
        if (\rex_addon::get('metainfo_lang_fields')->isAvailable()) {
            return \rex_clang::getAllIds();
        }

        return [\rex_clang::getStartId()];
    }

    /**
     * @param array<string, string> $textByClangId clang-id (String-Key) => Text
     */
    public static function write(\rex_media $media, array $textByClangId): void
    {
        if ([] === $textByClangId) {
            return;
        }

        $ownField = AltTextStatus::resolveOwnAltField();
        if ($ownField instanceof MetainfoField) {
            self::writeOwnField($media, $ownField, $textByClangId);

            return;
        }

        self::writeClassicField($media, $textByClangId);
    }

    /**
     * @param array<string, string> $textByClangId
     */
    private static function writeOwnField(\rex_media $media, MetainfoField $field, array $textByClangId): void
    {
        $data = MetainfoJsonStorage::loadFromMedia($media);
        $current = MetainfoJsonStorage::getFieldValue($data, $field->getKey());
        $value = is_array($current) ? $current : [];

        if ($field->isTranslatable()) {
            $textData = is_array($value['text'] ?? null) ? $value['text'] : [];
            foreach ($textByClangId as $clangId => $text) {
                $textData[(string) $clangId] = $text;
            }
            $value['text'] = $textData;
        } else {
            $value['text'] = reset($textByClangId);
        }

        MetainfoJsonStorage::setFieldValue($data, $field->getKey(), $value);
        MetainfoJsonStorage::saveToMedia($media, $data);
    }

    /**
     * @param array<string, string> $textByClangId
     */
    private static function writeClassicField(\rex_media $media, array $textByClangId): void
    {
        $filename = $media->getFileName();

        // med_alt ist seit der Umstellung auf metainfo_lang_fields ein
        // lang_text-Feld ([{"clang_id":1,"value":"..."}]) -- bestehende
        // Sprachen aus dem aktuellen Wert erhalten und nur die per KI
        // generierten Sprachen mergen, statt die Spalte komplett zu
        // ueberschreiben (das wuerde sonst alle anderen Sprachen loeschen).
        $value = $textByClangId;
        if (\rex_addon::get('metainfo_lang_fields')->isAvailable() && class_exists('FriendsOfRedaxo\\MetaInfoLangFields\\MetainfoLangHelper')) {
            $existing = [];
            foreach (\FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper::normalizeLanguageData($media->getValue('med_alt')) as $item) {
                $existing[(string) $item['clang_id']] = (string) $item['value'];
            }
            $value = array_replace($existing, $textByClangId);
        }

        $entries = [];
        foreach ($value as $clangId => $text) {
            $entries[] = ['clang_id' => (int) $clangId, 'value' => (string) $text];
        }
        $json = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // rex_media_service::updateMedia() ist HIER die falsche Wahl: die
        // Funktion kennt nur title/category_id/Datei-Upload-Spalten fest
        // verdrahtet, jedes 'med_alt' im $data-Array wird komplett ignoriert
        // -- der Text landete also nie in der Datenbank. Schlimmer noch:
        // sie liest $data['category_id'] OHNE Fallback (kein isset-Check),
        // ein fehlender Key wuerde still zu (int) null = 0 und die Datei in
        // die Wurzelkategorie verschieben. Direkt per rex_sql schreiben --
        // exakt dasselbe, bereits bewaehrte Muster wie
        // FocuspointIntegration::saveFocus() fuer ein einzelnes klassisches
        // Metainfo-Feld (kein Formular-Roundtrip, gleiche Aufraeum-/
        // Benachrichtigungskette wie ein normales Medien-Update).
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('media'));
        $sql->setWhere(['filename' => $filename]);
        $sql->setValue('med_alt', $json);
        $sql->addGlobalUpdateFields();
        $sql->update();

        \rex_media_cache::delete($filename);
        \rex_extension::registerPoint(new \rex_extension_point('MEDIA_UPDATED', '', ['filename' => $filename, 'id' => $media->getId()]));
    }
}
