<?php

namespace FriendsOfRedaxo\Mediaplace\Api;

use rex_api_function;
use rex_api_result;

/**
 * MediaPlace – Metainfo Schema Endpoint
 *
 * Liefert das Feld-Schema für einen Metainfo-Prefix (Standard: med_) als JSON.
 * Erlaubte Aufrufer: eingeloggte Backend-User.
 *
 * URL: index.php?rex-api-call=mediaplace_schema&prefix=med_
 */
class Schema extends rex_api_function
{
    /** @var bool Frontend-Aufruf erlauben (nötig, weil die API-Route im Frontend-Context läuft) */

    public function execute(): rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        // Nur eingeloggte Backend-User
        if (!\rex::getUser()) {
            \rex_response::setStatus(\rex_response::HTTP_UNAUTHORIZED);
            \rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        if (!\FriendsOfRedaxo\Mediaplace\MediaPermission::hasMediaAccess()) {
            \rex_response::setStatus(\rex_response::HTTP_FORBIDDEN);
            \rex_response::sendJson(['error' => 'Permission denied']);
            exit;
        }

        $allowedPrefixes = ['med_', 'art_', 'cat_', 'clang_'];
        $prefix = rex_request('prefix', 'string', 'med_');
        if (!in_array($prefix, $allowedPrefixes, true)) {
            $prefix = 'med_';
        }

        try {
            $sql = \rex_sql::factory();

            // Typen-Map laden
            $typeRows = $sql->getArray(
                'SELECT id, label FROM ' . \rex::getTable('metainfo_type') . ' ORDER BY id',
            );
            $typeMap = [];
            foreach ($typeRows as $t) {
                $typeMap[(int) $t['id']] = (string) $t['label'];
            }

            // Felder für Prefix laden
            $fieldRows = $sql->getArray(
                'SELECT id, name, title, type_id, priority, attributes, `default`, params, restrictions
                 FROM ' . \rex::getTable('metainfo_field') . '
                 WHERE name LIKE :prefix
                 ORDER BY priority, name',
                [':prefix' => $prefix . '%'],
            );

            $fields = [];
            foreach ($fieldRows as $row) {
                $typeId = (int) $row['type_id'];
                $typeLabel = $typeMap[$typeId] ?? 'text';
                $attributes = (string) ($row['attributes'] ?? '');
                $params = (string) ($row['params'] ?? '');
                $editorKind = self::resolveEditorKind($typeId, $attributes);

                // Legendenfelder überspringen (nur optischer Trenner, kein Wert)
                if ($editorKind === 'legend') {
                    continue;
                }

                // Titel kann ein i18n-Schlüssel sein (z.B. "translate:pool_file_copyright")
                $rawTitle = (string) $row['title'];
                $resolvedLabel = ($rawTitle !== '')
                    ? \rex_i18n::translate($rawTitle, false)
                    : (string) $row['name'];
                // Fallback: wenn Übersetzung nicht gefunden (translate() gibt Key zurück), rawTitle nutzen
                if ($resolvedLabel === $rawTitle && str_starts_with($rawTitle, 'translate:')) {
                    $resolvedLabel = substr($rawTitle, 10); // Strip "translate:" prefix
                }

                $fields[] = [
                    'name'       => (string) $row['name'],
                    'label'      => $resolvedLabel,
                    'type_id'    => $typeId,
                    'type_label' => $typeLabel,
                    'editor_kind' => $editorKind,
                    'multiple'   => self::isMultiple($typeId, $attributes),
                    'options'    => self::parseOptions($params, $typeId),
                    'default'    => (string) ($row['default'] ?? ''),
                    'attributes' => $attributes,
                    'restrictions' => (string) ($row['restrictions'] ?? ''),
                    'readonly'   => self::isReadonlyKind($editorKind),
                ];
            }

            \rex_response::sendJson(['data' => $fields, 'prefix' => $prefix]);
            exit;
        } catch (\rex_sql_exception $e) {
            \rex_response::setStatus(\rex_response::HTTP_INTERNAL_ERROR);
            \rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Leitet aus der type_id den Editor-Typ ab.
     * Unbekannte IDs (Custom-Typen von externen Addons) fallen sicher auf 'custom' zurück.
     */
    private static function resolveEditorKind(int $typeId, string $attributes): string
    {
        switch ($typeId) {
            case \rex_metainfo_default_type::TEXT:                return 'text';
            case \rex_metainfo_default_type::TEXTAREA:            return 'textarea';
            case \rex_metainfo_default_type::SELECT:              return 'select';
            case \rex_metainfo_default_type::RADIO:               return 'radio';
            case \rex_metainfo_default_type::CHECKBOX:            return 'checkbox';
            case \rex_metainfo_default_type::REX_MEDIA_WIDGET:    return 'media_widget';
            case \rex_metainfo_default_type::REX_MEDIALIST_WIDGET: return 'medialist';
            case \rex_metainfo_default_type::REX_LINK_WIDGET:     return 'link_widget';
            case \rex_metainfo_default_type::REX_LINKLIST_WIDGET: return 'linklist';
            case \rex_metainfo_default_type::DATE:                return 'date';
            case \rex_metainfo_default_type::DATETIME:            return 'datetime';
            case \rex_metainfo_default_type::TIME:                return 'time';
            case \rex_metainfo_default_type::LEGEND:              return 'legend';
            default:
                // Externe Addon-Typen: sicherer Fallback
                return 'custom';
        }
    }

    /** Ob ein Feld mehrere Werte speichert (pipe-List in DB). */
    private static function isMultiple(int $typeId, string $attributes): bool
    {
        if (in_array($typeId, [
            \rex_metainfo_default_type::CHECKBOX,
            \rex_metainfo_default_type::REX_MEDIALIST_WIDGET,
            \rex_metainfo_default_type::REX_LINKLIST_WIDGET,
        ], true)) {
            return true;
        }
        if (\rex_metainfo_default_type::SELECT === $typeId && str_contains($attributes, 'multiple')) {
            return true;
        }
        return false;
    }

    /**
     * Ob der Editor nur lesend angezeigt werden soll
     * (Custom-Felder von externen Addons können nicht richtig gerendert werden).
     */
    private static function isReadonlyKind(string $editorKind): bool
    {
        return in_array($editorKind, ['media_widget', 'link_widget', 'linklist', 'medialist', 'custom'], true);
    }

    /**
     * Parst die params-Spalte für Select/Radio/Checkbox-Felder.
     * REDAXO-Format: mit "|" getrennte Optionen, key:value-Paare mit ":" getrennt.
     * Labels können "translate:key" enthalten.
     * SQL-Queries (beginnend mit SELECT) werden als Hinweis markiert.
     *
     * @return list<array{label: string, value: string}>
     */
    private static function parseOptions(string $params, int $typeId): array
    {
        if ('' === $params) {
            return [];
        }

        if (!in_array($typeId, [
            \rex_metainfo_default_type::SELECT,
            \rex_metainfo_default_type::RADIO,
            \rex_metainfo_default_type::CHECKBOX,
        ], true)) {
            return [];
        }

        // SQL-Query-Erkennung (Sicherheit: nicht auswerten)
        if (preg_match('/^\s*SELECT\s/i', $params)) {
            return [['label' => '(SQL-Query – im REDAXO-Backend bearbeiten)', 'value' => '__sql__']];
        }

        // REDAXO-Format: Optionen durch | getrennt
        // Einzelne Option: "value" oder "key:label" (label kann translate:key sein)
        $options = [];
        foreach (explode('|', $params) as $group) {
            $group = trim($group);
            if ('' === $group) {
                continue;
            }
            // key:value-Paar – aber NICHT wenn es mit "translate:" beginnt
            if (str_contains($group, ':') && !str_starts_with($group, 'translate:')) {
                [$key, $labelRaw] = explode(':', $group, 2);
                $label = \rex_i18n::translate(trim($labelRaw), false);
                $options[] = ['label' => $label, 'value' => trim($key)];
            } else {
                // Wert ist Label (kann translate:key sein)
                $label = \rex_i18n::translate($group, false);
                $options[] = ['label' => $label, 'value' => $group];
            }
        }
        return $options;
    }
}
