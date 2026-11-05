<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Gemeinsame Bildaufbereitung fuer alle KI-Bildverstaendnis-Aufrufe
 * (AiAltTextService, AiAutoTagService): SVG-Rasterisierung (der Client liefert
 * bereits ein clientseitig auf Canvas gerendertes PNG, siehe
 * rasterizeSvgToPngDataUrl() in modules/ai_alt.js -- kein serverseitiger
 * Rasterizer vorhanden) sowie Downscale ueberdimensionierter Raster-Bilder
 * vor dem Versand (spart Traffic/Zeit/Kosten beim KI-Anbieter, konfigurierbare
 * maximale Kantenlaenge, Original bleibt unangetastet).
 *
 * resolve() liefert IMMER einen nutzbaren Dateipfad. Ob es sich um eine
 * temporaere Kopie handelt (die der Aufrufer per cleanup() wieder loeschen
 * muss) steht im "temp"-Flag -- Aufrufer-Muster:
 *
 *   $prepared = AiImagePreparer::resolve($media, $rasterizedImageData);
 *   try {
 *       // ... $prepared['path'] verwenden
 *   } finally {
 *       AiImagePreparer::cleanup($prepared);
 *   }
 */
final class AiImagePreparer
{
    /**
     * @return array{path: string, temp: bool}
     */
    public static function resolve(\rex_media $media, ?string $rasterizedImageData): array
    {
        $isSvg = 'svg' === strtolower(pathinfo($media->getFileName(), PATHINFO_EXTENSION));
        if ($isSvg && null === $rasterizedImageData) {
            // SVG ist ein Vektorformat -- ai_platform::understandImage() reicht
            // die Datei sonst ungeprueft als image/svg+xml-Data-URL an die
            // Vision-API durch, was dort meist mit einer leeren/fehlerhaften
            // Antwort scheitert. Gleicher Ausschluss wie in filepond_uploader.
            throw new \rex_exception('SVG wird für KI-Analyse nicht unterstützt (Vektorformat ohne verlässliches Pixel-Rendering).');
        }

        if (null !== $rasterizedImageData) {
            return ['path' => self::writeTempImageFromDataUrl($rasterizedImageData), 'temp' => true];
        }

        $sourcePath = \rex_path::media($media->getFileName());
        if (!is_file($sourcePath)) {
            throw new \rex_exception('Bilddatei nicht auf dem Dateisystem gefunden: ' . $media->getFileName());
        }

        // Nicht-Webformate (TIFF, BMP, HEIC, AVIF, PSD …) versteht die Vision-API meist nicht – und unverkleinert als
        // Base64 sprengen große Dateien Speicher/Zeitlimit. Daher vorher in ein verkleinertes JPEG umwandeln; geht das
        // nicht, sauber abbrechen (der Aufrufer überspringt und markiert die Datei).
        $extension = strtolower(pathinfo($media->getFileName(), PATHINFO_EXTENSION));
        if (!in_array($extension, self::WEB_FORMATS, true)) {
            return ['path' => self::convertToJpeg($sourcePath, $extension), 'temp' => true];
        }

        $downscaled = self::downscaleIfNeeded($sourcePath);
        if (null !== $downscaled) {
            return ['path' => $downscaled, 'temp' => true];
        }
        if (filesize($sourcePath) > self::MAX_SOURCE_BYTES) {
            // GD konnte nicht verkleinern (z. B. CMYK, beschädigt) – Imagick versuchen statt riesiger Base64-Last
            return ['path' => self::convertToJpeg($sourcePath, $extension), 'temp' => true];
        }

        return ['path' => $sourcePath, 'temp' => false];
    }

    /** Formate, die die Vision-APIs direkt annehmen */
    public const WEB_FORMATS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Größere Originale werden nie unverändert gesendet */
    private const MAX_SOURCE_BYTES = 8 * 1024 * 1024;

    /** Kann diese Datei für die KI aufbereitet werden (Webformat oder umwandelbar)? */
    public static function canPrepare(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, self::WEB_FORMATS, true) || 'svg' === $extension) {
            return true;
        }
        if (class_exists(\Imagick::class)) {
            try {
                return in_array(strtoupper('jpg' === $extension ? 'jpeg' : ('tif' === $extension ? 'tiff' : $extension)), \Imagick::queryFormats(), true);
            } catch (\Throwable) {
                return false;
            }
        }

        return in_array($extension, ['bmp', 'avif'], true) && extension_loaded('gd');
    }

    /** Erstes Bild/erste Ebene → sRGB-JPEG mit höchstens der konfigurierten Kantenlänge (Imagick, sonst GD) */
    private static function convertToJpeg(string $sourcePath, string $extension): string
    {
        $max = self::resolveMaxImageDimension();
        if (class_exists(\Imagick::class)) {
            try {
                $image = new \Imagick();
                $image->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
                $image->readImage($sourcePath . '[0]'); // erste Seite/Ebene (mehrseitige TIFF, PSD)
                if (method_exists($image, 'transformImageColorspace')) {
                    $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB); // CMYK-Druckdaten
                }
                $image->setImageBackgroundColor('white');
                $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $image->thumbnailImage($max, $max, true);
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality(85);
                $blob = $image->getImagesBlob();
                $image->clear();
                if ('' !== $blob) {
                    return self::writeTempFile($blob, 'jpg');
                }
            } catch (\Throwable $e) {
                throw new \rex_exception('Format „' . strtoupper($extension) . '“ konnte nicht für die KI umgewandelt werden (' . $e->getMessage() . ').');
            }
        }
        $gd = extension_loaded('gd') ? @imagecreatefromstring((string) \rex_file::get($sourcePath)) : false;
        if (false !== $gd) {
            $w = imagesx($gd);
            $h = imagesy($gd);
            $scale = min(1, $max / max($w, $h, 1));
            $resized = $scale < 1 ? imagescale($gd, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))) : $gd;
            ob_start();
            imagejpeg($resized ?: $gd, null, 85);
            $blob = (string) ob_get_clean();
            if ('' !== $blob) {
                return self::writeTempFile($blob, 'jpg');
            }
        }
        throw new \rex_exception('Format „' . strtoupper($extension) . '“ wird für die KI nicht unterstützt (kein Imagick zum Umwandeln) – übersprungen.');
    }

    /**
     * @param array{path: string, temp: bool} $prepared
     */
    public static function cleanup(array $prepared): void
    {
        if ($prepared['temp'] && is_file($prepared['path'])) {
            \rex_file::delete($prepared['path']);
        }
    }

    private static function resolveMaxImageDimension(): int
    {
        $value = (int) \rex_config::get('mediaplace', 'ai_alt_max_image_dimension', 1024);

        return max(256, min(2048, $value));
    }

    /**
     * Verkleinert das Bild auf die konfigurierte maximale Kantenlaenge, falls
     * es diese ueberschreitet. Gibt null zurueck, wenn kein Downscale noetig
     * war ODER GD das Format nicht lesen kann -- in beiden Faellen nutzt der
     * Aufrufer einfach die Originaldatei weiter (best effort, kein harter
     * Fehler: GD deckt die in der Praxis relevanten Formate JPEG/PNG/GIF/WEBP
     * ab, ein selteneres Format soll die Generierung nicht komplett
     * blockieren -- anders als bei SVG gibt es hier keinen bekannten Grund,
     * warum die KI mit der Originaldatei nichts anfangen koennte).
     */
    private static function downscaleIfNeeded(string $sourcePath): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $maxDimension = self::resolveMaxImageDimension();
        $size = @getimagesize($sourcePath);
        if (false === $size || ($size[0] <= $maxDimension && $size[1] <= $maxDimension)) {
            return null;
        }

        $imageData = \rex_file::get($sourcePath);
        if (!is_string($imageData) || '' === $imageData) {
            return null;
        }

        $image = @imagecreatefromstring($imageData);
        if (false === $image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $ratio = $width / $height;
        if ($width >= $height) {
            $targetWidth = $maxDimension;
            $targetHeight = max(1, (int) round($maxDimension / $ratio));
        } else {
            $targetHeight = $maxDimension;
            $targetWidth = max(1, (int) round($maxDimension * $ratio));
        }

        $resized = imagescale($image, $targetWidth, $targetHeight);
        if (false === $resized) {
            return null;
        }

        // PNG/GIF bleiben verlustfrei (Transparenz, Logos mit scharfen
        // Kanten), alles andere wird als JPEG kodiert -- gleiche Aufteilung
        // wie filepond_uploader::prepareImageWithGd().
        $mime = (string) ($size['mime'] ?? '');
        $keepPng = in_array($mime, ['image/png', 'image/gif'], true);

        ob_start();
        if ($keepPng) {
            imagepng($resized, null, 6);
        } else {
            imagejpeg($resized, null, 85);
        }
        $encoded = ob_get_clean();
        if (!is_string($encoded) || '' === $encoded) {
            return null;
        }

        return self::writeTempFile($encoded, $keepPng ? 'png' : 'jpg');
    }

    private static function writeTempImageFromDataUrl(string $dataUrl): string
    {
        if (1 !== preg_match('/^data:image\/png;base64,(.+)$/', $dataUrl, $matches)) {
            throw new \rex_exception('Ungültiges Bildformat für die KI-Analyse erhalten.');
        }
        $binary = base64_decode($matches[1], true);
        if (false === $binary || '' === $binary) {
            throw new \rex_exception('Bilddaten konnten nicht dekodiert werden.');
        }
        // Client-seitig ist die Canvas-Ausgabe bereits auf eine sinnvolle
        // Kantenlaenge begrenzt (siehe rasterizeSvgToPngDataUrl() in
        // ai_alt.js) -- diese Grenze ist nur ein zusaetzliches serverseitiges
        // Sicherheitsnetz gegen unerwartet grosse Payloads.
        if (strlen($binary) > 8 * 1024 * 1024) {
            throw new \rex_exception('Gerendertes Bild ist zu groß.');
        }

        return self::writeTempFile($binary, 'png');
    }

    private static function writeTempFile(string $binary, string $extension): string
    {
        $tempPath = \rex_path::addonData('mediaplace', 'ai_tmp_' . bin2hex(random_bytes(8)) . '.' . $extension);
        \rex_dir::create(dirname($tempPath));
        if (!\rex_file::put($tempPath, $binary)) {
            throw new \rex_exception('Temporäre Bilddatei konnte nicht geschrieben werden.');
        }

        return $tempPath;
    }
}
