<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Konsumiert die oeffentliche API des separaten "focuspoint"-Addons
 * (FriendsOfRedaxo/focuspoint, docs/developer.md), ohne dessen Dateien
 * anzufassen. Speicherung bleibt bewusst im klassischen Metainfo-Feld
 * (Standard: med_focuspoint auf rex_media) statt im eigenen med_json_data-
 * System -- Format/Effekte/Cache-Invalidierung gehoeren dem focuspoint-
 * Addon, wir schreiben nur mit denselben Regeln in dieselbe Spalte.
 *
 * Alle focuspoint-Klassen werden nur vollqualifiziert INNERHALB von
 * isAvailable()-abgesicherten Methoden referenziert (kein "use" am
 * Dateikopf) -- die Datei muss auch fehlerfrei ladbar sein, wenn das
 * focuspoint-Addon nicht installiert ist.
 */
class FocuspointIntegration
{
    public static function isAvailable(): bool
    {
        return \rex_addon::get('focuspoint')->isAvailable();
    }

    /**
     * Nicht nur "Addon installiert", sondern zusaetzlich "es gibt auch
     * mindestens einen Media-Manager-Typ, der einen Fokuspunkt-Effekt
     * nutzt" -- ohne das waere der Bearbeiten-Button nur ein totes
     * Klick-Ziel ohne jede Vorschau/Wirkung. Steuert die Sichtbarkeit des
     * Buttons im Detail-Panel (siehe detail_preview.php).
     */
    public static function canEdit(): bool
    {
        return self::isAvailable() && [] !== self::getTypesForImage();
    }

    /**
     * Media-Manager-Typen, die einen Fokuspunkt-Effekt nutzen (Effekt-Klasse
     * erbt von rex_effect_abstract_focuspoint), gruppiert nach Typname.
     * Feuert denselben Extension Point wie das Original (FOCUSPOINT_PREVIEW_SELECT),
     * damit individuell registrierte Labels von Drittaddons unveraendert greifen.
     *
     * @return array<string, array{label: string, meta: list<string>}>
     */
    public static function getTypesForImage(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $effects = \FriendsOfRedaxo\Focuspoint\Focuspoint::getFocuspointEffectsInUse();
        $names = array_unique(array_column($effects, 'name'));
        sort($names);
        $types = array_combine($names, array_fill(0, count($names), []));

        foreach (self::getMetafields() as $field) {
            foreach (\FriendsOfRedaxo\Focuspoint\Focuspoint::getFocuspointMetafieldInUse($field) as $e) {
                $types[$e['name']][] = $field;
            }
        }

        $result = [];
        foreach ($types as $name => $fields) {
            $result[$name] = ['label' => $name, 'meta' => $fields];
        }

        $result = \rex_extension::registerPoint(new \rex_extension_point('FOCUSPOINT_PREVIEW_SELECT', $result, ['effectsInUse' => $effects]));

        return is_array($result) ? $result : [];
    }

    /**
     * Alle Metainfo-Felder vom Typ "Focuspoint (AddOn)" (id => name).
     * Meist nur "med_focuspoint", kann aber von einem Admin um weitere
     * Felder ergaenzt worden sein.
     *
     * @return list<string>
     */
    public static function getMetafields(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        return array_values(\FriendsOfRedaxo\Focuspoint\Focuspoint::getMetafieldList());
    }

    /**
     * rex_effect_abstract_focuspoint::str2fp() (im focuspoint-Addon) liefert
     * die Koordinaten inkonsistent typisiert zurueck: als PHP-Strings, wenn
     * ein gespeicherter Wert per Regex geparst wurde, aber als Integer,
     * wenn mangels gespeichertem Wert der Default (rex_effect_abstract_focuspoint::$mitte)
     * greift. json_encode() serialisiert das dann mal als String, mal als
     * Zahl -- explizit auf float casten, damit unser API-Contract immer
     * echte JSON-Zahlen liefert (der Client verlaesst sich beim Speichern
     * auf .toFixed()).
     *
     * @return array{0: float, 1: float}
     */
    public static function getFocus(string $filename, string $metafield): array
    {
        $media = \FriendsOfRedaxo\Focuspoint\FocuspointMedia::get($filename);
        if (!$media) {
            return [50.0, 50.0];
        }

        $focus = $media->getFocus($metafield);

        return [(float) ($focus[0] ?? 50.0), (float) ($focus[1] ?? 50.0)];
    }

    /**
     * Validiert $metafield gegen die tatsaechlich vorhandenen Fokuspunkt-
     * Metainfo-Felder (Whitelist -- verhindert beliebige Spalten-Writes
     * ueber einen manipulierten Request) und $xy gegen das vom focuspoint-
     * Addon selbst genutzte Format. Schreibt danach direkt per rex_sql
     * (kein Roundtrip ueber das komplette klassische Metainfo-Formular,
     * das das Original fuers Speichern nutzt -- wir speichern isoliert nur
     * dieses eine Feld) und stoesst dieselbe Aufraeum-/Benachrichtigungs-
     * kette an, die auch ein normales Medien-Update ausloest.
     */
    public static function saveFocus(string $filename, string $metafield, string $xy): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
        if (!in_array($metafield, self::getMetafields(), true)) {
            return false;
        }
        if (false === \rex_effect_abstract_focuspoint::str2fp($xy)) {
            return false;
        }
        $media = \rex_media::get($filename);
        if (!$media) {
            return false;
        }

        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('media'));
        $sql->setWhere(['filename' => $filename]);
        $sql->setValue($metafield, $xy);
        $sql->addGlobalUpdateFields();
        $sql->update();

        \rex_media_cache::delete($filename);
        \rex_extension::registerPoint(new \rex_extension_point('MEDIA_UPDATED', '', ['filename' => $filename, 'id' => $media->getId()]));

        return true;
    }
}
