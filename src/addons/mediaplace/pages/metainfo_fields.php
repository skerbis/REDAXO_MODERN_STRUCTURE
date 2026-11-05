<?php

/**
 * Manage metainfo field definitions for mediaplace.
 */

$func = rex_request('func', 'string', '');
$moveId = rex_request('move_id', 'int', 0);
$moveDir = rex_request('move_dir', 'string', '');
$fieldId = rex_request('field_id', 'int', 0);
$fieldToEdit = null;

$listUrl = rex_url::currentBackendPage([], false);
$addUrl = rex_url::currentBackendPage(['func' => 'add'], false);

echo rex_view::info(rex_i18n::msg('mediaplace_fields_scope_hint'));

// Move field up/down
if (in_array($moveDir, ['up', 'down'], true) && $moveId > 0) {
    $allFields = \FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::getFields();
    $ids = array_map(fn($f) => $f->getId(), $allFields);
    $pos = array_search($moveId, $ids, true);
    if (false !== $pos) {
        $swapPos = 'up' === $moveDir ? $pos - 1 : $pos + 1;
        if (isset($ids[$swapPos])) {
            $table = rex::getTable('mediaplace_metainfo_fields');

            rex_sql::factory()->setQuery(
                'UPDATE ' . $table . ' SET priority = :priority WHERE id = :id',
                [':priority' => $swapPos, ':id' => $ids[$pos]],
            );

            rex_sql::factory()->setQuery(
                'UPDATE ' . $table . ' SET priority = :priority WHERE id = :id',
                [':priority' => $pos, ':id' => $ids[$swapPos]],
            );
        }
    }
}

// Delete field
if ('delete' === $func && $fieldId > 0) {
    $field = \FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::getFields();
    $fieldToDelete = null;
    foreach ($field as $f) {
        if ($f->getId() === $fieldId) {
            $fieldToDelete = $f;
            break;
        }
    }

    if ($fieldToDelete) {
        \FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::deleteField($fieldToDelete->getKey());
        echo rex_view::success(\rex_i18n::msg('mediaplace_field_deleted'));
    }
    $func = '';
}

// Edit/Create form
if ('edit' === $func && $fieldId > 0) {
    $fields = \FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::getFields();
    $fieldToEdit = null;
    foreach ($fields as $f) {
        if ($f->getId() === $fieldId) {
            $fieldToEdit = $f;
            break;
        }
    }

    if (!$fieldToEdit) {
        echo rex_view::error(\rex_i18n::msg('mediaplace_field_not_found'));
        $func = '';
    }
} elseif ('add' === $func) {
    $fieldToEdit = null;
}

if ('edit' === $func || 'add' === $func) {
    $key = rex_request('key', 'string', $fieldToEdit?->getKey() ?? '');
    $label = rex_request('label', 'string', $fieldToEdit?->getLabel() ?? '');
    $widgetType = rex_request('widget_type', 'string', $fieldToEdit?->getWidgetType() ?? 'text');
    $translatable = (bool) rex_request('translatable', 'int', $fieldToEdit?->isTranslatable() ? 1 : 0);
    $imageOnly = (bool) rex_request('image_only', 'int', $fieldToEdit?->isImageOnly() ? 1 : 0);

    $existingOptions = $fieldToEdit?->getOptions() ?? [];
    $choicesSource = rex_request('choices_source', 'string', (string) ($existingOptions['choices_source'] ?? ''));
    $selectMultiple = (bool) rex_request('select_multiple', 'int', !empty($existingOptions['multiple']) ? 1 : 0);

    // Eingebaute + von anderen Addons per Erweiterungspunkt registrierte Typen,
    // siehe MetainfoWidget::getRegisteredTypes().
    $allowedWidgets = array_map(
        static fn(array $type) => $type['label'],
        \FriendsOfRedaxo\Mediaplace\MetainfoWidget::getRegisteredTypes(),
    );

    ob_start();
    ?>
    <form method="post">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="key" class="control-label"><?php echo rex_i18n::msg('mediaplace_field_key'); ?></label>
                    <input type="text" id="key" name="key" value="<?php echo rex_escape($key); ?>" class="form-control" <?php if ($fieldToEdit) echo 'readonly'; ?> required>
                    <p class="help-block"><?php echo rex_i18n::msg('mediaplace_field_key_hint'); ?></p>
                </div>

                <div class="form-group">
                    <label for="label" class="control-label"><?php echo rex_i18n::msg('mediaplace_label'); ?></label>
                    <input type="text" id="label" name="label" value="<?php echo rex_escape($label); ?>" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="widget_type" class="control-label"><?php echo rex_i18n::msg('mediaplace_widget_type'); ?></label>
                    <select id="widget_type" name="widget_type" class="form-control selectpicker" required>
                        <option value=""><?php echo rex_i18n::msg('mediaplace_choose_ellipsis'); ?></option>
                        <?php foreach ($allowedWidgets as $type => $name): ?>
                            <option value="<?php echo rex_escape($type); ?>" <?php if ($widgetType === $type) echo 'selected'; ?>>
                                <?php echo rex_escape($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-md-6">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="translatable" name="translatable" value="1" <?php if ($translatable) echo 'checked'; ?>>
                        <?php echo rex_i18n::msg('mediaplace_translatable'); ?> <small class="text-muted">(<?php echo rex_i18n::msg('mediaplace_translatable_hint'); ?>)</small>
                    </label>
                    <p class="help-block" id="translatable-hint" style="display:none"></p>
                </div>

                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="image_only" name="image_only" value="1" <?php if ($imageOnly) echo 'checked'; ?>>
                        <?php echo rex_i18n::msg('mediaplace_images_only'); ?> <small class="text-muted">(<?php echo rex_i18n::msg('mediaplace_images_only_hint'); ?>)</small>
                    </label>
                </div>

                <div class="form-group" id="select-choices-group" style="display:none">
                    <label for="choices_source" class="control-label"><?php echo rex_i18n::msg('mediaplace_field_choices'); ?></label>
                    <textarea id="choices_source" name="choices_source" class="form-control" rows="5"><?php echo rex_escape($choicesSource); ?></textarea>
                    <p class="help-block"><?php echo rex_i18n::msg('mediaplace_field_choices_hint'); ?></p>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="select_multiple" name="select_multiple" value="1" <?php if ($selectMultiple) echo 'checked'; ?>>
                            <?php echo rex_i18n::msg('mediaplace_select_multiple'); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="rex-form-panel-footer">
            <button type="submit" name="save" value="1" class="btn btn-save"><i class="fa-solid fa-floppy-disk"></i> <?php echo rex_i18n::msg('mediaplace_save'); ?></button>
            <a href="<?php echo $listUrl; ?>" class="btn btn-abort"><?php echo rex_i18n::msg('mediaplace_cancel'); ?></a>
        </div>
    </form>
    <script>
    (function () {
        // Nicht jeder Widget-Typ unterstuetzt "Mehrsprachig" so, wie es der
        // Haken suggeriert: media_link rendert immer nur ein einzelnes
        // Eingabefeld (der Haken haette gar keine Wirkung, aeltere Bugs
        // dadurch entstanden -- siehe collectJsonValuesFromDetail() in
        // mediaplace.js), alt rendert immer pro Sprache (der Haken ist dort
        // immer wirkungslos "an"). UI reagiert entsprechend, serverseitig
        // wird das ohnehin unabhaengig davon erzwungen (siehe Save-Handler).
        var FORCED = { media_link: false, alt: true, checkbox: false, select: false };
        var HINT = {
            media_link: <?php echo json_encode(rex_i18n::msg('mediaplace_field_hint_media_link')); ?>,
            alt: <?php echo json_encode(rex_i18n::msg('mediaplace_field_hint_alt')); ?>,
            checkbox: <?php echo json_encode(rex_i18n::msg('mediaplace_field_hint_checkbox')); ?>,
            select: <?php echo json_encode(rex_i18n::msg('mediaplace_field_hint_select')); ?>
        };

        var widgetSelect = document.getElementById('widget_type');
        var translatableCheckbox = document.getElementById('translatable');
        var hintEl = document.getElementById('translatable-hint');
        var choicesGroup = document.getElementById('select-choices-group');
        if (!widgetSelect || !translatableCheckbox || !hintEl) return;

        function update() {
            var type = widgetSelect.value;
            var forced = Object.prototype.hasOwnProperty.call(FORCED, type) ? FORCED[type] : null;

            if (choicesGroup) choicesGroup.style.display = ('select' === type) ? '' : 'none';

            if (null === forced) {
                translatableCheckbox.disabled = false;
                hintEl.style.display = 'none';
                return;
            }

            translatableCheckbox.checked = forced;
            translatableCheckbox.disabled = true;
            hintEl.textContent = HINT[type];
            hintEl.style.display = '';
        }

        widgetSelect.addEventListener('change', update);
        // Bootstrap-select (falls aktiv) feuert eigene Events statt eines
        // nativen 'change' auf dem <select> selbst zuverlaessig durchzureichen.
        if (window.jQuery) {
            window.jQuery(widgetSelect).on('changed.bs.select', update);
        }
        update();
    })();
    </script>
    <?php
    $body = ob_get_clean();

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', $fieldToEdit ? rex_i18n::msg('mediaplace_field_edit_title') : rex_i18n::msg('mediaplace_field_new_title'));
    $fragment->setVar('body', $body, false);
    echo $fragment->parse('core/page/section.php');
}

// Handle save
if (1 === rex_post('save', 'int', 0)) {
    $key = rex_post('key', 'string', '');
    $label = rex_post('label', 'string', '');
    $widgetType = rex_post('widget_type', 'string', '');
    $translatable = (bool) rex_post('translatable', 'int');
    $imageOnly = (bool) rex_post('image_only', 'int');

    if ('alt' === $widgetType) {
        $key = 'alt';
        $imageOnly = true;
        // ALT-Text-Feld rendert immer pro Sprache (detail_field_body_alt.php),
        // unabhaengig vom translatable-Haken -- serverseitig konsistent halten.
        $translatable = true;
    }

    if ('media_link' === $widgetType) {
        // media_link rendert immer nur ein einzelnes Eingabefeld ohne
        // Sprachbezug (detail_field_body_media_link.php) -- ein per Formular
        // gesetztes translatable=1 wuerde in collectJsonValuesFromDetail()
        // (mediaplace.js) nach nicht existierenden [data-clang]-Unterfeldern
        // suchen und den Wert nie speichern.
        $translatable = false;
    }

    $options = [];

    if ('checkbox' === $widgetType) {
        // Ein Ja/Nein-Wert ist nicht sprachabhaengig.
        $translatable = false;
    }

    if ('select' === $widgetType) {
        // Gespeicherte Werte sind sprachunabhaengige Schluessel (nur die
        // Auswahlmoeglichkeiten selbst koennten uebersetzt werden, das ist
        // hier bewusst nicht vorgesehen -- choices_source ist global).
        $translatable = false;
        $options = [
            'multiple' => (bool) rex_post('select_multiple', 'int'),
            'choices_source' => rex_post('choices_source', 'string', ''),
        ];
    }

    if (!$key || !$label || !$widgetType) {
        echo rex_view::error(\rex_i18n::msg('mediaplace_invalid_input'));
    } else {
        \FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::saveField(
            $key,
            $label,
            $widgetType,
            $options,
            $translatable,
            $imageOnly,
        );
        echo rex_view::success(\rex_i18n::msg('mediaplace_field_saved'));
        $func = '';
    }
}

// List all fields
if ('' === $func) {
    $fields = \FriendsOfRedaxo\Mediaplace\MetainfoFieldGroup::getFields();

    ob_start();
    if (empty($fields)): ?>
        <div class="alert alert-info">
            <?php echo \rex_i18n::msg('mediaplace_no_fields'); ?>
        </div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th style="width: 70px;"></th>
                    <th><?php echo rex_i18n::msg('mediaplace_label'); ?></th>
                    <th><?php echo rex_i18n::msg('mediaplace_field_key'); ?></th>
                    <th><?php echo rex_i18n::msg('mediaplace_field_type'); ?></th>
                    <th><?php echo rex_i18n::msg('mediaplace_options'); ?></th>
                    <th class="rex-table-action" colspan="2"><?php echo rex_i18n::msg('mediaplace_functions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $i => $field): ?>
                    <tr>
                        <td style="white-space:nowrap;">
                            <?php if ($i > 0): ?><a href="<?php echo rex_url::currentBackendPage(['move_id' => $field->getId(), 'move_dir' => 'up'], false); ?>" class="btn btn-xs btn-default" title="<?php echo rex_i18n::msg('mediaplace_move_up'); ?>"><i class="fa-solid fa-arrow-up"></i></a><?php endif; ?>
                            <?php if ($i < count($fields) - 1): ?><a href="<?php echo rex_url::currentBackendPage(['move_id' => $field->getId(), 'move_dir' => 'down'], false); ?>" class="btn btn-xs btn-default" title="<?php echo rex_i18n::msg('mediaplace_move_down'); ?>"><i class="fa-solid fa-arrow-down"></i></a><?php endif; ?>
                        </td>
                        <td><?php echo rex_escape($field->getLabel()); ?></td>
                        <td><code><?php echo rex_escape($field->getKey()); ?></code></td>
                        <td>
                            <span class="label label-info"><?php echo rex_escape($field->getWidgetType()); ?></span>
                        </td>
                        <td>
                            <?php $badges = [];
                            if ($field->isTranslatable()) $badges[] = '<span class="label label-default">' . rex_escape(rex_i18n::msg('mediaplace_multilingual_badge')) . '</span>';
                            if ($field->isImageOnly()) $badges[] = '<span class="label label-warning">' . rex_escape(rex_i18n::msg('mediaplace_images_only_badge')) . '</span>';
                            echo implode(' ', $badges);
                            ?>
                        </td>
                        <td class="rex-table-action"><a href="<?php echo rex_url::currentBackendPage(['func' => 'edit', 'field_id' => $field->getId()], false); ?>" class="rex-edit"><i class="rex-icon rex-icon-edit"></i> <?php echo rex_i18n::msg('mediaplace_edit'); ?></a></td>
                        <td class="rex-table-action"><a href="<?php echo rex_url::currentBackendPage(['func' => 'delete', 'field_id' => $field->getId()], false); ?>" class="rex-delete" data-confirm="<?php echo rex_escape(rex_i18n::msg('mediaplace_confirm_delete_generic')); ?>"><i class="rex-icon rex-icon-delete"></i> <?php echo rex_i18n::msg('mediaplace_delete'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif;
    $content = ob_get_clean();

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('mediaplace_metainfo_title'));
    $fragment->setVar('options', '<div class="btn-group btn-group-xs"><a href="' . $addUrl . '" class="btn btn-default"><i class="fa-solid fa-plus"></i> ' . rex_escape(rex_i18n::msg('mediaplace_field_new_title')) . '</a></div>', false);
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}
