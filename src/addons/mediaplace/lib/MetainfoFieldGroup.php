<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Manages metainfo field groups and ensures med_json_data field exists.
 * Structured metadata is stored as JSON in med_json_data (TEXT column).
 * No need to manage individual med_* columns – everything is JSON-based.
 */
class MetainfoFieldGroup
{
    /** Ensure med_json_data field exists in rex_media table */
    public static function ensureJsonField(): void
    {
        // Check if med_json_data column exists in media table
        $columns = \rex_sql::showColumns(\rex::getTable('media'));
        $hasJsonField = false;
        foreach ($columns as $col) {
            if ('med_json_data' === $col['name']) {
                $hasJsonField = true;
                break;
            }
        }

        if (!$hasJsonField) {
            // Add med_json_data column as TEXT (JSON data)
            $table = \rex_sql_table::get(\rex::getTable('media'));
            $table->ensureColumn(new \rex_sql_column('med_json_data', 'longtext', true))
                ->ensure();
        }

        // Ensure config table for field definitions
        \rex_sql_table::get(\rex::getTable('mediaplace_metainfo_fields'))
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new \rex_sql_column('key', 'varchar(100)', true))
            ->ensureColumn(new \rex_sql_column('label', 'varchar(255)'))
            ->ensureColumn(new \rex_sql_column('widget_type', 'varchar(50)', true))
            ->ensureColumn(new \rex_sql_column('options', 'longtext'))
            ->ensureColumn(new \rex_sql_column('priority', 'int(10) unsigned', false, '0'))
            ->ensureColumn(new \rex_sql_column('translatable', 'tinyint(1)', false, '0'))
            ->ensureColumn(new \rex_sql_column('image_only', 'tinyint(1)', false, '0'))
            ->ensureColumn(new \rex_sql_column('create_user', 'varchar(255)'))
            ->ensureColumn(new \rex_sql_column('create_date', 'datetime'))
            ->ensureColumn(new \rex_sql_column('update_user', 'varchar(255)'))
            ->ensureColumn(new \rex_sql_column('update_date', 'datetime'))
            ->ensureGlobalColumns()
            ->ensure();
    }

    /**
     * Get all field definitions ordered by priority.
     * @return MetainfoField[]
     */
    public static function getFields(): array
    {
        $sql = \rex_sql::factory();
        $rows = $sql->getArray(
            'SELECT * FROM ' . \rex::getTable('mediaplace_metainfo_fields')
            . ' ORDER BY priority, id',
        );

        $fields = [];
        foreach ($rows as $row) {
            if ('tags' === (string) ($row['widget_type'] ?? '')) {
                continue;
            }
            $fields[] = new MetainfoField(
                (int) $row['id'],
                (string) $row['key'],
                (string) $row['label'],
                (string) $row['widget_type'],
                json_decode($row['options'] ?? '{}', true) ?: [],
                (bool) $row['translatable'],
                (bool) $row['image_only'],
            );
        }
        return $fields;
    }

    /**
     * Get field by key.
     */
    public static function getFieldByKey(string $key): ?MetainfoField
    {
        $sql = \rex_sql::factory();
        $row = $sql->getArray(
            'SELECT * FROM ' . \rex::getTable('mediaplace_metainfo_fields')
            . ' WHERE `key` = ?',
            [$key],
        );

        if (empty($row)) {
            return null;
        }

        $r = $row[0];
        return new MetainfoField(
            (int) $r['id'],
            (string) $r['key'],
            (string) $r['label'],
            (string) $r['widget_type'],
            json_decode($r['options'] ?? '{}', true) ?: [],
            (bool) $r['translatable'],
            (bool) $r['image_only'],
        );
    }

    /**
     * Save or update a field definition.
     */
    public static function saveField(
        string $key,
        string $label,
        string $widgetType,
        array $options = [],
        bool $translatable = false,
        bool $imageOnly = false,
        ?int $priority = null,
    ): int {
        self::ensureJsonField();

        if ('alt' === $widgetType) {
            // ALT is a singleton field with a stable key.
            $key = 'alt';
            $imageOnly = true;
        }

        $sql = \rex_sql::factory();

        if ('alt' === $widgetType) {
            // Remove accidental legacy ALT duplicates before save.
            $sql->setQuery(
                'DELETE FROM ' . \rex::getTable('mediaplace_metainfo_fields') . ' WHERE widget_type = :widget_type AND `key` <> :alt_key',
                ['widget_type' => 'alt', 'alt_key' => 'alt'],
            );
        }

        // Check if field exists
        $existing = $sql->getArray('SELECT id FROM ' . \rex::getTable('mediaplace_metainfo_fields') . ' WHERE `key` = ?', [$key]);

        if (!empty($existing)) {
            // Update
            $sql->setTable(\rex::getTable('mediaplace_metainfo_fields'));
            $sql->setWhere('`key` = :field_key', ['field_key' => $key]);
            $sql->setValue('label', $label);
            $sql->setValue('widget_type', $widgetType);
            $sql->setValue('options', json_encode($options));
            $sql->setValue('translatable', $translatable ? 1 : 0);
            $sql->setValue('image_only', $imageOnly ? 1 : 0);
            $sql->setValue('update_user', \rex::getUser()?->getLogin() ?? 'system');
            $sql->setValue('update_date', date('Y-m-d H:i:s'));
            if ($priority !== null) {
                $sql->setValue('priority', $priority);
            }
            $sql->update();
            return (int) $existing[0]['id'];
        }

        // Insert
        $sql->setTable(\rex::getTable('mediaplace_metainfo_fields'));
        $sql->setValue('key', $key);
        $sql->setValue('label', $label);
        $sql->setValue('widget_type', $widgetType);
        $sql->setValue('options', json_encode($options));
        $sql->setValue('translatable', $translatable ? 1 : 0);
        $sql->setValue('image_only', $imageOnly ? 1 : 0);
        $sql->setValue('priority', $priority ?? 100);
        $sql->setValue('create_user', \rex::getUser()?->getLogin() ?? 'system');
        $sql->setValue('create_date', date('Y-m-d H:i:s'));
        $sql->insert();
        return (int) $sql->getLastId();
    }

    /**
     * Delete a field definition by key.
     */
    public static function deleteField(string $key): bool
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . \rex::getTable('mediaplace_metainfo_fields') . ' WHERE `key` = ?',
            [$key],
        );
        return true;
    }
}
