<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Vertrag fuer ein Metainfo-Widget, das ueber den Erweiterungspunkt
 * MEDIAPLACE_WIDGET_TYPES (siehe MetainfoWidget::getRegisteredTypes())
 * registriert wird. Bewusst nur diese eine Methode -- externe Addons sollen
 * nicht von unserer abstrakten MetainfoWidget-Klasse erben muessen, nur
 * diesen Vertrag erfuellen. Die registrierte Klasse braucht ausserdem einen
 * Konstruktor, der ein MetainfoField entgegennimmt (siehe
 * MetainfoWidget::__construct()), da createByType() so instanziiert.
 */
interface MetainfoWidgetInterface
{
    /**
     * Validate and normalize input value before saving.
     * @return mixed Normalized value (or null to remove field)
     */
    public function normalizeValue(mixed $value): mixed;
}
