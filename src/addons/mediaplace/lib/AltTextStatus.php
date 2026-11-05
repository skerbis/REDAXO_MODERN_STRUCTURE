<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Prueft, ob fuer eine Datei ALT-Text fehlt -- fuer den Hinweis unter dem
 * "Metadaten bearbeiten"-Button im Detail-Panel. Eigene Metadaten (Feldtyp
 * "alt") haben Vorrang vor dem klassischen med_alt-Feld, wenn sie aktiv sind
 * und konfiguriert wurden; sonst zaehlt med_alt.
 */
class AltTextStatus
{
    /**
     * Klassisches Checkbox-Feld "kein ALT-Text noetig" (rein dekoratives
     * Bild) -- Gegenstueck zum "decorative"-Flag, das das EIGENE JSON-Feld
     * (Widget-Typ "alt") bereits laenger kennt (siehe isOwnValueEmpty()).
     * Ohne dieses Feld gibt es fuer med_alt keine Moeglichkeit, ein Bild
     * bewusst als "braucht keinen ALT-Text" auszuzeichnen -- leeres alt=""
     * ist barrierefreiheits-technisch valide, wurde bisher aber immer als
     * "fehlt" gewertet. Siehe AltTextFieldInstaller fuer die Anlage dieses
     * Feldes (Settings-Seite).
     */
    public const CLASSIC_DECORATIVE_FIELD = 'med_alt_decorative';


    /**
     * @param array<string, mixed> $ownData aktuelle Werte aus med_json_data
     */
    public static function isMissing(\rex_media $media, array $ownData): bool
    {
        // ALT-Text ist ein Bild-Barrierefreiheits-Konzept -- bei PDFs/Videos/
        // Audio etc. gibt es kein sinnvolles ALT-Attribut, der Hinweis waere
        // dort nur verwirrend.
        if (!$media->isImage()) {
            return false;
        }

        $ownField = self::findOwnAltField();
        if ($ownField instanceof MetainfoField) {
            return self::isOwnValueEmpty($ownField, $ownData[$ownField->getKey()] ?? null);
        }

        return self::isClassicAltEmpty($media);
    }

    /**
     * Ob der "Bitte ALT-Text hinterlegen"-Hinweis unter dem "Metadaten
     * bearbeiten"-Button (natives Formular, klassisches med_alt) gezeigt
     * werden soll. Ist das eigene Alt-Feld aktiv, hat DAS sein eigenes,
     * direkt am Feld sitzendes Hinweis-Widget (siehe
     * detail_field_body_alt.php) -- der Hinweis hier waere dann nicht nur
     * doppelt, sondern auch irrefuehrend: der Klick auf "Metadaten
     * bearbeiten" fuehrt zum klassischen med_alt-Feld im nativen Formular,
     * nicht zum eigentlich fehlenden eigenen Feld.
     *
     * @param array<string, mixed> $ownData aktuelle Werte aus med_json_data
     */
    public static function shouldShowNativeCanvasHint(\rex_media $media, array $ownData): bool
    {
        if (null !== self::findOwnAltField()) {
            return false;
        }
        return self::isMissing($media, $ownData);
    }

    /**
     * Oeffentlicher Zugriff auf dasselbe Feld, das isMissing() intern
     * ermittelt -- genutzt von der optionalen KI-Alt-Text-Generierung
     * (AiAltTextService/AiAltTextWriter), um zu wissen, WOHIN generierter
     * Text geschrieben werden soll (eigenes JSON-Feld vs. klassisches
     * med_alt), ohne dieselbe Ermittlungslogik ein zweites Mal zu duplizieren.
     */
    public static function resolveOwnAltField(): ?MetainfoField
    {
        return self::findOwnAltField();
    }

    private static function findOwnAltField(): ?MetainfoField
    {
        if (!\rex_config::get('mediaplace', 'enable_own_metadata', false)) {
            return null;
        }
        foreach (MetainfoFieldGroup::getFields() as $field) {
            if ('alt' === $field->getWidgetType()) {
                return $field;
            }
        }
        return null;
    }

    /**
     * "Fehlt" heisst bei einem MEHRSPRACHIGEN eigenen Feld: mindestens eine
     * der von AiAltTextWriter::resolveClangIds() abgedeckten Sprachen hat
     * noch keinen Text -- nicht erst, wenn ALLE leer sind. Vorher genuegte
     * bereits irgendeine einzelne gepflegte Sprache, um das Bild komplett
     * als "erledigt" zu werten, wodurch andere, noch leere Sprachen nie
     * mehr von der KI-Vervollstaendigung angefasst wurden (analog zum
     * gleichartigen Bug in isClassicAltValueMissingAnyLanguage()).
     */
    private static function isOwnValueEmpty(MetainfoField $field, mixed $value): bool
    {
        if (!is_array($value)) {
            return true;
        }
        if (!empty($value['decorative'])) {
            return false;
        }

        $textData = $value['text'] ?? [];

        if (!$field->isTranslatable()) {
            return '' === trim((string) $textData);
        }

        $textData = is_array($textData) ? $textData : [];
        foreach (AiAltTextWriter::resolveClangIds() as $clangId) {
            if ('' === trim((string) ($textData[(string) $clangId] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private static function isClassicAltEmpty(\rex_media $media): bool
    {
        if (!self::classicAltFieldExists()) {
            return false;
        }

        $decorativeExists = self::hasClassicDecorativeField();
        $cols = 'med_alt' . ($decorativeExists ? ', ' . self::CLASSIC_DECORATIVE_FIELD : '');

        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT ' . $cols . ' FROM ' . \rex::getTable('media') . ' WHERE id = ?', [$media->getId()]);
        if (1 !== $sql->getRows()) {
            return false;
        }

        if ($decorativeExists && (bool) $sql->getValue(self::CLASSIC_DECORATIVE_FIELD)) {
            return false;
        }

        $rawAlt = (string) $sql->getValue('med_alt');

        return self::isClassicAltValueMissingAnyLanguage($rawAlt);
    }

    /**
     * Ob $rawAlt (Rohwert der med_alt-Spalte) fuer IRGENDEINE der von
     * AiAltTextWriter::resolveClangIds() abgedeckten Sprachen noch keine
     * Uebersetzung hat -- nicht nur fuer die aktuelle. Ein Bild, bei dem nur
     * Deutsch gepflegt ist, gilt damit weiterhin als "unvollstaendig",
     * solange z.B. Niederlaendisch noch fehlt, auch wenn man es gerade in
     * der deutschen Sprachvariante des Backends betrachtet -- vorher wurde
     * hier nur rex_clang::getCurrentId() geprueft, wodurch ein Bild mit
     * "nur die aktuelle Sprache gepflegt" faelschlich als komplett
     * erledigt galt und von der KI-Vervollstaendigung (Api\AiAltComplete,
     * AiAltCompleteCronjob) sowie der "Medien ohne ALT-Text"-Ansicht nie
     * mehr angefasst wurde.
     */
    public static function isClassicAltValueMissingAnyLanguage(string $rawAlt): bool
    {
        if (!\rex_addon::get('metainfo_lang_fields')->isAvailable() || !class_exists('FriendsOfRedaxo\\MetaInfoLangFields\\MetainfoLangHelper')) {
            return '' === trim($rawAlt);
        }

        foreach (AiAltTextWriter::resolveClangIds() as $clangId) {
            if (!\FriendsOfRedaxo\MetaInfoLangFields\MetainfoLangHelper::hasTranslationForLanguage($rawAlt, $clangId)) {
                return true;
            }
        }

        return false;
    }

    private static function classicAltFieldExists(): bool
    {
        return self::hasClassicAltField();
    }

    /** Oeffentlich fuer denselben Zweck wie hasClassicDecorativeField(). */
    public static function hasClassicAltField(): bool
    {
        return self::metainfoFieldExists('med_alt');
    }

    /**
     * Oeffentlich, damit sowohl die Settings-Seite (Zustand fuer den
     * Installations-Button) als auch AltTextFieldInstaller (Idempotenz-Check
     * vor dem Anlegen) dieselbe Abfrage nutzen koennen.
     */
    public static function hasClassicDecorativeField(): bool
    {
        return self::metainfoFieldExists(self::CLASSIC_DECORATIVE_FIELD);
    }

    private static function metainfoFieldExists(string $name): bool
    {
        if (!\rex_addon::get('metainfo')->isAvailable()) {
            return false;
        }

        $sql = \rex_sql::factory();
        $exists = $sql->getArray(
            'SELECT id FROM ' . \rex::getTable('metainfo_field') . ' WHERE name = :name',
            ['name' => $name],
        );

        return [] !== $exists;
    }

    /**
     * True, wenn es UEBERHAUPT ein ALT-Text-Feld gibt (eigenes oder
     * klassisches) -- steuert, ob die "Medien ohne ALT-Text"-Sidebar-Ansicht
     * ueberhaupt sinnvoll ist (siehe boot.php OUTPUT_FILTER,
     * data-alt-missing-filter-available).
     */
    public static function hasAltField(): bool
    {
        return null !== self::findOwnAltField() || self::classicAltFieldExists();
    }

    /**
     * Bulk-Variante von isMissing() fuer die "Medien ohne ALT-Text"-Ansicht
     * (Api\MediaList.php, filter[alt_missing]): liest alle Bilddateien in
     * einem Rutsch statt pro Datei rex_media::get() + isMissing() aufzurufen,
     * folgt aber derselben Prioritaets-Logik (eigenes Feld vor med_alt).
     *
     * @return list<string>
     */
    public static function getFilenamesMissingAlt(): array
    {
        $ownField = self::findOwnAltField();

        if (null === $ownField && !self::classicAltFieldExists()) {
            return [];
        }

        $decorativeExists = null === $ownField && self::hasClassicDecorativeField();
        $selectCol = null !== $ownField
            ? 'med_json_data'
            : 'med_alt' . ($decorativeExists ? ', ' . self::CLASSIC_DECORATIVE_FIELD : '');
        $sql = \rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT filename, ' . $selectCol . " FROM " . \rex::getTable('media') . " WHERE filetype LIKE 'image/%'",
        );

        $missing = [];
        foreach ($rows as $row) {
            if (null !== $ownField) {
                $json = json_decode((string) ($row['med_json_data'] ?? ''), true);
                $ownData = is_array($json) ? $json : [];
                if (self::isOwnValueEmpty($ownField, $ownData[$ownField->getKey()] ?? null)) {
                    $missing[] = (string) $row['filename'];
                }
                continue;
            }
            if ($decorativeExists && !empty($row[self::CLASSIC_DECORATIVE_FIELD])) {
                continue;
            }
            $rawAlt = (string) ($row['med_alt'] ?? '');
            if (self::isClassicAltValueMissingAnyLanguage($rawAlt)) {
                $missing[] = (string) $row['filename'];
            }
        }

        return $missing;
    }
}
