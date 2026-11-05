<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Installiert die klassischen ALT-Text-/Dekorativ-Metainfo-Felder (med_alt,
 * AltTextStatus::CLASSIC_DECORATIVE_FIELD) auf Knopfdruck aus den
 * MediaPlace-Einstellungen -- fuer Betreiber, die (noch) kein eigenes
 * JSON-Alt-Feld (Widget-Typ "alt") einrichten, sondern beim klassischen
 * Medienpool-Formular bleiben wollen. Nutzt REDAXOs eigene oeffentliche
 * Metainfo-API (rex_metainfo_add_field()) -- dieselbe Funktion, die auch
 * metainfo's eigene Backend-Seite fuers Anlegen neuer Felder verwendet,
 * kein eigenes SQL/Spalten-Handling.
 */
class AltTextFieldInstaller
{
    private const ALT_FIELD = 'med_alt';
    private const ALT_FIELD_PRIORITY = 999;

    public static function isAvailable(): bool
    {
        return \rex_addon::get('metainfo')->isAvailable();
    }

    /** @return string|null Fehlermeldung, oder null bei Erfolg/bereits vorhanden. */
    public static function installAltField(): ?string
    {
        if (AltTextStatus::hasClassicAltField()) {
            return null;
        }
        return self::install(self::ALT_FIELD, 'ALT-Text', \rex_metainfo_default_type::TEXT, self::ALT_FIELD_PRIORITY);
    }

    /**
     * @return string|null Fehlermeldung, oder null bei Erfolg/bereits vorhanden.
     */
    public static function installDecorativeField(): ?string
    {
        if (AltTextStatus::hasClassicDecorativeField()) {
            return null;
        }

        // Direkt HINTER med_alt einsortieren, nicht ans Ende der (fuer uns
        // fremden) Feldliste: rex_metainfo_add_field() ruft intern
        // rex_sql_util::organizePriorities() auf, die alle Felder per
        // "ORDER BY priority, updatedate" neu durchnummeriert -- bei
        // gleichem priority-Wert gewinnt der AELTERE Zeitstempel den
        // vorderen Platz. Denselben priority-Wert wie das GERADE
        // bestehende med_alt zu uebergeben platziert unser neu erstelltes
        // (also juengeres) Feld deshalb zuverlaessig direkt danach,
        // unabhaengig davon, welche anderen med_*-Felder dazwischen liegen.
        $priority = self::currentAltFieldPriority() ?? self::ALT_FIELD_PRIORITY;

        return self::install(AltTextStatus::CLASSIC_DECORATIVE_FIELD, 'Dekoratives Bild (kein ALT-Text nötig)', \rex_metainfo_default_type::CHECKBOX, $priority);
    }

    private static function currentAltFieldPriority(): ?int
    {
        $sql = \rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT priority FROM ' . \rex::getTable('metainfo_field') . ' WHERE name = :name',
            ['name' => self::ALT_FIELD],
        );
        return [] !== $rows ? (int) $rows[0]['priority'] : null;
    }

    private static function install(string $name, string $title, int $typeId, int $priority): ?string
    {
        if (!self::isAvailable()) {
            return 'metainfo addon not available';
        }

        // boot.php des metainfo-Addons laedt diese Datei nur bei
        // rex::isBackend() -- in dem Kontext, in dem diese Klasse aufgerufen
        // wird (Settings-Seite), ist das zwar immer der Fall, defensiv
        // trotzdem nachladen statt uns auf Boot-Reihenfolge zu verlassen.
        if (!\function_exists('rex_metainfo_add_field')) {
            require_once \rex_path::addon('metainfo', 'functions/function_metainfo.php');
        }

        $result = \rex_metainfo_add_field($title, $name, $priority, '', $typeId, '');
        if (true !== $result) {
            return \is_string($result) ? $result : 'unknown error';
        }

        \rex_delete_cache();
        return null;
    }
}
