<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Base class for all metainfo widgets.
 * Widgets render editable UI elements for different field types.
 */
abstract class MetainfoWidget implements MetainfoWidgetInterface
{
    protected MetainfoField $field;

    public function __construct(MetainfoField $field)
    {
        $this->field = $field;
    }

    /**
     * Eingebaute + per Erweiterungspunkt MEDIAPLACE_WIDGET_TYPES registrierte
     * Widget-Typen. Einzige Quelle der Wahrheit fuer das Widget-Typ-Dropdown
     * (pages/metainfo_fields.php), den Save-Pfad (createByType()) und das
     * Detail-Panel-Rendering (fragments/mediaplace/detail_field.php).
     *
     * Andere Addons registrieren einen eigenen Feldtyp z.B. so:
     *
     *   rex_extension::register('MEDIAPLACE_WIDGET_TYPES', function (rex_extension_point $ep) {
     *       $types = $ep->getSubject();
     *       $types['my_widget'] = [
     *           'label' => 'Mein Feldtyp',
     *           'class' => MyAddon\MyWidget::class, // implements MetainfoWidgetInterface
     *           'fragment' => 'my_addon/detail_field_body_my_widget.php',
     *       ];
     *       return $types;
     *   });
     *
     * Das Fragment bekommt dieselben Variablen wie unsere eingebauten
     * detail_field_body_*.php-Dateien ($field, $value, $info, $clangs), siehe
     * fragments/mediaplace/detail_field.php. Fuer Feldwerte, die nicht ins
     * generische JS-Sammel-Muster (ein data-json-field-Element, skalar oder pro
     * Sprache) passen, siehe MP3.registerFieldCollector() in mediaplace.js.
     *
     * @return array<string, array{label:string, class:class-string, fragment:string}>
     */
    public static function getRegisteredTypes(): array
    {
        $builtin = [
            'text' => [
                'label' => 'Text (einzeilig)',
                'class' => Widgets\TextWidget::class,
                'fragment' => 'mediaplace/detail_field_body_text.php',
            ],
            'textarea' => [
                'label' => 'Text (mehrzeilig)',
                'class' => Widgets\TextareaWidget::class,
                'fragment' => 'mediaplace/detail_field_body_textarea.php',
            ],
            'checkbox' => [
                'label' => 'Checkbox (Ja/Nein)',
                'class' => Widgets\CheckboxWidget::class,
                'fragment' => 'mediaplace/detail_field_body_checkbox.php',
            ],
            'select' => [
                'label' => 'Auswahlliste (Select)',
                'class' => Widgets\SelectWidget::class,
                'fragment' => 'mediaplace/detail_field_body_select.php',
            ],
            'alt' => [
                'label' => 'ALT-Text (mit dekorativ-Option)',
                'class' => Widgets\AltFieldWidget::class,
                'fragment' => 'mediaplace/detail_field_body_alt.php',
            ],
            'media_link' => [
                'label' => 'Link zu Medium',
                'class' => Widgets\MediaLinkWidget::class,
                'fragment' => 'mediaplace/detail_field_body_media_link.php',
            ],
        ];

        return \rex_extension::registerPoint(new \rex_extension_point('MEDIAPLACE_WIDGET_TYPES', $builtin));
    }

    /**
     * Create widget instance by type.
     */
    public static function createByType(string $type, MetainfoField $field): ?MetainfoWidgetInterface
    {
        $widgetClass = self::getRegisteredTypes()[$type]['class'] ?? null;

        if (!$widgetClass || !class_exists($widgetClass)) {
            return null;
        }

        $widget = new $widgetClass($field);

        return $widget instanceof MetainfoWidgetInterface ? $widget : null;
    }

    /**
     * Validate and normalize input value before saving.
     * @return mixed Normalized value (or null to remove field)
     */
    public function normalizeValue(mixed $value): mixed
    {
        return $value;
    }
}
