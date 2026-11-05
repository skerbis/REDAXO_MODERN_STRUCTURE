<?php

/**
 * YForm-Feldtyp "mediaplace": bindet das MediaPlace-Widget (<input class="mp-widget">)
 * als YForm-Werttyp ein, analog zu yform/lib/Field/value/be_media.php. Speichert
 * Dateiname(n) kommasepariert im DB-Feld, exakt das Format, das das Widget selbst
 * (mediaplace_widget.js, _getFiles()/_setFiles()) ohnehin liest/schreibt.
 */
class rex_yform_value_mediaplace extends rex_yform_value_abstract
{
    public function enterObject()
    {
        if (!is_string($this->getValue())) {
            $this->setValue('');
        }

        if ('' != $this->getValue()) {
            $medias = [];
            foreach (explode(',', $this->getValue()) as $media) {
                $media = trim($media);
                if ('' !== $media && rex_media::get($media)) {
                    $medias[] = $media;
                }
            }
            $this->setValue(implode(',', $medias));
        }

        if ($this->needsOutput() && $this->isViewable()) {
            if (!$this->isEditable()) {
                $this->params['form_output'][$this->getId()] = $this->parse('value.view.tpl.php', ['value' => $this->getValue()]);
            } else {
                $this->params['form_output'][$this->getId()] = $this->parse('value.mediaplace.tpl.php');
            }
        }

        $this->params['value_pool']['email'][$this->getElement(1)] = $this->getValue();
        if ($this->saveInDB()) {
            $this->params['value_pool']['sql'][$this->getElement(1)] = $this->getValue();
        }
    }

    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'mediaplace',
            'values' => [
                'name' => ['type' => 'name', 'label' => rex_i18n::msg('yform_values_defaults_name')],
                'label' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_defaults_label')],
                'multiple' => ['type' => 'checkbox', 'label' => rex_i18n::msg('mediaplace_yform_multiple')],
                'upload' => ['type' => 'checkbox', 'label' => rex_i18n::msg('mediaplace_yform_upload')],
                'types' => ['type' => 'text', 'label' => rex_i18n::msg('mediaplace_yform_types'), 'notice' => rex_i18n::msg('mediaplace_yform_types_notice')],
                'max' => ['type' => 'text', 'label' => rex_i18n::msg('mediaplace_yform_max')],
                'view' => ['type' => 'choice', 'label' => rex_i18n::msg('mediaplace_yform_view'), 'choices' => ['' => rex_i18n::msg('mediaplace_yform_view_default'), 'grid' => rex_i18n::msg('mediaplace_yform_view_grid'), 'list' => rex_i18n::msg('mediaplace_yform_view_list')]],
                'notice' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_defaults_notice')],
            ],
            'description' => rex_i18n::msg('mediaplace_yform_description'),
            'formbuilder' => false,
            'db_type' => ['text'],
        ];
    }

    public static function getListValue($params)
    {
        $files = array_filter(array_map('trim', explode(',', (string) $params['subject'])));
        if ([] === $files) {
            return '';
        }

        $return = [];
        foreach ($files as $file) {
            $label = $file;
            if (mb_strlen($file) > 16) {
                $label = mb_substr($file, 0, 6) . ' ... ' . mb_substr($file, -6);
            }
            $return[] = '<span style="white-space:nowrap;" title="' . rex_escape($file) . '">' . rex_escape($label) . '</span>';
        }

        if (4 < count($return)) {
            $return = array_merge(array_slice($return, 0, 2), ['...'], array_slice($return, -2, 2));
        }

        return implode('<br />', $return);
    }

    public static function getSearchField($params)
    {
        rex_yform_value_text::getSearchField($params);
    }

    public static function getSearchFilter($params)
    {
        return rex_yform_value_text::getSearchFilter($params);
    }

    /**
     * Registriert in boot.php auf MEDIA_IS_IN_USE (nur wenn yform verfuegbar
     * ist). Gleiches Muster wie rex_yform_value_be_media::isMediaInUse() --
     * durchsucht alle YForm-Tabellen mit einem "mediaplace"-Feld nach dem
     * Dateinamen, per FIND_IN_SET bei Mehrfachauswahl-Feldern.
     */
    public static function isMediaInUse(rex_extension_point $ep)
    {
        $params = $ep->getParams();
        $warning = $ep->getSubject();

        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT * FROM `' . \rex_yform_manager_field::table() . '` LIMIT 0');

        $columns = $sql->getFieldnames();
        $select = in_array('multiple', $columns) ? ', `multiple`' : '';

        $fields = $sql->getArray('SELECT `table_name`, `name`' . $select . ' FROM `' . \rex_yform_manager_field::table() . '` WHERE `type_id`="value" AND `type_name` IN("mediaplace")');
        $fields = \rex_extension::registerPoint(new \rex_extension_point('YFORM_MEDIA_IS_IN_USE', $fields));

        if (!count($fields)) {
            return $warning;
        }

        $tables = [];
        $escapedFilename = $sql->escape($params['filename']);
        foreach ($fields as $field) {
            $tableName = $field['table_name'];
            $condition = $sql->escapeIdentifier((string) $field['name']) . ' = ' . $escapedFilename;

            if (isset($field['multiple']) && 1 == $field['multiple']) {
                $condition = 'FIND_IN_SET(' . $escapedFilename . ', ' . $sql->escapeIdentifier((string) $field['name']) . ')';
            }
            $tables[$tableName][] = $condition;
        }

        $messages = '';
        foreach ($tables as $tableName => $conditions) {
            $items = $sql->getArray('SELECT `id` FROM ' . $tableName . ' WHERE ' . implode(' OR ', $conditions));
            if (count($items)) {
                foreach ($items as $item) {
                    $sqlData = \rex_sql::factory();
                    $sqlData->setQuery('SELECT `name` FROM `' . \rex_yform_manager_table::table() . '` WHERE `table_name` = "' . $tableName . '"');
                    $editUrl = rex_yform_manager::url($tableName, $item['id']);
                    $messages .= '<li><a href="javascript:openPage(\'' . $editUrl . '\')">' . $sqlData->getValue('name') . ' [id=' . $item['id'] . ']</a></li>';
                }
            }
        }

        if ('' != $messages) {
            $warning[] = 'Tabelle<br /><ul>' . $messages . '</ul>';
        }

        return $warning;
    }
}
