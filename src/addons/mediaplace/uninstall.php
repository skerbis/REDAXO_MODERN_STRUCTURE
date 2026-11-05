<?php

$addon = rex_addon::get('mediaplace');
$addon->removeConfig('version');

// Remove med_json_data registration from core metainfo addon
if (rex_addon::get('metainfo')->isInstalled()) {
    rex_sql::factory()->setQuery(
        'DELETE FROM ' . rex::getTable('metainfo_field') . ' WHERE name = :name AND createuser = :cu',
        [':name' => 'med_json_data', ':cu' => 'mediaplace'],
    );
}
