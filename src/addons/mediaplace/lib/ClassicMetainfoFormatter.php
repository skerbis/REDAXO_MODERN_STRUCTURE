<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Formatiert med_json_data fuer die schreibgeschuetzte Anzeige im
 * klassischen Medienpool-Bearbeiten-Formular (boot.php, METAINFO_CUSTOM_FIELD).
 * Loest bekannte Werte-Formen (Sprach-Map, ALT-Feld {text,decorative}) in
 * lesbaren Text auf, statt pro Schluessel rohes JSON zu zeigen.
 */
class ClassicMetainfoFormatter
{
    public static function render(array $decoded): string
    {
        if (empty($decoded)) {
            return self::emptyHint();
        }

        $fieldsByKey = [];
        foreach (MetainfoFieldGroup::getFields() as $fieldDef) {
            $fieldsByKey[$fieldDef->getKey()] = $fieldDef;
        }

        $clangNames = [];
        foreach (\rex_clang::getAll() as $clang) {
            $clangNames[$clang->getId()] = $clang->getName();
        }

        $rows = '';
        foreach ($decoded as $key => $value) {
            $fieldDef = $fieldsByKey[$key] ?? null;
            $displayValue = self::formatValue($value, $fieldDef, $clangNames);
            if ('' === $displayValue) {
                continue;
            }
            $displayLabel = $fieldDef ? $fieldDef->getLabel() : (string) $key;
            // Kein Bootstrap "dl-horizontal": dessen feste dt-Breite (160px) ist auf
            // die volle Formularbreite ausgelegt und erzeugt hier, verschachtelt in
            // der eigenen dd-Spalte von "MediaPlace Metadaten", einen unverhaeltnis-
            // maessig grossen Einzug. Gestapeltes Layout stattdessen.
            $rows .= '<dt style="font-weight:600;margin:6px 0 0;">' . \rex_escape($displayLabel) . '</dt><dd style="margin:0 0 6px;">' . $displayValue . '</dd>';
        }

        return '' === $rows ? self::emptyHint() : '<dl style="margin:0;">' . $rows . '</dl>';
    }

    private static function emptyHint(): string
    {
        return '<p class="text-muted">' . \rex_i18n::msg('mediaplace_metainfo_readonly_empty') . '</p>';
    }

    /**
     * @param array<int, string> $clangNames
     */
    private static function formatValue(mixed $value, ?MetainfoField $fieldDef, array $clangNames): string
    {
        if ('alt' === $fieldDef?->getWidgetType() && is_array($value)) {
            if (!empty($value['decorative'])) {
                return '<em>' . \rex_escape(\rex_i18n::msg('mediaplace_alt_decorative_short')) . '</em>';
            }
            return self::formatValue($value['text'] ?? '', null, $clangNames);
        }

        if (is_array($value) && ($fieldDef?->isTranslatable() || self::looksLikeClangMap($value, $clangNames))) {
            return self::formatTranslatable($value, $clangNames);
        }

        if (is_scalar($value) || null === $value) {
            $text = null === $value ? '' : (string) $value;
            return nl2br(\rex_escape($text));
        }

        return nl2br(\rex_escape(json_encode($value, JSON_UNESCAPED_UNICODE) ?: ''));
    }

    /**
     * Erkennt eine Sprach-Map auch ohne (mehr vorhandene) Felddefinition --
     * z.B. verwaiste Schluessel nach dem Loeschen/Umbenennen eines Feldes.
     *
     * @param array<int, string> $clangNames
     */
    private static function looksLikeClangMap(array $value, array $clangNames): bool
    {
        if (empty($value)) {
            return false;
        }
        foreach (array_keys($value) as $clangId) {
            if (!isset($clangNames[(int) $clangId])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Sprachname wird immer vorangestellt (nicht nur bei >1 aktiver Sprache):
     * ein Ein-Sprachen-Stand heute schliesst weitere Sprachen morgen nicht aus,
     * und "Deutsch: ..." ist als einzige Zeile ebenso eindeutig wie ohne Praefix.
     *
     * @param array<int, string> $clangNames
     */
    private static function formatTranslatable(array $value, array $clangNames): string
    {
        $parts = [];
        foreach ($value as $clangId => $text) {
            if (!is_scalar($text) || '' === trim((string) $text)) {
                continue;
            }
            $escaped = nl2br(\rex_escape((string) $text));
            $parts[] = isset($clangNames[(int) $clangId])
                ? '<strong>' . \rex_escape($clangNames[(int) $clangId]) . ':</strong> ' . $escaped
                : $escaped;
        }
        return implode('<br>', $parts);
    }
}
