<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Represents a single metainfo field definition.
 * Holds metadata about how to render and validate the field.
 */
class MetainfoField
{
    public function __construct(
        private int $id,
        private string $key,
        private string $label,
        private string $widgetType,
        private array $options = [],
        private bool $translatable = false,
        private bool $imageOnly = false,
    ) {}

    public function getId(): int { return $this->id; }
    public function getKey(): string { return $this->key; }
    public function getLabel(): string { return $this->label; }
    public function getWidgetType(): string { return $this->widgetType; }
    public function getOptions(): array { return $this->options; }
    public function isTranslatable(): bool { return $this->translatable; }
    public function isImageOnly(): bool { return $this->imageOnly; }

    /**
     * Check if field should be shown for this media file.
     */
    public function isVisibleForMedia(\rex_media $media): bool
    {
        if (!$this->imageOnly) {
            return true;
        }
        // Only show for image files
        return in_array($media->getExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
    }

    /**
     * Get widget instance for this field.
     */
    public function createWidget(): ?MetainfoWidgetInterface
    {
        return MetainfoWidget::createByType($this->widgetType, $this);
    }
}
