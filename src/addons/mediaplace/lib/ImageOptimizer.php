<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * "Bild optimieren"-Button im Detail-Panel: resized ein bereits gespeichertes
 * Bild, dessen Breite/Hoehe die konfigurierten Upload-Resize-Grenzen
 * (Einstellungen -> "Bilder beim Upload verkleinern") ueberschreitet, in-place
 * auf diese Grenzen -- fuer Bestandsdateien, die vor Aktivierung dieses
 * Schalters hochgeladen wurden (oder ihn sonstwie umgangen haben). Nutzt
 * bewusst dieselbe Einstellung wie die Upload-Verkleinerung, keine eigene
 * zweite Schwelle -- der Button ist deshalb nur sichtbar, wenn
 * "enable_upload_resize" aktiv ist.
 *
 * Anders als bei ffmpeg's Video-Optimieren (asynchroner Job + eigene
 * Fingerprint-Registry, siehe FfmpegIntegration) reicht hier ein einzelner
 * synchroner Request: GD-Resize ist auch bei grossen Bildern deutlich unter
 * 1s schnell, und "bereits optimiert" ist verlustfrei aus dem aktuellen
 * Zustand ableitbar (Breite/Hoehe <= Grenzwerte) -- keine Registry noetig,
 * der Button verschwindet einfach von selbst.
 *
 * Resize + Speichern nutzt konsequent REDAXO-Kernmechanismen statt eigener
 * Bildverarbeitung: rex_effect_resize (Media-Manager-Kern) fuer die
 * Fit-Berechnung inkl. Transparenz-Erhalt, rex_media_service::updateMedia()
 * fuer das eigentliche Ersetzen der Datei (uebernimmt Verschieben,
 * MIME-Validierung, DB-Update von width/height/filesize, Cache-Invalidierung
 * und das Feuern von MEDIA_UPDATED -- exakt derselbe Weg, den auch ein
 * normales "Datei ersetzen" im Medienpool nutzt).
 */
class ImageOptimizer
{
    /**
     * GD-sicher kodierbare Formate. Kein GIF (Animation ginge bei einem
     * GD-Resize verloren, GD liest ohnehin nur den ersten Frame), kein SVG
     * (Vektor, "zu gross" ergibt hier keinen Sinn) -- gleiche Ausschlussliste
     * wie die client-seitige isResizableImageType() in mediaplace-helpers.js.
     */
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function isEnabled(): bool
    {
        return (bool) \rex_addon::get('mediaplace')->getConfig('enable_upload_resize', false);
    }

    public static function isSupportedType(string $filename): bool
    {
        if ('' === $filename) {
            return false;
        }
        $extension = strtolower(\rex_file::extension($filename));

        return \in_array($extension, self::SUPPORTED_EXTENSIONS, true);
    }

    /**
     * @return array{width: int, height: int}
     */
    public static function getTargetSize(): array
    {
        $addon = \rex_addon::get('mediaplace');

        return [
            'width' => max(1, (int) $addon->getConfig('upload_resize_width', 2000)),
            'height' => max(1, (int) $addon->getConfig('upload_resize_height', 2000)),
        ];
    }

    public static function isOversized(\rex_media $media): bool
    {
        $target = self::getTargetSize();

        return $media->getWidth() > $target['width'] || $media->getHeight() > $target['height'];
    }

    /**
     * Steuert die Sichtbarkeit des "Bild optimieren"-Buttons im Detail-Panel.
     */
    public static function canOptimize(string $filename): bool
    {
        if (!self::isEnabled() || !self::isSupportedType($filename)) {
            return false;
        }

        $media = \rex_media::get($filename);
        if (!$media || !self::isOversized($media)) {
            return false;
        }

        $user = \rex::getUser();

        return $user instanceof \rex_user && $user->hasPerm('mediaplace[optimize_image]');
    }

    /**
     * @return array{width: int, height: int, filesize: int}
     */
    public static function optimize(string $filename): array
    {
        $media = \rex_media::get($filename);
        if (!$media) {
            throw new \rex_exception('Media not found: ' . $filename);
        }

        $target = self::getTargetSize();
        $managedMedia = new \rex_managed_media(\rex_path::media($filename));

        $effect = new \rex_effect_resize();
        $effect->setMedia($managedMedia);
        $effect->setParams([
            'width' => $target['width'],
            'height' => $target['height'],
            'style' => 'maximum',
            'allow_enlarge' => 'not_enlarge',
        ]);
        $effect->execute();

        $tmpFile = \rex_path::addonData('mediaplace', 'optimize_tmp_' . uniqid('', true) . '.' . strtolower(\rex_file::extension($filename)));
        \rex_dir::create(dirname($tmpFile));

        $format = strtolower(\rex_file::extension($filename));
        $format = 'jpeg' === $format ? 'jpg' : $format;
        $mediaManagerAddon = \rex_addon::get('media_manager');
        $gdImage = $managedMedia->getImage();

        $written = match ($format) {
            'jpg' => imagejpeg($gdImage, $tmpFile, (int) $mediaManagerAddon->getConfig('jpg_quality', 85)),
            'png' => imagepng($gdImage, $tmpFile, (int) $mediaManagerAddon->getConfig('png_compression', 6)),
            'webp' => imagewebp($gdImage, $tmpFile, (int) $mediaManagerAddon->getConfig('webp_quality', 80)),
            default => false,
        };

        if (!$written || !is_file($tmpFile)) {
            @unlink($tmpFile);
            throw new \rex_exception('Could not encode optimized image for ' . $filename);
        }

        try {
            $result = \rex_media_service::updateMedia($filename, [
                'title' => $media->getTitle(),
                'category_id' => $media->getCategoryId(),
                'file' => [
                    'name' => $filename,
                    'tmp_name' => $tmpFile,
                ],
            ]);
        } finally {
            // updateMedia() verschiebt (rex_file::move()) die tmp-Datei bei
            // Erfolg an ihren Zielort -- existiert sie danach noch (Fehlerfall
            // vor dem Verschieben), wird sie hier aufgeraeumt.
            if (is_file($tmpFile)) {
                @unlink($tmpFile);
            }
        }

        if (empty($result['ok'])) {
            throw new \rex_exception((string) ($result['msg'] ?? 'Optimizing failed'));
        }

        \rex_media_manager::deleteCache(pathinfo($filename, PATHINFO_FILENAME));

        $updatedMedia = \rex_media::get($filename);

        return [
            'width' => $updatedMedia ? $updatedMedia->getWidth() : $target['width'],
            'height' => $updatedMedia ? $updatedMedia->getHeight() : $target['height'],
            'filesize' => $updatedMedia ? $updatedMedia->getSize() : 0,
        ];
    }
}
