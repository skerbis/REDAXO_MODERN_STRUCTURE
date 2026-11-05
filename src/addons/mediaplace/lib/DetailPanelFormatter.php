<?php

namespace FriendsOfRedaxo\Mediaplace;

/**
 * Kleine Formatierungs-Helfer fuer das serverseitig gerenderte Detail-Panel
 * (fragments/mediaplace/detail_*.php). Spiegeln bewusst 1:1 die
 * frueheren JS-Aequivalente in assets/mediaplace.js (formatBytes/formatDate/
 * fileIcon/mediaThumbSrc/getReplacementAcceptForFilename), damit sich das
 * Erscheinungsbild durch die Verlagerung nach PHP nicht aendert.
 */
class DetailPanelFormatter
{
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, '.', '') . ' KB';
        }
        return number_format($bytes / 1048576, 1, '.', '') . ' MB';
    }

    /**
     * @param int|string|null $raw Unix-Timestamp (so liefert es media/{filename}/info
     *                              des api-Addons, siehe rex_media::getCreateDate()/
     *                              getUpdateDate()) oder ein Datums-String.
     */
    public static function formatDate(int|string|null $raw): string
    {
        if (!$raw) {
            return '–';
        }
        $ts = is_int($raw) || ctype_digit((string) $raw) ? (int) $raw : strtotime((string) $raw);
        if (false === $ts || $ts <= 0) {
            return (string) $raw;
        }
        return date('d.m.Y H:i', $ts);
    }

    public static function isVideoFilename(string $filename): bool
    {
        return (bool) preg_match('/\.(mp4|webm|ogv|ogg|mov)$/i', $filename);
    }

    public static function isAudioFilename(string $filename): bool
    {
        return (bool) preg_match('/\.(mp3|wav|ogg|flac|aac|m4a)$/i', $filename);
    }

    public static function isImageFilename(string $filename): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp|svg|avif|bmp)$/i', $filename);
    }

    public static function isPdfFilename(string $filename): bool
    {
        return (bool) preg_match('/\.pdf$/i', $filename);
    }

    public static function fileIconClass(string $filename): string
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $icons = [
            'pdf' => 'fa-file-pdf',
            'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
            'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel',
            'ppt' => 'fa-file-powerpoint', 'pptx' => 'fa-file-powerpoint',
            'zip' => 'fa-file-zipper', 'rar' => 'fa-file-zipper', 'gz' => 'fa-file-zipper',
            'mp3' => 'fa-file-audio', 'wav' => 'fa-file-audio', 'ogg' => 'fa-file-audio', 'flac' => 'fa-file-audio',
            'mp4' => 'fa-file-video', 'avi' => 'fa-file-video', 'mov' => 'fa-file-video', 'webm' => 'fa-file-video',
            'txt' => 'fa-file-lines', 'csv' => 'fa-file-csv', 'log' => 'fa-file-lines',
            'html' => 'fa-file-code', 'css' => 'fa-file-code', 'js' => 'fa-file-code',
            'json' => 'fa-file-code', 'xml' => 'fa-file-code', 'php' => 'fa-file-code',
        ];
        return 'fa-solid ' . ($icons[$ext] ?? 'fa-file');
    }

    /**
     * Datei-Endung fuer das accept-Attribut des Ersetzen-Inputs (nur dieselbe
     * Endung wie das Original ist erlaubt, siehe extensionsCompatible() in JS).
     */
    public static function replacementAccept(string $filename): string
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if ('jpeg' === $ext || 'jpe' === $ext) {
            $ext = 'jpg';
        }
        if ('' === $ext) {
            return '';
        }
        if ('jpg' === $ext) {
            return '.jpg,.jpeg';
        }
        return '.' . $ext;
    }

    /**
     * Media-Manager-Thumbnail-URL inkl. Cache-Buster (updatedate, sonst
     * Dateigroesse) -- SVGs koennen nicht zuverlaessig durch den Media
     * Manager gejagt werden, siehe mediaThumbSrc() in mediaplace.js. Nutzt
     * rex_url::media()/backendController() statt fest kodierter "/media/"-
     * bzw. "index.php?..."-Pfade, damit Installationen in einem Unterordner
     * (siehe https://redaxo.org/doku/5.x/pfade) nicht brechen.
     */
    public static function mediaThumbUrl(string $filename, string $mmType, ?string $updatedate, int $filesize): string
    {
        $token = $updatedate ?: (string) $filesize;

        if (preg_match('/\.svg$/i', $filename)) {
            $url = \rex_url::media(rawurlencode($filename));
            return $token ? $url . '?mpv=' . rawurlencode($token) : $url;
        }

        $url = \rex_url::backendController(['rex_media_type' => $mmType, 'rex_media_file' => $filename], false);
        return $token ? $url . '&mpv=' . rawurlencode($token) : $url;
    }

    /**
     * URL auf die Original-Mediendatei im media/-Ordner (Video-/Audio-
     * Vorschau, "Datei ersetzen"-Kompatibilitaetscheck betrifft das nicht).
     */
    public static function mediaFileUrl(string $filename, ?string $updatedate, int $filesize): string
    {
        $token = $updatedate ?: (string) $filesize;
        $url = \rex_url::media(rawurlencode($filename));
        return $token ? $url . '?mpv=' . rawurlencode($token) : $url;
    }
}
